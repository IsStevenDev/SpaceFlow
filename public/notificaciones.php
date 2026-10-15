<?php
/**
 * SpaceFlow — Endpoint JSON de notificaciones de la biblioteca.
 *
 * Lo consultan el navegador cada 30 segundos para pintar la cuenta
 * regresiva del estudiante y el panel "Próximas a vencer" del
 * administrador. Exige sesión iniciada y responde solo con JSON.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\ReservaController;
use App\Middleware\Auth;

Auth::requerirSesion();
ReservaController::notificaciones();
