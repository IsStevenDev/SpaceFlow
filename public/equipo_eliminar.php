<?php
/**
 * SpaceFlow — Eliminación de equipos. Solo administradores.
 *
 * Si el equipo ya tiene reservas registradas se da de baja en lugar
 * de borrarlo, para conservar el historial de préstamos.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\EquipoController;
use App\Middleware\Auth;

Auth::requerirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/equipos.php');
    exit;
}

EquipoController::eliminar();
