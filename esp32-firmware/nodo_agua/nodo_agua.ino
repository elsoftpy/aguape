/*
  NODO DE AGUA — NODO 1 (anteproyecto v5)
  ---------------------------------------
  v1.1 — configuración remota del intervalo (CU-06), igual que nodo_pastura v1.4: cada
         lectura sale con BAND_ESCUCHA, el nodo espera 2 s la trama 0x20 y guarda la
         versión y el intervalo en NVS.
  v1.0 — primera versión. Basado en nodo_ambiental v1.0 / nodo_pastura v1.3.2 (protocolo
         binario v1, configuración del E220 solo al encender, trama de arranque, sueño
         profundo con TX/M0/M1 retenidos y detección de batería crítica / ausente).

  Variables (crudas o con escalado entero; la conversión fina va en el backend, igual que
  en el nodo ambiental, porque las calibraciones se hacen con el nodo instalado):
    AJ-SR04M  -> distancia del transductor a la superficie del agua (mm, mediana de 7 ecos).
                 El backend calcula el nivel: nivel = altura de montaje - distancia.
    DS18B20   -> temperatura del agua (°C x100)
    PH-4502C  -> tensión de Po del lado del sensor (mV). pH = f(mV) con la calibración
                 de tampones guardada en el backend.
    KS0414    -> tensión de S del lado del sensor (mV). Turbidez = f(mV) con la calibración.
    TDS V1.0  -> tensión de A (mV). Conductividad = f(mV, temperatura del agua) en el backend.

  Trama 0x02 — Lectura de agua (20 bytes, little-endian):
    0-5   encabezado (version, tipo, nodo, secuencia, banderas)
    6-7   bateria_mv      uint16  mV
    8     cfg_version     uint8
    9     estado          uint8   bit0 nivel, bit1 temp, bit2 pH, bit3 turbidez, bit4 conductividad
                                  (1 = falla); 0xFF = no se midió (batería crítica)
    10-11 distancia_mm    uint16  mm del transductor a la superficie (0 si no hubo eco)
    12-13 temp_agua_c100  int16   °C x 100
    14-15 ph_mv           uint16  mV de Po (lado sensor)
    16-17 turbidez_mv     uint16  mV de S (lado sensor)
    18-19 tds_mv          uint16  mV de A del TDS
  En Python: struct.unpack('<HBBHhHHH', datos[6:20])

  Ciclo: despierta -> batería -> nivel -> pH y turbidez -> TDS -> temperatura -> transmite -> duerme.
  El pH se lee con el TDS apagado y el AJ-SR04M en reposo (interferencia).
  Al encender (no al despertar): verifica el E220 y envía la trama de arranque.

  Hardware (ver "Perfboard Nodo 1" y "Cajas de los nodos"):
    E220: TXD->G16, RXD->G17 (51k a 3V3), AUX->G4, M0->G18, M1->G19, VCC->5V (LM2596)
    AJ-SR04M: TRIG->G13, ECHO->G27 (INPUT_PULLDOWN: un ECHO cortado da SIN_ECO), VCC 3V3
    DS18B20: DQ->G33 (pull-up 4,7k en el perfboard), VCC 3V3
    pH y turbidez: 5V conmutado por IRF9540N + 2N3904 desde G23 (alto = encendidos)
      pH Po -> divisor 100k/100k -> G36 (VP) | turbidez S -> divisor 100k/100k -> G39 (VN)
      El switch de la placa de turbidez tiene que estar en A.
    TDS: + desde G32 (lo alimenta el GPIO), A -> G35
    Divisor de batería: 100k + 100k / 51k -> G34 (factor 4,99)
    LED verde (latido): G25 | LED azul (transmisión): G26

  Librerías: EByte LoRa E220 1.0.8 | OneWire 2.3.8 | DallasTemperature 4.0.6
  Núcleo esp32 3.3.12 | Placa en Arduino IDE: ESP32 Dev Module
*/

#include "Arduino.h"
#define FREQUENCY_900
#define E220_30
#include "LoRa_E220.h"
#include <OneWire.h>
#include <DallasTemperature.h>
#include "driver/gpio.h"
#include "esp_system.h"
#include <Preferences.h>

// ---------------- Identificación ----------------
#define NODO_ID        1
#define FW_MAYOR       1
#define FW_MENOR       1

// ---------------- Protocolo ----------------
#define PROTO_VERSION      1
#define TIPO_AGUA          0x02
#define TIPO_ARRANQUE      0x10
#define TIPO_CONFIG        0x20

#define FALLA_NIVEL    0x01
#define FALLA_TEMP     0x02
#define FALLA_PH       0x04
#define FALLA_TURB     0x08
#define FALLA_COND     0x10
#define ESTADO_SIN_MEDICION 0xFF     // batería crítica: no se midió

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

struct __attribute__((packed)) TramaAgua {
  Encabezado enc;
  uint16_t bateria_mv;
  uint8_t  cfg_version;
  uint8_t  estado;
  uint16_t distancia_mm;
  int16_t  temp_agua_c100;
  uint16_t ph_mv;
  uint16_t turbidez_mv;
  uint16_t tds_mv;
};
static_assert(sizeof(TramaAgua) == 20, "La trama de agua debe medir 20 bytes");

struct __attribute__((packed)) TramaArranque {
  Encabezado enc;
  uint16_t bateria_mv;
  uint8_t  cfg_version;
  uint8_t  fw_mayor;
  uint8_t  fw_menor;
  uint8_t  motivo;
};
static_assert(sizeof(TramaArranque) == 12, "La trama de arranque debe medir 12 bytes");

struct __attribute__((packed)) TramaConfig {      // gateway -> nodo
  Encabezado enc;
  uint8_t  cfg_version;
  uint16_t intervalo_min;
};                                   // 9 bytes
static_assert(sizeof(TramaConfig) == 9, "La trama de configuración debe medir 9 bytes");

// ---------------- Pines ----------------
#define PIN_AUX        4
#define PIN_M0         18
#define PIN_M1         19
#define PIN_RX_ESP32   16
#define PIN_TX_ESP32   17
#define PIN_TRIG       13
#define PIN_ECHO       27
#define PIN_DS18B20    33
#define PIN_SENS_5V    23     // MOSFET de pH y turbidez
#define PIN_TDS_VCC    32
#define PIN_TDS_A      35
#define PIN_PH_A       36     // VP
#define PIN_TURB_A     39     // VN
#define PIN_BATERIA    34
#define PIN_LED_ESTADO 25
#define PIN_LED_TX     26

// ---------------- Ciclo ----------------
#define INTERVALO_DEFECTO_S 60    // hasta recibir la configuración remota (pruebas: 60)
#define VENTANA_ESCUCHA_MS  2000  // espera de la trama 0x20 después de cada lectura
#define FIN_TRAMA_MS        30    // silencio en el UART que marca el fin de una trama recibida
#define INTERVALO_MIN_MIN   15    // rango que acepta el nodo (el mismo que valida el backend)
#define INTERVALO_MAX_MIN   120

// ---------------- Nivel (AJ-SR04M) ----------------
#define DIST_MIN_MM          250    // zona ciega del AJ-SR04M
#define DIST_MAX_MM          6000   // alcance del sensor
#define N_LECTURAS           7
#define MIN_LECTURAS_VALIDAS 4
#define TEMP_AIRE_C          25.0   // temperatura del aire supuesta para la velocidad del sonido
#define K_CAL                1.000

// ---------------- Calidad de agua ----------------
#define SENS_ESTAB_MS      2000   // pH y turbidez encendidos antes de leer
#define TDS_ESTAB_MS       1000
#define N_MUESTRAS_ADC     32
#define DIV_PH             2.0    // R4 = R5
#define DIV_TURB           2.0    // R6 = R7
#define PISO_DIVIDIDO_MV   300    // 2 x piso del ADC: con menos, la placa no entrega señal
#define TECHO_DIVIDIDO_MV  4900   // cerca de la alimentación: placa saturada o turbidez en modo D
#define TECHO_TDS_MV       2500   // por encima del máximo de la placa TDS

// ---------------- Batería ----------------
#define FACTOR_DIVISOR       4.99
#define N_MUESTRAS_BAT       16
#define BAT_CORTE_MV         6400   // 3,2 V por celda
#define BAT_AUSENTE_MV       3000   // por debajo: sin batería (USB o switch apagado)
#define INTERVALO_BAT_BAJA_S 3600

// ---------------- E220 ----------------
#define CANAL_E220     73
#define POTENCIA_E220  POWER_30     // banco: POWER_21 | campo: POWER_30

LoRa_E220 e220ttl(PIN_RX_ESP32, PIN_TX_ESP32, &Serial2, PIN_AUX, PIN_M0, PIN_M1, UART_BPS_RATE_9600);
OneWire oneWire(PIN_DS18B20);
DallasTemperature ds(&oneWire);

RTC_DATA_ATTR uint16_t secuencia = 0;
RTC_DATA_ATTR uint8_t  cfgVersion = 0;   // 0 = sin configuración remota
RTC_DATA_ATTR uint32_t intervaloS = INTERVALO_DEFECTO_S;
RTC_DATA_ATTR bool     cfgRechazada = false;   // se informa en la próxima lectura

// ===================================================================
// Lecturas analógicas
// ===================================================================
uint16_t leerMv(int pin, int muestras) {
  analogSetPinAttenuation(pin, ADC_11db);
  analogReadMilliVolts(pin);                        // primera lectura descartada
  uint32_t suma = 0;
  for (int i = 0; i < muestras; i++) {
    suma += analogReadMilliVolts(pin);
    delayMicroseconds(200);
  }
  return (uint16_t)(suma / muestras);
}

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
// Nivel: AJ-SR04M (mediana de 7 ecos, como el nodo de pastura)
// ===================================================================
unsigned long medirPulso() {
  digitalWrite(PIN_TRIG, LOW);  delayMicroseconds(5);
  digitalWrite(PIN_TRIG, HIGH); delayMicroseconds(20);
  digitalWrite(PIN_TRIG, LOW);
  return pulseIn(PIN_ECHO, HIGH, 40000);            // 0 = sin eco (timeout ~6,8 m)
}

// true si la lectura es válida; deja en distanciaMm la mediana (0 si no hubo ecos suficientes)
bool medirDistancia(uint16_t &distanciaMm, int &validas) {
  unsigned long t[N_LECTURAS];
  validas = 0;
  for (int i = 0; i < N_LECTURAS; i++) {
    unsigned long p = medirPulso();
    if (p > 0) t[validas++] = p;
    delay(70);
  }
  if (validas < MIN_LECTURAS_VALIDAS) { distanciaMm = 0; return false; }

  for (int i = 1; i < validas; i++) {               // ordenar (inserción)
    unsigned long k = t[i];
    int j = i - 1;
    while (j >= 0 && t[j] > k) { t[j + 1] = t[j]; j--; }
    t[j + 1] = k;
  }
  float velocidad = 331.3 + 0.606 * TEMP_AIRE_C;    // m/s
  float mm = t[validas / 2] * velocidad / 2000.0 * K_CAL;
  distanciaMm = (mm > 65535.0) ? 65535 : (uint16_t)(mm + 0.5);
  return distanciaMm >= DIST_MIN_MM && distanciaMm <= DIST_MAX_MM;
}

// ===================================================================
// Sensores
// ===================================================================
void apagarSensores() {
  digitalWrite(PIN_SENS_5V, LOW);
  digitalWrite(PIN_TDS_VCC, LOW);
}

void medirSensores(TramaAgua &t) {
  uint8_t fallas = 0;

  // 1) Nivel, con pH, turbidez y TDS apagados
  int validas = 0;
  if (!medirDistancia(t.distancia_mm, validas)) fallas |= FALLA_NIVEL;

  // 2) Temperatura del agua: se lanza la conversión ahora y se lee al final
  bool dsOk = false;
  ds.begin();
  if (ds.getDeviceCount() > 0) {
    ds.setResolution(12);
    ds.setWaitForConversion(false);
    ds.requestTemperatures();                        // ~750 ms, en paralelo con lo que sigue
    dsOk = true;
  }
  unsigned long tConv = millis();

  // 3) pH y turbidez (TDS apagado, AJ-SR04M en reposo)
  digitalWrite(PIN_SENS_5V, HIGH);
  delay(SENS_ESTAB_MS);
  t.ph_mv       = (uint16_t)(leerMv(PIN_PH_A, N_MUESTRAS_ADC) * DIV_PH);
  t.turbidez_mv = (uint16_t)(leerMv(PIN_TURB_A, N_MUESTRAS_ADC) * DIV_TURB);
  digitalWrite(PIN_SENS_5V, LOW);
  if (t.ph_mv <= PISO_DIVIDIDO_MV || t.ph_mv >= TECHO_DIVIDIDO_MV) fallas |= FALLA_PH;
  if (t.turbidez_mv <= PISO_DIVIDIDO_MV || t.turbidez_mv >= TECHO_DIVIDIDO_MV) fallas |= FALLA_TURB;

  // 4) TDS (pH apagado). El piso del ADC no es falla: es agua con muy pocas sales
  digitalWrite(PIN_TDS_VCC, HIGH);
  delay(TDS_ESTAB_MS);
  t.tds_mv = leerMv(PIN_TDS_A, N_MUESTRAS_ADC);
  digitalWrite(PIN_TDS_VCC, LOW);
  if (t.tds_mv >= TECHO_TDS_MV) fallas |= FALLA_COND;

  // 5) Temperatura del agua
  float temp = DEVICE_DISCONNECTED_C;
  if (dsOk) {
    while (millis() - tConv < 800) delay(5);
    temp = ds.getTempCByIndex(0);
  }
  if (!dsOk || temp == DEVICE_DISCONNECTED_C || temp == 85.0f) {   // 85 °C = valor de encendido
    fallas |= FALLA_TEMP;
    t.temp_agua_c100 = 0;
  } else {
    t.temp_agua_c100 = (int16_t)constrain(lroundf(temp * 100.0f), -32768L, 32767L);
  }

  t.estado = fallas;
  Serial.printf("Dist: %u mm (%d/%d ecos) | T: %.2f C | pH: %u mV | Turb: %u mV | TDS: %u mV | Fallas: 0x%02X\n",
                t.distancia_mm, validas, N_LECTURAS, temp, t.ph_mv, t.turbidez_mv, t.tds_mv, fallas);
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
// Configuración remota (CU-06)
// ===================================================================
// Se guarda en NVS (flash) para que sobreviva un corte de alimentación;
// al despertar del sueño profundo alcanza con las variables RTC.
void cargarConfiguracion() {
  Preferences prefs;
  prefs.begin("aguape", true);
  cfgVersion = prefs.getUChar("cfg_version", 0);
  intervaloS = prefs.getUInt("intervalo_s", INTERVALO_DEFECTO_S);
  prefs.end();
  Serial.printf("Config remota: version %u, intervalo %lu s\n", cfgVersion, (unsigned long)intervaloS);
}

void guardarConfiguracion() {
  Preferences prefs;
  prefs.begin("aguape", false);
  prefs.putUChar("cfg_version", cfgVersion);
  prefs.putUInt("intervalo_s", intervaloS);
  prefs.end();
}

// Lee una trama recibida (ráfaga de bytes + byte de RSSI). Termina con FIN_TRAMA_MS de
// silencio, o devuelve 0 si llega a limiteMs sin haber recibido nada.
uint8_t recibirTrama(uint8_t *buf, uint8_t max, unsigned long limiteMs) {
  uint8_t n = 0;
  unsigned long ultimo = millis();
  while (true) {
    if (Serial2.available()) {
      uint8_t b = Serial2.read();
      if (n < max) buf[n++] = b;
      ultimo = millis();
    } else if (n > 0 && millis() - ultimo >= FIN_TRAMA_MS) {
      return n;
    } else if (n == 0 && millis() >= limiteMs) {
      return 0;
    } else {
      delay(1);
    }
  }
}

// Después de cada lectura (enviada con BAND_ESCUCHA) el nodo espera la trama 0x20 durante
// VENTANA_ESCUCHA_MS. En modo transparente también oye las lecturas de otros nodos: se
// descarta todo lo que no sea una configuración dirigida a este NODO_ID.
void escucharConfiguracion() {
  while (Serial2.available()) Serial2.read();
  uint8_t buf[32];
  unsigned long limite = millis() + VENTANA_ESCUCHA_MS;
  while (millis() < limite) {
    uint8_t n = recibirTrama(buf, sizeof(buf), limite);
    if (n != sizeof(TramaConfig) + 1) continue;       // + 1 byte de RSSI
    TramaConfig c;
    memcpy(&c, buf, sizeof(c));
    if (c.enc.version != PROTO_VERSION || c.enc.tipo != TIPO_CONFIG || c.enc.nodo != NODO_ID) continue;
    if (c.cfg_version == 0 || c.intervalo_min < INTERVALO_MIN_MIN || c.intervalo_min > INTERVALO_MAX_MIN) {
      cfgRechazada = true;
      Serial.printf("Config remota RECHAZADA: version %u, intervalo %u min\n", c.cfg_version, c.intervalo_min);
      return;
    }
    cfgVersion = c.cfg_version;
    intervaloS = (uint32_t)c.intervalo_min * 60;
    guardarConfiguracion();
    Serial.printf("Config remota aplicada: version %u, intervalo %u min\n", cfgVersion, c.intervalo_min);
    return;
  }
}

// ===================================================================
// Sueño profundo
// ===================================================================
void dormir(uint32_t segundos) {
  Serial2.flush();
  esperarAUX(1000);
  e220ttl.setMode(MODE_3_SLEEP);
  delay(50);
  apagarSensores();
  gpio_hold_en((gpio_num_t)PIN_TX_ESP32);       // TX en reposo (alto), sin desconectar el UART
  gpio_hold_en((gpio_num_t)PIN_M0);
  gpio_hold_en((gpio_num_t)PIN_M1);
  gpio_hold_en((gpio_num_t)PIN_SENS_5V);        // pH y turbidez apagados durante el sueño
  gpio_hold_en((gpio_num_t)PIN_TDS_VCC);        // TDS apagado durante el sueño
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
  gpio_hold_dis((gpio_num_t)PIN_SENS_5V);
  gpio_hold_dis((gpio_num_t)PIN_TDS_VCC);

  Serial.begin(115200);
  pinMode(PIN_SENS_5V, OUTPUT);
  pinMode(PIN_TDS_VCC, OUTPUT);
  apagarSensores();
  pinMode(PIN_LED_ESTADO, OUTPUT);
  pinMode(PIN_LED_TX, OUTPUT);
  pinMode(PIN_AUX, INPUT);
  pinMode(PIN_TRIG, OUTPUT);
  digitalWrite(PIN_TRIG, LOW);
  pinMode(PIN_ECHO, INPUT_PULLDOWN);

  digitalWrite(PIN_LED_ESTADO, HIGH);
  delay(50);
  digitalWrite(PIN_LED_ESTADO, LOW);

  esp_reset_reason_t motivo = esp_reset_reason();
  bool despertar = (motivo == ESP_RST_DEEPSLEEP);
  if (!despertar) cargarConfiguracion();

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

  TramaAgua t;
  memset(&t, 0, sizeof(t));
  t.bateria_mv = batMv;
  t.cfg_version = cfgVersion;

  // Batería crítica: no se mide; se informa cada hora con la bandera
  if (batMv >= BAT_AUSENTE_MV && batMv < BAT_CORTE_MV) {
    llenarEncabezado(t.enc, TIPO_AGUA, BAND_BATERIA_CRITICA);
    t.estado = ESTADO_SIN_MEDICION;
    transmitir(&t, sizeof(t));
    dormir(INTERVALO_BAT_BAJA_S);
  }

  medirSensores(t);
  llenarEncabezado(t.enc, TIPO_AGUA, BAND_ESCUCHA | (cfgRechazada ? BAND_CFG_RECHAZADA : 0));
  cfgRechazada = false;
  transmitir(&t, sizeof(t));

  escucharConfiguracion();
  dormir(intervaloS);
}

void loop() {}
