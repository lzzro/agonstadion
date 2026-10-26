#!/bin/bash
# Seguridad del servidor (menú): cortafuegos, fail2ban, antivirus, AIDE, SSH, SELinux y el certificado SSL.

carpeta_script="$(cd "$(dirname "$0")" && pwd)"
raiz="$(cd "$carpeta_script/.." && pwd)"
carpeta_log="/var/log/stadion"
log="$carpeta_log/servidor.log"

anotar(){
sudo mkdir -p "$carpeta_log"
sudo chmod 750 "$carpeta_log"
echo "($1) $2 (FECHA) $(date)" | sudo tee -a "$log" > /dev/null
}

pausa(){
echo
read -r -p "Presione Enter para volver al menú..."
}

# Pide s/n; devuelve 0 solo con "s"
confirmar(){
read -r -p "$1 (s/n): " respuesta
[ "$respuesta" == "s" ]
}

instalar_epel(){
if ! rpm -q epel-release > /dev/null 2>&1; then
    echo "Instalando el repositorio EPEL..."
    sudo dnf install -y epel-release
fi
}

cortafuegos(){
clear
echo "===== CORTAFUEGOS (firewalld) ====="
echo "Deja abiertos solo ssh (22), http (80) y https (443) en la zona public."
echo "Quita cockpit y dhcpv6-client, que AlmaLinux trae abiertos de fábrica."
echo
echo "Ahora:"
sudo firewall-cmd --list-services 2>/dev/null || echo "(firewalld apagado)"
echo
if confirmar "¿Aplicar el cortafuegos?"; then
    sudo systemctl enable --now firewalld
    sudo firewall-cmd --set-default-zone=public
    sudo firewall-cmd --permanent --zone=public --add-service=ssh --add-service=http --add-service=https
    sudo firewall-cmd --permanent --zone=public --remove-service=cockpit --remove-service=dhcpv6-client 2>/dev/null
    sudo firewall-cmd --reload
    echo
    echo "Quedó así:"
    sudo firewall-cmd --list-services
    anotar "CORTAFUEGOS" "ssh http https"
fi
pausa
}

# 2. Ver las reglas.
ver_reglas(){
clear
echo "===== REGLAS DEL CORTAFUEGOS ====="
sudo firewall-cmd --list-all
echo
echo "Puertos escuchando en la máquina (los de afuera llegan solo a 22, 80 y 443):"
sudo ss -tlnp | awk 'NR == 1 || /:22 |:80 |:443 |:3306 /'
echo
if confirmar "¿Ver también las reglas a bajo nivel (nftables)?"; then
    sudo nft list table inet firewalld | less
fi
pausa
}

instalar_fail2ban(){
clear
echo "===== FAIL2BAN ====="
echo "5 intentos fallidos de SSH en 10 minutos = 1 hora bloqueado."
echo
if confirmar "¿Instalar y configurar fail2ban?"; then
    instalar_epel
    sudo dnf install -y fail2ban
    printf '%s\n' \
        "# Stadion - fail2ban (lo escribe scripts/seguridad-servidor.sh)" \
        "[DEFAULT]" \
        "bantime  = 1h" \
        "findtime = 10m" \
        "maxretry = 5" \
        "" \
        "[sshd]" \
        "enabled = true" | sudo tee /etc/fail2ban/jail.local > /dev/null
    sudo systemctl enable --now fail2ban
    sudo systemctl restart fail2ban
    sleep 2
    sudo fail2ban-client status sshd
    anotar "FAIL2BAN" "sshd 5/10m 1h"
fi
pausa
}

# 4. Las direcciones bloqueadas por fail2ban, y desbloquear una
bloqueadas(){
clear
echo "===== DIRECCIONES BLOQUEADAS ====="
sudo fail2ban-client status sshd
echo
read -r -p "Dirección a desbloquear (Enter para ninguna): " ip
if [ -n "$ip" ]; then
    sudo fail2ban-client set sshd unbanip "$ip"
    anotar "DESBLOQUEAR:IP" "$ip"
fi
pausa
}

# 5. El antivirus: ClamAV, con las firmas al día.
antivirus(){
clear
echo "===== ANTIVIRUS (ClamAV) ====="
echo "Instala ClamAV, baja las firmas y programa con cron:"
echo "  - las firmas, todos los días a las 3:00"
echo "  - la revisión de $raiz, todos los días a las 3:30"
echo
if confirmar "¿Instalar el antivirus?"; then
    instalar_epel
    sudo dnf install -y clamav clamav-update
    echo "Bajando las firmas (tarda unos minutos)..."
    sudo freshclam
    # Las dos líneas de cron, en el crontab de root, sin repetirlas
    tabla=$(mktemp)
    sudo crontab -l 2>/dev/null | grep -v "freshclam\|clamscan" > "$tabla"
    echo "0 3 * * * /usr/bin/freshclam --quiet" >> "$tabla"
    echo "30 3 * * * /usr/bin/clamscan -r -i --log=/var/log/clamav-stadion.log $raiz > /dev/null" >> "$tabla"
    sudo crontab "$tabla"
    rm -f "$tabla"
    echo
    echo "Crontab de root:"
    sudo crontab -l
    anotar "ANTIVIRUS" "ClamAV con cron 3:00 y 3:30"
fi
pausa
}

# 6. Revisar ahora con el antivirus. -i: muestra solo lo infectado.
revisar_antivirus(){
clear
echo "===== REVISIÓN CON EL ANTIVIRUS ====="
echo "1- Solo la carpeta de las fotos subidas (rápido)"
echo "2- El proyecto entero"
read -r -p "Seleccione una opción: " opc
case $opc in
1) carpeta="$raiz/public/subidas";;
2) carpeta="$raiz";;
*) echo "Opción no válida."; sleep 2; return;;
esac
echo "Revisando $carpeta (ClamAV usa cerca de 1 GB de memoria mientras revisa)..."
sudo clamscan -r -i "$carpeta"
anotar "ANTIVIRUS:REVISION" "$carpeta"
echo
echo "Última revisión automática:"
sudo tail -8 /var/log/clamav-stadion.log 2>/dev/null || echo "(todavía no corrió)"
pausa
}

revisar_aide(){
clear
echo "===== AIDE ====="
echo "1- Instalar y sacar la primera huella (tarda varios minutos)"
echo "2- Comparar ahora"
echo "3- Aceptar los cambios (después de una actualización hecha a propósito)"
read -r -p "Seleccione una opción: " opc
case $opc in
1)
    sudo dnf install -y aide
    sudo aide --init
    sudo mv /var/lib/aide/aide.db.new.gz /var/lib/aide/aide.db.gz
    tabla=$(mktemp)
    sudo crontab -l 2>/dev/null | grep -v "aide" > "$tabla"
    echo "0 4 * * * /usr/sbin/aide --check > /var/log/aide-revision.log 2>&1" >> "$tabla"
    sudo crontab "$tabla"
    rm -f "$tabla"
    echo "Huella guardada. La comparación corre todos los días a las 4:00."
    anotar "AIDE" "primera huella y cron 4:00"
    ;;
2)
    sudo aide --check | less
    ;;
3)
    if confirmar "Los cambios que muestra la comparación pasan a ser lo normal. ¿Seguro?"; then
        sudo aide --update
        sudo mv /var/lib/aide/aide.db.new.gz /var/lib/aide/aide.db.gz
        anotar "AIDE" "huella actualizada"
    fi
    ;;
*) echo "Opción no válida.";;
esac
pausa
}

seguridad_ssh(){
clear
echo "===== SSH ====="
config="/etc/ssh/sshd_config"
echo "Ahora:"
sudo grep -E "^(PermitRootLogin|PasswordAuthentication)" "$config" || echo "(los valores de fábrica)"
echo
# Las cuentas que entran por SSH: las que tienen /bin/bash y carpeta en /home
cuentas=$(awk -F: '$7 == "/bin/bash" && $6 ~ /^\/home\// {print $1}' /etc/passwd)
con_clave="si"
if [ -z "$cuentas" ]; then
    con_clave="no"
fi
for c in $cuentas
do
    carpeta=$(awk -F: -v u="$c" '$1 == u {print $6}' /etc/passwd)
    if sudo test -s "$carpeta/.ssh/authorized_keys"; then
        echo "$c: clave pública cargada."
    else
        echo "$c: SIN clave pública."
        con_clave="no"
    fi
done
if [ "$con_clave" == "si" ]; then
    echo "Todas tienen clave: se puede apagar la contraseña."
else
    echo "Alguna cuenta no tiene clave: la contraseña queda prendida."
    echo "(Para cargarlas, ver el manual de administrador, sección 7.)"
fi
echo
if confirmar "¿Aplicar?"; then
    copia="$config.antes-$(date +%Y%m%d%H%M)"
    sudo cp "$config" "$copia"
    # Cambia la línea si está (comentada o no), y si no está la agrega
    if sudo grep -Eq "^#?PermitRootLogin" "$config"; then
        sudo sed -i -E "s/^#?PermitRootLogin.*/PermitRootLogin no/" "$config"
    else
        echo "PermitRootLogin no" | sudo tee -a "$config" > /dev/null
    fi
    if [ "$con_clave" == "si" ]; then
        if sudo grep -Eq "^#?PasswordAuthentication" "$config"; then
            sudo sed -i -E "s/^#?PasswordAuthentication.*/PasswordAuthentication no/" "$config"
        else
            echo "PasswordAuthentication no" | sudo tee -a "$config" > /dev/null
        fi
    fi
    if sudo sshd -t; then
        sudo systemctl reload sshd
        echo "Aplicado. NO cerrar esta sesión hasta probar que entra una nueva."
        anotar "SSH" "PermitRootLogin no, contraseña: $([ "$con_clave" == "si" ] && echo apagada || echo prendida)"
    else
        sudo cp "$copia" "$config"
        echo "El archivo quedó con un error: se volvió a la copia y no se cambió nada."
    fi
fi
pausa
}

# 9. SELinux: tiene que estar en Enforcing.
selinux(){
clear
echo "===== SELINUX ====="
sestatus | head -5
echo
echo "Contexto de la carpeta de las fotos:"
ls -Zd "$raiz/public/subidas"
echo
echo "Apache escribe solo en public/subidas. Con SELinux en Enforcing, esa carpeta"
echo "necesita el tipo httpd_sys_rw_content_t, o la carga de fotos falla."
if confirmar "¿Darle ese tipo a public/subidas?"; then
    if ! command -v semanage > /dev/null 2>&1; then
        sudo dnf install -y policycoreutils-python-utils
    fi
    sudo semanage fcontext -a -t httpd_sys_rw_content_t "$raiz/public/subidas(/.*)?" 2>/dev/null \
        || sudo semanage fcontext -m -t httpd_sys_rw_content_t "$raiz/public/subidas(/.*)?"
    sudo restorecon -Rv "$raiz/public/subidas"
    anotar "SELINUX" "httpd_sys_rw_content_t en public/subidas"
fi
if [ "$(getenforce)" != "Enforcing" ]; then
    echo
    echo "ATENCIÓN: SELinux no está en Enforcing. Ver el manual de administrador, sección 7."
fi
pausa
}

certificado(){
clear
echo "===== CERTIFICADO SSL ====="
read -r -p "Nombre del sitio en la VM [stadion.local]: " nombre
nombre=${nombre:-stadion.local}
echo
echo "Va a:"
echo "  - instalar mod_ssl"
echo "  - crear un certificado propio para $nombre (RSA 3072, un año)"
echo "  - escribir /etc/httpd/conf.d/stadion-ssl.conf (el sitio por https)"
echo "  - comprobar la configuración y reiniciar Apache"
echo
if confirmar "¿Seguir?"; then
    sudo dnf install -y mod_ssl
    sudo openssl req -x509 -newkey rsa:3072 -sha256 -days 365 -nodes \
        -subj "/CN=$nombre" -addext "subjectAltName=DNS:$nombre" \
        -keyout /etc/pki/tls/private/stadion.key -out /etc/pki/tls/certs/stadion.crt
    sudo chmod 600 /etc/pki/tls/private/stadion.key
    sed -e "s#RAIZ_DEL_PROYECTO#$raiz#g" -e "s#NOMBRE_DEL_SITIO#$nombre#g" \
        "$carpeta_script/stadion-ssl.conf.ejemplo" | sudo tee /etc/httpd/conf.d/stadion-ssl.conf > /dev/null
    if sudo apachectl configtest; then
        sudo systemctl restart httpd
        echo
        echo "Listo: https://$nombre/ (el navegador avisa que el certificado es propio)."
        anotar "SSL" "$nombre"
    else
        sudo rm -f /etc/httpd/conf.d/stadion-ssl.conf
        echo "La configuración tiene un error: se quitó stadion-ssl.conf y Apache sigue como estaba."
    fi
fi
pausa
}

# 11. Resumen: el estado de cada medida, en una pantalla
resumen(){
clear
echo "===== RESUMEN DE SEGURIDAD ====="
printf "%-28s %s\n" "Cortafuegos (firewalld)" "$(systemctl is-active firewalld 2>/dev/null)"
printf "%-28s %s\n" "Servicios abiertos" "$(sudo firewall-cmd --list-services 2>/dev/null)"
printf "%-28s %s\n" "fail2ban" "$(systemctl is-active fail2ban 2>/dev/null)"
printf "%-28s %s\n" "Direcciones bloqueadas" "$(sudo fail2ban-client status sshd 2>/dev/null | awk -F: '/Currently banned/ {print $2}')"
printf "%-28s %s\n" "Antivirus (firmas)" "$(sudo find /var/lib/clamav -name 'daily.c?d' -printf '%TY-%Tm-%Td' 2>/dev/null)"
printf "%-28s %s\n" "AIDE (huella)" "$(sudo test -f /var/lib/aide/aide.db.gz && echo guardada || echo no)"
printf "%-28s %s\n" "SELinux" "$(getenforce 2>/dev/null)"
printf "%-28s %s\n" "SSH: entrar como root" "$(sudo sshd -T 2>/dev/null | awk '/^permitrootlogin/ {print $2}')"
printf "%-28s %s\n" "SSH: con contraseña" "$(sudo sshd -T 2>/dev/null | awk '/^passwordauthentication/ {print $2}')"
printf "%-28s %s\n" "Certificado SSL" "$(sudo openssl x509 -enddate -noout -in /etc/pki/tls/certs/stadion.crt 2>/dev/null | cut -d= -f2)"
printf "%-28s %s\n" "Actualizaciones de seguridad" "$(sudo dnf -q updateinfo list --security 2>/dev/null | wc -l) pendientes"
pausa
}

# Menú principal: se repite hasta que se elige 0
opcion=""
while [ "$opcion" != "0" ]
do
    clear
    echo "===== AGÓN - SEGURIDAD DEL SERVIDOR ====="
    echo "1- Aplicar el cortafuegos"
    echo "2- Ver las reglas del cortafuegos"
    echo "3- Instalar fail2ban"
    echo "4- Direcciones bloqueadas por fail2ban"
    echo "5- Instalar el antivirus"
    echo "6- Revisar con el antivirus"
    echo "7- AIDE (archivos del sistema)"
    echo "8- SSH: sin root y con clave"
    echo "9- SELinux"
    echo "10- Certificado SSL"
    echo "11- Resumen de seguridad"
    echo "0- Salir"
    read -r -p "Seleccione una opción: " opcion
    case $opcion in
    1) cortafuegos;;
    2) ver_reglas;;
    3) instalar_fail2ban;;
    4) bloqueadas;;
    5) antivirus;;
    6) revisar_antivirus;;
    7) revisar_aide;;
    8) seguridad_ssh;;
    9) selinux;;
    10) certificado;;
    11) resumen;;
    0) echo "Saliendo...";;
    *) echo "Opción no válida."; sleep 2;;
    esac
done
