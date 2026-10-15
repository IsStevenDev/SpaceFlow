<?php
/**
 * SpaceFlow — Cierre de sesión.
 *
 * Registra el evento en el log y destruye por completo la sesión.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Middleware\Csrf;
use App\Services\AuthService;
use App\Config\Database;
use App\Repositories\UsuarioRepository;
use App\Services\Logger;

Csrf::validar();

$servicio = new AuthService(
    new UsuarioRepository(Database::conexion()),
    Logger::porDefecto()
);
$servicio->cerrarSesion();

header('Location: ' . url('index.php'));
exit;
