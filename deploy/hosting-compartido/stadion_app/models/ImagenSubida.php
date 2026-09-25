<?php
# =====================================================================
# Modelo: ImagenSubida
# Proyecto SGDM - Stadion (Agon) - Lucas Martiarena
# ---------------------------------------------------------------------
# Una imagen que llega por formulario (foto de perfil o portada). Decide
# si se acepta y, si se acepta, la guarda con un nombre nuevo.
#
# Por que cada precaucion:
#   - El tipo se averigua mirando el contenido con finfo, no por la
#     extension ni por el tipo que declara el navegador: los dos los
#     escribe quien sube el archivo y los dos se pueden falsear. Un
#     "foto.jpg" que por dentro es PHP se rechaza.
#   - Ademas getimagesize() tiene que poder leerla como imagen y dar el
#     mismo tipo. Si no, no es una imagen de verdad.
#   - Solo JPG, PNG y WEBP. No GIF (no hace falta) ni SVG, que es texto
#     y puede llevar scripts adentro.
#   - Hasta 2 MB: sobra para una foto de perfil o una portada, y no deja
#     llenar el disco del hosting con archivos enormes.
#   - Hasta 4000 pixeles de lado: una camara de telefono saca menos que
#     eso, y una imagen mas grande es sospechosa o un error.
#   - El nombre lo pone el sistema: 32 caracteres al azar mas la
#     extension que corresponde al tipo detectado. El nombre original
#     nunca se usa, ni para guardar ni para mostrar: podria traer
#     "../", espacios, o terminar en .php.
#
# La carpeta donde se guarda, ademas, no ejecuta nada (ver el .htaccess
# de public/subidas/). Son dos defensas independientes: si una fallara,
# queda la otra.
# =====================================================================

class ImagenSubida
{
    const TAMANO_MAXIMO = 2097152;   # 2 MB, en bytes
    const LADO_MAXIMO   = 4000;      # pixeles

    #region ATRIBUTOS
    private $archivo;     # la entrada de $_FILES, o null
    private $tipo;        # tipo detectado por finfo, si valida
    private $ancho;
    private $alto;
    #endregion

    #region FUNCIONES

    public function __construct($archivo)
    {
        $this->archivo = is_array($archivo) ? $archivo : null;
        $this->tipo    = null;
        $this->ancho   = 0;
        $this->alto    = 0;
    }

    public function getTipo()  { return $this->tipo; }
    public function getAncho() { return $this->ancho; }
    public function getAlto()  { return $this->alto; }

    # Los tres tipos aceptados y la extension que le toca a cada uno.
    public static function tiposAceptados()
    {
        return array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
    }

    # Si un nombre tiene exactamente la forma de los que genera el
    # sistema. Se usa antes de borrar o mostrar un archivo, aunque el
    # nombre venga de la base: la base tiene el mismo control
    # (ck_usuario_foto), pero no cuesta nada mirar dos veces.
    public static function nombreValido($nombre)
    {
        return is_string($nombre)
            && preg_match('/^[0-9a-f]{32}\.(jpg|png|webp)$/', $nombre) === 1;
    }

    public function validar()
    {
        $errores = array();
        $aviso_tipo   = 'Solo se aceptan imagenes JPG, PNG o WEBP.';
        $aviso_tamano = 'La imagen supera los 2 MB.';

        if ($this->archivo === null || !isset($this->archivo['error'])
            || $this->archivo['error'] === UPLOAD_ERR_NO_FILE) {
            $errores[] = 'Falta elegir una imagen.';
            return $errores;
        }

        # El tamano lo corta el propio PHP antes de llegar aca si pasa el
        # limite de su configuracion; se avisa igual que el limite propio.
        if ($this->archivo['error'] === UPLOAD_ERR_INI_SIZE
            || $this->archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
            $errores[] = $aviso_tamano;
            return $errores;
        }

        if ($this->archivo['error'] !== UPLOAD_ERR_OK
            || !is_uploaded_file($this->archivo['tmp_name'])) {
            $errores[] = 'La imagen no llega entera. Conviene volver a intentarlo.';
            return $errores;
        }

        if ($this->archivo['size'] > self::TAMANO_MAXIMO
            || filesize($this->archivo['tmp_name']) > self::TAMANO_MAXIMO) {
            $errores[] = $aviso_tamano;
            return $errores;
        }

        # El tipo, mirando el contenido del archivo.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $tipo  = $finfo->file($this->archivo['tmp_name']);
        $aceptados = self::tiposAceptados();
        if (!isset($aceptados[$tipo])) {
            $errores[] = $aviso_tipo;
            return $errores;
        }

        # Que ademas se deje leer como imagen, y del mismo tipo.
        $medidas = @getimagesize($this->archivo['tmp_name']);
        if ($medidas === false || $medidas['mime'] !== $tipo
            || $medidas[0] < 1 || $medidas[1] < 1) {
            $errores[] = $aviso_tipo;
            return $errores;
        }

        if ($medidas[0] > self::LADO_MAXIMO || $medidas[1] > self::LADO_MAXIMO) {
            $errores[] = 'La imagen supera los ' . self::LADO_MAXIMO . ' pixeles de lado.';
            return $errores;
        }

        $this->tipo  = $tipo;
        $this->ancho = $medidas[0];
        $this->alto  = $medidas[1];
        return $errores;
    }

    # Guarda la imagen ya validada en $carpeta con un nombre nuevo.
    # Devuelve ese nombre, o null si no se pudo guardar.
    public function guardarEn($carpeta)
    {
        if ($this->tipo === null || !is_dir($carpeta) || !is_writable($carpeta)) {
            return null;
        }

        $aceptados = self::tiposAceptados();
        $nombre  = bin2hex(random_bytes(16)) . '.' . $aceptados[$this->tipo];
        $destino = rtrim($carpeta, '/\\') . '/' . $nombre;

        if (!move_uploaded_file($this->archivo['tmp_name'], $destino)) {
            return null;
        }
        # Lectura para todos, escritura solo para el dueno. Nadie la
        # ejecuta: una imagen no tiene por que.
        @chmod($destino, 0644);
        return $nombre;
    }

    #endregion
}
