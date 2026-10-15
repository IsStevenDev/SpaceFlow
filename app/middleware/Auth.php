<?php
/**
 * SpaceFlow — Middleware de autenticación y autorización.
 *
 * Métodos estáticos invocados al principio de cada página privada.
 * Ocultar botones es solo UX: la garantía real de seguridad es esta
 * comprobación hecha siempre en el servidor.
 *
 * @package SpaceFlow\Middleware
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Logger;

class Auth
{
    /**
     * Exige tener sesión iniciada; si no, redirige al login.
     *
     * @return void
     */
    public static function requerirSesion(): void
    {
        if (empty($_SESSION['usuario'])) {
            flash('aviso', 'Debes iniciar sesión para acceder al panel.');
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }

    /**
     * Exige el rol administrador; si no, registra el intento y muestra 403.
     *
     * @return void
     */
    public static function requerirAdmin(): void
    {
        self::requerirSesion();

        if (($_SESSION['usuario']['rol_id'] ?? 0) !== 1) {
            Logger::porDefecto()->advertencia(
                'Intento de acceso no autorizado: \''
                . ($_SESSION['usuario']['correo'] ?? 'invitado')
                . '\' intentó acceder a \'' . ($_SERVER['REQUEST_URI'] ?? '?') . '\'.'
            );
            http_response_code(403);
            $titulo = 'Acceso denegado';
            $mensaje = 'Tu rol no tiene permiso para ver esta sección. '
                     . 'Si crees que es un error, contacta al administrador.';
            require dirname(__DIR__) . '/views/errores/denegado.php';
            exit;
        }
    }

    /**
     * Comprueba si la sesión actual pertenece a un administrador.
     *
     * @return bool
     */
    public static function esAdmin(): bool
    {
        return ($_SESSION['usuario']['rol_id'] ?? 0) === 1;
    }
}
