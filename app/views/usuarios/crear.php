<?php
/**
 * SpaceFlow — Vista: creación de usuario.
 * Delega el formulario en el parcial compartido.
 *
 * @package SpaceFlow\Views
 *
 * @var array<int, array<string, mixed>> $roles
 * @var array<int, array<string, mixed>> $programas
 */
?>
<div class="sf-pagina-cabecera">
    <div>
        <h2>Crear usuario</h2>
        <p>Registra una cuenta nueva con correo institucional @<?= DOMINIO_CORREO ?>.</p>
    </div>
    <a href="<?= url('usuarios.php') ?>" class="btn btn-suave">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al listado
    </a>
</div>

<?php foreach (flash_obtener() as $alerta): ?>
    <div class="alerta-sf alerta-<?= $alerta['tipo'] === 'exito' ? 'exito' : 'error' ?> mb-3"
         role="alert" data-auto-cerrar>
        <i class="bi <?= $alerta['tipo'] === 'exito' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>" aria-hidden="true"></i>
        <span><?= e($alerta['mensaje']) ?></span>
    </div>
<?php endforeach; ?>

<div class="tarjeta formulario-panel">
    <div class="tarjeta-cabecera">
        <div>
            <h3>Datos de la cuenta</h3>
            <p class="texto-auxiliar">Todos los campos marcados son obligatorios.</p>
        </div>
    </div>
    <div class="tarjeta-cuerpo">
        <?php vista('usuarios/formulario', [
            'roles'     => $roles,
            'programas' => $programas,
            'usuario'   => null,
            'accion'    => 'crear',
        ]); ?>
    </div>
</div>
