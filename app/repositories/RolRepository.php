<?php
/**
 * SpaceFlow — Repositorio de roles.
 *
 * @package SpaceFlow\Repositories
 */

declare(strict_types=1);

namespace App\Repositories;

class RolRepository extends RepositorioBase
{
    /** {@inheritdoc} */
    protected function tabla(): string
    {
        return 'roles';
    }

    /**
     * Devuelve todos los roles ordenados por id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarTodos(): array
    {
        return $this->conexion->query('SELECT id, nombre, descripcion FROM roles ORDER BY id')
            ->fetchAll();
    }
}
