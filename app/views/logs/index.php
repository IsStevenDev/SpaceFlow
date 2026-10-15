<?php
/**
 * SpaceFlow — Vista: registro de actividad (logs).
 *
 * Presenta las líneas del archivo real logs/aplicacion.log en formato
 * de tabla, con los niveles INFO/WARNING/ERROR diferenciados y filtro
 * por nivel.
 *
 * @package SpaceFlow\Views
 *
 * @var array<int, array{fecha:string, nivel:string, usuario:string, mensaje:string}> $eventos
 * @var string $nivel Filtro activo ('' = todos).
 */

$claseNivel = [
    'INFO'    => 'badge-info',
    'WARNING' => 'badge-warning',
    'ERROR'   => 'badge-error',
];

/** Icono según el nivel para la columna visual. */
$iconeNivel = [
    'INFO'    => ['bi-info-circle', 'texto-azul'],
    'WARNING' => ['bi-exclamation-triangle', 'texto-ambar'],
    'ERROR'   => ['bi-x-octagon', 'texto-rojo'],
];

/** URL conservando el filtro de nivel. */
$enlaceNivel = static fn(string $valor): string => url('logs.php?' . http_build_query(
    $valor === '' ? [] : ['nivel' => $valor]
));
?>
<div class="sf-pagina-cabecera">
    <div>
        <h2>Registro de actividad</h2>
        <p>Trazabilidad de las operaciones del sistema. Últimos 200 eventos.</p>
    </div>
</div>

<div class="alerta-sf alerta-info mb-3">
    <i class="bi bi-shield-lock" aria-hidden="true"></i>
    <span>Los registros se escriben en <code>logs/aplicacion.log</code> y solo son
        consultables por administradores. No contienen contraseñas ni tokens.</span>
</div>

<div class="tarjeta">
    <!-- Filtro por nivel -->
    <div class="tarjeta-cabecera">
        <div class="d-flex gap-2 flex-wrap" role="group" aria-label="Filtrar por nivel">
            <a href="<?= e($enlaceNivel('')) ?>"
               class="btn btn-sm <?= $nivel === '' ? 'btn-primary' : 'btn-suave' ?>">Todos</a>
            <a href="<?= e($enlaceNivel('INFO')) ?>"
               class="btn btn-sm <?= $nivel === 'INFO' ? 'btn-primary' : 'btn-suave' ?>">INFO</a>
            <a href="<?= e($enlaceNivel('WARNING')) ?>"
               class="btn btn-sm <?= $nivel === 'WARNING' ? 'btn-primary' : 'btn-suave' ?>">WARNING</a>
            <a href="<?= e($enlaceNivel('ERROR')) ?>"
               class="btn btn-sm <?= $nivel === 'ERROR' ? 'btn-primary' : 'btn-suave' ?>">ERROR</a>
        </div>
        <span class="texto-auxiliar"><?= count($eventos) ?> evento(s)</span>
    </div>

    <?php if ($eventos === []): ?>
        <div class="estado-vacio">
            <span class="estado-vacio-icone"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
            <h3><?= $nivel === '' ? 'Sin registros todavía' : 'Sin eventos de este nivel' ?></h3>
            <p><?= $nivel === ''
                    ? 'Las operaciones que se realicen en el sistema aparecerán aquí.'
                    : 'No hay eventos ' . e($nivel) . ' en las últimas entradas.' ?></p>
        </div>
    <?php else: ?>
        <div class="deslizar-tabla">
            <table class="table tabla-sf align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Fecha y hora</th>
                        <th scope="col">Nivel</th>
                        <th scope="col">Usuario</th>
                        <th scope="col">Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventos as $evento): ?>
                        <?php [$icone, $color] = $iconeNivel[$evento['nivel']] ?? $iconeNivel['INFO']; ?>
                        <tr>
                            <td class="texto-auxiliar text-nowrap"><?= e($evento['fecha']) ?></td>
                            <td>
                                <span class="badge-sf <?= $claseNivel[$evento['nivel']] ?? 'badge-info' ?>">
                                    <?= e($evento['nivel']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="d-flex align-items-center gap-2">
                                    <i class="bi <?= e($icone) ?> <?= e($color) ?>" aria-hidden="true"></i>
                                    <span class="texto-truncado"><?= e($evento['usuario']) ?></span>
                                </span>
                            </td>
                            <td><?= e($evento['mensaje']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
