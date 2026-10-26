<?php
# Desvio a la administracion real

require __DIR__ . '/../stadion_app/config/pagina.php';

header('Location: ' . $ruta_admin);
exit;
