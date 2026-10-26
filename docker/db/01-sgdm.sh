#!/bin/bash
# La base de Stadion en Docker - se corre UNA vez, sola

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

raiz -e "ALTER USER 'sgdm_consulta'@'localhost' ACCOUNT LOCK;
         ALTER USER 'sgdm_admin'@'localhost' ACCOUNT LOCK;" || exit 1

echo "[stadion] Base lista."
