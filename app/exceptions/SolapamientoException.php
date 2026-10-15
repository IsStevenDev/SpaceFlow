<?php
/**
 * SpaceFlow — Excepción: solapamiento de reservas.
 *
 * Se lanza cuando el equipo solicitado ya tiene una reserva activa que
 * intersecta con el horario pedido. Conserva los datos del conflicto
 * para que el controlador pueda mostrar un mensaje orientativo
 * ("ya está reservado de 10:00 a 12:00").
 *
 * @package SpaceFlow\Exceptions
 */

declare(strict_types=1);

namespace App\Exceptions;

class SolapamientoException extends ReservaInvalidaException
{
    /**
     * @param string $codigoEquipo   Código del equipo ocupado.
     * @param string $horaInicioConflicto Inicio de la reserva existente (HH:MM).
     * @param string $horaFinConflicto    Fin de la reserva existente (HH:MM).
     */
    public function __construct(
        private readonly string $codigoEquipo,
        private readonly string $horaInicioConflicto,
        private readonly string $horaFinConflicto
    ) {
        parent::__construct(
            "El equipo {$codigoEquipo} ya está reservado en ese horario "
            . "(de {$horaInicioConflicto} a {$horaFinConflicto}). "
            . 'Elige otra franja u otro portátil.'
        );
    }

    /** @return string Código del equipo en conflicto. */
    public function obtenerCodigoEquipo(): string
    {
        return $this->codigoEquipo;
    }

    /** @return array{inicio: string, fin: string} Horario de la reserva existente. */
    public function obtenerHorarioConflicto(): array
    {
        return ['inicio' => $this->horaInicioConflicto, 'fin' => $this->horaFinConflicto];
    }
}
