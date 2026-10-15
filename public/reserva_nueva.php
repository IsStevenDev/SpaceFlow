<?php
/**
 * SpaceFlow — Nueva reserva de portátil. Ambos roles.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\ReservaController;
use App\Middleware\Auth;

Auth::requerirSesion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ReservaController::crear();
}

ReservaController::formularioCrear();
