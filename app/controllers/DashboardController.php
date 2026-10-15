<?php
/**
 * SpaceFlow — Controlador del dashboard.
 *
 * @package SpaceFlow\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Exceptions\ConexionException;
use App\Repositories\EquipoRepository;
use App\Repositories\ReservaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\Logger;
use App\Services\ReservaService;

class DashboardController
{
    /**
     * Muestra el panel principal con estadísticas reales de la BD.
     *
     * El administrador ve usuarios, equipos, reservas y el panel de
     * "próximas a vencer"; el usuario estándar ve su bienvenida y sus
     * próximas reservas con la cuenta regresiva del navegador.
     *
     * @return void
     */
    public static function mostrar(): void
    {
        $esAdmin = ($_SESSION['usuario']['rol_id'] ?? 0) === 1;
        $usuarioId = (int)($_SESSION['usuario']['id'] ?? 0);
        $logger = Logger::porDefecto();

        try {
            // Servicio de reservas compartido por ambas ramas.
            $servicioReservas = new ReservaService(
                new ReservaRepository(Database::conexion()),
                new EquipoRepository(Database::conexion()),
                $logger
            );

            if ($esAdmin) {
                $repositorioUsuarios = new UsuarioRepository(Database::conexion());
                $estadisticas = $repositorioUsuarios->contarPorEstado();
                $distribucion = $repositorioUsuarios->contarPorRol();
                $actividad = self::actividadReciente($logger, 5);

                // KPIs y panel "próximas a vencer" de la biblioteca.
                $biblioteca = $servicioReservas->estadisticasDashboard();
                $panel = $servicioReservas->panelVencimientos(MINUTOS_AVISO_TEMPRANO);
                $proximasReservas = [];
            } else {
                // Vista reducida del usuario estándar + sus próximas reservas.
                $estadisticas = ['total' => 0, 'activos' => 0, 'inactivos' => 0];
                $distribucion = [];
                $actividad = [];
                $biblioteca = ['equipos' => [], 'reservas' => []];
                $panel = ['vencer' => [], 'atrasados' => []];
                $proximasReservas = $servicioReservas->proximasDeUsuario($usuarioId, 4);
            }

            pagina('dashboard/index', [
                'estadisticas'     => $estadisticas,
                'distribucion'     => $distribucion,
                'actividad'        => $actividad,
                'esAdmin'          => $esAdmin,
                'biblioteca'       => $biblioteca,
                'panel'            => $panel,
                'proximasReservas' => $proximasReservas,
            ], 'Dashboard', 'Resumen general de la plataforma', 'dashboard');
        } catch (ConexionException $e) {
            $logger->error('Fallo de conexión en el dashboard: ' . $e->getMessage());
            http_response_code(403);
            $titulo = 'Servicio no disponible';
            $mensaje = 'No fue posible conectar con la base de datos. Intenta más tarde.';
            require dirname(__DIR__) . '/views/errores/denegado.php';
        }
    }

    /**
     * Convierte las últimas líneas del log en eventos para la vista.
     *
     * Formato de cada línea:
     * [fecha] [NIVEL] [usuario] - mensaje
     *
     * @param Logger $logger Registrador con acceso al archivo.
     * @param int $cantidad Cantidad de eventos a mostrar.
     * @return array<int, array{usuario:string, mensaje:string, hora:string, icone:string, color:string}>
     */
    private static function actividadReciente(Logger $logger, int $cantidad): array
    {
        $eventos = [];
        foreach ($logger->ultimasLineas($cantidad) as $linea) {
            if (!preg_match('/^\[(.+?)\] \[(INFO|WARNING|ERROR)\] \[(.+?)\] - (.+)$/', $linea, $coincidencias)) {
                continue;
            }
            [, $fecha, $nivel, $usuario, $mensaje] = $coincidencias;

            [$icone, $color] = match ($nivel) {
                'WARNING' => ['bi-exclamation-triangle', 'ambar'],
                'ERROR'   => ['bi-x-circle', 'rojo'],
                default   => ['bi-check2-circle', 'azul'],
            };

            $eventos[] = [
                'usuario'  => $usuario,
                'mensaje'  => $mensaje,
                'hora'     => date('d/m H:i', strtotime($fecha)),
                'icone'    => $icone,
                'color'    => $color,
            ];
        }
        return $eventos;
    }
}
