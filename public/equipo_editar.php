<?php
/**
 * SpaceFlow — Edición de equipos. Solo administradores.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\EquipoController;
use App\Middleware\Auth;

Auth::requerirAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    EquipoController::editar();
}

EquipoController::formularioEditar();
