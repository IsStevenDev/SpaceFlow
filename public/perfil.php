<?php
/**
 * SpaceFlow — Perfil personal. Accesible a cualquier usuario autenticado.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\PerfilController;
use App\Middleware\Auth;

Auth::requerirSesion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    PerfilController::actualizar();
}

PerfilController::mostrar();
