"""
Prueba manual del envio firmado a `/api/ingesta`, sin necesitar el hardware
LoRa conectado todavia: arma un mensaje de ejemplo directamente (salteando
`decodificar_trama`, que lee del puerto serial) y lo manda usando las mismas
funciones que usara `main.py` en el bucle real.

Uso: primero levantar Laravel (`sail up -d`) y setear las mismas variables de
entorno que usa `main.py` (`INGESTA_URL`, `INGESTA_TOKEN`,
`INGESTA_HMAC_SECRET`), despues correr:

    python probar_envio.py
"""

from main import construir_payload, enviar

decodificado_de_ejemplo = {
    "codigo": "N-1",
    "nivel_bateria": 87.5,
    "lecturas": [
        {"variable": "temperatura_aire", "valor": 42.3},
        {"variable": "humedad_aire", "valor": 92.2},
    ],
}

enviar(construir_payload(decodificado_de_ejemplo))