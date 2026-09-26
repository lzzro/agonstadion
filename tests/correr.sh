#!/bin/bash
# =====================================================================
# Corre todas las baterias contra una instalacion de PRUEBA
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
#   bash tests/correr.sh
#
# Lo que necesita, por variables de entorno, esta en tests/README.md.
# Nunca contra un servidor real: las pruebas crean cuentas, ligas y
# equipos, y dan roles por SQL. Las de navegador no arrancan contra una
# direccion que no sea la propia maquina.
#
# Sale con 0 si todas las baterias dieron bien, y con 1 si alguna fallo.
# =====================================================================

set -u
cd "$(dirname "$0")/.." || exit 2

FALLAS=0
correr() {
    echo
    echo ">>> $*"
    "$@" || FALLAS=$((FALLAS + 1))
}

# Sin base
correr php tests/php/fixture_propiedades.php
correr php tests/php/modelo_ligas.php

# Con la base de prueba (ya migrada con la 005)
correr php tests/php/muestra.php

# Con el sitio andando
correr node tests/e2e/ligas.js
correr node tests/e2e/publicas.js
correr node tests/e2e/accesibilidad.js

# La migracion, en bases propias que crea y borra (STADION_SOCKETS: los
# servidores de MariaDB donde probarla, separados por espacios)
if [ -n "${STADION_SOCKETS:-}" ]; then
    # shellcheck disable=SC2086
    correr bash tests/sql/migracion_005.sh $STADION_SOCKETS
else
    correr bash tests/sql/migracion_005.sh
fi

echo
if [ "$FALLAS" -eq 0 ]; then
    echo "TODAS LAS BATERIAS BIEN"
    exit 0
fi
echo "$FALLAS BATERIAS CON FALLAS"
exit 1
