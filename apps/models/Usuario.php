<?php
# =====================================================================
# Modelo: Usuario   ->   tabla "usuario" de sql/schema.sql
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Composicion: un Usuario contiene un arreglo de objetos Rol, que es la
# relacion N:M de la tabla intermedia "usuario_rol".
#
# La contrasena nunca se guarda en claro: entra por asignarClave(), que
# la pasa por password_hash(), y se comprueba con verificarClave(), que
# usa password_verify().
#
# La baja es logica (activo = 0), igual que en la base: el usuario de
# base de datos de la aplicacion no tiene permiso de DELETE sobre esta
# tabla, asi que darDeBaja() es la unica via.
#
# Una cuenta de muestra (de_muestra = 1) es una de las organizadoras de
# las ligas de muestra de sql/migraciones/005_ligas.sql. No inicia
# sesion nunca: el inicio de sesion la rechaza con esDeMuestra() antes
# de mirar la clave (y, ademas, su hash no lo acepta password_verify).
# =====================================================================

require_once __DIR__ . '/Rol.php';

class Usuario
{
    #region ATRIBUTOS
    private $id_usuario;
    private $correo;
    private $hash_password;
    private $nombre;
    private $apellido;
    private $alias;
    private $presentacion;
    private $activo;
    private $fecha_alta;
    private $foto_perfil;    # nombre del archivo, o null si no hay
    private $foto_portada;   # idem
    private $de_muestra;     # 1 si es una cuenta de muestra
    private $roles;          # arreglo de objetos Rol
    #endregion

    #region FUNCIONES

    public function __construct($id_usuario, $correo, $hash_password, $nombre, $apellido,
                                $alias = null, $presentacion = null, $activo = 1, $fecha_alta = null,
                                $foto_perfil = null, $foto_portada = null, $de_muestra = 0)
    {
        $this->id_usuario    = $id_usuario;
        $this->correo        = $correo;
        $this->hash_password = $hash_password;
        $this->nombre        = $nombre;
        $this->apellido      = $apellido;
        $this->alias         = $alias;
        $this->presentacion  = $presentacion;
        $this->activo        = (int)$activo;
        $this->fecha_alta    = $fecha_alta;
        $this->foto_perfil   = $foto_perfil;
        $this->foto_portada  = $foto_portada;
        $this->de_muestra    = (int)$de_muestra;
        $this->roles         = array();
    }

    public function getIdUsuario()   { return $this->id_usuario; }

    # Unico setter de la clase: sirve para guardar el id que genera
    # el AUTO_INCREMENT de la tabla despues de un INSERT.
    public function setIdUsuario($id_usuario)
    {
        $this->id_usuario = (int)$id_usuario;
    }

    public function getCorreo()      { return $this->correo; }
    public function getHashPassword(){ return $this->hash_password; }
    public function getNombre()      { return $this->nombre; }
    public function getApellido()    { return $this->apellido; }
    public function getAlias()       { return $this->alias; }
    public function getPresentacion(){ return $this->presentacion; }
    public function getActivo()      { return $this->activo; }
    public function getFechaAlta()   { return $this->fecha_alta; }
    public function getFotoPerfil()  { return $this->foto_perfil; }
    public function getFotoPortada() { return $this->foto_portada; }
    public function getRoles()       { return $this->roles; }

    # Equivale al CONCAT(nombre, ' ', apellido) de la consulta de
    # posiciones del schema.
    public function getNombreCompleto()
    {
        return $this->nombre . ' ' . $this->apellido;
    }

    # --- Nombres para mostrar ----------------------------------------
    # Lo que se guarda no se toca: si alguien se registra como "lucas",
    # en la base queda "lucas". Solo al mostrarlo sale "Lucas".
    #
    # La regla: si el texto esta entero en minuscula, se sube la primera
    # letra y nada mas. Si ya tiene alguna mayuscula, se respeta tal como
    # la persona lo escribio. Asi "lucas" pasa a "Lucas", pero "de Itaca"
    # (con la I mayuscula) queda como esta, con su "de" en minuscula: no
    # se convierte palabra por palabra.
    #
    # Con las funciones mb_, que cuentan caracteres y no bytes: con
    # strtoupper, una tilde o una enie quedarian sin convertir o rotas.
    public static function paraMostrar($texto)
    {
        $texto = (string)$texto;
        if ($texto === '' || $texto !== mb_strtolower($texto)) {
            return $texto;
        }
        return mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }

    public function getNombreVisible()   { return self::paraMostrar($this->nombre); }
    public function getApellidoVisible() { return self::paraMostrar($this->apellido); }

    public function getNombreCompletoVisible()
    {
        return trim($this->getNombreVisible() . ' ' . $this->getApellidoVisible());
    }

    public function estaActivo()
    {
        return $this->activo === 1;
    }

    public function esDeMuestra()
    {
        return $this->de_muestra === 1;
    }

    # Baja logica, para no perder el historial de enfrentamientos.
    public function darDeBaja()
    {
        $this->activo = 0;
    }

    # Toma la clave en claro, la valida y guarda solo el hash.
    # Devuelve un arreglo de errores; vacio significa que se asigno.
    #
    # La politica de contrasenas de Ciberseguridad (primera entrega),
    # desde la tercera entrega controlada por el sistema: 10 caracteres
    # o mas, con una mayuscula, una minuscula y un numero, y sin el
    # nombre, el apellido ni la parte del correo antes de la @ (cuando
    # tienen 3 letras o mas). Vale para toda clave nueva: el alta y el
    # cambio desde el perfil. Las cuentas que ya existian siguen
    # entrando con su clave de antes.
    public function asignarClave($clave_en_claro)
    {
        $errores = array();
        if (empty($clave_en_claro)) {
            $errores[] = 'La contraseña no puede quedar vacía.';
        } elseif (mb_strlen($clave_en_claro) < 10) {
            $errores[] = 'La contraseña necesita al menos 10 caracteres.';
        } else {
            if (!preg_match('/\p{Lu}/u', $clave_en_claro)
                || !preg_match('/\p{Ll}/u', $clave_en_claro)
                || !preg_match('/[0-9]/', $clave_en_claro)) {
                $errores[] = 'La contraseña lleva al menos una mayúscula, una minúscula y un número.';
            }
            if ($this->contieneDatosPropios($clave_en_claro)) {
                $errores[] = 'La contraseña no puede contener el nombre, el apellido ni el correo de la cuenta.';
            }
        }
        if (empty($errores)) {
            $this->hash_password = password_hash($clave_en_claro, PASSWORD_DEFAULT);
        }
        return $errores;
    }

    # true si la clave contiene, sin mirar mayusculas, el nombre, el
    # apellido o la parte del correo antes de la @ de esta cuenta. Los de
    # menos de 3 letras no cuentan: casi cualquier clave los tendria.
    private function contieneDatosPropios($clave_en_claro)
    {
        $clave = mb_strtolower($clave_en_claro, 'UTF-8');
        $propios = array($this->nombre, $this->apellido);
        if (!empty($this->correo) && mb_strpos($this->correo, '@') !== false) {
            $propios[] = mb_substr($this->correo, 0, mb_strpos($this->correo, '@'));
        }
        foreach ($propios as $dato) {
            $dato = mb_strtolower(trim((string)$dato), 'UTF-8');
            if (mb_strlen($dato) >= 3 && mb_strpos($clave, $dato) !== false) {
                return true;
            }
        }
        return false;
    }

    # Comprueba la clave del login contra el hash guardado.
    public function verificarClave($clave_en_claro)
    {
        if (empty($this->hash_password) || empty($clave_en_claro)) {
            return false;
        }
        return password_verify($clave_en_claro, $this->hash_password);
    }

    public function agregarRol(Rol $rol)
    {
        if (!$this->tieneRol($rol->getNombre())) {
            $this->roles[] = $rol;
        }
    }

    public function tieneRol($nombre_rol)
    {
        foreach ($this->roles as $rol) {
            if ($rol->getNombre() === $nombre_rol) {
                return true;
            }
        }
        return false;
    }

    # Las validaciones repiten las restricciones de la tabla, para
    # avisar con un mensaje claro antes de llegar a la base.
    public function validar()
    {
        $errores = array();

        if (empty($this->correo)) {
            $errores[] = 'El correo es obligatorio.';
        } elseif (mb_strlen($this->correo) > 120) {
            $errores[] = 'El correo no puede pasar de 120 caracteres.';
        } elseif (!filter_var($this->correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo no tiene un formato válido.';
        }

        if (empty($this->nombre) || mb_strlen($this->nombre) < 2 || mb_strlen($this->nombre) > 40) {
            $errores[] = 'El nombre tiene que tener entre 2 y 40 caracteres.';
        }

        if (empty($this->apellido) || mb_strlen($this->apellido) < 2 || mb_strlen($this->apellido) > 40) {
            $errores[] = 'El apellido tiene que tener entre 2 y 40 caracteres.';
        }

        # El alias es opcional, pero si viene tiene el mismo formato que
        # el pattern del formulario de perfil: letras, numeros y guion bajo.
        if (!empty($this->alias)) {
            if (mb_strlen($this->alias) > 20) {
                $errores[] = 'El alias no puede pasar de 20 caracteres.';
            } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $this->alias)) {
                $errores[] = 'El alias solo admite letras, números y guion bajo.';
            }
        }

        if (!empty($this->presentacion) && mb_strlen($this->presentacion) > 300) {
            $errores[] = 'La presentación no puede pasar de 300 caracteres.';
        }

        if (empty($this->hash_password)) {
            $errores[] = 'La cuenta no tiene contraseña asignada.';
        }

        if ($this->activo !== 0 && $this->activo !== 1) {
            $errores[] = 'Una cuenta está activa o de baja.';
        }

        return $errores;
    }

    #endregion
}
