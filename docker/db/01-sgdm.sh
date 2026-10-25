#!/bin/bash
# =====================================================================
# La base de Stadion en Docker - se corre UNA vez, sola
# ---------------------------------------------------------------------
# La imagen de MariaDB corre los archivos de esta carpeta la primera vez
# que arranca con el volumen de datos vacio. Despues, nunca mas (para
# empezar de cero: docker compose down -v, ver docs/docker.md).
#
#   1. sql/schema.sql entero: la base sgdm, las 19 tablas y el DCL (los
#      tres usuarios de base de datos, con su minimo privilegio).
#   2. Las migraciones 005 a 008: en una base nueva, la 005 carga las
#      tres ligas de muestra y la 006 marca la serie de una; la 007 y la
#      008 no cambian nada (el esquema ya las trae).
#   3. La clave de sgdm_app, que llega del .env (SGDM_APP_CLAVE), y la
#      misma cuenta para la red de Docker ('sgdm_app'@'%'): el sitio se
#      conecta desde otro contenedor, no desde localhost. Con los mismos
#      permisos que 'sgdm_app'@'localhost', copiados de SHOW GRANTS.
#
# La clave solo puede tener letras y numeros (ver docs/docker.md).
# =====================================================================

raiz() { mariadb --protocol=socket -uroot -p"${MARIADB_ROOT_PASSWORD}" "$@"; }

echo "[stadion] Esquema (sql/schema.sql)..."
raiz < /sgdm/sql/schema.sql || exit 1

for m in 005 006 007 008; do
    echo "[stadion] Migracion $m..."
    raiz sgdm < /sgdm/sql/migraciones/${m}_*.sql > /dev/null || exit 1
done

if ! printf '%s' "${SGDM_APP_CLAVE}" | grep -Eq '^[A-Za-z0-9]{12,}$'; then
    echo "[stadion] SGDM_APP_CLAVE tiene que tener 12 letras o numeros, o mas." >&2
    exit 1
fi

echo "[stadion] La cuenta sgdm_app para la red de Docker..."
raiz -e "ALTER USER 'sgdm_app'@'localhost' IDENTIFIED BY '${SGDM_APP_CLAVE}';
         CREATE USER IF NOT EXISTS 'sgdm_app'@'%' IDENTIFIED BY '${SGDM_APP_CLAVE}';" || exit 1
raiz -N -e "SHOW GRANTS FOR 'sgdm_app'@'localhost'" \
    | grep -v '^GRANT USAGE' \
    | sed -e 's/`localhost`/`%`/' -e 's/$/;/' \
    | raiz || exit 1

# sgdm_consulta y sgdm_admin no se usan en Docker: quedan bloqueadas
# (sus claves del esquema son marcadores).
raiz -e "ALTER USER 'sgdm_consulta'@'localhost' ACCOUNT LOCK;
         ALTER USER 'sgdm_admin'@'localhost' ACCOUNT LOCK;" || exit 1

echo "[stadion] Base lista."
