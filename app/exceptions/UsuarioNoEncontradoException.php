<?php
/**
 * SpaceFlow — Excepción: usuario no encontrado.
 *
 * Se lanza cuando se solicita un identificador que no existe en la
 * base de datos (edición, consulta o eliminación).
 *
 * @package SpaceFlow\Exceptions
 */

declare(strict_types=1);

namespace App\Exceptions;

class UsuarioNoEncontradoException extends SpaceFlowException
{
    /** @param int $id Identificador buscado. */
    public function __construct(private readonly int $id)
    {
        parent::__construct("No existe ningún usuario con el identificador {$id}.");
    }

    /** @return int Identificador que causó la excepción. */
    public function obtenerId(): int
    {
        return $this->id;
    }
}
