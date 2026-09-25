-- =====================================================================
-- SGDM - Stadion (Agon) - Lucas Martiarena
-- Migracion 004: la base entera a utf8mb4 (utf8mb4_unicode_ci)
-- ---------------------------------------------------------------------
-- Para las bases que quedaron en latin1. Paso en el hosting: para
-- importar schema.sql ahi se borro a mano el CREATE DATABASE (el
-- hosting no deja crear bases por SQL), y con el se fue la codificacion.
-- Las tablas no declaraban la suya, asi que tomaron la de la base, que
-- cPanel crea con la del servidor: latin1 (latin1_swedish_ci).
--
-- latin1 guarda las tildes y la enie, pero no un emoji ni ningun
-- caracter fuera del alfabeto de Europa occidental: con esos, guardar
-- el perfil falla. Esta migracion pasa la base y las 17 tablas a
-- utf8mb4, que guarda cualquier caracter, sin perder ni cambiar los
-- datos que ya estan. Hoy schema.sql declara la codificacion en cada
-- tabla, asi que una base nueva ya nace en utf8mb4 y no la necesita.
-- En una base que ya esta en utf8mb4 (la del XAMPP, si se creo con
-- schema.sql) no cambia nada, y correrla no hace dano.
--
-- ORDEN, en una base que viene de antes:
--   1. 003_pedidos_de_rol.sql (si todavia no se corrio)
--   2. esta, la 004
--   3. sql/primer_administrador.sql (si todavia no hay administrador)
-- La 004 necesita las 17 tablas, asi que la 003 va antes: sin ella,
-- esta frena en la primera consulta sin cambiar nada.
--
-- ANTES DE CORRERLA: un respaldo. En phpMyAdmin, con la base elegida,
-- Export -> Quick -> SQL -> Go. Hay cuentas reales adentro.
--
-- Como se corre, a mano, una vez en cada base (igual que las otras):
--   1. En phpMyAdmin, elegir la base en la lista de la izquierda (sgdm en
--      XAMPP, la del hosting con su prefijo). Sin USE, a proposito: el
--      nombre de la base no es el mismo en los dos lugares.
--   2. Pestana SQL (o Import), pegar o subir este archivo, y Go.
--   3. Con la cuenta con la que se administra la base (root en XAMPP, la
--      cuenta de cPanel en el hosting). sgdm_app no alcanza: solo tiene
--      permisos sobre los datos, no sobre la estructura.
--
-- Que hace, en orden:
--   1. Comprueba que esten las 17 tablas (que la 003 ya corrio).
--   2. Lista los valores que chocarian en un indice unico al convertir
--      (ver "Indices unicos", abajo) y FRENA si hay alguno, antes de
--      cambiar nada.
--   3. Pasa la codificacion por defecto de la base a utf8mb4.
--   4. Convierte las 17 tablas, una por una, con sus datos.
--   5. Muestra el resultado, para comparar con los numeros de abajo.
--
-- Que esperar:
--   - La primera consulta muestra cuantos pedidos de rol hay (cualquier
--     numero, incluido 0). Si da "Table ... pedido_rol doesn't exist",
--     falta la 003: correrla y repetir esta. No se cambio nada.
--   - La segunda consulta lista los valores que chocarian. Lo normal es
--     que no devuelva ninguna fila. Si devuelve alguna, la migracion
--     frena en el paso siguiente con "CONSTRAINT ck_004_sin_choques
--     failed" y no cambia nada. Cuando algo da error, phpMyAdmin muestra
--     SOLO el error, no los resultados de antes: para ver los valores,
--     correr sola la consulta del paso 2a, mas abajo (la que empieza con
--     SELECT 'rol.nombre', hasta el primer punto y coma). Despues,
--     renombrar uno de los dos valores de cada choque (por ejemplo en
--     phpMyAdmin, Edit) y volver a correr el archivo entero.
--   - La ultima consulta tiene que mostrar:
--         base                       utf8mb4_unicode_ci
--         tablas_utf8mb4             17
--         tablas_en_otra             0
--         columnas_en_otra           0
--         restricciones_check        28
--         claves_foraneas            25
--         indices_unicos             14
--     que son los mismos numeros de una base nueva creada con
--     schema.sql. Si alguno difiere, NO seguir: avisar.
--   - Correrla dos veces no hace dano: la segunda vez vuelve a escribir
--     las tablas igual que estan, y la ultima consulta da lo mismo.
--
-- Para mirar una tabla por separado: SHOW CREATE TABLE usuario; (en
-- phpMyAdmin, con Extra options -> Full texts, o sale cortado). La
-- ultima linea tiene que terminar en
--     DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- (en una tabla con filas, antes aparece AUTO_INCREMENT=N, que no
-- importa). La pestana Structure de phpMyAdmin tambien lo muestra, en
-- la columna Collation de cada tabla.
-- -------------------------------------------------------------------
-- Por que es segura para los datos
-- -------------------------------------------------------------------
-- Datos: CONVERT TO CHARACTER SET no copia los bytes tal cual: traduce
-- cada caracter de latin1 al mismo caracter en utf8mb4. Todo caracter de
-- latin1 existe en utf8mb4, asi que no se pierde ninguno. Los datos
-- estan bien guardados en latin1 porque la aplicacion siempre se conecta
-- en utf8mb4 (set_charset en apps/config/database.php) y el servidor
-- traduce al guardar. Lo que ya se hubiera guardado como "?" (un emoji,
-- con la base en latin1) no se puede recuperar: esta migracion conserva
-- lo que hay, no lo que no llego a guardarse.
--
-- Claves foraneas: las 25 son entre columnas numericas (INT UNSIGNED),
-- ninguna es de texto. La codificacion no les cambia nada, asi que las
-- tablas se pueden convertir en cualquier orden, y no hace falta apagar
-- FOREIGN_KEY_CHECKS (mejor no apagarlas: siguen cuidando los datos
-- mientras se convierte).
--
-- Indices unicos: dos cosas.
--   * El largo. En utf8mb4 cada caracter puede ocupar 4 bytes, y un
--     indice de InnoDB tiene un limite: 3072 bytes con el formato de
--     fila por defecto (DYNAMIC), 767 con el viejo (COMPACT). El indice
--     de texto mas largo es el de usuario.correo: 120 caracteres x 4 =
--     480 bytes, debajo de los dos limites. Los otros: torneo.nombre
--     80 x 4 = 320 (+ la fecha), equipo y disciplina 160, rol,
--     tipo_torneo y modulo_competencia 120, usuario.alias 80.
--   * Los choques. utf8mb4_unicode_ci compara distinto que
--     latin1_swedish_ci: por ejemplo trata ue con dieresis igual que u,
--     a con dieresis igual que a, y la ese alemana igual que "ss", y
--     latin1_swedish_ci no. Dos valores que hoy conviven en un indice
--     unico (un equipo "Muller" y otro con dieresis) serian el mismo
--     despues, y el ALTER de esa tabla fallaria a mitad de camino. Por
--     eso el paso 2 los busca antes y frena sin tocar nada.
--
-- Restricciones CHECK: las 28 quedan. El ALTER las vuelve a comprobar
-- fila por fila al copiar la tabla, asi que si alguna fila no las
-- cumpliera, esa tabla no se convierte y queda como estaba. Las dos de
-- las fotos (ck_usuario_foto y ck_usuario_portada) usan BINARY para
-- distinguir mayusculas; los nombres de archivo son solo letras y
-- numeros comunes, que ocupan los mismos bytes en latin1 y en utf8mb4,
-- asi que siguen valiendo igual.
--
-- Columna calculada: pedido_rol.pendiente_de se vuelve a calcular al
-- copiar la tabla; da lo mismo que antes, porque sale de estado e
-- id_usuario, que no cambian.
--
-- Texto largo: configuracion_torneo.reglas es TEXT. CONVERT TO la
-- agrandaria sola a MEDIUMTEXT (en utf8mb4 un caracter puede ocupar 4
-- bytes); el MODIFY de esa tabla la deja en TEXT, igual que en
-- schema.sql, para que la base convertida sea identica a una nueva.
--
-- Si algo falla a mitad de camino, la tabla que fallo queda como estaba
-- (cada ALTER es todo o nada), y las anteriores ya quedaron en utf8mb4.
-- Corregido el problema, se vuelve a correr el archivo entero.
--
-- Necesita MariaDB 10.2 o superior, igual que schema.sql. Probada en
-- MariaDB 10.11 (la de la VM) y 11.4 (la del hosting), por la consola y
-- a traves de phpMyAdmin 5.2.1 (pestanas SQL e Import).
-- =====================================================================


-- 1. Que la 003 ya este: si falta pedido_rol, esto da error y frena.
SELECT COUNT(*) AS pedidos_de_rol FROM pedido_rol;


-- 2a. Los valores que chocarian en un indice unico. Lo normal: ninguna
--     fila. Cada fila es un grupo de valores que hoy son distintos y en
--     utf8mb4_unicode_ci serian el mismo. Se puede correr sola, cuantas
--     veces haga falta: solo mira, no cambia nada.
SELECT 'rol.nombre' AS indice, GROUP_CONCAT(nombre SEPARATOR '  |  ') AS valores
  FROM rol
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'usuario.correo', GROUP_CONCAT(correo SEPARATOR '  |  ')
  FROM usuario
 GROUP BY CONVERT(correo USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'usuario.alias', GROUP_CONCAT(alias SEPARATOR '  |  ')
  FROM usuario WHERE alias IS NOT NULL
 GROUP BY CONVERT(alias USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'equipo.nombre', GROUP_CONCAT(nombre SEPARATOR '  |  ')
  FROM equipo
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'disciplina.nombre', GROUP_CONCAT(nombre SEPARATOR '  |  ')
  FROM disciplina
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'tipo_torneo.nombre', GROUP_CONCAT(nombre SEPARATOR '  |  ')
  FROM tipo_torneo
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'modulo_competencia.nombre', GROUP_CONCAT(nombre SEPARATOR '  |  ')
  FROM modulo_competencia
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1
UNION ALL
SELECT 'torneo.nombre + fecha_inicio', GROUP_CONCAT(nombre SEPARATOR '  |  ')
  FROM torneo
 GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci, fecha_inicio HAVING COUNT(*) > 1;


-- 2b. Y si hay alguno, se frena aca, antes de cambiar nada. La tabla
--     temporal solo acepta un 0: con cualquier otro numero el INSERT
--     falla, y phpMyAdmin (o la consola) deja de correr el archivo.
--     La tabla desaparece sola al cerrar la conexion.
CREATE TEMPORARY TABLE control_004 (
  valores_que_chocan INT NOT NULL,
  CONSTRAINT ck_004_sin_choques CHECK (valores_que_chocan = 0)
);

INSERT INTO control_004 (valores_que_chocan)
SELECT
    (SELECT COUNT(*) FROM (SELECT 1 FROM rol
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM usuario
        GROUP BY CONVERT(correo USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM usuario WHERE alias IS NOT NULL
        GROUP BY CONVERT(alias USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM equipo
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM disciplina
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM tipo_torneo
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM modulo_competencia
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1) c)
  + (SELECT COUNT(*) FROM (SELECT 1 FROM torneo
        GROUP BY CONVERT(nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci, fecha_inicio HAVING COUNT(*) > 1) c);

DROP TEMPORARY TABLE control_004;


-- 3. La codificacion por defecto de la base. Sin nombre: la que esta
--    elegida. Es la que toma una tabla nueva que no declare la suya.
ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- 4. Las 17 tablas, en el orden de schema.sql. Cada ALTER traduce los
--    datos de todas las columnas de texto de la tabla y cambia la
--    codificacion por defecto de la tabla.
ALTER TABLE rol                  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuario              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuario_rol          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE pedido_rol           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE equipo               CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE integrante_equipo    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE disciplina           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE tipo_torneo          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE modulo_competencia   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE torneo               CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- reglas vuelve a TEXT (ver "Texto largo", arriba).
ALTER TABLE configuracion_torneo CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                                 MODIFY reglas TEXT NULL;
ALTER TABLE participante         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE ronda                CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE enfrentamiento       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE resultado            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE tabla_posiciones     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE auditoria            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- 5. El resultado. Tiene que dar los numeros de la cabecera:
--    utf8mb4_unicode_ci, 17, 0, 0, 28, 25, 14.
SELECT
    (SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA
      WHERE SCHEMA_NAME = DATABASE())                                   AS base,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
        AND TABLE_COLLATION = 'utf8mb4_unicode_ci')                     AS tablas_utf8mb4,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
        AND TABLE_COLLATION <> 'utf8mb4_unicode_ci')                    AS tablas_en_otra,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND COLLATION_NAME IS NOT NULL
        AND COLLATION_NAME <> 'utf8mb4_unicode_ci')                     AS columnas_en_otra,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK')        AS restricciones_check,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY')  AS claves_foraneas,
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'UNIQUE')       AS indices_unicos;
