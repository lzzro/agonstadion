#!/bin/bash
# =====================================================================
# La migracion 008, en bases de prueba propias - Stadion (Agon)
# ---------------------------------------------------------------------
#   bash tests/sql/migracion_008.sh [socket...]
#
# Prueba sql/migraciones/008_modulos.sql (modulo_competencia.habilitado,
# RF-24) en cada servidor de MariaDB cuyo socket se le pase (sin
# argumentos, /run/mysqld/mysqld.sock). Crea sus propias bases, con el
# prefijo t008_ y un sufijo al azar, y las borra al terminar, tambien si
# algo falla (trap). Nunca toca sgdm. Corre la 008 sin el bloque del
# permiso de sgdm_app ("[solo servidor propio]").
#
# La base de antes de la 008 es el schema.sql del commit 5d8c612 (el
# ultimo antes de la fase 3) con la 005, la 006 y la 007.
#
# Casos:
#   1. Base de antes: la 008 da en su ultima consulta lo de su cabecera
#      (columna 1, 3 modulos habilitados, CHECK 38), y la base queda como
#      una nueva (SHOW CREATE TABLE).
#   2. La 008 otra vez: no cambia nada.
#   3. Base nueva (schema.sql de ahora, la 005, la 006 y la 007): lo
#      mismo, y la 008 no cambia nada. La base de antes con la 008 y las
#      que siguen (si hay) queda igual que la nueva.
#   4. Sin la 007: frena en la primera consulta, sin cambiar nada.
#   5. La restriccion: habilitado solo 0 o 1; un modulo deshabilitado
#      sigue en el catalogo.
#   6. El original trae el permiso dentro del bloque "[solo servidor
#      propio]", sobre una sola columna.
#
# Escribe una linea por comprobacion (ok o FALLA) y al final TODO BIEN o
# cuantas fallaron; sale con 0 o con 1, y con 2 si no puede ni empezar.
# =====================================================================

set -u
cd "$(dirname "$0")/../.." || exit 2

if [ "$#" -gt 0 ]; then SOCKETS=("$@"); else SOCKETS=(/run/mysqld/mysqld.sock); fi

COMMIT_VIEJO=5d8c612
M005=sql/migraciones/005_ligas.sql
M006=sql/migraciones/006_resultados.sql
M007=sql/migraciones/007_mediciones.sql
M008=sql/migraciones/008_modulos.sql

TMP=$(mktemp -d) || exit 2
SUFIJO=$(od -An -N3 -tx1 /dev/urandom | tr -d ' \n')
CREADAS=()
SOCKET=''
PREFIJO=''
BASE=''
CODIGO=0

limpiar() {
    local entrada
    for entrada in ${CREADAS[@]+"${CREADAS[@]}"}; do
        mysql --socket="${entrada%%|*}" -e "DROP DATABASE IF EXISTS \`${entrada#*|}\`" 2>/dev/null
    done
    CREADAS=()
}
trap 'limpiar; rm -rf "$TMP"' EXIT
trap 'exit 2' INT TERM HUP

BUENAS=0
MALAS=0
chk() {
    local descripcion=$1
    shift
    if "$@"; then
        BUENAS=$((BUENAS + 1)); echo "  ok    $PREFIJO$descripcion"; return 0
    fi
    MALAS=$((MALAS + 1)); echo "  FALLA $PREFIJO$descripcion"; return 1
}
detalle() { sed 's/^/        /' | head -n "${1:-12}"; }
iguales() { [ "$1" = "$2" ]; }

sql() { mysql --socket="$SOCKET" --default-character-set=utf8mb4 -N -B "$@"; }
crear_base() {
    BASE="t008_${SUFIJO}_$1"
    CREADAS+=("$SOCKET|$BASE")
    sql -e "CREATE DATABASE \`$BASE\`"
}
cargar() {  # base archivo
    mysql --socket="$SOCKET" --default-character-set=utf8mb4 "$1" < "$2" > "$TMP/carga" 2>&1 && return 0
    detalle 6 < "$TMP/carga"; return 1
}
correr() {  # base archivo
    mysql --socket="$SOCKET" --default-character-set=utf8mb4 -B "$1" < "$2" > "$TMP/salida" 2> "$TMP/error"
    CODIGO=$?
}
sin_bloques() {
    awk -v desde='-- [solo servidor propio] desde aca' -v hasta='-- [solo servidor propio] hasta aca' '
        { linea = $0; sub(/\r$/, "", linea) }
        linea == desde { dentro = 1; next }
        linea == hasta { dentro = 0; next }
        !dentro { print }'
}
# La ultima consulta de la salida, como "columna=valor columna=valor".
resultado() {
    tail -n 2 "$TMP/salida" | awk -F'\t' '
        NR == 1 { for (i = 1; i <= NF; i++) col[i] = $i; n = NF }
        NR == 2 { for (i = 1; i <= n; i++) printf "%s%s=%s", (i > 1 ? " " : ""), col[i], $i; print "" }'
}
tablas_de() {
    sql -e "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = '$1' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
}
siguientes() {  # las migraciones posteriores a la numero $1, en orden
    local m n
    for m in sql/migraciones/[0-9][0-9][0-9]_*.sql; do
        n=$(basename "$m" | cut -c1-3)
        if [ "$((10#$n))" -gt "$1" ]; then echo "$m"; fi
    done
}
checks_de() {  # base: cuantas CHECK tiene
    sql -e "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = '$1' AND CONSTRAINT_TYPE = 'CHECK'"
}
estructura() {  # base [tabla...]
    local base=$1 t
    shift
    for t in ${@:-$(tablas_de "$base")}; do sql -r "$base" -e "SHOW CREATE TABLE \`$t\`"; done | sed -E 's/ AUTO_INCREMENT=[0-9]+//'
}
foto() {  # base archivo
    estructura "$1" > "$2"
    mysqldump --socket="$SOCKET" --default-character-set=utf8mb4 --no-create-info --skip-extended-insert \
              --compact --order-by-primary --skip-triggers --no-tablespaces "$1" >> "$2"
}

ESPERADO='columna_habilitado=1 modulos_habilitados=3 restricciones_check=38'

git show "$COMMIT_VIEJO:sql/schema.sql" 2>/dev/null | sin_bloques > "$TMP/schema_viejo.sql"
sin_bloques < sql/schema.sql > "$TMP/schema_nuevo.sql"
sin_bloques < "$M005" > "$TMP/005.sql"
sin_bloques < "$M006" > "$TMP/006.sql"
sin_bloques < "$M007" > "$TMP/007.sql"
sin_bloques < "$M008" > "$TMP/008.sql"
if [ ! -s "$TMP/schema_viejo.sql" ] || grep -q 'habilitado' "$TMP/schema_viejo.sql"; then
    echo "No se puede sacar el esquema de antes de la 008 del commit $COMMIT_VIEJO (hace falta el historial de git)."
    exit 2
fi

for SOCKET in "${SOCKETS[@]}"; do
    VERSION=$(mysql --socket="$SOCKET" -N -B -e 'SELECT VERSION()' 2>/dev/null) || { echo "No se puede entrar por $SOCKET"; MALAS=$((MALAS + 1)); continue; }
    echo "===== Migracion 008 en MariaDB $VERSION ($SOCKET) ====="

    PREFIJO='1. '
    crear_base vieja
    chk 'se arma la base de antes (esquema de la fase 2, la 005, la 006 y la 007)' cargar "$BASE" "$TMP/schema_viejo.sql" \
        && cargar "$BASE" "$TMP/005.sql" && cargar "$BASE" "$TMP/006.sql" && cargar "$BASE" "$TMP/007.sql"
    VIEJA=$BASE
    correr "$VIEJA" "$TMP/008.sql"
    chk 'la 008 corre sin error' iguales "$CODIGO" 0 || detalle 4 < "$TMP/error"
    chk "la ultima consulta da lo de la cabecera ($ESPERADO)" iguales "$(resultado)" "$ESPERADO" || resultado | detalle

    PREFIJO='3. '
    crear_base nueva
    chk 'una base nueva (schema.sql de ahora) con la 005, la 006 y la 007' cargar "$BASE" "$TMP/schema_nuevo.sql" \
        && cargar "$BASE" "$TMP/005.sql" && cargar "$BASE" "$TMP/006.sql" && cargar "$BASE" "$TMP/007.sql"
    NUEVA=$BASE
    foto "$NUEVA" "$TMP/antes"
    correr "$NUEVA" "$TMP/008.sql"
    foto "$NUEVA" "$TMP/despues"
    ESPERADO_NUEVA="columna_habilitado=1 modulos_habilitados=3 restricciones_check=$(checks_de "$NUEVA")"
    chk "la 008 da lo mismo ($ESPERADO_NUEVA)" iguales "$(resultado)" "$ESPERADO_NUEVA" || resultado | detalle
    chk 'y en una base nueva no cambia nada' cmp -s "$TMP/antes" "$TMP/despues" || diff "$TMP/antes" "$TMP/despues" | detalle
    PREFIJO='1. '
    chk 'modulo_competencia queda como en la base nueva (SHOW CREATE TABLE)' \
        iguales "$(estructura "$VIEJA" modulo_competencia)" "$(estructura "$NUEVA" modulo_competencia)" \
        || diff <(estructura "$VIEJA" modulo_competencia) <(estructura "$NUEVA" modulo_competencia) | detalle
    # La base de antes con esta migracion y las que siguen queda igual
    # que una nueva (las que siguen pueden cambiar tablas que ya estaban).
    VUELTA=$BASE
    crear_base aldia
    cargar "$BASE" "$TMP/schema_viejo.sql" && cargar "$BASE" "$TMP/005.sql" && cargar "$BASE" "$TMP/006.sql" && cargar "$BASE" "$TMP/007.sql" && cargar "$BASE" "$TMP/008.sql"
    for M in $(siguientes 8); do sin_bloques < "$M" > "$TMP/siguiente.sql"; cargar "$BASE" "$TMP/siguiente.sql"; done
    chk 'y con las migraciones que siguen, la base entera igual que una nueva' iguales "$(estructura "$BASE")" "$(estructura "$NUEVA")" \
        || diff <(estructura "$BASE") <(estructura "$NUEVA") | detalle
    BASE=$VUELTA

    PREFIJO='2. '
    foto "$VIEJA" "$TMP/antes"
    correr "$VIEJA" "$TMP/008.sql"
    foto "$VIEJA" "$TMP/despues"
    chk 'la 008 otra vez corre sin error y da lo mismo' iguales "$CODIGO|$(resultado)" "0|$ESPERADO"
    chk 'y no cambia nada' cmp -s "$TMP/antes" "$TMP/despues" || diff "$TMP/antes" "$TMP/despues" | detalle

    PREFIJO='4. '
    crear_base sin007
    cargar "$BASE" "$TMP/schema_viejo.sql"
    cargar "$BASE" "$TMP/005.sql"
    cargar "$BASE" "$TMP/006.sql"
    foto "$BASE" "$TMP/antes"
    correr "$BASE" "$TMP/008.sql"
    foto "$BASE" "$TMP/despues"
    chk 'sin la 007 frena en la primera consulta' grep -q "medicion' doesn't exist" "$TMP/error"
    chk 'sin cambiar nada' cmp -s "$TMP/antes" "$TMP/despues"

    PREFIJO='5. '
    chk 'los tres modulos arrancan habilitados' iguales "$(sql "$NUEVA" -e 'SELECT GROUP_CONCAT(habilitado) FROM modulo_competencia')" '1,1,1'
    chk 'habilitado en 0, si' sql "$NUEVA" -e "UPDATE modulo_competencia SET habilitado = 0 WHERE nombre = 'Suizo'"
    chk 'y el modulo sigue en el catalogo' iguales "$(sql "$NUEVA" -e "SELECT COUNT(*) FROM modulo_competencia WHERE nombre = 'Suizo'")" 1
    otro_valor() { ! sql "$NUEVA" -e "UPDATE modulo_competencia SET habilitado = 2 WHERE nombre = 'Liga'" 2>/dev/null; }
    chk 'habilitado en 2, no' otro_valor
    sql "$NUEVA" -e "UPDATE modulo_competencia SET habilitado = 1"
    limpiar
done

PREFIJO='6. '
chk 'el original trae el GRANT de una sola columna, dentro del bloque' \
    bash -c "awk '/solo servidor propio\\] desde aca/,/solo servidor propio\\] hasta aca/' '$M008' | grep -q '^GRANT UPDATE (habilitado) ON sgdm.modulo_competencia'"

echo "$([ "$MALAS" -eq 0 ] && echo 'TODO BIEN' || echo "$MALAS FALLARON"): $BUENAS de $((BUENAS + MALAS)) comprobaciones"
[ "$MALAS" -eq 0 ]
