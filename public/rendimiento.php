<?php
# =====================================================================
# Desvio a la pestana Rendimiento del perfil real
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Esta pagina mostraba el rendimiento fisico de una persona inventada.
# Ese contenido vive ahora en la pestana Rendimiento del perfil real,
# marcado como de muestra, asi que esta direccion solo manda para alla.
#
# La pagina original queda en el historial del repositorio.
# =====================================================================

require __DIR__ . '/../apps/config/pagina.php';

header('Location: ' . $ruta_perfil . '#rendimiento');
exit;
