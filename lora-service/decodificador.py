"""
Decodificador del protocolo binario Aguape v1 (primera versión del lora-service).

Cambios (nov. 2026, RF01-RF04): cada lectura decodificada se convierte a las variables de
Laravel y se envía a POST /api/ingesta (token + firma HMAC) desde un hilo aparte. Las que
no se pueden enviar (Laravel caído, sin red) se guardan en pendientes.jsonl y se reintentan.
Las variables que necesitan calibración (altura de pasto, nivel de agua, pH, turbidez,
conductividad, humedad del suelo) se envían solo si el nodo tiene su entrada en
calibracion.json (ver calibracion.ejemplo.json). La trama 0x02 de agua pasa a campos con nombre.

Cambios (nov. 2026, CU-06): configuración remota del intervalo. Un hilo consulta
GET /api/configuracion cada 5 minutos (mismo token y firma HMAC que la ingesta); cuando
llega una lectura con la bandera ESCUCHA y su cfg_version no coincide con la vigente, se
responde en el acto con una trama 0x20 de configuración. Sin CONFIGURACION_URL no se
envía nada (útil en el banco, para que los nodos sigan con su intervalo de prueba).

Cambios (nov. 2026): trama 0x03 ambiental del Nodo 3 (nodo_ambiental v1.0) con los
campos definitivos; lluvia del intervalo calculada con el contador acumulado de
basculadas; columnas ambientales en el CSV (el CSV con columnas anteriores se archiva);
aviso de posible NODO_ID duplicado.

Lee las tramas del E220 conectado al UART de la Raspberry Pi (/dev/ttyAMA0),
las decodifica y guarda:
  - log_crudo.txt   : todas las líneas con fecha y hora (sirve para reproducir la prueba)
  - lecturas.csv    : una fila por trama decodificada
y muestra un resumen de métricas cada 10 minutos y al terminar (Ctrl+C).

Uso:
  python3 decodificador.py /dev/ttyAMA0 --salida /var/lib/aguape     # en vivo
  python3 decodificador.py --archivo log_crudo.txt --salida datos/banco   # reproducir

Variables de entorno (opcionales; sin ellas solo se registra en CSV, como en el banco):
  INGESTA_URL         p. ej. http://localhost/api/ingesta
  CONFIGURACION_URL   p. ej. http://localhost/api/configuracion
  INGESTA_TOKEN, INGESTA_HMAC_SECRET   los mismos del .env de Laravel
  CALIBRACION         ruta de calibracion.json (por defecto, junto a este archivo)

Trama 0x20 — Configuración (gateway -> nodo, 9 bytes, little-endian):
  0-5  encabezado (version, tipo 0x20, nodo destino, secuencia del gateway, banderas 0)
  6    cfg_version     uint8   1..255 (0 = sin configuración remota)
  7-8  intervalo_min   uint16  minutos entre transmisiones (15..120)

Requiere: python3-serial (apt) en la Raspberry Pi
"""

import argparse
import csv
import datetime
import hashlib
import hmac
import json
import re
import struct
import os
import sys
import threading
import time
import queue
import urllib.error
import urllib.request
from collections import Counter

PROTO_VERSION = 1
ESTADOS_PASTURA = {0: "OK", 1: "SIN_ECO", 2: "ZONA_CIEGA", 3: "FUERA_ESCALA", 255: "SIN_MEDICION"}
BANDERAS = {0x01: "ARRANQUE", 0x02: "BATERIA_CRITICA", 0x04: "CFG_RECHAZADA", 0x08: "ESCUCHA"}
FALLAS_AMBIENTAL = {0x01: "TEMP", 0x02: "HUM", 0x04: "SUELO", 0x08: "LUZ", 0x10: "LLUVIA"}
FALLAS_AGUA = {0x01: "NIVEL", 0x02: "TEMP", 0x04: "PH", 0x08: "TURBIDEZ", 0x10: "CONDUCTIVIDAD"}
MM_POR_BASCULADA = 0.2794          # valor de la librería del SEN0575; reemplazar por el calibrado
MAX_BASCULADAS_INTERVALO = 1000    # ~280 mm en un intervalo: por encima, el contador se reinició
COLUMNAS_CSV = ["fecha_hora", "rssi_dbm", "nodo", "tipo", "secuencia", "banderas",
                "bateria_mv", "cfg_version", "estado", "distancia_mm",
                "firmware", "motivo_reinicio",
                "temp_aire_c", "hum_aire_pct", "suelo_mv", "luz_lx", "basculadas", "lluvia_mm",
                "temp_agua_c", "ph_mv", "turbidez_mv", "tds_mv",
                "hex"]
RE_RX = re.compile(r"\|(-\d{1,3})\|((?:[0-9A-Fa-f]{2}){6,})\s*$")   # |<rssi>|<hex>: tolera el comienzo dañado
RE_HB = re.compile(r"^(?:#?H?B?\|)?\d{3,}\s*$")   # #HB|<ms>, aunque haya perdido parte o todo el prefijo
RESUMEN_CADA_S = 600
TIPO_CONFIG = 0x20
BAND_CFG_RECHAZADA = 0x04
BAND_ESCUCHA = 0x08
CONSULTA_CONFIG_CADA_S = 300
REINTENTO_PENDIENTES_S = 60
# Batería 2S de litio: el firmware corta a 3,2 V por celda (6400 mV) y por debajo de
# 3000 mV no hay batería (USB o switch apagado).
BAT_VACIA_MV, BAT_LLENA_MV, BAT_AUSENTE_MV = 6400, 8400, 3000
LUX_A_W_M2 = 0.0079   # luz solar: ~127 lm/W. Aproximación; el BH1750 no mide radiación.

# --------------------------------------------------------------------------
# Decodificación
# --------------------------------------------------------------------------
def nombres_banderas(b):
    return "+".join(n for bit, n in BANDERAS.items() if b & bit) or "-"


def decodificar(datos):
    """Devuelve un dict con los campos de la trama, o lanza ValueError."""
    if len(datos) < 6:
        raise ValueError(f"longitud {len(datos)} < 6")
    version, tipo, nodo, secuencia, banderas = struct.unpack("<BBBHB", datos[:6])
    if version != PROTO_VERSION:
        raise ValueError(f"versión {version} desconocida")
    t = {"tipo": tipo, "nodo": nodo, "secuencia": secuencia, "banderas": banderas}
    cuerpo = datos[6:]

    if tipo == 0x01:                                    # lectura de pastura
        if len(cuerpo) != 6:
            raise ValueError(f"pastura: cuerpo de {len(cuerpo)} bytes, se esperaban 6")
        bat, cfg, estado, dist = struct.unpack("<HBBH", cuerpo)
        t.update(nombre="PASTURA", bateria_mv=bat, cfg_version=cfg,
                 estado=ESTADOS_PASTURA.get(estado, f"DESCONOCIDO_{estado}"), distancia_mm=dist)
    elif tipo == 0x10:                                  # arranque
        if len(cuerpo) != 6:
            raise ValueError(f"arranque: cuerpo de {len(cuerpo)} bytes, se esperaban 6")
        bat, cfg, mayor, menor, motivo = struct.unpack("<HBBBB", cuerpo)
        t.update(nombre="ARRANQUE", bateria_mv=bat, cfg_version=cfg,
                 firmware=f"{mayor}.{menor}", motivo_reinicio=motivo)
    elif tipo == 0x03:                                  # lectura ambiental (nodo_ambiental v1.0)
        if len(cuerpo) != 14:
            raise ValueError(f"ambiental: cuerpo de {len(cuerpo)} bytes, se esperaban 14")
        bat, cfg, estado, temp_c100, hum_x10, suelo_mv, luz_div2, basc = struct.unpack("<HBBhHHHH", cuerpo)
        if estado == 0xFF:                              # batería crítica: no se midió
            t.update(nombre="AMBIENTAL", bateria_mv=bat, cfg_version=cfg, estado="SIN_MEDICION")
        else:
            fallas = [n for bit, n in FALLAS_AMBIENTAL.items() if estado & bit]
            t.update(nombre="AMBIENTAL", bateria_mv=bat, cfg_version=cfg,
                     estado="OK" if not fallas else "FALLA_" + "+".join(fallas),
                     temp_aire_c=None if estado & 0x01 else temp_c100 / 100,
                     hum_aire_pct=None if estado & 0x02 else hum_x10 / 10,
                     suelo_mv=None if estado & 0x04 else suelo_mv,
                     luz_lx=None if estado & 0x08 else luz_div2 * 2,
                     basculadas=None if estado & 0x10 else basc)
    elif tipo == 0x02:                                  # lectura de agua (nodo_agua v1.0)
        if len(cuerpo) != 14:
            raise ValueError(f"agua: cuerpo de {len(cuerpo)} bytes, se esperaban 14")
        bat, cfg, estado, dist, temp_c100, ph_mv, turb_mv, tds_mv = struct.unpack("<HBBHhHHH", cuerpo)
        if estado == 0xFF:                              # batería crítica: no se midió
            t.update(nombre="AGUA", bateria_mv=bat, cfg_version=cfg, estado="SIN_MEDICION")
        else:
            fallas = [n for bit, n in FALLAS_AGUA.items() if estado & bit]
            t.update(nombre="AGUA", bateria_mv=bat, cfg_version=cfg,
                     estado="OK" if not fallas else "FALLA_" + "+".join(fallas),
                     distancia_mm=None if estado & 0x01 else dist,
                     temp_agua_c=None if estado & 0x02 else temp_c100 / 100,
                     ph_mv=None if estado & 0x04 else ph_mv,
                     turbidez_mv=None if estado & 0x08 else turb_mv,
                     tds_mv=None if estado & 0x10 else tds_mv)
    else:
        raise ValueError(f"tipo {tipo:#04x} desconocido")
    return t


# --------------------------------------------------------------------------
# Conversión a las variables de Laravel (RF01, RF02)
# --------------------------------------------------------------------------
def lineal(mv, puntos):
    """Recta por dos puntos de calibración [[mv1, valor1], [mv2, valor2]]."""
    (x1, y1), (x2, y2) = puntos
    return y1 + (mv - x1) * (y2 - y1) / (x2 - x1)


def nivel_bateria(mv):
    if mv is None or mv < BAT_AUSENTE_MV:
        return None                     # sin batería: no es un 0 %
    return round(min(100.0, max(0.0, 100 * (mv - BAT_VACIA_MV) / (BAT_LLENA_MV - BAT_VACIA_MV))), 1)


def conductividad(tds_mv, temp_agua_c, k):
    """µS/cm con la fórmula del sensor TDS de DFRobot, compensada a 25 °C."""
    v = tds_mv / 1000
    if temp_agua_c is not None:
        v /= 1 + 0.02 * (temp_agua_c - 25)
    return (133.42 * v ** 3 - 255.86 * v ** 2 + 857.39 * v) * k


def payload_ingesta(t, cal, medido_en):
    """Cuerpo de POST /api/ingesta para la trama `t`, o None si no hay nada que enviar.

    `cal` es la entrada del nodo en calibracion.json ({} si no tiene): las variables que
    dependen de una calibración faltante no se envían.
    """
    if t["tipo"] not in (0x01, 0x02, 0x03):             # arranque: no trae mediciones
        return None
    lecturas = []

    def agregar(variable, valor, decimales=2):
        if valor is not None:
            lecturas.append({"variable": variable, "valor": round(valor, decimales)})

    if t["tipo"] == 0x01 and t["estado"] == "OK" and "altura_montaje_mm" in cal:
        agregar("altura_pastura", (cal["altura_montaje_mm"] - t["distancia_mm"]) / 10, 1)
    elif t["tipo"] == 0x03:
        agregar("temperatura_aire", t.get("temp_aire_c"))
        agregar("humedad_aire", t.get("hum_aire_pct"), 1)
        agregar("precipitacion", t.get("lluvia_mm"))
        if t.get("luz_lx") is not None:
            agregar("radiacion_solar", t["luz_lx"] * LUX_A_W_M2, 1)
        if t.get("suelo_mv") is not None and "suelo" in cal:
            seco, saturado = cal["suelo"]["mv_seco"], cal["suelo"]["mv_saturado"]
            agregar("humedad_suelo", min(100.0, max(0.0, 100 * (seco - t["suelo_mv"]) / (seco - saturado))), 1)
    elif t["tipo"] == 0x02:
        agregar("temperatura_agua", t.get("temp_agua_c"))
        if t.get("distancia_mm") is not None and "altura_montaje_mm" in cal:
            agregar("nivel_agua", (cal["altura_montaje_mm"] - t["distancia_mm"]) / 10, 1)
        if t.get("ph_mv") is not None and "ph" in cal:
            agregar("ph_agua", lineal(t["ph_mv"], cal["ph"]["puntos"]))
        if t.get("turbidez_mv") is not None and "turbidez" in cal:
            agregar("turbidez_agua", max(0.0, lineal(t["turbidez_mv"], cal["turbidez"]["puntos"])), 1)
        if t.get("tds_mv") is not None and "tds" in cal:
            agregar("conductividad_agua", conductividad(t["tds_mv"], t.get("temp_agua_c"), cal["tds"].get("k", 1.0)), 1)

    bateria = nivel_bateria(t.get("bateria_mv"))
    if not lecturas and bateria is None:
        return None
    return {"codigo": f"N-{t['nodo']}", "medido_en": medido_en, "nivel_bateria": bateria, "lecturas": lecturas}


def cargar_calibracion(ruta):
    if not os.path.exists(ruta):
        print(f"Sin {ruta}: no se envían las variables que necesitan calibración")
        return {}
    with open(ruta, encoding="utf-8") as f:
        return {int(nodo): datos for nodo, datos in json.load(f).items() if not nodo.startswith("_")}


def firmar_cabeceras(cuerpo, token, secreto):
    """Mismas cabeceras que valida Laravel: HMAC-SHA256 de "{timestamp}.{cuerpo}"."""
    timestamp = str(int(time.time()))
    firma = hmac.new(secreto.encode(), f"{timestamp}.".encode() + cuerpo, hashlib.sha256).hexdigest()
    return {"Accept": "application/json", "Authorization": f"Bearer {token}",
            "X-Ingesta-Timestamp": timestamp, "X-Ingesta-Signature": firma}


class EnvioIngesta:
    """Envía las lecturas a POST /api/ingesta en un hilo aparte.

    Un envío que falla por red o por un error 5xx se guarda en pendientes.jsonl y se
    reintenta cada minuto, en orden, con su `medido_en` original (Laravel no duplica:
    la lectura se identifica por nodo, variable y fecha). Un 4xx (nodo no registrado,
    inactivo o datos inválidos) no se reintenta: se avisa y se descarta.
    """

    def __init__(self, url, token, secreto, ruta_pendientes):
        self.url, self.token, self.secreto = url, token, secreto
        self.ruta_pendientes = ruta_pendientes
        self.cola = queue.Queue()
        self.enviadas = self.rechazadas = 0
        threading.Thread(target=self._trabajar, daemon=True).start()

    def enviar(self, payload):
        self.cola.put(payload)

    def _trabajar(self):
        ultimo_reintento = 0
        while True:
            try:
                payload = self.cola.get(timeout=REINTENTO_PENDIENTES_S)
                if self._hay_pendientes() or not self._post(payload):
                    self._guardar_pendiente(payload)     # se conserva el orden de llegada
            except queue.Empty:
                pass
            if time.time() - ultimo_reintento >= REINTENTO_PENDIENTES_S:
                ultimo_reintento = time.time()
                self._reintentar_pendientes()

    def _post(self, payload):
        """True si quedó resuelto (enviado o rechazado por Laravel); False si hay que reintentar."""
        cuerpo = json.dumps(payload).encode()
        pedido = urllib.request.Request(self.url, data=cuerpo, method="POST",
                                        headers={"Content-Type": "application/json",
                                                 **firmar_cabeceras(cuerpo, self.token, self.secreto)})
        try:
            with urllib.request.urlopen(pedido, timeout=10):
                self.enviadas += 1
                return True
        except urllib.error.HTTPError as e:
            if e.code >= 500:
                print(f"  ! Ingesta {payload['codigo']}: HTTP {e.code}, se reintenta")
                return False
            self.rechazadas += 1
            print(f"  ! Ingesta {payload['codigo']} rechazada: HTTP {e.code} {e.read()[:200].decode(errors='replace')}")
            return True
        except (urllib.error.URLError, TimeoutError, ConnectionError) as e:
            print(f"  ! Ingesta {payload['codigo']}: sin conexión ({e}), queda pendiente")
            return False

    def _hay_pendientes(self):
        return os.path.exists(self.ruta_pendientes) and os.path.getsize(self.ruta_pendientes) > 0

    def _guardar_pendiente(self, payload):
        with open(self.ruta_pendientes, "a", encoding="utf-8") as f:
            f.write(json.dumps(payload) + "\n")

    def _reintentar_pendientes(self):
        if not self._hay_pendientes():
            return
        with open(self.ruta_pendientes, encoding="utf-8") as f:
            pendientes = [json.loads(l) for l in f if l.strip()]
        enviados = 0
        for payload in pendientes:
            if not self._post(payload):
                break
            enviados += 1
        with open(self.ruta_pendientes, "w", encoding="utf-8") as f:
            for payload in pendientes[enviados:]:
                f.write(json.dumps(payload) + "\n")
        if enviados:
            print(f"Pendientes enviados: {enviados}, quedan {len(pendientes) - enviados}")


# --------------------------------------------------------------------------
# Configuración remota (CU-06)
# --------------------------------------------------------------------------
class ConfiguracionRemota:
    """Intervalo vigente según Laravel, actualizado en segundo plano.

    La consulta va en un hilo aparte para que una demora o una caída de Laravel
    nunca retrase la respuesta a un nodo: el nodo escucha solo un par de segundos
    después de transmitir. Si una consulta falla se conserva el último valor bueno.
    """

    def __init__(self, url, token, secreto):
        self.url, self.token, self.secreto = url, token, secreto
        self.vigente = None             # (cfg_version, intervalo_min) o None
        self.secuencia = 0
        threading.Thread(target=self._consultar_siempre, daemon=True).start()

    def _consultar_siempre(self):
        while True:
            try:
                self.vigente = self._consultar()
            except Exception as e:      # red, HTTP o JSON: se reintenta en el próximo ciclo
                print(f"  ! No se pudo consultar la configuración: {e}")
            time.sleep(CONSULTA_CONFIG_CADA_S)

    def _consultar(self):
        pedido = urllib.request.Request(self.url, headers=firmar_cabeceras(b"", self.token, self.secreto))
        with urllib.request.urlopen(pedido, timeout=5) as respuesta:
            datos = json.load(respuesta)
        vigente = (int(datos["cfg_version"]), int(datos["intervalo_transmision_minutos"]))
        if vigente != self.vigente:
            print(f"Configuración vigente: versión {vigente[0]}, intervalo {vigente[1]} min")
        return vigente

    def trama_para(self, t):
        """Trama 0x20 para el nodo de `t` si está escuchando y no tiene la versión vigente."""
        if self.vigente is None or not (t["banderas"] & BAND_ESCUCHA):
            return None
        cfg_version, intervalo = self.vigente
        if t.get("cfg_version") == cfg_version:
            return None
        self.secuencia = (self.secuencia + 1) % 65536
        return struct.pack("<BBBHBBH", PROTO_VERSION, TIPO_CONFIG, t["nodo"], self.secuencia, 0,
                           cfg_version, intervalo)


# --------------------------------------------------------------------------
# Métricas de la prueba
# --------------------------------------------------------------------------
class Metricas:
    def __init__(self):
        self.inicio = None
        self.nodos = {}                 # nodo -> dict de contadores
        self.lineas = 0
        self.eventos_ff = 0
        self.recuperadas = 0
        self.descartes = Counter()
        self.errores_decod = 0
        self.ultimo_hb = None
        self.huecos_hb = 0
        self.reinicios_gateway = 0

    def nodo(self, n):
        return self.nodos.setdefault(n, {"recibidas": 0, "perdidas": 0, "reinicios": 0,
                                         "ultima_seq": None, "estados": Counter(),
                                         "bat_inicial": None, "bat_final": None,
                                         "dist_ok": [], "rssi": [],
                                         "temp": [], "hum": [], "suelo": [], "luz": [],
                                         "ultima_basc": None, "reinicio_pluvio": False,
                                         "lluvia_total": 0.0, "secuencias_raras": 0,
                                         "cfg_enviadas": 0, "cfg_rechazadas": 0})

    def registrar_trama(self, t, rssi):
        m = self.nodo(t["nodo"])
        seq = t["secuencia"]
        es_arranque = t["tipo"] == 0x10 or (t["banderas"] & 0x01)
        if m["ultima_seq"] is not None and not es_arranque:
            esperada = (m["ultima_seq"] + 1) % 65536
            salto = (seq - esperada) % 65536
            if salto < 1000:            # hueco razonable: mensajes perdidos
                m["perdidas"] += salto
            else:                       # la secuencia retrocedió sin trama de arranque
                m["reinicios"] += 1
                m["secuencias_raras"] += 1
                print(f"  ! Nodo {t['nodo']}: la secuencia saltó de {m['ultima_seq']} a {seq} sin trama de "
                      f"arranque. ¿Hay dos nodos con el mismo NODO_ID?")
        if es_arranque and m["ultima_seq"] is not None:
            m["reinicios"] += 1
        if es_arranque:
            m["reinicio_pluvio"] = True   # el SEN0575 pudo perder su contador
        if t["banderas"] & BAND_CFG_RECHAZADA:
            m["cfg_rechazadas"] += 1
            print(f"  ! Nodo {t['nodo']}: rechazó la última configuración enviada")
        m["ultima_seq"] = seq
        m["recibidas"] += 1
        m["rssi"].append(rssi)
        if "bateria_mv" in t:
            if m["bat_inicial"] is None:
                m["bat_inicial"] = t["bateria_mv"]
            m["bat_final"] = t["bateria_mv"]
        if t["tipo"] == 0x01:
            m["estados"][t["estado"]] += 1
            if t["estado"] == "OK":
                m["dist_ok"].append(t["distancia_mm"])
        elif t["tipo"] == 0x03:
            m["estados"][t["estado"]] += 1
            for clave, lista in (("temp_aire_c", "temp"), ("hum_aire_pct", "hum"),
                                 ("suelo_mv", "suelo"), ("luz_lx", "luz")):
                if t.get(clave) is not None:
                    m[lista].append(t[clave])
            basc = t.get("basculadas")
            if basc is not None:
                if m["ultima_basc"] is None:
                    delta = None                        # primera lectura: no hay referencia
                elif m["reinicio_pluvio"] and basc < m["ultima_basc"]:
                    delta = basc                        # el contador volvió a 0 con el reinicio
                else:
                    delta = (basc - m["ultima_basc"]) % 65536
                    if delta > MAX_BASCULADAS_INTERVALO:     # salto imposible: el contador se reinició
                        print(f"  ! Nodo {t['nodo']}: contador del pluviómetro {m['ultima_basc']} -> {basc} "
                              f"sin trama de arranque; se descarta la lluvia de este intervalo")
                        delta = None
                m["ultima_basc"] = basc
                m["reinicio_pluvio"] = False
                if delta is not None:
                    t["lluvia_mm"] = round(delta * MM_POR_BASCULADA, 2)
                    m["lluvia_total"] += t["lluvia_mm"]

    def registrar_hb(self, ahora):
        if self.ultimo_hb is not None and ahora - self.ultimo_hb > 6:
            self.huecos_hb += 1
        self.ultimo_hb = ahora

    def resumen(self, ahora):
        dur = (ahora - self.inicio) / 3600 if self.inicio else 0
        print("\n" + "=" * 64)
        print(f"RESUMEN  duración {dur:.2f} h  |  líneas {self.lineas}  |  eventos FF {self.eventos_ff}"
              f" ({self.eventos_ff / dur:.1f}/h)" if dur > 0 else "RESUMEN")
        print(f"  recuperadas {self.recuperadas} | descartes {dict(self.descartes)} | "
              f"errores de decodificación {self.errores_decod} | huecos de latido {self.huecos_hb} | "
              f"reinicios del gateway {self.reinicios_gateway}")
        for n, m in sorted(self.nodos.items()):
            esperadas = m["recibidas"] + m["perdidas"]
            entrega = 100 * m["recibidas"] / esperadas if esperadas else 0
            print(f"  Nodo {n}: recibidas {m['recibidas']}/{esperadas} ({entrega:.2f} %) | "
                  f"reinicios {m['reinicios']} | estados {dict(m['estados'])}")
            if m["dist_ok"]:
                d = m["dist_ok"]
                print(f"    distancia OK: min {min(d)} | max {max(d)} | media {sum(d) / len(d):.1f} mm")
            if m["rssi"]:
                print(f"    RSSI: min {min(m['rssi'])} | max {max(m['rssi'])} dBm")
            if m["bat_inicial"] is not None:
                print(f"    batería: {m['bat_inicial']} -> {m['bat_final']} mV")
            for lista, nombre, unidad in (("temp", "temperatura", "°C"), ("hum", "humedad", "%"),
                                          ("suelo", "suelo", "mV"), ("luz", "luz", "lx")):
                if m[lista]:
                    v = m[lista]
                    print(f"    {nombre}: min {min(v)} | max {max(v)} {unidad}")
            if m["ultima_basc"] is not None:
                print(f"    lluvia acumulada: {m['lluvia_total']:.2f} mm (contador {m['ultima_basc']})")
            if m["cfg_enviadas"] or m["cfg_rechazadas"]:
                print(f"    configuraciones enviadas {m['cfg_enviadas']} | rechazadas {m['cfg_rechazadas']}")
            if m["secuencias_raras"]:
                print(f"    ! saltos de secuencia sin arranque: {m['secuencias_raras']} (¿NODO_ID duplicado?)")
        print("=" * 64 + "\n")


# --------------------------------------------------------------------------
# Procesamiento de líneas
# --------------------------------------------------------------------------
def procesar(raw, ts, ahora, met, escritor):
    met.lineas += 1
    if met.inicio is None:
        met.inicio = ahora
    if b"\xff" in raw:
        met.eventos_ff += 1
    texto = raw.replace(b"\xff", b"").decode("ascii", errors="replace").strip()

    m = RE_RX.search(texto)
    if m:
        if not texto.startswith("RX|"):
            met.recuperadas += 1
        rssi, hexa = int(m.group(1)), m.group(2)
        try:
            t = decodificar(bytes.fromhex(hexa))
        except ValueError as e:
            met.errores_decod += 1
            print(f"{ts} [ERROR DECODIFICACIÓN] {e} | {hexa}")
            return
        met.registrar_trama(t, rssi)
        detalle = {k: v for k, v in t.items() if k not in ("tipo", "nodo", "secuencia", "banderas", "nombre")}
        print(f"{ts} N{t['nodo']} #{t['secuencia']} {t['nombre']} [{nombres_banderas(t['banderas'])}] "
              f"RSSI {rssi} | {detalle}")
        fila = dict(t, fecha_hora=ts, rssi_dbm=rssi, tipo=t["nombre"],
                    banderas=nombres_banderas(t["banderas"]), hex=hexa)
        escritor.writerow(["" if fila.get(c) is None else fila.get(c, "") for c in COLUMNAS_CSV])
        return t

    if RE_HB.search(texto):
        met.registrar_hb(ahora)
        return
    if "#CONFIG|" in texto:
        met.reinicios_gateway += 1
    if "#DESCARTE|" in texto:
        motivo = texto.split("#DESCARTE|", 1)[1].split("|")[0]
        met.descartes[motivo] += 1
    if texto:
        print(f"{ts} {texto}")


def fuente_e220(ser):
    """Lee el E220 conectado al UART de la Raspberry Pi.

    El módulo entrega cada trama recibida como una ráfaga continua de bytes,
    seguida del byte de RSSI (REG3, bit 7). Una pausa de más de 50 ms marca el
    fin de la trama. Se devuelve en el formato de línea del gateway ESP32
    (RX|<rssi>|<hex>) para conservar el log crudo y el modo --archivo.
    Recibe el puerto ya abierto porque también se usa para responder (trama 0x20).
    """
    ser.reset_input_buffer()
    buf = b""
    while True:
        b = ser.read(64)
        if b:
            buf += b
            continue
        if len(buf) >= 2:
            rssi = -(256 - buf[-1])
            linea = f"RX|{rssi}|{buf[:-1].hex().upper()}".encode()
            ts = datetime.datetime.now().isoformat(timespec="milliseconds")
            yield linea, ts, time.time(), True
        buf = b""

def fuente_archivo(ruta):
    with open(ruta, "rb") as f:
        for linea in f:
            ts_b, _, contenido = linea.rstrip(b"\r\n").partition(b" ")
            ts = ts_b.decode("ascii", errors="replace")
            try:
                ahora = datetime.datetime.fromisoformat(ts).timestamp()
            except ValueError:
                continue
            yield contenido, ts, ahora, False


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("puerto", nargs="?", help="puerto serie del E220 (/dev/ttyAMA0)")
    ap.add_argument("--archivo", help="reproducir un log_crudo.txt guardado")
    ap.add_argument("--salida", default=".", help="carpeta para log_crudo.txt y lecturas.csv")
    args = ap.parse_args()
    if not args.puerto and not args.archivo:
        ap.error("indicá un puerto o --archivo")

    os.makedirs(args.salida, exist_ok=True)
    met = Metricas()
    ser = None
    configuracion = None
    envio = None
    calibracion = {}
    if args.archivo:
        fuente = fuente_archivo(args.archivo)
    else:
        import serial
        ser = serial.Serial(args.puerto, 9600, timeout=0.05)
        fuente = fuente_e220(ser)
        token, secreto = os.environ.get("INGESTA_TOKEN", ""), os.environ.get("INGESTA_HMAC_SECRET", "")
        url = os.environ.get("CONFIGURACION_URL", "")
        if url:
            configuracion = ConfiguracionRemota(url, token, secreto)
        else:
            print("Sin CONFIGURACION_URL: no se envía configuración a los nodos")
        url = os.environ.get("INGESTA_URL", "")
        if url:
            envio = EnvioIngesta(url, token, secreto, os.path.join(args.salida, "pendientes.jsonl"))
            calibracion = cargar_calibracion(os.environ.get(
                "CALIBRACION", os.path.join(os.path.dirname(os.path.abspath(__file__)), "calibracion.json")))
        else:
            print("Sin INGESTA_URL: las lecturas solo se guardan en el CSV")
    sufijo = "_reproduccion" if args.archivo else ""
    ruta_csv = os.path.join(args.salida, f"lecturas{sufijo}.csv")
    ruta_log = os.path.join(args.salida, "log_crudo.txt")
    if os.path.exists(ruta_csv):                       # CSV con columnas anteriores: se archiva
        with open(ruta_csv, encoding="utf-8") as f:
            encabezado = f.readline().strip()
        if encabezado and encabezado != ",".join(COLUMNAS_CSV):
            archivado = ruta_csv.replace(".csv", f"_hasta_{datetime.date.today():%Y%m%d}.csv")
            os.rename(ruta_csv, archivado)
            print(f"CSV con columnas anteriores archivado como {archivado}")
    with open(ruta_csv, "a", newline="", encoding="utf-8") as fcsv, \
         open(ruta_log, "ab") if not args.archivo else open(os.devnull, "wb") as flog:
        escritor = csv.writer(fcsv)
        if fcsv.tell() == 0:
            escritor.writerow(COLUMNAS_CSV)
        ultimo_resumen = None
        ultimo_ahora = time.time()
        print("Registrando... Ctrl+C para terminar")
        try:
            for raw, ts, ahora, en_vivo in fuente:
                ultimo_ahora = ahora
                if en_vivo:
                    flog.write(ts.encode() + b" " + raw.rstrip(b"\r\n") + b"\n")
                    flog.flush()
                t = procesar(raw, ts, ahora, met, escritor)
                trama = configuracion.trama_para(t) if configuracion and t else None
                if trama:
                    ser.write(trama)            # el nodo sigue escuchando: se responde en el acto
                    ser.flush()
                    met.nodo(t["nodo"])["cfg_enviadas"] += 1
                    flog.write(ts.encode() + b" TX|" + trama.hex().upper().encode() + b"\n")
                    flog.flush()
                    print(f"{ts} -> N{t['nodo']} CONFIG versión {configuracion.vigente[0]}, "
                          f"intervalo {configuracion.vigente[1]} min")
                if envio and t:
                    medido_en = datetime.datetime.now().astimezone().isoformat(timespec="seconds")
                    payload = payload_ingesta(t, calibracion.get(t["nodo"], {}), medido_en)
                    if payload:
                        envio.enviar(payload)
                fcsv.flush()
                if ultimo_resumen is None:
                    ultimo_resumen = ahora
                elif ahora - ultimo_resumen >= RESUMEN_CADA_S:
                    met.resumen(ahora)
                    ultimo_resumen = ahora
        except KeyboardInterrupt:
            pass
        met.resumen(time.time() if not args.archivo else ultimo_ahora)
    if envio:
        print(f"Ingesta: enviadas {envio.enviadas} | rechazadas {envio.rechazadas} | "
              f"pendientes en {envio.ruta_pendientes} si quedó alguna")
    if ser:
        ser.close()


if __name__ == "__main__":
    main()