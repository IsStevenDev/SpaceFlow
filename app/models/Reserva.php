<?php
/**
 * SpaceFlow — Modelo de dominio: Reserva.
 *
 * Préstamo de un portátil en un bloque horario concreto. La entrega y
 * la devolución las confirma el administrador; el estudiante solo puede
 * cancelar sus propias reservas mientras estén activas.
 *
 * @package SpaceFlow\Models
 */

declare(strict_types=1);

namespace App\Models;

class Reserva
{
    /** Estados posibles (deben coincidir con el ENUM de la tabla). */
    public const ESTADO_ACTIVA    = 'activa';
    public const ESTADO_PRESTAMO  = 'prestamo';
    public const ESTADO_ENTREGADA = 'entregada';
    public const ESTADO_CANCELADA = 'cancelada';
    public const ESTADO_VENCIDA   = 'vencida';

    private ?int $id = null;
    private int $usuarioId = 0;
    private string $usuarioNombre = '';
    private int $equipoId = 0;
    private string $equipoCodigo = '';
    private string $equipoNombre = '';
    private string $fecha = '';
    private string $horaInicio = '';
    private string $horaFin = '';
    private string $estado = self::ESTADO_ACTIVA;
    private string $motivo = '';
    private string $fechaCreacion = '';

    /** @return int|null Identificador o null si no está guardada. */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUsuarioId(): int
    {
        return $this->usuarioId;
    }

    public function setUsuarioId(int $usuarioId): self
    {
        $this->usuarioId = $usuarioId;
        return $this;
    }

    public function getUsuarioNombre(): string
    {
        return $this->usuarioNombre;
    }

    public function setUsuarioNombre(string $usuarioNombre): self
    {
        $this->usuarioNombre = $usuarioNombre;
        return $this;
    }

    public function getEquipoId(): int
    {
        return $this->equipoId;
    }

    public function setEquipoId(int $equipoId): self
    {
        $this->equipoId = $equipoId;
        return $this;
    }

    public function getEquipoCodigo(): string
    {
        return $this->equipoCodigo;
    }

    public function setEquipoCodigo(string $equipoCodigo): self
    {
        $this->equipoCodigo = $equipoCodigo;
        return $this;
    }

    public function getEquipoNombre(): string
    {
        return $this->equipoNombre;
    }

    public function setEquipoNombre(string $equipoNombre): self
    {
        $this->equipoNombre = $equipoNombre;
        return $this;
    }

    /** @return string Fecha en formato YYYY-MM-DD. */
    public function getFecha(): string
    {
        return $this->fecha;
    }

    public function setFecha(string $fecha): self
    {
        $this->fecha = $fecha;
        return $this;
    }

    /** @return string Hora de inicio en formato HH:MM:SS. */
    public function getHoraInicio(): string
    {
        return $this->horaInicio;
    }

    public function setHoraInicio(string $horaInicio): self
    {
        $this->horaInicio = $horaInicio;
        return $this;
    }

    /** @return string Hora de fin en formato HH:MM:SS. */
    public function getHoraFin(): string
    {
        return $this->horaFin;
    }

    public function setHoraFin(string $horaFin): self
    {
        $this->horaFin = $horaFin;
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

    public function getMotivo(): string
    {
        return $this->motivo;
    }

    public function setMotivo(string $motivo): self
    {
        $this->motivo = trim($motivo);
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

    /**
     * Momento exacto en que termina la reserva.
     *
     * @return \DateTimeImmutable|null Null si los datos están incompletos.
     */
    public function finDateTime(): ?\DateTimeImmutable
    {
        if ($this->fecha === '' || $this->horaFin === '') {
            return null;
        }
        return new \DateTimeImmutable($this->fecha . ' ' . $this->horaFin);
    }

    /**
     * Minutos restantes hasta el fin de la reserva (negativo si ya venció).
     *
     * @param \DateTimeImmutable|null $ahora Momento de referencia (por defecto, ahora).
     * @return int|null Null si no hay fecha/hora válidas.
     */
    public function minutosRestantes(?\DateTimeImmutable $ahora = null): ?int
    {
        $fin = $this->finDateTime();
        if ($fin === null) {
            return null;
        }
        $ahora ??= new \DateTimeImmutable('now');
        return (int)round(($fin->getTimestamp() - $ahora->getTimestamp()) / 60);
    }

    /**
     * Etiqueta legible del estado para la interfaz.
     *
     * @return string Texto presentable.
     */
    public function estadoEtiqueta(): string
    {
        return match ($this->estado) {
            self::ESTADO_ACTIVA    => 'Activa',
            self::ESTADO_PRESTAMO  => 'En préstamo',
            self::ESTADO_ENTREGADA => 'Entregada',
            self::ESTADO_CANCELADA => 'Cancelada',
            self::ESTADO_VENCIDA   => 'Vencida',
            default                => $this->estado,
        };
    }
}
