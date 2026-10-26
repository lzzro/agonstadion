#!/bin/bash
# Anota disco, memoria y el estado de los servicios en metricas/metricas.csv (cada 5 minutos, con cron).

set -u

CARPETA_SCRIPT="$(cd "$(dirname "$0")" && pwd)"
RAIZ="$(cd "$CARPETA_SCRIPT/.." && pwd)"

DESTINO="$RAIZ/metricas"
ARCHIVO="$DESTINO/metricas.csv"
CABECERA="fecha_hora,disco_pct,memoria_pct,httpd,mariadb"

# Los servicios a vigilar.
SERVICIO_WEB="httpd"
SERVICIO_BASE="mariadb"

case "${1:-}" in
    -h|--ayuda|ayuda)
        echo "Uso: $0"
        echo
        echo "Agrega una linea con el estado del servidor a:"
        echo "    $ARCHIVO"
        echo
        echo "Columnas: $CABECERA"
        exit 0
        ;;
    "")
        ;;
    *)
        echo "ERROR: argumento desconocido: $1 (probar: $0 --ayuda)" >&2
        exit 2
        ;;
esac

mkdir -p "$DESTINO" || { echo "ERROR: no se puede crear $DESTINO" >&2; exit 6; }
[ -w "$DESTINO" ]   || { echo "ERROR: no hay permiso de escritura en $DESTINO" >&2; exit 6; }

DISCO="$(df -P / 2>/dev/null | awk 'NR==2 {print $5}' | tr -d '%')"
case "$DISCO" in
    ''|*[!0-9]*) DISCO="" ;;
esac

# Memoria: usada sobre total, en porcentaje entero.
MEMORIA="$(free -b 2>/dev/null | awk '/^Mem:/ {if ($2 > 0) printf "%d", ($3 * 100) / $2}')"
case "$MEMORIA" in
    ''|*[!0-9]*) MEMORIA="" ;;
esac

estado_servicio() {
    local RESPUESTA

    if ! command -v systemctl >/dev/null 2>&1; then
        echo ""
        return
    fi

    RESPUESTA="$(systemctl is-active "$1" 2>/dev/null)"

    if [ -z "$RESPUESTA" ]; then
        echo ""
    elif [ "$RESPUESTA" = "active" ]; then
        echo "1"
    else
        echo "0"
    fi
}

WEB="$(estado_servicio "$SERVICIO_WEB")"
BASE="$(estado_servicio "$SERVICIO_BASE")"

AHORA="$(date +%Y-%m-%dT%H:%M:%S%:z)"

# La cabecera se escribe una sola vez, cuando el archivo todavia no existe.
if [ ! -f "$ARCHIVO" ]; then
    echo "$CABECERA" > "$ARCHIVO" || { echo "ERROR: no se puede escribir $ARCHIVO" >&2; exit 6; }
    chmod 644 "$ARCHIVO"
fi

echo "${AHORA},${DISCO},${MEMORIA},${WEB},${BASE}" >> "$ARCHIVO" \
    || { echo "ERROR: no se puede escribir $ARCHIVO" >&2; exit 6; }

exit 0
