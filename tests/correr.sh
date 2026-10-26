#!/bin/bash
# Corre todas las baterias contra una instalacion de PRUEBA

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

correr php tests/php/muestra.php
correr php tests/php/resultados.php
correr php tests/php/formatos.php
correr php tests/php/rendimiento.php
correr php tests/php/administracion.php

# Con el sitio andando.
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

if [ -n "${STADION_SOCKETS:-}" ]; then
    correr bash tests/sql/migracion_005.sh $STADION_SOCKETS
    correr bash tests/sql/migracion_006.sh $STADION_SOCKETS
    correr bash tests/sql/migracion_007.sh $STADION_SOCKETS
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
