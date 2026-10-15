<?php
/**
 * SpaceFlow — Excepción: credenciales inválidas.
 *
 * Se lanza cuando el correo no existe, la contraseña no coincide o la
 * cuenta está inactiva. El mensaje es deliberadamente genérico para no
 * revelar cuál de las condiciones falló.
 *
 * @package SpaceFlow\Exceptions
 */

declare(strict_types=1);

namespace App\Exceptions;

class CredencialesInvalidasException extends SpaceFlowException
{
    public function __construct(string $mensaje = 'Correo o contraseña incorrectos.')
    {
        parent::__construct($mensaje);
    }
}
