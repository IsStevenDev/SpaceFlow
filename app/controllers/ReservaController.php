<?php
/**
 * SpaceFlow — Controlador del módulo de reservas (biblioteca).
 *
 * Coordina la creación de reservas (ambos roles), la cancelación
 * (el usuario la suya; el administrador, cualquiera), la entrega y
 * devolución (solo administrador) y el endpoint JSON que alimenta
 * las notificaciones en tiempo real del navegador.
 *
 * @package SpaceFlow\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Exceptions\AccesoDenegadoException;
use App\Exceptions\ConexionException;
use App\Exceptions\ReservaInvalidaException;
use App\Exceptions\SolapamientoException;
use App\Middleware\Auth;
use App\Middleware\Csrf;
use App\Repositories\EquipoRepository;
use App\Repositories\ReservaRepository;
use App\Services\Logger;
use App\Services\ReservaService;

class ReservaController
{
    /** Número de reservas por página del listado. */
    private const POR_PAGINA = 10;

    /* ---------------------------------------------------------------
     * Composición de dependencias.
     * --------------------------------------------------------------- */

    private static function servicio(): ReservaService
    {
        return new ReservaService(
            new ReservaRepository(Database::conexion()),
            new EquipoRepository(Database::conexion()),
            Logger::porDefecto()
        );
    }

    /* ---------------------------------------------------------------
     * Acciones
     * --------------------------------------------------------------- */

    /**
     * Listado: el administrador ve todas; el usuario, solo las suyas.
     *
     * @return void
     */
    public static function listar(): void
    {
        $esAdmin = Auth::esAdmin();
        $usuarioId = (int)($_SESSION['usuario']['id'] ?? 0);

        $busqueda = trim((string)($_GET['busqueda'] ?? ''));
        $estado   = (string)($_GET['estado'] ?? '');
        $fecha    = (string)($_GET['fecha'] ?? '');
        $pagina   = max(1, (int)($_GET['pagina'] ?? 1));

        try {
            $resultado = self::servicio()->listar(
                $esAdmin ? null : $usuarioId,
                $estado,
                $fecha,
                $pagina,
                $esAdmin ? $busqueda : ''
            );

            $totalPaginas = max(1, (int)ceil($resultado['total'] / self::POR_PAGINA));

            pagina('reservas/listar', [
                'reservas'     => $resultado['reservas'],
                'esAdmin'      => $esAdmin,
                'busqueda'     => $busqueda,
                'filtroEstado' => $estado,
                'filtroFecha'  => $fecha,
                'pagina'       => $resultado['pagina'],
                'totalPaginas' => $totalPaginas,
                'total'        => $resultado['total'],
            ], 'Reservas', 'Préstamo de portátiles de la biblioteca', 'reservas');
        } catch (AccesoDenegadoException $e) {
            self::mostrarDenegado($e->getMessage());
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al listar reservas: ' . $e->getMessage());
            self::mostrarDenegado('No fue posible conectar con la base de datos. Intenta más tarde.');
        }
    }

    /**
     * Formulario de nueva reserva (GET).
     *
     * Recibe fecha/hora/duración opcionales para mostrar los portátiles
     * realmente disponibles en esa franja (validado en el servidor).
     *
     * @return void
     */
    public static function formularioCrear(): void
    {
        $fecha  = trim((string)($_GET['fecha'] ?? ''));
        $inicio = trim((string)($_GET['hora_inicio'] ?? ''));
        $duracion = (int)($_GET['duracion'] ?? 0);

        // Valor por defecto de fecha: si ya pasó la franja de hoy
        // (las 18:00), se propone mañana para no ofrecer un horario
        // imposible (evita bucles de redirección con errores).
        if ($fecha === '') {
            $fecha = (int)date('H') >= HORA_BIBLIOTECA_FIN
                ? date('Y-m-d', strtotime('+1 day'))
                : date('Y-m-d');
        }

        // El inicio solo se propone por defecto si es válido (en punto y
        // dentro de la franja). Un parámetro descartable se reemplaza ya
        // mismo y se avisa, para no rebotar con una segunda petición.
        $inicioValido = (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $inicio)
            && substr($inicio, 3) === '00'
            && (int)substr($inicio, 0, 2) >= HORA_BIBLIOTECA_INICIO
            && (int)substr($inicio, 0, 2) < HORA_BIBLIOTECA_FIN;
        if (!$inicioValido) {
            if ($inicio !== '') {
                flash('aviso', 'La hora de inicio debe estar en punto (por ejemplo 09:00) '
                    . 'dentro de la franja de 6:00 a. m. a 6:00 p. m.; se cargó la hora por defecto.');
            }
            $inicio = ($fecha === date('Y-m-d'))
                ? sprintf('%02d:00', max(HORA_BIBLIOTECA_INICIO, min((int)date('H') + 1, HORA_BIBLIOTECA_FIN - 1)))
                : '08:00';
        }

        // Duración: nunca mayor que la que cabe dentro de la franja
        // (18:00) ni que el máximo permitido. Un exceso se ajusta con
        // un aviso visible en lugar de rechazar la carga.
        $duracionMaxima = min(
            MAX_HORAS_PRESTAMO,
            HORA_BIBLIOTECA_FIN - (int)substr($inicio, 0, 2)
        );
        if ($duracion < 1) {
            $duracion = min(2, $duracionMaxima);
        } elseif ($duracion > $duracionMaxima) {
            flash('aviso', 'La duración se ajustó a ' . $duracionMaxima
                . ' hora(s) para que la reserva termine como máximo a las 6:00 p. m.');
            $duracion = $duracionMaxima;
        }

        try {
            $horaFin = self::horaFin($inicio, $duracion);
            $disponibles = self::servicio()->disponibilidad($fecha, $inicio, $horaFin);
            $esHoy = $fecha === date('Y-m-d');

            pagina('reservas/crear', [
                'disponibles' => $disponibles,
                'fecha'       => $fecha,
                'horaInicio'  => $inicio,
                'duracion'    => $duracion,
                'horaFin'     => $horaFin,
                'esHoy'       => $esHoy,
            ], 'Nueva reserva', 'Solicita el préstamo de un portátil', 'reservas');
        } catch (ReservaInvalidaException $e) {
            foreach ($e->obtenerErrores() as $error) {
                flash('error', $error);
            }
            header('Location: ' . BASE_URL . '/reserva_nueva.php');
            exit;
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión: ' . $e->getMessage());
            self::mostrarDenegado('No fue posible conectar con la base de datos.');
        }
    }

    /**
     * Procesa la creación de la reserva (POST).
     *
     * @return void
     */
    public static function crear(): void
    {
        Csrf::validar();
        $usuarioId = (int)($_SESSION['usuario']['id'] ?? 0);
        $volver = self::urlFormulario($_POST);

        try {
            self::servicio()->crear($_POST, $usuarioId);

            if (($_POST['fecha'] ?? '') === date('Y-m-d')) {
                flash('aviso', 'Reserva de hoy registrada: su uso depende de la disponibilidad '
                    . 'actual del equipo en la biblioteca.');
            }
            flash('exito', 'Reserva creada correctamente. Recoge tu portátil a la hora indicada.');
            header('Location: ' . BASE_URL . '/reservas.php');
            exit;
        } catch (SolapamientoException $e) {
            flash('error', $e->getMessage());
            header('Location: ' . $volver);
            exit;
        } catch (ReservaInvalidaException $e) {
            foreach ($e->obtenerErrores() as $error) {
                flash('error', $error);
            }
            header('Location: ' . $volver);
            exit;
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al crear reserva: ' . $e->getMessage());
            flash('error', 'No fue posible registrar la reserva. Intenta más tarde.');
            header('Location: ' . $volver);
            exit;
        }
    }

    /**
     * Cancela una reserva (POST). Cualquier usuario cancela la suya;
     * el administrador puede cancelar cualquiera.
     *
     * @return void
     */
    public static function cancelar(): void
    {
        Csrf::validar();
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            self::servicio()->cancelar($id, (int)($_SESSION['usuario']['id'] ?? 0), Auth::esAdmin());
            flash('exito', 'Reserva cancelada.');
        } catch (ReservaInvalidaException $e) {
            flash('error', $e->getMessage());
        } catch (AccesoDenegadoException $e) {
            self::mostrarDenegado($e->getMessage());
            return;
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al cancelar reserva: ' . $e->getMessage());
            flash('error', 'No fue posible cancelar la reserva. Intenta más tarde.');
        }
        header('Location: ' . BASE_URL . '/reservas.php');
        exit;
    }

    /**
     * Registra la entrega física del equipo (POST, solo administrador).
     *
     * @return void
     */
    public static function entregar(): void
    {
        Csrf::validar();
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            self::servicio()->registrarEntrega($id);
            flash('exito', 'Entrega registrada: el equipo quedó como prestado.');
        } catch (ReservaInvalidaException $e) {
            flash('error', $e->getMessage());
        } catch (AccesoDenegadoException $e) {
            self::mostrarDenegado($e->getMessage());
            return;
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al registrar entrega: ' . $e->getMessage());
            flash('error', 'No fue posible registrar la entrega. Intenta más tarde.');
        }
        header('Location: ' . BASE_URL . '/reservas.php');
        exit;
    }

    /**
     * Registra la devolución del equipo (POST, solo administrador):
     * el portátil vuelve a estar disponible para nuevas reservas.
     *
     * @return void
     */
    public static function devolver(): void
    {
        Csrf::validar();
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            self::servicio()->registrarDevolucion($id);
            flash('exito', 'Devolución registrada: el equipo volvió a estar disponible.');
        } catch (ReservaInvalidaException $e) {
            flash('error', $e->getMessage());
        } catch (AccesoDenegadoException $e) {
            self::mostrarDenegado($e->getMessage());
            return;
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión al registrar devolución: ' . $e->getMessage());
            flash('error', 'No fue posible registrar la devolución. Intenta más tarde.');
        }
        header('Location: ' . BASE_URL . '/reservas.php');
        exit;
    }

    /**
     * Endpoint JSON de notificaciones (polling del navegador).
     *
     * Devuelve, en segundos restantes calculados por el servidor
     * (el reloj del cliente nunca es la autoridad):
     *  - Para cualquier usuario: sus reservas activas de hoy.
     *  - Para el administrador: además, las que están por vencer y
     *    los préstamos atrasados (equipos numerados por código).
     *
     * @return void
     */
    public static function notificaciones(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $usuarioId = (int)($_SESSION['usuario']['id'] ?? 0);
            $servicio = self::servicio();
            $ahora = time();

            $propias = array_map(
                static fn(array $r): array => self::jsonReserva($r, $ahora),
                $servicio->activasHoyDeUsuario($usuarioId)
            );

            $respuesta = [
                'servidor' => $ahora,
                'propias'  => $propias,
            ];

            if (Auth::esAdmin()) {
                $panel = $servicio->panelVencimientos(MINUTOS_AVISO_TEMPRANO);
                $respuesta['vencer'] = array_map(
                    static fn(array $r): array => self::jsonReserva($r, $ahora),
                    $panel['vencer']
                );
                $respuesta['atrasados'] = array_map(
                    static fn(array $r): array => self::jsonReserva($r, $ahora),
                    $panel['atrasados']
                );
            }

            echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
        } catch (ConexionException $e) {
            Logger::porDefecto()->error('Fallo de conexión en notificaciones: ' . $e->getMessage());
            http_response_code(503);
            echo json_encode(['error' => 'Servicio no disponible.'], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            // Nunca se filtran detalles crudos al navegador.
            Logger::porDefecto()->error('Error inesperado en notificaciones: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error interno.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /* ---------------------------------------------------------------
     * Auxiliares privados
     * --------------------------------------------------------------- */

    /**
     * Convierte una fila de reserva en el JSON que consume el navegador.
     *
     * @param array<string, mixed> $reserva Fila con JOIN de usuario y equipo.
     * @param int $ahora Epoch actual del servidor.
     * @return array<string, mixed>
     */
    private static function jsonReserva(array $reserva, int $ahora): array
    {
        $fin = strtotime($reserva['fecha'] . ' ' . $reserva['hora_fin']);
        return [
            'id'               => (int)$reserva['id'],
            'equipo_codigo'    => (string)$reserva['equipo_codigo'],
            'usuario_nombre'   => (string)$reserva['usuario_nombre'],
            'fecha'            => (string)$reserva['fecha'],
            'hora_inicio'      => substr((string)$reserva['hora_inicio'], 0, 5),
            'hora_fin'         => substr((string)$reserva['hora_fin'], 0, 5),
            'estado'           => (string)$reserva['estado'],
            'segundos_restantes' => $fin - $ahora,
        ];
    }

    /**
     * Calcula la hora de fin de la reserva a partir del formulario.
     *
     * @param string $inicio Inicio HH:MM.
     * @param int $duracion Duración en horas.
     * @return string Fin HH:MM.
     */
    private static function horaFin(string $inicio, int $duracion): string
    {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $inicio) || $duracion < 1 || $duracion > MAX_HORAS_PRESTAMO) {
            return '';
        }
        return date('H:i', strtotime($inicio) + $duracion * 3600);
    }

    /**
     * Reconstruye la URL del formulario conservando la franja elegida,
     * para volver allí tras un error de validación.
     *
     * @param array<string, mixed> $datos Datos del POST.
     * @return string URL con parámetros de consulta.
     */
    private static function urlFormulario(array $datos): string
    {
        $consulta = http_build_query([
            'fecha'       => (string)($datos['fecha'] ?? ''),
            'hora_inicio' => (string)($datos['hora_inicio'] ?? ''),
            'duracion'    => (int)($datos['duracion'] ?? 0),
        ]);
        return BASE_URL . '/reserva_nueva.php?' . $consulta;
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
        require dirname(__DIR__) . '/views/errores/denegado.php';
    }
}
