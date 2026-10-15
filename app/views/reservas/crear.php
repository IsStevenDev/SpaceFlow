<?php
/**
 * SpaceFlow — Vista: nueva reserva de portátil.
 *
 * El formulario se completa en dos pasos:
 *  1. La franja (fecha, inicio y duración) se envía con GET para que
 *     el servidor calcule qué portátiles están realmente libres.
 *  2. La confirmación se envía con POST (con CSRF) y el servidor
 *     vuelve a validar todo antes de guardar.
 *
 * Sin JavaScript el botón "Ver disponibilidad" hace el paso 1; con
 * JavaScript el formulario se autoenvía al cambiar cualquier campo.
 *
 * @package SpaceFlow\Views
 *
 * @var array<int, array<string, mixed>> $disponibles
 * @var string $fecha
 * @var string $horaInicio
 * @var int $duracion
 * @var string $horaFin
 * @var bool $esHoy
 */

/** Bloques de inicio permitidos: 6:00 a. m. a 5:00 p. m. (en punto). */
$bloques = [];
for ($h = HORA_BIBLIOTECA_INICIO; $h < HORA_BIBLIOTECA_FIN; $h++) {
    $bloques[sprintf('%02d:00', $h)] = sprintf('%d:00 %s', $h, $h < 12 ? 'a. m.' : 'p. m.');
}

/** Formato legible de la hora (24h -> 12h). */
$legible = static function(string $hora): string {
    [$h, $m] = explode(':', $hora);
    $h = (int)$h;
    return sprintf('%d:%s %s', $h === 0 ? 12 : ($h > 12 ? $h - 12 : $h), $m, $h < 12 ? 'a. m.' : 'p. m.');
};

$maxFecha = date('Y-m-d', strtotime('+' . DIAS_ANTELACION_MAX . ' days'));

/** Duración máxima que cabe en la franja elegida (cierre a las 18:00). */
$duracionMaxima = min(MAX_HORAS_PRESTAMO, HORA_BIBLIOTECA_FIN - (int)substr($horaInicio, 0, 2));
?>
<div class="sf-pagina-cabecera">
    <div>
        <h2>Nueva reserva</h2>
        <p>Solicita el préstamo de un portátil por bloques de una hora (máx. <?= MAX_HORAS_PRESTAMO ?>).</p>
    </div>
    <a href="<?= url('reservas.php') ?>" class="btn btn-suave">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a mis reservas
    </a>
</div>

<?php foreach (flash_obtener() as $alerta): ?>
    <div class="alerta-sf alerta-<?= $alerta['tipo'] === 'exito' ? 'exito' : ($alerta['tipo'] === 'aviso' ? 'aviso' : 'error') ?> mb-3"
         role="alert" data-auto-cerrar>
        <i class="bi <?= $alerta['tipo'] === 'exito' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>" aria-hidden="true"></i>
        <span><?= e($alerta['mensaje']) ?></span>
    </div>
<?php endforeach; ?>

<?php if ($esHoy): ?>
    <div class="alerta-sf alerta-aviso mb-3" role="alert">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
        <span>Estás reservando para <strong>hoy</strong>. Las solicitudes del mismo día dependen de la
            <strong>disponibilidad actual</strong> del equipo en la biblioteca; confirma con el personal al recogerlo.</span>
    </div>
<?php endif; ?>

<div class="tarjeta formulario-panel">
    <div class="tarjeta-cabecera">
        <div>
            <h3>1. Elige la franja horaria</h3>
            <p class="texto-auxiliar">La biblioteca atiende de 6:00 a. m. a 6:00 p. m. Hoy es <?= e(date('d/m/Y')) ?>.</p>
        </div>
    </div>
    <div class="tarjeta-cuerpo">
        <!-- Paso 1: disponibilidad (GET) -->
        <form method="get" action="<?= url('reserva_nueva.php') ?>" class="row g-3" data-buscar-disponibilidad>
            <div class="col-md-4">
                <label for="campoFecha" class="form-label">Fecha <span class="text-rojo">*</span></label>
                <input type="date" id="campoFecha" name="fecha" class="form-control"
                       value="<?= e($fecha) ?>" min="<?= e(date('Y-m-d')) ?>" max="<?= e($maxFecha) ?>" required>
                <div class="form-text">Hoy o hasta <?= DIAS_ANTELACION_MAX ?> días adelante.</div>
            </div>

            <div class="col-md-4">
                <label for="campoInicio" class="form-label">Hora de inicio <span class="text-rojo">*</span></label>
                <select id="campoInicio" name="hora_inicio" class="form-select" required>
                    <?php foreach ($bloques as $clave => $texto): ?>
                        <option value="<?= e($clave) ?>" <?= $horaInicio === $clave ? 'selected' : '' ?>>
                            <?= e($texto) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="campoDuracion" class="form-label">Duración <span class="text-rojo">*</span></label>
                <select id="campoDuracion" name="duracion" class="form-select" required>
                    <?php for ($d = 1; $d <= $duracionMaxima; $d++): ?>
                        <option value="<?= $d ?>" <?= $duracion === $d ? 'selected' : '' ?>>
                            <?= $d ?> hora<?= $d > 1 ? 's' : '' ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-12 d-flex align-items-center gap-3 flex-wrap">
                <button type="submit" class="btn btn-suave">
                    <i class="bi bi-search" aria-hidden="true"></i> Ver disponibilidad
                </button>
                <span class="badge-sf badge-info">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    Franja: <?= e($legible($horaInicio)) ?> – <?= e($legible($horaFin)) ?>
                </span>
            </div>
        </form>
    </div>
</div>

<div class="tarjeta formulario-panel mt-3">
    <div class="tarjeta-cabecera">
        <div>
            <h3>2. Confirma la reserva</h3>
            <p class="texto-auxiliar">
                Portátiles <strong>libres</strong> para la franja seleccionada
                (<?= e(date('d/m/Y', strtotime($fecha))) ?>, <?= e($legible($horaInicio)) ?>–<?= e($legible($horaFin)) ?>).
            </p>
        </div>
    </div>
    <div class="tarjeta-cuerpo">
        <?php if ($disponibles === []): ?>
            <div class="estado-vacio">
                <span class="estado-vacio-icone"><i class="bi bi-laptop" aria-hidden="true"></i></span>
                <h3>No hay portátiles disponibles en esa franja</h3>
                <p>Todos los equipos reservables ya están ocupados. Prueba con otra hora, otra duración u otra fecha.</p>
            </div>
        <?php else: ?>
            <form method="post" action="<?= url('reserva_nueva.php') ?>" data-validar data-cargando novalidate>
                <?php csrf_campo(); ?>
                <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
                <input type="hidden" name="hora_inicio" value="<?= e($horaInicio) ?>">
                <input type="hidden" name="duracion" value="<?= (int)$duracion ?>">

                <div class="row g-3">
                    <div class="col-12">
                        <label for="campoEquipo" class="form-label">Portátil <span class="text-rojo">*</span></label>
                        <select id="campoEquipo" name="equipo_id" class="form-select" required>
                            <?php foreach ($disponibles as $equipo): ?>
                                <option value="<?= (int)$equipo['id'] ?>">
                                    <?= e($equipo['codigo']) ?> — <?= e($equipo['nombre']) ?>
                                    <?= $equipo['marca'] ? '(' . e($equipo['marca']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            <?= count($disponibles) ?> portátil(es) libre(s) en esta franja.
                            <?php // Un equipo "prestado" se muestra si su devolución es anterior a esta franja. ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="campoMotivo" class="form-label">Motivo (opcional)</label>
                        <input type="text" id="campoMotivo" name="motivo" class="form-control"
                               placeholder="Taller, trabajo de grado, investigación..." maxlength="255">
                        <div class="form-text">Máximo 255 caracteres. Ayuda a la biblioteca a planificar.</div>
                    </div>

                    <div class="col-12">
                        <div class="alerta-sf alerta-info" role="note">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            <span>Recuerda llegar a la biblioteca a la hora reservada. El equipo te será entregado
                                por el personal administrativo y deberás devolverlo antes de las
                                <?= e($legible($horaFin)) ?>.</span>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-calendar2-check" aria-hidden="true"></i> Confirmar reserva
                    </button>
                    <a href="<?= url('reservas.php') ?>" class="btn btn-suave">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
