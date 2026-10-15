<?php
/**
 * SpaceFlow — Bootstrap de la aplicación.
 *
 * Cada página pública de public/ comienza requiriendo este archivo:
 * carga la configuración, el autoloader de clases, la sesión segura
 * y los helpers comunes. Centralizarlo aquí evita duplicar código.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

/* Configuración */
require_once __DIR__ . '/config/config.php';

/* Configuración de sesiones seguras (debe ir antes de session_start) */
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('spaceflow_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,                 // No accesible desde JavaScript.
    'samesite' => 'Lax',                // Mitiga CSRF junto al token propio.
    'secure'   => false,                // true si se sirve por HTTPS.
]);
session_start();

date_default_timezone_set(ZONA_HORARIA);

/**
 * Autoload PSR-4 simplificado para el espacio de nombres App\.
 *
 * App\Models\Usuario -> app/models/Usuario.php
 */
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'App\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $relativo = substr($clase, strlen($prefijo));
    $archivo  = __DIR__ . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $relativo) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

/* Helpers de presentación, URLs, CSRF y mensajes flash */
require_once __DIR__ . '/helpers/vistas.php';

/* ------------------------------------------------------------------
 * Manejador global de excepciones.
 *
 * Cualquier excepción no capturada se registra en el log como ERROR
 * y se muestra una página amigable: jamás se filtran mensajes crudos
 * de PHP ni detalles de la base de datos.
 * ------------------------------------------------------------------ */
set_exception_handler(static function (\Throwable $excepcion): void {
    try {
        $logger = \App\Services\Logger::porDefecto();
        $logger->error(sprintf(
            'Excepción no controlada (%s): %s en %s:%d',
            $excepcion::class,
            $excepcion->getMessage(),
            $excepcion->getFile(),
            $excepcion->getLine()
        ));
    } catch (\Throwable) {
        // Si ni siquiera hay log disponible, se continúa igualmente.
    }

    http_response_code(500);
    $titulo = 'Algo salió mal';
    $mensaje = 'Ocurrió un error inesperado. El incidente quedó registrado '
             . 'en el log del sistema; intenta de nuevo en unos minutos.';
    require __DIR__ . '/views/errores/denegado.php';
    exit;
});
