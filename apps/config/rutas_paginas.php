<?php
# =====================================================================
# Direcciones que usan las paginas publicas - instalacion local
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Vistas desde una pagina de public/ (/index.php, /torneos.php...).
# "Cerrar sesion" no esta en las paginas sino en el perfil, asi que aca
# no hace falta la direccion de la salida.
# En la maquina local los controladores se alcanzan por el Alias
# /apps/controllers de Apache (ver docs/configuracion-apache.md).
#
# En el hosting este archivo se reemplaza por otro con las direcciones
# de los puentes de controllers/: lo escribe scripts/armar-deploy.sh.
# =====================================================================

$ruta_publica = '.';
$ruta_perfil  = '../apps/controllers/perfilController.php';
