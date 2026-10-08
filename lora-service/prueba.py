"""
Lector/escritor serial del receptor E220-900T30D
-------------------------------------------------
Requiere: pip install pyserial

Antes de correr este script, CERRA el Monitor Serie del Arduino IDE
(el puerto COM solo lo puede usar un programa a la vez).
"""

import serial
import serial.tools.list_ports
import sys
import threading

BAUDRATE = 9600

# Poné aca el puerto fijo si ya lo conoces (ej: "COM3" en Windows, "/dev/ttyUSB0" en Linux/Mac).
# Dejalo como None para que el script te pregunte cada vez.
PUERTO_FIJO = "/dev/ttyUSB0"


def elegir_puerto():
    if PUERTO_FIJO:
        return PUERTO_FIJO

    puertos = list(serial.tools.list_ports.comports())
    if not puertos:
        print("No se encontro ningun puerto serial conectado.")
        sys.exit(1)

    if len(puertos) == 1:
        return puertos[0].device

    for i, p in enumerate(puertos):
        print(f"  [{i}] {p.device} - {p.description}")
    idx = input("Elegi el numero de puerto: ").strip()
    return puertos[int(idx)].device


def hilo_lectura(ser, detener):
    """Corre en paralelo: lee todo lo que manda el ESP32 y lo muestra."""
    while not detener.is_set():
        try:
            linea = ser.readline().decode("utf-8", errors="ignore").strip()
        except serial.SerialException:
            break

        if linea:
            print(linea)


def main():
    puerto = elegir_puerto()

    try:
        ser = serial.Serial(puerto, BAUDRATE, timeout=1)
    except serial.SerialException as e:
        print(f"No se pudo abrir {puerto}: {e}")
        sys.exit(1)

    detener = threading.Event()
    lector = threading.Thread(target=hilo_lectura, args=(ser, detener), daemon=True)
    lector.start()

    try:
        while True:
            comando = input().strip().lower()

            if comando == "salir":
                break
            elif comando == "nivel_agua":
                ser.write(b"nivel_agua\n")

    except KeyboardInterrupt:
        pass
    finally:
        detener.set()
        ser.close()


if __name__ == "__main__":
    main()