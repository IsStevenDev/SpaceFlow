<?php
/**
 * SpaceFlow — Excepción: reserva inválida.
 *
 * Se lanza cuando una solicitud de reserva no cumple las reglas de
 * negocio (franja horaria de la biblioteca, duración máxima, fecha
 * fuera del plazo, equipo no reservable, etc.).
 *
 * @package SpaceFlow\Exceptions
 */

declare(strict_types=1);

namespace App\Exceptions;

class ReservaInvalidaException extends SpaceFlowException
{
    /** @var array<int, string> */
    private array $errores;

    /**
     * @param array<int, string>|string $errores Lista de mensajes o uno solo.
     */
    public function __construct(array|string $errores)
    {
        $this->errores = is_array($errores) ? array_values($errores) : [$errores];
        parent::__construct(implode(' ', $this->errores));
    }

    /** @return array<int, string> Mensajes de validación. */
    public function obtenerErrores(): array
    {
        return $this->errores;
    }
}
