<?php
# =====================================================================
# La direccion del CSS y del JS, con su version
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Cada pagina pide style.css y tema.js con una marca al final:
#     css/style.css?v=3f9a0c21be
# La marca son los primeros caracteres de un resumen (md5) del
# contenido del archivo: mientras el archivo no cambia, la marca es la
# misma y el navegador usa su copia guardada; cuando cambia, la marca
# cambia, la direccion es otra y el navegador lo baja de nuevo. Sin
# esto, despues de subir un CSS nuevo, quien ya visito el sitio seguia
# viendo el viejo hasta vaciar la memoria del navegador.
#
# El archivo se busca en el disco, en la carpeta publica. En la copia de
# publicacion esa carpeta es public_html y queda al lado de stadion_app:
# scripts/armar-deploy.sh cambia la ruta de $carpeta en la copia.
# =====================================================================

function recurso($ruta_publica, $archivo)
{
    $carpeta  = __DIR__ . '/../../public';
    $en_disco = $carpeta . '/' . $archivo;
    $version  = is_file($en_disco) ? substr(md5_file($en_disco), 0, 10) : '0';
    return $ruta_publica . '/' . $archivo . '?v=' . $version;
}
