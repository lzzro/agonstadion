#!/bin/bash
# =====================================================================
# Respaldo diario de SGDM - Stadion (Agon)
# Proyecto de Lucas Martiarena
# ---------------------------------------------------------------------
# Deja en backups/ un solo archivo por dia:
#
#     backups/respaldo_2026-09-30.tar.gz
#
# Adentro van tres cosas:
#     base-sgdm.sql   volcado completo de la base sgdm
#     apps/           el codigo PHP
#     public/         las paginas, el CSS y las imagenes
#
# La contrasena de sgdm_admin NO esta en este archivo. Va en
# scripts/respaldo.local.cnf, que el .gitignore excluye. Se le pasa a
# mysqldump con --defaults-extra-file y no como argumento: lo que va en
# la linea de comandos lo ve cualquiera con un "ps aux".
#
# Sin rotacion: los respaldos viejos quedan donde estan. Borrarlos o
# mandarlos afuera es una decision aparte, todavia no tomada.
#
# Se ejecuta sin preguntar nada, porque quien lo llama es cron y no hay
# nadie del otro lado para contestar.
# =====================================================================

set -u

# ---------------------------------------------------------------------
# Rutas. Se deducen de donde esta el script, asi el mismo archivo sirve
# en la VM del instituto y en cualquier copia del proyecto.
# ---------------------------------------------------------------------
CARPETA_SCRIPT="$(cd "$(dirname "$0")" && pwd)"
RAIZ="$(cd "$CARPETA_SCRIPT/.." && pwd)"

CREDENCIALES="$CARPETA_SCRIPT/respaldo.local.cnf"
DESTINO="$RAIZ/backups"
BASE="sgdm"

FECHA="$(date +%F)"
ARCHIVO="$DESTINO/respaldo_${FECHA}.tar.gz"

# ---------------------------------------------------------------------
# Cada mensaje sale con la hora adelante, para que el registro que deja
# cron se pueda leer de arriba abajo.
# ---------------------------------------------------------------------
avisar() {
    echo "[$(date '+%F %T')] $1"
}

fallar() {
    avisar "ERROR: $1"
    exit "$2"
}

case "${1:-}" in
    -h|--ayuda|ayuda)
        echo "Uso: $0"
        echo
        echo "Respalda la base $BASE y los archivos del proyecto en:"
        echo "    $DESTINO/respaldo_AAAA-MM-DD.tar.gz"
        echo
        echo "Sin argumentos. La contrasena de la base se lee de:"
        echo "    $CREDENCIALES"
        exit 0
        ;;
    "")
        ;;
    *)
        fallar "argumento desconocido: $1 (probar: $0 --ayuda)" 2
        ;;
esac

avisar "Respaldo de $BASE iniciado."

# ---------------------------------------------------------------------
# 1. Comprobaciones previas. Conviene que falle aca, con un mensaje
#    claro, y no a la mitad dejando un archivo incompleto.
# ---------------------------------------------------------------------
command -v mysqldump >/dev/null 2>&1 \
    || fallar "mysqldump no esta instalado o no esta en el PATH." 3

command -v tar >/dev/null 2>&1 \
    || fallar "tar no esta instalado o no esta en el PATH." 3

[ -f "$CREDENCIALES" ] \
    || fallar "falta el archivo de credenciales $CREDENCIALES (copiar respaldo.local.cnf.ejemplo)." 4

[ -r "$CREDENCIALES" ] \
    || fallar "el archivo de credenciales $CREDENCIALES no se puede leer." 4

# El archivo con la contrasena tiene que ser privado. 600 o 400: solo
# su duenio. Si lo puede leer el grupo o cualquiera, el respaldo no
# arranca, para que el problema no pase desapercibido.
PERMISOS="$(stat -c '%a' "$CREDENCIALES" 2>/dev/null || echo '')"
case "$PERMISOS" in
    600|400) ;;
    *) fallar "permisos $PERMISOS en $CREDENCIALES; deben ser 600 (chmod 600 $CREDENCIALES)." 4 ;;
esac

for CARPETA in apps public; do
    [ -d "$RAIZ/$CARPETA" ] \
        || fallar "no aparece la carpeta $RAIZ/$CARPETA." 5
done

mkdir -p "$DESTINO" \
    || fallar "no se puede crear la carpeta $DESTINO." 6

[ -w "$DESTINO" ] \
    || fallar "no hay permiso de escritura en $DESTINO." 6

# ---------------------------------------------------------------------
# 2. Volcado de la base en una carpeta temporal.
#    Primero el volcado, despues el comprimido: si mysqldump falla, no
#    llega a armarse ningun .tar.gz y el respaldo del dia anterior
#    queda intacto.
# ---------------------------------------------------------------------
TEMPORAL="$(mktemp -d)" || fallar "no se puede crear la carpeta temporal." 6
trap 'rm -rf "$TEMPORAL"' EXIT

VOLCADO="$TEMPORAL/base-sgdm.sql"

avisar "Volcando la base $BASE..."
mysqldump --defaults-extra-file="$CREDENCIALES" \
          --single-transaction \
          --routines \
          --triggers \
          --databases "$BASE" > "$VOLCADO" 2> "$TEMPORAL/error.txt"

if [ $? -ne 0 ]; then
    avisar "mysqldump devolvio lo siguiente:"
    sed 's/^/    /' "$TEMPORAL/error.txt"
    fallar "el volcado de la base fallo; no se genera ningun archivo." 7
fi

[ -s "$VOLCADO" ] \
    || fallar "el volcado de la base salio vacio; no se genera ningun archivo." 7

avisar "Base volcada: $(du -h "$VOLCADO" | cut -f1)."

# ---------------------------------------------------------------------
# 3. Un solo comprimido con el volcado y los archivos del proyecto.
#    Se arma con otro nombre y recien al final se lo mueve al definitivo,
#    asi nunca queda un respaldo_AAAA-MM-DD.tar.gz a medio escribir.
# ---------------------------------------------------------------------
PARCIAL="$TEMPORAL/respaldo.tar.gz"

avisar "Comprimiendo la base y los archivos del proyecto..."
tar -czf "$PARCIAL" \
    -C "$TEMPORAL" base-sgdm.sql \
    -C "$RAIZ" apps public 2> "$TEMPORAL/error.txt"

if [ $? -ne 0 ]; then
    avisar "tar devolvio lo siguiente:"
    sed 's/^/    /' "$TEMPORAL/error.txt"
    fallar "no se pudo armar el comprimido." 8
fi

mv "$PARCIAL" "$ARCHIVO" \
    || fallar "no se pudo dejar el comprimido en $ARCHIVO." 8

chmod 600 "$ARCHIVO"

avisar "Respaldo terminado: $ARCHIVO ($(du -h "$ARCHIVO" | cut -f1))."
exit 0
