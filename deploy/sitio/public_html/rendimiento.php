<?php
# Desvio a la pestana Rendimiento del perfil real

require __DIR__ . '/../stadion_app/config/pagina.php';

header('Location: ' . $ruta_perfil . '#rendimiento');
exit;
