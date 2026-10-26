<?php
# Modelo: ImagenSubida

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

    # Si un nombre tiene exactamente la forma de los que genera el sistema.
    public static function nombreValido($nombre)
    {
        return is_string($nombre)
            && preg_match('/^[0-9a-f]{32}\.(jpg|png|webp)$/', $nombre) === 1;
    }

    public function validar()
    {
        $errores = array();
        $aviso_tipo   = 'Solo se aceptan imágenes JPG, PNG o WEBP.';
        $aviso_tamano = 'La imagen supera los 2 MB.';

        if ($this->archivo === null || !isset($this->archivo['error'])
            || $this->archivo['error'] === UPLOAD_ERR_NO_FILE) {
            $errores[] = 'Falta elegir una imagen.';
            return $errores;
        }

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
            $errores[] = 'La imagen supera los ' . self::LADO_MAXIMO . ' píxeles de lado.';
            return $errores;
        }

        $this->tipo  = $tipo;
        $this->ancho = $medidas[0];
        $this->alto  = $medidas[1];
        return $errores;
    }

    # Guarda la imagen ya validada en $carpeta con un nombre nuevo.
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
        # Lectura para todos, escritura solo para el dueno.
        @chmod($destino, 0644);
        return $nombre;
    }

    #endregion
}
