/*
  NODO DE PASTURA — ALTURA DE PASTO
  ---------------------------------
  v1.3.1 — umbral de zona ciega en 250 mm (el sensor informa ~205–215 mm dentro de su
           zona ciega) y detección de batería ausente (USB o switch apagado), que ya
           no se interpreta como batería crítica
  v1.3 — protocolo binario (ver "Protocolo binario de mensajería — Sistema Aguape"):
         trama 0x01 con la distancia cruda en mm, trama de arranque 0x10,
         estados ZONA_CIEGA / FUERA_ESCALA con la mediana medida, y verificación
         y autorreparación de la configuración del E220 solo al encender.
         La altura y la clasificación de obstáculo pasan al backend.
  v1.2 — TX retenido sin desconectar el UART (elimina las tramas FC/00)
  v1.1 — TX retenido en alto durante el sueño profundo
  v1.0 — versión inicial

  Ciclo: despierta -> mide batería -> mide distancia (mediana de 7) -> transmite -> duerme.
  Al encender (no al despertar): verifica la configuración del E220 y envía la trama de arranque.

  Hardware (ver "Conexiones del Nodo 1 — Altura de pasto"):
    E220: TXD->G16, RXD->G17, AUX->G4, M0->G18, M1->G19, VCC->5V (LM2596)
    Protección del E220: 51k G17->3V3
    AJ-SR04M: Trig->G13, Echo->G27, VCC->3V3
    Divisor de batería: 100k + 100k / 51k -> G34 (factor calibrado 4,99)
    LED verde (latido): G25 | LED azul (transmisión): G26

  Versiones: núcleo esp32 3.3.12 | librería EByte LoRa E220 1.0.8
  Placa en Arduino IDE: ESP32 Dev Module
*/

#include "Arduino.h"
#define FREQUENCY_900
#define E220_30
#include "LoRa_E220.h"
#include "driver/gpio.h"
#include "esp_system.h"

// ---------------- Identificación ----------------
#define NODO_ID        1      // AJUSTAR según la numeración definitiva de los nodos
#define FW_MAYOR       1
#define FW_MENOR       3

// ---------------- Protocolo ----------------
#define PROTO_VERSION      1
#define TIPO_PASTURA       0x01
#define TIPO_ARRANQUE      0x10

#define ESTADO_OK            0
#define ESTADO_SIN_ECO       1
#define ESTADO_ZONA_CIEGA    2
#define ESTADO_FUERA_ESCALA  3
#define ESTADO_SIN_MEDICION  255   // batería crítica: no se midió

#define BAND_ARRANQUE        0x01
#define BAND_BATERIA_CRITICA 0x02
#define BAND_CFG_RECHAZADA   0x04
#define BAND_ESCUCHA         0x08

struct __attribute__((packed)) Encabezado {
  uint8_t  version;
  uint8_t  tipo;
  uint8_t  nodo;
  uint16_t secuencia;
  uint8_t  banderas;
};

struct __attribute__((packed)) TramaPastura {
  Encabezado enc;
  uint16_t bateria_mv;
  uint8_t  cfg_version;
  uint8_t  estado;
  uint16_t distancia_mm;
};                                   // 12 bytes

struct __attribute__((packed)) TramaArranque {
  Encabezado enc;
  uint16_t bateria_mv;
  uint8_t  cfg_version;
  uint8_t  fw_mayor;
  uint8_t  fw_menor;
  uint8_t  motivo;
};                                   // 12 bytes

// ---------------- Pines ----------------
#define PIN_AUX        4
#define PIN_M0         18
#define PIN_M1         19
#define PIN_RX_ESP32   16
#define PIN_TX_ESP32   17
#define PIN_TRIG       13
#define PIN_ECHO       27
#define PIN_BATERIA    34
#define PIN_LED_ESTADO 25
#define PIN_LED_TX     26

// ---------------- Ciclo ----------------
#define INTERVALO_S    60     // pruebas: 60 | campo: lo define el backend (v1.4)

// ---------------- Sensor ----------------
#define DIST_MIN_MM          250    // zona ciega del AJ-SR04M
#define DIST_MAX_MM          6000   // alcance del sensor
#define N_LECTURAS           7
#define MIN_LECTURAS_VALIDAS 4
#define TEMP_C               25.0   // temperatura supuesta (hasta tener el DS18B20)
#define K_CAL                1.000

// ---------------- Batería ----------------
#define FACTOR_DIVISOR       4.99
#define N_MUESTRAS_BAT       16
#define BAT_CORTE_MV         6400   // 3,2 V por celda
#define BAT_AUSENTE_MV       3000   // por debajo: sin batería (USB o switch apagado), no es batería crítica
#define INTERVALO_BAT_BAJA_S 3600

// ---------------- E220 ----------------
#define CANAL_E220     73
#define POTENCIA_E220  POWER_21     // banco: POWER_21 | campo: POWER_30

LoRa_E220 e220ttl(PIN_RX_ESP32, PIN_TX_ESP32, &Serial2, PIN_AUX, PIN_M0, PIN_M1, UART_BPS_RATE_9600);

RTC_DATA_ATTR uint16_t secuencia = 0;
RTC_DATA_ATTR uint8_t  cfgVersion = 0;   // 0 = sin configuración remota (llega en la v1.4)

// ===================================================================
// Batería
// ===================================================================
uint16_t leerBateriaMv() {
  analogSetPinAttenuation(PIN_BATERIA, ADC_11db);
  analogReadMilliVolts(PIN_BATERIA);
  uint32_t suma = 0;
  for (int i = 0; i < N_MUESTRAS_BAT; i++) {
    suma += analogReadMilliVolts(PIN_BATERIA);
    delay(5);
  }
  return (uint16_t)((suma / (float)N_MUESTRAS_BAT) * FACTOR_DIVISOR);
}

// ===================================================================
// Sensor ultrasónico
// ===================================================================
unsigned long medirPulso() {
  digitalWrite(PIN_TRIG, LOW);  delayMicroseconds(5);
  digitalWrite(PIN_TRIG, HIGH); delayMicroseconds(20);
  digitalWrite(PIN_TRIG, LOW);
  return pulseIn(PIN_ECHO, HIGH, 40000);              // 0 = sin eco (timeout ~6,8 m)
}

// Devuelve el estado y deja en distanciaMm la mediana medida (0 si no hubo ecos suficientes)
uint8_t medirDistancia(uint16_t &distanciaMm, int &validas) {
  unsigned long t[N_LECTURAS];
  validas = 0;
  for (int i = 0; i < N_LECTURAS; i++) {
    unsigned long p = medirPulso();
    if (p > 0) t[validas++] = p;
    delay(70);
  }
  if (validas < MIN_LECTURAS_VALIDAS) { distanciaMm = 0; return ESTADO_SIN_ECO; }

  for (int i = 1; i < validas; i++) {                 // ordenar (inserción)
    unsigned long k = t[i];
    int j = i - 1;
    while (j >= 0 && t[j] > k) { t[j + 1] = t[j]; j--; }
    t[j + 1] = k;
  }
  float velocidad = 331.3 + 0.606 * TEMP_C;           // m/s
  float mm = t[validas / 2] * velocidad / 2000.0 * K_CAL;
  distanciaMm = (mm > 65535.0) ? 65535 : (uint16_t)(mm + 0.5);

  if (distanciaMm < DIST_MIN_MM) return ESTADO_ZONA_CIEGA;
  if (distanciaMm > DIST_MAX_MM) return ESTADO_FUERA_ESCALA;
  return ESTADO_OK;
}

// ===================================================================
// E220: configuración (solo al encender)
// ===================================================================
bool configCanonica(const Configuration &cfg) {
  return cfg.ADDH == 0x00 && cfg.ADDL == 0x01 && cfg.CHAN == CANAL_E220 &&
         cfg.SPED.uartBaudRate == UART_BPS_9600 &&
         cfg.SPED.uartParity == MODE_00_8N1 &&
         cfg.SPED.airDataRate == AIR_DATA_RATE_010_24 &&
         cfg.OPTION.subPacketSetting == SPS_200_00 &&
         cfg.OPTION.transmissionPower == POTENCIA_E220 &&
         cfg.TRANSMISSION_MODE.fixedTransmission == FT_TRANSPARENT_TRANSMISSION &&
         cfg.TRANSMISSION_MODE.enableRSSI == RSSI_ENABLED &&
         cfg.TRANSMISSION_MODE.enableLBT == LBT_DISABLED;
}

bool leerConfig(Configuration &out) {
  ResponseStructContainer c = e220ttl.getConfiguration();
  if (c.status.code != 1) return false;               // no tocar memoria inválida
  out = *(Configuration*) c.data;
  c.close();
  return true;
}

void escribirConfigCanonica() {
  Configuration nueva;
  memset(&nueva, 0, sizeof(nueva));
  nueva.ADDH = 0x00; nueva.ADDL = 0x01; nueva.CHAN = CANAL_E220;
  nueva.SPED.uartBaudRate = UART_BPS_9600;
  nueva.SPED.uartParity = MODE_00_8N1;
  nueva.SPED.airDataRate = AIR_DATA_RATE_010_24;
  nueva.OPTION.subPacketSetting = SPS_200_00;
  nueva.OPTION.RSSIAmbientNoise = RSSI_AMBIENT_NOISE_DISABLED;
  nueva.OPTION.transmissionPower = POTENCIA_E220;
  nueva.TRANSMISSION_MODE.fixedTransmission = FT_TRANSPARENT_TRANSMISSION;
  nueva.TRANSMISSION_MODE.enableRSSI = RSSI_ENABLED;
  nueva.TRANSMISSION_MODE.enableLBT = LBT_DISABLED;
  nueva.TRANSMISSION_MODE.WORPeriod = WOR_2000_011;
  nueva.CRYPT.CRYPT_H = 0x00; nueva.CRYPT.CRYPT_L = 0x00;
  e220ttl.setConfiguration(nueva, WRITE_CFG_PWR_DWN_SAVE);
}

void verificarConfiguracion() {
  for (int intento = 1; intento <= 3; intento++) {
    Configuration cfg;
    if (leerConfig(cfg) && configCanonica(cfg)) {
      Serial.println(intento == 1 ? "Config E220: OK" : "Config E220: REPARADA");
      return;
    }
    Serial.printf("Config E220: incorrecta o sin respuesta (intento %d), reescribiendo\n", intento);
    escribirConfigCanonica();
    delay(200);
  }
  Configuration cfg;
  Serial.println(leerConfig(cfg) && configCanonica(cfg) ? "Config E220: REPARADA"
                                                        : "Config E220: NO_REPARABLE");
}

// ===================================================================
// LoRa
// ===================================================================
void esperarAUX(unsigned long timeoutMs) {
  unsigned long t0 = millis();
  while (digitalRead(PIN_AUX) == LOW && millis() - t0 < timeoutMs) delay(5);
  delay(50);
}

void llenarEncabezado(Encabezado &e, uint8_t tipo, uint8_t banderas) {
  e.version = PROTO_VERSION;
  e.tipo = tipo;
  e.nodo = NODO_ID;
  e.secuencia = secuencia++;
  e.banderas = banderas;
}

bool transmitir(const void *trama, uint8_t largo) {
  digitalWrite(PIN_LED_TX, HIGH);
  ResponseStatus rs = e220ttl.sendMessage(trama, largo);
  esperarAUX(3000);
  digitalWrite(PIN_LED_TX, LOW);
  Serial.print("TX: ");
  const uint8_t *b = (const uint8_t *)trama;
  for (uint8_t i = 0; i < largo; i++) Serial.printf("%02X", b[i]);
  Serial.printf(" -> %s\n", rs.getResponseDescription().c_str());
  return rs.code == 1;
}

// ===================================================================
// Sueño profundo
// ===================================================================
void dormir(uint32_t segundos) {
  Serial2.flush();
  esperarAUX(1000);
  e220ttl.setMode(MODE_3_SLEEP);
  delay(50);
  gpio_hold_en((gpio_num_t)PIN_TX_ESP32);       // TX en reposo (alto), sin desconectar el UART
  gpio_hold_en((gpio_num_t)PIN_M0);
  gpio_hold_en((gpio_num_t)PIN_M1);
  gpio_deep_sleep_hold_en();
  Serial.printf("Durmiendo %lu s\n", (unsigned long)segundos);
  Serial.flush();
  esp_sleep_enable_timer_wakeup((uint64_t)segundos * 1000000ULL);
  esp_deep_sleep_start();
}

// ===================================================================
// Programa principal
// ===================================================================
void setup() {
  gpio_deep_sleep_hold_dis();
  gpio_hold_dis((gpio_num_t)PIN_M0);
  gpio_hold_dis((gpio_num_t)PIN_M1);
  gpio_hold_dis((gpio_num_t)PIN_TX_ESP32);

  Serial.begin(115200);
  pinMode(PIN_LED_ESTADO, OUTPUT);
  pinMode(PIN_LED_TX, OUTPUT);
  pinMode(PIN_TRIG, OUTPUT);
  pinMode(PIN_ECHO, INPUT);
  pinMode(PIN_AUX, INPUT);

  digitalWrite(PIN_LED_ESTADO, HIGH);
  delay(50);
  digitalWrite(PIN_LED_ESTADO, LOW);

  esp_reset_reason_t motivo = esp_reset_reason();
  bool despertar = (motivo == ESP_RST_DEEPSLEEP);

  uint16_t batMv = leerBateriaMv();
  Serial.printf("Bateria: %u mV\n", batMv);

  e220ttl.begin();

  // Solo al encender o reiniciar: verificar el E220 y avisar el arranque
  if (!despertar) {
    verificarConfiguracion();
    e220ttl.setMode(MODE_0_NORMAL);
    TramaArranque a;
    llenarEncabezado(a.enc, TIPO_ARRANQUE, BAND_ARRANQUE);
    a.bateria_mv = batMv;
    a.cfg_version = cfgVersion;
    a.fw_mayor = FW_MAYOR;
    a.fw_menor = FW_MENOR;
    a.motivo = (uint8_t)motivo;
    transmitir(&a, sizeof(a));
  }
  e220ttl.setMode(MODE_0_NORMAL);

  TramaPastura t;
  t.bateria_mv = batMv;
  t.cfg_version = cfgVersion;

  // Batería crítica: no se mide; se informa cada hora con la bandera
  if (batMv >= BAT_AUSENTE_MV && batMv < BAT_CORTE_MV) {
    llenarEncabezado(t.enc, TIPO_PASTURA, BAND_BATERIA_CRITICA);
    t.estado = ESTADO_SIN_MEDICION;
    t.distancia_mm = 0;
    transmitir(&t, sizeof(t));
    dormir(INTERVALO_BAT_BAJA_S);
  }

  int validas = 0;
  uint16_t distMm = 0;
  uint8_t estado = medirDistancia(distMm, validas);
  Serial.printf("Lecturas validas: %d/%d | Distancia: %u mm | Estado: %u\n",
                validas, N_LECTURAS, distMm, estado);

  llenarEncabezado(t.enc, TIPO_PASTURA, 0);
  t.estado = estado;
  t.distancia_mm = distMm;
  transmitir(&t, sizeof(t));

  dormir(INTERVALO_S);
}

void loop() {}
