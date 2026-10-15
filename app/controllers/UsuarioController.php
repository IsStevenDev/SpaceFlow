<?php
/**
 * SpaceFlow — Controlador del módulo de usuarios (SGU).
 *
 * Coordina el listado, la creación, la consulta, la edición y la
 * eliminación. La lógica de negocio vive en UsuarioService; aquí solo
 * se orquesta y se traducen excepciones en mensajes de interfaz.
 *
 * @package SpaceFlow\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Exceptions\AccesoDenegadoException;
use App\Exceptions\ConexionException;
use App\Exceptions\UsuarioNoEncontradoException;
use App\Exceptions\ValidacionException;
use App\Middleware\Csrf;
use App\Repositories\ProgramaRepository;
use App\Repositories\RolRepository;
use App\Repositories\UsuarioRepository;
use App\Services\Logger;
use App\Services\UsuarioService;

class UsuarioController
{
    /** Número de usuarios por página del listado. */
    private const POR_PAGINA = 10;

    /* ---------------------------------------------------------------
     * Composición de dependencias (simple "contenedor" de fábrica).
     * --------------------------------------------------------------- */

    private static function servicio(): UsuarioService
    {
        return new UsuarioService(
            new UsuarioRepository(Database::conexion()),
            Logger::porDefecto()
        );
    }

    private static function repositorio(): UsuarioRepository
    {
        return new UsuarioRepository(Database::conexion());
    }

    /* ---------------------------------------------------------------
     * Acciones
     * --------------------------------------------------------------- */

    /**
     * Listado con búsqueda, filtros y paginación.
     *
     * @return void
     */
    public static function listar(): void
    {
        $busqueda    = trim((string)($_GET['busqueda'] ?? ''));
        $filtroRol   = (string)($_GET['rol'] ?? '');
        $filtroEstado = (string)($_GET['estado'] ?? '');
        $pagina      = max(1, (int)($_GET['pagina'] ?? 1));

        $rolId = $filtroRol === '' ? null : (int)$filtroRol;

        try {
            $resultado = self::servicio()->listarParaAdministrador(
                $busqueda,
                $rolId,
                in_array($filtroEstado, ['', 'activo', 'inactivo'], true) ? $filtroEstado : '',
                $pagina
            );

            $roles = (new RolRepository(Database::conexion()))->listarTodos();

            $totalPaginas = max(1, (int)ceil($resultado['total'] / self::POR_PAGINA));
            // Página ya acotada al rango válido por el servicio.
            $pagina = $resultado['pagina'];

            pagina('usuarios/listar', [
                'usuarios'     => $resultado['usuarios'],
                'roles'        => $roles,
                'busqueda'     => $busqueda,
                'filtroRol'    => $filtroRol,
                'filtroEstado' => $filtroEstado,
                'pagina'       => $pagina,
                'totalPaginas' => $totalPaginas,
                'total'        => $resultado['total'],
            ], 'Usuarios', 'Gestión de cuentas de la comunidad', 'usuarios');
        } catch (AccesoDenegadoException $e) {
            self::mostrarDenegado($e->getMessage());
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al listar usuarios: ' . $e->getMessage());
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
            // Comprueba permiso antes de mostrar nada.
            self::servicio()->estadisticas();
            pagina('usuarios/crear', self::datosFormulario([], 'crear'),
                'Crear usuario', 'Registro de una cuenta institucional', 'usuarios');
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
            flash('exito', 'Usuario creado correctamente.');
            header('Location: ' . BASE_URL . '/usuarios.php');
            exit;
        } catch (ValidacionException | UsuarioNoEncontradoException | AccesoDenegadoException $e) {
            $errores = $e instanceof ValidacionException ? $e->obtenerErrores() : [$e->getMessage()];
            foreach ($errores as $error) {
                flash('error', $error);
            }
            header('Location: ' . BASE_URL . '/usuario_nuevo.php');
            exit;
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al crear usuario: ' . $e->getMessage());
            flash('error', 'No fue posible guardar el usuario. Intenta más tarde.');
            header('Location: ' . BASE_URL . '/usuario_nuevo.php');
            exit;
        }
    }

    /**
     * Muestra el detalle de un usuario (GET).
     *
     * @return void
     */
    public static function ver(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        try {
            // La validación de permiso y existencia ocurre en el servicio.
            $usuario = self::servicio()->obtener($id);
            self::servicio()->estadisticas(); // Verifica rol admin.

            pagina('usuarios/ver', [
                'usuario' => $usuario,
            ], 'Detalle de usuario', 'Consulta de la información registrada', 'usuarios');
        } catch (UsuarioNoEncontradoException) {
            flash('error', 'El usuario solicitado no existe.');
            header('Location: ' . BASE_URL . '/usuarios.php');
            exit;
        } catch (AccesoDenegadoException $e) {
            self::mostrarDenegado($e->getMessage());
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión: ' . $e->getMessage());
            self::mostrarDenegado('No fue posible conectar con la base de datos.');
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
            $usuario = self::servicio()->obtener($id);
            self::servicio()->estadisticas();

            pagina('usuarios/editar', self::datosFormulario($usuario, 'editar'),
                'Editar usuario', 'Modificación de la información de la cuenta', 'usuarios');
        } catch (UsuarioNoEncontradoException) {
            flash('error', 'El usuario solicitado no existe.');
            header('Location: ' . BASE_URL . '/usuarios.php');
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
            flash('exito', 'Usuario actualizado correctamente.');
            header('Location: ' . BASE_URL . '/usuarios.php');
            exit;
        } catch (ValidacionException | UsuarioNoEncontradoException | AccesoDenegadoException $e) {
            $errores = $e instanceof ValidacionException ? $e->obtenerErrores() : [$e->getMessage()];
            foreach ($errores as $error) {
                flash('error', $error);
            }
            header('Location: ' . BASE_URL . '/usuario_editar.php?id=' . $id);
            exit;
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al editar usuario: ' . $e->getMessage());
            flash('error', 'No fue posible actualizar el usuario. Intenta más tarde.');
            header('Location: ' . BASE_URL . '/usuario_editar.php?id=' . $id);
            exit;
        }
    }

    /**
     * Procesa la eliminación lógica (POST).
     *
     * @return void
     */
    public static function eliminar(): void
    {
        Csrf::validar();
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        try {
            self::servicio()->eliminar($id);
            flash('exito', 'Usuario desactivado. Ya no podrá iniciar sesión.');
        } catch (UsuarioNoEncontradoException) {
            flash('error', 'El usuario solicitado no existe.');
        } catch (ValidacionException | AccesoDenegadoException $e) {
            flash('error', $e->getMessage());
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al eliminar usuario: ' . $e->getMessage());
            flash('error', 'No fue posible desactivar el usuario. Intenta más tarde.');
        }
        header('Location: ' . BASE_URL . '/usuarios.php');
        exit;
    }

    /* ---------------------------------------------------------------
     * Auxiliares privados
     * --------------------------------------------------------------- */

    /**
     * Prepara los datos comunes de los formularios (crear/editar).
     *
     * @param array<string, mixed> $usuario Datos previos o [].
     * @param string $accion 'crear' | 'editar'.
     * @return array<string, mixed>
     */
    private static function datosFormulario(array $usuario, string $accion): array
    {
        $conexion = Database::conexion();
        return [
            'roles'     => (new RolRepository($conexion))->listarTodos(),
            'programas' => (new ProgramaRepository($conexion))->listarTodos(),
            'usuario'   => $usuario,
            'accion'    => $accion,
        ];
    }

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
        // La vista denegado.php espera las variables $titulo y $mensaje.
        require dirname(__DIR__) . '/views/errores/denegado.php';
    }
}
