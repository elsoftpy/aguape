/*
  TRANSMISOR (NODO) - E220-900T30D
  ---------------------------------
  Modelo push + sleep (RF05): el nodo despierta cada <intervalo> minutos,
  toma una lectura, la envia sin que se la pidan, y escucha un breve
  instante (VENTANA_ACK_MS) por un ACK de la base con el intervalo vigente
  antes de volver a dormir.

  PRESENCIA|<codigo> se sigue enviando con el boton, solo para pruebas de
  banco/instalacion (el nodo debe estar despierto - no aplica en operacion
  normal a bateria).

  IMPORTANTE: antes de usar este sketch, este mismo modulo debe haber sido
  configurado una vez con "configurar_modulo.ino" (canal 73, 923.125 MHz,
  misma direccion 0x0001 que el receptor).
*/

#include "Arduino.h"
#define FREQUENCY_900
#define E220_30
#include "LoRa_E220.h"
#include "esp_sleep.h"

#define PIN_AUX   15
#define PIN_M0    4
#define PIN_M1    5
#define PIN_BOTON 2

#define PIN_TRIG  18
#define PIN_ECHO  19

const String CODIGO_NODO = "N-1";

const int INTERVALO_MINUTOS_POR_DEFECTO = 15; // solo se usa en el primer arranque en frio
const unsigned long VENTANA_ACK_MS = 3000;    // cuanto espera el ACK antes de dormir igual
const unsigned long VENTANA_BOTON_MS = 2000;  // cuanto queda despierto para probar el boton
const unsigned long DEBOUNCE_MS = 50;

// TODO: sin bateria cableada todavia - placeholder hasta calibrar el
// divisor de voltaje real.
const float NIVEL_BATERIA_PLACEHOLDER = 100.0;

// RTC_DATA_ATTR sobrevive el deep sleep (pero no un reset/power-cycle
// completo), asi que el intervalo vigente se recuerda entre ciclos de
// sueno sin volver al valor por defecto cada vez.
RTC_DATA_ATTR int intervaloVigenteMinutos = INTERVALO_MINUTOS_POR_DEFECTO;

LoRa_E220 e220ttl(&Serial2, PIN_AUX, PIN_M0, PIN_M1);

int medirDistanciaCM() {
  digitalWrite(PIN_TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(PIN_TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(PIN_TRIG, LOW);

  long duracion = pulseIn(PIN_ECHO, HIGH, 30000); // timeout 30ms (~5m maximo)
  if (duracion == 0) return -1; // sin eco / fuera de rango

  return (int)(duracion * 0.0343 / 2.0);
}

void enviarPresencia() {
  String trama = "PRESENCIA|" + CODIGO_NODO;
  ResponseStatus rs = e220ttl.sendMessage(trama);
  Serial.println("[TX presencia] " + trama);
  Serial.println(rs.getResponseDescription());
}

void enviarLecturaYEsperarAck() {
  int nivelAgua = medirDistanciaCM();

  if (nivelAgua < 0) {
    Serial.println("Lectura descartada: sensor sin eco");
    return;
  }

  String trama = CODIGO_NODO + "|" + String(NIVEL_BATERIA_PLACEHOLDER, 1)
      + "|nivel_agua=" + String(nivelAgua);

  e220ttl.sendMessage(trama);
  Serial.println("[TX lectura] " + trama);

  String prefijoAck = "ACK|" + CODIGO_NODO + "|intervalo=";
  unsigned long inicioEspera = millis();

  while (millis() - inicioEspera < VENTANA_ACK_MS) {
    if (e220ttl.available() > 0) {
      ResponseContainer rc = e220ttl.receiveMessage();
      String respuesta = rc.data;
      respuesta.trim();

      if (respuesta.startsWith(prefijoAck)) {
        int nuevoIntervalo = respuesta.substring(prefijoAck.length()).toInt();

        if (nuevoIntervalo > 0) {
          intervaloVigenteMinutos = nuevoIntervalo;
          Serial.println("[ACK] nuevo intervalo: " + String(intervaloVigenteMinutos) + " min");
        }

        return;
      }
    }
  }

  Serial.println("[ACK] sin respuesta, se mantiene intervalo: " + String(intervaloVigenteMinutos) + " min");
}

void dormir() {
  Serial.println("Durmiendo " + String(intervaloVigenteMinutos) + " min...");
  Serial.flush();

  uint64_t microsegundos = (uint64_t) intervaloVigenteMinutos * 60ULL * 1000000ULL;
  esp_sleep_enable_timer_wakeup(microsegundos);
  esp_deep_sleep_start();
}

void setup() {
  Serial.begin(9600);
  pinMode(PIN_BOTON, INPUT); // ya tiene resistencia pull-down externa
  pinMode(PIN_TRIG, OUTPUT);
  pinMode(PIN_ECHO, INPUT);

  e220ttl.begin();

  esp_sleep_wakeup_cause_t causaDespertar = esp_sleep_get_wakeup_cause();
  Serial.println(causaDespertar == ESP_SLEEP_WAKEUP_TIMER
      ? "Despertar por temporizador"
      : "Arranque en frio");

  enviarLecturaYEsperarAck();

  // Ventana breve para probar el boton de presencia mientras el nodo esta
  // despierto (solo pruebas de banco).
  unsigned long finVentanaBoton = millis() + VENTANA_BOTON_MS;
  bool botonAnterior = LOW;
  unsigned long ultimoCambio = 0;

  while (millis() < finVentanaBoton) {
    bool botonActual = digitalRead(PIN_BOTON);

    if (botonActual == HIGH && botonAnterior == LOW &&
        (millis() - ultimoCambio) > DEBOUNCE_MS) {
      ultimoCambio = millis();
      enviarPresencia();
    }

    botonAnterior = botonActual;
  }

  dormir();
}

void loop() {
  // No se usa: cada ciclo hace todo su trabajo en setup() antes de volver
  // a dormir - el deep sleep reinicia la placa, que vuelve a correr setup().
}