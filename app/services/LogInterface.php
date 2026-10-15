<?php
/**
 * SpaceFlow — Interfaz del sistema de logs.
 *
 * Define el contrato mínimo que debe cumplir cualquier registrador de
 * eventos. Permite sustituir la implementación en archivo por otra
 * (base de datos, syslog...) sin tocar el código que la utiliza.
 *
 * @package SpaceFlow\Services
 */

declare(strict_types=1);

namespace App\Services;

interface LogInterface
{
    /** Registra un evento informativo (INFO). */
    public function info(string $mensaje): void;

    /** Registra una advertencia o acción rechazada (WARNING). */
    public function advertencia(string $mensaje): void;

    /** Registra un error o excepción capturada (ERROR). */
    public function error(string $mensaje): void;
}
