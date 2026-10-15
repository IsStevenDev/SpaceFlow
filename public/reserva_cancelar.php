<?php
/**
 * SpaceFlow — Cancelación de reservas. Ambos roles.
 *
 * Un usuario solo puede cancelar sus propias reservas (se verifica
 * en el servicio, no en el formulario).
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\ReservaController;
use App\Middleware\Auth;

Auth::requerirSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/reservas.php');
    exit;
}

ReservaController::cancelar();
