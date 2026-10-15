<?php
/**
 * SpaceFlow — Parcial: formulario de usuario (crear y editar).
 *
 * Compartido por usuario_nuevo.php y usuario_editar.php para no
 * duplicar markup. Las etiquetas y validaciones coinciden con las
 * reglas del servidor.
 *
 * @package SpaceFlow\Views
 *
 * @var array<int, array<string, mixed>> $roles     Lista de roles (id, nombre).
 * @var array<int, array<string, mixed>> $programas Lista de programas (id, nombre, nivel).
 * @var array<string, mixed>|null $usuario Datos previos (edición) o null (creación).
 * @var string $accion 'crear' | 'editar'.
 */

$esEdicion = $accion === 'editar' && is_array($usuario);
$datos = $esEdicion ? $usuario : [];
$etiquetaBoton = $esEdicion ? 'Guardar cambios' : 'Guardar usuario';

/** Etiqueta del tipo de documento según el valor guardado. */
$tipos = ['CC' => 'Cédula de ciudadanía', 'TI' => 'Tarjeta de identidad',
          'CE' => 'Cédula de extranjería', 'PP' => 'Pasaporte'];

/** Agrupa programas por nivel para el <optgroup>. */
$porNivel = [];
foreach ($programas as $programa) {
    $porNivel[$programa['nivel']][] = $programa;
}
$niveles = ['tecnica' => 'Técnicas Profesionales', 'tecnologia' => 'Tecnologías',
            'profesional' => 'Profesionales'];
?>
<form method="post"
      action="<?= $esEdicion ? url('usuario_editar.php') : url('usuario_nuevo.php') ?>"
      data-cargando data-validar novalidate>
    <?php csrf_campo(); ?>
    <?php if ($esEdicion): ?>
        <input type="hidden" name="id" value="<?= (int)$usuario['id'] ?>">
    <?php endif; ?>

    <div class="row g-3">
        <!-- Nombre completo -->
        <div class="col-12">
            <label for="nombre" class="form-label">Nombre completo *</label>
            <input type="text" id="nombre" name="nombre" class="form-control"
                   placeholder="Ej. María Fernanda López"
                   minlength="3" maxlength="100" required
                   value="<?= e($datos['nombre'] ?? '') ?>">
            <div class="texto-error-campo">Ingresa un nombre de al menos 3 caracteres.</div>
        </div>

        <!-- Correo institucional -->
        <div class="col-12">
            <label for="correo" class="form-label">Correo institucional *</label>
            <div class="input-con-icone">
                <i class="bi bi-envelope" aria-hidden="true"></i>
                <input type="email" id="correo" name="correo" class="form-control"
                       placeholder="nombre@<?= DOMINIO_CORREO ?>" required
                       value="<?= e($datos['correo'] ?? '') ?>">
            </div>
            <p class="texto-ayuda">Solo se aceptan correos @<?= DOMINIO_CORREO ?>.</p>
            <div class="texto-error-campo">Ingresa un correo institucional válido.</div>
        </div>

        <!-- Identificación -->
        <div class="col-sm-5">
            <label for="tipo_documento" class="form-label">Tipo de identificación *</label>
            <select id="tipo_documento" name="tipo_documento" class="form-select" required>
                <option value="">Selecciona...</option>
                <?php foreach ($tipos as $clave => $etiqueta): ?>
                    <option value="<?= $clave ?>"
                        <?= ($datos['tipo_documento'] ?? '') === $clave ? 'selected' : '' ?>>
                        <?= e($etiqueta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="texto-error-campo">Selecciona el tipo de documento.</div>
        </div>
        <div class="col-sm-7">
            <label for="numero_documento" class="form-label">Número de identificación *</label>
            <input type="text" id="numero_documento" name="numero_documento" class="form-control"
                   placeholder="Ej. 1032456789" pattern="[0-9A-Za-z]{5,15}" required
                   value="<?= e($datos['numero_documento'] ?? '') ?>">
            <div class="texto-error-campo">Entre 5 y 15 caracteres alfanuméricos.</div>
        </div>

        <!-- Programa académico -->
        <div class="col-12">
            <label for="programa_id" class="form-label">Programa académico *</label>
            <select id="programa_id" name="programa_id" class="form-select" required>
                <option value="">Selecciona el programa...</option>
                <?php foreach ($niveles as $claveNivel => $tituloNivel): ?>
                    <?php if (empty($porNivel[$claveNivel])) continue; ?>
                    <optgroup label="<?= e($tituloNivel) ?>">
                        <?php foreach ($porNivel[$claveNivel] as $programa): ?>
                            <option value="<?= (int)$programa['id'] ?>"
                                <?= (string)($datos['programa_id'] ?? '') === (string)$programa['id'] ? 'selected' : '' ?>>
                                <?= e($programa['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <div class="texto-error-campo">Selecciona el programa al que pertenece.</div>
        </div>

        <!-- Rol y estado (solo edición administrativa) -->
        <div class="col-sm-6">
            <label for="rol_id" class="form-label">Rol *</label>
            <select id="rol_id" name="rol_id" class="form-select" required>
                <option value="">Selecciona...</option>
                <?php foreach ($roles as $rol): ?>
                    <option value="<?= (int)$rol['id'] ?>"
                        <?= (string)($datos['rol_id'] ?? '') === (string)$rol['id'] ? 'selected' : '' ?>>
                        <?= e($rol['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="texto-error-campo">Selecciona un rol.</div>
        </div>
        <div class="col-sm-6">
            <label for="estado" class="form-label">Estado *</label>
            <select id="estado" name="estado" class="form-select" required>
                <option value="activo" <?= ($datos['estado'] ?? 'activo') === 'activo' ? 'selected' : '' ?>>Activo</option>
                <option value="inactivo" <?= ($datos['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <!-- Contraseñas -->
        <div class="col-sm-6">
            <label for="clave" class="form-label">Contraseña <?= $esEdicion ? '(opcional)' : '*' ?></label>
            <div class="input-con-icone">
                <i class="bi bi-lock" aria-hidden="true"></i>
                <input type="password" id="clave" name="clave" class="form-control clave-visible"
                       placeholder="<?= $esEdicion ? 'Déjala vacía para no cambiar' : 'Mínimo 8 caracteres' ?>"
                       minlength="8"
                       <?= $esEdicion ? '' : 'required' ?>>
                <button type="button" class="alternar-clave bi bi-eye"
                        data-alternar-clave="clave" aria-label="Mostrar u ocultar contraseña"></button>
            </div>
            <p class="texto-ayuda">Mínimo 8 caracteres, con mayúscula y número.</p>
            <div class="texto-error-campo">La contraseña debe tener al menos 8 caracteres, una mayúscula y un número.</div>
        </div>
        <div class="col-sm-6">
            <label for="clave_confirmacion" class="form-label">Confirmar contraseña <?= $esEdicion ? '(opcional)' : '*' ?></label>
            <div class="input-con-icone">
                <i class="bi bi-lock" aria-hidden="true"></i>
                <input type="password" id="clave_confirmacion" name="clave_confirmacion" class="form-control clave-visible"
                       placeholder="Repite la contraseña"
                       minlength="8"
                       <?= $esEdicion ? '' : 'required' ?>>
                <button type="button" class="alternar-clave bi bi-eye"
                        data-alternar-clave="clave_confirmacion" aria-label="Mostrar u ocultar contraseña"></button>
            </div>
            <div class="texto-error-campo">Ambas contraseñas deben coincidir.</div>
        </div>
    </div>

    <hr class="my-4">

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle" aria-hidden="true"></i> <?= e($etiquetaBoton) ?>
        </button>
        <a href="<?= url('usuarios.php') ?>" class="btn btn-suave">Cancelar</a>
    </div>
</form>
