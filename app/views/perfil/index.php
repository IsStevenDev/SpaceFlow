<?php
/**
 * SpaceFlow — Vista: perfil personal.
 *
 * A diferencia del formulario administrativo, aquí NO existen los
 * campos rol ni estado: el usuario estándar no puede autoasignarse
 * permisos. La contraseña solo cambia si se completa el bloque.
 *
 * @package SpaceFlow\Views
 *
 * @var array<string, mixed> $usuario
 * @var array<int, array<string, mixed>> $programas
 */

$tipos = ['CC' => 'Cédula de ciudadanía', 'TI' => 'Tarjeta de identidad',
          'CE' => 'Cédula de extranjería', 'PP' => 'Pasaporte'];

$porNivel = [];
foreach ($programas as $programa) {
    $porNivel[$programa['nivel']][] = $programa;
}
$niveles = ['tecnica' => 'Técnicas Profesionales', 'tecnologia' => 'Tecnologías',
            'profesional' => 'Profesionales'];
?>
<div class="sf-pagina-cabecera">
    <div>
        <h2>Mi perfil</h2>
        <p>Actualiza tu información personal y contraseña.</p>
    </div>
</div>

<?php foreach (flash_obtener() as $alerta): ?>
    <div class="alerta-sf alerta-<?= $alerta['tipo'] === 'exito' ? 'exito' : 'error' ?> mb-3" role="alert" data-auto-cerrar>
        <i class="bi <?= $alerta['tipo'] === 'exito' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>" aria-hidden="true"></i>
        <span><?= e($alerta['mensaje']) ?></span>
    </div>
<?php endforeach; ?>

<div class="row g-4">
    <!-- Tarjeta resumen -->
    <div class="col-lg-4">
        <div class="tarjeta">
            <div class="tarjeta-cuerpo text-center p-4">
                <span class="sf-avatar mx-auto mb-3 d-flex" style="width: 72px; height: 72px; font-size: 1.35rem;">
                    <?= e((new \App\Models\Usuario())->setNombre((string)$usuario['nombre'])->iniciales()) ?>
                </span>
                <h3 class="mb-1"><?= e($usuario['nombre']) ?></h3>
                <p class="texto-auxiliar mb-3"><?= e($usuario['correo']) ?></p>
                <span class="badge-sf <?= $usuario['rol_nombre'] === 'Administrador' ? 'badge-admin' : 'badge-usuario' ?>">
                    <?= e($usuario['rol_nombre']) ?>
                </span>
                <span class="badge-sf <?= $usuario['estado'] === 'activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                    <?= $usuario['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                </span>
                <p class="texto-auxiliar mt-3 mb-0">
                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                    Tu rol y estado los administra el equipo de la institución.
                </p>
            </div>
        </div>
    </div>

    <!-- Formulario -->
    <div class="col-lg-8">
        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <div>
                    <h3>Información personal</h3>
                    <p class="texto-auxiliar">Los campos marcados con * son obligatorios.</p>
                </div>
            </div>
            <div class="tarjeta-cuerpo">
                <form method="post" action="<?= url('perfil.php') ?>" data-cargando data-validar novalidate>
                    <?php csrf_campo(); ?>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="nombre" class="form-label">Nombre completo *</label>
                            <input type="text" id="nombre" name="nombre" class="form-control"
                                   minlength="3" maxlength="100" required
                                   value="<?= e($usuario['nombre']) ?>">
                            <div class="texto-error-campo">Ingresa un nombre de al menos 3 caracteres.</div>
                        </div>

                        <div class="col-12">
                            <label for="correo" class="form-label">Correo institucional *</label>
                            <div class="input-con-icone">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <input type="email" id="correo" name="correo" class="form-control"
                                       required value="<?= e($usuario['correo']) ?>">
                            </div>
                            <p class="texto-ayuda">Solo se aceptan correos @<?= DOMINIO_CORREO ?>.</p>
                            <div class="texto-error-campo">Ingresa un correo institucional válido.</div>
                        </div>

                        <div class="col-sm-5">
                            <label for="tipo_documento" class="form-label">Tipo de identificación *</label>
                            <select id="tipo_documento" name="tipo_documento" class="form-select" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($tipos as $clave => $etiqueta): ?>
                                    <option value="<?= $clave ?>"
                                        <?= $usuario['tipo_documento'] === $clave ? 'selected' : '' ?>>
                                        <?= e($etiqueta) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="texto-error-campo">Selecciona el tipo de documento.</div>
                        </div>
                        <div class="col-sm-7">
                            <label for="numero_documento" class="form-label">Número de identificación *</label>
                            <input type="text" id="numero_documento" name="numero_documento" class="form-control"
                                   pattern="[0-9A-Za-z]{5,15}" required
                                   value="<?= e($usuario['numero_documento']) ?>">
                            <div class="texto-error-campo">Entre 5 y 15 caracteres alfanuméricos.</div>
                        </div>

                        <div class="col-12">
                            <label for="programa_id" class="form-label">Programa académico *</label>
                            <select id="programa_id" name="programa_id" class="form-select" required>
                                <option value="">Selecciona tu programa...</option>
                                <?php foreach ($niveles as $claveNivel => $tituloNivel): ?>
                                    <?php if (empty($porNivel[$claveNivel])) continue; ?>
                                    <optgroup label="<?= e($tituloNivel) ?>">
                                        <?php foreach ($porNivel[$claveNivel] as $programa): ?>
                                            <option value="<?= (int)$programa['id'] ?>"
                                                <?= (string)$usuario['programa_id'] === (string)$programa['id'] ? 'selected' : '' ?>>
                                                <?= e($programa['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <div class="texto-error-campo">Selecciona tu programa académico.</div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h3 class="h6 text-uppercase texto-auxiliar fw-bold mb-3">
                        <i class="bi bi-key me-1" aria-hidden="true"></i>Cambiar contraseña
                    </h3>
                    <p class="texto-auxiliar mt-n2 mb-3">Déjala vacía si no quieres cambiarla.</p>

                    <div class="row g-3">
                        <div class="col-sm-4">
                            <label for="clave_actual" class="form-label">Contraseña actual</label>
                            <div class="input-con-icone">
                                <i class="bi bi-lock" aria-hidden="true"></i>
                                <input type="password" id="clave_actual" name="clave_actual" class="form-control clave-visible"
                                       autocomplete="current-password">
                                <button type="button" class="alternar-clave bi bi-eye"
                                        data-alternar-clave="clave_actual" aria-label="Mostrar u ocultar contraseña"></button>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <label for="clave" class="form-label">Nueva contraseña</label>
                            <div class="input-con-icone">
                                <i class="bi bi-lock" aria-hidden="true"></i>
                                <input type="password" id="clave" name="clave" class="form-control clave-visible"
                                       minlength="8" autocomplete="new-password">
                                <button type="button" class="alternar-clave bi bi-eye"
                                        data-alternar-clave="clave" aria-label="Mostrar u ocultar contraseña"></button>
                            </div>
                            <p class="texto-ayuda">Mín. 8, con mayúscula y número.</p>
                        </div>
                        <div class="col-sm-4">
                            <label for="clave_confirmacion" class="form-label">Confirmar contraseña</label>
                            <div class="input-con-icone">
                                <i class="bi bi-lock" aria-hidden="true"></i>
                                <input type="password" id="clave_confirmacion" name="clave_confirmacion" class="form-control clave-visible"
                                       minlength="8" autocomplete="new-password">
                                <button type="button" class="alternar-clave bi bi-eye"
                                        data-alternar-clave="clave_confirmacion" aria-label="Mostrar u ocultar contraseña"></button>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i> Guardar cambios
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
