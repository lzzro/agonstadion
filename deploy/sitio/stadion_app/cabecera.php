<?php
# Vista parcial: la persona en la cabecera

require_once __DIR__ . '/models/Usuario.php';
require_once __DIR__ . '/models/ImagenSubida.php';

# La primera letra del nombre y la del apellido, en mayuscula.
function inicialesPersona(Usuario $persona)
{
    return mb_strtoupper(mb_substr((string)$persona->getNombre(), 0, 1)
                       . mb_substr((string)$persona->getApellido(), 0, 1));
}

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
