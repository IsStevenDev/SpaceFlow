<?php
/**
 * SpaceFlow — Vista parcial: formulario de equipo.
 *
 * Compartido por la creación y la edición. Valida también en el
 * navegador (patrón, longitud), pero la autoridad es el servidor.
 *
 * @package SpaceFlow\Views
 *
 * @var array<string, mixed> $equipo Fila existente o [] al crear.
 * @var string $accion 'crear' | 'editar'.
 */

/** Estados del catálogo (deben coincidir con el ENUM de la tabla). */
$estados = [
    'disponible'    => 'Disponible',
    'prestado'      => 'Prestado (en préstamo activo)',
    'mantenimiento' => 'En mantenimiento',
    'baja'          => 'Dado de baja',
];

$esEdicion = $accion === 'editar';
?>
<form method="post"
      action="<?= $esEdicion ? url('equipo_editar.php') : url('equipo_nuevo.php') ?>"
      data-validar data-cargando novalidate>
    <?php csrf_campo(); ?>
    <?php if ($esEdicion): ?>
        <input type="hidden" name="id" value="<?= (int)($equipo['id'] ?? 0) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-4">
            <label for="campoCodigo" class="form-label">Código <span class="text-rojo">*</span></label>
            <input type="text" id="campoCodigo" name="codigo" class="form-control"
                   value="<?= e($equipo['codigo'] ?? '') ?>"
                   placeholder="PORT-009" maxlength="30" required
                   pattern="[A-Za-z0-9\-]{3,30}"
                   title="De 3 a 30 caracteres: letras, números o guiones.">
            <div class="invalid-feedback">Usa de 3 a 30 caracteres (letras, números o guiones).</div>
        </div>

        <div class="col-md-8">
            <label for="campoNombre" class="form-label">Nombre del equipo <span class="text-rojo">*</span></label>
            <input type="text" id="campoNombre" name="nombre" class="form-control"
                   value="<?= e($equipo['nombre'] ?? '') ?>"
                   placeholder="Portátil Lenovo IdeaPad 3" minlength="3" maxlength="100" required>
            <div class="invalid-feedback">El nombre debe tener entre 3 y 100 caracteres.</div>
        </div>

        <div class="col-md-6">
            <label for="campoMarca" class="form-label">Marca</label>
            <input type="text" id="campoMarca" name="marca" class="form-control"
                   value="<?= e($equipo['marca'] ?? '') ?>"
                   placeholder="Lenovo, HP, Dell..." maxlength="60">
            <div class="form-text">Opcional. Máximo 60 caracteres.</div>
        </div>

        <div class="col-md-6">
            <label for="campoEstado" class="form-label">Estado <span class="text-rojo">*</span></label>
            <select id="campoEstado" name="estado" class="form-select" required>
                <?php foreach ($estados as $clave => $texto): ?>
                    <option value="<?= e($clave) ?>"
                        <?= ($equipo['estado'] ?? 'disponible') === $clave ? 'selected' : '' ?>>
                        <?= e($texto) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                <?php // La entrega/devolución de reservas cambia este estado en el servidor. ?>
                Solo los equipos <strong>disponibles</strong> y <strong>prestados</strong> admiten reservas.
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>
            <?= $esEdicion ? 'Guardar cambios' : 'Registrar equipo' ?>
        </button>
        <a href="<?= url('equipos.php') ?>" class="btn btn-suave">Cancelar</a>
    </div>
</form>
