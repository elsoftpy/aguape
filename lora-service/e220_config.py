#!/usr/bin/env python3
"""
Lee y, si se pide, escribe la configuración del E220 del gateway desde la Raspberry Pi.

Cableado (ver /boot/firmware/config.txt): M0 -> GPIO23, M1 -> GPIO25, AUX -> GPIO24.
Usa `pinctrl` (Raspberry Pi OS) para mover M0/M1 y leer AUX, y pyserial para la UART.

La configuración canónica es la misma que graban los nodos al arrancar
(configCanonica / escribirConfigCanonica en los .ino):
  dirección 0x0001, canal 73 (923,125 MHz), UART 9600 8N1, aire 2,4 kbps,
  subpaquete 200 B, 30 dBm, RSSI ambiente apagado, byte de RSSI encendido,
  transmisión transparente, LBT apagado, WOR 2000 ms.

Uso (con decodificador.py y aguape-lora detenidos, porque usan el mismo puerto):
  python3 e220_config.py              # solo lee y compara
  python3 e220_config.py --escribir   # escribe la canónica si difiere (persiste sin alimentación)

Código de salida: 0 = canónica (o reescrita y verificada), 2 = difiere y no se escribió,
1 = error (el módulo no respondió).
"""
import argparse
import subprocess
import sys
import time

import serial

M0, M1, AUX = 23, 25, 24

# ADDH, ADDL, REG0, REG1, REG2 (canal), REG3
CANONICA = bytes([
    0x00,        # ADDH
    0x01,        # ADDL
    0b01100010,  # REG0: UART 9600 (011) | 8N1 (00) | aire 2,4 kbps (010)
    0b00000000,  # REG1: subpaquete 200 B (00) | RSSI ambiente off (0) | 30 dBm (00)
    73,          # REG2: canal
    0b10000011,  # REG3: byte RSSI on (1) | transparente (0) | LBT off (0) | WOR 2000 ms (011)
])

BAUDIOS = {0: 1200, 1: 2400, 2: 4800, 3: 9600, 4: 19200, 5: 38400, 6: 57600, 7: 115200}
PARIDAD = {0: "8N1", 1: "8O1", 2: "8E1", 3: "8N1"}
AIRE = {0: "2,4k", 1: "2,4k", 2: "2,4k", 3: "4,8k", 4: "9,6k", 5: "19,2k", 6: "38,4k", 7: "62,5k"}
SUBPAQ = {0: "200 B", 1: "128 B", 2: "64 B", 3: "32 B"}
POTENCIA = {0: "30 dBm", 1: "27 dBm", 2: "24 dBm", 3: "21 dBm"}
WOR = {i: f"{(i + 1) * 500} ms" for i in range(8)}


def pin(n, alto):
    subprocess.run(["pinctrl", "set", str(n), "op", "dh" if alto else "dl"], check=True)


def aux_alto():
    salida = subprocess.run(["pinctrl", "get", str(AUX)], capture_output=True, text=True, check=True).stdout
    return "| hi" in salida


def esperar_aux(timeout=2.0):
    limite = time.monotonic() + timeout
    while time.monotonic() < limite:
        if aux_alto():
            return True
        time.sleep(0.01)
    return False


def modo_config(activo):
    pin(M0, activo)
    pin(M1, activo)
    time.sleep(0.05)
    if not esperar_aux():
        print("  ! AUX no subió después de cambiar de modo (sigo igual)")
    time.sleep(0.05)


def comando(puerto, trama):
    puerto.reset_input_buffer()
    puerto.write(trama)
    puerto.flush()
    respuesta = puerto.read(9)
    esperar_aux()
    if len(respuesta) == 9 and respuesta[0] == 0xC1 and respuesta[1] == 0x00 and respuesta[2] == 0x06:
        return respuesta[3:9]
    return None


def describir(cfg):
    addh, addl, reg0, reg1, chan, reg3 = cfg
    print(f"  Dirección:        0x{addh:02X}{addl:02X}")
    print(f"  Canal:            {chan} ({850.125 + chan:.3f} MHz)")
    print(f"  UART:             {BAUDIOS[reg0 >> 5]} {PARIDAD[(reg0 >> 3) & 3]}")
    print(f"  Velocidad aérea:  {AIRE[reg0 & 7]}")
    print(f"  Subpaquete:       {SUBPAQ[reg1 >> 6]}")
    print(f"  RSSI ambiente:    {'on' if reg1 & 0x20 else 'off'}")
    print(f"  Potencia:         {POTENCIA[reg1 & 3]}")
    print(f"  Byte de RSSI:     {'on' if reg3 & 0x80 else 'OFF'}")
    print(f"  Transmisión:      {'fija' if reg3 & 0x40 else 'transparente'}")
    print(f"  LBT:              {'on' if reg3 & 0x10 else 'off'}")
    print(f"  WOR:              {WOR[reg3 & 7]}")


def leer(puerto, reintentos):
    for intento in range(1, reintentos + 1):
        cfg = comando(puerto, bytes([0xC1, 0x00, 0x06]))
        if cfg is not None:
            return cfg
        print(f"  ! Sin respuesta válida (intento {intento}/{reintentos}), espero 5 s...")
        time.sleep(5)
    return None


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--puerto", default="/dev/ttyAMA0")
    ap.add_argument("--escribir", action="store_true", help="escribe la configuración canónica si difiere")
    ap.add_argument("--reintentos", type=int, default=18, help="intentos de lectura cada 5 s (18 = 90 s)")
    args = ap.parse_args()

    try:
        puerto = serial.Serial(args.puerto, 9600, timeout=1)
    except serial.SerialException as e:
        print(f"No se pudo abrir {args.puerto}: {e}")
        print("¿Está corriendo decodificador.py o el servicio aguape-lora? Detenelo primero.")
        return 1

    codigo = 1
    try:
        modo_config(True)
        cfg = leer(puerto, args.reintentos)
        if cfg is None:
            print("El E220 no respondió en modo configuración.")
            return 1

        print("Configuración actual del E220:")
        describir(cfg)

        if cfg == CANONICA:
            print("\nOK: coincide con la configuración de los nodos.")
            codigo = 0
        elif not args.escribir:
            print("\nDIFIERE de la configuración de los nodos. Corré con --escribir para corregirla.")
            codigo = 2
        else:
            print("\nDifiere: escribiendo la configuración canónica (persistente)...")
            escrita = comando(puerto, bytes([0xC0, 0x00, 0x06]) + CANONICA)
            verificada = leer(puerto, 3)
            if escrita == CANONICA and verificada == CANONICA:
                print("OK: escrita y verificada.")
                describir(verificada)
                codigo = 0
            else:
                print("ERROR: la verificación no coincide.")
                if verificada is not None:
                    describir(verificada)
                codigo = 1
    finally:
        modo_config(False)
        puerto.close()
    return codigo


if __name__ == "__main__":
    sys.exit(main())
