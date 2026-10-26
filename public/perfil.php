<?php
# Desvio al perfil real

require __DIR__ . '/../apps/config/pagina.php';

header('Location: ' . $ruta_perfil);
exit;
