# Tersime — Plataforma de monitorización IoT

Aplicación web para monitorizar el consumo eléctrico de dispositivos IoT. Agrega series temporales desde InfluxDB, las visualiza en dashboards Grafana, ofrece predicciones de consumo mediante un microservicio Python con Prophet y realiza informes. 

## Stack

| Componente | Tecnología |
|---|---|
| Backend | Laravel 12 · PHP 8.4 |
| Base de datos | MySQL 8.0 |
| Series temporales | InfluxDB 2.7 |
| Dashboards | Grafana 11.4 |
| Generación de informes PDF | Grafana Image Renderer 3.11 |
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