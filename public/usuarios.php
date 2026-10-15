<?php
/**
 * SpaceFlow — Listado de usuarios (SGU). Solo administradores.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\UsuarioController;
use App\Middleware\Auth;

Auth::requerirAdmin();
UsuarioController::listar();
