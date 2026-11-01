/*
  NODO AMBIENTAL — NODO 3 (anteproyecto v5)
  -----------------------------------------
  v1.1 — configuración remota del intervalo (CU-06), igual que nodo_pastura v1.4: cada
         lectura sale con BAND_ESCUCHA, el nodo espera 2 s la trama 0x20 y guarda la
         versión y el intervalo en NVS.
  v1.0 — primera versión. Basado en nodo_pastura v1.3.2 (protocolo binario v1,
         configuración del E220 solo al encender, trama de arranque, sueño profundo
         con TX/M0/M1 retenidos y detección de batería crítica / ausente).

  Variables (todas crudas o con escalado entero; la conversión fina va en el backend):
    SHT3X   (I2C 0x44) -> temperatura del aire (°C x100) y humedad relativa (% x10)
    BH1750  (I2C 0x23) -> iluminancia en unidades de 2 lx (indicador de radiación solar)
                          MTreg = 31 para ampliar el rango hasta ~121 000 lx (sol pleno)
    SEN0575 (I2C 0x1D) -> contador ACUMULADO de basculadas (uint16, módulo 65536).
                          El backend calcula la lluvia del intervalo como la diferencia
                          con la trama anterior (x 0,2794 mm o el valor calibrado).
    HD-38   (analógico) -> mV crudos de AO en G35; VCC conmutado desde G32 (~100 ms por
                          lectura, para limitar la electrólisis). La conversión a %
                          usa la calibración seco/saturado guardada en el backend.

  Trama 0x03 — Lectura ambiental (20 bytes, little-endian):
    0-5   encabezado (version, tipo, nodo, secuencia, banderas)
    6-7   bateria_mv      uint16  mV
    8     cfg_version     uint8
    9     estado          uint8   bit0 temp, bit1 hum, bit2 suelo, bit3 luz, bit4 lluvia (1 = falla)
                                  0xFF = no se midió (batería crítica)
    10-11 temp_aire_c100  int16   °C x 100
    12-13 hum_aire_x10    uint16  % HR x 10
    14-15 suelo_mv        uint16  mV crudos del HD-38
    16-17 luz_lx_div2     uint16  lux / 2
    18-19 basculadas      uint16  contador acumulado del pluviómetro (módulo 65536)

  Ciclo: despierta -> batería -> sensores -> transmite -> duerme.
  Al encender (no al despertar): verifica el E220, espera al SEN0575 y envía la trama de arranque.

  Hardware (ver "Montaje Nodo 3"):
    E220: TXD->G16, RXD->G17 (51k a 3V3), AUX->G4, M0->G18, M1->G19, VCC->5V (LM2596)
    I2C: SDA->G21, SCL->G22 (SHT3X, BH1750, SEN0575 alimentados desde 3V3)
    HD-38: VCC->G32, AO->G35
    Divisor de batería: 100k + 100k / 51k -> G34 (factor 4,99)
    LED verde (latido): G25 | LED azul (transmisión): G26

  Librerías: EByte LoRa E220 1.0.8 | Adafruit SHT31 2.2.2 (+ BusIO) |
             BH1750 (Christopher Laws) 1.3.0 | DFRobot_RainfallSensor + DFRobot_RTU
  Núcleo esp32 3.3.12 | Placa en Arduino IDE: ESP32 Dev Module
*/

#include "Arduino.h"
#define FREQUENCY_900
#define E220_30
#include "LoRa_E220.h"
#include <Wire.h>
#include <Adafruit_SHT31.h>
#include <BH1750.h>
#include "DFRobot_RainfallSensor.h"
#include "driver/gpio.h"
#include "esp_system.h"
#include <Preferences.h>

// ---------------- Identificación ----------------
#define NODO_ID        3
#define FW_MAYOR       1
#define FW_MENOR       1

// ---------------- Protocolo ----------------
#define PROTO_VERSION      1
#define TIPO_AMBIENTAL     0x03
#define TIPO_ARRANQUE      0x10
#define TIPO_CONFIG        0x20

#define FALLA_TEMP     0x01
#define FALLA_HUM      0x02
#define FALLA_SUELO    0x04
#define FALLA_LUZ      0x08
#define FALLA_LLUVIA   0x10
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

struct __attribute__((packed)) TramaAmbiental {
  Encabezado enc;
  uint16_t bateria_mv;
  uint8_t  cfg_version;
  uint8_t  estado;
  int16_t  temp_aire_c100;
  uint16_t hum_aire_x10;
  uint16_t suelo_mv;
  uint16_t luz_lx_div2;
  uint16_t basculadas;
};
static_assert(sizeof(TramaAmbiental) == 20, "La trama ambiental debe medir 20 bytes");

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
#define PIN_SDA        21
#define PIN_SCL        22
#define PIN_SUELO_VCC  32
#define PIN_SUELO_AO   35
#define PIN_BATERIA    34
#define PIN_LED_ESTADO 25
#define PIN_LED_TX     26

// ---------------- Ciclo ----------------
#define INTERVALO_DEFECTO_S 60    // hasta recibir la configuración remota (pruebas: 60)
#define VENTANA_ESCUCHA_MS  2000  // espera de la trama 0x20 después de cada lectura
#define FIN_TRAMA_MS        30    // silencio en el UART que marca el fin de una trama recibida
#define INTERVALO_MIN_MIN   15    // rango que acepta el nodo (el mismo que valida el backend)
#define INTERVALO_MAX_MIN   120

// ---------------- Sensores ----------------
#define DIR_SHT3X          0x44
#define DIR_BH1750         0x23
#define BH1750_MTREG       31     // mínimo: rango ~121 000 lx (por defecto 69 satura a ~54 600 lx)
#define SUELO_ESTAB_MS     100
#define N_MUESTRAS_SUELO   16
#define ESPERA_SEN0575_MS  1000   // el SEN0575 necesita ~1 s después de energizarse

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
Adafruit_SHT31 sht;
BH1750 bh(DIR_BH1750);
DFRobot_RainfallSensor_I2C pluvio(&Wire);

RTC_DATA_ATTR uint16_t secuencia = 0;
RTC_DATA_ATTR uint8_t  cfgVersion = 0;   // 0 = sin configuración remota
RTC_DATA_ATTR uint32_t intervaloS = INTERVALO_DEFECTO_S;
RTC_DATA_ATTR bool     cfgRechazada = false;   // se informa en la próxima lectura

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
// Sensores
// ===================================================================
uint16_t leerSueloMv() {
  analogSetPinAttenuation(PIN_SUELO_AO, ADC_11db);
  digitalWrite(PIN_SUELO_VCC, HIGH);
  delay(SUELO_ESTAB_MS);
  analogReadMilliVolts(PIN_SUELO_AO);
  uint32_t suma = 0;
  for (int i = 0; i < N_MUESTRAS_SUELO; i++) suma += analogReadMilliVolts(PIN_SUELO_AO);
  digitalWrite(PIN_SUELO_VCC, LOW);   // apagado para limitar la electrólisis
  return (uint16_t)(suma / N_MUESTRAS_SUELO);
}

void medirSensores(TramaAmbiental &t) {
  uint8_t fallas = 0;

  // SHT3X: temperatura y humedad del aire
  float temp = NAN, hum = NAN;
  if (sht.begin(DIR_SHT3X)) {
    sht.heater(false);
    if (!sht.readBoth(&temp, &hum)) { temp = NAN; hum = NAN; }
  }
  if (isnan(temp)) { fallas |= FALLA_TEMP; t.temp_aire_c100 = 0; }
  else t.temp_aire_c100 = (int16_t)constrain(lroundf(temp * 100.0f), -32768L, 32767L);
  if (isnan(hum))  { fallas |= FALLA_HUM;  t.hum_aire_x10 = 0; }
  else t.hum_aire_x10 = (uint16_t)constrain(lroundf(hum * 10.0f), 0L, 1000L);

  // BH1750: iluminancia
  float lux = -1;
  if (bh.begin(BH1750::ONE_TIME_HIGH_RES_MODE, DIR_BH1750, &Wire) && bh.setMTreg(BH1750_MTREG)) {
    bh.configure(BH1750::ONE_TIME_HIGH_RES_MODE);
    unsigned long t0 = millis();
    while (!bh.measurementReady(true) && millis() - t0 < 500) delay(5);
    lux = bh.readLightLevel();                // -1 / -2 indican error
  }
  if (lux < 0) { fallas |= FALLA_LUZ; t.luz_lx_div2 = 0; }
  else t.luz_lx_div2 = (uint16_t)constrain(lroundf(lux / 2.0f), 0L, 65535L);

  // SEN0575: contador acumulado de basculadas (una sola lectura del contador crudo)
  if (pluvio.begin()) t.basculadas = (uint16_t)(pluvio.getRawData() & 0xFFFF);
  else { fallas |= FALLA_LLUVIA; t.basculadas = 0; }

  // HD-38: humedad del suelo (sin detección de falla confiable: un cable cortado se
  // confunde con suelo seco; se revisa en el backend por valores fuera de lo esperable)
  t.suelo_mv = leerSueloMv();

  t.estado = fallas;
  Serial.printf("T: %.2f C | HR: %.1f %% | Luz: %.1f lx | Basculadas: %u | Suelo: %u mV | Fallas: 0x%02X\n",
                temp, hum, lux, t.basculadas, t.suelo_mv, fallas);
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
  digitalWrite(PIN_SUELO_VCC, LOW);
  gpio_hold_en((gpio_num_t)PIN_TX_ESP32);       // TX en reposo (alto), sin desconectar el UART
  gpio_hold_en((gpio_num_t)PIN_M0);
  gpio_hold_en((gpio_num_t)PIN_M1);
  gpio_hold_en((gpio_num_t)PIN_SUELO_VCC);      // HD-38 apagado durante el sueño
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
  gpio_hold_dis((gpio_num_t)PIN_SUELO_VCC);

  Serial.begin(115200);
  pinMode(PIN_LED_ESTADO, OUTPUT);
  pinMode(PIN_LED_TX, OUTPUT);
  pinMode(PIN_AUX, INPUT);
  pinMode(PIN_SUELO_VCC, OUTPUT);
  digitalWrite(PIN_SUELO_VCC, LOW);

  digitalWrite(PIN_LED_ESTADO, HIGH);
  delay(50);
  digitalWrite(PIN_LED_ESTADO, LOW);

  esp_reset_reason_t motivo = esp_reset_reason();
  bool despertar = (motivo == ESP_RST_DEEPSLEEP);
  if (!despertar) cargarConfiguracion();

  uint16_t batMv = leerBateriaMv();
  Serial.printf("Bateria: %u mV\n", batMv);

  Wire.begin(PIN_SDA, PIN_SCL);
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
    while (millis() < ESPERA_SEN0575_MS) delay(10);   // el SEN0575 recién se energizó
  }
  e220ttl.setMode(MODE_0_NORMAL);

  TramaAmbiental t;
  memset(&t, 0, sizeof(t));
  t.bateria_mv = batMv;
  t.cfg_version = cfgVersion;

  // Batería crítica: no se mide; se informa cada hora con la bandera
  if (batMv >= BAT_AUSENTE_MV && batMv < BAT_CORTE_MV) {
    llenarEncabezado(t.enc, TIPO_AMBIENTAL, BAND_BATERIA_CRITICA);
    t.estado = ESTADO_SIN_MEDICION;
    transmitir(&t, sizeof(t));
    dormir(INTERVALO_BAT_BAJA_S);
  }

  medirSensores(t);
  llenarEncabezado(t.enc, TIPO_AMBIENTAL, BAND_ESCUCHA | (cfgRechazada ? BAND_CFG_RECHAZADA : 0));
  cfgRechazada = false;
  transmitir(&t, sizeof(t));

  escucharConfiguracion();
  dormir(intervaloS);
}

void loop() {}
