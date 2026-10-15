<?php
/**
 * SpaceFlow — Registro de devolución de equipo. Solo administradores.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\ReservaController;
use App\Middleware\Auth;

Auth::requerirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/reservas.php');
    exit;
}

ReservaController::devolver();
