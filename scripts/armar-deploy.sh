#!/bin/bash
# =====================================================================
# Arma la copia para hosting compartido - Stadion (Agon)
# Proyecto de Lucas Martiarena
# ---------------------------------------------------------------------
# Genera deploy/hosting-compartido/ a partir de public/ y apps/, con la
# forma que pide un hosting tipo cPanel:
#
#     deploy/hosting-compartido/
#     ├── public_html/      -> va tal cual a public_html/ del hosting
#     ├── stadion_app/      -> va AL LADO de public_html, no adentro
#     └── sql/              -> NO se sube: el esquema para phpMyAdmin
#
# Existe para que la copia no se desincronice del original. Despues de
# tocar cualquier cosa en public/ o en apps/, se corre esto y la copia
# queda al dia. Sin script habria que acordarse de copiar a mano cada
# archivo, y tarde o temprano una correccion quedaria solo de un lado.
#
# No sube nada a ningun lado: solo escribe carpetas locales. La subida
# al hosting se hace a mano, por File Manager. Ver
# docs/deploy-hosting-compartido.md.
# =====================================================================

set -u

CARPETA_SCRIPT="$(cd "$(dirname "$0")" && pwd)"
RAIZ="$(cd "$CARPETA_SCRIPT/.." && pwd)"

DESTINO="$RAIZ/deploy/hosting-compartido"
PUBLICO="$DESTINO/public_html"
PRIVADO="$DESTINO/stadion_app"

avisar() { echo "[armar-deploy] $1"; }

case "${1:-}" in
    -h|--ayuda|ayuda)
        echo "Uso: $0"
        echo
        echo "Rehace $DESTINO"
        echo "a partir de public/ y apps/."
        exit 0
        ;;
    "") ;;
    *) echo "ERROR: argumento desconocido: $1" >&2; exit 2 ;;
esac

[ -d "$RAIZ/public" ] || { echo "ERROR: no aparece $RAIZ/public" >&2; exit 5; }
[ -d "$RAIZ/apps" ]   || { echo "ERROR: no aparece $RAIZ/apps" >&2; exit 5; }

avisar "Rehaciendo $DESTINO"
rm -rf "$DESTINO"
mkdir -p "$PUBLICO/controllers" "$PRIVADO"

# ---------------------------------------------------------------------
# 1. Lo publico: las paginas, el CSS, el JS y las imagenes.
# ---------------------------------------------------------------------
cp -r "$RAIZ/public/." "$PUBLICO/"

# Las fotos que se hayan subido en la maquina local NO viajan: son de
# prueba, y son datos de personas. De la carpeta de subidas solo va el
# .htaccess que impide ejecutar nada ahi. Asi, ademas, el zip nunca trae
# un archivo que pise una foto que ya este en el hosting.
find "$PUBLICO/subidas" -mindepth 1 ! -name '.htaccess' -exec rm -rf {} +
avisar "Paginas y recursos copiados (subidas: solo el .htaccess)."

# ---------------------------------------------------------------------
# 2. Lo privado: la aplicacion entera, que queda FUERA de public_html.
#
#    La configuracion local con la contrasena real NUNCA se copia. Es
#    la de la maquina de Lucas y ademas no tiene por que viajar: en el
#    hosting se escribe una propia, con las credenciales de cPanel.
# ---------------------------------------------------------------------
cp -r "$RAIZ/apps/." "$PRIVADO/"
rm -f "$PRIVADO/config/database.local.php"
avisar "Aplicacion copiada (sin la configuracion local)."

if [ -e "$PRIVADO/config/database.local.php" ]; then
    echo "ERROR: la configuracion local se colo en la copia." >&2
    exit 9
fi

# ---------------------------------------------------------------------
# 3. Las rutas de los formularios.
#
#    En la maquina local los formularios apuntan a
#    ../apps/controllers/xxxController.php, que Apache alcanza por un
#    Alias. En un hosting compartido eso no existe: apps/ queda fuera
#    de la carpeta publica a proposito. Los formularios pasan a apuntar
#    a los puentes de controllers/.
# ---------------------------------------------------------------------
sed -i 's|action="\.\./apps/controllers/loginController\.php"|action="controllers/login.php"|' "$PUBLICO/login.php"
sed -i 's|action="\.\./apps/controllers/registroController\.php"|action="controllers/registrar.php"|' "$PUBLICO/registro.php"
avisar "Rutas de los formularios ajustadas."

# ---------------------------------------------------------------------
# 4. El arranque de las paginas.
#
#    Cada pagina .php empieza incluyendo apps/config/pagina.php, que
#    mira si hay sesion para armar la cabecera. En el hosting esa
#    carpeta se llama stadion_app/ y queda al lado de public_html.
#
#    Ademas las paginas necesitan saber donde esta el perfil, y esa
#    direccion tambien cambia: en el hosting es el puente de
#    controllers/. Se reescribe el archivo que la guarda.
#
#    perfil.php, rendimiento.php y admin.php ya no hacen falta
#    tocarlos: son desvios del lado del servidor al perfil real y a la
#    administracion real, en las dos instalaciones.
# ---------------------------------------------------------------------
for PAGINA in "$PUBLICO"/*.php; do
    sed -i "s|__DIR__ \. '/\.\./apps/config/pagina\.php'|__DIR__ . '/../stadion_app/config/pagina.php'|" "$PAGINA"
done
if grep -l "/../apps/config/pagina.php" "$PUBLICO"/*.php | grep -q .; then
    echo "ERROR: alguna pagina sigue buscando apps/ en vez de stadion_app/." >&2
    exit 9
fi

cat > "$PRIVADO/config/rutas_paginas.php" <<'RUTAS'
<?php
# =====================================================================
# Direcciones que usan las paginas publicas - HOSTING
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Generado por scripts/armar-deploy.sh. No editar a mano.
#
# Vistas desde una pagina de public_html/ (/index.php, /torneos.php...).
# En el hosting los controladores se alcanzan por los puentes de
# public_html/controllers/.
# =====================================================================

$ruta_publica = '.';
$ruta_perfil  = 'controllers/perfil.php';
$ruta_admin   = 'controllers/admin.php';
RUTAS
avisar "Paginas apuntadas a stadion_app/ y a los puentes."

# ---------------------------------------------------------------------
# 5. Los puentes.
#
#    Cada uno hace una sola cosa: incluir el controlador de verdad, que
#    vive fuera de public_html. Son cortos a proposito. Toda la logica
#    esta del otro lado, donde ningun pedido web puede llegar.
#
#    Esto es lo que hace que config/ sea inalcanzable por URL: no hace
#    falta un .htaccess bien escrito ni confiar en la configuracion del
#    hosting, porque la carpeta directamente no esta dentro de lo que
#    el servidor publica.
# ---------------------------------------------------------------------
escribir_puente() {
    local ARCHIVO="$1"
    local CONTROLADOR="$2"
    local QUE_HACE="$3"

    cat > "$PUBLICO/controllers/$ARCHIVO" <<PUENTE
<?php
# =====================================================================
# Puente: $QUE_HACE
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Generado por scripts/armar-deploy.sh. No editar a mano.
#
# Este archivo no decide nada: solo llama al controlador de verdad, que
# vive en stadion_app/, fuera de public_html. Asi los modelos y la
# configuracion con la contrasena de la base quedan donde ningun pedido
# web puede alcanzarlos, ni con un .htaccess mal escrito, porque no
# estan dentro de la carpeta que el servidor publica.
#
# Si en el hosting stadion_app/ no quedara al lado de public_html, la
# unica linea que hay que cambiar es la de \$APLICACION.
# =====================================================================

# Desde public_html/controllers/, subir dos veces deja en la carpeta de
# la cuenta, donde esta stadion_app/ al lado de public_html/.
\$APLICACION = __DIR__ . '/../../stadion_app';

# Donde estan el CSS, el JS y las paginas, vistos desde la direccion de
# este archivo en el navegador (/controllers/...).
\$ruta_publica = '..';
\$ruta_perfil  = 'perfil.php';
\$ruta_salir   = 'salir.php';
\$ruta_admin   = 'admin.php';

# Donde se guardan las fotos de perfil y las portadas: la carpeta
# subidas/ de public_html, al lado de esta. La que tiene el .htaccess
# que impide ejecutar nada.
\$carpeta_subidas = __DIR__ . '/../subidas';

if (!is_dir(\$APLICACION)) {
    http_response_code(500);
    echo 'El sitio no encuentra su aplicacion.';
    exit;
}

require_once \$APLICACION . '/controllers/$CONTROLADOR';
PUENTE
}

escribir_puente "registrar.php" "registroController.php" "alta de cuenta"
escribir_puente "login.php"     "loginController.php"    "inicio de sesion"
escribir_puente "perfil.php"    "perfilController.php"   "perfil"
escribir_puente "salir.php"     "salirController.php"    "cierre de sesion"
escribir_puente "admin.php"     "adminController.php"    "administracion"
avisar "Cinco puentes escritos."

# ---------------------------------------------------------------------
# 6. La plantilla de configuracion propia del hosting.
#    Las credenciales de cPanel no son las de XAMPP: cambian el nombre
#    de la base y el del usuario, no solo la contrasena.
# ---------------------------------------------------------------------
cat > "$PRIVADO/config/database.local.php.ejemplo" <<'EJEMPLO'
<?php
# =====================================================================
# Configuracion de la base en el hosting - ARCHIVO DE EJEMPLO
# Proyecto SGDM - Stadion (Agon)
# ---------------------------------------------------------------------
# Este archivo SI se sube, porque no tiene nada secreto. La copia con
# los datos de verdad se llama database.local.php y se escribe en el
# hosting, no acá.
#
# Pasos, una vez subido todo:
#   1. en el File Manager de cPanel, entrar a stadion_app/config/
#   2. copiar este archivo como database.local.php
#      (boton derecho -> Copy, y cambiarle el nombre)
#   3. editarlo y poner los tres datos que da cPanel
#   4. dejarlo en permisos 600
#
# En cPanel los nombres llevan adelante el de la cuenta. Si la cuenta
# es "lucasmar" y la base se llamo "sgdm", el nombre completo es
# lucasmar_sgdm. Lo mismo con el usuario. Los nombres completos estan
# a la vista en MySQL Databases.
#
# Son distintos de los de XAMPP a proposito: la contrasena local no
# sirve acá, y la del hosting no sirve en la maquina de casa.
#
# El servidor casi siempre es localhost, porque la base corre en la
# misma maquina que el sitio. Si el hosting indica otro, va ese.
# =====================================================================

return array(
    'servidor' => 'localhost',
    'usuario'  => 'CUENTA_sgdm_app',
    'clave'    => 'LA-CONTRASENA-QUE-DIO-CPANEL',
    'base'     => 'CUENTA_sgdm'
);
EJEMPLO
avisar "Plantilla de configuracion del hosting escrita."

# ---------------------------------------------------------------------
# 7. El esquema para importar en el hosting.
#
#    sql/schema.sql esta pensado para un servidor propio (XAMPP, la VM):
#    crea la base, la elige con USE, borra las tablas para poder correrse
#    de nuevo y crea los usuarios de la base con sus permisos (DCL). En
#    un hosting compartido nada de eso se puede o se debe hacer: la base
#    y los usuarios los da cPanel, y en una base con cuentas reales un
#    DROP TABLE se lleva los datos por delante.
#
#    Esos bloques van marcados en schema.sql entre dos renglones:
#        -- [solo servidor propio] desde aca
#        -- [solo servidor propio] hasta aca
#    y aca se sacan. Todo lo demas pasa tal cual, incluida la
#    codificacion que cada tabla lleva escrita (utf8mb4): es la que hace
#    que el resultado no dependa de la base donde se importe.
#
#    La copia queda en sql/, al lado de public_html/ y stadion_app/,
#    pero esa carpeta NO se sube: el archivo se importa en phpMyAdmin.
#
#    Existe para que nunca haya que recortar schema.sql a mano: la vez
#    que se hizo, con el CREATE DATABASE se fue tambien la codificacion,
#    y las tablas del hosting quedaron en latin1.
# ---------------------------------------------------------------------
ESQUEMA="$RAIZ/sql/schema.sql"
ESQUEMA_HOSTING="$DESTINO/sql/schema-hosting.sql"
mkdir -p "$DESTINO/sql"

[ -f "$ESQUEMA" ] || { echo "ERROR: no aparece $ESQUEMA" >&2; exit 5; }

# Cada tabla tiene que declarar su codificacion: se comprueba en el
# original, asi una tabla nueva que la olvide no llega a ningun lado.
# (tr saca el \r de los finales de renglon de Windows.)
TABLAS=$(tr -d '\r' < "$ESQUEMA" | grep -c '^CREATE TABLE')
CON_CODIFICACION=$(tr -d '\r' < "$ESQUEMA" | grep -c '^) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;$')
if [ "$TABLAS" -eq 0 ] || [ "$TABLAS" -ne "$CON_CODIFICACION" ]; then
    echo "ERROR: schema.sql tiene $TABLAS tablas y solo $CON_CODIFICACION declaran" >&2
    echo "       ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci." >&2
    exit 9
fi

{
    cat <<'CABECERA'
-- =====================================================================
-- ESQUEMA PARA HOSTING COMPARTIDO - GENERADO, NO EDITAR A MANO
-- ---------------------------------------------------------------------
-- Lo genera scripts/armar-deploy.sh a partir de sql/schema.sql. Es el
-- mismo esquema sin los bloques "[solo servidor propio]": sin CREATE
-- DATABASE, sin USE, sin el borrado de las tablas y sin el DCL (los
-- usuarios de la base y sus permisos se dan en cPanel). Cada tabla
-- lleva su codificacion escrita, asi que queda en utf8mb4 aunque la
-- base del hosting venga en latin1.
--
-- Se importa en phpMyAdmin, con la base del hosting elegida a la
-- izquierda, en una base VACIA. Si la base ya tiene tablas, frena en la
-- primera ("already exists") sin tocar nada: para una base que ya
-- existe van las migraciones de sql/migraciones/. El paso a paso esta
-- en docs/deploy-hosting-compartido.md.
-- =====================================================================

CABECERA
    awk -v desde='-- [solo servidor propio] desde aca' \
        -v hasta='-- [solo servidor propio] hasta aca' '
        { linea = $0; sub(/\r$/, "", linea) }
        linea == desde {
            if (dentro) { print "renglon " NR ": se abre un bloque sin cerrar el anterior" > "/dev/stderr"; mal = 1 }
            dentro = 1; next
        }
        linea == hasta {
            if (!dentro) { print "renglon " NR ": se cierra un bloque que no se abrio" > "/dev/stderr"; mal = 1 }
            dentro = 0; next
        }
        !dentro { print }
        END {
            if (dentro) { print "un bloque [solo servidor propio] queda sin cerrar" > "/dev/stderr"; mal = 1 }
            exit mal
        }' "$ESQUEMA"
} > "$ESQUEMA_HOSTING" || { echo "ERROR: las marcas [solo servidor propio] de schema.sql no cierran." >&2; exit 9; }

# Lo que no puede quedar en la version del hosting, fuera de los
# comentarios.
if tr -d '\r' < "$ESQUEMA_HOSTING" | grep -v '^[[:space:]]*--' \
     | grep -Eiq '^[[:space:]]*(CREATE[[:space:]]+(DATABASE|SCHEMA|USER)|DROP[[:space:]]+(DATABASE|SCHEMA|TABLE|USER)|USE[[:space:]]|GRANT[[:space:]]|REVOKE[[:space:]]|ALTER[[:space:]]+USER|FLUSH[[:space:]])'; then
    echo "ERROR: la version del hosting todavia trae CREATE DATABASE, USE," >&2
    echo "       DROP, usuarios o permisos. Revisar las marcas de schema.sql." >&2
    exit 9
fi
# Y tienen que estar todas las tablas, cada una con su codificacion.
TABLAS_HOSTING=$(tr -d '\r' < "$ESQUEMA_HOSTING" | grep -c '^CREATE TABLE')
CODIFICACION_HOSTING=$(tr -d '\r' < "$ESQUEMA_HOSTING" | grep -c '^) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;$')
if [ "$TABLAS_HOSTING" -ne "$TABLAS" ] || [ "$CODIFICACION_HOSTING" -ne "$TABLAS" ]; then
    echo "ERROR: la version del hosting tiene $TABLAS_HOSTING de $TABLAS tablas" >&2
    echo "       ($CODIFICACION_HOSTING con la codificacion escrita)." >&2
    exit 9
fi
avisar "Esquema para el hosting escrito: $TABLAS tablas, todas en utf8mb4."

# ---------------------------------------------------------------------
# 8. Comprobaciones: que no se haya colado nada que no deba estar.
# ---------------------------------------------------------------------
COLADOS=0
for PROHIBIDO in "database.local.php" "respaldo.local.cnf"; do
    if find "$DESTINO" -name "$PROHIBIDO" | grep -q .; then
        echo "ERROR: $PROHIBIDO aparece en la copia." >&2
        COLADOS=1
    fi
done
if find "$PUBLICO/subidas" -mindepth 1 ! -name '.htaccess' | grep -q .; then
    echo "ERROR: hay imagenes subidas en la copia; solo va el .htaccess." >&2
    COLADOS=1
fi
if [ ! -f "$PUBLICO/subidas/.htaccess" ]; then
    echo "ERROR: falta public_html/subidas/.htaccess." >&2
    COLADOS=1
fi
[ "$COLADOS" -eq 0 ] || exit 9

avisar "Listo."
echo
echo "    $PUBLICO"
echo "        -> sube a public_html/ del hosting"
echo "    $PRIVADO"
echo "        -> sube AL LADO de public_html/, no adentro"
echo "    $ESQUEMA_HOSTING"
echo "        -> NO se sube: se importa en phpMyAdmin, en una base vacia"
echo
echo "El paso a paso completo: docs/deploy-hosting-compartido.md"
