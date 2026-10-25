#!/bin/bash
# =====================================================================
# La migracion 006, en bases de prueba propias - Stadion (Agon)
# ---------------------------------------------------------------------
#   bash tests/sql/migracion_006.sh [socket...]
#
# Prueba sql/migraciones/006_resultados.sql en cada servidor de MariaDB
# cuyo socket se le pase (sin argumentos, el de la maquina de prueba:
# /run/mysqld/mysqld.sock). Crea sus propias bases, con el prefijo t006_
# y un sufijo al azar, y las borra al terminar, tambien si algo falla
# (trap). Nunca toca sgdm. Entra por el socket con la cuenta del sistema
# que la corre (root en la maquina de prueba), la que administra la base.
#
# Necesita el historial de git: el esquema de antes de la 006 sale del
# commit 5d8c612 (el ultimo antes de la fase 3), sin los bloques
# "[solo servidor propio]", con el mismo awk de scripts/armar-deploy.sh.
# La 005 y la 006 se corren sin esos bloques.
#
# Casos:
#   1. Base vieja (esquema de antes y la 005): la 006 da en su ultima
#      consulta los numeros de su cabecera (serie 3, empates 0, CHECK
#      35), y configuracion_torneo y auditoria quedan como en una base
#      nueva (SHOW CREATE TABLE).
#   2. La misma base, la 006 otra vez: no cambia nada.
#   3. Base nueva (schema.sql de ahora, la 005 y la 006): el mismo
#      resultado. El schema.sql de ahora ya trae lo de las migraciones
#      que siguen (la 007, la 008...), con sus CHECK: la base vieja con
#      la 006 y esas migraciones queda igual que la nueva.
#   4. Sin la 005: frena en la primera consulta, sin cambiar nada.
#   5. Una base donde la Valorant de muestra volvio a admitir empates:
#      la 006 no le pone la serie (serie vacia), y no frena.
#   6. Las restricciones nuevas: al mejor de 4 y una serie con empate
#      no entran; las acciones nuevas de la auditoria si, y una
#      inventada no.
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
    BASE="t006_${SUFIJO}_$1"
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

ESPERADO='serie_valorant=3 empates_en_series=0 restricciones_check=35'

git show "$COMMIT_VIEJO:sql/schema.sql" 2>/dev/null | sin_bloques > "$TMP/schema_viejo.sql"
sin_bloques < sql/schema.sql > "$TMP/schema_nuevo.sql"
sin_bloques < "$M005" > "$TMP/005.sql"
sin_bloques < "$M006" > "$TMP/006.sql"
if [ ! -s "$TMP/schema_viejo.sql" ] || grep -q mejor_de "$TMP/schema_viejo.sql"; then
    echo "No se puede sacar el esquema de antes de la 006 del commit $COMMIT_VIEJO (hace falta el historial de git)."
    exit 2
fi

for SOCKET in "${SOCKETS[@]}"; do
    VERSION=$(mysql --socket="$SOCKET" -N -B -e 'SELECT VERSION()' 2>/dev/null) || { echo "No se puede entrar por $SOCKET"; MALAS=$((MALAS + 1)); continue; }
    echo "===== Migracion 006 en MariaDB $VERSION ($SOCKET) ====="

    PREFIJO='1. '
    crear_base vieja
    chk 'se arma la base vieja (esquema de antes y la 005)' cargar "$BASE" "$TMP/schema_viejo.sql" \
        && chk 'con la 005' cargar "$BASE" "$TMP/005.sql"
    VIEJA=$BASE
    correr "$VIEJA" "$TMP/006.sql"
    chk 'la 006 corre sin error' iguales "$CODIGO" 0 || detalle 4 < "$TMP/error"
    chk "la ultima consulta da lo de la cabecera ($ESPERADO)" iguales "$(resultado)" "$ESPERADO" || resultado | detalle

    PREFIJO='3. '
    crear_base nueva
    chk 'una base nueva (schema.sql de ahora) con la 005' cargar "$BASE" "$TMP/schema_nuevo.sql"
    cargar "$BASE" "$TMP/005.sql"
    NUEVA=$BASE
    correr "$NUEVA" "$TMP/006.sql"
    # La base nueva ya trae las CHECK de las migraciones que siguen.
    ESPERADO_NUEVA="serie_valorant=3 empates_en_series=0 restricciones_check=$(checks_de "$NUEVA")"
    chk "la 006 da lo mismo ($ESPERADO_NUEVA: las CHECK de la base nueva incluyen las de las que siguen)" \
        iguales "$(resultado)" "$ESPERADO_NUEVA" || resultado | detalle
    PREFIJO='1. '
    chk 'configuracion_torneo y auditoria quedan como en la base nueva (SHOW CREATE TABLE)' \
        iguales "$(estructura "$VIEJA" configuracion_torneo auditoria)" "$(estructura "$NUEVA" configuracion_torneo auditoria)" \
        || diff <(estructura "$VIEJA" configuracion_torneo auditoria) <(estructura "$NUEVA" configuracion_torneo auditoria) | detalle
    # La base de antes con esta migracion y las que siguen queda igual
    # que una nueva (las que siguen pueden cambiar tablas que ya estaban).
    VUELTA=$BASE
    crear_base aldia
    cargar "$BASE" "$TMP/schema_viejo.sql" && cargar "$BASE" "$TMP/005.sql" && cargar "$BASE" "$TMP/006.sql"
    for M in $(siguientes 6); do sin_bloques < "$M" > "$TMP/siguiente.sql"; cargar "$BASE" "$TMP/siguiente.sql"; done
    chk 'y con las migraciones que siguen, la base entera igual que una nueva' iguales "$(estructura "$BASE")" "$(estructura "$NUEVA")" \
        || diff <(estructura "$BASE") <(estructura "$NUEVA") | detalle
    BASE=$VUELTA

    PREFIJO='2. '
    foto "$VIEJA" "$TMP/antes"
    correr "$VIEJA" "$TMP/006.sql"
    foto "$VIEJA" "$TMP/despues"
    chk 'la 006 otra vez corre sin error y da lo mismo' iguales "$CODIGO|$(resultado)" "0|$ESPERADO"
    chk 'y no cambia nada (estructura y datos)' cmp -s "$TMP/antes" "$TMP/despues" || diff "$TMP/antes" "$TMP/despues" | detalle

    PREFIJO='4. '
    crear_base sin005
    cargar "$BASE" "$TMP/schema_viejo.sql"
    sql "$BASE" -e 'DROP TABLE pedido_inscripcion' 2>/dev/null
    foto "$BASE" "$TMP/antes"
    correr "$BASE" "$TMP/006.sql"
    foto "$BASE" "$TMP/despues"
    chk 'sin la 005 frena en la primera consulta' grep -q "pedido_inscripcion' doesn't exist" "$TMP/error"
    chk 'sin cambiar nada' cmp -s "$TMP/antes" "$TMP/despues"

    PREFIJO='5. '
    crear_base empates
    cargar "$BASE" "$TMP/schema_viejo.sql"
    cargar "$BASE" "$TMP/005.sql"
    sql "$BASE" -e "UPDATE configuracion_torneo c JOIN torneo t USING (id_torneo) SET c.admite_empate = 1
                    WHERE t.nombre = 'Liga Valorant · Otoño'"
    correr "$BASE" "$TMP/006.sql"
    chk 'con la Valorant admitiendo empates, la 006 no le pone la serie' \
        iguales "$CODIGO|$(resultado)" "0|serie_valorant=NULL empates_en_series=0 restricciones_check=35"

    PREFIJO='6. '
    sql "$NUEVA" -e "UPDATE configuracion_torneo SET mejor_de = 4 WHERE mejor_de = 3" 2> "$TMP/error"
    chk 'al mejor de 4 no entra (ck_config_mejor_de)' grep -q ck_config_mejor_de "$TMP/error"
    sql "$NUEVA" -e "UPDATE configuracion_torneo SET admite_empate = 1 WHERE mejor_de = 3" 2> "$TMP/error"
    chk 'una serie con empate no entra (ck_config_serie_empate)' grep -q ck_config_serie_empate "$TMP/error"
    chk 'las cinco acciones nuevas de la auditoria entran' \
        sql "$NUEVA" -e "INSERT INTO auditoria (tabla_afectada, accion) VALUES ('enfrentamiento', 'resultado'),
                         ('enfrentamiento', 'programacion'), ('torneo', 'final'), ('torneo', 'cancelacion'), ('medicion', 'medicion')"
    sql "$NUEVA" -e "INSERT INTO auditoria (tabla_afectada, accion) VALUES ('torneo', 'borrado')" 2> "$TMP/error"
    chk 'una inventada, no (ck_audit_accion)' grep -q ck_audit_accion "$TMP/error"
    limpiar
done

PREFIJO='7. '

echo "$([ "$MALAS" -eq 0 ] && echo 'TODO BIEN' || echo "$MALAS FALLARON"): $BUENAS de $((BUENAS + MALAS)) comprobaciones"
[ "$MALAS" -eq 0 ]
