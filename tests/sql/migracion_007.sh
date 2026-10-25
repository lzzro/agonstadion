#!/bin/bash
# =====================================================================
# La migracion 007, en bases de prueba propias - Stadion (Agon)
# ---------------------------------------------------------------------
#   bash tests/sql/migracion_007.sh [socket...]
#
# Prueba sql/migraciones/007_mediciones.sql (la tabla medicion, RF-29)
# en cada servidor de MariaDB cuyo socket se le pase (sin argumentos,
# /run/mysqld/mysqld.sock). Crea sus propias bases, con el prefijo t007_
# y un sufijo al azar, y las borra al terminar, tambien si algo falla
# (trap). Nunca toca sgdm. Corre la 007 sin el bloque del permiso de
# sgdm_app ("[solo servidor propio]").
#
# La base de antes de la 007 es el schema.sql del commit 5d8c612 (el
# ultimo antes de la fase 3) con la 005 y la 006.
#
# Casos:
#   1. Base de antes: la 007 da en su ultima consulta lo de su cabecera
#      (tabla 1, CHECK 37), y la tabla medicion queda como en una base
#      nueva (SHOW CREATE TABLE).
#   2. La 007 otra vez: no cambia nada.
#   3. Base nueva (schema.sql de ahora, la 005 y la 006): lo mismo (con
#      las CHECK que suman las migraciones que siguen), y la 007 no
#      cambia nada. La base de antes con la 007 y las que siguen queda
#      igual que la nueva.
#   4. Sin la 006: frena en la primera consulta, sin cambiar nada.
#   5. Las restricciones: cada prueba con sus datos y en rango, y sin
#      ninguno de los que pide en NULL (un BETWEEN sobre NULL no frena una
#      CHECK); una medicion bien cargada entra; y al borrar la cuenta se
#      van sus mediciones.
#   6. El original trae el permiso dentro del bloque "[solo servidor
#      propio]".
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
    BASE="t007_${SUFIJO}_$1"
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

ESPERADO='tabla_medicion=1 restricciones_check=37'

git show "$COMMIT_VIEJO:sql/schema.sql" 2>/dev/null | sin_bloques > "$TMP/schema_viejo.sql"
sin_bloques < sql/schema.sql > "$TMP/schema_nuevo.sql"
sin_bloques < "$M005" > "$TMP/005.sql"
sin_bloques < "$M006" > "$TMP/006.sql"
sin_bloques < "$M007" > "$TMP/007.sql"
if [ ! -s "$TMP/schema_viejo.sql" ] || grep -q 'CREATE TABLE medicion' "$TMP/schema_viejo.sql"; then
    echo "No se puede sacar el esquema de antes de la 007 del commit $COMMIT_VIEJO (hace falta el historial de git)."
    exit 2
fi

for SOCKET in "${SOCKETS[@]}"; do
    VERSION=$(mysql --socket="$SOCKET" -N -B -e 'SELECT VERSION()' 2>/dev/null) || { echo "No se puede entrar por $SOCKET"; MALAS=$((MALAS + 1)); continue; }
    echo "===== Migracion 007 en MariaDB $VERSION ($SOCKET) ====="

    PREFIJO='1. '
    crear_base vieja
    chk 'se arma la base de antes (esquema de la fase 2, la 005 y la 006)' cargar "$BASE" "$TMP/schema_viejo.sql" \
        && cargar "$BASE" "$TMP/005.sql" && cargar "$BASE" "$TMP/006.sql"
    VIEJA=$BASE
    correr "$VIEJA" "$TMP/007.sql"
    chk 'la 007 corre sin error' iguales "$CODIGO" 0 || detalle 4 < "$TMP/error"
    chk "la ultima consulta da lo de la cabecera ($ESPERADO)" iguales "$(resultado)" "$ESPERADO" || resultado | detalle

    PREFIJO='3. '
    crear_base nueva
    chk 'una base nueva (schema.sql de ahora) con la 005 y la 006' cargar "$BASE" "$TMP/schema_nuevo.sql" \
        && cargar "$BASE" "$TMP/005.sql" && cargar "$BASE" "$TMP/006.sql"
    NUEVA=$BASE
    foto "$NUEVA" "$TMP/antes"
    correr "$NUEVA" "$TMP/007.sql"
    foto "$NUEVA" "$TMP/despues"
    ESPERADO_NUEVA="tabla_medicion=1 restricciones_check=$(checks_de "$NUEVA")"
    chk "la 007 da lo mismo ($ESPERADO_NUEVA: las CHECK de la base nueva incluyen las de las que siguen)" iguales "$(resultado)" "$ESPERADO_NUEVA" || resultado | detalle
    chk 'y en una base nueva no cambia nada' cmp -s "$TMP/antes" "$TMP/despues" || diff "$TMP/antes" "$TMP/despues" | detalle
    PREFIJO='1. '
    chk 'la tabla medicion queda como en la base nueva (SHOW CREATE TABLE)' \
        iguales "$(estructura "$VIEJA" medicion)" "$(estructura "$NUEVA" medicion)" \
        || diff <(estructura "$VIEJA" medicion) <(estructura "$NUEVA" medicion) | detalle
    # La base de antes con esta migracion y las que siguen queda igual
    # que una nueva (las que siguen pueden cambiar tablas que ya estaban).
    VUELTA=$BASE
    crear_base aldia
    cargar "$BASE" "$TMP/schema_viejo.sql" && cargar "$BASE" "$TMP/005.sql" && cargar "$BASE" "$TMP/006.sql" && cargar "$BASE" "$TMP/007.sql"
    for M in $(siguientes 7); do sin_bloques < "$M" > "$TMP/siguiente.sql"; cargar "$BASE" "$TMP/siguiente.sql"; done
    chk 'y con las migraciones que siguen, la base entera igual que una nueva' iguales "$(estructura "$BASE")" "$(estructura "$NUEVA")" \
        || diff <(estructura "$BASE") <(estructura "$NUEVA") | detalle
    BASE=$VUELTA

    PREFIJO='2. '
    foto "$VIEJA" "$TMP/antes"
    correr "$VIEJA" "$TMP/007.sql"
    foto "$VIEJA" "$TMP/despues"
    chk 'la 007 otra vez corre sin error y da lo mismo' iguales "$CODIGO|$(resultado)" "0|$ESPERADO"
    chk 'y no cambia nada' cmp -s "$TMP/antes" "$TMP/despues" || diff "$TMP/antes" "$TMP/despues" | detalle

    PREFIJO='4. '
    crear_base sin006
    cargar "$BASE" "$TMP/schema_viejo.sql"
    cargar "$BASE" "$TMP/005.sql"
    foto "$BASE" "$TMP/antes"
    correr "$BASE" "$TMP/007.sql"
    foto "$BASE" "$TMP/despues"
    chk 'sin la 006 frena en la primera consulta' grep -q "Unknown column 'mejor_de'" "$TMP/error"
    chk 'sin cambiar nada' cmp -s "$TMP/antes" "$TMP/despues"

    PREFIJO='5. '
    U=$(sql "$NUEVA" -e "INSERT INTO usuario (correo, hash_password, nombre, apellido)
                         VALUES ('prueba-007@ejemplo.invalid', 'x', 'Prueba', 'Medicion'); SELECT LAST_INSERT_ID();")
    no_entra() { ! sql "$NUEVA" -e "INSERT INTO medicion (id_usuario, prueba, distancia_cm, tiempo_s, masa_kg, fecha) VALUES ($U, $1, '2026-10-10')" 2>/dev/null; }
    entra()    {   sql "$NUEVA" -e "INSERT INTO medicion (id_usuario, prueba, distancia_cm, tiempo_s, masa_kg, fecha) VALUES ($U, $1, '2026-10-10')" 2>/dev/null; }
    chk 'una reaccion con su distancia entra'            entra    "'reaccion', 15, NULL, NULL"
    chk 'un sprint con tiempo y masa entra'              entra    "'sprint', NULL, 2.30, 68"
    chk 'un salto con tiempo de vuelo y masa entra'      entra    "'salto', NULL, 0.45, 60"
    chk 'una reaccion con tiempo, no'                    no_entra "'reaccion', 15, 2.0, NULL"
    chk 'una regla de mas de 30 cm, no'                  no_entra "'reaccion', 31, NULL, NULL"
    chk 'un sprint sin masa, no'                         no_entra "'sprint', NULL, 2.30, NULL"
    chk 'un sprint sin tiempo, no'                       no_entra "'sprint', NULL, NULL, 68"
    chk 'una reaccion sin distancia, no'                 no_entra "'reaccion', NULL, NULL, NULL"
    chk 'un salto sin tiempo de vuelo, no'               no_entra "'salto', NULL, NULL, 60"
    chk 'un salto con distancia, no'                     no_entra "'salto', 10, 0.45, 60"
    chk 'una masa de 10 kg, no'                          no_entra "'salto', NULL, 0.45, 10"
    chk 'un vuelo de 2 s, no'                            no_entra "'salto', NULL, 2.00, 60"
    chk 'una prueba inventada, no'                       no_entra "'natacion', NULL, 30, 60"
    sql "$NUEVA" -e "DELETE FROM usuario WHERE id_usuario = $U"
    chk 'al borrar la cuenta se van sus mediciones' iguales "$(sql "$NUEVA" -e 'SELECT COUNT(*) FROM medicion')" 0
    limpiar
done

PREFIJO='6. '
chk 'el original trae el GRANT, dentro del bloque' \
    bash -c "awk '/solo servidor propio\] desde aca/,/solo servidor propio\] hasta aca/' '$M007' | grep -q '^GRANT INSERT, DELETE ON sgdm.medicion'"

echo "$([ "$MALAS" -eq 0 ] && echo 'TODO BIEN' || echo "$MALAS FALLARON"): $BUENAS de $((BUENAS + MALAS)) comprobaciones"
[ "$MALAS" -eq 0 ]
