<?php
/**
 * SpaceFlow — Excepción: acceso denegado.
 *
 * Se lanza cuando un usuario intenta ejecutar una operación que su rol
 * no tiene permitida (verificada siempre en el servidor).
 *
 * @package SpaceFlow\Exceptions
 */

declare(strict_types=1);

namespace App\Exceptions;

class AccesoDenegadoException extends SpaceFlowException
{
}
