#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# Tersime — Importación de datos a InfluxDB
#
# Uso:
#   ./docker/data/import.sh datos.csv          # importar CSV
#   ./docker/data/import.sh datos.lp           # importar Line Protocol
#   ./docker/data/import.sh --demo             # cargar 400 días de datos demo
#   ./docker/data/import.sh --demo --days 90   # demo con N días
#
# Formato CSV esperado (ver docker/data/sample.csv y INFLUXDB_SCHEMA.txt):
#   timestamp,device,kwh
#   2025-01-01T00:00:00Z,oficina,0.875
# ─────────────────────────────────────────────────────────────────────────────
set -e

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${CYAN}[import]${NC} $*"; }
success() { echo -e "${GREEN}[import]${NC} $*"; }
warn()    { echo -e "${YELLOW}[import]${NC} $*"; }
error()   { echo -e "${RED}[import]${NC} $*"; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$PROJECT_DIR"

COMPOSE_CMD="docker compose"
$COMPOSE_CMD version >/dev/null 2>&1 || COMPOSE_CMD="docker-compose"

# ── Leer configuración del .env ───────────────────────────────────────────────
if [ ! -f .env ]; then
    error ".env no encontrado. Ejecuta ./install.sh primero."
fi
set -a; source .env; set +a

INFLUX_HOST="localhost:8087"
INFLUX_ORG="${INFLUXDB_ORG:-tersime}"
INFLUX_BUCKET="${INFLUX_BUCKET:-tersime}"
INFLUX_TOKEN="${INFLUXDB_TOKEN}"
DEMO_DAYS=400

# ── Parsear argumentos ────────────────────────────────────────────────────────
MODE=""
INPUT_FILE=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --demo)    MODE="demo";  shift ;;
        --days)    DEMO_DAYS="$2"; shift 2 ;;
        --help|-h)
            echo "Uso: $0 [--demo [--days N]] | [fichero.csv | fichero.lp]"
            echo ""
            echo "  --demo          Cargar datos de demostración (${DEMO_DAYS} días, 3 dispositivos)"
            echo "  --days N        Número de días de datos demo (por defecto: 400)"
            echo "  fichero.csv     Importar datos desde CSV"
            echo "  fichero.lp      Importar datos en formato InfluxDB Line Protocol"
            exit 0
            ;;
        -*)        error "Opción desconocida: $1" ;;
        *)         INPUT_FILE="$1"; MODE="file"; shift ;;
    esac
done

[ -z "$MODE" ] && error "Especifica un fichero o usa --demo. Usa --help para ver la ayuda."

# ── Verificar que InfluxDB está activo ────────────────────────────────────────
info "Verificando conexión con InfluxDB (http://${INFLUX_HOST})..."
if ! curl -sf "http://${INFLUX_HOST}/health" | grep -q "pass"; then
    error "InfluxDB no responde en http://${INFLUX_HOST}. ¿Está el stack corriendo?"
fi
success "InfluxDB OK"

# ── Función: escribir line protocol en InfluxDB ───────────────────────────────
write_lp() {
    local lp_file="$1"
    local line_count
    line_count=$(wc -l < "$lp_file")
    info "Enviando ${line_count} líneas a InfluxDB..."

    HTTP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" \
        -X POST "http://${INFLUX_HOST}/api/v2/write?org=${INFLUX_ORG}&bucket=${INFLUX_BUCKET}&precision=s" \
        -H "Authorization: Token ${INFLUX_TOKEN}" \
        -H "Content-Type: text/plain; charset=utf-8" \
        --data-binary @"$lp_file" 2>&1)

    if [ "$HTTP_CODE" = "204" ] || [ "$HTTP_CODE" = "200" ]; then
        success "Escritura OK (HTTP ${HTTP_CODE})"
    else
        # Intentar con respuesta de error para diagnóstico
        RESP=$(curl -s -X POST "http://${INFLUX_HOST}/api/v2/write?org=${INFLUX_ORG}&bucket=${INFLUX_BUCKET}&precision=s" \
            -H "Authorization: Token ${INFLUX_TOKEN}" \
            -H "Content-Type: text/plain; charset=utf-8" \
            --data-binary @"$lp_file")
        error "Escritura fallida (HTTP ${HTTP_CODE}): ${RESP}"
    fi
}

# ── Función: convertir CSV a Line Protocol ────────────────────────────────────
csv_to_lp() {
    local csv_file="$1"
    local lp_file="$2"

    # Verificar cabecera mínima
    local header
    header=$(head -1 "$csv_file")
    if ! echo "$header" | grep -qE "timestamp.*device.*kwh|timestamp.*kwh.*device"; then
        error "El CSV debe tener columnas: timestamp, device, kwh (ver docker/data/sample.csv)"
    fi

    info "Convirtiendo CSV a Line Protocol..."

    # Usar python3 si está disponible, si no usar awk
    if command -v python3 >/dev/null 2>&1; then
        python3 - "$csv_file" "$lp_file" <<'PYEOF'
import sys
import csv
from datetime import datetime, timezone

in_file, out_file = sys.argv[1], sys.argv[2]
lp_lines = []
hourly_points = {}  # (device, day) -> list of kwh

with open(in_file, newline='') as f:
    reader = csv.DictReader(f)
    for row in reader:
        ts_str  = row['timestamp'].strip()
        device  = row['device'].strip().replace(' ', r'\ ').replace(',', r'\,').replace('=', r'\=')
        kwh     = float(row['kwh'].strip())

        # Parse timestamp → epoch seconds
        for fmt in ('%Y-%m-%dT%H:%M:%SZ', '%Y-%m-%dT%H:%M:%S', '%Y-%m-%d %H:%M:%S'):
            try:
                dt = datetime.strptime(ts_str, fmt).replace(tzinfo=timezone.utc)
                break
            except ValueError:
                continue
        else:
            sys.stderr.write(f"Timestamp inválido: {ts_str}\n")
            continue

        epoch = int(dt.timestamp())

        # Tags opcionales
        extra_tags = ''
        if 'dev_eui' in row and row['dev_eui'].strip():
            extra_tags += f",dev_eui={row['dev_eui'].strip()}"
        if 'device_name' in row and row['device_name'].strip():
            dn = row['device_name'].strip().replace(' ', r'\ ')
            extra_tags += f",device_name={dn}"

        lp_lines.append(f"hourly,name={device}{extra_tags} kwh={kwh} {epoch}")

        # Acumular para daily
        day_key = (device, dt.strftime('%Y-%m-%d'))
        if day_key not in hourly_points:
            day_epoch = int(dt.replace(hour=0, minute=0, second=0).timestamp())
            hourly_points[day_key] = {'epoch': day_epoch, 'total': 0.0}
        hourly_points[day_key]['total'] = round(hourly_points[day_key]['total'] + kwh, 4)

# Añadir puntos daily
for (device, day), info in hourly_points.items():
    lp_lines.append(f"daily,name={device} kwh_total={info['total']} {info['epoch']}")

with open(out_file, 'w') as f:
    f.write('\n'.join(lp_lines))

print(f"  {len(lp_lines)} líneas generadas ({len(lp_lines) - len(hourly_points)} hourly + {len(hourly_points)} daily)")
PYEOF
    else
        error "python3 no está disponible. Convierte el CSV manualmente a Line Protocol."
    fi
}

# ── Modo DEMO ─────────────────────────────────────────────────────────────────
if [ "$MODE" = "demo" ]; then
    info "Cargando datos de demostración (${DEMO_DAYS} días, 3 dispositivos)..."

    # Copiar seed.py al contenedor y ejecutarlo
    TMP_SEED=$(mktemp)
    # Adaptar seed.py para que use el bucket y org del entorno
    sed \
        -e "s|INFLUX_URL.*=.*\".*\"|INFLUX_URL = \"http://localhost:8086\"|" \
        -e "s|INFLUX_TOKEN.*=.*\".*\"|INFLUX_TOKEN = \"${INFLUX_TOKEN}\"|" \
        -e "s|INFLUX_ORG.*=.*\".*\"|INFLUX_ORG = \"${INFLUX_ORG}\"|" \
        -e "s|INFLUX_BUCKET.*=.*\".*\"|INFLUX_BUCKET = \"${INFLUX_BUCKET}\"|" \
        -e "s|^DAYS.*=.*|DAYS = ${DEMO_DAYS}|" \
        mock/seed.py > "$TMP_SEED"

    $COMPOSE_CMD cp "$TMP_SEED" influxdb:/tmp/seed.py
    rm -f "$TMP_SEED"

    info "Ejecutando seed en el contenedor (puede tardar 1-2 min)..."
    $COMPOSE_CMD exec influxdb python3 /tmp/seed.py
    success "Datos demo cargados: ${DEMO_DAYS} días × 3 dispositivos (oficina, servidores, almacen)"
    echo ""
    echo "  Dispositivos disponibles en Tersime:"
    echo "    · Tag: oficina     → Oficina Principal"
    echo "    · Tag: servidores  → Sala Servidores"
    echo "    · Tag: almacen     → Almacén"
    echo ""
    echo "  Configura estos tags en: Monitorización → Dispositivos → campo 'Tag InfluxDB'"
    exit 0
fi

# ── Modo FICHERO ──────────────────────────────────────────────────────────────
[ -z "$INPUT_FILE" ] && error "Fichero no especificado."
[ ! -f "$INPUT_FILE" ] && error "Fichero no encontrado: $INPUT_FILE"

EXT="${INPUT_FILE##*.}"
TMP_LP=$(mktemp /tmp/tersime_import_XXXXXX.lp)
trap "rm -f $TMP_LP" EXIT

case "$EXT" in
    csv|CSV)
        csv_to_lp "$INPUT_FILE" "$TMP_LP"
        write_lp "$TMP_LP"
        ;;
    lp|LP|txt|TXT)
        cp "$INPUT_FILE" "$TMP_LP"
        write_lp "$TMP_LP"
        ;;
    *)
        error "Formato no reconocido: .${EXT}. Usa .csv o .lp"
        ;;
esac

# ── Verificar que los datos llegaron ─────────────────────────────────────────
info "Verificando datos importados..."
DEVICES_RESP=$($COMPOSE_CMD exec -T influxdb influx query \
    --org "$INFLUX_ORG" --token "$INFLUX_TOKEN" \
    "from(bucket: \"${INFLUX_BUCKET}\") |> range(start: -5y) |> filter(fn: (r) => r._measurement == \"hourly\") |> distinct(column: \"name\") |> keep(columns: [\"name\"])" \
    2>/dev/null | grep -v "^#\|^,\|^result\|^table\|^$" | awk -F',' '{print $NF}' | sort -u | tr '\n' ' ')

if [ -n "$DEVICES_RESP" ]; then
    success "Dispositivos con datos: ${DEVICES_RESP}"
else
    warn "No se detectaron dispositivos. Revisa el formato del fichero."
fi
