<?php
# Desvio a la administracion real

require __DIR__ . '/../apps/config/pagina.php';

header('Location: ' . $ruta_admin);
exit;
