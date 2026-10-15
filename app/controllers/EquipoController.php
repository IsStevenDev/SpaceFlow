<?php
/**
 * SpaceFlow — Controlador del módulo de equipos (biblioteca).
 *
 * Coordina el catálogo de portátiles: listado con filtros, creación,
 * edición y eliminación (física o lógica según el historial). La lógica
 * vive en EquipoService; aquí solo se orquesta y se traducen las
 * excepciones en mensajes de interfaz.
 *
 * @package SpaceFlow\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Exceptions\AccesoDenegadoException;
use App\Exceptions\ConexionException;
use App\Exceptions\EquipoNoEncontradoException;
use App\Exceptions\ValidacionException;
use App\Middleware\Csrf;
use App\Repositories\EquipoRepository;
use App\Repositories\ReservaRepository;
use App\Services\EquipoService;
use App\Services\Logger;

class EquipoController
{
    /** Número de equipos por página del listado. */
    private const POR_PAGINA = 10;

    /* ---------------------------------------------------------------
     * Composición de dependencias.
     * --------------------------------------------------------------- */

    private static function servicio(): EquipoService
    {
        return new EquipoService(
            new EquipoRepository(Database::conexion()),
            new ReservaRepository(Database::conexion()),
            Logger::porDefecto()
        );
    }

    /* ---------------------------------------------------------------
     * Acciones
     * --------------------------------------------------------------- */

    /**
     * Listado con búsqueda, filtro de estado y paginación (admin).
     *
     * @return void
     */
    public static function listar(): void
    {
        $busqueda = trim((string)($_GET['busqueda'] ?? ''));
        $estado   = (string)($_GET['estado'] ?? '');
        $pagina   = max(1, (int)($_GET['pagina'] ?? 1));

        try {
            $resultado = self::servicio()->listarParaAdministrador(
                $busqueda,
                in_array($estado, ['', 'disponible', 'prestado', 'mantenimiento', 'baja'], true) ? $estado : '',
                $pagina
            );

            $totalPaginas = max(1, (int)ceil($resultado['total'] / self::POR_PAGINA));

            pagina('equipos/listar', [
                'equipos'      => $resultado['equipos'],
                'busqueda'     => $busqueda,
                'filtroEstado' => $estado,
                'pagina'       => $resultado['pagina'],
                'totalPaginas' => $totalPaginas,
                'total'        => $resultado['total'],
            ], 'Equipos', 'Catálogo de portátiles de la biblioteca', 'equipos');
        } catch (AccesoDenegadoException $e) {
            self::mostrarDenegado($e->getMessage());
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al listar equipos: ' . $e->getMessage());
            self::mostrarDenegado('No fue posible conectar con la base de datos. Intenta más tarde.');
        }
    }

    /**
     * Muestra el formulario de creación (GET).
     *
     * @return void
     */
    public static function formularioCrear(): void
    {
        try {
            self::servicio()->estadisticas(); // Comprueba permiso antes de mostrar nada.
            pagina('equipos/crear', ['equipo' => [], 'accion' => 'crear'],
                'Crear equipo', 'Registro de un portátil en el catálogo', 'equipos');
        } catch (AccesoDenegadoException $e) {
            self::mostrarDenegado($e->getMessage());
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión: ' . $e->getMessage());
            self::mostrarDenegado('No fue posible conectar con la base de datos.');
        }
    }

    /**
     * Procesa la creación (POST).
     *
     * @return void
     */
    public static function crear(): void
    {
        Csrf::validar();
        try {
            self::servicio()->crear($_POST);
            flash('exito', 'Equipo registrado correctamente.');
            header('Location: ' . BASE_URL . '/equipos.php');
            exit;
        } catch (ValidacionException | AccesoDenegadoException $e) {
            $errores = $e instanceof ValidacionException ? $e->obtenerErrores() : [$e->getMessage()];
            foreach ($errores as $error) {
                flash('error', $error);
            }
            header('Location: ' . BASE_URL . '/equipo_nuevo.php');
            exit;
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al crear equipo: ' . $e->getMessage());
            flash('error', 'No fue posible guardar el equipo. Intenta más tarde.');
            header('Location: ' . BASE_URL . '/equipo_nuevo.php');
            exit;
        }
    }

    /**
     * Muestra el formulario de edición (GET).
     *
     * @return void
     */
    public static function formularioEditar(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        try {
            $equipo = self::servicio()->obtener($id);
            self::servicio()->estadisticas(); // Verifica rol admin.

            pagina('equipos/editar', ['equipo' => $equipo, 'accion' => 'editar'],
                'Editar equipo', 'Modificación de la información del portátil', 'equipos');
        } catch (EquipoNoEncontradoException) {
            flash('error', 'El equipo solicitado no existe.');
            header('Location: ' . BASE_URL . '/equipos.php');
            exit;
        } catch (AccesoDenegadoException $e) {
            self::mostrarDenegado($e->getMessage());
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión: ' . $e->getMessage());
            self::mostrarDenegado('No fue posible conectar con la base de datos.');
        }
    }

    /**
     * Procesa la edición (POST).
     *
     * @return void
     */
    public static function editar(): void
    {
        Csrf::validar();
        $id = (int)($_POST['id'] ?? 0);
        try {
            self::servicio()->editar($id, $_POST);
            flash('exito', 'Equipo actualizado correctamente.');
            header('Location: ' . BASE_URL . '/equipos.php');
            exit;
        } catch (ValidacionException | EquipoNoEncontradoException | AccesoDenegadoException $e) {
            $errores = $e instanceof ValidacionException ? $e->obtenerErrores() : [$e->getMessage()];
            foreach ($errores as $error) {
                flash('error', $error);
            }
            header('Location: ' . BASE_URL . '/equipo_editar.php?id=' . $id);
            exit;
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al editar equipo: ' . $e->getMessage());
            flash('error', 'No fue posible actualizar el equipo. Intenta más tarde.');
            header('Location: ' . BASE_URL . '/equipo_editar.php?id=' . $id);
            exit;
        }
    }

    /**
     * Procesa la eliminación (POST): física si no tiene historial,
     * o baja lógica conservando las reservas.
     *
     * @return void
     */
    public static function eliminar(): void
    {
        Csrf::validar();
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        try {
            $resultado = self::servicio()->eliminar($id);
            flash(
                'exito',
                $resultado === 'baja'
                    ? 'El equipo tiene reservas registradas: se dio de baja conservando su historial.'
                    : 'Equipo eliminado del catálogo.'
            );
        } catch (EquipoNoEncontradoException) {
            flash('error', 'El equipo solicitado no existe.');
        } catch (ValidacionException | AccesoDenegadoException $e) {
            flash('error', $e->getMessage());
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al eliminar equipo: ' . $e->getMessage());
            flash('error', 'No fue posible eliminar el equipo. Intenta más tarde.');
        }
        header('Location: ' . BASE_URL . '/equipos.php');
        exit;
    }

    /* ---------------------------------------------------------------
     * Auxiliares privados
     * --------------------------------------------------------------- */

    /**
     * Muestra la página 403 con el mensaje indicado.
     *
     * @param string $mensaje Explicación amigable.
     * @return void
     */
    private static function mostrarDenegado(string $mensaje): void
    {
        http_response_code(403);
        $titulo = 'Acceso denegado';
        require dirname(__DIR__) . '/views/errores/denegado.php';
    }
}
