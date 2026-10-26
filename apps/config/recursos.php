<?php
# La direccion del CSS y del JS, con su version

function recurso($ruta_publica, $archivo)
{
    $carpeta  = __DIR__ . '/../../public';
    $en_disco = $carpeta . '/' . $archivo;
    $version  = is_file($en_disco) ? substr(md5_file($en_disco), 0, 10) : '0';
    return $ruta_publica . '/' . $archivo . '?v=' . $version;
}
