#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# Tersime — Script de instalación
# Uso: ./install.sh
# ─────────────────────────────────────────────────────────────────────────────
set -e

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'

info()    { echo -e "${CYAN}[tersime]${NC} $*"; }
success() { echo -e "${GREEN}[tersime]${NC} $*"; }
warn()    { echo -e "${YELLOW}[tersime]${NC} $*"; }
error()   { echo -e "${RED}[tersime]${NC} $*"; exit 1; }

# ── Comprobaciones previas ────────────────────────────────────────────────────
command -v docker       >/dev/null 2>&1 || error "Docker no está instalado."
command -v openssl      >/dev/null 2>&1 || error "openssl no está instalado."
docker info >/dev/null 2>&1             || error "El daemon de Docker no está corriendo."

COMPOSE_CMD="docker compose"
$COMPOSE_CMD version >/dev/null 2>&1 || COMPOSE_CMD="docker-compose"
$COMPOSE_CMD version >/dev/null 2>&1 || error "docker compose / docker-compose no encontrado."

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# ── Banner ────────────────────────────────────────────────────────────────────
echo ""
echo -e "${CYAN}╔═══════════════════════════════════════╗${NC}"
echo -e "${CYAN}║         Tersime — Instalación         ║${NC}"
echo -e "${CYAN}╚═══════════════════════════════════════╝${NC}"
echo ""

# ── Si ya existe .env, preguntar si reinstalar ────────────────────────────────
if [ -f .env ]; then
    warn ".env ya existe."
    read -rp "¿Sobrescribir la configuración existente? [s/N] " OVERWRITE
    if [[ ! "$OVERWRITE" =~ ^[sS]$ ]]; then
        info "Usando .env existente. Saltando configuración."
        SKIP_CONFIG=1
    fi
fi

# ── Recoger configuración del usuario ────────────────────────────────────────
if [ -z "$SKIP_CONFIG" ]; then
    echo "Responde las siguientes preguntas (pulsa ENTER para aceptar el valor por defecto):"
    echo ""

    # URL pública
    read -rp "URL pública de la app (incluye puerto) [http://localhost:8080]: " APP_URL
    APP_URL="${APP_URL:-http://localhost:8080}"

    # Credenciales admin de Tersime
    read -rp "Nombre del usuario administrador [admin]: " TERSIME_ADMIN_USER
    TERSIME_ADMIN_USER="${TERSIME_ADMIN_USER:-admin}"
    while true; do
        read -rsp "Contraseña del administrador de Tersime: " TERSIME_ADMIN_PASSWORD; echo
        [ -n "$TERSIME_ADMIN_PASSWORD" ] && break
        warn "La contraseña no puede estar vacía."
    done

    # Grafana admin
    read -rp "Usuario admin de Grafana [admin]: " GF_ADMIN_USER
    GF_ADMIN_USER="${GF_ADMIN_USER:-admin}"
    while true; do
        read -rsp "Contraseña admin de Grafana: " GF_ADMIN_PASSWORD; echo
        [ -n "$GF_ADMIN_PASSWORD" ] && break
        warn "La contraseña no puede estar vacía."
    done

    # InfluxDB
    read -rp "Contraseña admin de InfluxDB [tersime2024]: " INFLUX_ADMIN_PASS
    INFLUX_ADMIN_PASS="${INFLUX_ADMIN_PASS:-tersime2024}"
    read -rp "Organización de InfluxDB [tersime]: " INFLUXDB_ORG
    INFLUXDB_ORG="${INFLUXDB_ORG:-tersime}"
    read -rp "Bucket de InfluxDB [tersime]: " INFLUX_BUCKET
    INFLUX_BUCKET="${INFLUX_BUCKET:-tersime}"

    echo ""
    info "Generando claves seguras..."

    # Generar tokens aleatorios
    APP_KEY="base64:$(openssl rand -base64 32)"
    INFLUXDB_TOKEN="$(openssl rand -base64 48 | tr -d '/+=' | head -c 64)"

    # ── Escribir .env ─────────────────────────────────────────────────────────
    info "Escribiendo .env..."
    sed \
        -e "s|^APP_URL=.*|APP_URL=${APP_URL}|" \
        -e "s|^TERSIME_ADMIN_USER=.*|TERSIME_ADMIN_USER=${TERSIME_ADMIN_USER}|" \
        -e "s|^TERSIME_ADMIN_PASSWORD=.*|TERSIME_ADMIN_PASSWORD=${TERSIME_ADMIN_PASSWORD}|" \
        -e "s|^GF_SECURITY_ADMIN_USER=.*|GF_SECURITY_ADMIN_USER=${GF_ADMIN_USER}|" \
        -e "s|^GF_SECURITY_ADMIN_PASSWORD=.*|GF_SECURITY_ADMIN_PASSWORD=${GF_ADMIN_PASSWORD}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_PASSWORD=.*|DOCKER_INFLUXDB_INIT_PASSWORD=${INFLUX_ADMIN_PASS}|" \
        -e "s|^INFLUXDB_ORG=.*|INFLUXDB_ORG=${INFLUXDB_ORG}|" \
        -e "s|^INFLUX_BUCKET=.*|INFLUX_BUCKET=${INFLUX_BUCKET}|" \
        -e "s|^INFLUXDB_TOKEN=.*|INFLUXDB_TOKEN=${INFLUXDB_TOKEN}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_ORG=.*|DOCKER_INFLUXDB_INIT_ORG=${INFLUXDB_ORG}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_BUCKET=.*|DOCKER_INFLUXDB_INIT_BUCKET=${INFLUX_BUCKET}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_ADMIN_TOKEN=.*|DOCKER_INFLUXDB_INIT_ADMIN_TOKEN=${INFLUXDB_TOKEN}|" \
        .env.docker > .env

    # Insertar APP_KEY generado
    if grep -q "^APP_KEY=" .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
    else
        echo "APP_KEY=${APP_KEY}" >> .env
    fi
fi

# Cargar variables del .env para usarlas en el script
set -a; source .env; set +a

# ── Construir e iniciar contenedores ─────────────────────────────────────────
echo ""
info "Construyendo imágenes Docker (puede tardar varios minutos la primera vez)..."
$COMPOSE_CMD build --no-cache

info "Iniciando contenedores..."
$COMPOSE_CMD up -d

# ── Esperar a que los servicios estén listos ──────────────────────────────────
echo ""
info "Esperando a que los servicios arranquen..."

wait_for() {
    local name=$1 url=$2 max=${3:-120} i=0
    printf "  Esperando %-12s " "$name..."
    until curl -sf "$url" >/dev/null 2>&1; do
        sleep 3; i=$((i+3))
        printf "."
        [ $i -ge $max ] && echo "" && error "Timeout esperando $name ($url)"
    done
    echo " listo"
}

wait_for "InfluxDB"  "http://localhost:8087/health"    120
wait_for "Grafana"   "http://localhost:3001/api/health" 180
wait_for "App"       "http://localhost:8080/"            120

# ── Generar API key de Grafana y configurarla en la app ──────────────────────
echo ""
info "Generando API key de Grafana..."

GF_ADMIN_USER="${GF_SECURITY_ADMIN_USER:-admin}"
GF_ADMIN_PASSWORD="${GF_SECURITY_ADMIN_PASSWORD}"

# Crear service account "tersime" con rol Admin
SA_RESPONSE=$(curl -sf -X POST "http://localhost:3001/api/serviceaccounts" \
    -H "Content-Type: application/json" \
    -d '{"name":"tersime","role":"Admin","isDisabled":false}' \
    -u "${GF_ADMIN_USER}:${GF_ADMIN_PASSWORD}" 2>/dev/null || echo '{}')

SA_ID=$(echo "$SA_RESPONSE" | grep -o '"id":[0-9]*' | head -1 | grep -o '[0-9]*')

if [ -n "$SA_ID" ] && [ "$SA_ID" != "null" ]; then
    TOKEN_RESPONSE=$(curl -sf -X POST "http://localhost:3001/api/serviceaccounts/${SA_ID}/tokens" \
        -H "Content-Type: application/json" \
        -d '{"name":"tersime-token"}' \
        -u "${GF_ADMIN_USER}:${GF_ADMIN_PASSWORD}" 2>/dev/null || echo '{}')

    GRAFANA_API_KEY=$(echo "$TOKEN_RESPONSE" | grep -o '"key":"[^"]*"' | sed 's/"key":"//;s/"//')
fi

# Fallback: intentar con el endpoint legacy de API keys
if [ -z "$GRAFANA_API_KEY" ]; then
    warn "Service account no disponible, intentando API key legacy..."
    KEY_RESPONSE=$(curl -sf -X POST "http://localhost:3001/api/auth/keys" \
        -H "Content-Type: application/json" \
        -d '{"name":"tersime","role":"Admin"}' \
        -u "${GF_ADMIN_USER}:${GF_ADMIN_PASSWORD}" 2>/dev/null || echo '{}')
    GRAFANA_API_KEY=$(echo "$KEY_RESPONSE" | grep -o '"key":"[^"]*"' | sed 's/"key":"//;s/"//')
fi

if [ -n "$GRAFANA_API_KEY" ]; then
    # Guardar en settings de la app vía docker exec
    $COMPOSE_CMD exec -T app php artisan tinker --execute="
        App\Models\Setting::set('grafana_api_key', '$GRAFANA_API_KEY');
        echo 'API key de Grafana guardada en settings.';
    " 2>/dev/null || true

    # También actualizar el .env para referencia
    sed -i "s|^GRAFANA_API_KEY=.*|GRAFANA_API_KEY=${GRAFANA_API_KEY}|" .env
    success "API key de Grafana configurada automáticamente."
else
    warn "No se pudo generar la API key automáticamente."
    warn "Entra en http://localhost:3001 con ${GF_ADMIN_USER}/${GF_SECURITY_ADMIN_PASSWORD}"
    warn "y crea una API key en: Administration → Service accounts."
    warn "Después ve a /configuracion/conexiones en la app y pégala."
fi

# ── Obtener el datasource ID de Grafana ──────────────────────────────────────
DS_RESPONSE=$(curl -sf "http://localhost:3001/api/datasources" \
    -u "${GF_ADMIN_USER}:${GF_ADMIN_PASSWORD}" 2>/dev/null || echo '[]')
DS_ID=$(echo "$DS_RESPONSE" | grep -o '"id":[0-9]*' | head -1 | grep -o '[0-9]*')

if [ -n "$DS_ID" ]; then
    $COMPOSE_CMD exec -T app php artisan tinker --execute="
        App\Models\Setting::set('grafana_datasource_id', '$DS_ID');
        echo 'Datasource ID de Grafana: $DS_ID';
    " 2>/dev/null || true
fi

# ── Resumen final ─────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           ✓ Tersime instalado correctamente       ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${CYAN}Aplicación:${NC}  ${APP_URL}"
echo -e "  ${CYAN}Usuario:${NC}     ${TERSIME_ADMIN_USER:-admin}"
echo -e "  ${CYAN}Contraseña:${NC}  ${TERSIME_ADMIN_PASSWORD}"
echo ""
echo -e "  ${CYAN}InfluxDB UI:${NC} http://localhost:8087"
echo -e "  ${CYAN}Grafana UI:${NC}  http://localhost:3001  (${GF_ADMIN_USER}/${GF_SECURITY_ADMIN_PASSWORD})"
echo ""
echo -e "  ${YELLOW}Próximos pasos:${NC}"
echo -e "  1. Entra en ${APP_URL} y configura tus dispositivos"
echo -e "  2. Configura las notificaciones en /configuracion/conexiones"
echo -e "  3. Añade datos a InfluxDB (bucket: ${INFLUX_BUCKET:-tersime})"
echo ""
echo -e "  ${CYAN}Comandos útiles:${NC}"
echo -e "  · Ver logs:      $COMPOSE_CMD logs -f"
echo -e "  · Parar:         $COMPOSE_CMD down"
echo -e "  · Reiniciar:     $COMPOSE_CMD restart"
echo -e "  · Shell PHP:     $COMPOSE_CMD exec app bash"
echo ""
