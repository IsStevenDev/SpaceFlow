<?php
/**
 * SpaceFlow — Punto de entrada: inicio de sesión.
 *
 * GET  muestra el formulario.
 * POST procesa las credenciales mediante AuthController.
 *
 * @package SpaceFlow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AuthController;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthController::iniciarSesion();
}

/* Si ya tiene sesión, no necesita volver a ver el login. */
if (!empty($_SESSION['usuario'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

/* Mensaje de error amigable (se limpia tras mostrarse). */
$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);

/* Correo recordado tras un intento fallido. */
$correoRecordado = $_SESSION['login_correo'] ?? '';

/* Aviso flash generado al cerrar sesión o al proteger una página. */
$mensajes = flash_obtener();

vista('auth/login', [
    'error'           => $error ?: ($mensajes[0]['mensaje'] ?? null),
    'tipoError'       => $error ? 'error' : ($mensajes[0]['tipo'] ?? 'error'),
    'correoRecordado' => $correoRecordado,
]);
