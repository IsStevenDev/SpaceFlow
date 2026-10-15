<?php
/**
 * SpaceFlow — Modelo de dominio: Equipo.
 *
 * Portátiles disponibles para préstamo en la biblioteca. Los atributos
 * son privados y se accede mediante getters/setters; la persistencia
 * vive en EquipoRepository.
 *
 * @package SpaceFlow\Models
 */

declare(strict_types=1);

namespace App\Models;

class Equipo
{
    /** Estados posibles (deben coincidir con el ENUM de la tabla). */
    public const ESTADO_DISPONIBLE    = 'disponible';
    public const ESTADO_PRESTADO      = 'prestado';
    public const ESTADO_MANTENIMIENTO = 'mantenimiento';
    public const ESTADO_BAJA          = 'baja';

    private ?int $id = null;
    private string $codigo = '';
    private string $nombre = '';
    private string $marca = '';
    private string $estado = self::ESTADO_DISPONIBLE;
    private string $fechaCreacion = '';

    /** @return int|null Identificador o null si no está guardado. */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): self
    {
        $this->codigo = strtoupper(trim($codigo));
        return $this;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = trim($nombre);
        return $this;
    }

    public function getMarca(): string
    {
        return $this->marca;
    }

    public function setMarca(string $marca): self
    {
        $this->marca = trim($marca);
        return $this;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): self
    {
        $this->estado = $estado;
        return $this;
    }

    public function getFechaCreacion(): string
    {
        return $this->fechaCreacion;
    }

    public function setFechaCreacion(string $fechaCreacion): self
    {
        $this->fechaCreacion = $fechaCreacion;
        return $this;
    }

    /** @return bool true si el equipo está disponible para reserva. */
    public function estaDisponible(): bool
    {
        return $this->estado === self::ESTADO_DISPONIBLE;
    }

    /**
     * @return bool true si el equipo puede entrar en el ciclo de reservas
     *              (mantenimiento y baja quedan excluidos).
     */
    public function esReservable(): bool
    {
        return in_array($this->estado, [self::ESTADO_DISPONIBLE, self::ESTADO_PRESTADO], true);
    }

    /**
     * Etiqueta legible del estado para la interfaz.
     *
     * @return string Texto presentable.
     */
    public function estadoEtiqueta(): string
    {
        return match ($this->estado) {
            self::ESTADO_DISPONIBLE    => 'Disponible',
            self::ESTADO_PRESTADO      => 'Prestado',
            self::ESTADO_MANTENIMIENTO => 'En mantenimiento',
            self::ESTADO_BAJA          => 'Dado de baja',
            default                    => $this->estado,
        };
    }
}
