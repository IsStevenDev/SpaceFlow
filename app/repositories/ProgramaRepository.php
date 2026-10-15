<?php
/**
 * SpaceFlow — Repositorio del catálogo de programas académicos.
 *
 * @package SpaceFlow\Repositories
 */

declare(strict_types=1);

namespace App\Repositories;

class ProgramaRepository extends RepositorioBase
{
    /** {@inheritdoc} */
    protected function tabla(): string
    {
        return 'programas';
    }

    /**
     * Devuelve los programas agrupados por nivel académico.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarTodos(): array
    {
        $sql = "SELECT id, nombre, nivel FROM programas
                 ORDER BY FIELD(nivel, 'tecnica', 'tecnologia', 'profesional'), nombre";
        return $this->conexion->query($sql)->fetchAll();
    }
}
