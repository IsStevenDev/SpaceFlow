<?php
/**
 * SpaceFlow — Modelo de dominio: Usuario.
 *
 * Entidad con encapsulamiento: todos los atributos son privados y se
 * accede a ellos mediante getters y setters. No contiene SQL; esa
 * responsabilidad vive en los repositorios.
 *
 * @package SpaceFlow\Models
 */

declare(strict_types=1);

namespace App\Models;

class Usuario
{
    private ?int $id = null;
    private string $nombre = '';
    private string $correo = '';
    private string $passwordHash = '';
    private int $rolId = 0;
    private string $rolNombre = '';
    private string $tipoDocumento = 'CC';
    private string $numeroDocumento = '';
    private ?int $programaId = null;
    private string $programaNombre = '';
    private string $estado = 'activo';
    private string $fechaCreacion = '';

    /** @return int|null Identificador o null si aún no se ha guardado. */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
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

    public function getCorreo(): string
    {
        return $this->correo;
    }

    public function setCorreo(string $correo): self
    {
        $this->correo = strtolower(trim($correo));
        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $hash): self
    {
        $this->passwordHash = $hash;
        return $this;
    }

    public function getRolId(): int
    {
        return $this->rolId;
    }

    public function setRolId(int $rolId): self
    {
        $this->rolId = $rolId;
        return $this;
    }

    public function getRolNombre(): string
    {
        return $this->rolNombre;
    }

    public function setRolNombre(string $rolNombre): self
    {
        $this->rolNombre = $rolNombre;
        return $this;
    }

    public function getTipoDocumento(): string
    {
        return $this->tipoDocumento;
    }

    public function setTipoDocumento(string $tipoDocumento): self
    {
        $this->tipoDocumento = strtoupper(trim($tipoDocumento));
        return $this;
    }

    public function getNumeroDocumento(): string
    {
        return $this->numeroDocumento;
    }

    public function setNumeroDocumento(string $numeroDocumento): self
    {
        $this->numeroDocumento = trim($numeroDocumento);
        return $this;
    }

    public function getProgramaId(): ?int
    {
        return $this->programaId;
    }

    public function setProgramaId(?int $programaId): self
    {
        $this->programaId = $programaId;
        return $this;
    }

    public function getProgramaNombre(): string
    {
        return $this->programaNombre;
    }

    public function setProgramaNombre(string $programaNombre): self
    {
        $this->programaNombre = $programaNombre;
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

    /** @return bool true si la cuenta está activa. */
    public function estaActivo(): bool
    {
        return $this->estado === 'activo';
    }

    /** @return bool true si el rol del usuario es administrador. */
    public function esAdministrador(): bool
    {
        return $this->rolId === 1;
    }

    /**
     * Devuelve las iniciales del nombre para los avatares.
     *
     * @return string Hasta dos letras en mayúscula.
     */
    public function iniciales(): string
    {
        $partes = array_slice(explode(' ', trim($this->nombre)), 0, 2);
        return strtoupper(implode('', array_map(
            static fn(string $p): string => mb_substr($p, 0, 1),
            $partes
        )));
    }
}
