# Tersime — Plataforma de monitorización IoT

Aplicación web para monitorizar el consumo eléctrico de dispositivos IoT. Agrega series temporales desde InfluxDB, las visualiza en dashboards Grafana y ofrece predicciones de consumo mediante un microservicio Python con Prophet.

## Stack

| Componente | Tecnología |
|---|---|
| Backend | Laravel 12 · PHP 8.4 |
| Base de datos | MySQL 8.0 |
| Series temporales | InfluxDB 2.7 |
| Dashboards | Grafana 11.4 |
| Predictor | Python 3.12 · Prophet (FastAPI) |
| Proxy | Nginx 1.25 |

## Instalación

```bash
git clone <repositorio> tersime && cd tersime
./install.sh
```

El script configura el entorno, construye las imágenes, levanta los contenedores y verifica que todos los servicios están operativos. Para instalaciones desatendidas: `./install.sh --non-interactive`.

## Puertos

| Servicio | Puerto |
|---|---|
| Aplicación | `8080` |
| InfluxDB | `8087` |
| Grafana (admin) | `3001` |

## Comandos frecuentes

```bash
docker compose ps                                    # estado de los contenedores
docker compose logs -f worker                        # logs del worker de jobs
docker compose exec app php artisan migrate:status   # estado de migraciones
docker compose restart nginx                         # reiniciar proxy
docker compose down                                  # parar (datos persistidos)
docker compose down -v                               # parar y eliminar datos
```
