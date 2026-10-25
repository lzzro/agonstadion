#!/bin/bash
# =====================================================================
# Lectura de estado del servidor - Stadion (Agon)
# Proyecto de Lucas Martiarena
# ---------------------------------------------------------------------
# Agrega una linea a metricas/metricas.csv con cuatro datos:
#
#     fecha_hora,disco_pct,memoria_pct,httpd,mariadb
#     2026-09-30T02:05:00-03:00,37,52,1,1
#
#     disco_pct     porcentaje ocupado de la particion raiz
#     memoria_pct   porcentaje de RAM en uso
#     httpd         1 si el servicio esta activo, 0 si no
#     mariadb       idem
#
# Los dos servicios van como numero y no como "activo"/"caido" porque
# Grafana grafica numeros: 1 dibuja una linea arriba y 0 un pozo, que
# es justo lo que hay que ver de un vistazo.
#
# De este archivo lee Grafana. El porque de esta forma de medir, en
# lugar de Prometheus y node_exporter, esta en docs/respaldos-cron.md.
#
# Se ejecuta sin preguntar nada: quien lo llama es cron.
# =====================================================================

set -u

CARPETA_SCRIPT="$(cd "$(dirname "$0")" && pwd)"
RAIZ="$(cd "$CARPETA_SCRIPT/.." && pwd)"

DESTINO="$RAIZ/metricas"
ARCHIVO="$DESTINO/metricas.csv"
CABECERA="fecha_hora,disco_pct,memoria_pct,httpd,mariadb"

# Los servicios a vigilar. Son los nombres de AlmaLinux: en Debian el
# de la base se llama distinto.
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

# ---------------------------------------------------------------------
# Disco: porcentaje ocupado de la particion raiz.
# df -P da una sola linea por sistema de archivos, sin cortarla aunque
# el nombre del dispositivo sea largo. El tr saca el signo de porciento.
# ---------------------------------------------------------------------
DISCO="$(df -P / 2>/dev/null | awk 'NR==2 {print $5}' | tr -d '%')"
case "$DISCO" in
    ''|*[!0-9]*) DISCO="" ;;
esac

# ---------------------------------------------------------------------
# Memoria: usada sobre total, en porcentaje entero.
# Se toma la columna "used" de free, que ya descuenta cache y buffers,
# de modo que el numero se parece a lo que de verdad esta ocupado.
# ---------------------------------------------------------------------
MEMORIA="$(free -b 2>/dev/null | awk '/^Mem:/ {if ($2 > 0) printf "%d", ($3 * 100) / $2}')"
case "$MEMORIA" in
    ''|*[!0-9]*) MEMORIA="" ;;
esac

# ---------------------------------------------------------------------
# Servicios. systemctl is-active contesta "active" cuando el servicio
# esta andando, e "inactive" o "failed" cuando no.
#
# Hay un tercer caso que no es ninguno de los dos: que no se pueda
# preguntar, porque systemd no esta o no responde. Ahi no contesta
# nada, y la columna queda vacia en vez de marcar un 0. Un 0 significa
# "el servicio esta caido", y decir eso cuando en realidad nadie pudo
# averiguarlo seria una alarma falsa: mandaria a revisar Apache un
# domingo por un problema que esta en otro lado.
# ---------------------------------------------------------------------
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

# ---------------------------------------------------------------------
# La fecha va en formato ISO con la diferencia horaria incluida, que es
# el que Grafana entiende sin que haya que explicarle nada.
# ---------------------------------------------------------------------
AHORA="$(date +%Y-%m-%dT%H:%M:%S%:z)"

# La cabecera se escribe una sola vez, cuando el archivo todavia no
# existe. Un CSV sin cabecera obliga a nombrar las columnas a mano en
# cada panel.
if [ ! -f "$ARCHIVO" ]; then
    echo "$CABECERA" > "$ARCHIVO" || { echo "ERROR: no se puede escribir $ARCHIVO" >&2; exit 6; }
    chmod 644 "$ARCHIVO"
fi

echo "${AHORA},${DISCO},${MEMORIA},${WEB},${BASE}" >> "$ARCHIVO" \
    || { echo "ERROR: no se puede escribir $ARCHIVO" >&2; exit 6; }

exit 0
