<?php
/**
 * SpaceFlow — Vista: listado de usuarios (SGU).
 *
 * Tabla con búsqueda, filtros de rol/estado y paginación resueltas por
 * el servidor (la autoridad sobre los datos es MySQL; el JavaScript
 * solo refina la página actual en pantalla).
 *
 * @package SpaceFlow\Views
 *
 * @var array<int, array<string, mixed>> $usuarios
 * @var array<int, array<string, mixed>> $roles
 * @var string $busqueda
 * @var string $filtroRol
 * @var string $filtroEstado
 * @var int $pagina
 * @var int $totalPaginas
 * @var int $total
 */

/** Iniciales para el avatar de cada fila. */
$iniciales = static fn(string $nombre): string => strtoupper(implode('', array_map(
    static fn(string $p): string => mb_substr($p, 0, 1),
    array_slice(explode(' ', trim($nombre)), 0, 2)
)));

/** Construye la URL conservando los filtros actuales. */
$enlacePagina = static fn(int $n): string => url('usuarios.php?' . http_build_query(array_merge(
    $_GET,
    ['pagina' => $n]
)));
?>
<div class="sf-pagina-cabecera">
    <div>
        <h2>Gestión de usuarios</h2>
        <p>Consulta, crea, edita y desactiva las cuentas de la comunidad institucional.</p>
    </div>
    <a href="<?= url('usuario_nuevo.php') ?>" class="btn btn-primary">
        <i class="bi bi-person-plus" aria-hidden="true"></i> Crear usuario
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
        <form method="get" action="<?= url('usuarios.php') ?>" class="barra-herramientas w-100">
            <div class="input-con-icone flex-grow-1" style="min-width: 220px; max-width: 380px;">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" name="busqueda" class="form-control"
                       placeholder="Buscar por nombre o correo..."
                       aria-label="Buscar usuarios" value="<?= e($busqueda) ?>">
            </div>

            <select name="rol" class="form-select" style="max-width: 180px;" aria-label="Filtrar por rol">
                <option value="">Todos los roles</option>
                <?php foreach ($roles as $rol): ?>
                    <option value="<?= (int)$rol['id'] ?>"
                        <?= $filtroRol === (string)$rol['id'] ? 'selected' : '' ?>>
                        <?= e($rol['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="estado" class="form-select" style="max-width: 170px;" aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                <option value="activo" <?= $filtroEstado === 'activo' ? 'selected' : '' ?>>Activos</option>
                <option value="inactivo" <?= $filtroEstado === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
            </select>

            <button type="submit" class="btn btn-suave">
                <i class="bi bi-funnel" aria-hidden="true"></i> Filtrar
            </button>

            <?php if ($busqueda !== '' || $filtroRol !== '' || $filtroEstado !== ''): ?>
                <a href="<?= url('usuarios.php') ?>" class="btn btn-suave">
                    <i class="bi bi-x-circle" aria-hidden="true"></i> Limpiar
                </a>
            <?php endif; ?>

            <span class="texto-auxiliar ms-auto">
                <?= (int)$total ?> usuario(s) en total
            </span>
        </form>
    </div>

    <!-- Tabla -->
    <?php if ($usuarios === []): ?>
        <div class="estado-vacio">
            <span class="estado-vacio-icone"><i class="bi bi-people" aria-hidden="true"></i></span>
            <?php // Con filtros activos no hay resultados; sin filtros, la tabla está vacía. ?>
            <?php $conFiltros = $busqueda !== '' || $filtroRol !== '' || $filtroEstado !== ''; ?>
            <h3><?= $conFiltros ? 'No se encontraron usuarios' : 'Aún no hay usuarios registrados' ?></h3>
            <p><?= $conFiltros
                    ? 'Ajusta la búsqueda o los filtros para encontrar lo que buscas.'
                    : 'Crea la primera cuenta para comenzar a usar el sistema.' ?></p>
            <?php if (!$conFiltros): ?>
                <a href="<?= url('usuario_nuevo.php') ?>" class="btn btn-primary mt-3">
                    <i class="bi bi-person-plus" aria-hidden="true"></i> Crear usuario
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="deslizar-tabla">
            <table class="table tabla-sf align-middle mb-0" data-tabla-usuarios>
                <thead>
                    <tr>
                        <th scope="col">Usuario</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Creado</th>
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr data-nombre="<?= e($usuario['nombre']) ?>"
                            data-correo="<?= e($usuario['correo']) ?>">
                            <td>
                                <span class="celda-usuario">
                                    <span class="sf-avatar"><?= e($iniciales((string)$usuario['nombre'])) ?></span>
                                    <span>
                                        <span class="nombre"><?= e($usuario['nombre']) ?></span>
                                        <span class="correo"><?= e($usuario['correo']) ?></span>
                                    </span>
                                </span>
                            </td>
                            <td>
                                <span class="badge-sf <?= $usuario['rol_nombre'] === 'Administrador' ? 'badge-admin' : 'badge-usuario' ?>">
                                    <?= e($usuario['rol_nombre']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-sf <?= $usuario['estado'] === 'activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                                    <?= $usuario['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="texto-auxiliar">
                                <?= e(date('d/m/Y', strtotime((string)$usuario['fecha_creacion']))) ?>
                            </td>
                            <td>
                                <span class="acciones-fila">
                                    <a class="btn-icono" href="<?= url('usuario_ver.php?id=' . (int)$usuario['id']) ?>"
                                       title="Consultar usuario" aria-label="Consultar a <?= e($usuario['nombre']) ?>">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>
                                    <a class="btn-icono" href="<?= url('usuario_editar.php?id=' . (int)$usuario['id']) ?>"
                                       title="Editar usuario" aria-label="Editar a <?= e($usuario['nombre']) ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <?php
                                    // El último administrador activo no se puede desactivar.
            $esUltimoAdmin = (int)$usuario['rol_id'] === 1
                && $usuario['estado'] === 'activo'
                && array_filter($usuarios, static fn(array $u): bool => (int)$u['rol_id'] === 1 && $u['estado'] === 'activo') === [];
                                    ?>
                                    <?php if ($esUltimoAdmin): ?>
                                        <span class="btn-icono" style="opacity:.4;" title="No se puede desactivar al único administrador activo"
                                              aria-label="Acción no disponible">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </span>
                                    <?php else: ?>
                                        <button type="button" class="btn-icono peligro" title="Eliminar usuario"
                                                aria-label="Eliminar a <?= e($usuario['nombre']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalConfirmacion"
                                                data-confirmar="al usuario <?= e($usuario['nombre']) ?> (<?= e($usuario['correo']) ?>)"
                                                data-url="<?= url('usuario_eliminar.php?id=' . (int)$usuario['id']) ?>">
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
                <nav aria-label="Paginación de usuarios">
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
                Esta acción desactivará la cuenta y el usuario no podrá iniciar sesión.
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
