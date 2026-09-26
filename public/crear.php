<?php
# =====================================================================
# Desvio a la creacion de ligas
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Aca estaba la maqueta del asistente de torneo nuevo, cuyo "Continuar"
# no guardaba nada. El formulario de verdad lo arma
# apps/controllers/crearController.php, que guarda la liga en la base y
# es solo para organizadores: esta direccion manda para alla, y alla se
# decide. Sin sesion, a iniciar sesion; sin el rol, al perfil, donde se
# pide.
#
# La maqueta original queda en el historial del repositorio.
# =====================================================================

require __DIR__ . '/../apps/config/pagina.php';

header('Location: ' . $ruta_crear);
exit;
