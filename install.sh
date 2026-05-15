#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# Tersime — Script de instalación
# Uso: ./install.sh
#      ./install.sh --non-interactive   (usa todos los valores por defecto)
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

# ── Colores ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; DIM='\033[2m'; NC='\033[0m'

info()    { echo -e "${CYAN}▸${NC} $*"; }
success() { echo -e "${GREEN}✓${NC} $*"; }
warn()    { echo -e "${YELLOW}⚠${NC} $*"; }
error()   { echo -e "${RED}✗${NC} $*"; exit 1; }
section() { echo -e "\n${BOLD}$*${NC}"; echo -e "${DIM}$(printf '─%.0s' {1..60})${NC}"; }
ok()      { echo -e "  ${GREEN}✓${NC} $*"; }
fail()    { echo -e "  ${RED}✗${NC} $*"; }
skip()    { echo -e "  ${DIM}–${NC} $*"; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

NON_INTERACTIVE=0
[[ "${1:-}" == "--non-interactive" ]] && NON_INTERACTIVE=1

# ── Función para leer input con default ───────────────────────────────────────
ask() {
    # ask VAR_NAME "Pregunta" "default"  [secret]
    local var_name="$1" prompt="$2" default="$3" secret="${4:-}"

    if [ "$NON_INTERACTIVE" = "1" ]; then
        eval "$var_name=\"$default\""
        return
    fi

    if [ -n "$secret" ]; then
        read -rsp "  ${prompt} [${DIM}auto${NC}]: " tmp; echo
        eval "$var_name=\"${tmp:-$default}\""
    else
        read -rp "  ${prompt} [${CYAN}${default}${NC}]: " tmp
        eval "$var_name=\"${tmp:-$default}\""
    fi
}

# ── Comprobaciones previas ────────────────────────────────────────────────────
section "0 · Comprobando dependencias"

command -v docker  >/dev/null 2>&1 && ok "docker" || error "Docker no instalado."
command -v curl    >/dev/null 2>&1 && ok "curl"   || error "curl no instalado."
command -v openssl >/dev/null 2>&1 && ok "openssl"|| error "openssl no instalado."
docker info >/dev/null 2>&1        && ok "Docker daemon corriendo" \
                                   || error "El daemon de Docker no está activo."

COMPOSE_CMD="docker compose"
$COMPOSE_CMD version >/dev/null 2>&1 || COMPOSE_CMD="docker-compose"
$COMPOSE_CMD version >/dev/null 2>&1 \
    && ok "docker compose ($($COMPOSE_CMD version --short 2>/dev/null | head -1))" \
    || error "docker compose no disponible."

# ── Banner ────────────────────────────────────────────────────────────────────
echo ""
echo -e "${CYAN}${BOLD}"
echo "  ████████╗███████╗██████╗ ███████╗██╗███╗   ███╗███████╗"
echo "     ██╔══╝██╔════╝██╔══██╗██╔════╝██║████╗ ████║██╔════╝"
echo "     ██║   █████╗  ██████╔╝███████╗██║██╔████╔██║█████╗  "
echo "     ██║   ██╔══╝  ██╔══██╗╚════██║██║██║╚██╔╝██║██╔══╝  "
echo "     ██║   ███████╗██║  ██║███████║██║██║ ╚═╝ ██║███████╗"
echo "     ╚═╝   ╚══════╝╚═╝  ╚═╝╚══════╝╚═╝╚═╝     ╚═╝╚══════╝"
echo -e "${NC}"
echo -e "  Plataforma de monitorización IoT${DIM} — instalación Docker${NC}"
echo ""

# ── Si ya existe .env preguntar ───────────────────────────────────────────────
SKIP_CONFIG=0
if [ -f .env ]; then
    if grep -q "DOCKER_INFLUXDB_INIT_PASSWORD" .env 2>/dev/null; then
        # .env ya tiene config Docker — preguntar si reconfigurar
        if [ "$NON_INTERACTIVE" = "0" ]; then
            echo -e "${YELLOW}  .env Docker ya existe.${NC}"
            read -rp "  ¿Reconfigurar desde cero? [s/N]: " OVERWRITE
            [[ "$OVERWRITE" =~ ^[sS]$ ]] || SKIP_CONFIG=1
        else
            SKIP_CONFIG=1
        fi
    else
        # .env existe pero es de desarrollo local (sin vars Docker) — avisar y preguntar
        warn ".env existente no tiene configuración Docker."
        if [ "$NON_INTERACTIVE" = "0" ]; then
            echo -e "  ${DIM}Se sobrescribirá con la configuración Docker (se hará una copia de seguridad).${NC}"
            read -rp "  ¿Continuar? [S/n]: " OVERWRITE
            if [[ "$OVERWRITE" =~ ^[nN]$ ]]; then
                error "Instalación cancelada. Mueve o renombra tu .env antes de continuar."
            fi
        fi
        cp .env .env.bak
        info ".env local guardado como .env.bak"
    fi
fi

# ── Configuración ─────────────────────────────────────────────────────────────
section "1 · Configuración"

if [ "$SKIP_CONFIG" = "0" ]; then

    echo -e "  ${DIM}Pulsa ENTER para aceptar el valor entre corchetes.${NC}"
    echo ""

    # Generar valores seguros por defecto
    _INFLUX_TOKEN="$(openssl rand -base64 48 | tr -d '/+=' | head -c 64)"
    _APP_PASS="$(openssl rand -base64 12 | tr -d '/+=' | head -c 12)"
    _GF_PASS="$(openssl rand -base64 12 | tr -d '/+=' | head -c 12)"
    _INFLUX_PASS="$(openssl rand -base64 12 | tr -d '/+=' | head -c 12)"

    echo -e "  ${BOLD}Aplicación${NC}"
    ask APP_URL         "URL pública con puerto"       "http://localhost:8080"
    ask ADMIN_USER      "Usuario administrador"        "admin"
    ask ADMIN_PASS      "Contraseña administrador"     "$_APP_PASS"   secret

    echo ""
    echo -e "  ${BOLD}Grafana${NC}"
    ask GF_ADMIN_USER   "Usuario admin Grafana"        "admin"
    ask GF_ADMIN_PASS   "Contraseña admin Grafana"     "$_GF_PASS"    secret

    echo ""
    echo -e "  ${BOLD}InfluxDB${NC}"
    ask INFLUX_ORG      "Organización"                 "tersime"
    ask INFLUX_BUCKET   "Bucket (nombre del dataset)"  "tersime"
    ask INFLUX_PASS     "Contraseña admin InfluxDB"    "$_INFLUX_PASS" secret
    ask INFLUX_TOKEN    "Token InfluxDB"               "$_INFLUX_TOKEN" secret

    echo ""
    info "Generando clave de aplicación..."
    APP_KEY="base64:$(openssl rand -base64 32)"

    info "Escribiendo .env..."
    sed \
        -e "s|^APP_URL=.*|APP_URL=${APP_URL}|" \
        -e "s|^TERSIME_ADMIN_USER=.*|TERSIME_ADMIN_USER=${ADMIN_USER}|" \
        -e "s|^TERSIME_ADMIN_PASSWORD=.*|TERSIME_ADMIN_PASSWORD=${ADMIN_PASS}|" \
        -e "s|^GF_SECURITY_ADMIN_USER=.*|GF_SECURITY_ADMIN_USER=${GF_ADMIN_USER}|" \
        -e "s|^GF_SECURITY_ADMIN_PASSWORD=.*|GF_SECURITY_ADMIN_PASSWORD=${GF_ADMIN_PASS}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_USERNAME=.*|DOCKER_INFLUXDB_INIT_USERNAME=admin|" \
        -e "s|^DOCKER_INFLUXDB_INIT_PASSWORD=.*|DOCKER_INFLUXDB_INIT_PASSWORD=${INFLUX_PASS}|" \
        -e "s|^INFLUXDB_ORG=.*|INFLUXDB_ORG=${INFLUX_ORG}|" \
        -e "s|^INFLUX_BUCKET=.*|INFLUX_BUCKET=${INFLUX_BUCKET}|" \
        -e "s|^INFLUXDB_TOKEN=.*|INFLUXDB_TOKEN=${INFLUX_TOKEN}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_ORG=.*|DOCKER_INFLUXDB_INIT_ORG=${INFLUX_ORG}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_BUCKET=.*|DOCKER_INFLUXDB_INIT_BUCKET=${INFLUX_BUCKET}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_ADMIN_TOKEN=.*|DOCKER_INFLUXDB_INIT_ADMIN_TOKEN=${INFLUX_TOKEN}|" \
        -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" \
        .env.docker > .env

    success ".env generado"
fi

# Cargar variables del .env
set -a; source .env; set +a

# ── Build y arranque ──────────────────────────────────────────────────────────
section "2 · Construyendo imágenes"
info "Esto puede tardar varios minutos la primera vez..."
$COMPOSE_CMD build 2>&1 | grep -E "^(Step|#|==>|Successfully|ERROR|error)" || true
success "Imágenes construidas"

section "3 · Iniciando servicios"
$COMPOSE_CMD up -d
success "Contenedores iniciados"

# ── Esperar a que los servicios estén listos ──────────────────────────────────
section "4 · Esperando servicios"

wait_http() {
    local name="$1" url="$2" max="${3:-120}" i=0
    printf "  Esperando %-14s" "$name..."
    until curl -sf "$url" >/dev/null 2>&1; do
        sleep 3; i=$((i+3)); printf "."
        [ $i -ge $max ] && echo "" && error "Timeout esperando $name"
    done
    echo " listo"
}

wait_http "InfluxDB"  "http://localhost:8087/health"    120
wait_http "Grafana"   "http://localhost:3001/api/health" 180
wait_http "App"       "http://localhost:8080/"           120

# El predictor puede tardar más (carga Prophet)
printf "  Esperando %-14s" "Predictor..."
for i in $(seq 1 40); do
    curl -sf "http://localhost:8080/" >/dev/null 2>&1 && break  # app ready
    sleep 3; printf "."
done
# Predictor: acceso interno, verificamos después
echo " (verificación posterior)"

# ── Generar API key de Grafana ────────────────────────────────────────────────
section "5 · Configurando Grafana"

GF_USER="${GF_SECURITY_ADMIN_USER:-admin}"
GF_PASS="${GF_SECURITY_ADMIN_PASSWORD}"

info "Creando service account en Grafana..."
SA_RESP=$(curl -sf -X POST "http://localhost:3001/api/serviceaccounts" \
    -H "Content-Type: application/json" \
    -d '{"name":"tersime-app","role":"Admin","isDisabled":false}' \
    -u "${GF_USER}:${GF_PASS}" 2>/dev/null || echo '{}')

SA_ID=$(echo "$SA_RESP" | grep -o '"id":[0-9]*' | head -1 | grep -o '[0-9]*' || true)

GRAFANA_API_KEY=""
if [ -n "$SA_ID" ]; then
    TOK_RESP=$(curl -sf -X POST "http://localhost:3001/api/serviceaccounts/${SA_ID}/tokens" \
        -H "Content-Type: application/json" \
        -d '{"name":"tersime-token"}' \
        -u "${GF_USER}:${GF_PASS}" 2>/dev/null || echo '{}')
    GRAFANA_API_KEY=$(echo "$TOK_RESP" | grep -o '"key":"[^"]*"' | sed 's/"key":"//;s/"//' || true)
fi

if [ -z "$GRAFANA_API_KEY" ]; then
    info "Intentando API key legacy..."
    KEY_RESP=$(curl -sf -X POST "http://localhost:3001/api/auth/keys" \
        -H "Content-Type: application/json" \
        -d '{"name":"tersime","role":"Admin"}' \
        -u "${GF_USER}:${GF_PASS}" 2>/dev/null || echo '{}')
    GRAFANA_API_KEY=$(echo "$KEY_RESP" | grep -o '"key":"[^"]*"' | sed 's/"key":"//;s/"//' || true)
fi

if [ -n "$GRAFANA_API_KEY" ]; then
    $COMPOSE_CMD exec -T app php artisan tinker --execute="
        App\Models\Setting::set('grafana_api_key', '$GRAFANA_API_KEY');
        echo 'OK';
    " >/dev/null 2>&1 || true
    sed -i "s|^GRAFANA_API_KEY=.*|GRAFANA_API_KEY=${GRAFANA_API_KEY}|" .env
    success "API key de Grafana configurada"
else
    warn "No se pudo generar API key. Hazlo manualmente en /configuracion/conexiones"
fi

# Obtener datasource ID
DS_RESP=$(curl -sf "http://localhost:3001/api/datasources" \
    -u "${GF_USER}:${GF_PASS}" 2>/dev/null || echo '[]')
DS_ID=$(echo "$DS_RESP" | grep -o '"id":[0-9]*' | head -1 | grep -o '[0-9]*' || true)
if [ -n "$DS_ID" ]; then
    $COMPOSE_CMD exec -T app php artisan tinker --execute="
        App\Models\Setting::set('grafana_datasource_id', '$DS_ID');
        echo 'OK';
    " >/dev/null 2>&1 || true
fi

# ─────────────────────────────────────────────────────────────────────────────
# 6 · VERIFICACIÓN DE SERVICIOS
# ─────────────────────────────────────────────────────────────────────────────
section "6 · Verificación de servicios"

INFLUX_ORG="${INFLUXDB_ORG:-tersime}"
INFLUX_BUCKET_NAME="${INFLUX_BUCKET:-tersime}"
INFLUX_TOKEN_VAL="${INFLUXDB_TOKEN}"
INFLUX_API="http://localhost:8087"

# ── InfluxDB ──────────────────────────────────────────────────────────────────
echo -e "\n  ${BOLD}InfluxDB${NC} (http://localhost:8087)"

# 1. Health
HEALTH=$(curl -sf "${INFLUX_API}/health" 2>/dev/null || echo '{}')
if echo "$HEALTH" | grep -q '"status":"pass"'; then
    ok "Servicio activo y respondiendo"
else
    fail "Servicio no responde correctamente (health: ${HEALTH})"
fi

# 2. Autenticación con token
AUTH_CODE=$(curl -sf -o /dev/null -w "%{http_code}" \
    "${INFLUX_API}/api/v2/buckets" \
    -H "Authorization: Token ${INFLUX_TOKEN_VAL}" 2>/dev/null || echo "000")
if [ "$AUTH_CODE" = "200" ]; then
    ok "Token válido (autenticación OK)"
else
    fail "Token inválido o rechazado (HTTP ${AUTH_CODE})"
fi

# 3. Bucket existe
BUCKET_RESP=$(curl -sf "${INFLUX_API}/api/v2/buckets?name=${INFLUX_BUCKET_NAME}" \
    -H "Authorization: Token ${INFLUX_TOKEN_VAL}" 2>/dev/null || echo '{}')
BUCKET_COUNT=$(echo "$BUCKET_RESP" | grep -o '"id":"[^"]*"' | wc -l || echo "0")
if [ "$BUCKET_COUNT" -ge "1" ]; then
    ok "Bucket '${INFLUX_BUCKET_NAME}' existe"
else
    fail "Bucket '${INFLUX_BUCKET_NAME}' NO encontrado"
fi

# 4. Schema: measurement 'hourly' con field 'kwh'
SCHEMA_QUERY=$(cat <<FLUX
from(bucket: "${INFLUX_BUCKET_NAME}")
  |> range(start: -5y)
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh")
  |> distinct(column: "name")
  |> keep(columns: ["name"])
  |> limit(n: 20)
FLUX
)

SCHEMA_RESP=$($COMPOSE_CMD exec -T influxdb influx query \
    --org "$INFLUX_ORG" --token "$INFLUX_TOKEN_VAL" "$SCHEMA_QUERY" 2>/dev/null \
    | grep -v "^#\|^,result\|^,table\|^$" \
    | awk -F',' 'NR>1 {print $NF}' \
    | sort -u | tr '\n' ' ' | xargs || true)

INFLUXDB_EMPTY=0
if [ -n "$SCHEMA_RESP" ]; then
    ok "Measurement 'hourly' con field 'kwh' detectado"
    ok "Dispositivos con datos: ${SCHEMA_RESP}"

    # 5. Datos recientes (últimas 48 h)
    RECENT_QUERY=$(cat <<FLUX
from(bucket: "${INFLUX_BUCKET_NAME}")
  |> range(start: -48h)
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh")
  |> count()
  |> sum()
FLUX
)
    RECENT_COUNT=$($COMPOSE_CMD exec -T influxdb influx query \
        --org "$INFLUX_ORG" --token "$INFLUX_TOKEN_VAL" "$RECENT_QUERY" 2>/dev/null \
        | grep -v "^#\|^,result\|^,table\|^$" \
        | awk -F',' 'NR>1 {print $NF}' | head -1 | tr -d '[:space:]' || echo "0")

    if [ -n "$RECENT_COUNT" ] && [ "$RECENT_COUNT" -gt "0" ] 2>/dev/null; then
        ok "Datos recientes (últimas 48 h): ${RECENT_COUNT} puntos"
    else
        skip "Sin datos en las últimas 48 h (datos históricos sí presentes)"
    fi
else
    skip "Bucket vacío — no hay datos aún"
    warn "  → Carga datos demo: ./docker/data/import.sh --demo"
    warn "  → Importa tus datos: ./docker/data/import.sh tus-datos.csv"
    INFLUXDB_EMPTY=1
fi

# 6. Measurement 'daily' con field 'kwh_total'
DAILY_QUERY=$(cat <<FLUX
from(bucket: "${INFLUX_BUCKET_NAME}")
  |> range(start: -5y)
  |> filter(fn: (r) => r._measurement == "daily" and r._field == "kwh_total")
  |> count()
  |> sum()
FLUX
)
DAILY_COUNT=$($COMPOSE_CMD exec -T influxdb influx query \
    --org "$INFLUX_ORG" --token "$INFLUX_TOKEN_VAL" "$DAILY_QUERY" 2>/dev/null \
    | grep -v "^#\|^,result\|^,table\|^$" \
    | awk -F',' 'NR>1 {print $NF}' | head -1 | tr -d '[:space:]' || echo "0")

if [ -n "$DAILY_COUNT" ] && [ "$DAILY_COUNT" -gt "0" ] 2>/dev/null; then
    ok "Measurement 'daily' con field 'kwh_total': ${DAILY_COUNT} puntos"
else
    skip "Measurement 'daily' vacío"
fi

# ── Grafana ───────────────────────────────────────────────────────────────────
echo -e "\n  ${BOLD}Grafana${NC} (http://localhost:3001)"

# 1. Health
GF_HEALTH=$(curl -sf "http://localhost:3001/api/health" 2>/dev/null || echo '{}')
if echo "$GF_HEALTH" | grep -q '"database":"ok"'; then
    ok "Servicio activo (database OK)"
elif echo "$GF_HEALTH" | grep -q '"status"'; then
    ok "Servicio activo"
else
    fail "Servicio no responde"
fi

# 2. Datasource InfluxDB conectado
DS_LIST=$(curl -sf "http://localhost:3001/api/datasources" \
    -u "${GF_USER}:${GF_PASS}" 2>/dev/null || echo '[]')
INFLUX_DS=$(echo "$DS_LIST" | grep -c '"type":"influxdb"' || echo "0")
if [ "$INFLUX_DS" -ge "1" ]; then
    ok "Datasource InfluxDB provisionado"
else
    fail "Datasource InfluxDB no encontrado"
fi

# 3. Health del datasource InfluxDB
DS_UID="feg5i5n3pjq4gf"
DS_HEALTH=$(curl -sf "http://localhost:3001/api/datasources/uid/${DS_UID}/health" \
    -u "${GF_USER}:${GF_PASS}" 2>/dev/null || echo '{}')
if echo "$DS_HEALTH" | grep -qi '"status":"OK"'; then
    ok "Datasource InfluxDB: conectado y respondiendo"
elif echo "$DS_HEALTH" | grep -qi '"status"'; then
    DS_MSG=$(echo "$DS_HEALTH" | grep -o '"message":"[^"]*"' | head -1 | sed 's/"message":"//;s/"//')
    warn "Datasource InfluxDB: ${DS_MSG:-sin datos}"
else
    skip "Health del datasource no disponible (puede ser normal si el bucket está vacío)"
fi

# 4. JSON-API datasource (predictor)
JSON_DS=$(echo "$DS_LIST" | grep -c '"type":"marcusolsson-json-datasource"' || echo "0")
if [ "$JSON_DS" -ge "1" ]; then
    ok "Datasource JSON-API (predictor) provisionado"
else
    warn "Datasource JSON-API no encontrado (plugin puede estar descargando)"
fi

# 5. Dashboards cargados
DASH_RESP=$(curl -sf "http://localhost:3001/api/search?type=dash-db" \
    -u "${GF_USER}:${GF_PASS}" 2>/dev/null || echo '[]')
DASH_COUNT=$(echo "$DASH_RESP" | grep -c '"id"' || echo "0")
if [ "$DASH_COUNT" -ge "1" ]; then
    ok "${DASH_COUNT} dashboard(s) cargados"
else
    warn "Dashboards no detectados (pueden estar cargando — espera 30s y verifica en Grafana)"
fi

# ── App Laravel ───────────────────────────────────────────────────────────────
echo -e "\n  ${BOLD}Aplicación Laravel${NC} (http://localhost:8080)"

APP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" "http://localhost:8080/" 2>/dev/null || echo "000")
if [ "$APP_CODE" = "200" ] || [ "$APP_CODE" = "302" ]; then
    ok "Página de login accesible (HTTP ${APP_CODE})"
else
    fail "No responde (HTTP ${APP_CODE})"
fi

# Verificar que la BD SQLite existe y tiene tablas
DB_CHECK=$($COMPOSE_CMD exec -T app php artisan tinker --execute="
    try {
        \$c = DB::table('users')->count();
        echo 'users:' . \$c;
    } catch (\Exception \$e) {
        echo 'error:' . \$e->getMessage();
    }
" 2>/dev/null | grep -o 'users:[0-9]*' || echo "")

if echo "$DB_CHECK" | grep -q "^users:"; then
    USER_COUNT=$(echo "$DB_CHECK" | grep -o '[0-9]*$')
    ok "Base de datos SQLite OK (${USER_COUNT} usuario(s))"
else
    fail "Error accediendo a la base de datos"
fi

# Verificar configuración de conexiones
SETTINGS_CHECK=$($COMPOSE_CMD exec -T app php artisan tinker --execute="
    \$keys = ['influxdb_url','influxdb_token','grafana_base_url'];
    foreach (\$keys as \$k) {
        \$v = App\Models\Setting::get(\$k);
        echo \$k . ':' . (empty(\$v) ? 'empty' : 'ok') . PHP_EOL;
    }
" 2>/dev/null || echo "")

if echo "$SETTINGS_CHECK" | grep -q "empty"; then
    warn "Algunas configuraciones de conexión están vacías — revisa /configuracion/conexiones"
else
    ok "Configuración de conexiones cargada en la BD"
fi

# ── Predictor ─────────────────────────────────────────────────────────────────
echo -e "\n  ${BOLD}Predictor Prophet${NC} (interno: predictor:8888)"

PRED_HEALTH=$($COMPOSE_CMD exec -T app curl -sf http://predictor:8888/health 2>/dev/null || echo '{}')
if echo "$PRED_HEALTH" | grep -q '"status":"ok"'; then
    ok "Servicio activo (/health OK)"
else
    warn "Predictor no responde aún (Prophet puede tardar ~60s en arrancar)"
fi

# Test básico de predicción
PRED_TEST=$($COMPOSE_CMD exec -T app curl -sf \
    -X POST http://predictor:8888/predict \
    -H "Content-Type: application/json" \
    -d '{"timestamps":["2025-01-01T00:00:00Z","2025-01-01T01:00:00Z","2025-01-01T02:00:00Z"],"values":[0.5,0.6,0.55],"predic_hours":3}' \
    2>/dev/null || echo '{}')

if echo "$PRED_TEST" | grep -q '"forecast"'; then
    ok "Predicción de prueba OK (Prophet respondiendo)"
elif echo "$PRED_TEST" | grep -q '"timestamps"'; then
    ok "Predicción de prueba OK"
else
    warn "Predictor no responde a predicciones (puede estar iniciando)"
fi

# ── Datos demo ─────────────────────────────────────────────────────────────────
if [ "$INFLUXDB_EMPTY" = "1" ] && [ "$NON_INTERACTIVE" = "0" ]; then
    echo ""
    echo -e "${YELLOW}  El bucket de InfluxDB está vacío.${NC}"
    read -rp "  ¿Cargar 400 días de datos de demostración? [S/n]: " LOAD_DEMO
    if [[ ! "$LOAD_DEMO" =~ ^[nN]$ ]]; then
        section "7 · Cargando datos de demostración"
        bash docker/data/import.sh --demo --days 400
    fi
fi

# ── Resumen final ─────────────────────────────────────────────────────────────
section "✓ Instalación completa"

echo ""
echo -e "  ${BOLD}URLs de acceso:${NC}"
echo -e "  ┌────────────────────────────────────────────────────────┐"
echo -e "  │  App       →  ${CYAN}${APP_URL:-http://localhost:8080}${NC}"
echo -e "  │  InfluxDB  →  ${CYAN}http://localhost:8087${NC}"
echo -e "  │  Grafana   →  ${CYAN}http://localhost:3001${NC}  (admin directo)"
echo -e "  └────────────────────────────────────────────────────────┘"
echo ""
echo -e "  ${BOLD}Credenciales:${NC}"
echo -e "  ┌────────────────────────────────────────────────────────┐"
echo -e "  │  Tersime   →  ${ADMIN_USER:-admin}  /  ${ADMIN_PASS:-<configurado>}"
echo -e "  │  Grafana   →  ${GF_ADMIN_USER:-admin}  /  ${GF_ADMIN_PASS:-<configurado>}"
echo -e "  └────────────────────────────────────────────────────────┘"
echo ""
echo -e "  ${BOLD}Datos InfluxDB:${NC}"
echo -e "  Org: ${INFLUX_ORG:-tersime}  ·  Bucket: ${INFLUX_BUCKET_NAME:-tersime}"
echo -e "  Ver estructura: ${CYAN}cat INFLUXDB_SCHEMA.txt${NC}"
echo ""
echo -e "  ${BOLD}Comandos útiles:${NC}"
echo -e "  ·  Cargar datos demo:   ${CYAN}./docker/data/import.sh --demo${NC}"
echo -e "  ·  Importar CSV:        ${CYAN}./docker/data/import.sh mis-datos.csv${NC}"
echo -e "  ·  Ver logs:            ${CYAN}docker compose logs -f${NC}"
echo -e "  ·  Reiniciar:           ${CYAN}docker compose restart${NC}"
echo -e "  ·  Parar todo:          ${CYAN}docker compose down${NC}"
echo -e "  ·  Parar + borrar BD:   ${CYAN}docker compose down -v${NC}"
echo ""
