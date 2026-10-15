<?php
/**
 * SpaceFlow — Reservas de equipos (biblioteca). Ambos roles.
 *
 * El administrador ve todas las reservas; el usuario, solo las suyas.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\ReservaController;
use App\Middleware\Auth;

Auth::requerirSesion();
ReservaController::listar();
