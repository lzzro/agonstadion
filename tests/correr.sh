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
# direccion que no sea la propia maquina. Las que limpian borran solo
# lo que ellas mismas crean (ver tests/README.md).
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

# Con la base de prueba (ya migrada hasta la 008). resultados.php,
# rendimiento.php y administracion.php borran al final lo que crean.
correr php tests/php/muestra.php
correr php tests/php/resultados.php
correr php tests/php/formatos.php
correr php tests/php/rendimiento.php
correr php tests/php/administracion.php

# Con el sitio andando. Primero las que limpian lo suyo y comparan la
# base antes y despues; despues las que dejan rastro. Nunca dos a la vez.
correr node tests/e2e/permisos.js
correr node tests/e2e/sesion.js
correr node tests/e2e/subidas.js
correr node tests/e2e/recorrido.js
correr node tests/e2e/rendimiento.js
correr node tests/e2e/administracion.js
correr node tests/e2e/cabeceras.js
correr node tests/e2e/contrasenas.js
correr node tests/e2e/ligas.js
correr node tests/e2e/resultados.js
correr node tests/e2e/individuales.js
correr node tests/e2e/formatos.js
correr node tests/e2e/reportes.js
correr node tests/e2e/publicas.js
correr node tests/e2e/accesibilidad.js

# Las migraciones, en bases propias que crean y borran
# (STADION_SOCKETS: los servidores de MariaDB donde probarlas, separados
# por espacios)
if [ -n "${STADION_SOCKETS:-}" ]; then
    # shellcheck disable=SC2086
    correr bash tests/sql/migracion_005.sh $STADION_SOCKETS
    # shellcheck disable=SC2086
    correr bash tests/sql/migracion_006.sh $STADION_SOCKETS
    # shellcheck disable=SC2086
    correr bash tests/sql/migracion_007.sh $STADION_SOCKETS
    # shellcheck disable=SC2086
    correr bash tests/sql/migracion_008.sh $STADION_SOCKETS
else
    correr bash tests/sql/migracion_005.sh
    correr bash tests/sql/migracion_006.sh
    correr bash tests/sql/migracion_007.sh
    correr bash tests/sql/migracion_008.sh
fi

echo
if [ "$FALLAS" -eq 0 ]; then
    echo "TODAS LAS BATERIAS BIEN"
    exit 0
fi
echo "$FALLAS BATERIAS CON FALLAS"
exit 1
