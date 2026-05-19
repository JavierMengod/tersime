# Tersime — Plataforma de monitorización IoT

Tersime es una aplicación web para la monitorización del consumo eléctrico de dispositivos IoT. Agrega datos de series temporales desde InfluxDB, los visualiza a través de dashboards de Grafana y ofrece predicciones de consumo mediante un microservicio Python con Prophet.

## Stack tecnológico

| Componente | Tecnología |
|---|---|
| Backend | Laravel 12 · PHP 8.4 |
| Base de datos relacional | MySQL 8.0 |
| Base de datos de series temporales | InfluxDB 2.7 |
| Dashboards | Grafana 11.4 |
| Renderer de informes PDF | Grafana Image Renderer 3.11.6 |
| Predictor de consumo | Python 3.12 · Prophet (FastAPI) |
| Proxy / ficheros estáticos | Nginx 1.25 |

---

## Instalación con Docker (recomendada)

### Requisitos previos

- Docker Engine 24+
- Docker Compose v2
- `curl` y `openssl` disponibles en el sistema
- ~3 GB de espacio libre para las imágenes (más espacio para datos)

### Instalación en un solo paso

```bash
git clone <repositorio> tersime
cd tersime
./install.sh
```

El script interactivo solicita los parámetros esenciales, genera contraseñas seguras automáticamente, construye las imágenes, levanta los contenedores y verifica que todos los servicios están operativos.

Para instalaciones desatendidas (CI/CD, servidores sin terminal interactiva):

```bash
./install.sh --non-interactive
```

### Lo que hace `install.sh` paso a paso

1. **Comprueba dependencias** — verifica que Docker, Docker Compose, `curl` y `openssl` están disponibles.
2. **Solicita configuración** — URL pública, credenciales del administrador de la app, Grafana e InfluxDB. Genera contraseñas y tokens criptográficamente seguros por defecto.
3. **Escribe `.env`** — a partir de `.env.docker` como plantilla, sustituye todos los valores `CHANGE_ME`.
4. **Construye las imágenes** — `docker compose build` compila las imágenes de la app, worker y predictor.
5. **Levanta los servicios** — `docker compose up -d` arranca los 8 contenedores.
6. **Espera a que los servicios estén listos** — sondea InfluxDB, Grafana y la app hasta que responden.
7. **Configura Grafana** — crea un service account, genera un API key y lo persiste en la base de datos de la app.
8. **Verifica la instalación** — comprueba InfluxDB (token, bucket, datos), Grafana (datasources, dashboards), la app Laravel (BD, settings) y el predictor Prophet.
9. **Ofrece cargar datos de demostración** — si el bucket está vacío, pregunta si cargar 400 días de datos sintéticos.

### Instalación manual paso a paso

Si prefieres controlar cada paso:

```bash
# 1. Copiar la plantilla de configuración
cp .env.docker .env

# 2. Editar .env y sustituir todos los valores CHANGE_ME
nano .env

# 3. Construir las imágenes
docker compose build

# 4. Levantar el stack
docker compose up -d

# 5. Verificar migraciones
docker compose exec app php artisan migrate:status
```

---

## Configuración

### Variables de entorno principales (`.env`)

| Variable | Descripción |
|---|---|
| `APP_URL` | URL pública completa con puerto. El navegador accede aquí. |
| `TERSIME_ADMIN_USER` | Nombre del usuario administrador creado en el primer arranque. |
| `TERSIME_ADMIN_PASSWORD` | Contraseña del administrador de la aplicación. |
| `DB_PASSWORD` | Contraseña de MySQL para el usuario de la app. |
| `INFLUXDB_TOKEN` | Token de administrador de InfluxDB. Debe coincidir con `DOCKER_INFLUXDB_INIT_ADMIN_TOKEN`. |
| `INFLUX_BUCKET` | Nombre del bucket donde se almacenan los datos de consumo. |
| `GF_SECURITY_ADMIN_PASSWORD` | Contraseña del administrador de Grafana. |
| `GRAFANA_API_KEY` | Generada automáticamente por `install.sh`. No rellenar manualmente. |
| `COSTE_ESTIMADO_KWH` | Coste estimado del kWh en euros, usado en dashboards e informes. |

### Override para entornos locales

El fichero `docker-compose.override.yml` permite añadir configuración local sin modificar el compose principal. Por defecto expone la app en el puerto `8088` (en lugar de `8080`) y carga variables de `.env.docker.local`, útil para pruebas sin sobrescribir `.env`.

---

## Puertos expuestos

| Servicio | Puerto del host | Descripción |
|---|---|---|
| App (Nginx) | `8080` | Acceso principal a la aplicación |
| InfluxDB | `8087` | UI y API de InfluxDB |
| Grafana | `3001` | Acceso directo al panel de Grafana (admin) |

> El renderer y el predictor son **servicios internos** — no se exponen al host, solo son accesibles dentro de la red Docker.

---

## Arquitectura de contenedores

```
                        ┌──────────────────────────────────────┐
                        │           Red Docker: tersime         │
                        │                                      │
  Navegador ──:8080──▶  │  nginx ──▶ app (PHP-FPM :9000)       │
                        │            │                          │
                        │            ├──▶ mysql :3306           │
                        │            ├──▶ influxdb :8086        │
                        │            ├──▶ grafana :3000         │
                        │            │      └──▶ renderer :8081 │
                        │            └──▶ predictor :8888       │
                        │                                      │
                        │  worker (queue:work) ◀── jobs BD     │
                        └──────────────────────────────────────┘
```

- **app** — PHP-FPM con Laravel. En el primer arranque ejecuta migraciones, crea el usuario admin y enlaza el storage.
- **worker** — Mismo Dockerfile que `app`, arranca `php artisan queue:work`. Procesa jobs asíncronos (generación de informes PDF, notificaciones). Se reinicia automáticamente cada hora (`--max-time=3600`) por diseño.
- **nginx** — Proxy inverso y servidor de ficheros estáticos. Todas las rutas `/grafana/*` se pasan a PHP para ser proxiadas a Grafana.
- **grafana** — Dashboards preconfigurados mediante provisioning. Autenticación via Auth Proxy (Laravel inyecta el header `X-WEBAUTH-USER`).
- **renderer** — Plugin de Grafana para renderizar paneles como imágenes PNG/PDF. Requerido para la generación de informes; no puede sustituirse por la API nativa `/render/`.
- **predictor** — Microservicio FastAPI con Prophet. Expone `/predict` y `/health`. Puede tardar ~60 segundos en arrancar al cargar el modelo.

---

## Estructura de datos InfluxDB

Los datos de consumo se almacenan en el bucket configurado (por defecto `tersime`) con dos measurements:

### `hourly` — Consumo horario

| Campo | Tipo | Descripción |
|---|---|---|
| `name` (tag) | string | Identificador del dispositivo. **Requerido.** Debe coincidir con el campo `influx_tag` del dispositivo en MySQL. |
| `kwh` (field) | float | Consumo de esa hora en kWh. |

### `daily` — Consumo diario acumulado

| Campo | Tipo | Descripción |
|---|---|---|
| `name` (tag) | string | Identificador del dispositivo. |
| `kwh_total` (field) | float | Consumo total del día en kWh. |

Consulta `INFLUXDB_SCHEMA.txt` para la documentación completa del esquema, incluyendo tags opcionales y ejemplos de consultas Flux.

---

## Datos de demostración

Para cargar 400 días de datos sintéticos en el bucket:

```bash
./docker/data/import.sh --demo --days 400
```

Para importar datos reales desde un CSV:

```bash
./docker/data/import.sh mis-datos.csv
```

---

## Comandos útiles

```bash
# Ver el estado de todos los contenedores
docker compose ps

# Seguir los logs de todos los servicios
docker compose logs -f

# Ver solo los logs del worker (jobs asíncronos)
docker compose logs -f worker

# Reiniciar un servicio sin parar el resto
docker compose restart nginx

# Ejecutar un comando Artisan dentro del contenedor
docker compose exec app php artisan migrate:status

# Parar todos los contenedores (los datos persisten en volúmenes)
docker compose down

# Parar y eliminar todos los datos (volúmenes incluidos)
docker compose down -v

# Reconstruir una imagen tras un cambio en el código
docker compose build app && docker compose up -d --no-deps app
```

---

## Primer arranque: comportamiento esperado

1. Los contenedores `mysql` e `influxdb` tardan ~20-30 segundos en inicializarse.
2. El contenedor `app` espera a que MySQL esté disponible, ejecuta las migraciones y crea el usuario admin antes de arrancar PHP-FPM.
3. El contenedor `worker` espera a que `app` termine la instalación antes de arrancar `queue:work`.
4. El predictor carga el modelo Prophet al arrancar; puede tardar hasta 60 segundos antes de responder en `/health`.
5. Grafana descarga el plugin `marcusolsson-json-datasource` en el primer arranque si tiene acceso a internet.

---

## Solución de problemas frecuentes

**502 Bad Gateway tras reiniciar un contenedor**

Nginx cachea la IP de `app` al arrancar. Si se recrea el contenedor `app`, hay que reiniciar también nginx:

```bash
docker compose restart nginx
```

**Los paneles de Grafana no cargan (error de assets JS)**

Verificar que la directiva `^~ /grafana/` está presente en `docker/nginx/default.conf`. Sin ella, nginx intercepta los ficheros `.js` y `.css` de Grafana y devuelve 404 en lugar de pasarlos al proxy PHP.

**El predictor no responde**

Esperar ~60 segundos tras el arranque inicial. Prophet tarda en cargar. Verificar con:

```bash
docker compose exec app curl -s http://predictor:8888/health
```

**Espacio en disco insuficiente**

Docker acumula imágenes y caché de build con el tiempo. Limpiar recursos no utilizados:

```bash
docker image prune -f
docker builder prune -f
```
