<?php
# =====================================================================
# Desvio al perfil real
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Aca estaba la maqueta del perfil, con datos inventados. El perfil de
# verdad lo arma apps/controllers/perfilController.php con los datos de
# la base, asi que esta direccion solo manda para alla. Sin sesion, el
# perfil a su vez manda a iniciar sesion.
#
# La maqueta original queda en el historial del repositorio.
# =====================================================================

require __DIR__ . '/../stadion_app/config/pagina.php';

header('Location: ' . $ruta_perfil);
exit;
