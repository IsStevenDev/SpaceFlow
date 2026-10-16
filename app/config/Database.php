<?php
/**
 * SpaceFlow — Conexión a la base de datos (patrón Singleton).
 *
 * Centraliza una única conexión PDO para toda la aplicación. Al usar
 * PDO::ERRMODE_EXCEPTION, cualquier fallo de consulta se convierte en
 * una excepción capturable en la capa de servicios.
 *
 * Nota: el archivo de parámetros se llama conexion.php (no database.php)
 * para evitar la colisión de nombres con esta clase en Windows, cuyo
 * sistema de archivos no distingue mayúsculas.
 *
 * @package SpaceFlow\Config
 */

declare(strict_types=1);

namespace App\Config;

use App\Exceptions\ConexionException;
use PDO;

class Database
{
    /** @var PDO|null Instancia única de la conexión. */
    private static ?PDO $conexion = null;

    /**
     * Devuelve la conexión PDO, creándola si no existe.
     *
     * @return PDO Conexión lista para usar.
     * @throws ConexionException Si no hay conexión con el servidor.
     */
    public static function conexion(): PDO
    {
        if (self::$conexion === null) {
            $archivo = __DIR__ . '/conexion.php';
            if (!is_file($archivo)) {
                throw new ConexionException(
                    'Falta el archivo app/config/conexion.php: copia database/config.ejemplo.php '
                    . 'como app/config/conexion.php y ajusta los valores (ver README, paso 4).'
                );
            }
            /** @var array{dsn:string, usuario:string, clave:string, opciones:array} $config */
            $config = require $archivo;
            try {
                self::$conexion = new PDO(
                    $config['dsn'],
                    $config['usuario'],
                    $config['clave'],
                    $config['opciones']
                );
            } catch (\PDOException $e) {
                // El detalle se registra en el log; jamás se muestra al usuario.
                throw new ConexionException(
                    'No fue posible establecer la conexión con la base de datos.',
                    0,
                    $e
                );
            }
        }
        return self::$conexion;
    }

    /**
     * Cierra la conexión (uso principal en pruebas).
     *
     * @return void
     */
    public static function cerrar(): void
    {
        self::$conexion = null;
    }
}
