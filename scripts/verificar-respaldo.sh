#!/bin/bash
# =====================================================================
# Verificar (y abrir) un respaldo - Stadion (Agon)
# ---------------------------------------------------------------------
#   bash scripts/verificar-respaldo.sh backups/respaldo_AAAA-MM-DD.tar.gz.enc
#   bash scripts/verificar-respaldo.sh backups/respaldo_AAAA-MM-DD.tar.gz.enc --abrir CARPETA
#
# Comprueba, en orden:
#   1. el hash: el SHA-256 del archivo es el anotado en el .sha256
#   2. la firma digital: el .sig se hizo con la clave privada del
#      servidor sobre ESTE archivo (openssl dgst -verify, con la clave
#      publica de scripts/firma/respaldo-publica.pem)
# Con --abrir, ademas lo descifra (con scripts/firma/respaldo.clave) y
# lo desempaqueta en CARPETA, que tiene que estar vacia o no existir:
# ahi quedan base-sgdm.sql, apps/ y public/. Para restaurar la base,
# ver docs/respaldos-cron.md.
#
# Sale con 0 si todo da bien; con 1 si el hash o la firma no coinciden
# (el archivo cambio, o no es de este servidor: no usarlo).
# =====================================================================

set -u
# Que el descifrado cuente aunque vaya en una tuberia con tar.
set -o pipefail
CARPETA_SCRIPT="$(cd "$(dirname "$0")" && pwd)"
PUBLICA="$CARPETA_SCRIPT/firma/respaldo-publica.pem"
FRASE="$CARPETA_SCRIPT/firma/respaldo.clave"

ARCHIVO="${1:-}"
if [ -z "$ARCHIVO" ] || [ ! -f "$ARCHIVO" ]; then
    echo "Uso: $0 backups/respaldo_AAAA-MM-DD.tar.gz.enc [--abrir CARPETA]"
    exit 2
fi
ABRIR=""
if [ "${2:-}" = "--abrir" ]; then
    ABRIR="${3:-}"
    [ -n "$ABRIR" ] || { echo "Falta la carpeta donde abrirlo."; exit 2; }
fi

DIA="$(basename "$ARCHIVO" | sed -E 's/^respaldo_([0-9-]+)\..*$/\1/')"
HASH="$(dirname "$ARCHIVO")/respaldo_${DIA}.sha256"

# 1. El hash
if [ -f "$HASH" ]; then
    if ( cd "$(dirname "$ARCHIVO")" && sha256sum --quiet -c "$(basename "$HASH")" ); then
        echo "Hash SHA-256: coincide."
    else
        echo "Hash SHA-256: NO coincide. El archivo cambio: no usarlo."
        exit 1
    fi
else
    echo "Hash SHA-256: falta $HASH (no se comprueba)."
fi

# 2. La firma
if [ ! -f "$ARCHIVO.sig" ]; then
    echo "Firma: falta $ARCHIVO.sig. Un respaldo sin firma no se puede verificar."
    exit 1
fi
[ -f "$PUBLICA" ] || { echo "Falta la clave publica $PUBLICA."; exit 3; }
if openssl dgst -sha256 -verify "$PUBLICA" -signature "$ARCHIVO.sig" "$ARCHIVO" > /dev/null 2>&1; then
    echo "Firma digital: valida (lo hizo este servidor y no cambio)."
else
    echo "Firma digital: NO es valida. No usar este respaldo."
    exit 1
fi

# 3. Abrirlo, si se pidio
if [ -n "$ABRIR" ]; then
    [ -f "$FRASE" ] || { echo "Falta la frase $FRASE: sin ella no se descifra."; exit 3; }
    if [ -e "$ABRIR" ] && [ -n "$(ls -A "$ABRIR" 2>/dev/null)" ]; then
        echo "La carpeta $ABRIR no esta vacia: no se pisa nada."
        exit 2
    fi
    mkdir -p "$ABRIR" && chmod 700 "$ABRIR"
    if openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -in "$ARCHIVO" -pass "file:$FRASE" | tar -xzf - -C "$ABRIR"; then
        echo "Abierto en $ABRIR: $(ls "$ABRIR" | tr '\n' ' ')"
    else
        echo "No se pudo descifrar o desempaquetar."
        exit 1
    fi
fi
exit 0
