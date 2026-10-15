<?php
/**
 * SpaceFlow — Eliminación (lógico) de usuarios. Solo administradores.
 * Solo acepta POST, para que no se pueda invocar desde un enlace.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\UsuarioController;
use App\Middleware\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/usuarios.php');
    exit;
}

Auth::requerirAdmin();
UsuarioController::eliminar();
