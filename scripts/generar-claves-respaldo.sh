#!/bin/bash
# =====================================================================
# Claves del cifrado y la firma de los respaldos - Stadion (Agon)
# ---------------------------------------------------------------------
#   bash scripts/generar-claves-respaldo.sh
#
# Se corre UNA vez, en el servidor, con la cuenta que corre el respaldo
# (la de cron). Crea en scripts/firma/:
#   respaldo.clave          la frase del cifrado AES-256: 48 bytes al
#                           azar (openssl rand), nadie la escribe
#   respaldo-privada.pem    la clave privada RSA de 3072 bits: firma
#   respaldo-publica.pem    la publica: verifica (se puede copiar a
#                           otra maquina para verificar alli)
# Las dos primeras quedan en modo 600 y la carpeta en 700: solo su
# duenio. Nada de esto va al repositorio (.gitignore).
#
# IMPORTANTE: sin respaldo.clave, un respaldo cifrado no se puede abrir
# nunca. Guardar una copia de la frase y de la clave privada fuera del
# servidor (un pendrive guardado), no junto a los respaldos.
#
# Si las claves ya estan, no hace nada: pisarlas dejaria sin abrir los
# respaldos hechos con las anteriores.
# =====================================================================

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
