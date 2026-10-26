#!/bin/bash
# Arma la copia de publicacion - Stadion (Agon)

set -u

CARPETA_SCRIPT="$(cd "$(dirname "$0")" && pwd)"
RAIZ="$(cd "$CARPETA_SCRIPT/.." && pwd)"

DESTINO="$RAIZ/deploy/sitio"
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

# 1. Lo publico: las paginas, el CSS, el JS y las imagenes.
cp -r "$RAIZ/public/." "$PUBLICO/"

# Las fotos que se hayan subido en la maquina local NO viajan: son de prueba, y son datos de personas.
find "$PUBLICO/subidas" -mindepth 1 ! -name '.htaccess' -exec rm -rf {} +
avisar "Paginas y recursos copiados (subidas: solo el .htaccess)."

# 2. Lo privado: la aplicacion entera, que queda FUERA de public_html.
cp -r "$RAIZ/apps/." "$PRIVADO/"
rm -f "$PRIVADO/config/database.local.php"
avisar "Aplicacion copiada (sin la configuracion local)."

sed -i "s|\$carpeta  = __DIR__ \. '/\.\./\.\./public';|\$carpeta  = __DIR__ . '/../../public_html';|" "$PRIVADO/config/recursos.php"
if ! grep -q "/../../public_html';" "$PRIVADO/config/recursos.php"; then
    echo "ERROR: config/recursos.php de la copia no apunta a public_html/." >&2
    exit 9
fi

if [ -e "$PRIVADO/config/database.local.php" ]; then
    echo "ERROR: la configuracion local se colo en la copia." >&2
    exit 9
fi

# 3. Las rutas de los formularios.
sed -i 's|action="\.\./apps/controllers/loginController\.php"|action="controllers/login.php"|' "$PUBLICO/login.php"
sed -i 's|action="\.\./apps/controllers/registroController\.php"|action="controllers/registrar.php"|' "$PUBLICO/registro.php"
avisar "Rutas de los formularios ajustadas."

# 4. El arranque de las paginas.
for PAGINA in "$PUBLICO"/*.php; do
    sed -i "s|__DIR__ \. '/\.\./apps/config/pagina\.php'|__DIR__ . '/../stadion_app/config/pagina.php'|" "$PAGINA"
done
if grep -l "/../apps/config/pagina.php" "$PUBLICO"/*.php | grep -q .; then
    echo "ERROR: alguna pagina sigue buscando apps/ en vez de stadion_app/." >&2
    exit 9
fi

cat > "$PRIVADO/config/rutas_paginas.php" <<'RUTAS'
<?php
# Direcciones de las paginas en la copia de publicacion. Generado por scripts/armar-deploy.sh.

$ruta_publica     = '.';
$ruta_perfil      = 'controllers/perfil.php';
$ruta_admin       = 'controllers/admin.php';
$ruta_crear       = 'controllers/crear.php';
$ruta_panel       = 'controllers/panel.php';
$ruta_inscripcion = 'controllers/inscripcion.php';
$ruta_partidos    = 'controllers/partidos.php';
RUTAS
avisar "Paginas apuntadas a stadion_app/ y a los puentes."

# 5. Los puentes.
escribir_puente() {
    local ARCHIVO="$1"
    local CONTROLADOR="$2"
    local QUE_HACE="$3"

    cat > "$PUBLICO/controllers/$ARCHIVO" <<PUENTE
<?php
# Puente: $QUE_HACE. Llama al controlador de stadion_app/, fuera de lo publicado.
# Generado por scripts/armar-deploy.sh.

\$APLICACION = __DIR__ . '/../../stadion_app';

\$ruta_publica     = '..';
\$ruta_perfil      = 'perfil.php';
\$ruta_salir       = 'salir.php';
\$ruta_admin       = 'admin.php';
\$ruta_crear       = 'crear.php';
\$ruta_panel       = 'panel.php';
\$ruta_inscripcion = 'inscripcion.php';
\$ruta_partidos    = 'partidos.php';
\$ruta_reportes    = 'reportes.php';

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
escribir_puente "crear.php"       "crearController.php"       "crear una liga"
escribir_puente "panel.php"       "panelController.php"       "panel del organizador"
escribir_puente "inscripcion.php" "inscripcionController.php" "pedido de lugar en una liga"
escribir_puente "partidos.php"    "partidosController.php"    "fechas y resultados de una liga"
escribir_puente "reportes.php"    "reportesController.php"    "reportes de los torneos"
avisar "Diez puentes escritos."

# 6. La codificacion de las tablas.
ESQUEMA="$RAIZ/sql/schema.sql"
[ -f "$ESQUEMA" ] || { echo "ERROR: no aparece $ESQUEMA" >&2; exit 5; }
TABLAS=$(tr -d '\r' < "$ESQUEMA" | grep -c '^CREATE TABLE')
CON_CODIFICACION=$(tr -d '\r' < "$ESQUEMA" | grep -c '^) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;$')
if [ "$TABLAS" -eq 0 ] || [ "$TABLAS" -ne "$CON_CODIFICACION" ]; then
    echo "ERROR: schema.sql tiene $TABLAS tablas y solo $CON_CODIFICACION declaran" >&2
    echo "       ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci." >&2
    exit 9
fi
avisar "Esquema revisado: $TABLAS tablas, todas en utf8mb4."

# 7. Comprobaciones: que no se haya colado nada que no deba estar.
COLADOS=0
# Las pruebas (tests/) son de la maquina local: no viajan nunca.
if find "$DESTINO" -path '*/tests' -o -path '*/tests/*' | grep -q .; then
    echo "ERROR: las pruebas (tests/) aparecen en la copia." >&2
    COLADOS=1
fi
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
echo "        -> lo que el servidor publica"
echo "    $PRIVADO"
echo "        -> la aplicacion, al lado de public_html/, no adentro"
echo
echo "Docker usa esta copia: despues de correr esto, docker compose up -d --build."
