<?php
/**
 * SpaceFlow — Excepción: error de conexión a la base de datos.
 *
 * Se lanza desde la capa de configuración cuando PDO no puede
 * establecer la conexión. Su mensaje NUNCA llega al usuario final.
 *
 * @package SpaceFlow\Exceptions
 */

declare(strict_types=1);

namespace App\Exceptions;

class ConexionException extends SpaceFlowException
{
}
