<?php
/**
 * SpaceFlow — Registro de actividad. Solo administradores.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\LogController;

LogController::mostrar();
