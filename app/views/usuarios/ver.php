<?php
/**
 * SpaceFlow — Vista: detalle de un usuario (consulta administrativa).
 *
 * @package SpaceFlow\Views
 *
 * @var array<string, mixed> $usuario
 */

$iniciales = strtoupper(implode('', array_map(
    static fn(string $p): string => mb_substr($p, 0, 1),
    array_slice(explode(' ', trim((string)$usuario['nombre'])), 0, 2)
)));
?>
<div class="sf-pagina-cabecera">
    <div>
        <h2><?= e($usuario['nombre']) ?></h2>
        <p>Consulta detallada de la cuenta registrada.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('usuario_editar.php?id=' . (int)$usuario['id']) ?>" class="btn btn-primary">
            <i class="bi bi-pencil" aria-hidden="true"></i> Editar
        </a>
        <a href="<?= url('usuarios.php') ?>" class="btn btn-suave">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="tarjeta">
            <div class="tarjeta-cuerpo text-center p-4">
                <span class="sf-avatar mx-auto mb-3 d-flex" style="width: 72px; height: 72px; font-size: 1.35rem;">
                    <?= e($iniciales) ?>
                </span>
                <h3 class="mb-1"><?= e($usuario['nombre']) ?></h3>
                <p class="texto-auxiliar mb-3"><?= e($usuario['correo']) ?></p>
                <span class="badge-sf <?= $usuario['rol_nombre'] === 'Administrador' ? 'badge-admin' : 'badge-usuario' ?>">
                    <?= e($usuario['rol_nombre']) ?>
                </span>
                <span class="badge-sf <?= $usuario['estado'] === 'activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                    <?= $usuario['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="tarjeta h-100">
            <div class="tarjeta-cabecera">
                <div>
                    <h3>Información registrada</h3>
                    <p class="texto-auxiliar">Datos institucionales de la cuenta.</p>
                </div>
            </div>
            <div class="tarjeta-cuerpo">
                <dl class="row mb-0">
                    <dt class="col-sm-4 texto-auxiliar fw-semibold">Identificación</dt>
                    <dd class="col-sm-8">
                        <?= e($usuario['tipo_documento']) ?> · <?= e($usuario['numero_documento']) ?>
                    </dd>

                    <dt class="col-sm-4 texto-auxiliar fw-semibold">Programa</dt>
                    <dd class="col-sm-8"><?= e($usuario['programa_nombre'] ?? 'Sin programa asignado') ?></dd>

                    <dt class="col-sm-4 texto-auxiliar fw-semibold">Rol</dt>
                    <dd class="col-sm-8"><?= e($usuario['rol_nombre']) ?></dd>

                    <dt class="col-sm-4 texto-auxiliar fw-semibold">Estado</dt>
                    <dd class="col-sm-8"><?= $usuario['estado'] === 'activo' ? 'Activo' : 'Inactivo (no puede iniciar sesión)' ?></dd>

                    <dt class="col-sm-4 texto-auxiliar fw-semibold">Creado</dt>
                    <dd class="col-sm-8"><?= e(date('d/m/Y H:i', strtotime((string)$usuario['fecha_creacion']))) ?></dd>

                    <?php if (!empty($usuario['fecha_actualizacion'])): ?>
                        <dt class="col-sm-4 texto-auxiliar fw-semibold">Última edición</dt>
                        <dd class="col-sm-8"><?= e(date('d/m/Y H:i', strtotime((string)$usuario['fecha_actualizacion']))) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>
