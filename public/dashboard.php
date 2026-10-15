<?php
/**
 * SpaceFlow — Dashboard administrativo.
 *
 * Protegido por sesión; las estadísticas reales se cargan desde MySQL
 * mediante DashboardController.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\DashboardController;
use App\Middleware\Auth;

Auth::requerirSesion();
DashboardController::mostrar();
