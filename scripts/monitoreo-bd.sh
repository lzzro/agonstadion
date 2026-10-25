#!/bin/bash
# monitoreo-bd.sh
# Agón - Stadion - Administración de Sistemas Operativos - Tercera entrega
# Consultas y monitoreo de la base de datos sgdm (MariaDB 10.11), con menú.
#
#   bash scripts/monitoreo-bd.sh          el menú
#   bash scripts/monitoreo-bd.sh --csv    una lectura en metricas/bd.csv, sin
#                                         preguntar nada (para cron)
#
# Entra con sgdm_consulta, la cuenta de la base que SOLO PUEDE LEER (el DCL de
# sql/schema.sql le da SELECT y nada más): mirar la base no puede cambiarla.
# Su contraseña va en scripts/consultas.local.cnf, que el .gitignore excluye,
# y se le pasa al cliente con --defaults-extra-file, igual que en respaldo.sh:
# lo que va en la línea de comandos lo ve cualquiera con "ps aux".
# El paso a paso, en docs/servidor.md.


# Rutas: se deducen de dónde está el script, así anda desde cualquier carpeta
# (cron no arranca en la carpeta del proyecto).
carpeta_script="$(cd "$(dirname "$0")" && pwd)"
raiz="$(cd "$carpeta_script/.." && pwd)"
credenciales="$carpeta_script/consultas.local.cnf"
csv="$raiz/metricas/bd.csv"
base="sgdm"


# Comprobaciones antes de mostrar nada
comprobar(){
if ! command -v mysql > /dev/null 2>&1; then
    echo "Falta el cliente mysql (paquete MariaDB-client)."
    exit 3
fi
if [ ! -f "$credenciales" ]; then
    echo "Falta $credenciales."
    echo "Copiar consultas.local.cnf.ejemplo con ese nombre y poner la contraseña de sgdm_consulta."
    exit 4
fi
# El archivo tiene una contraseña: solo lo lee su dueño (como en respaldo.sh)
permisos=$(stat -c "%a" "$credenciales")
if [ "$permisos" != "600" ]; then
    echo "Permisos $permisos en $credenciales: deben ser 600 (chmod 600 $credenciales)."
    exit 4
fi
}

# La hora de Montevideo, la misma que usa el sitio (apps/config/database.php):
# la auditoría se guarda con esa hora, y "las últimas 24 horas" se cuentan
# desde ahí.
zona="SET time_zone = '-03:00';"

# Una consulta, mostrada como tabla
consulta(){
mysql --defaults-extra-file="$credenciales" --default-character-set=utf8mb4 -t "$base" -e "$zona $1"
}

# Una consulta que devuelve un solo valor (o renglones sueltos), sin encabezado
valor(){
mysql --defaults-extra-file="$credenciales" --default-character-set=utf8mb4 -N -B "$base" -e "$zona $1"
}

pausa(){
echo
read -r -p "Presione Enter para volver al menú..."
}


# 1. El servidor de base de datos: si está andando, versión, tiempo encendido
#    y conexiones. Lo de GLOBAL_STATUS lo puede leer cualquier cuenta.
estado_servidor(){
clear
echo "===== ESTADO DEL SERVIDOR DE BASE DE DATOS ====="
servicio=$(systemctl is-active mariadb 2>/dev/null)
echo "Servicio mariadb: ${servicio:-sin dato}"
if ! valor "SELECT 1" > /dev/null 2>&1; then
    echo "No hay conexión con la base: el servicio está detenido o la contraseña de"
    echo "consultas.local.cnf no es la de sgdm_consulta."
    pausa
    return
fi
consulta "
SELECT 'Versión' AS Dato, VERSION() AS Valor
UNION ALL SELECT 'Encendida desde hace',
       CONCAT(FLOOR(s.uptime / 86400), ' d ', SEC_TO_TIME(s.uptime % 86400))
  FROM (SELECT CAST(VARIABLE_VALUE AS UNSIGNED) AS uptime FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'UPTIME') s
UNION ALL SELECT 'Conexiones abiertas ahora', VARIABLE_VALUE
  FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'THREADS_CONNECTED'
UNION ALL SELECT 'Máximo de conexiones a la vez', VARIABLE_VALUE
  FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'MAX_USED_CONNECTIONS'
UNION ALL SELECT 'Límite de conexiones', @@max_connections
UNION ALL SELECT 'Consultas desde que arrancó', VARIABLE_VALUE
  FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'QUESTIONS'
UNION ALL SELECT 'Consultas lentas', VARIABLE_VALUE
  FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'SLOW_QUERIES'
UNION ALL SELECT 'Conexiones rechazadas (clave mal o sin permiso)', VARIABLE_VALUE
  FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'ABORTED_CONNECTS';"
pausa
}


# 2. Cuánto ocupa la base, tabla por tabla, de la más grande a la más chica
tamano_tablas(){
clear
echo "===== TAMAÑO DE LA BASE $base ====="
consulta "
SELECT table_name AS Tabla,
       table_rows AS 'Filas (aprox.)',
       ROUND(data_length / 1024, 1) AS 'Datos (KB)',
       ROUND(index_length / 1024, 1) AS 'Índices (KB)'
  FROM information_schema.TABLES
 WHERE table_schema = '$base'
 ORDER BY data_length + index_length DESC;
SELECT COUNT(*) AS Tablas,
       ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Total (MB)'
  FROM information_schema.TABLES
 WHERE table_schema = '$base';"
pausa
}


# 3. Cantidad exacta de registros de cada tabla. La de la opción 2 es una
#    estimación de InnoDB; esta cuenta fila por fila (COUNT).
registros_por_tabla(){
clear
echo "===== REGISTROS POR TABLA ====="
tablas=$(valor "SELECT table_name FROM information_schema.TABLES WHERE table_schema = '$base' AND table_type = 'BASE TABLE' ORDER BY table_name")
sql=""
for tabla in $tablas
do
    if [ -n "$sql" ]; then
        sql="$sql UNION ALL "
    fi
    sql="$sql SELECT '$tabla' AS Tabla, COUNT(*) AS Registros FROM \`$tabla\`"
done
consulta "$sql;"
pausa
}


# 4. Lo que pasa en el sistema: cuentas, torneos, partidos, pedidos y
#    mediciones
actividad(){
clear
echo "===== ACTIVIDAD DEL SISTEMA ====="
consulta "
SELECT CASE WHEN de_muestra = 1 THEN 'De muestra'
            WHEN activo = 1 THEN 'Activas'
            ELSE 'De baja' END AS Cuentas,
       COUNT(*) AS Cantidad
  FROM usuario GROUP BY 1;
SELECT r.nombre AS Rol, COUNT(*) AS Cuentas
  FROM usuario_rol ur JOIN rol r ON r.id_rol = ur.id_rol
 GROUP BY r.nombre ORDER BY COUNT(*) DESC;
SELECT m.nombre AS Formato,
       SUM(t.estado = 'inscripcion') AS 'Inscripción',
       SUM(t.estado = 'en_curso') AS 'En curso',
       SUM(t.estado = 'finalizado') AS Terminados,
       SUM(t.estado = 'cancelado') AS Cancelados
  FROM torneo t JOIN modulo_competencia m ON m.id_modulo = t.id_modulo
 GROUP BY m.nombre;
SELECT SUM(estado = 'jugado') AS 'Partidos jugados',
       SUM(estado = 'en_vivo') AS 'En vivo',
       SUM(estado = 'programado') AS 'Por jugar'
  FROM enfrentamiento;
SELECT (SELECT COUNT(*) FROM pedido_rol WHERE estado = 'pendiente') AS 'Pedidos de rol pendientes',
       (SELECT COUNT(*) FROM pedido_inscripcion WHERE estado = 'pendiente') AS 'Pedidos de lugar pendientes',
       (SELECT COUNT(*) FROM medicion WHERE fecha >= CURDATE() - INTERVAL 30 DAY) AS 'Mediciones (30 días)';"
pausa
}


# 5. Seguridad: inicios de sesión fallidos y últimos movimientos del
#    registro de auditoría. Muchos fallos seguidos desde una misma dirección
#    son un intento de adivinar contraseñas.
seguridad(){
clear
echo "===== SEGURIDAD (últimas 24 horas) ====="
consulta "
SELECT SUM(accion = 'login_ok') AS 'Inicios de sesión',
       SUM(accion = 'login_error') AS 'Intentos fallidos'
  FROM auditoria
 WHERE fecha_hora >= NOW() - INTERVAL 1 DAY;
SELECT IFNULL(direccion_ip, 'sin dato') AS 'Dirección IP',
       COUNT(*) AS 'Intentos fallidos',
       MAX(fecha_hora) AS 'Último'
  FROM auditoria
 WHERE accion = 'login_error' AND fecha_hora >= NOW() - INTERVAL 1 DAY
 GROUP BY direccion_ip
 ORDER BY COUNT(*) DESC
 LIMIT 10;"
echo
echo "Últimos 15 movimientos del registro de auditoría:"
consulta "
SELECT a.fecha_hora AS Fecha, a.accion AS Acción, a.tabla_afectada AS Tabla,
       IFNULL(u.correo, '-') AS Cuenta, IFNULL(a.direccion_ip, '-') AS IP
  FROM auditoria a LEFT JOIN usuario u ON u.id_usuario = a.id_usuario
 ORDER BY a.id_auditoria DESC
 LIMIT 15;"
pausa
}


# 6. Comprobar las tablas: CHECK TABLE revisa que los datos y los índices de
#    cada una estén sanos (después de un corte de luz, por ejemplo)
comprobar_tablas(){
clear
echo "===== COMPROBACIÓN DE LAS TABLAS ====="
tablas=$(valor "SELECT table_name FROM information_schema.TABLES WHERE table_schema = '$base' AND table_type = 'BASE TABLE' ORDER BY table_name")
lista=""
for tabla in $tablas
do
    if [ -n "$lista" ]; then
        lista="$lista, "
    fi
    lista="$lista\`$tabla\`"
done
# Un renglón por tabla: "sgdm.usuario: OK". Si alguna no da OK, el mensaje
# de MariaDB dice qué tiene.
valor "CHECK TABLE $lista;" | awk -F'\t' '$3 == "status" || $3 == "error" { print $1 ": " $4 }'
malas=$(valor "CHECK TABLE $lista;" | awk -F'\t' '$3 == "status" && $4 != "OK" || $3 == "error"' | wc -l)
echo
if [ "$malas" -eq 0 ]; then
    echo "Todas las tablas están sanas."
else
    echo "ATENCIÓN: $malas tabla(s) con problemas. Restaurar desde el último respaldo (docs/respaldos-cron.md)."
fi
pausa
}


# 7. Una lectura en metricas/bd.csv. Es lo que corre cron cada 5 minutos
#    (con --csv), y Grafana lo lee igual que metricas/metricas.csv.
guardar_lectura(){
mkdir -p "$(dirname "$csv")" || exit 6
if [ ! -f "$csv" ]; then
    echo "fecha_hora,conexiones,max_conexiones,consultas,consultas_lentas,conexiones_rechazadas,tamano_kb,cuentas_activas,torneos_en_curso,partidos_en_vivo,logins_fallidos_1h" > "$csv"
fi
linea=$(valor "
SELECT CONCAT_WS(',',
  DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'),
  (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'THREADS_CONNECTED'),
  (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'MAX_USED_CONNECTIONS'),
  (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'QUESTIONS'),
  (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'SLOW_QUERIES'),
  (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'ABORTED_CONNECTS'),
  (SELECT ROUND(SUM(data_length + index_length) / 1024) FROM information_schema.TABLES WHERE table_schema = '$base'),
  (SELECT COUNT(*) FROM usuario WHERE activo = 1 AND de_muestra = 0),
  (SELECT COUNT(*) FROM torneo WHERE estado = 'en_curso'),
  (SELECT COUNT(*) FROM enfrentamiento WHERE estado = 'en_vivo'),
  (SELECT COUNT(*) FROM auditoria WHERE accion = 'login_error' AND fecha_hora >= NOW() - INTERVAL 1 HOUR))" 2>&1)
# Si la base no contesta, se anota la hora con las columnas vacías: un hueco
# en el gráfico dice más que una línea que se corta sin aviso.
if echo "$linea" | grep -Eq "^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9:]{8}(,[0-9]+){10}$"; then
    echo "$linea" >> "$csv"
else
    echo "$(date '+%Y-%m-%d %H:%M:%S'),,,,,,,,,," >> "$csv"
    echo "La base no contesta: $linea" >&2
    return 7
fi
}


# Sin menú, para cron: una lectura y listo
if [ "${1:-}" = "--csv" ]; then
    comprobar
    guardar_lectura
    exit $?
elif [ -n "${1:-}" ]; then
    echo "Uso: $0 [--csv]"
    exit 2
fi


# Menú principal: se repite hasta que se elige 0
comprobar
opcion=""
while [ "$opcion" != "0" ]
do
    clear
    echo "===== AGÓN - CONSULTAS Y MONITOREO DE LA BASE DE DATOS ====="
    echo "1- Estado del servidor de base de datos"
    echo "2- Tamaño de la base y de cada tabla"
    echo "3- Registros por tabla"
    echo "4- Actividad del sistema"
    echo "5- Seguridad: inicios de sesión y auditoría"
    echo "6- Comprobar las tablas"
    echo "7- Guardar una lectura en metricas/bd.csv"
    echo "0- Salir"
    read -r -p "Seleccione una opción: " opcion
    case $opcion in
    1) estado_servidor;;
    2) tamano_tablas;;
    3) registros_por_tabla;;
    4) actividad;;
    5) seguridad;;
    6) comprobar_tablas;;
    7) if guardar_lectura; then echo "Lectura guardada en $csv:"; tail -1 "$csv"; fi; pausa;;
    0) echo "Saliendo...";;
    *) echo "Opción no válida."; sleep 2;;
    esac
done
