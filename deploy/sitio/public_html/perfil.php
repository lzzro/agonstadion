<?php
# Desvio al perfil real

require __DIR__ . '/../stadion_app/config/pagina.php';

header('Location: ' . $ruta_perfil);
exit;
