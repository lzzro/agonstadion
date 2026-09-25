<?php
# =====================================================================
# Vista parcial: acciones de la cabecera
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# La usan las paginas publicas (a traves de config/pagina.php) y las
# vistas de los controladores. Imprime el bloque de la derecha de la
# cabecera:
#
#   sin sesion   Iniciar sesion · Crear torneo   (igual que siempre)
#   con sesion   nombre (enlace al perfil) · Cerrar sesion · Crear torneo
#
# No abre ni valida la sesion: eso ya lo hizo quien la llama, con
# sesionVigente(). Aca solo se mira si quedo alguien adentro.
#
# "Cerrar sesion" es un formulario con POST y no un enlace: un enlace
# se puede disparar desde cualquier otra pagina con una imagen o un
# redireccionamiento, y cerrarle la sesion a alguien sin que lo pida.
#
# Recibe las tres direcciones porque cambian segun desde donde se mire:
# una pagina publica y un controlador no estan en la misma carpeta.
# =====================================================================

function accionesCabecera($ruta_publica, $ruta_perfil, $ruta_salir)
{
    $hay_sesion = isset($_SESSION['id_usuario']);
    echo '<div class="acciones">';
    if ($hay_sesion) {
        $nombre = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Perfil';
        echo '<a class="sesion-nombre" href="' . htmlspecialchars($ruta_perfil) . '">'
           . htmlspecialchars($nombre) . '</a>';
        echo '<form class="salir" action="' . htmlspecialchars($ruta_salir) . '" method="post">'
           . '<button class="btn" type="submit">Cerrar sesión</button></form>';
    } else {
        echo '<a class="btn" href="' . htmlspecialchars($ruta_publica) . '/login.php">Iniciar sesión</a>';
    }
    echo '<a class="btn btn-primario" href="' . htmlspecialchars($ruta_publica) . '/crear.php">Crear torneo</a>';
    echo '</div>';
}
