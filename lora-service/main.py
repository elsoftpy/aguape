"""
Servicio receptor LoRa (RF01/RF03/RF04): lee las tramas que llegan por el
modulo CDEBYTE E220-900T30D (UART) conectado a la Raspberry Pi, las decodifica
y reenvia cada lectura al endpoint de ingesta de Laravel (`POST /api/ingesta`),
firmando cada mensaje igual que documenta `docs/server-infra-guide.md`.

"""
import hashlib
import hmac
import json 
import os
import time

import requests
import serial

PUERTO_SERIAL = os.environ.get("LORA_SERIAL_PORT", "/dev/ttyUSB0")
BAUDRATE = int(os.environ.get("LORA_BAUDRATE", "9600"))
INGESTA_URL = os.environ.get("INGESTA_URL", "http://localhost:8081/api/ingesta")
INGESTA_TOKEN = os.environ.get("INGESTA_TOKEN", "")
INGESTA_HMAC_SECRET = os.environ.get("INGESTA_HMAC_SECRET", "")

def decodificar_trama(cruda: bytes) -> dict:
    """
    PLACEHOLDER - reemplazar cuando se confirme el formato real de las tramas
    que transmiten los nodos (pendiente, ver nota al inicio del archivo).

    Formato asumido por ahora, solo para poder probar el resto del pipeline
    de punta a punta: texto plano, delimitado por `|` y `;`.

        "N-1|87|temperatura_aire=24.3;humedad_aire=61.2;nivel_agua=132.0"
         codigo  bateria  lecturas separadas por `;`, cada una `variable=valor`
    """
    texto = cruda.decode("ascii", errors="ignore").strip()
    codigo, nivel_bateria, lecturas_crudas = texto.split("|")

    lecturas = []
    for lectura in lecturas_crudas.split(";"):
        variable, valor = lectura.split("=")
        lecturas.append({"variable": variable, "valor": float(valor)})

    return {
        "codigo": codigo,
        "nivel_bateria": float(nivel_bateria),
        "lecturas": lecturas,
    }

def construir_payload(decodificado: dict) -> dict:
    """
    Arma el cuerpo JSON que espera `/api/ingesta` (ver
    `docs/server-infra-guide.md`). `medido_en` se toma del momento en que la
    Raspberry Pi recibe el mensaje, no de un reloj propio del nodo: los nodos
    no tienen RTC, y el enlace LoRa es practicamente instantaneo, asi que la
    hora de recepcion es una medicion suficientemente precisa del momento
    real en que se tomo la lectura.
    """

    return {
        "codigo": decodificado["codigo"],
        "medido_en": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
        "nivel_bateria": decodificado["nivel_bateria"],
        "lecturas": decodificado["lecturas"],
    }

def firmar(payload: dict, timestamp: int) -> str:
    """
    Misma firma que valida `VerifyIngestaSignature` en Laravel:
    HMAC-SHA256 sobre "{timestamp}.{cuerpo_json}", en hexadecimal.
    `json.dumps` tiene que serializar exactamente como se envia en el `POST`
    (los mismos bytes, no solo la misma estructura de datos en memoria),
    porque la firma se calcula sobre los bytes reales que viajan por la red.
    """
    cuerpo = json.dumps(payload)
    firma = hmac.new(
        INGESTA_HMAC_SECRET.encode("ascii"),
        f"{timestamp}.{cuerpo}".encode("ascii"),
        hashlib.sha256,
    )

    return firma.hexdigest()

def enviar(payload: dict) -> None:
    """POST firmado a `/api/ingesta`, con log a stdout del resultado."""

    timestamp = int(time.time())
    headers = {
        "Authorization": f"Bearer {INGESTA_TOKEN}",
        "X-Ingesta-Timestamp": str(timestamp),
        "X-Ingesta-Signature": firmar(payload, timestamp),
        "Content-Type": "application/json",
    } 

    respuesta = requests.post(INGESTA_URL, json=payload, headers=headers, timeout=5)

    if respuesta.ok:
        print(f"[OK] {payload['codigo']}: {respuesta.status_code}")
    else:
        print(f"[ERROR] {payload['codigo']}: {respuesta.status_code} {respuesta.text}")

def main() -> None:
    """
    Bucle principal: abre el puerto serial y, por cada linea que llega (una
    trama), la decodifica y la reenvia. `with serial.Serial(...) as puerto`
    asegura que el puerto se cierre bien incluso si el programa se corta con
    Ctrl+C o una excepcion - mismo espiritu que un `try/finally`, mas corto.
    """
    with serial.Serial(PUERTO_SERIAL, BAUDRATE, timeout=1) as puerto:
        print(f"Escuchando en {PUERTO_SERIAL} a {BAUDRATE} baudios...")

        while True:
            linea = puerto.readline() 

            if not linea:
                continue 

            try: 
                decodificado = decodificar_trama(linea)
                payload = construir_payload(decodificado)
                enviar(payload)
            except Exception as error:
                print(f"[ERROR] trama descartada: {error!r} ({linea!r})")

if __name__ == "__main__":
    main()


