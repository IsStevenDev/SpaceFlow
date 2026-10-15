<?php
/**
 * SpaceFlow — Clase abstracta base de los repositorios.
 *
 * Reúne la conexión PDO y las operaciones comunes a todos los
 * repositorios (buscar por id, contar). Heredan de ella
 * UsuarioRepository, RolRepository, ProgramaRepository, etc.
 *
 * Todas las consultas con datos variables usan sentencias preparadas
 * con parámetros, eliminando el riesgo de inyección SQL.
 *
 * @package SpaceFlow\Repositories
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

abstract class RepositorioBase
{
    protected readonly PDO $conexion;

    public function __construct(?PDO $conexion = null)
    {
        $this->conexion = $conexion ?? Database::conexion();
    }

    /**
     * Devuelve el nombre de la tabla concreta de la subclase.
     *
     * @return string
     */
    abstract protected function tabla(): string;

    /**
     * Busca un registro por su identificador.
     *
     * @param int $id Identificador.
     * @return array<string, mixed>|null Fila encontrada o null.
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->tabla()} WHERE id = :id";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':id' => $id]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $fila;
    }

    /**
     * Cuenta la cantidad total de registros de la tabla.
     *
     * @return int
     */
    public function contar(): int
    {
        $sql = "SELECT COUNT(*) AS total FROM {$this->tabla()}";
        $consulta = $this->conexion->query($sql);
        return (int)($consulta->fetchColumn() ?: 0);
    }
}
