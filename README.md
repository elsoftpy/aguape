# Aguape: sistema de monitoreo y alertas productivas mediante IoT

Sistema de monitoreo y generación de alertas productivas para la etapa de terminación bovina del establecimiento ganadero Aguape (Chaco Central, Paraguay). Fue desarrollado como trabajo final de grado de la carrera de Ingeniería Informática de la Universidad de la Integración de las Américas (UNIDA), 2026.

Los nodos sensores miden el nivel y la calidad del agua de los bebederos, la altura de la pastura y las condiciones ambientales (temperatura, humedad del aire y del suelo, radiación solar y lluvia). Las lecturas se transmiten por LoRa a un servidor local instalado en una Raspberry Pi 5, que las almacena, calcula el índice de temperatura y humedad (ITH), genera alertas cuando se superan los umbrales configurados y las notifica por correo electrónico. Los datos se sincronizan con una plataforma en la nube de solo lectura, y el sistema sigue operando sin conexión a internet.

## Arquitectura

| Componente | Tecnología | Ubicación |
|---|---|---|
| Nodos sensores | ESP32, módulo Ebyte E220-900T30D, firmware en C++ (Arduino) | Potrero |
| Gateway LoRa | Módulo E220-900T30D conectado por UART a la Raspberry Pi 5 | Oficina de control |
| Servicio receptor LoRa | Python 3 y pyserial, servicio de systemd | Raspberry Pi 5 |
| Aplicación web local | Laravel 13, Livewire 4, Fortify, Spatie Laravel Permission, Laravel Auditing, Tailwind CSS 4 | Raspberry Pi 5 |
| Servidor web | Nginx con HTTPS y PHP-FPM 8.4 | Raspberry Pi 5 |
| Base de datos local | MariaDB 11.8 | Raspberry Pi 5 |
| Cola y tareas programadas | Colas de Laravel en base de datos, systemd y cron | Raspberry Pi 5 |
| Plataforma en la nube | Laravel en modo de solo lectura, MySQL, HTTPS | Cloudways sobre DigitalOcean |

## Estructura del repositorio

| Carpeta | Contenido |
|---|---|
| `app/` | Modelos, acciones de dominio, controladores, componentes de Livewire, middleware, observadores y comandos de consola |
| `config/` | Configuración de la aplicación y de los paquetes |
| `database/` | Migraciones y seeders de roles, permisos y variables |
| `resources/` | Vistas Blade, estilos y scripts de la interfaz |
| `routes/` | Rutas web, rutas de la API y tareas programadas |
| `public/` | Punto de entrada de la aplicación y recursos compilados |
| `lora-service/` | Servicio receptor LoRa: decodificador de tramas, configurador del E220 y calibración de ejemplo |
| `esp32-firmware/` | Firmware de los nodos de agua, pastura y ambiental, y programas auxiliares de configuración y prueba |

## Requisitos

- **Servidor local:** Raspberry Pi 5 de 8 GB, microSD de 32 GB, fuente de 5 V y 5 A, con Raspberry Pi OS Lite de 64 bits (Debian 13).
- **Software del servidor local:** PHP 8.4 con PHP-FPM, Composer, MariaDB 11.8, Nginx, mkcert, Node.js 20.19 o superior, Python 3 con pyserial y Git.
- **Plataforma en la nube:** servidor administrado con PHP 8.4 o superior, MySQL, dominio propio y certificado TLS.
- **Firmware:** Arduino IDE con el núcleo esp32 3.3.12 (placa ESP32 Dev Module) y las librerías EByte LoRa E220, OneWire, DallasTemperature, Adafruit SHT31, BH1750 y DFRobot RainfallSensor.

## Instalación del servidor local

1. **Sistema operativo.** Grabar Raspberry Pi OS Lite de 64 bits con Raspberry Pi Imager (equipo `aguape-srv`, acceso SSH con clave pública, zona horaria `America/Asuncion`). Reservar la IP del servidor en el enrutador y verificar la sincronización NTP con `timedatectl`.
2. **Actualización y cortafuegos.**
   ```bash
   sudo apt update && sudo apt upgrade -y
   ```
   Habilitar `ufw` con acceso a los puertos 22, 80 y 443 solo desde la red local.
3. **PHP, Composer y Node.js.** Instalar `php8.4-fpm` y las extensiones `mysql`, `xml`, `mbstring`, `curl`, `zip`, `bcmath`, `gd` e `intl`, además de Composer, `nodejs` y `npm`.
4. **Base de datos.**
   ```bash
   sudo apt install -y mariadb-server
   sudo mariadb-secure-installation
   ```
   Crear la base `aguape_local` y el usuario `aguape`, con privilegios solo sobre esa base.
5. **Servidor web y HTTPS.** Instalar Nginx y mkcert; generar el certificado para `aguape-srv.local`, `localhost`, `127.0.0.1` y la IP reservada, y configurar el sitio con raíz en `/srv/aguape/public`, redirección de HTTP a HTTPS y PHP-FPM.
6. **Código fuente.**
   ```bash
   sudo git clone https://github.com/elsoftpy/aguape /srv/aguape
   ```
   Otorgar a `www-data` permisos de escritura únicamente sobre `storage` y `bootstrap/cache`.
7. **Dependencias y compilación.**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```
8. **Configuración.**
   ```bash
   cp .env.example .env
   php artisan key:generate
   openssl rand -hex 32   # para cada secreto de ingesta y sincronización
   chmod 640 .env
   ```
   Completar las variables de la sección siguiente.
9. **Base de datos de la aplicación.**
   ```bash
   sudo -u www-data php artisan migrate --force
   sudo -u www-data php artisan db:seed --class=RolesAndPermissionsSeeder
   sudo -u www-data php artisan db:seed --class=VariableSeeder
   ```
   Crear el primer usuario con el rol Administrador.
10. **Caché de producción.**
    ```bash
    sudo -u www-data php artisan config:cache
    sudo -u www-data php artisan route:cache
    sudo -u www-data php artisan view:cache
    ```
    Cada cambio en el `.env` requiere volver a ejecutar `config:cache`.

Los comandos de Artisan se ejecutan con el usuario `www-data`, el mismo de PHP-FPM, para evitar conflictos de permisos.

## Variables de entorno

| Variable | Descripción | Instancia local | Instancia en la nube |
|---|---|---|---|
| `APP_ROL` | Rol de la instancia | `local` | `nube` |
| `APP_KEY` | Clave de cifrado | Generada en la instalación | La misma de la instancia local |
| `APP_TIMEZONE` | Zona horaria | `America/Asuncion` | `America/Asuncion` |
| `DB_CONNECTION` y `DB_*` | Conexión a la base de datos | `mariadb` | `mysql` |
| `INGESTA_TOKEN`, `INGESTA_HMAC_SECRET` | Autenticación de la ingesta y de la configuración de los nodos | Requeridas | No aplica |
| `SINCRONIZACION_URL` | API de sincronización de la nube | Requerida | No aplica |
| `SINCRONIZACION_TOKEN`, `SINCRONIZACION_HMAC_SECRET` | Autenticación de la sincronización | Requeridas | Los mismos de la instancia local |
| `MAIL_*` | Servidor SMTP para alertas e invitaciones | Requeridas | No aplica |
| `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY` | Claves de reCAPTCHA | Requeridas | Requeridas |

El archivo `.env` está excluido del repositorio.

## Gateway LoRa y servicio receptor

- Habilitar la UART de los pines GPIO14 y GPIO15 (`/dev/ttyAMA0`) con `dtparam=uart0=on` en la sección `[all]` de `/boot/firmware/config.txt`.
- El módulo E220 del gateway debe tener la misma configuración que los nodos: dirección 0x0001, canal 73 (923,125 MHz), UART a 9600 baudios 8N1, velocidad aérea de 2,4 kbps, subpaquete de 200 bytes, potencia de 30 dBm, transmisión transparente y byte de RSSI habilitado. Para verificarla y corregirla:
  ```bash
  python3 lora-service/e220_config.py            # lee y compara
  python3 lora-service/e220_config.py --escribir # corrige de forma persistente
  ```
- **Colocar la antena antes de alimentar el módulo:** un E220 de 30 dBm puede dañarse si transmite sin ella.
- Los secretos del servicio receptor van en `/etc/aguape-lora.env`, con lectura exclusiva del administrador del sistema. La calibración de los sensores va en `/var/lib/aguape/calibracion.json`, a partir de `lora-service/calibracion.ejemplo.json`. Las variables sin calibración no se envían a la aplicación.
- Los nodos deben registrarse en la aplicación con los códigos `N-1`, `N-2` y `N-3`, que coinciden con la constante `NODO_ID` del firmware. La ingesta rechaza lecturas de nodos no registrados o inactivos.

## Servicios y tareas programadas

| Servicio o tarea | Función | Gestión |
|---|---|---|
| `nginx`, `php8.4-fpm` | Servidor web y aplicación | systemd |
| `mariadb` | Base de datos local | systemd |
| `aguape-lora` | Servicio receptor LoRa; verifica la configuración del E220 en cada arranque | systemd |
| `aguape-queue` | Cola de trabajos para el envío de correos, con reintentos | systemd |
| `sincronizacion:enviar` | Envío de registros pendientes a la nube, cada minuto | cron de `www-data` |
| `ith:registrar-cortes` | Registro de los cortes del ITH, cada hora | cron de `www-data` |

Todos los servicios se inician automáticamente al encender el equipo.

## Firmware de los nodos

El firmware de cada nodo está en `esp32-firmware/` (`nodo_agua`, `nodo_pastura`, `nodo_ambiental`) y se carga con el Arduino IDE. Al encenderse, el nodo verifica la configuración de su E220 y envía una trama de arranque; luego transmite cada 60 segundos hasta recibir la configuración remota, que fija el intervalo definido en la aplicación (entre 15 y 120 minutos). Con batería baja, transmite una vez por hora.

El firmware se cargó en los nodos durante las pruebas de banco y de campo antes de registrarse en el repositorio, por lo que las fechas del historial no coinciden necesariamente con las de su uso en el establecimiento.

## Plataforma en la nube

Se despliega desde este mismo repositorio en un servidor administrado, con `APP_ROL=nube` y la misma `APP_KEY`, zona horaria y secretos de sincronización que la instancia local. Después de las migraciones se cargan solo los roles y permisos (`RolesAndPermissionsSeeder`); el resto de los datos llega desde la instancia local con los mismos identificadores. La plataforma en la nube no envía correos electrónicos.

## Interfaces de la API

| Ruta | Método | Instancia | Función |
|---|---|---|---|
| `/api/ingesta` | POST | Local | Recibe las lecturas decodificadas por el servicio receptor |
| `/api/configuracion` | GET | Local | Devuelve el intervalo de transmisión y la versión de configuración vigentes |
| `/api/sincronizacion` | POST | Nube | Recibe los lotes de registros enviados desde la instancia local |

Todas las rutas se autentican con token y firma HMAC-SHA256 con marca de tiempo.

## Actualización

```bash
sudo mariadb-dump aguape_local > /var/backups/aguape-AAAAMMDD.sql
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
php artisan queue:restart
sudo systemctl restart aguape-lora   # si se modificó el servicio receptor
```

## Autor

Elías Andrés Yambay Ferreira. Ingeniería Informática, Universidad de la Integración de las Américas (UNIDA), 2026.