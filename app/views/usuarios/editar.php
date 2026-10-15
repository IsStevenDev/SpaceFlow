<?php
/**
 * SpaceFlow — Vista: edición de usuario (panel administrativo).
 *
 * @package SpaceFlow\Views
 *
 * @var array<int, array<string, mixed>> $roles
 * @var array<int, array<string, mixed>> $programas
 * @var array<string, mixed> $usuario
 */
?>
<div class="sf-pagina-cabecera">
    <div>
        <h2>Editar usuario</h2>
        <p>Modifica la información de <strong><?= e($usuario['nombre']) ?></strong>.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('usuario_ver.php?id=' . (int)$usuario['id']) ?>" class="btn btn-suave">
            <i class="bi bi-eye" aria-hidden="true"></i> Ver detalle
        </a>
        <a href="<?= url('usuarios.php') ?>" class="btn btn-suave">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver
        </a>
    </div>
</div>

<?php foreach (flash_obtener() as $alerta): ?>
    <div class="alerta-sf alerta-<?= $alerta['tipo'] === 'exito' ? 'exito' : 'error' ?> mb-3" role="alert">
        <i class="bi <?= $alerta['tipo'] === 'exito' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>" aria-hidden="true"></i>
        <span><?= e($alerta['mensaje']) ?></span>
    </div>
<?php endforeach; ?>

<div class="tarjeta formulario-panel">
    <div class="tarjeta-cabecera">
        <div>
            <h3>Información de la cuenta</h3>
            <p class="texto-auxiliar">La contraseña solo se cambia si se escribe una nueva.</p>
        </div>
    </div>
    <div class="tarjeta-cuerpo">
        <?php vista('usuarios/formulario', [
            'roles'     => $roles,
            'programas' => $programas,
            'usuario'   => $usuario,
            'accion'    => 'editar',
        ]); ?>
    </div>
</div>
