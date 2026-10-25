<?php
# =====================================================================
# Desvio al panel del organizador
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Aca estaba la maqueta panel.html, con la cuenta fija "Club Sur" y sus
# datos escritos a mano. El panel de verdad lo arma
# apps/controllers/panelController.php con las ligas de la cuenta de la
# sesion: esta direccion (la de "Organizadores" en el menu) manda para
# alla. Sin sesion, a iniciar sesion; sin el rol de organizador, al
# perfil, donde se pide.
#
# La maqueta original queda en el historial del repositorio, y el
# .htaccess manda panel.html para aca.
# =====================================================================

require __DIR__ . '/../stadion_app/config/pagina.php';

header('Location: ' . $ruta_panel);
exit;
