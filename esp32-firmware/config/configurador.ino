/*
  CONFIGURADOR E220-900T30D
  ---------------------------------
  Subir este sketch UNA VEZ a cada ESP32 (con su módulo E220 conectado)
  para grabar canal, dirección y potencia en el módulo.
  Abrir el Monitor Serie a 9600 baudios para ver el resultado.

  Conexiones (iguales en ambas placas):
    E220 TXD -> ESP32 GPIO16 (RX2)
    E220 RXD -> ESP32 GPIO17 (TX2)
    E220 AUX -> ESP32 GPIO15
    E220 M0  -> ESP32 GPIO4
    E220 M1  -> ESP32 GPIO5
    E220 VCC -> Fuente externa 3.3-5V (NO el 3.3V del ESP32)
    E220 GND -> GND común
*/

#include "Arduino.h"
#define FREQUENCY_900   // E220-900T: banda base 850MHz + canal = frecuencia real (canal 65 -> 915MHz)
#define E220_30         // Habilita los nombres de potencia correctos para el modelo 30D (POWER_30, etc.)
#include "LoRa_E220.h"

#define PIN_AUX 15
#define PIN_M0  4
#define PIN_M1  5

LoRa_E220 e220ttl(&Serial2, PIN_AUX, PIN_M0, PIN_M1);

void setup() {
  Serial.begin(9600);
  delay(500);

  e220ttl.begin();

  ResponseStructContainer c = e220ttl.getConfiguration();
  Configuration configuration = *(Configuration*) c.data;

  Serial.println("--- Configuracion actual ---");
  Serial.print("Canal: "); Serial.println(configuration.CHAN, DEC);

  // --- Parametros nuevos ---
  configuration.CHAN = 73;              // 850.125 + 65 = 915.125 MHz (banda libre Paraguay)
  configuration.ADDH = 0x00;
  configuration.ADDL = 0x01;            // MISMA direccion en emisor y receptor

  configuration.SPED.uartBaudRate = UART_BPS_9600;
  configuration.SPED.airDataRate  = AIR_DATA_RATE_010_24;

  // Ahora que definimos E220_30 arriba, ya se puede usar el nombre real
  configuration.OPTION.transmissionPower = POWER_30; // 30dBm (maxima potencia)

  // fixedTransmission pertenece a TRANSMISSION_MODE, no a OPTION
  configuration.TRANSMISSION_MODE.fixedTransmission = FT_TRANSPARENT_TRANSMISSION;

  // Habilita que el modulo reporte la fuerza de señal (RSSI) de lo que recibe
  configuration.TRANSMISSION_MODE.enableRSSI = RSSI_ENABLED;

  ResponseStatus rs = e220ttl.setConfiguration(configuration, WRITE_CFG_PWR_DWN_SAVE);
  Serial.println(rs.getResponseDescription());

  c.close();

  Serial.println("Listo. Ahora subi el sketch transmisor.ino o receptor.ino segun corresponda.");
}

void loop() {}