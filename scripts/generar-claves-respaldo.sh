#!/bin/bash
# Crea una vez las claves del cifrado y la firma de los respaldos, en scripts/firma/.

set -u
CARPETA="$(cd "$(dirname "$0")" && pwd)/firma"

command -v openssl >/dev/null 2>&1 || { echo "Falta openssl (dnf install openssl)."; exit 3; }

if [ -e "$CARPETA/respaldo.clave" ] || [ -e "$CARPETA/respaldo-privada.pem" ]; then
    echo "Las claves ya estan en $CARPETA: no se cambia nada."
    exit 0
fi

umask 077
mkdir -p "$CARPETA" || exit 6
chmod 700 "$CARPETA"

openssl rand -base64 48 > "$CARPETA/respaldo.clave" || exit 9
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out "$CARPETA/respaldo-privada.pem" 2>/dev/null || exit 9
openssl pkey -in "$CARPETA/respaldo-privada.pem" -pubout -out "$CARPETA/respaldo-publica.pem" || exit 9
chmod 600 "$CARPETA/respaldo.clave" "$CARPETA/respaldo-privada.pem"
chmod 644 "$CARPETA/respaldo-publica.pem"

echo "Claves creadas en $CARPETA:"
ls -l "$CARPETA"
echo
echo "Guardar una copia de respaldo.clave y respaldo-privada.pem fuera del servidor."
