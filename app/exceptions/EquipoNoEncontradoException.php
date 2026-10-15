<?php
/**
 * SpaceFlow — Excepción: equipo no encontrado.
 *
 * Se lanza cuando se solicita un identificador o código de equipo que
 * no existe en el catálogo.
 *
 * @package SpaceFlow\Exceptions
 */

declare(strict_types=1);

namespace App\Exceptions;

class EquipoNoEncontradoException extends SpaceFlowException
{
    /** @param string $identificador Código o id buscado. */
    public function __construct(private readonly string $identificador)
    {
        parent::__construct("No existe ningún equipo con el identificador '{$identificador}'.");
    }

    /** @return string Identificador que causó la excepción. */
    public function obtenerIdentificador(): string
    {
        return $this->identificador;
    }
}
