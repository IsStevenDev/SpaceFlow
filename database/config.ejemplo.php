<?php
/**
 * SpaceFlow — Ejemplo de configuración de base de datos.
 *
 * Copia este archivo como app/config/conexion.php y ajusta los valores
 * a tu entorno. El archivo real conexión.php no se versiona (ver .gitignore)
 * para que las credenciales no queden en el repositorio.
 *
 * @package SpaceFlow\Config
 */

declare(strict_types=1);

return [
    'dsn'      => 'mysql:host=127.0.0.1;port=3306;dbname=spaceflow;charset=utf8mb4',
    'usuario'  => 'root',
    'clave'    => '',
    'opciones' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Consultas preparadas reales.
    ],
];
