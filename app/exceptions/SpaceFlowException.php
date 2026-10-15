<?php
/**
 * SpaceFlow — Excepción base del dominio.
 *
 * Todas las excepciones propias del proyecto heredan de esta clase,
 * lo que permite capturarlas todas con un único `catch (SpaceFlowException)`.
 *
 * @package SpaceFlow\Exceptions
 */

declare(strict_types=1);

namespace App\Exceptions;

class SpaceFlowException extends \Exception
{
}
