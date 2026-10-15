<?php
/**
 * SpaceFlow — Excepción: validación de datos fallida.
 *
 * Agrupa uno o varios mensajes de validación comprensibles para que
 * el controlador pueda mostrarlos junto al formulario.
 *
 * @package SpaceFlow\Exceptions
 */

declare(strict_types=1);

namespace App\Exceptions;

class ValidacionException extends SpaceFlowException
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
