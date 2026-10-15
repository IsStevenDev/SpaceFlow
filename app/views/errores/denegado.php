<?php
/**
 * SpaceFlow — Vista de error 403 (acceso denegado / solicitud inválida).
 *
 * Se carga directamente desde los middlewares, sin pasar por el layout
 * del panel, porque el usuario puede no tener sesión válida.
 *
 * @package SpaceFlow\Views
 *
 * @var string $titulo  Título del error.
 * @var string $mensaje Explicación amigable.
 */
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
<body style="background: var(--sf-fondo);">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="tarjeta">
                <div class="tarjeta-cuerpo text-center p-4 p-md-5">
                    <span class="estado-vacio-icone" style="background: var(--sf-rojo-tenue); color: var(--sf-rojo);">
                        <i class="bi bi-shield-x" aria-hidden="true"></i>
                    </span>
                    <h1 class="h4 mt-3"><?= e($titulo) ?></h1>
                    <p class="texto-auxiliar mb-4"><?= e($mensaje) ?></p>
                    <a href="<?= url('dashboard.php') ?>" class="btn btn-primary">
                        <i class="bi bi-house" aria-hidden="true"></i> Volver al panel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
