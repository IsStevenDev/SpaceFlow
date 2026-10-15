<?php
/**
 * SpaceFlow — Controlador del registro de actividad (logs).
 *
 * Acceso exclusivo del administrador. Lee y presenta el archivo real
 * logs/aplicacion.log, sin datos simulados.
 *
 * @package SpaceFlow\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Auth;
use App\Services\Logger;

class LogController
{
    /**
     * Muestra las últimas entradas del archivo de log.
     *
     * @return void
     */
    public static function mostrar(): void
    {
        // Verificación de rol REAL en el servidor (además del menú).
        Auth::requerirAdmin();

        $logger = Logger::porDefecto();

        // Filtro opcional por nivel (?nivel=INFO|WARNING|ERROR).
        $nivel = strtoupper((string)($_GET['nivel'] ?? ''));
        if (!in_array($nivel, ['INFO', 'WARNING', 'ERROR'], true)) {
            $nivel = '';
        }

        $lineas = $logger->ultimasLineas(200);
        $eventos = [];

        foreach ($lineas as $linea) {
            if (!preg_match('/^\[(.+?)\] \[(INFO|WARNING|ERROR)\] \[(.+?)\] - (.+)$/', $linea, $coincidencias)) {
                continue;
            }
            [, $fecha, $nivelLinea, $usuario, $mensaje] = $coincidencias;

            if ($nivel !== '' && $nivelLinea !== $nivel) {
                continue;
            }

            $eventos[] = [
                'fecha'   => $fecha,
                'nivel'   => $nivelLinea,
                'usuario' => $usuario,
                'mensaje' => $mensaje,
            ];
        }

        pagina('logs/index', [
            'eventos' => $eventos,
            'nivel'   => $nivel,
        ], 'Registro de actividad', 'Trazabilidad de las operaciones del sistema', 'logs');
    }
}
