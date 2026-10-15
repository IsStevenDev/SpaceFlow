<?php
/**
 * SpaceFlow — Layout principal del panel administrativo.
 *
 * Estructura compartida por todas las páginas privadas: sidebar de
 * navegación, barra superior con el perfil y área de contenido.
 * Recibe el HTML ya renderizado de cada página mediante $contenido.
 *
 * Variables esperadas:
 *  - string $contenido HTML de la página.
 *  - string $titulo    Título de la sección.
 *  - string $subtitulo Descripción breve de la sección.
 *  - string $pagina    Identificador de la sección activa del menú.
 *
 * @package SpaceFlow\Views
 *
 * @var string $contenido
 * @var string $titulo
 * @var string $subtitulo
 * @var string $pagina
 */

/** @var array{nombre:string, correo:string, rol:string} $sesionUsuario */
$sesionUsuario = $_SESSION['usuario'] ?? [
    'nombre'  => 'Usuario demo',
    'correo'  => 'demo@' . DOMINIO_CORREO,
    'rol'     => 'Administrador',
];

/** Iniciales para el avatar del perfil. */
$iniciales = strtoupper(implode('', array_map(
    static fn(string $p): string => mb_substr($p, 0, 1),
    array_slice(explode(' ', trim($sesionUsuario['nombre'])), 0, 2)
)));

/**
 * Elementos del menú lateral.
 * 'roles' indica quién puede verlo: null = todos.
 */
$enlaces = [
    'principal' => [
        'titulo'  => 'Principal',
        'items'   => [
            ['pagina' => 'dashboard', 'texto' => 'Dashboard',   'icone'  => 'bi-grid-1x2',        'url' => url('dashboard.php'), 'roles' => null],
            ['pagina' => 'reservas',  'texto' => 'Reservas',    'icone'  => 'bi-calendar2-week',  'url' => url('reservas.php'),  'roles' => null],
            ['pagina' => 'usuarios',  'texto' => 'Usuarios',    'icone'  => 'bi-people',          'url' => url('usuarios.php'),  'roles' => [1]],
            ['pagina' => 'equipos',   'texto' => 'Equipos',     'icone'  => 'bi-laptop',          'url' => url('equipos.php'),   'roles' => [1]],
        ],
    ],
    'administracion' => [
        'titulo'  => 'Administración',
        'items'   => [
            ['pagina' => 'logs', 'texto' => 'Registro de actividad', 'icone' => 'bi-journal-text', 'url' => url('logs.php'), 'roles' => [1]],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> · <?= NOMBRE_APP ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= asset('img/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/icons/bootstrap-icons.min.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= asset('css/spaceflow.css') ?>">
</head>
<body>

<!-- Menú lateral -->
<aside class="sf-sidebar" id="sidebarPrincipal" aria-label="Navegación principal">
    <div class="sf-marca">
        <span class="sf-logo"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i></span>
        <span>
            <span class="sf-nombre"><?= NOMBRE_APP ?></span><br>
            <span class="sf-subnombre">Gestión institucional</span>
        </span>
    </div>

    <nav class="flex-grow-0">
        <?php
        // El filtrado usa el rol_id de la sesión (numérico y estable):
        // 1 = administrador, 2 = usuario. Es solo presentación; la
        // autorización real la hace Auth::requerirAdmin() en cada página.
        $rolIdSesion = (int)($_SESSION['usuario']['rol_id'] ?? 0);
        ?>
        <?php foreach ($enlaces as $seccion): ?>
            <?php
            $visibles = array_filter(
                $seccion['items'],
                static fn(array $item): bool => $item['roles'] === null
                    || in_array($rolIdSesion, $item['roles'], true)
            );
            if (empty($visibles)) {
                continue;
            }
            ?>
            <div class="sf-menu-titulo"><?= e($seccion['titulo']) ?></div>
            <?php foreach ($visibles as $item): ?>
                <a href="<?= e($item['url']) ?>"
                   class="sf-enlace<?= $pagina === $item['pagina'] ? ' activo' : '' ?>">
                    <i class="bi <?= e($item['icone']) ?>" aria-hidden="true"></i>
                    <?= e($item['texto']) ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sf-sidebar-pie">
        <span class="punto" aria-hidden="true"></span>
        <?= NOMBRE_APP ?> · <?= ORGANIZACION ?>
    </div>
</aside>

<!-- Fondo oscurecido del menú en móvil -->
<div class="sf-fondo-menu" data-fondo-menu></div>

<!-- Contenido -->
<div class="sf-contenido">

    <header class="sf-topbar">
        <button class="sf-menu-movil" type="button" data-menu-movil aria-label="Abrir menú de navegación">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div class="sf-titulo-seccion">
            <h1><?= e($titulo) ?></h1>
            <p class="texto-auxiliar d-none d-sm-block"><?= e($subtitulo) ?></p>
        </div>

        <div class="dropdown">
            <button class="sf-perfil dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="sf-avatar"><?= e($iniciales) ?></span>
                <span class="sf-perfil-texto">
                    <span class="sf-perfil-nombre"><?= e($sesionUsuario['nombre']) ?></span>
                    <span class="sf-perfil-rol"><?= e($sesionUsuario['rol']) ?></span>
                </span>
            </button>
            <ul class="dropdown-menu sf-dropdown dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="<?= url('perfil.php') ?>">
                        <i class="bi bi-person" aria-hidden="true"></i> Mi perfil
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= url('salir.php') ?>" class="m-0">
                        <?php csrf_campo(); ?>
                        <button class="dropdown-item peligro w-100 text-start" type="submit">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Cerrar sesión
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main class="sf-principal">
        <?= $contenido ?>
    </main>
</div>

<?php
// Configuración del módulo de notificaciones de la biblioteca: el
// JavaScript consulta notificaciones.php cada 30 s y pinta avisos de
// cuenta regresiva (estudiante) y "próximas a vencer" (administrador).
$esAdminSesion = ($_SESSION['usuario']['rol_id'] ?? 0) === 1;
?>
<div hidden
     data-config-biblioteca
     data-es-admin="<?= $esAdminSesion ? '1' : '0' ?>"
     data-url-notificaciones="<?= url('notificaciones.php') ?>"
     data-aviso-temprano="<?= MINUTOS_AVISO_TEMPRANO ?>"
     data-aviso-tardio="<?= MINUTOS_AVISO_TARDIO ?>"></div>

<div class="sf-toasts" data-toasts aria-live="polite" aria-atomic="false"></div>

<script src="<?= asset('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/spaceflow.js') ?>"></script>
</body>
</html>
