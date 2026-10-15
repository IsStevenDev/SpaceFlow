<?php
/**
 * SpaceFlow — Creación de usuarios. Solo administradores.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\UsuarioController;
use App\Middleware\Auth;

Auth::requerirAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    UsuarioController::crear();
}

UsuarioController::formularioCrear();
