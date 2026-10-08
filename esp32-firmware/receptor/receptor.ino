/*
  RECEPTOR (BASE) - E220-900T30D - PRUEBA DE BANCO
  ---------------------------------
  Sketch de prueba para simular el rol de la Raspberry Pi + lora-service
  mientras esta ausente el hardware final. Corre en un segundo ESP32,
  alimentado por USB - siempre esta escuchando, no necesita dormir.

  Responde cada lectura push con un ACK que lleva el intervalo de
  transmision vigente (INTERVALO_PRUEBA_MINUTOS aqui abajo - el servicio
  Python real va a tomar ese valor de la base de datos via
  GET /api/nodos/{codigo} en vez de un valor fijo).

  IMPORTANTE: mismo canal/direccion que el transmisor (configurar_modulo.ino,
  canal 73, 923.125 MHz, direccion 0x0001).
*/

#include "Arduino.h"
#define FREQUENCY_900
#define E220_30
#include "LoRa_E220.h"

#define PIN_AUX 15
#define PIN_M0  4
#define PIN_M1  5
#define PIN_LED 2 // LED integrado en la mayoria de placas ESP32 devkit

// TODO: reemplazar por una consulta real a Laravel (GET /api/nodos/{codigo})
// cuando el servicio Python este listo - por ahora, valor fijo para probar
// el ciclo de sleep/ACK de punta a punta.
const int INTERVALO_PRUEBA_MINUTOS = 1;

LoRa_E220 e220ttl(&Serial2, PIN_AUX, PIN_M0, PIN_M1);

void procesarPresencia(const String &codigoNodo) {
  Serial.println("[PRESENCIA] " + codigoNodo + " esta en rango");
  digitalWrite(PIN_LED, HIGH);
  delay(200);
  digitalWrite(PIN_LED, LOW);
}

void procesarLectura(const String &trama) {
  // Formato: <codigo>|<nivel_bateria>|variable=valor;variable=valor;...
  int primerSeparador = trama.indexOf('|');
  int segundoSeparador = trama.indexOf('|', primerSeparador + 1);

  if (primerSeparador == -1 || segundoSeparador == -1) {
    Serial.println("[ERROR] trama con formato invalido: " + trama);
    return;
  }

  String codigoNodo = trama.substring(0, primerSeparador);
  String nivelBateria = trama.substring(primerSeparador + 1, segundoSeparador);
  String lecturas = trama.substring(segundoSeparador + 1);

  Serial.println("[LECTURA] nodo=" + codigoNodo + " bateria=" + nivelBateria + " " + lecturas);

  String ack = "ACK|" + codigoNodo + "|intervalo=" + String(INTERVALO_PRUEBA_MINUTOS);
  ResponseStatus rs = e220ttl.sendMessage(ack);
  Serial.println("[TX ack] " + ack);
  Serial.println(rs.getResponseDescription());
}

void setup() {
  Serial.begin(9600);
  pinMode(PIN_LED, OUTPUT);
  digitalWrite(PIN_LED, LOW);

  e220ttl.begin();

  ResponseStructContainer c = e220ttl.getConfiguration();
  Configuration configuration = *(Configuration*) c.data;
  Serial.println("--- Receptor LoRa E220 ---");
  Serial.print("Canal configurado: "); Serial.println(configuration.CHAN);
  Serial.print("Banda: "); Serial.println(configuration.getChannelDescription());
  c.close();

  Serial.println("Receptor listo, esperando tramas...");
}

void loop() {
  if (e220ttl.available() > 0) {
    ResponseContainer rc = e220ttl.receiveMessage();
    String trama = rc.data;
    trama.trim();

    if (trama.startsWith("PRESENCIA|")) {
      procesarPresencia(trama.substring(String("PRESENCIA|").length()));
    } else if (trama.indexOf('|') != -1) {
      procesarLectura(trama);
    } else {
      Serial.println("[IGNORADO] " + trama);
    }
  }
}