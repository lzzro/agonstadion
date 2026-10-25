#!/bin/bash
# gestion_usuarios.sh
# Agón - Stadion - Administración de Sistemas Operativos - Primera entrega
# Menú para gestionar los usuarios y los grupos del servidor (AlmaLinux 8.10).
# Lo ejecuta agonadmin, que es el usuario con sudo:  bash gestion_usuarios.sh
# Igual que en los scripts de clase, cada comando que cambia el sistema lleva sudo.

# Archivo donde queda anotado cada cambio (como el Logs.txt del script de clase)
log="gestion_usuarios.log"


# 1. Crear un usuario, con un grupo nuevo o con uno que ya existe
crear_usuario(){
clear
usuario=""
# Pide el nombre hasta que no quede vacío
while [ -z "$usuario" ]
do
    read -p "Ingrese el nombre del nuevo usuario: " usuario
    if [ -z "$usuario" ]; then
        echo "El campo no puede estar vacío."
    fi
done

# Busca el nombre en /etc/passwd: si aparece, el usuario ya existe
verif_usuario=$(cut -d":" -f1 /etc/passwd | grep -w "$usuario")
if ! [ -z "$verif_usuario" ]; then
    echo "El usuario $usuario ya existe."
else
    echo "1- Crear un grupo nuevo para el usuario"
    echo "2- Usar un grupo que ya existe"
    read -p "Seleccione una opción: " opc
    crear="no"
    case $opc in
    1)
        read -p "Nombre del grupo nuevo: " grupo
        verif_grupo=$(cut -d":" -f1 /etc/group | grep -w "$grupo")
        if [ -z "$grupo" ]; then
            echo "El campo no puede estar vacío."
        elif ! [ -z "$verif_grupo" ]; then
            echo "El grupo $grupo ya existe."
        else
            sudo groupadd $grupo
            crear="si"
        fi
        ;;
    2)
        read -p "Nombre del grupo existente: " grupo
        verif_grupo=$(cut -d":" -f1 /etc/group | grep -w "$grupo")
        if [ -z "$grupo" ]; then
            echo "El campo no puede estar vacío."
        elif [ -z "$verif_grupo" ]; then
            echo "El grupo $grupo no existe."
        else
            crear="si"
        fi
        ;;
    *)
        echo "Opción no válida."
        ;;
    esac

    if [ "$crear" == "si" ]; then
        # Los usuarios de servicio (como respaldo) no inician sesión
        read -p "¿El usuario va a iniciar sesión? (s/n): " sesion
        if [ "$sesion" == "n" ]; then
            shell="/sbin/nologin"
        else
            shell="/bin/bash"
        fi
        # Crea el usuario con su carpeta personal en /home
        sudo useradd -d /home/$usuario -s $shell -g $grupo -m $usuario
        # Solo el dueño puede entrar a su carpeta personal
        sudo chmod 700 /home/$usuario
        # Si inicia sesión, se le pone la contraseña inicial (la pide dos veces)
        if [ "$shell" == "/bin/bash" ]; then
            sudo passwd $usuario
        fi
        # Se vuelve a buscar en /etc/passwd para confirmar que se creó
        verif_usuario=$(cut -d":" -f1 /etc/passwd | grep -w "$usuario")
        if [ -z "$verif_usuario" ]; then
            echo "No se pudo crear el usuario $usuario."
        else
            echo "Usuario $usuario creado en el grupo $grupo."
            echo "(CREAR:USUARIO) $usuario (GRUPO) $grupo (FECHA) $(date)" >> $log
        fi
    fi
fi
sleep 2
}


# 2. Eliminar un usuario y su carpeta personal, pidiendo confirmación
eliminar_usuario(){
clear
read -p "Ingrese el usuario a eliminar: " usuario
verif_usuario=$(cut -d":" -f1 /etc/passwd | grep -w "$usuario")
if [ -z "$usuario" ]; then
    echo "El campo no puede estar vacío."
elif [ -z "$verif_usuario" ]; then
    echo "El usuario $usuario no existe."
else
    read -p "Se va a eliminar $usuario y su carpeta personal. ¿Confirma? (s/n): " confirma
    if [ "$confirma" == "s" ]; then
        sudo userdel -r $usuario
        # Se vuelve a buscar en /etc/passwd para confirmar que se borró
        verif_usuario=$(cut -d":" -f1 /etc/passwd | grep -w "$usuario")
        if [ -z "$verif_usuario" ]; then
            echo "Usuario $usuario eliminado."
            echo "(ELIMINAR:USUARIO) $usuario (FECHA) $(date)" >> $log
        else
            echo "No se pudo eliminar el usuario $usuario."
        fi
    else
        echo "Operación cancelada."
    fi
fi
sleep 2
}


# 3. Listar los usuarios que tienen su carpeta personal en /home
#    (los del sistema, como apache o mysql, tienen su carpeta en otro lado)
listar_usuarios(){
clear
echo "Usuarios con carpeta personal en /home:"
grep /home /etc/passwd | cut -d":" -f1
read -p "Presione Enter para volver al menú..." x
}


# 4. Cambiar la contraseña de un usuario
cambiar_contrasena(){
clear
read -p "Ingrese el usuario: " usuario
verif_usuario=$(cut -d":" -f1 /etc/passwd | grep -w "$usuario")
if [ -z "$usuario" ]; then
    echo "El campo no puede estar vacío."
elif [ -z "$verif_usuario" ]; then
    echo "El usuario $usuario no existe."
else
    sudo passwd $usuario
    echo "(CAMBIAR:CONTRASEÑA) $usuario (FECHA) $(date)" >> $log
fi
sleep 2
}


# 5. Bloquear o desbloquear la cuenta de un usuario
bloquear_usuario(){
clear
read -p "Ingrese el usuario: " usuario
verif_usuario=$(cut -d":" -f1 /etc/passwd | grep -w "$usuario")
if [ -z "$usuario" ]; then
    echo "El campo no puede estar vacío."
elif [ -z "$verif_usuario" ]; then
    echo "El usuario $usuario no existe."
else
    read -p "¿Desea bloquear o desbloquear la cuenta? (bloquear/desbloquear): " accion
    if [ "$accion" == "bloquear" ]; then
        sudo usermod -L $usuario
        echo "Usuario $usuario bloqueado."
        echo "(BLOQUEAR:USUARIO) $usuario (FECHA) $(date)" >> $log
    elif [ "$accion" == "desbloquear" ]; then
        sudo usermod -U $usuario
        echo "Usuario $usuario desbloqueado."
        echo "(DESBLOQUEAR:USUARIO) $usuario (FECHA) $(date)" >> $log
    else
        echo "Acción no válida."
    fi
fi
sleep 2
}


# 6. Crear un grupo
crear_grupo(){
clear
read -p "Nombre del grupo nuevo: " grupo
verif_grupo=$(cut -d":" -f1 /etc/group | grep -w "$grupo")
if [ -z "$grupo" ]; then
    echo "El campo no puede estar vacío."
elif ! [ -z "$verif_grupo" ]; then
    echo "El grupo $grupo ya existe."
else
    sudo groupadd $grupo
    echo "Grupo $grupo creado."
    echo "(CREAR:GRUPO) $grupo (FECHA) $(date)" >> $log
fi
sleep 2
}


# 7. Agregar un usuario a un grupo, sin sacarlo de los que ya tiene (-aG)
agregar_a_grupo(){
clear
read -p "Ingrese el usuario: " usuario
read -p "Ingrese el grupo: " grupo
verif_usuario=$(cut -d":" -f1 /etc/passwd | grep -w "$usuario")
verif_grupo=$(cut -d":" -f1 /etc/group | grep -w "$grupo")
if [ -z "$usuario" ]; then
    echo "El campo no puede estar vacío."
elif [ -z "$grupo" ]; then
    echo "El campo no puede estar vacío."
elif [ -z "$verif_usuario" ]; then
    echo "El usuario $usuario no existe."
elif [ -z "$verif_grupo" ]; then
    echo "El grupo $grupo no existe."
else
    sudo usermod -aG $grupo $usuario
    echo "Usuario $usuario agregado al grupo $grupo."
    echo "(AGREGAR:GRUPO) $usuario (GRUPO) $grupo (FECHA) $(date)" >> $log
fi
sleep 2
}


# 8. Listar los grupos: el campo 1 es el nombre y el 4, los usuarios que
#    tienen ese grupo como secundario (por ejemplo stadion:apache,respaldo)
listar_grupos(){
clear
echo "Grupos (nombre:integrantes):"
cut -d":" -f1,4 /etc/group
read -p "Presione Enter para volver al menú..." x
}


# Menú principal: se repite hasta que se elige 0
opcion=""
while [ "$opcion" != "0" ]
do
    clear
    echo "===== AGÓN - GESTIÓN DE USUARIOS Y GRUPOS ====="
    echo "1- Crear usuario"
    echo "2- Eliminar usuario"
    echo "3- Listar usuarios"
    echo "4- Cambiar contraseña"
    echo "5- Bloquear o desbloquear usuario"
    echo "6- Crear grupo"
    echo "7- Agregar usuario a un grupo"
    echo "8- Listar grupos"
    echo "0- Salir"
    read -p "Seleccione una opción: " opcion
    case $opcion in
    1) crear_usuario;;
    2) eliminar_usuario;;
    3) listar_usuarios;;
    4) cambiar_contrasena;;
    5) bloquear_usuario;;
    6) crear_grupo;;
    7) agregar_a_grupo;;
    8) listar_grupos;;
    0) echo "Saliendo...";;
    *) echo "Opción no válida."; sleep 2;;
    esac
done
