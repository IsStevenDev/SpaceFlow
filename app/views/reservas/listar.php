<?php
/**
 * SpaceFlow — Vista: listado de reservas.
 *
 * El administrador ve todas las reservas con las acciones de entrega,
 * devolución y cancelación; el usuario ve solo las suyas y puede
 * cancelar las que sigan activas. Las reservas de hoy incluyen los
 * datos (atributos data-*) que alimentan la cuenta regresiva en el
 * navegador; la autoridad sobre el tiempo sigue siendo el servidor.
 *
 * @package SpaceFlow\Views
 *
 * @var array<int, array<string, mixed>> $reservas
 * @var bool $esAdmin
 * @var string $busqueda
 * @var string $filtroEstado
 * @var string $filtroFecha
 * @var int $pagina
 * @var int $totalPaginas
 * @var int $total
 */

/** Etiquetas y clases visuales por estado de la reserva. */
$estados = [
    'activa'    => ['Activa', 'badge-info'],
    'prestamo'  => ['En préstamo', 'badge-pendiente'],
    'entregada' => ['Entregada', 'badge-activo'],
    'cancelada' => ['Cancelada', 'badge-inactivo'],
    'vencida'   => ['Vencida', 'badge-error'],
];

/** Etiqueta amigable de la franja horaria. */
$horario = static fn(array $r): string =>
    substr((string)$r['hora_inicio'], 0, 5) . ' – ' . substr((string)$r['hora_fin'], 0, 5);

/** Construye la URL conservando los filtros actuales. */
$enlacePagina = static fn(int $n): string => url('reservas.php?' . http_build_query(array_merge(
    $_GET,
    ['pagina' => $n]
)));
?>
<div class="sf-pagina-cabecera">
    <div>
        <h2><?= $esAdmin ? 'Reservas de la biblioteca' : 'Mis reservas' ?></h2>
        <p><?= $esAdmin
                ? 'Control de préstamos: entrega, devolución y cancelación de portátiles.'
                : 'Consulta y administra tus solicitudes de préstamo de portátiles.' ?></p>
    </div>
    <a href="<?= url('reserva_nueva.php') ?>" class="btn btn-primary">
        <i class="bi bi-calendar-plus" aria-hidden="true"></i> Nueva reserva
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
    <!-- Filtros (envían la consulta al servidor) -->
    <div class="tarjeta-cabecera">
        <form method="get" action="<?= url('reservas.php') ?>" class="barra-herramientas w-100">
            <?php if ($esAdmin): ?>
                <div class="input-con-icone flex-grow-1" style="min-width: 200px; max-width: 320px;">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" name="busqueda" class="form-control"
                           placeholder="Buscar por usuario o equipo..."
                           aria-label="Buscar reservas" value="<?= e($busqueda) ?>">
                </div>
            <?php endif; ?>

            <select name="estado" class="form-select" style="max-width: 180px;" aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                <?php foreach ($estados as $clave => [$texto]): ?>
                    <option value="<?= e($clave) ?>" <?= $filtroEstado === $clave ? 'selected' : '' ?>>
                        <?= e($texto) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="fecha" class="form-control" style="max-width: 175px;"
                   aria-label="Filtrar por fecha" value="<?= e($filtroFecha) ?>">

            <button type="submit" class="btn btn-suave">
                <i class="bi bi-funnel" aria-hidden="true"></i> Filtrar
            </button>

            <?php if (($esAdmin && $busqueda !== '') || $filtroEstado !== '' || $filtroFecha !== ''): ?>
                <a href="<?= url('reservas.php') ?>" class="btn btn-suave">
                    <i class="bi bi-x-circle" aria-hidden="true"></i> Limpiar
                </a>
            <?php endif; ?>

            <span class="texto-auxiliar ms-auto">
                <?= (int)$total ?> reserva(s) en total
            </span>
        </form>
    </div>

    <!-- Tabla -->
    <?php if ($reservas === []): ?>
        <div class="estado-vacio">
            <span class="estado-vacio-icone"><i class="bi bi-calendar2-week" aria-hidden="true"></i></span>
            <?php $conFiltros = ($esAdmin && $busqueda !== '') || $filtroEstado !== '' || $filtroFecha !== ''; ?>
            <h3><?= $conFiltros ? 'No se encontraron reservas' : 'Aún no hay reservas' ?></h3>
            <p><?= $conFiltros
                    ? 'Ajusta la búsqueda o los filtros para encontrar lo que buscas.'
                    : 'Crea tu primera reserva para solicitar un portátil.' ?></p>
            <?php if (!$conFiltros): ?>
                <a href="<?= url('reserva_nueva.php') ?>" class="btn btn-primary mt-3">
                    <i class="bi bi-calendar-plus" aria-hidden="true"></i> Nueva reserva
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="deslizar-tabla">
            <table class="table tabla-sf align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Equipo</th>
                        <?php if ($esAdmin): ?>
                            <th scope="col">Solicitante</th>
                        <?php endif; ?>
                        <th scope="col">Fecha y horario</th>
                        <th scope="col">Estado</th>
                        <?php if ($esAdmin): ?>
                            <th scope="col" class="text-end">Acciones</th>
                        <?php else: ?>
                            <th scope="col" class="text-end">Acción</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservas as $reserva): ?>
                        <?php
                        [$texto, $clase] = $estados[(string)$reserva['estado']] ?? ['—', 'badge-info'];
                        $esHoy = $reserva['fecha'] === date('Y-m-d');
                        // Cuenta regresiva solo para las reservas de hoy activas o en préstamo.
                        $enCurso = $esHoy && in_array($reserva['estado'], ['activa', 'prestamo'], true);
                        $finEpoch = strtotime($reserva['fecha'] . ' ' . $reserva['hora_fin']);
                        ?>
                        <tr>
                            <td>
                                <span class="celda-usuario">
                                    <span class="sf-avatar"><i class="bi bi-laptop" aria-hidden="true"></i></span>
                                    <span>
                                        <span class="nombre"><?= e($reserva['equipo_codigo']) ?></span>
                                        <span class="correo"><?= e($reserva['equipo_nombre']) ?></span>
                                    </span>
                                </span>
                            </td>
                            <?php if ($esAdmin): ?>
                                <td>
                                    <span class="nombre"><?= e($reserva['usuario_nombre']) ?></span>
                                </td>
                            <?php endif; ?>
                            <td>
                                <span class="nombre"><?= e(date('d/m/Y', strtotime((string)$reserva['fecha']))) ?></span>
                                <span class="correo"><?= e($horario($reserva)) ?></span>
                                <?php if ($enCurso): ?>
                                    <span class="badge-sf badge-warning mt-1"
                                          data-cuenta-regresiva="<?= (int)$finEpoch ?>"
                                          data-reserva-id="<?= (int)$reserva['id'] ?>">
                                        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                        <span data-texto-cuenta>Cargando...</span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-sf <?= e($clase) ?>"><?= e($texto) ?></span>
                            </td>
                            <td class="text-end">
                                <span class="acciones-fila">
                                    <?php if ($esAdmin && $reserva['estado'] === 'activa'): ?>
                                        <form method="post" action="<?= url('reserva_entregar.php') ?>" class="m-0">
                                            <?php csrf_campo(); ?>
                                            <input type="hidden" name="id" value="<?= (int)$reserva['id'] ?>">
                                            <button type="submit" class="btn-icono" title="Registrar entrega al estudiante"
                                                    aria-label="Registrar entrega de <?= e($reserva['equipo_codigo']) ?>">
                                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($esAdmin && $reserva['estado'] === 'prestamo'): ?>
                                        <form method="post" action="<?= url('reserva_devolver.php') ?>" class="m-0">
                                            <?php csrf_campo(); ?>
                                            <input type="hidden" name="id" value="<?= (int)$reserva['id'] ?>">
                                            <button type="submit" class="btn-icono" title="Registrar devolución del equipo"
                                                    aria-label="Registrar devolución de <?= e($reserva['equipo_codigo']) ?>">
                                                <i class="bi bi-box-arrow-in-down" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($reserva['estado'] === 'activa'): ?>
                                        <form method="post" action="<?= url('reserva_cancelar.php') ?>" class="m-0"
                                              data-cargando>
                                            <?php csrf_campo(); ?>
                                            <input type="hidden" name="id" value="<?= (int)$reserva['id'] ?>">
                                            <button type="submit" class="btn-icono peligro" title="Cancelar reserva"
                                                    aria-label="Cancelar reserva de <?= e($reserva['equipo_codigo']) ?>">
                                                <i class="bi bi-x-circle" aria-hidden="true"></i>
                                            </button>
                                        </form>
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
                <nav aria-label="Paginación de reservas">
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
