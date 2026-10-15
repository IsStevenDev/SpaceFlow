<?php
/**
 * SpaceFlow — Controlador del perfil personal.
 *
 * Permite a cualquier usuario autenticado consultar y actualizar su
 * propia información. A diferencia del módulo administrativo, aquí no
 * se pueden modificar rol ni estado.
 *
 * @package SpaceFlow\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Exceptions\ConexionException;
use App\Exceptions\UsuarioNoEncontradoException;
use App\Exceptions\ValidacionException;
use App\Middleware\Csrf;
use App\Repositories\ProgramaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\Logger;
use App\Services\UsuarioService;

class PerfilController
{
    /**
     * Muestra el formulario del perfil propio (GET).
     *
     * @return void
     */
    public static function mostrar(): void
    {
        $id = (int)($_SESSION['usuario']['id'] ?? 0);
        try {
            $servicio = new UsuarioService(
                new UsuarioRepository(Database::conexion()),
                Logger::porDefecto()
            );
            $usuario = $servicio->obtener($id);

            $programas = (new ProgramaRepository(Database::conexion()))->listarTodos();

            pagina('perfil/index', [
                'usuario'   => $usuario,
                'programas' => $programas,
            ], 'Mi perfil', 'Actualiza tu información personal', 'perfil');
        } catch (UsuarioNoEncontradoException) {
            // La sesión apunta a una cuenta borrada: se cierra.
            session_destroy();
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al abrir el perfil: ' . $e->getMessage());
            http_response_code(403);
            $titulo = 'Servicio no disponible';
            $mensaje = 'No fue posible conectar con la base de datos. Intenta más tarde.';
            require dirname(__DIR__) . '/views/errores/denegado.php';
            exit;
        }
    }

    /**
     * Procesa la actualización del perfil propio (POST).
     *
     * @return void
     */
    public static function actualizar(): void
    {
        Csrf::validar();
        $id = (int)($_SESSION['usuario']['id'] ?? 0);

        try {
            $servicio = new UsuarioService(
                new UsuarioRepository(Database::conexion()),
                Logger::porDefecto()
            );
            // El id SIEMPRE sale de la sesión: un usuario estándar jamás
            // puede editar a otra persona alterando la URL.
            $servicio->actualizarPerfilPropio($id, $_POST);
            flash('exito', 'Perfil actualizado correctamente.');
        } catch (ValidacionException $e) {
            foreach ($e->obtenerErrores() as $error) {
                flash('error', $error);
            }
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al actualizar perfil: ' . $e->getMessage());
            flash('error', 'No fue posible guardar los cambios. Intenta más tarde.');
        }

        header('Location: ' . BASE_URL . '/perfil.php');
        exit;
    }
}
