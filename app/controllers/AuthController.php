<?php
/**
 * SpaceFlow — Controlador de autenticación.
 *
 * Procesa el formulario de inicio de sesión usando AuthService y
 * traduce las excepciones en mensajes amigables para el usuario.
 *
 * @package SpaceFlow\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Exceptions\CredencialesInvalidasException;
use App\Exceptions\ConexionException;
use App\Middleware\Csrf;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use App\Services\Logger;

class AuthController
{
    /**
     * Procesa el POST del formulario de login.
     *
     * @return void
     */
    public static function iniciarSesion(): void
    {
        Csrf::validar();

        $correo = strtolower(trim((string)($_POST['correo'] ?? '')));
        $clave  = (string)($_POST['clave'] ?? '');

        try {
            $servicio = new AuthService(
                new UsuarioRepository(Database::conexion()),
                Logger::porDefecto()
            );
            $servicio->iniciarSesion($correo, $clave);
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } catch (CredencialesInvalidasException $e) {
            // Mensaje controlado (cuenta inactiva o bloqueo); el genérico
            // no revela si el correo existe o no.
            $_SESSION['login_error'] = $e->getMessage();
        } catch (ConexionException $e) {
            Logger::porDefecto()->error(
                'Falló la conexión al iniciar sesión: ' . $e->getMessage()
                . ($e->getPrevious() !== null ? ' | Detalle: ' . $e->getPrevious()->getMessage() : '')
            );
            $_SESSION['login_error'] = 'El servicio no está disponible en este momento. Intenta más tarde.';
        }
    }
}
