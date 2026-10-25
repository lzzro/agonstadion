<?php
# =====================================================================
# Configuracion de la base en Docker
# Proyecto SGDM - Stadion (Agon)
# ---------------------------------------------------------------------
# El Dockerfile la copia como stadion_app/config/database.local.php.
# Este archivo SI va al repositorio: no tiene ninguna clave. La de
# sgdm_app llega como variable de entorno (STADION_DB_CLAVE), que
# docker-compose.yml toma del archivo .env, que no se versiona.
# =====================================================================

return array(
    'servidor' => getenv('STADION_DB_SERVIDOR') ?: 'db',
    'usuario'  => 'sgdm_app',
    'clave'    => getenv('STADION_DB_CLAVE') ?: '',
    'base'     => 'sgdm'
);
