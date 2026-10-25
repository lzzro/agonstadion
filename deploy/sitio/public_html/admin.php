<?php
# =====================================================================
# Desvio a la administracion real
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Aca estaba la maqueta admin.html, con cuentas inventadas. La pagina
# de verdad la arma apps/controllers/adminController.php con los datos
# de la base, y es solo para cuentas con el rol administrador: esta
# direccion manda para alla, y alla se decide si se entra. Sin sesion,
# a iniciar sesion; sin el rol, un aviso y nada mas.
#
# La maqueta original queda en el historial del repositorio.
# =====================================================================

require __DIR__ . '/../stadion_app/config/pagina.php';

header('Location: ' . $ruta_admin);
exit;
