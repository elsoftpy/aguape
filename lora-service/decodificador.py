"""
Decodificador del protocolo binario Aguape v1 (primera versión del lora-service).

Lee las tramas del E220 conectado al UART de la Raspberry Pi (/dev/ttyAMA0),
las decodifica y guarda:
  - log_crudo.txt   : todas las líneas con fecha y hora (sirve para reproducir la prueba)
  - lecturas.csv    : una fila por trama decodificada
y muestra un resumen de métricas cada 10 minutos y al terminar (Ctrl+C).

Uso:
  python3 decodificador.py /dev/ttyAMA0 --salida /var/lib/aguape     # en vivo
  python3 decodificador.py --archivo log_crudo.txt --salida datos/banco   # reproducir

Requiere: python3-serial (apt) en la Raspberry Pi
"""

import argparse
import csv
import datetime
import re
import struct
import os
import sys
import time
from collections import Counter

PROTO_VERSION = 1
ESTADOS_PASTURA = {0: "OK", 1: "SIN_ECO", 2: "ZONA_CIEGA", 3: "FUERA_ESCALA", 255: "SIN_MEDICION"}
BANDERAS = {0x01: "ARRANQUE", 0x02: "BATERIA_CRITICA", 0x04: "CFG_RECHAZADA", 0x08: "ESCUCHA"}
RE_RX = re.compile(r"\|(-\d{1,3})\|((?:[0-9A-Fa-f]{2}){6,})\s*$")   # |<rssi>|<hex>: tolera el comienzo dañado
RE_HB = re.compile(r"^(?:#?H?B?\|)?\d{3,}\s*$")   # #HB|<ms>, aunque haya perdido parte o todo el prefijo
RESUMEN_CADA_S = 600

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
    elif tipo in (0x02, 0x03):                          # agua / ambiental (provisionales)
        if len(cuerpo) != 14:
            raise ValueError(f"tipo {tipo:#04x}: cuerpo de {len(cuerpo)} bytes, se esperaban 14")
        formato = "<HBBHhHHH" if tipo == 0x02 else "<HBBhHHHH"   # agua: nivel primero | ambiental: temperatura primero
        bat, cfg, estado, *valores = struct.unpack(formato, cuerpo)
        t.update(nombre="AGUA" if tipo == 0x02 else "AMBIENTAL", bateria_mv=bat,
                 cfg_version=cfg, estado=f"bits={estado:05b}", valores=valores)
    else:
        raise ValueError(f"tipo {tipo:#04x} desconocido")
    return t


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
                                         "dist_ok": [], "rssi": []})

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
        if es_arranque and m["ultima_seq"] is not None:
            m["reinicios"] += 1
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
        escritor.writerow([ts, rssi, t["nodo"], t["nombre"], t["secuencia"], nombres_banderas(t["banderas"]),
                           t.get("bateria_mv", ""), t.get("cfg_version", ""), t.get("estado", ""),
                           t.get("distancia_mm", ""), t.get("firmware", ""), t.get("motivo_reinicio", ""), hexa])
        return

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


def fuente_e220(puerto):
    """Lee el E220 conectado al UART de la Raspberry Pi.

    El módulo entrega cada trama recibida como una ráfaga continua de bytes,
    seguida del byte de RSSI (REG3, bit 7). Una pausa de más de 50 ms marca el
    fin de la trama. Se devuelve en el formato de línea del gateway ESP32
    (RX|<rssi>|<hex>) para conservar el log crudo y el modo --archivo.
    """
    import serial
    with serial.Serial(puerto, 9600, timeout=0.05) as ser:
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
    fuente = fuente_archivo(args.archivo) if args.archivo else fuente_e220(args.puerto)
    sufijo = "_reproduccion" if args.archivo else ""
    ruta_csv = os.path.join(args.salida, f"lecturas{sufijo}.csv")
    ruta_log = os.path.join(args.salida, "log_crudo.txt")
    with open(ruta_csv, "a", newline="", encoding="utf-8") as fcsv, \
         open(ruta_log, "ab") if not args.archivo else open(os.devnull, "wb") as flog:
        escritor = csv.writer(fcsv)
        if fcsv.tell() == 0:
            escritor.writerow(["fecha_hora", "rssi_dbm", "nodo", "tipo", "secuencia", "banderas",
                               "bateria_mv", "cfg_version", "estado", "distancia_mm",
                               "firmware", "motivo_reinicio", "hex"])
        ultimo_resumen = None
        ultimo_ahora = time.time()
        print("Registrando... Ctrl+C para terminar")
        try:
            for raw, ts, ahora, en_vivo in fuente:
                ultimo_ahora = ahora
                if en_vivo:
                    flog.write(ts.encode() + b" " + raw.rstrip(b"\r\n") + b"\n")
                    flog.flush()
                procesar(raw, ts, ahora, met, escritor)
                fcsv.flush()
                if ultimo_resumen is None:
                    ultimo_resumen = ahora
                elif ahora - ultimo_resumen >= RESUMEN_CADA_S:
                    met.resumen(ahora)
                    ultimo_resumen = ahora
        except KeyboardInterrupt:
            pass
        met.resumen(time.time() if not args.archivo else ultimo_ahora)


if __name__ == "__main__":
    main()