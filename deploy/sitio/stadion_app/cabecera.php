<?php
# =====================================================================
# Vista parcial: la persona en la cabecera
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La usan las paginas publicas (a traves de config/pagina.php) y las
# vistas de los controladores. Tiene dos funciones:
#
#   circuloPersona()     el circulo con la foto de la persona, o sus
#                        iniciales si no tiene. Es UNA sola funcion para
#                        el circulo chico de la cabecera y el grande del
#                        perfil: cambia el tamano (lo pone el CSS), no la
#                        logica.
#
#   accionesCabecera()   el bloque de la derecha de la cabecera:
#                          sin sesion   Iniciar sesion · Crear torneo
#                                       (exactamente igual que siempre)
#                          con sesion   el circulo (enlace al perfil) ·
#                                       Crear torneo
#
# "Cerrar sesion" no esta en la cabecera: vive en el perfil, debajo del
# nombre. Sigue siendo un formulario con POST (ver apps/perfil.php).
#
# accionesCabecera() no abre ni valida la sesion: eso ya lo hizo quien
# la llama, con sesionVigente(). Aca solo se mira si quedo alguien
# adentro.
# =====================================================================

require_once __DIR__ . '/models/Usuario.php';
require_once __DIR__ . '/models/ImagenSubida.php';

# La primera letra del nombre y la del apellido, en mayuscula.
function inicialesPersona(Usuario $persona)
{
    return mb_strtoupper(mb_substr((string)$persona->getNombre(), 0, 1)
                       . mb_substr((string)$persona->getApellido(), 0, 1));
}

# El circulo de una persona.
#   $clase       la clase de tamano: "avatar" (perfil) o "avatar
#                avatar-chico" (cabecera)
#   $decorativo  true cuando el circulo va dentro de un enlace que ya
#                dice a quien lleva: entonces no se anuncia dos veces.
# La foto solo se usa si su nombre tiene la forma de los que genera el
# sistema; si no, van las iniciales, como si no hubiera foto.
# La foto lleva width y height con el lado del circulo (30 en la
# cabecera, 96 en el perfil): el navegador reserva el lugar antes de
# bajarla. El CSS manda igual sobre el tamano (34 desde 768 px).
function circuloPersona(Usuario $persona, $ruta_publica, $clase, $decorativo)
{
    $foto = $persona->getFotoPerfil();
    if (ImagenSubida::nombreValido($foto)) {
        $alt  = $decorativo ? '' : 'Foto de perfil';
        $lado = (strpos($clase, 'avatar-chico') !== false) ? 30 : 96;
        return '<img class="' . $clase . '" src="' . htmlspecialchars($ruta_publica)
             . '/subidas/' . $foto . '" width="' . $lado . '" height="' . $lado . '" alt="' . $alt . '">';
    }
    $anuncio = $decorativo ? ' aria-hidden="true"' : ' role="img" aria-label="Sin foto de perfil"';
    return '<span class="' . $clase . ' avatar-vacio"' . $anuncio . '>'
         . htmlspecialchars(inicialesPersona($persona)) . '</span>';
}

# $persona es la cuenta de la sesion ya leida de la base (un Usuario), o
# null si quien llama no la tiene a mano. En ese caso alcanza con el
# nombre que guarda la sesion: el circulo sale con su inicial.
function accionesCabecera($ruta_publica, $ruta_perfil, $persona = null)
{
    echo '<div class="acciones">';
    if (isset($_SESSION['id_usuario'])) {
        if (!($persona instanceof Usuario)) {
            $nombre  = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : '';
            $persona = new Usuario((int)$_SESSION['id_usuario'], null, null, $nombre, '');
        }
        $quien = 'Perfil de ' . $persona->getNombreCompletoVisible();
        echo '<a class="sesion-circulo" href="' . htmlspecialchars($ruta_perfil) . '"'
           . ' aria-label="' . htmlspecialchars($quien) . '" title="' . htmlspecialchars($quien) . '">'
           . circuloPersona($persona, $ruta_publica, 'avatar avatar-chico', true) . '</a>';
    } else {
        echo '<a class="btn" href="' . htmlspecialchars($ruta_publica) . '/login.php">Iniciar sesión</a>';
    }
    echo '<a class="btn btn-primario" href="' . htmlspecialchars($ruta_publica) . '/crear.php">Crear torneo</a>';
    echo '</div>';
}
