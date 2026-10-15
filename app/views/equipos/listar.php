<?php
/**
 * SpaceFlow — Vista: listado de equipos (biblioteca).
 *
 * Tabla con búsqueda, filtro de estado y paginación resueltos por el
 * servidor. Incluye las acciones de edición y eliminación (confirmada
 * en modal), que en el servicio deciden entre baja lógica y borrado.
 *
 * @package SpaceFlow\Views
 *
 * @var array<int, array<string, mixed>> $equipos
 * @var string $busqueda
 * @var string $filtroEstado
 * @var int $pagina
 * @var int $totalPaginas
 * @var int $total
 */

/** Etiquetas y clases visuales por estado del equipo. */
$estados = [
    'disponible'    => ['Disponible', 'badge-activo', 'bi-check-circle'],
    'prestado'      => ['Prestado', 'badge-pendiente', 'bi-arrow-up-right-circle'],
    'mantenimiento' => ['Mantenimiento', 'badge-warning', 'bi-tools'],
    'baja'          => ['Dado de baja', 'badge-inactivo', 'bi-x-circle'],
];

/** Construye la URL conservando los filtros actuales. */
$enlacePagina = static fn(int $n): string => url('equipos.php?' . http_build_query(array_merge(
    $_GET,
    ['pagina' => $n]
)));
?>
<div class="sf-pagina-cabecera">
    <div>
        <h2>Catálogo de equipos</h2>
        <p>Portátiles disponibles para préstamo en la biblioteca institucional.</p>
    </div>
    <a href="<?= url('equipo_nuevo.php') ?>" class="btn btn-primary">
        <i class="bi bi-laptop" aria-hidden="true"></i> Registrar equipo
    </a>
</div>

<?php foreach (flash_obtener() as $alerta): ?>
    <div class="alerta-sf alerta-<?= in_array($alerta['tipo'], ['exito', 'aviso'], true) ? ($alerta['tipo'] === 'exito' ? 'exito' : 'aviso') : 'error' ?> mb-3"
         role="alert" data-auto-cerrar>
        <i class="bi <?= $alerta['tipo'] === 'exito' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>" aria-hidden="true"></i>
        <span><?= e($alerta['mensaje']) ?></span>
    </div>
<?php endforeach; ?>

<div class="tarjeta">
    <!-- Buscador y filtros (envían la consulta al servidor) -->
    <div class="tarjeta-cabecera">
        <form method="get" action="<?= url('equipos.php') ?>" class="barra-herramientas w-100">
            <div class="input-con-icone flex-grow-1" style="min-width: 220px; max-width: 380px;">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" name="busqueda" class="form-control"
                       placeholder="Buscar por código, nombre o marca..."
                       aria-label="Buscar equipos" value="<?= e($busqueda) ?>">
            </div>

            <select name="estado" class="form-select" style="max-width: 190px;" aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                <?php foreach ($estados as $clave => [$texto]): ?>
                    <option value="<?= e($clave) ?>" <?= $filtroEstado === $clave ? 'selected' : '' ?>>
                        <?= e($texto) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-suave">
                <i class="bi bi-funnel" aria-hidden="true"></i> Filtrar
            </button>

            <?php if ($busqueda !== '' || $filtroEstado !== ''): ?>
                <a href="<?= url('equipos.php') ?>" class="btn btn-suave">
                    <i class="bi bi-x-circle" aria-hidden="true"></i> Limpiar
                </a>
            <?php endif; ?>

            <span class="texto-auxiliar ms-auto">
                <?= (int)$total ?> equipo(s) en total
            </span>
        </form>
    </div>

    <!-- Tabla -->
    <?php if ($equipos === []): ?>
        <div class="estado-vacio">
            <span class="estado-vacio-icone"><i class="bi bi-laptop" aria-hidden="true"></i></span>
            <?php $conFiltros = $busqueda !== '' || $filtroEstado !== ''; ?>
            <h3><?= $conFiltros ? 'No se encontraron equipos' : 'Aún no hay equipos registrados' ?></h3>
            <p><?= $conFiltros
                    ? 'Ajusta la búsqueda o los filtros para encontrar lo que buscas.'
                    : 'Registra el primer portátil para comenzar a prestarlo.' ?></p>
            <?php if (!$conFiltros): ?>
                <a href="<?= url('equipo_nuevo.php') ?>" class="btn btn-primary mt-3">
                    <i class="bi bi-laptop" aria-hidden="true"></i> Registrar equipo
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="deslizar-tabla">
            <table class="table tabla-sf align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Equipo</th>
                        <th scope="col">Marca</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Registrado</th>
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipos as $equipo): ?>
                        <?php [$texto, $clase] = $estados[(string)$equipo['estado']] ?? ['—', 'badge-info']; ?>
                        <tr>
                            <td>
                                <span class="celda-usuario">
                                    <span class="sf-avatar"><i class="bi bi-laptop" aria-hidden="true"></i></span>
                                    <span>
                                        <span class="nombre"><?= e($equipo['codigo']) ?></span>
                                        <span class="correo"><?= e($equipo['nombre']) ?></span>
                                    </span>
                                </span>
                            </td>
                            <td class="texto-auxiliar"><?= e($equipo['marca'] ?? '—') ?></td>
                            <td>
                                <span class="badge-sf <?= e($clase) ?>"><?= e($texto) ?></span>
                            </td>
                            <td class="texto-auxiliar">
                                <?= e(date('d/m/Y', strtotime((string)$equipo['fecha_creacion']))) ?>
                            </td>
                            <td>
                                <span class="acciones-fila">
                                    <a class="btn-icono" href="<?= url('equipo_editar.php?id=' . (int)$equipo['id']) ?>"
                                       title="Editar equipo" aria-label="Editar el equipo <?= e($equipo['codigo']) ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <?php if ((string)$equipo['estado'] === 'baja'): ?>
                                        <span class="btn-icono" style="opacity:.4;"
                                              title="El equipo ya está dado de baja"
                                              aria-label="Acción no disponible">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </span>
                                    <?php else: ?>
                                        <button type="button" class="btn-icono peligro" title="Eliminar equipo"
                                                aria-label="Eliminar el equipo <?= e($equipo['codigo']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalConfirmacion"
                                                data-confirmar="al equipo <?= e($equipo['codigo']) ?> (<?= e($equipo['nombre']) ?>)"
                                                data-url="<?= url('equipo_eliminar.php?id=' . (int)$equipo['id']) ?>">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación del servidor -->
        <?php if ($totalPaginas > 1): ?>
            <div class="tarjeta-cabecera border-top-0 justify-content-end">
                <nav aria-label="Paginación de equipos">
                    <ul class="pagination paginacion mb-0">
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $pagina > 1 ? e($enlacePagina($pagina - 1)) : '#' ?>"
                               <?= $pagina <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e($enlacePagina($i)) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $pagina < $totalPaginas ? e($enlacePagina($pagina + 1)) : '#' ?>"
                               <?= $pagina >= $totalPaginas ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Siguiente</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal de confirmación antes de eliminar -->
<div class="modal fade" id="modalConfirmacion" tabindex="-1" aria-labelledby="modalConfirmacionTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalConfirmacionTitulo">
                    <i class="bi bi-exclamation-triangle text-ambar me-2" aria-hidden="true"></i>Confirmar eliminación
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                Vas a eliminar <strong id="confirmacionTexto">este registro</strong>.
                <?php // El servicio decide: si tiene reservas, se da de baja lógica. ?>
                Si el equipo ya tiene reservas registradas, se dará de baja conservando su historial.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-suave" data-bs-dismiss="modal">Cancelar</button>
                <form method="post" id="confirmacionFormulario" action="#" class="m-0">
                    <?php csrf_campo(); ?>
                    <button type="submit" class="btn btn-peligro">
                        <i class="bi bi-trash" aria-hidden="true"></i> Sí, eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
