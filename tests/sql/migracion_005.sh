#!/bin/bash
# =====================================================================
# La migracion 005, en bases de prueba propias - Stadion (Agon)
# ---------------------------------------------------------------------
#   bash tests/sql/migracion_005.sh [socket...]
#
# Prueba sql/migraciones/005_ligas.sql en cada servidor de MariaDB cuyo
# socket se le pase. Sin argumentos, en los dos de la maquina de prueba:
# /run/mysqld/mysqld.sock (la 10.11, la de la VM) y
# /srv/m114/run/mysqld.sock (la 11.4, la del hosting, que arranca en
# latin1 como el de cPanel).
#
# En cada servidor crea sus propias bases, todas con el prefijo t005_ y
# un sufijo al azar, y las borra al terminar, tambien si algo falla en
# el medio (trap). Nunca toca sgdm. Entra por el socket con la cuenta
# del sistema que la corre (root, sin contrasena, en la maquina de
# prueba), que es la que administra la base: sgdm_app no alcanza para
# cambiar la estructura.
#
# Necesita el historial de git: el esquema de antes de la 005 sale del
# commit 469a990 (el ultimo antes de la fase 2), sin los bloques
# "[solo servidor propio]", con el mismo awk de scripts/armar-deploy.sh.
# La 005 se corre como en el hosting: tambien sin esos bloques.
#
# Casos:
#   1. Base vieja (antes de la 005): la ultima consulta da exactamente
#      los numeros de la cabecera de la 005.
#   2. La misma base, la 005 otra vez: frena con ck_005_ya_esta_aplicada
#      y no cambia nada; la consulta del final, sola, da lo mismo.
#   3. Base nueva (el schema.sql de ahora) con la 005: el mismo resultado,
#      los mismos datos y SHOW CREATE TABLE de las 18 tablas identico al
#      de la base vieja migrada (sin AUTO_INCREMENT=N); ahi la parte de
#      estructura de la 005 no cambia nada.
#   4. Sin la 003: frena en la primera consulta ("pedido_rol doesn't
#      exist"), sin cambiar nada.
#   5. Sin la 004 (una tabla en latin1): frena con ck_005_falta_la_004,
#      sin cambiar nada.
#   6. Con un nombre de muestra ocupado (Vortex; Atlantida GG sin tilde y
#      en mayusculas; un correo de muestra), o con dos ligas vigentes con
#      el mismo nombre (que el UNIQUE nuevo no admitiria): frena con
#      ck_005_nombres_libres, sin cambiar nada, y la consulta 2c dice
#      cual esta ocupado. Una vigente y otra finalizada con el mismo
#      nombre no frenan.
#   7. Una falla a mitad de la carga (una restriccion de prueba en
#      tabla_posiciones): no queda ningun dato de muestra, la estructura
#      si; sin la restriccion, la 005 otra vez carga todo.
#   8. La copia del hosting no trae el GRANT y es el original sin el
#      bloque; el original lo trae, dentro del bloque. Corrido con root
#      donde existe sgdm_app, el original tambien anda y da el permiso
#      (que la prueba saca enseguida, y otra vez al terminar).
#
# "Sin cambiar nada" es: antes y despues, la misma estructura (SHOW
# CREATE TABLE de cada tabla, con su AUTO_INCREMENT), las mismas filas
# en cada tabla y los mismos datos.
#
# Escribe una linea por comprobacion (ok o FALLA) y al final TODO BIEN o
# cuantas fallaron; sale con 0 o con 1, y con 2 si no puede ni empezar.
# =====================================================================

set -u
cd "$(dirname "$0")/../.." || exit 2

if [ "$#" -gt 0 ]; then
    SOCKETS=("$@")
else
    SOCKETS=(/run/mysqld/mysqld.sock /srv/m114/run/mysqld.sock)
fi

COMMIT_VIEJO=469a990
MIGRACION=sql/migraciones/005_ligas.sql
COPIA_HOSTING=deploy/hosting-compartido/sql/migraciones/005_ligas.sql

TMP=$(mktemp -d) || exit 2
SUFIJO=$(od -An -N3 -tx1 /dev/urandom | tr -d ' \n')
CREADAS=()     # "socket|base" de cada base que crea la prueba
SOCKET=''
PREFIJO=''
BASE=''
CODIGO=0

# ---------------------------------------------------------------------
# Limpieza: los permisos que pudo dejar el GRANT del original y las
# bases de prueba. Corre al terminar, pase lo que pase.
# ---------------------------------------------------------------------
limpiar() {
    local entrada sock base
    for entrada in ${CREADAS[@]+"${CREADAS[@]}"}; do
        sock=${entrada%%|*}
        base=${entrada#*|}
        mysql --socket="$sock" -N -B -e "
            SELECT CONCAT('REVOKE ALL PRIVILEGES ON \`', Db, '\`.\`', Table_name, '\` FROM \`', User, '\`@\`', Host, '\`;')
              FROM mysql.tables_priv WHERE Db = '$base'" 2>/dev/null \
            | mysql --socket="$sock" 2>/dev/null
        mysql --socket="$sock" -e "DROP DATABASE IF EXISTS \`$base\`" 2>/dev/null
    done
    CREADAS=()
}
trap 'limpiar; rm -rf "$TMP"' EXIT
trap 'exit 2' INT TERM HUP

# ---------------------------------------------------------------------
# Comprobaciones
# ---------------------------------------------------------------------
BUENAS=0
MALAS=0
FALLADAS=()

# chk "descripcion" comando...   (ok si el comando sale con 0)
chk() {
    local descripcion=$1
    shift
    if "$@"; then
        BUENAS=$((BUENAS + 1))
        echo "  ok    $PREFIJO$descripcion"
        return 0
    fi
    MALAS=$((MALAS + 1))
    FALLADAS+=("$PREFIJO$descripcion")
    echo "  FALLA $PREFIJO$descripcion"
    return 1
}

# Lo que sigue a una FALLA, sangrado debajo.
detalle() { sed 's/^/        /' | head -n "${1:-12}"; }

iguales() { [ "$1" = "$2" ]; }
no() { ! "$@"; }

# ---------------------------------------------------------------------
# La base
# ---------------------------------------------------------------------
sql() { mysql --socket="$SOCKET" --default-character-set=utf8mb4 -N -B "$@"; }

crear_base() {
    BASE="t005_${SUFIJO}_$1"
    CREADAS+=("$SOCKET|$BASE")
    sql -e "CREATE DATABASE \`$BASE\`"
}

# Carga un archivo .sql (o lo que llegue por la entrada) en una base; si
# falla, muestra el error.
cargar() {
    if mysql --socket="$SOCKET" --default-character-set=utf8mb4 "$1" < "${2:-/dev/stdin}" > "$TMP/carga" 2>&1; then
        return 0
    fi
    detalle 6 < "$TMP/carga"
    return 1
}

# Una base con el esquema de antes de la 005, y lo que se le agregue por
# la entrada. Es una comprobacion: si no se arma, el caso no sigue.
base_vieja() {  # nombre caso descripcion  < sql de mas
    crear_base "$1" || return 1
    { cat "$TMP/schema_viejo.sql"; cat; } > "$TMP/vieja.sql"
    chk "$2: se arma la base vieja$3" cargar "$BASE" "$TMP/vieja.sql"
}

# Corre un archivo como lo hace el cliente con un archivo: se detiene en
# el primer error y, al cerrar la conexion, se deshace la transaccion
# que haya quedado abierta. Deja la salida y el error en $TMP, y el
# codigo de salida en CODIGO.
correr() {
    mysql --socket="$SOCKET" --default-character-set=utf8mb4 -B "$1" < "$2" > "$TMP/salida" 2> "$TMP/error"
    CODIGO=$?
}

corrio_bien() { [ "$CODIGO" -eq 0 ]; }

# Frena con un error que dice el texto dado.
frena_con() { [ "$CODIGO" -ne 0 ] && grep -qF -- "$1" "$TMP/error"; }

# El renglon ERROR del cliente (antes de ese renglon, el cliente repite
# la sentencia que fallo).
mostrar_error() {
    if grep -q '^ERROR' "$TMP/error"; then grep '^ERROR' "$TMP/error" | detalle 4
    else grep -v '^-*$' "$TMP/error" | detalle 4; fi
}

# La ultima consulta de la salida, como "columna<TAB>valor".
resultado() {
    tail -n 2 "$TMP/salida" | awk -F'\t' '
        NR == 1 { for (i = 1; i <= NF; i++) col[i] = $i; n = NF }
        NR == 2 { for (i = 1; i <= n; i++) print col[i] "\t" $i }'
}

# Lo que dice la cabecera de la 005.
ESPERADO=$(printf '%s\t%s\n' \
    cuentas_de_muestra  3 \
    claves_usables      0 \
    ligas_de_muestra    3 \
    estados             'en_curso / inscripcion / en_curso' \
    equipos             31 \
    partidos            111 \
    jugados             57 \
    en_vivo             1 \
    filas_tabla         22 \
    puntero             'Titanes CS · 18 pts · +9' \
    octavo              'Liceo 3 · 9 pts · -3' \
    tablas              18 \
    restricciones_check 33 \
    claves_foraneas     29 \
    indices_unicos      16)

resultado_esperado() {
    local obtenido
    obtenido=$(resultado)
    chk "$1" iguales "$obtenido" "$ESPERADO" && return 0
    awk -F'\t' '
        NR == FNR { esp[$1] = $2; orden[++n] = $1; next }
        { obt[$1] = $2 }
        END {
            for (i = 1; i <= n; i++) {
                k = orden[i]
                if (!(k in obt)) print k ": no esta en la salida"
                else if (obt[k] != esp[k]) print k ": da \"" obt[k] "\", se espera \"" esp[k] "\""
            }
        }' <(printf '%s\n' "$ESPERADO") <(printf '%s\n' "$obtenido") | detalle 16
    [ -s "$TMP/error" ] && mostrar_error
    return 1
}

tablas_de() {
    sql -e "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = '$1' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
}

# SHOW CREATE TABLE de cada tabla; con "sin_autoinc", sin AUTO_INCREMENT=N.
estructura() {
    local t
    for t in $(tablas_de "$1"); do
        sql -r "$1" -e "SHOW CREATE TABLE \`$t\`"
    done | if [ "${2:-}" = sin_autoinc ]; then sed -E 's/ AUTO_INCREMENT=[0-9]+//'; else cat; fi
}

conteos() {
    local t
    for t in $(tablas_de "$1"); do
        printf '%s\t%s\n' "$t" "$(sql "$1" -e "SELECT COUNT(*) FROM \`$t\`")"
    done
}

# Los datos de todas las tablas, fila por fila, por clave primaria. Con
# "sin_fecha", la auditoria va sin su fecha (la pone el servidor al
# insertar, y cambia de una base a otra).
datos() {
    local ignorar=()
    [ "${2:-}" = sin_fecha ] && ignorar=(--ignore-table="$1.auditoria")
    mysqldump --socket="$SOCKET" --default-character-set=utf8mb4 --no-create-info --skip-extended-insert \
              --compact --order-by-primary --skip-triggers --no-tablespaces ${ignorar[@]+"${ignorar[@]}"} "$1"
    if [ "${2:-}" = sin_fecha ]; then
        sql "$1" -e "SELECT id_auditoria, id_usuario, tabla_afectada, id_registro, accion, detalle, direccion_ip
                       FROM auditoria ORDER BY id_auditoria"
    fi
}

# Foto de una base, para ver que no cambie nada: estructura, filas y datos.
foto() {  # base archivo
    estructura "$1" > "$2.estructura"
    { conteos "$1"; datos "$1"; } > "$2.filas"
}
antes()   { foto "$1" "$TMP/antes"; }
despues() { foto "$1" "$TMP/despues"; }

sin_cambios() {  # caso
    chk "$1: no cambia nada: la misma estructura (SHOW CREATE TABLE de las $(grep -c 'CREATE TABLE `' "$TMP/antes.estructura") tablas)" \
        cmp -s "$TMP/antes.estructura" "$TMP/despues.estructura" \
        || diff "$TMP/antes.estructura" "$TMP/despues.estructura" | detalle
    chk "$1: no cambia nada: las mismas filas en cada tabla, con los mismos datos" \
        cmp -s "$TMP/antes.filas" "$TMP/despues.filas" \
        || diff "$TMP/antes.filas" "$TMP/despues.filas" | detalle
}

# Cuantas filas hay en cada tabla de datos, en un renglon.
filas_de_datos() {
    sql "$1" -e "SELECT CONCAT_WS(' ',
        CONCAT('cuentas_de_muestra=', (SELECT COUNT(*) FROM usuario WHERE de_muestra = 1)),
        CONCAT('usuarios=',       (SELECT COUNT(*) FROM usuario)),
        CONCAT('roles_dados=',    (SELECT COUNT(*) FROM usuario_rol)),
        CONCAT('torneos=',        (SELECT COUNT(*) FROM torneo)),
        CONCAT('configuracion=',  (SELECT COUNT(*) FROM configuracion_torneo)),
        CONCAT('equipos=',        (SELECT COUNT(*) FROM equipo)),
        CONCAT('participantes=',  (SELECT COUNT(*) FROM participante)),
        CONCAT('rondas=',         (SELECT COUNT(*) FROM ronda)),
        CONCAT('partidos=',       (SELECT COUNT(*) FROM enfrentamiento)),
        CONCAT('resultados=',     (SELECT COUNT(*) FROM resultado)),
        CONCAT('tabla=',          (SELECT COUNT(*) FROM tabla_posiciones)),
        CONCAT('auditoria=',      (SELECT COUNT(*) FROM auditoria)))" 2>&1
}
SIN_DATOS='cuentas_de_muestra=0 usuarios=0 roles_dados=0 torneos=0 configuracion=0 equipos=0 participantes=0 rondas=0 partidos=0 resultados=0 tabla=0 auditoria=0'

existe_columna() {  # base tabla columna
    [ "$(sql -e "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = '$1' AND TABLE_NAME = '$2' AND COLUMN_NAME = '$3'")" = 1 ]
}
existe_tabla() {  # base tabla
    [ "$(sql -e "SELECT COUNT(*) FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = '$1' AND TABLE_NAME = '$2'")" = 1 ]
}
sin_estructura_005() {
    ! existe_columna "$1" usuario de_muestra && ! existe_tabla "$1" pedido_inscripcion
}
con_estructura_005() {
    existe_columna "$1" usuario de_muestra && existe_columna "$1" configuracion_torneo criterio_desempate \
        && existe_tabla "$1" pedido_inscripcion
}

# El permiso que da el GRANT del original, tal como lo guarda el servidor.
permisos_de_la_base() {
    sql -e "SELECT CONCAT(User, '@', Host, ' ', Table_name, ' ', Table_priv)
              FROM mysql.tables_priv WHERE Db = '$1' ORDER BY User, Table_name"
}

# ---------------------------------------------------------------------
# Los archivos: el esquema de antes, el de ahora y la 005, cada uno sin
# los bloques "[solo servidor propio]" (el mismo awk de armar-deploy.sh).
# ---------------------------------------------------------------------
sin_bloques() {
    awk -v desde='-- [solo servidor propio] desde aca' \
        -v hasta='-- [solo servidor propio] hasta aca' '
        { linea = $0; sub(/\r$/, "", linea) }
        linea == desde { if (dentro) { mal = 1 } dentro = 1; next }
        linea == hasta { if (!dentro) { mal = 1 } dentro = 0; next }
        !dentro { print }
        END { if (dentro) { mal = 1 } exit mal }' "$@"
}

# Lo contrario: solo lo que esta dentro de los bloques.
solo_bloques() {
    awk -v desde='-- [solo servidor propio] desde aca' \
        -v hasta='-- [solo servidor propio] hasta aca' '
        { linea = $0; sub(/\r$/, "", linea) }
        linea == desde { dentro = 1; next }
        linea == hasta { dentro = 0; next }
        dentro { print }' "$@"
}

# Lo que no puede estar en la copia del hosting de una migracion (fuera
# de los comentarios): la misma lista que mira armar-deploy.sh.
trae_permisos() {
    tr -d '\r' < "$1" | grep -v '^[[:space:]]*--' \
        | grep -Eiq '^[[:space:]]*(GRANT|REVOKE|CREATE[[:space:]]+USER|USE)[[:space:]]'
}
trae_el_grant() {
    tr -d '\r' < "$1" | grep -v '^[[:space:]]*--' \
        | grep -Eq "^GRANT INSERT, UPDATE ON pedido_inscripcion TO 'sgdm_app'@'localhost';"
}
copia_al_dia() {
    head -n 2 "$COPIA_HOSTING" | grep -q '^-- GENERADO' \
        && tail -n +3 "$COPIA_HOSTING" | cmp -s - "$TMP/005_hosting.sql"
}

if ! git show "$COMMIT_VIEJO:sql/schema.sql" > "$TMP/schema_viejo_entero.sql" 2>/dev/null; then
    echo "No se puede leer sql/schema.sql del commit $COMMIT_VIEJO: hace falta el historial de git."
    exit 2
fi
if ! sin_bloques "$TMP/schema_viejo_entero.sql" > "$TMP/schema_viejo.sql" \
   || ! sin_bloques sql/schema.sql > "$TMP/schema_nuevo.sql" \
   || ! sin_bloques "$MIGRACION" > "$TMP/005_hosting.sql"; then
    echo "Las marcas [solo servidor propio] no cierran en alguno de los archivos."
    exit 2
fi
solo_bloques "$MIGRACION" > "$TMP/005_bloque.sql"

# La consulta del final (paso 6), sola.
sed -n '/^-- 6\. El resultado/,$p' "$MIGRACION" > "$TMP/005_consulta_final.sql"

# El renglon de la primera consulta (1a), la que frena sin la 003.
RENGLON_1A=$(grep -n '^SELECT COUNT(\*) AS pedidos_de_rol FROM pedido_rol;' "$TMP/005_hosting.sql" | head -n 1 | cut -d: -f1)

echo "===== Migracion 005 (bases t005_${SUFIJO}_*) ====="
echo "--- 8. Los archivos ---"
chk "caso 8: el original trae el GRANT a sgdm_app sobre pedido_inscripcion" trae_el_grant "$MIGRACION"
chk "caso 8: en el original, el GRANT esta dentro del bloque [solo servidor propio]" trae_el_grant "$TMP/005_bloque.sql"
chk "caso 8: fuera del bloque, el original no trae GRANT, REVOKE, usuarios ni USE" no trae_permisos "$TMP/005_hosting.sql"
if chk "caso 8: esta la copia del hosting ($COPIA_HOSTING)" test -f "$COPIA_HOSTING"; then
    chk "caso 8: la copia del hosting no trae GRANT, REVOKE, usuarios ni USE" no trae_permisos "$COPIA_HOSTING"
    chk "caso 8: la copia del hosting es el original sin el bloque (y las dos lineas de GENERADO)" copia_al_dia \
        || diff <(tail -n +3 "$COPIA_HOSTING") "$TMP/005_hosting.sql" | detalle
fi

# ---------------------------------------------------------------------
# Un servidor
# ---------------------------------------------------------------------
probar() {
    SOCKET=$1
    PREFIJO=''
    local version caso1
    version=$(sql -e "SELECT VERSION()" 2>/dev/null)
    echo
    echo "===== MariaDB ${version:-?} ($SOCKET) ====="
    chk "el servidor responde por el socket" test -n "$version" || return
    PREFIJO="[$(echo "$version" | cut -d. -f1,2)] "

    # --- 1 ------------------------------------------------------------
    echo "--- 1. Base vieja (antes de la 005) ---"
    base_vieja vieja "caso 1" " (schema.sql de $COMMIT_VIEJO, sin los bloques)" < /dev/null || return
    caso1=$BASE
    chk "caso 1: la base vieja tiene 17 tablas, sin torneos ni equipos" \
        iguales "$(sql -e "SELECT (SELECT COUNT(*) FROM information_schema.TABLES
                                    WHERE TABLE_SCHEMA = '$BASE' AND TABLE_TYPE = 'BASE TABLE'),
                                  (SELECT COUNT(*) FROM \`$BASE\`.torneo) + (SELECT COUNT(*) FROM \`$BASE\`.equipo)")" \
               "$(printf '17\t0')"
    correr "$BASE" "$TMP/005_hosting.sql"
    chk "caso 1: la 005 (sin el bloque, como en el hosting) corre sin error" corrio_bien || mostrar_error
    resultado_esperado "caso 1: la ultima consulta da los numeros de la cabecera de la 005"
    chk "caso 1: la base y sus 18 tablas quedan en utf8mb4_unicode_ci" \
        iguales "$(sql -e "SELECT CONCAT((SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA
                                           WHERE SCHEMA_NAME = '$BASE'), ' ',
                                         (SELECT COUNT(*) FROM information_schema.TABLES
                                           WHERE TABLE_SCHEMA = '$BASE' AND TABLE_TYPE = 'BASE TABLE'
                                             AND TABLE_COLLATION = 'utf8mb4_unicode_ci'))")" \
               'utf8mb4_unicode_ci 18'
    chk "caso 1: sin el bloque, ningun permiso nuevo sobre la base" iguales "$(permisos_de_la_base "$BASE")" ''

    # --- 2 ------------------------------------------------------------
    echo "--- 2. La 005 otra vez ---"
    antes "$caso1"
    correr "$caso1" "$TMP/005_hosting.sql"
    chk "caso 2: frena con ck_005_ya_esta_aplicada" frena_con 'ck_005_ya_esta_aplicada' || mostrar_error
    despues "$caso1"
    sin_cambios "caso 2"
    correr "$caso1" "$TMP/005_consulta_final.sql"
    chk "caso 2: la consulta del final (paso 6) corre sola" corrio_bien || mostrar_error
    resultado_esperado "caso 2: y sigue dando los numeros de la cabecera"

    # --- 3 ------------------------------------------------------------
    echo "--- 3. Base nueva (schema.sql de ahora) ---"
    crear_base nueva
    if chk "caso 3: se carga el schema.sql de ahora, sin los bloques" cargar "$BASE" "$TMP/schema_nuevo.sql"; then
        estructura "$BASE" sin_autoinc > "$TMP/nueva_sin_005"
        correr "$BASE" "$TMP/005_hosting.sql"
        chk "caso 3: la 005 corre sin error" corrio_bien || mostrar_error
        resultado_esperado "caso 3: la ultima consulta da lo mismo que en el caso 1"
        estructura "$caso1" sin_autoinc > "$TMP/estructura_1"
        estructura "$BASE" sin_autoinc > "$TMP/estructura_3"
        chk "caso 3: las dos bases tienen 18 tablas" \
            iguales "$(tablas_de "$caso1" | wc -l) $(tablas_de "$BASE" | wc -l)" '18 18'
        chk "caso 3: SHOW CREATE TABLE de las 18 tablas, identico al de la base vieja migrada (sin AUTO_INCREMENT=N)" \
            cmp -s "$TMP/estructura_1" "$TMP/estructura_3" \
            || diff "$TMP/estructura_1" "$TMP/estructura_3" | detalle 20
        chk "caso 3: en una base nueva, la parte de estructura de la 005 no cambia nada" \
            cmp -s "$TMP/nueva_sin_005" "$TMP/estructura_3" \
            || diff "$TMP/nueva_sin_005" "$TMP/estructura_3" | detalle 20
        datos "$caso1" sin_fecha > "$TMP/datos_1"
        datos "$BASE" sin_fecha > "$TMP/datos_3"
        chk "caso 3: los mismos datos, fila por fila, que la base vieja migrada (la auditoria sin su fecha)" \
            cmp -s "$TMP/datos_1" "$TMP/datos_3" \
            || diff "$TMP/datos_1" "$TMP/datos_3" | detalle 20
    fi

    # --- 4 ------------------------------------------------------------
    echo "--- 4. Sin la 003 ---"
    if base_vieja sin003 "caso 4" ", sin pedido_rol" <<< 'DROP TABLE pedido_rol;'; then
        antes "$BASE"
        correr "$BASE" "$TMP/005_hosting.sql"
        chk "caso 4: frena en la primera consulta (renglon $RENGLON_1A): pedido_rol doesn't exist" \
            frena_con "at line $RENGLON_1A: Table '$BASE.pedido_rol' doesn't exist" || mostrar_error
        despues "$BASE"
        sin_cambios "caso 4"
        chk "caso 4: sin la columna de_muestra ni la tabla pedido_inscripcion" sin_estructura_005 "$BASE"
    fi

    # --- 5 ------------------------------------------------------------
    echo "--- 5. Sin la 004 ---"
    if base_vieja sin004 "caso 5" ", con equipo en latin1" <<< 'ALTER TABLE equipo CONVERT TO CHARACTER SET latin1;'; then
        antes "$BASE"
        correr "$BASE" "$TMP/005_hosting.sql"
        chk "caso 5: frena con ck_005_falta_la_004" frena_con 'ck_005_falta_la_004' || mostrar_error
        despues "$BASE"
        sin_cambios "caso 5"
        chk "caso 5: sin la columna de_muestra ni la tabla pedido_inscripcion" sin_estructura_005 "$BASE"
    fi

    # --- 6 ------------------------------------------------------------
    echo "--- 6. Nombres de muestra ocupados ---"
    if base_vieja ocupados "caso 6" " con un equipo Vortex" \
         <<< "INSERT INTO equipo (nombre, fecha_alta) VALUES ('Vortex', '2026-09-01 10:00:00');"; then
        ocupado "caso 6" $'equipo\tVortex'
        sql "$BASE" -e "DELETE FROM equipo;
                        INSERT INTO equipo (nombre, fecha_alta) VALUES ('ATLANTIDA GG', '2026-09-01 10:00:00');"
        ocupado "caso 6 (Atlantida GG sin tilde, en mayusculas)" $'equipo\tATLANTIDA GG'
        sql "$BASE" -e "DELETE FROM equipo;
                        INSERT INTO usuario (correo, hash_password, nombre, apellido)
                        VALUES ('clubsur@ejemplo.invalid', '!prueba', 'Prueba', 'Ocupa');"
        ocupado "caso 6 (el correo clubsur@ejemplo.invalid)" $'cuenta\tclubsur@ejemplo.invalid'
        # Dos ligas vigentes con el mismo nombre (antes de la 005 se podia,
        # con distinta fecha): el UNIQUE nuevo (uq_torneo_vigente) fallaria
        # a mitad de la estructura, asi que la 005 frena antes. Con otra
        # mayuscula tambien: el cotejo no la distingue.
        sql "$BASE" -e "DELETE FROM usuario;
                        INSERT INTO usuario (correo, hash_password, nombre, apellido)
                        VALUES ('ocupa@ejemplo.invalid', '!prueba', 'Prueba', 'Ocupa');
                        INSERT INTO torneo (nombre, id_disciplina, id_tipo_torneo, id_modulo, id_usuario_organizador,
                                            fecha_inicio, max_participantes, estado)
                        SELECT f.nombre, (SELECT MIN(id_disciplina) FROM disciplina), (SELECT MIN(id_tipo_torneo) FROM tipo_torneo),
                               (SELECT MIN(id_modulo) FROM modulo_competencia), u.id_usuario, f.inicio, 8, f.estado
                          FROM usuario u
                         CROSS JOIN (SELECT 'Liga Repetida' AS nombre, '2026-10-01' AS inicio, 'inscripcion' AS estado
                                     UNION ALL SELECT 'LIGA REPETIDA', '2026-11-01', 'en_curso') f;"
        antes "$BASE"
        correr "$BASE" "$TMP/005_hosting.sql"
        chk "caso 6 (dos ligas vigentes con el mismo nombre, en distinta mayuscula): frena con ck_005_nombres_libres" \
            frena_con 'ck_005_nombres_libres' || mostrar_error
        chk "caso 6: la consulta 2c las lista como torneo vigente repetido" grep -qix -- $'torneo vigente repetido\tLiga Repetida' "$TMP/salida"
        despues "$BASE"
        sin_cambios "caso 6 (ligas vigentes repetidas)"
        # Una vigente y otra ya terminada, con el mismo nombre: no chocan.
        sql "$BASE" -e "UPDATE torneo SET estado = 'finalizado' WHERE nombre = 'LIGA REPETIDA' COLLATE utf8mb4_bin;"
        correr "$BASE" "$TMP/005_hosting.sql"
        chk "caso 6: una vigente y una finalizada con el mismo nombre no frenan la 005" corrio_bien || mostrar_error
        chk "caso 6: y nombre_vigente las distingue (el nombre en la vigente, NULL en la finalizada)" \
            iguales "$(sql "$BASE" -e "SELECT GROUP_CONCAT(CONCAT(estado, '=', IFNULL(nombre_vigente, 'NULL')) ORDER BY estado)
                                         FROM torneo WHERE nombre = 'Liga Repetida'")" 'finalizado=NULL,inscripcion=Liga Repetida'
    fi

    # --- 7 ------------------------------------------------------------
    echo "--- 7. Falla a mitad de la carga ---"
    if base_vieja atomica "caso 7" " con ck_prueba (ganados < 5) en tabla_posiciones" \
         <<< 'ALTER TABLE tabla_posiciones ADD CONSTRAINT ck_prueba CHECK (ganados < 5);'; then
        correr "$BASE" "$TMP/005_hosting.sql"
        chk "caso 7: la 005 falla en la carga de la tabla (ck_prueba)" frena_con 'ck_prueba' || mostrar_error
        chk "caso 7: no queda ningun dato de muestra (0 cuentas, 0 torneos, 0 equipos, nada)" \
            iguales "$(filas_de_datos "$BASE")" "$SIN_DATOS" \
            || filas_de_datos "$BASE" | detalle
        chk "caso 7: la estructura de la 005 si queda (de_muestra, criterio_desempate, pedido_inscripcion)" \
            con_estructura_005 "$BASE"
        sql "$BASE" -e 'ALTER TABLE tabla_posiciones DROP CONSTRAINT ck_prueba;'
        correr "$BASE" "$TMP/005_hosting.sql"
        chk "caso 7: sin ck_prueba, la 005 otra vez corre sin error" corrio_bien || mostrar_error
        resultado_esperado "caso 7: y carga todo: la ultima consulta da lo mismo que en el caso 1"
        estructura "$caso1" sin_autoinc > "$TMP/estructura_1"
        estructura "$BASE" sin_autoinc > "$TMP/estructura_7"
        chk "caso 7: la estructura queda identica a la del caso 1 (sin AUTO_INCREMENT=N)" \
            cmp -s "$TMP/estructura_1" "$TMP/estructura_7" \
            || diff "$TMP/estructura_1" "$TMP/estructura_7" | detalle 20
    fi

    # --- 8 ------------------------------------------------------------
    echo "--- 8. El original, con el GRANT ---"
    if [ "$(sql -e "SELECT COUNT(*) FROM mysql.user WHERE User = 'sgdm_app' AND Host = 'localhost'")" != 1 ]; then
        echo "  --    ${PREFIJO}caso 8: sgdm_app@localhost no existe en este servidor: no aplica"
        return
    fi
    if base_vieja original "caso 8" "" < /dev/null; then
        correr "$BASE" "$MIGRACION"
        chk "caso 8: el original (con el GRANT), corrido con root, anda" corrio_bien || mostrar_error
        resultado_esperado "caso 8: la ultima consulta da lo mismo que en el caso 1"
        chk "caso 8: sgdm_app queda con INSERT y UPDATE sobre pedido_inscripcion, y nada mas en la base" \
            iguales "$(permisos_de_la_base "$BASE")" 'sgdm_app@localhost pedido_inscripcion Insert,Update' \
            || permisos_de_la_base "$BASE" | detalle
        sql -e "REVOKE INSERT, UPDATE ON \`$BASE\`.pedido_inscripcion FROM 'sgdm_app'@'localhost'"
        chk "caso 8: el permiso de prueba se saca" iguales "$(permisos_de_la_base "$BASE")" ''
    fi
}

# Frena con ck_005_nombres_libres, sin cambiar nada, y la consulta 2c
# dice que esta ocupado.
ocupado() {  # caso fila_de_2c
    antes "$BASE"
    correr "$BASE" "$TMP/005_hosting.sql"
    chk "$1: frena con ck_005_nombres_libres" frena_con 'ck_005_nombres_libres' || mostrar_error
    chk "$1: la consulta 2c lo lista ($(tr '\t' ' ' <<< "$2"))" grep -qxF -- "$2" "$TMP/salida"
    despues "$BASE"
    sin_cambios "$1"
}

for S in "${SOCKETS[@]}"; do
    probar "$S"
done

# ---------------------------------------------------------------------
# Al terminar: las bases y los permisos de prueba, fuera.
# ---------------------------------------------------------------------
echo
echo "--- Limpieza ---"
PREFIJO=''
limpiar
for S in "${SOCKETS[@]}"; do
    SOCKET=$S
    sql -e "SELECT 1" > /dev/null 2>&1 || continue
    chk "no queda ninguna base t005_${SUFIJO}_* ni permiso sobre ellas ($S)" \
        iguales "$(sql -e "SELECT (SELECT COUNT(*) FROM information_schema.SCHEMATA
                                    WHERE SCHEMA_NAME LIKE 't005\\_${SUFIJO}\\_%'),
                                  (SELECT COUNT(*) FROM mysql.tables_priv
                                    WHERE Db LIKE 't005\\_${SUFIJO}\\_%')")" "$(printf '0\t0')"
done

if [ "$MALAS" -gt 0 ]; then
    echo
    echo "Las que fallaron:"
    printf '  - %s\n' "${FALLADAS[@]}"
fi
echo "$([ "$MALAS" -eq 0 ] && echo 'TODO BIEN' || echo "$MALAS FALLARON"): $BUENAS de $((BUENAS + MALAS)) comprobaciones"
[ "$MALAS" -eq 0 ] && exit 0
exit 1
