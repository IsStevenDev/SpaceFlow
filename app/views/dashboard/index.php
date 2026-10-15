<?php
/**
 * SpaceFlow — Vista del dashboard administrativo.
 *
 * Presenta las estadísticas del sistema, la actividad reciente y los
 * accesos rápidos. Los datos llegan desde el controlador; en la Etapa 2
 * son temporales para validar el diseño y se sustituirán por consultas
 * reales a MySQL en la Etapa 4.
 *
 * @package SpaceFlow\Views
 *
 * @var array{total:int,activos:int,inactivos:int}   $estadisticas
 * @var array<int, array{rol_id:int, rol_nombre:string, cantidad:int}> $distribucion
 * @var array<int, array{icone:string, color:string, usuario:string, mensaje:string, hora:string}> $actividad
 * @var bool $esAdmin true si la sesión pertenece a un administrador.
 */

/** Suma total para calcular los porcentajes de las barras. */
$totalDistribucion = array_sum(array_column($distribucion, 'cantidad'));
$porcentajeDe = static fn(int $cantidad): int =>
    $totalDistribucion > 0 ? (int)round($cantidad * 100 / $totalDistribucion) : 0;

/** Iniciales para los avatares de la actividad reciente. */
$iniciales = static fn(string $nombre): string => strtoupper(implode('', array_map(
    static fn(string $p): string => mb_substr($p, 0, 1),
    array_slice(explode(' ', trim($nombre)), 0, 2)
)));

/** Etiquetas y clases visuales por estado de reserva. */
$estadosReserva = [
    'activa'    => ['Activa', 'badge-info'],
    'prestamo'  => ['En préstamo', 'badge-pendiente'],
    'entregada' => ['Entregada', 'badge-activo'],
    'cancelada' => ['Cancelada', 'badge-inactivo'],
    'vencida'   => ['Vencida', 'badge-error'],
];

/** Cuenta cuántos equipos hay por estado en el catálogo. */
$equiposDisponibles = (int)($biblioteca['equipos']['disponibles'] ?? 0);
$equiposTotales     = (int)($biblioteca['equipos']['total'] ?? 0);
$reservasHoy        = (int)($biblioteca['reservas']['hoy'] ?? 0);
$reservasVencer     = (int)($biblioteca['reservas']['vencer'] ?? 0);
?>
<div class="sf-pagina-cabecera">
    <div>
        <h2><?= $esAdmin ? 'Resumen general' : 'Bienvenido(a), ' . e($_SESSION['usuario']['nombre'] ?? '') ?></h2>
        <p><?= $esAdmin
                ? 'Estado de la plataforma y actividad de la comunidad institucional.'
                : 'Este es tu espacio personal en ' . NOMBRE_APP . '.' ?></p>
    </div>
</div>

<?php if (!$esAdmin): ?>
    <!-- Vista reducida del usuario estándar: sin estadísticas globales. -->
    <div class="alerta-sf alerta-info mb-4">
        <i class="bi bi-person-circle" aria-hidden="true"></i>
        <span>Desde tu perfil puedes actualizar tu nombre, correo, documento y programa,
            así como cambiar tu contraseña cuando quieras.</span>
    </div>

    <!-- Mis próximas reservas (con cuenta regresiva para las de hoy) -->
    <div class="tarjeta mb-4">
        <div class="tarjeta-cabecera">
            <div>
                <h3>Mis próximas reservas</h3>
                <p class="texto-auxiliar">Portátiles que tienes reservados o en préstamo ahora mismo.</p>
            </div>
            <a href="<?= url('reserva_nueva.php') ?>" class="btn btn-suave">
                <i class="bi bi-calendar-plus" aria-hidden="true"></i> Nueva reserva
            </a>
        </div>
        <div class="tarjeta-cuerpo">
            <?php if (empty($proximasReservas)): ?>
                <div class="estado-vacio">
                    <span class="estado-vacio-icone"><i class="bi bi-calendar2-week" aria-hidden="true"></i></span>
                    <h3>No tienes reservas próximas</h3>
                    <p>Solicita el préstamo de un portátil y aparecerá aquí con su cuenta regresiva.</p>
                    <a href="<?= url('reserva_nueva.php') ?>" class="btn btn-primary mt-3">
                        <i class="bi bi-calendar-plus" aria-hidden="true"></i> Crear una reserva
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($proximasReservas as $reserva): ?>
                        <?php
                        [$texto, $clase] = $estadosReserva[(string)$reserva['estado']] ?? ['—', 'badge-info'];
                        $esHoy = $reserva['fecha'] === date('Y-m-d');
                        $enCurso = $esHoy && in_array($reserva['estado'], ['activa', 'prestamo'], true);
                        $finEpoch = strtotime($reserva['fecha'] . ' ' . $reserva['hora_fin']);
                        ?>
                        <div class="col-md-6">
                            <a class="acceso-rapido flex-column align-items-start h-100"
                               href="<?= url('reservas.php') ?>">
                                <span class="d-flex align-items-center gap-2 w-100">
                                    <i class="bi bi-laptop" aria-hidden="true"></i>
                                    <strong><?= e($reserva['equipo_codigo']) ?></strong>
                                    <span class="badge-sf <?= e($clase) ?> ms-auto"><?= e($texto) ?></span>
                                </span>
                                <span class="texto-auxiliar d-block mt-1">
                                    <?= e(date('d/m/Y', strtotime((string)$reserva['fecha']))) ?> ·
                                    <?= e(substr((string)$reserva['hora_inicio'], 0, 5)) ?>–<?= e(substr((string)$reserva['hora_fin'], 0, 5)) ?>
                                </span>
                                <?php if ($enCurso): ?>
                                    <span class="badge-sf badge-warning mt-2"
                                          data-cuenta-regresiva="<?= (int)$finEpoch ?>"
                                          data-reserva-id="<?= (int)$reserva['id'] ?>">
                                        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                        <span data-texto-cuenta>Cargando...</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Estadísticas principales (solo administradores) -->
<?php if ($esAdmin): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="kpi">
            <span class="kpi-icone noche"><i class="bi bi-people" aria-hidden="true"></i></span>
            <span>
                <span class="kpi-valor"><?= (int)$estadisticas['total'] ?></span>
                <span class="kpi-etiqueta d-block">Usuarios registrados</span>
            </span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi">
            <span class="kpi-icone turquesa"><i class="bi bi-person-check" aria-hidden="true"></i></span>
            <span>
                <span class="kpi-valor"><?= (int)$estadisticas['activos'] ?></span>
                <span class="kpi-etiqueta d-block">Usuarios activos</span>
            </span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi">
            <span class="kpi-icone rojo"><i class="bi bi-person-x" aria-hidden="true"></i></span>
            <span>
                <span class="kpi-valor"><?= (int)$estadisticas['inactivos'] ?></span>
                <span class="kpi-etiqueta d-block">Usuarios inactivos</span>
            </span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi">
            <span class="kpi-icone azul"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
            <span>
                <span class="kpi-valor"><?= count($distribucion) ?></span>
                <span class="kpi-etiqueta d-block">Roles configurados</span>
            </span>
        </div>
    </div>
</div>

<!-- KPIs de la biblioteca (equipos y reservas) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="kpi">
            <span class="kpi-icone turquesa"><i class="bi bi-laptop" aria-hidden="true"></i></span>
            <span>
                <span class="kpi-valor" data-contar="<?= $equiposDisponibles ?>"><?= $equiposDisponibles ?></span>
                <span class="kpi-etiqueta d-block">Equipos disponibles (de <?= $equiposTotales ?>)</span>
            </span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi">
            <span class="kpi-icone azul"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
            <span>
                <span class="kpi-valor" data-contar="<?= $reservasHoy ?>"><?= $reservasHoy ?></span>
                <span class="kpi-etiqueta d-block">Reservas activas hoy</span>
            </span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi">
            <span class="kpi-icone ambar"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
            <span>
                <span class="kpi-valor" data-contar="<?= $reservasVencer ?>"><?= $reservasVencer ?></span>
                <span class="kpi-etiqueta d-block">Próximas a vencer (30 min)</span>
            </span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi">
            <span class="kpi-icone noche"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></span>
            <span>
                <span class="kpi-valor"><?= (int)($biblioteca['reservas']['prestamo'] ?? 0) ?></span>
                <span class="kpi-etiqueta d-block">Equipos en préstamo</span>
            </span>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Panel de vencimientos (solo administradores, actualizado por polling) -->
    <?php if ($esAdmin): ?>
    <div class="col-12">
        <div class="tarjeta" data-panel-vencimientos>
            <div class="tarjeta-cabecera">
                <div>
                    <h3><i class="bi bi-bell me-1" aria-hidden="true"></i> Próximas a vencer</h3>
                    <p class="texto-auxiliar">Reservas que terminan en los próximos <?= MINUTOS_AVISO_TEMPRANO ?> minutos y préstamos atrasados. Se actualiza solo.</p>
                </div>
                <span class="badge-sf badge-info" data-vencimientos-contador>
                    <?= count($panel['vencer']) ?> por vencer · <?= count($panel['atrasados']) ?> atrasado(s)
                </span>
            </div>
            <div class="tarjeta-cuerpo" data-vencimientos-lista>
                <?php if ($panel['vencer'] === [] && $panel['atrasados'] === []): ?>
                    <p class="texto-auxiliar mb-0">
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                        No hay reservas por vencer ni préstamos atrasados en este momento.
                    </p>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($panel['vencer'] as $reserva): ?>
                            <div class="col-md-6">
                                <div class="alerta-sf alerta-aviso mb-0">
                                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                    <span>
                                        PC <strong><?= e($reserva['equipo_codigo']) ?></strong> — <?= e($reserva['usuario_nombre']) ?>
                                        termina a las <?= e(substr((string)$reserva['hora_fin'], 0, 5)) ?>
                                        (<span data-cuenta-regresiva="<?= (int)strtotime($reserva['fecha'] . ' ' . $reserva['hora_fin']) ?>"
                                               data-reserva-id="<?= (int)$reserva['id'] ?>"><span data-texto-cuenta>...</span></span>)
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($panel['atrasados'] as $reserva): ?>
                            <div class="col-md-6">
                                <div class="alerta-sf alerta-error mb-0">
                                    <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                                    <span>
                                        PC <strong><?= e($reserva['equipo_codigo']) ?></strong> — <?= e($reserva['usuario_nombre']) ?>
                                        <strong>vencida sin devolución</strong> (hora fin <?= e(substr((string)$reserva['hora_fin'], 0, 5)) ?>)
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- Actividad reciente (solo administradores) -->
    <?php if ($esAdmin): ?>
    <div class="col-lg-7">
        <div class="tarjeta h-100">
            <div class="tarjeta-cabecera">
                <div>
                    <h3>Actividad reciente</h3>
                    <p class="texto-auxiliar">Últimas operaciones registradas en el sistema.</p>
                </div>
            </div>
            <div class="tarjeta-cuerpo">
                <?php if (empty($actividad)): ?>
                    <div class="estado-vacio">
                        <span class="estado-vacio-icone"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
                        <h3>Sin actividad registrada</h3>
                        <p>Las operaciones que se realicen aparecerán aquí.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($actividad as $evento): ?>
                        <div class="actividad-item">
                            <span class="actividad-icone kpi-icone <?= e($evento['color']) ?>">
                                <i class="bi <?= e($evento['icone']) ?>" aria-hidden="true"></i>
                            </span>
                            <span class="actividad-texto">
                                <p><strong><?= e($evento['usuario']) ?></strong> — <?= e($evento['mensaje']) ?></p>
                            </span>
                            <span class="actividad-hora"><?= e($evento['hora']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Columna derecha: distribución + accesos rápidos -->
    <div class="<?= $esAdmin ? 'col-lg-5' : 'col-lg-8 offset-lg-2' ?>">
        <?php if ($esAdmin): ?>
        <div class="tarjeta mb-4">
            <div class="tarjeta-cabecera">
                <div>
                    <h3>Usuarios por rol</h3>
                    <p class="texto-auxiliar">Distribución actual de la comunidad.</p>
                </div>
            </div>
            <div class="tarjeta-cuerpo py-2">
                <?php if ($distribucion === []): ?>
                    <p class="texto-auxiliar mb-0">Sin datos por mostrar.</p>
                <?php else: ?>
                    <?php
                    // Color de barra por posición: la primera en azul, la
                    // segunda en turquesa; el resto, azul suave.
                    $coloresBarra = ['azul', 'turquesa', 'azul'];
                    ?>
                    <?php foreach ($distribucion as $indice => $fila): ?>
                        <div class="barra-rol">
                            <span class="barra-nombre"><?= e($fila['rol_nombre']) ?></span>
                            <span class="barra-track">
                                <span class="barra-relleno <?= e($coloresBarra[$indice % 3] ?? 'azul') ?>"
                                      style="width: <?= $porcentajeDe((int)$fila['cantidad']) ?>%"></span>
                            </span>
                            <span class="barra-valor"><?= (int)$fila['cantidad'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <div>
                    <h3>Accesos rápidos</h3>
                    <p class="texto-auxiliar">Operaciones más frecuentes.</p>
                </div>
            </div>
            <div class="tarjeta-cuerpo">
                <div class="row g-2">
                    <?php if ($esAdmin): ?>
                    <div class="col-12">
                        <a class="acceso-rapido" href="<?= url('usuarios.php') ?>">
                            <i class="bi bi-people" aria-hidden="true"></i>
                            Administrar usuarios
                        </a>
                    </div>
                    <div class="col-12">
                        <a class="acceso-rapido" href="<?= url('equipo_nuevo.php') ?>">
                            <i class="bi bi-laptop" aria-hidden="true"></i>
                            Registrar un equipo
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <a class="acceso-rapido" href="<?= url('reserva_nueva.php') ?>">
                            <i class="bi bi-calendar-plus" aria-hidden="true"></i>
                            Nueva reserva
                        </a>
                    </div>
                    <div class="col-12">
                        <a class="acceso-rapido" href="<?= url('perfil.php') ?>">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            Actualizar mi perfil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
