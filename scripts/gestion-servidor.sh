#!/bin/bash
# Script general del servidor de Stadion (menú): servicios, recursos, registros, respaldos y actualizaciones.

carpeta_script="$(cd "$(dirname "$0")" && pwd)"
raiz="$(cd "$carpeta_script/.." && pwd)"
carpeta_log="/var/log/stadion"
log="$carpeta_log/servidor.log"

usuario_tareas="respaldo"

# Los servicios de los que depende el sitio, y los de la seguridad
servicios="httpd mariadb crond sshd firewalld fail2ban"

anotar(){
sudo mkdir -p "$carpeta_log"
sudo chmod 750 "$carpeta_log"
echo "($1) $2 (FECHA) $(date)" | sudo tee -a "$log" > /dev/null
}

pausa(){
echo
read -r -p "Presione Enter para volver al menú..."
}

confirmar(){
read -r -p "$1 (s/n): " respuesta
[ "$respuesta" == "s" ]
}

# 1. El estado de cada servicio: activo, detenido, y si arranca solo al prender la máquina
estado_servicios(){
clear
echo "===== ESTADO DE LOS SERVICIOS ====="
printf "%-12s %-12s %s\n" "Servicio" "Estado" "Arranca solo"
for s in $servicios
do
    estado=$(systemctl is-active "$s" 2>/dev/null)
    arranque=$(systemctl is-enabled "$s" 2>/dev/null)
    printf "%-12s %-12s %s\n" "$s" "${estado:-sin dato}" "${arranque:-sin dato}"
done
echo
echo "Sitio: $(curl -s -o /dev/null -w "%{http_code}" http://localhost/ 2>/dev/null) en http://localhost/ (200 es que anda)"
pausa
}

# 2. Iniciar, detener o reiniciar un servicio de la lista
manejar_servicio(){
clear
echo "===== INICIAR, DETENER O REINICIAR UN SERVICIO ====="
echo "Servicios: $servicios"
read -r -p "Servicio: " s
if ! echo " $servicios " | grep -q " $s "; then
    echo "Ese servicio no está en la lista."
    sleep 2
    return
fi
echo "1- Iniciar"
echo "2- Detener"
echo "3- Reiniciar"
read -r -p "Seleccione una opción: " opc
case $opc in
1) accion="start"; texto="Iniciar";;
2) accion="stop"; texto="Detener";;
3) accion="restart"; texto="Reiniciar";;
*) echo "Opción no válida."; sleep 2; return;;
esac
# Detener sshd deja la máquina sin acceso remoto: se avisa antes
if [ "$s" == "sshd" ] && [ "$accion" == "stop" ]; then
    echo "ATENCIÓN: sin sshd nadie más entra por SSH (esta sesión sigue abierta)."
fi
if [ "$s" == "httpd" ] && [ "$accion" != "stop" ]; then
    if ! sudo apachectl configtest; then
        echo "La configuración de Apache tiene un error: no se reinicia."
        pausa
        return
    fi
fi
if confirmar "¿$texto $s?"; then
    sudo systemctl "$accion" "$s"
    echo "$s: $(systemctl is-active "$s")"
    anotar "SERVICIO:$texto" "$s"
fi
pausa
}

# 3. Recursos: disco, memoria, carga y los procesos que más consumen
recursos(){
clear
echo "===== RECURSOS ====="
echo "Disco:"
df -h / /var | awk 'NR == 1 || !visto[$0]++'
echo
echo "Memoria:"
free -h
echo
echo "Carga (1, 5 y 15 minutos) y tiempo encendida:"
uptime
echo
echo "Los 5 procesos que más memoria usan:"
ps -eo user,pid,%cpu,%mem,comm --sort=-%mem | head -6
echo
echo "Lo que más ocupa del proyecto:"
du -sh "$raiz"/backups "$raiz"/metricas "$raiz"/public/subidas 2>/dev/null
pausa
}

# 4. Quién está conectado, los últimos inicios de sesión y los fallidos
sesiones(){
clear
echo "===== SESIONES ====="
echo "Conectados ahora:"
who
echo
echo "Últimos 10 inicios de sesión:"
last -n 10 | head -10
echo
echo "Últimos 10 intentos fallidos:"
sudo lastb -n 10 2>/dev/null | head -10
pausa
}

# 5. Los registros: los últimos renglones de Apache, MariaDB, el respaldo y este mismo registro
registros(){
clear
echo "===== REGISTROS ====="
echo "1- Errores de Apache"
echo "2- MariaDB"
echo "3- Respaldo diario"
echo "4- Cambios hechos con los scripts del servidor"
echo "5- Intentos de entrar por SSH (fail2ban)"
read -r -p "Seleccione una opción: " opc
case $opc in
1) sudo tail -30 /var/log/httpd/error_log;;
2) sudo journalctl -u mariadb -n 30 --no-pager;;
3) sudo tail -30 "$raiz/backups/respaldo.log" 2>/dev/null || echo "(todavía no hay respaldos)";;
4) sudo tail -30 "$log" 2>/dev/null || echo "(todavía no hay cambios anotados)";;
5) sudo tail -30 /var/log/fail2ban.log 2>/dev/null || echo "(fail2ban no está instalado)";;
*) echo "Opción no válida.";;
esac
pausa
}

respaldos(){
clear
echo "===== RESPALDOS ====="
echo "1- Hacer un respaldo ahora"
echo "2- Ver los respaldos"
echo "3- Comprobar el último (hash y firma digital)"
read -r -p "Seleccione una opción: " opc
case $opc in
1)
    # Como respaldo: es el dueño de la clave de la base y de las de la firma
    if sudo -u "$usuario_tareas" bash "$carpeta_script/respaldo.sh"; then
        anotar "RESPALDO" "a mano"
    fi
    ;;
2)
    sudo ls -lh "$raiz/backups" 2>/dev/null | grep "respaldo_" || echo "(todavía no hay respaldos)"
    ;;
3)
    ultimo=$(sudo ls -t "$raiz/backups" 2>/dev/null | grep "\.tar\.gz\.enc$" | head -1)
    if [ -z "$ultimo" ]; then
        echo "No hay respaldos cifrados todavía."
    else
        echo "Último: $ultimo"
        sudo -u "$usuario_tareas" bash "$carpeta_script/verificar-respaldo.sh" "$raiz/backups/$ultimo"
    fi
    ;;
*) echo "Opción no válida.";;
esac
pausa
}

# 7. Actualizaciones: ver las pendientes y aplicar las de seguridad
actualizaciones(){
clear
echo "===== ACTUALIZACIONES ====="
echo "Actualizaciones de seguridad pendientes:"
sudo dnf -q updateinfo list --security 2>/dev/null
echo
echo "Paquetes con versión nueva: $(sudo dnf -q check-update 2>/dev/null | grep -c '^[a-zA-Z0-9]')"
echo
if confirmar "¿Aplicar ahora las de seguridad?"; then
    sudo dnf update -y --security
    anotar "ACTUALIZAR" "seguridad"
    echo "Si se actualizó el núcleo (kernel), hace falta reiniciar la máquina."
    echo "Después de actualizar, AIDE va a avisar de los archivos cambiados:"
    echo "se aceptan con seguridad-servidor.sh, opción 7."
fi
pausa
}

# 8. Un informe con todo lo anterior, en un archivo con la fecha, para guardar o mandar
informe(){
clear
archivo="$carpeta_log/informe_$(date +%Y-%m-%d_%H%M).txt"
sudo mkdir -p "$carpeta_log"
{
    echo "===== INFORME DEL SERVIDOR - $(hostname) - $(date) ====="
    echo
    echo "--- Servicios ---"
    for s in $servicios
    do
        printf "%-12s %s\n" "$s" "$(systemctl is-active "$s" 2>/dev/null)"
    done
    echo
    echo "--- Disco ---"
    df -h /
    echo
    echo "--- Memoria ---"
    free -h
    echo
    echo "--- Carga ---"
    uptime
    echo
    echo "--- Cortafuegos ---"
    sudo firewall-cmd --list-services 2>/dev/null
    echo
    echo "--- fail2ban ---"
    sudo fail2ban-client status sshd 2>/dev/null
    echo
    echo "--- Último respaldo ---"
    sudo ls -lh "$raiz/backups" 2>/dev/null | grep "respaldo_" | tail -3
    echo
    echo "--- Últimos inicios de sesión ---"
    last -n 5 | head -5
    echo
    echo "--- Actualizaciones de seguridad pendientes ---"
    sudo dnf -q updateinfo list --security 2>/dev/null | wc -l
} 2>&1 | sudo tee "$archivo" > /dev/null
sudo chmod 600 "$archivo"
echo "Informe guardado en $archivo"
anotar "INFORME" "$archivo"
pausa
}

# 9, 10 y 11: los otros scripts del servidor.
abrir(){
if [ -f "$carpeta_script/$1" ]; then
    if [ -n "${2:-}" ]; then
        orden=(sudo -u "$2" bash "$carpeta_script/$1")
    else
        orden=(bash "$carpeta_script/$1")
    fi
    # Si el script corta con un aviso (le falta algo), se deja leerlo
    if ! "${orden[@]}"; then
        pausa
    fi
else
    echo "No está $carpeta_script/$1."
    sleep 2
fi
}

# Menú principal: se repite hasta que se elige 0
opcion=""
while [ "$opcion" != "0" ]
do
    clear
    echo "===== AGÓN - GESTIÓN DEL SERVIDOR ====="
    echo "1- Estado de los servicios"
    echo "2- Iniciar, detener o reiniciar un servicio"
    echo "3- Recursos (disco, memoria, carga)"
    echo "4- Sesiones"
    echo "5- Registros"
    echo "6- Respaldos"
    echo "7- Actualizaciones"
    echo "8- Informe del servidor"
    echo "9- Usuarios y grupos"
    echo "10- Seguridad del servidor"
    echo "11- Consultas y monitoreo de la base de datos"
    echo "0- Salir"
    read -r -p "Seleccione una opción: " opcion
    case $opcion in
    1) estado_servicios;;
    2) manejar_servicio;;
    3) recursos;;
    4) sesiones;;
    5) registros;;
    6) respaldos;;
    7) actualizaciones;;
    8) informe;;
    9) abrir gestion_usuarios.sh;;
    10) abrir seguridad-servidor.sh;;
    11) abrir monitoreo-bd.sh "$usuario_tareas";;
    0) echo "Saliendo...";;
    *) echo "Opción no válida."; sleep 2;;
    esac
done
