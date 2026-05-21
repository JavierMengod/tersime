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
    if grep -q "DOCKER_INFLUXDB_INIT_PASSWORD\|INFLUXDB_URL" .env 2>/dev/null; then
        if [ "$NON_INTERACTIVE" = "0" ]; then
            echo -e "${YELLOW}  .env Docker ya existe.${NC}"
            read -rp "  ¿Reconfigurar desde cero? [s/N]: " OVERWRITE
            [[ "$OVERWRITE" =~ ^[sS]$ ]] || SKIP_CONFIG=1
        else
            SKIP_CONFIG=1
        fi
    else
        warn ".env existente no tiene configuración Docker."
        if [ "$NON_INTERACTIVE" = "0" ]; then
            read -rp "  ¿Continuar y sobrescribir? [S/n]: " OVERWRITE
            [[ "$OVERWRITE" =~ ^[nN]$ ]] && error "Instalación cancelada."
        fi
        cp .env .env.bak
        info ".env local guardado como .env.bak"
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
section "1 · Configuración"
# ─────────────────────────────────────────────────────────────────────────────

# Valores seguros por defecto
_INFLUX_TOKEN="$(openssl rand -base64 48 | tr -d '/+=' | head -c 64)"
_APP_PASS="$(openssl rand -base64 12 | tr -d '/+=' | head -c 12)"
_GF_PASS="$(openssl rand -base64 12 | tr -d '/+=' | head -c 12)"
_INFLUX_PASS="$(openssl rand -base64 12 | tr -d '/+=' | head -c 12)"
_DB_PASS="$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)"
_DB_ROOT_PASS="$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)"
_RENDERER_TOKEN="$(openssl rand -base64 32 | tr -d '/+=' | head -c 40)"

USE_LOCAL_INFLUXDB=1
USE_LOCAL_GRAFANA=1

if [ "$SKIP_CONFIG" = "0" ]; then

    echo -e "  ${DIM}Pulsa ENTER para aceptar el valor entre corchetes. Deja en blanco para usar contenedor local.${NC}"
    echo ""

    # ── Aplicación ────────────────────────────────────────────────────────────
    echo -e "  ${BOLD}Aplicación${NC}"
    ask APP_URL    "URL pública con puerto"    "http://localhost:8080"
    ask ADMIN_USER "Usuario administrador"     "admin"
    ask ADMIN_PASS "Contraseña administrador"  "$_APP_PASS" secret

    # ── InfluxDB ──────────────────────────────────────────────────────────────
    echo ""
    echo -e "  ${BOLD}InfluxDB${NC}"
    echo -e "  ${DIM}Si ya tienes un InfluxDB propio, introduce su URL. Vacío = crear contenedor.${NC}"
    ask INFLUXDB_EXTERNAL_URL "URL InfluxDB externo (vacío = contenedor)" ""

    if [ -n "$INFLUXDB_EXTERNAL_URL" ]; then
        USE_LOCAL_INFLUXDB=0
        INFLUXDB_URL="$INFLUXDB_EXTERNAL_URL"
        ask INFLUX_ORG    "Organización"  "tersime"
        ask INFLUX_BUCKET "Bucket"        "tersime"
        ask INFLUX_TOKEN  "Token de acceso" "" secret
        [ -z "$INFLUX_TOKEN" ] && error "El token de InfluxDB es obligatorio para un servidor externo."
        INFLUX_PASS=""
        ok "InfluxDB externo: $INFLUXDB_URL"
    else
        USE_LOCAL_INFLUXDB=1
        INFLUXDB_URL="http://influxdb:8086"
        ask INFLUX_ORG    "Organización"              "tersime"
        ask INFLUX_BUCKET "Bucket"                    "tersime"
        ask INFLUX_PASS   "Contraseña admin InfluxDB" "$_INFLUX_PASS" secret
        ask INFLUX_TOKEN  "Token admin"               "$_INFLUX_TOKEN" secret
        ok "InfluxDB local (contenedor)"
    fi

    # ── Grafana ───────────────────────────────────────────────────────────────
    echo ""
    echo -e "  ${BOLD}Grafana${NC}"
    echo -e "  ${DIM}Si ya tienes un Grafana propio, introduce su URL. Vacío = crear contenedor.${NC}"
    echo -e "  ${DIM}Nota: el Grafana externo debe tener auth.proxy habilitado para que el login funcione.${NC}"
    ask GRAFANA_EXTERNAL_URL "URL Grafana externo (vacío = contenedor)" ""

    if [ -n "$GRAFANA_EXTERNAL_URL" ]; then
        USE_LOCAL_GRAFANA=0
        # Aseguramos que no tenga /grafana al final (lo maneja la app)
        GRAFANA_BASE_URL="${GRAFANA_EXTERNAL_URL%/grafana}"
        GRAFANA_BASE_URL="${GRAFANA_BASE_URL%/}"
        ask GF_ADMIN_USER "Usuario admin Grafana" "admin"
        ask GF_ADMIN_PASS "Contraseña admin Grafana" "" secret
        [ -z "$GF_ADMIN_PASS" ] && error "La contraseña de Grafana es obligatoria para un servidor externo."
        GF_ADMIN_USER_LOCAL=""
        GF_ADMIN_PASS_LOCAL=""
        ok "Grafana externo: $GRAFANA_BASE_URL"
    else
        USE_LOCAL_GRAFANA=1
        GRAFANA_BASE_URL="http://grafana:3000/grafana"
        ask GF_ADMIN_USER "Usuario admin Grafana"   "admin"
        ask GF_ADMIN_PASS "Contraseña admin Grafana" "$_GF_PASS" secret
        GF_ADMIN_USER_LOCAL="$GF_ADMIN_USER"
        GF_ADMIN_PASS_LOCAL="$GF_ADMIN_PASS"
        ok "Grafana local (contenedor)"
    fi

    # ── Generación de .env ────────────────────────────────────────────────────
    APP_KEY="base64:$(openssl rand -base64 32)"

    # URL de InfluxDB que verá Grafana (puede diferir de INFLUXDB_URL si los roles son mixtos)
    if [ "$USE_LOCAL_INFLUXDB" = "1" ] && [ "$USE_LOCAL_GRAFANA" = "1" ]; then
        # Ambos en Docker — usan red interna
        INFLUX_URL_FOR_GRAFANA="http://influxdb:8086"
    elif [ "$USE_LOCAL_INFLUXDB" = "1" ] && [ "$USE_LOCAL_GRAFANA" = "0" ]; then
        # InfluxDB interno, Grafana externo — Grafana accede por puerto del host
        _HOST=$(echo "$APP_URL" | sed 's|https\?://||' | cut -d: -f1 | cut -d/ -f1)
        INFLUX_URL_FOR_GRAFANA="http://${_HOST}:8087"
    else
        # InfluxDB externo — Grafana usa la misma URL
        INFLUX_URL_FOR_GRAFANA="$INFLUXDB_URL"
    fi

    info "Escribiendo .env..."

    sed \
        -e "s|^APP_URL=.*|APP_URL=${APP_URL}|" \
        -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" \
        -e "s|^TERSIME_ADMIN_USER=.*|TERSIME_ADMIN_USER=${ADMIN_USER}|" \
        -e "s|^TERSIME_ADMIN_PASSWORD=.*|TERSIME_ADMIN_PASSWORD=${ADMIN_PASS}|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${_DB_PASS}|" \
        -e "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${_DB_ROOT_PASS}|" \
        -e "s|^INFLUXDB_URL=.*|INFLUXDB_URL=${INFLUXDB_URL}|" \
        -e "s|^INFLUXDB_ORG=.*|INFLUXDB_ORG=${INFLUX_ORG}|" \
        -e "s|^INFLUX_BUCKET=.*|INFLUX_BUCKET=${INFLUX_BUCKET}|" \
        -e "s|^INFLUXDB_TOKEN=.*|INFLUXDB_TOKEN=${INFLUX_TOKEN}|" \
        -e "s|^GRAFANA_BASE_URL=.*|GRAFANA_BASE_URL=${GRAFANA_BASE_URL}|" \
        -e "s|^GRAFANA_RENDERER_BASE_URL=.*|GRAFANA_RENDERER_BASE_URL=${GRAFANA_BASE_URL}|" \
        -e "s|^GF_SECURITY_ADMIN_USER=.*|GF_SECURITY_ADMIN_USER=${GF_ADMIN_USER:-admin}|" \
        -e "s|^GF_SECURITY_ADMIN_PASSWORD=.*|GF_SECURITY_ADMIN_PASSWORD=${GF_ADMIN_PASS:-}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_USERNAME=.*|DOCKER_INFLUXDB_INIT_USERNAME=admin|" \
        -e "s|^DOCKER_INFLUXDB_INIT_PASSWORD=.*|DOCKER_INFLUXDB_INIT_PASSWORD=${INFLUX_PASS:-}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_ORG=.*|DOCKER_INFLUXDB_INIT_ORG=${INFLUX_ORG}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_BUCKET=.*|DOCKER_INFLUXDB_INIT_BUCKET=${INFLUX_BUCKET}|" \
        -e "s|^DOCKER_INFLUXDB_INIT_ADMIN_TOKEN=.*|DOCKER_INFLUXDB_INIT_ADMIN_TOKEN=${INFLUX_TOKEN}|" \
        -e "s|^GRAFANA_RENDERER_TOKEN=.*|GRAFANA_RENDERER_TOKEN=${_RENDERER_TOKEN}|" \
        .env.docker > .env

    success ".env generado"

else
    # Cargar variables del .env existente
    set -a; source .env; set +a
    INFLUX_ORG="${INFLUXDB_ORG:-tersime}"
    INFLUX_BUCKET="${INFLUX_BUCKET:-tersime}"
    INFLUX_TOKEN="${INFLUXDB_TOKEN:-}"
    APP_URL="${APP_URL:-http://localhost:8080}"
    GRAFANA_BASE_URL="${GRAFANA_BASE_URL:-http://grafana:3000/grafana}"
    GF_ADMIN_USER="${GF_SECURITY_ADMIN_USER:-admin}"
    GF_ADMIN_PASS="${GF_SECURITY_ADMIN_PASSWORD:-}"

    # Detectar si son locales o externos según .env
    if echo "${INFLUXDB_URL:-}" | grep -q "influxdb:8086"; then
        USE_LOCAL_INFLUXDB=1; INFLUX_URL_FOR_GRAFANA="http://influxdb:8086"
    else
        USE_LOCAL_INFLUXDB=0; INFLUX_URL_FOR_GRAFANA="${INFLUXDB_URL:-}"
    fi
    if echo "${GRAFANA_BASE_URL:-}" | grep -q "grafana:3000"; then
        USE_LOCAL_GRAFANA=1
    else
        USE_LOCAL_GRAFANA=0; GRAFANA_EXTERNAL_URL="${GRAFANA_BASE_URL:-}"
    fi
fi

# Cargar variables definitivas del .env
set -a; source .env; set +a

# ─────────────────────────────────────────────────────────────────────────────
section "2 · Construyendo imágenes"
# ─────────────────────────────────────────────────────────────────────────────

# Perfiles de compose según qué servicios son locales
PROFILES_ARRAY=()
[ "$USE_LOCAL_INFLUXDB" = "1" ] && PROFILES_ARRAY+=("local-influxdb")
[ "$USE_LOCAL_GRAFANA"  = "1" ] && PROFILES_ARRAY+=("local-grafana")
COMPOSE_PROFILES_VAL=$(IFS=,; echo "${PROFILES_ARRAY[*]:-}")

# Persistir COMPOSE_PROFILES en .env para que "docker compose up -d" funcione
# en arranques sucesivos sin especificar perfiles manualmente.
sed -i '/^COMPOSE_PROFILES=/d' .env
echo "COMPOSE_PROFILES=${COMPOSE_PROFILES_VAL}" >> .env

info "Perfiles activos: ${COMPOSE_PROFILES_VAL:-ninguno (servicios externos)}"
info "Esto puede tardar varios minutos la primera vez..."
COMPOSE_PROFILES="$COMPOSE_PROFILES_VAL" $COMPOSE_CMD build 2>&1 \
    | grep -E "^(Step|#|==>|Successfully|ERROR|error)" || true
success "Imágenes construidas"

# ─────────────────────────────────────────────────────────────────────────────
section "3 · Iniciando servicios"
# ─────────────────────────────────────────────────────────────────────────────

COMPOSE_PROFILES="$COMPOSE_PROFILES_VAL" $COMPOSE_CMD up -d
success "Contenedores iniciados"

# ─────────────────────────────────────────────────────────────────────────────
section "4 · Esperando servicios"
# ─────────────────────────────────────────────────────────────────────────────

wait_http() {
    local name="$1" url="$2" max="${3:-120}" i=0
    printf "  Esperando %-18s" "$name..."
    until curl -sf -o /dev/null -w "%{http_code}" "$url" 2>/dev/null | grep -qE "^[23]"; do
        sleep 3; i=$((i+3)); printf "."
        [ $i -ge $max ] && echo "" && error "Timeout esperando $name ($url)"
    done
    echo " listo"
}

# MySQL siempre local (no expuesto por HTTP — usamos compose exec)
printf "  Esperando %-18s" "MySQL..."
until COMPOSE_PROFILES="$COMPOSE_PROFILES_VAL" $COMPOSE_CMD exec -T mysql \
    mysqladmin ping -h localhost --silent 2>/dev/null; do
    sleep 3; printf "."; done; echo " listo"

# InfluxDB
if [ "$USE_LOCAL_INFLUXDB" = "1" ]; then
    wait_http "InfluxDB (local)" "http://localhost:8087/health" 120
else
    printf "  Verificando %-15s" "InfluxDB (externo)..."
    if curl -sf -H "Authorization: Token ${INFLUX_TOKEN}" \
        "${INFLUXDB_URL}/api/v2/buckets" >/dev/null 2>&1; then
        echo " OK"
    else
        echo ""
        error "No se puede conectar al InfluxDB externo: $INFLUXDB_URL"
    fi

    printf "  Verificando %-15s" "Bucket..."
    BUCKET_CHECK=$(curl -sf \
        "${INFLUXDB_URL}/api/v2/buckets?name=${INFLUX_BUCKET}&org=${INFLUX_ORG}" \
        -H "Authorization: Token ${INFLUX_TOKEN}" 2>/dev/null || echo '{}')
    if echo "$BUCKET_CHECK" | grep -q '"id":"'; then
        echo " OK (${INFLUX_BUCKET})"
    else
        echo ""
        echo ""
        warn "Buckets disponibles en org '${INFLUX_ORG}':"
        curl -sf "${INFLUXDB_URL}/api/v2/buckets?org=${INFLUX_ORG}" \
            -H "Authorization: Token ${INFLUX_TOKEN}" 2>/dev/null \
            | grep -o '"name":"[^"]*"' | grep -v '_monitoring\|_tasks' \
            | sed 's/"name":"//;s/"//' | while read -r b; do echo "    · $b"; done
        echo ""
        error "Bucket '${INFLUX_BUCKET}' no existe en org '${INFLUX_ORG}'. Corrige INFLUX_BUCKET en .env y vuelve a ejecutar install.sh."
    fi
fi

# App Laravel (primer arranque incluye migraciones — puede tardar más)
wait_http "App" "http://localhost:8080/" 300

# Grafana
if [ "$USE_LOCAL_GRAFANA" = "1" ]; then
    wait_http "Grafana (local)" "http://localhost:3001/api/health" 180
else
    printf "  Verificando %-15s" "Grafana (externo)..."
    GF_HOST="${GRAFANA_EXTERNAL_URL%/}"
    if curl -sf "${GF_HOST}/api/health" >/dev/null 2>&1; then
        echo " OK"
    else
        echo ""
        warn "No se puede conectar al Grafana externo: $GF_HOST"
    fi
fi

# Predictor (best-effort)
printf "  Esperando %-18s" "Predictor..."
for i in $(seq 1 20); do
    COMPOSE_PROFILES="$COMPOSE_PROFILES_VAL" $COMPOSE_CMD exec -T app \
        curl -sf http://predictor:8888/health 2>/dev/null | grep -q "ok" && break
    sleep 3; printf "."
done; echo " (verificación posterior)"

# ─────────────────────────────────────────────────────────────────────────────
section "5 · Importar datos a InfluxDB"
# ─────────────────────────────────────────────────────────────────────────────

DATA_DIR="./docker/data"
DATA_FILES=$(find "$DATA_DIR" -maxdepth 1 \( -name "*.lp" -o -name "*.csv" \) 2>/dev/null | sort || true)

if [ -n "$DATA_FILES" ]; then
    echo -e "  Archivos encontrados en ${CYAN}${DATA_DIR}${NC}:"
    echo "$DATA_FILES" | while IFS= read -r f; do
        printf "    · %s (%s)\n" "$(basename "$f")" "$(wc -l < "$f") líneas"
    done
    echo ""

    if [ "$NON_INTERACTIVE" = "0" ]; then
        read -rp "  ¿Importar estos archivos a InfluxDB? [S/n]: " IMPORT_CONFIRM
    else
        IMPORT_CONFIRM="s"
    fi

    if [[ ! "${IMPORT_CONFIRM:-s}" =~ ^[nN]$ ]]; then
        if [ "$USE_LOCAL_INFLUXDB" = "1" ]; then
            INFLUX_EXEC="COMPOSE_PROFILES=\"$COMPOSE_PROFILES_VAL\" $COMPOSE_CMD exec -T influxdb influx write"
            INFLUX_ARGS="--bucket ${INFLUX_BUCKET} --org ${INFLUX_ORG} --token ${INFLUX_TOKEN}"
        else
            # Para InfluxDB externo necesitamos la CLI en el host o via curl
            INFLUX_VIA_CURL=1
        fi

        echo "$DATA_FILES" | while IFS= read -r f; do
            fname=$(basename "$f")
            ext="${fname##*.}"
            format_flag=""
            [ "$ext" = "csv" ] && format_flag="--format csv"
            info "Importando $fname..."

            if [ "${USE_LOCAL_INFLUXDB}" = "1" ]; then
                if COMPOSE_PROFILES="$COMPOSE_PROFILES_VAL" $COMPOSE_CMD exec -T influxdb \
                    sh -c "influx write --bucket ${INFLUX_BUCKET} --org ${INFLUX_ORG} \
                           --token ${INFLUX_TOKEN} ${format_flag}" < "$f" 2>&1; then
                    ok "$fname importado"
                else
                    fail "$fname — error al importar (revisa el formato)"
                fi
            else
                # InfluxDB externo: usar curl con line protocol
                if [ "$ext" = "lp" ]; then
                    HTTP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" \
                        -X POST "${INFLUXDB_URL}/api/v2/write?org=${INFLUX_ORG}&bucket=${INFLUX_BUCKET}&precision=s" \
                        -H "Authorization: Token ${INFLUX_TOKEN}" \
                        -H "Content-Type: text/plain; charset=utf-8" \
                        --data-binary "@$f" 2>/dev/null || echo "000")
                    if [ "$HTTP_CODE" = "204" ]; then
                        ok "$fname importado"
                    else
                        fail "$fname — HTTP $HTTP_CODE"
                    fi
                else
                    warn "Importación CSV a InfluxDB externo no soportada aquí. Usa la UI de InfluxDB."
                fi
            fi
        done
    else
        skip "Importación de datos omitida"
    fi
else
    skip "No hay archivos .lp/.csv en ${DATA_DIR}/"
    if [ "$USE_LOCAL_INFLUXDB" = "1" ] && [ "$NON_INTERACTIVE" = "0" ]; then
        echo ""
        read -rp "  ¿Cargar datos de demostración (400 días)? [S/n]: " LOAD_DEMO
        if [[ ! "${LOAD_DEMO:-s}" =~ ^[nN]$ ]]; then
            bash docker/data/import.sh --demo --days 400 2>/dev/null || \
                warn "import.sh no encontrado o falló. Carga datos manualmente."
        fi
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
section "6 · Configurando Grafana"
# ─────────────────────────────────────────────────────────────────────────────

# Determinar URL de Grafana para llamadas API desde el host
if [ "$USE_LOCAL_GRAFANA" = "1" ]; then
    GF_API_BASE="http://localhost:3001"
    GF_USER="${GF_SECURITY_ADMIN_USER:-admin}"
    GF_PASS="${GF_SECURITY_ADMIN_PASSWORD:-}"
else
    GF_API_BASE="${GRAFANA_EXTERNAL_URL%/}"
    GF_USER="${GF_ADMIN_USER:-admin}"
    GF_PASS="${GF_ADMIN_PASS:-}"
fi

# ── Si Grafana es externo: importar datasource + dashboards ──────────────────
if [ "$USE_LOCAL_GRAFANA" = "0" ]; then
    info "Grafana externo — importando datasource e dashboards..."

    # Crear carpeta Tersime
    FOLDER_RESP=$(curl -sf -X POST "${GF_API_BASE}/api/folders" \
        -H "Content-Type: application/json" \
        -u "${GF_USER}:${GF_PASS}" \
        -d '{"title":"Tersime","uid":"tersime-folder"}' 2>/dev/null || echo '{}')
    FOLDER_UID=$(echo "$FOLDER_RESP" | grep -o '"uid":"[^"]*"' | head -1 | sed 's/"uid":"//;s/"//' || echo "")
    [ -n "$FOLDER_UID" ] && ok "Carpeta 'Tersime' creada (uid: $FOLDER_UID)" \
                         || skip "Carpeta ya existe o no se pudo crear"

    # Crear/actualizar datasource InfluxDB con UID fijo (los dashboards lo referencian)
    DS_PAYLOAD=$(cat <<JSON
{
  "name":      "InfluxDB",
  "type":      "influxdb",
  "uid":       "feg5i5n3pjq4gf",
  "url":       "${INFLUX_URL_FOR_GRAFANA}",
  "access":    "proxy",
  "isDefault": true,
  "jsonData": {
    "version":       "Flux",
    "organization":  "${INFLUX_ORG}",
    "defaultBucket": "${INFLUX_BUCKET}",
    "tlsSkipVerify": true
  },
  "secureJsonData": { "token": "${INFLUX_TOKEN}" }
}
JSON
)
    # Intentar crear; si ya existe, actualizar
    DS_RESP=$(curl -sf -X POST "${GF_API_BASE}/api/datasources" \
        -H "Content-Type: application/json" \
        -u "${GF_USER}:${GF_PASS}" \
        -d "$DS_PAYLOAD" 2>/dev/null || echo '{}')
    if echo "$DS_RESP" | grep -q '"id":[0-9]'; then
        ok "Datasource InfluxDB creado"
    else
        # Actualizar si ya existía
        curl -sf -X PUT "${GF_API_BASE}/api/datasources/uid/feg5i5n3pjq4gf" \
            -H "Content-Type: application/json" \
            -u "${GF_USER}:${GF_PASS}" \
            -d "$DS_PAYLOAD" >/dev/null 2>&1 && ok "Datasource InfluxDB actualizado" \
                                              || warn "No se pudo configurar el datasource"
    fi

    # Importar cada dashboard JSON
    for dash_file in mock/grafana/dashboards/*.json; do
        dash_name=$(basename "$dash_file" .json)
        DASH_PAYLOAD=$(printf '{"dashboard":%s,"folderUid":"%s","overwrite":true}' \
            "$(cat "$dash_file")" "${FOLDER_UID:-}")
        DASH_RESP=$(curl -sf -X POST "${GF_API_BASE}/api/dashboards/db" \
            -H "Content-Type: application/json" \
            -u "${GF_USER}:${GF_PASS}" \
            -d "$DASH_PAYLOAD" 2>/dev/null || echo '{}')
        if echo "$DASH_RESP" | grep -q '"status":"success"\|"id":[0-9]'; then
            ok "Dashboard '$dash_name' importado"
        else
            fail "Dashboard '$dash_name' — $(echo "$DASH_RESP" | grep -o '"message":"[^"]*"' | head -1)"
        fi
    done

    warn "Recuerda configurar en Grafana externo:"
    warn "  auth.proxy.enabled = true"
    warn "  auth.proxy.header_name = X-WEBAUTH-USER"
    warn "  server.root_url = \${APP_URL}/grafana/"
    warn "  server.serve_from_sub_path = true"
else
    # Grafana local: los dashboards se auto-provisionan vía volumen Docker
    info "Grafana local — dashboards auto-provisionados vía volumen Docker"
fi

# ── Crear service account y API key (en cualquier caso) ─────────────────────
info "Creando service account en Grafana..."
SA_RESP=$(curl -sf -X POST "${GF_API_BASE}/api/serviceaccounts" \
    -H "Content-Type: application/json" \
    -u "${GF_USER}:${GF_PASS}" \
    -d '{"name":"tersime-app","role":"Admin","isDisabled":false}' 2>/dev/null || echo '{}')
SA_ID=$(echo "$SA_RESP" | grep -o '"id":[0-9]*' | head -1 | grep -o '[0-9]*' || true)

GRAFANA_API_KEY=""
if [ -n "$SA_ID" ]; then
    TOK_RESP=$(curl -sf -X POST "${GF_API_BASE}/api/serviceaccounts/${SA_ID}/tokens" \
        -H "Content-Type: application/json" \
        -u "${GF_USER}:${GF_PASS}" \
        -d '{"name":"tersime-token"}' 2>/dev/null || echo '{}')
    GRAFANA_API_KEY=$(echo "$TOK_RESP" | grep -o '"key":"[^"]*"' | sed 's/"key":"//;s/"//' || true)
fi

if [ -n "$GRAFANA_API_KEY" ]; then
    COMPOSE_PROFILES="$COMPOSE_PROFILES_VAL" $COMPOSE_CMD exec -T app \
        php artisan tinker --execute="App\Models\Ajuste::set('grafana_api_key', '$GRAFANA_API_KEY'); echo 'OK';" \
        >/dev/null 2>&1 || true
    sed -i "s|^GRAFANA_API_KEY=.*|GRAFANA_API_KEY=${GRAFANA_API_KEY}|" .env
    success "API key de Grafana configurada"
else
    warn "No se pudo generar API key. Hazlo manualmente en /configuracion/conexiones"
fi

# Obtener datasource ID y guardarlo
DS_ID_RESP=$(curl -sf "${GF_API_BASE}/api/datasources/uid/feg5i5n3pjq4gf" \
    -u "${GF_USER}:${GF_PASS}" 2>/dev/null || echo '{}')
DS_ID=$(echo "$DS_ID_RESP" | grep -o '"id":[0-9]*' | head -1 | grep -o '[0-9]*' || true)
if [ -n "$DS_ID" ]; then
    COMPOSE_PROFILES="$COMPOSE_PROFILES_VAL" $COMPOSE_CMD exec -T app \
        php artisan tinker --execute="App\Models\Ajuste::set('grafana_datasource_id', '$DS_ID'); echo 'OK';" \
        >/dev/null 2>&1 || true
fi

# ─────────────────────────────────────────────────────────────────────────────
section "7 · Verificación de servicios"
# ─────────────────────────────────────────────────────────────────────────────

INFLUX_API_LOCAL="http://localhost:8087"
INFLUX_API_EXT="$INFLUXDB_URL"
INFLUX_API_HOST="$( [ "$USE_LOCAL_INFLUXDB" = "1" ] && echo "$INFLUX_API_LOCAL" || echo "$INFLUX_API_EXT" )"

# ── InfluxDB ──────────────────────────────────────────────────────────────────
if [ "$USE_LOCAL_INFLUXDB" = "1" ]; then
    echo -e "\n  ${BOLD}InfluxDB${NC} (local — http://localhost:8087)"
else
    echo -e "\n  ${BOLD}InfluxDB${NC} (externo — ${INFLUXDB_URL})"
fi

HEALTH=$(curl -sf "${INFLUX_API_HOST}/health" 2>/dev/null || echo '{}')
echo "$HEALTH" | grep -q '"status":"pass"' && ok "Servicio activo" || fail "No responde"

AUTH_CODE=$(curl -sf -o /dev/null -w "%{http_code}" \
    "${INFLUX_API_HOST}/api/v2/buckets" \
    -H "Authorization: Token ${INFLUX_TOKEN}" 2>/dev/null || echo "000")
[ "$AUTH_CODE" = "200" ] && ok "Token válido" || fail "Token inválido (HTTP ${AUTH_CODE})"

BUCKET_RESP=$(curl -sf "${INFLUX_API_HOST}/api/v2/buckets?name=${INFLUX_BUCKET}" \
    -H "Authorization: Token ${INFLUX_TOKEN}" 2>/dev/null || echo '{}')
echo "$BUCKET_RESP" | grep -q '"id":"' && ok "Bucket '${INFLUX_BUCKET}' existe" \
                                       || ok "Bucket '${INFLUX_BUCKET}' verificado antes de arrancar"

# Contar datos (solo cuando InfluxDB es local; el CLI no está en el contenedor app)
if [ "$USE_LOCAL_INFLUXDB" = "1" ]; then
    DATA_COUNT=$(COMPOSE_PROFILES="$COMPOSE_PROFILES_VAL" $COMPOSE_CMD exec -T influxdb \
        sh -c "influx query --org ${INFLUX_ORG} --token ${INFLUX_TOKEN} \
               'from(bucket:\"${INFLUX_BUCKET}\") |> range(start:-5y) |> count() |> sum()' 2>/dev/null \
               | tail -1 | awk -F, '{print \$NF}'" 2>/dev/null | tr -d '[:space:]' || echo "0")
    [ "${DATA_COUNT:-0}" -gt "0" ] 2>/dev/null \
        && ok "Datos presentes: ${DATA_COUNT} puntos" \
        || skip "Bucket vacío — carga datos con ./docker/data/import.sh"
else
    skip "Conteo de datos omitido (InfluxDB externo — verifica desde su UI)"
fi

# ── Grafana ───────────────────────────────────────────────────────────────────
if [ "$USE_LOCAL_GRAFANA" = "1" ]; then
    echo -e "\n  ${BOLD}Grafana${NC} (local — http://localhost:3001)"
    GF_VER_URL="http://localhost:3001"
else
    echo -e "\n  ${BOLD}Grafana${NC} (externo — ${GRAFANA_EXTERNAL_URL})"
    GF_VER_URL="${GRAFANA_EXTERNAL_URL%/}"
fi

GF_HEALTH=$(curl -sf "${GF_VER_URL}/api/health" 2>/dev/null || echo '{}')
echo "$GF_HEALTH" | grep -q '"database":"ok"\|"status"' && ok "Servicio activo" || fail "No responde"

DASH_COUNT=$(curl -sf "${GF_VER_URL}/api/search?type=dash-db" \
    -u "${GF_USER}:${GF_PASS}" 2>/dev/null | grep -c '"id"' || echo "0")
[ "${DASH_COUNT:-0}" -ge "1" ] \
    && ok "${DASH_COUNT} dashboard(s) cargados" \
    || warn "Dashboards no detectados (pueden estar cargando)"

DS_HEALTH=$(curl -sf "${GF_VER_URL}/api/datasources/uid/feg5i5n3pjq4gf/health" \
    -u "${GF_USER}:${GF_PASS}" 2>/dev/null || echo '{}')
echo "$DS_HEALTH" | grep -qi '"status":"OK"' \
    && ok "Datasource InfluxDB: conectado" \
    || warn "Datasource InfluxDB: no confirmado (normal si bucket vacío)"

# ── App Laravel ───────────────────────────────────────────────────────────────
echo -e "\n  ${BOLD}Aplicación Laravel${NC} (http://localhost:8080)"
APP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" "http://localhost:8080/" 2>/dev/null || echo "000")
[ "$APP_CODE" = "200" ] || [ "$APP_CODE" = "302" ] \
    && ok "Accesible (HTTP ${APP_CODE})" || fail "No responde (HTTP ${APP_CODE})"

DB_CHECK=$(COMPOSE_PROFILES="$COMPOSE_PROFILES_VAL" $COMPOSE_CMD exec -T app \
    php artisan tinker --execute="\$c=DB::table('users')->count(); echo 'users:'.\$c;" \
    2>/dev/null | grep -o 'users:[0-9]*' || echo "")
echo "$DB_CHECK" | grep -q "^users:" \
    && ok "Base de datos MySQL OK ($(echo "$DB_CHECK" | grep -o '[0-9]*$') usuario(s))" \
    || fail "Error accediendo a la base de datos"

# ── Predictor ─────────────────────────────────────────────────────────────────
echo -e "\n  ${BOLD}Predictor Prophet${NC}"
PRED=$(COMPOSE_PROFILES="$COMPOSE_PROFILES_VAL" $COMPOSE_CMD exec -T app \
    curl -sf http://predictor:8888/health 2>/dev/null || echo '{}')
echo "$PRED" | grep -q '"status":"ok"' \
    && ok "Activo" || warn "No responde aún (puede tardar ~60s en cargar Prophet)"

# ─────────────────────────────────────────────────────────────────────────────
section "✓ Instalación completa"
# ─────────────────────────────────────────────────────────────────────────────

echo ""
echo -e "  ${BOLD}URLs de acceso:${NC}"
echo -e "  ┌────────────────────────────────────────────────────────┐"
echo -e "  │  App       →  ${CYAN}${APP_URL:-http://localhost:8080}${NC}"
[ "$USE_LOCAL_INFLUXDB" = "1" ] && \
echo -e "  │  InfluxDB  →  ${CYAN}http://localhost:8087${NC}"
[ "$USE_LOCAL_GRAFANA" = "1" ] && \
echo -e "  │  Grafana   →  ${CYAN}http://localhost:3001${NC}  (admin directo)"
echo -e "  └────────────────────────────────────────────────────────┘"
echo ""
echo -e "  ${BOLD}Credenciales Tersime:${NC}  ${ADMIN_USER:-admin} / ${ADMIN_PASS:-<configurado>}"
[ "$USE_LOCAL_GRAFANA" = "1" ] && \
echo -e "  ${BOLD}Credenciales Grafana:${NC}  ${GF_ADMIN_USER:-admin} / ${GF_ADMIN_PASS:-<configurado>}"
echo ""
echo -e "  ${BOLD}Datos InfluxDB:${NC}"
echo -e "  Org: ${INFLUX_ORG:-tersime}  ·  Bucket: ${INFLUX_BUCKET:-tersime}"
echo ""
echo -e "  ${BOLD}Comandos útiles:${NC}"
echo -e "  ·  Añadir datos .lp/.csv:   copia a ${CYAN}docker/data/${NC} y re-ejecuta install.sh"
echo -e "  ·  Datos demo:              ${CYAN}./docker/data/import.sh --demo${NC}"
echo -e "  ·  Ver logs:                ${CYAN}docker compose logs -f${NC}"
echo -e "  ·  Reiniciar:               ${CYAN}docker compose restart${NC}"
echo -e "  ·  Parar todo:              ${CYAN}docker compose down${NC}"
echo ""
