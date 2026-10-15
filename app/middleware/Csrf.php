<?php
/**
 * SpaceFlow — Middleware de protección CSRF.
 *
 * Todos los formularios que modifican datos envían un token generado
 * en la sesión; aquí se verifica antes de procesar cualquier POST.
 *
 * @package SpaceFlow\Middleware
 */

declare(strict_types=1);

namespace App\Middleware;

class Csrf
{
    /**
     * Valida el token del POST actual; responde 403 si no coincide.
     *
     * @return void
     */
    public static function validar(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || empty($_SESSION['csrf_token'])
            || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            $titulo = 'Solicitud no válida';
            $mensaje = 'La solicitud no superó la verificación de seguridad. '
                     . 'Vuelve atrás y vuelve a intentarlo.';
            require dirname(__DIR__) . '/views/errores/denegado.php';
            exit;
        }
    }
}
