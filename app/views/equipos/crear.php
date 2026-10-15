<?php
/**
 * SpaceFlow — Vista: creación de equipo.
 * Delega el formulario en el parcial compartido.
 *
 * @package SpaceFlow\Views
 *
 * @var array<string, mixed> $equipo
 */
?>
<div class="sf-pagina-cabecera">
    <div>
        <h2>Registrar equipo</h2>
        <p>Añade un portátil al catálogo de préstamo de la biblioteca.</p>
    </div>
    <a href="<?= url('equipos.php') ?>" class="btn btn-suave">
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
            <h3>Datos del equipo</h3>
            <p class="texto-auxiliar">Todos los campos marcados son obligatorios.</p>
        </div>
    </div>
    <div class="tarjeta-cuerpo">
        <?php vista('equipos/formulario', ['equipo' => $equipo, 'accion' => 'crear']); ?>
    </div>
</div>
