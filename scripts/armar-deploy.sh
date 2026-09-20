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
#     └── stadion_app/      -> va AL LADO de public_html, no adentro
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
avisar "Paginas y recursos copiados."

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
sed -i 's|action="\.\./apps/controllers/loginController\.php"|action="controllers/login.php"|' "$PUBLICO/login.html"
sed -i 's|action="\.\./apps/controllers/registroController\.php"|action="controllers/registrar.php"|' "$PUBLICO/registro.html"
avisar "Rutas de los formularios ajustadas."

# ---------------------------------------------------------------------
# 4. perfil.html era una maqueta con datos inventados. En el hosting no
#    puede quedar publicada: cualquiera que entre veria un perfil que
#    no existe. Se reemplaza por un desvio al perfil de verdad.
# ---------------------------------------------------------------------
cat > "$PUBLICO/perfil.html" <<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="refresh" content="0; url=controllers/perfil.php">
  <title>Perfil · Stadion</title>
  <link rel="icon" href="img/stadion.png">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="pagina">
<main>
<section>
  <p class="etiqueta">Perfil</p>
  <h1>El perfil se abre con la sesión iniciada.</h1>
  <div class="fila"><a class="btn btn-primario" href="controllers/perfil.php">Ir al perfil</a></div>
</section>
</main>
</div>
</body>
</html>
HTML
avisar "perfil.html reemplazado por el desvio al perfil real."

# ---------------------------------------------------------------------
# 5. Los tres puentes.
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
avisar "Tres puentes escritos."

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
# 7. Comprobaciones: que no se haya colado nada que no deba estar.
# ---------------------------------------------------------------------
COLADOS=0
for PROHIBIDO in "database.local.php" "respaldo.local.cnf"; do
    if find "$DESTINO" -name "$PROHIBIDO" | grep -q .; then
        echo "ERROR: $PROHIBIDO aparece en la copia." >&2
        COLADOS=1
    fi
done
[ "$COLADOS" -eq 0 ] || exit 9

avisar "Listo."
echo
echo "    $PUBLICO"
echo "        -> sube a public_html/ del hosting"
echo "    $PRIVADO"
echo "        -> sube AL LADO de public_html/, no adentro"
echo
echo "El paso a paso completo: docs/deploy-hosting-compartido.md"
