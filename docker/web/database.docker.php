<?php
# Configuracion de la base en Docker

return array(
    'servidor' => getenv('STADION_DB_SERVIDOR') ?: 'db',
    'usuario'  => 'sgdm_app',
    'clave'    => getenv('STADION_DB_CLAVE') ?: '',
    'base'     => 'sgdm'
);
