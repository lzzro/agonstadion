#!/bin/bash
# Respaldo diario en backups/: la base, apps/ y public/, cifrado, firmado y con su hash.

set -u

# Rutas.
CARPETA_SCRIPT="$(cd "$(dirname "$0")" && pwd)"
RAIZ="$(cd "$CARPETA_SCRIPT/.." && pwd)"

CREDENCIALES="$CARPETA_SCRIPT/respaldo.local.cnf"
DESTINO="$RAIZ/backups"
BASE="sgdm"

# Las claves del cifrado y de la firma (ver generar-claves-respaldo.sh).
FRASE="$CARPETA_SCRIPT/firma/respaldo.clave"
PRIVADA="$CARPETA_SCRIPT/firma/respaldo-privada.pem"

FECHA="$(date +%F)"
ARCHIVO="$DESTINO/respaldo_${FECHA}.tar.gz"

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

# 1. Comprobaciones previas.
command -v mysqldump >/dev/null 2>&1 \
    || fallar "mysqldump no esta instalado o no esta en el PATH." 3

command -v tar >/dev/null 2>&1 \
    || fallar "tar no esta instalado o no esta en el PATH." 3

[ -f "$CREDENCIALES" ] \
    || fallar "falta el archivo de credenciales $CREDENCIALES (copiar respaldo.local.cnf.ejemplo)." 4

[ -r "$CREDENCIALES" ] \
    || fallar "el archivo de credenciales $CREDENCIALES no se puede leer." 4

# El archivo con la contrasena tiene que ser privado. 600 o 400: solo su duenio.
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

# 2. Volcado de la base en una carpeta temporal.
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

# 3. Un solo comprimido con el volcado y los archivos del proyecto.
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

# 4. Cifrado y firma digital (si estan las claves), y el hash.
if [ -f "$FRASE" ] && [ -f "$PRIVADA" ] && command -v openssl >/dev/null 2>&1; then
    for CLAVE in "$FRASE" "$PRIVADA"; do
        case "$(stat -c '%a' "$CLAVE" 2>/dev/null)" in
            600|400) ;;
            *) fallar "permisos de $CLAVE: deben ser 600 (chmod 600 $CLAVE)." 9 ;;
        esac
    done
    avisar "Cifrando (AES-256)..."
    openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt \
            -in "$PARCIAL" -out "$PARCIAL.enc" -pass "file:$FRASE" 2> "$TEMPORAL/error.txt" \
        || fallar "no se pudo cifrar: $(cat "$TEMPORAL/error.txt")" 9
    avisar "Firmando (RSA y SHA-256)..."
    openssl dgst -sha256 -sign "$PRIVADA" -out "$PARCIAL.enc.sig" "$PARCIAL.enc" 2> "$TEMPORAL/error.txt" \
        || fallar "no se pudo firmar: $(cat "$TEMPORAL/error.txt")" 9
    FINAL="$ARCHIVO.enc"
    mv "$PARCIAL.enc" "$FINAL" && mv "$PARCIAL.enc.sig" "$FINAL.sig" \
        || fallar "no se pudo dejar el respaldo en $DESTINO." 8
    chmod 600 "$FINAL" "$FINAL.sig"
else
    avisar "AVISO: sin las claves de scripts/firma/ (o sin openssl), el respaldo queda sin cifrar ni firmar."
    avisar "       Para cifrarlo y firmarlo: scripts/generar-claves-respaldo.sh"
    FINAL="$ARCHIVO"
    mv "$PARCIAL" "$FINAL" \
        || fallar "no se pudo dejar el comprimido en $FINAL." 8
    chmod 600 "$FINAL"
fi

# El hash, con el nombre del archivo tal como queda en backups/.
( cd "$DESTINO" && sha256sum "$(basename "$FINAL")" > "respaldo_${FECHA}.sha256" ) \
    || fallar "no se pudo calcular el hash." 9
chmod 600 "$DESTINO/respaldo_${FECHA}.sha256"

avisar "Respaldo terminado: $FINAL ($(du -h "$FINAL" | cut -f1))."
exit 0
