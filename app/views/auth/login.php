<?php
/**
 * SpaceFlow — Pantalla de inicio de sesión.
 *
 * Vista pública: panel de identidad institucional + formulario.
 * El procesamiento de credenciales se conecta en la Etapa 3 (AuthService).
 *
 * @package SpaceFlow\Views
 *
 * @var array<int, array{tipo: string, mensaje: string}> $mensajes
 * @var string|null $error Error amigable a mostrar (si lo hay).
 */

$error = $error ?? null;
$correoRecordado = $correoRecordado ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · <?= NOMBRE_APP ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= asset('img/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/icons/bootstrap-icons.min.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= asset('css/spaceflow.css') ?>">
</head>
<body>
<div class="sf-login">

    <!-- Panel de identidad -->
    <section class="sf-login-marca" aria-label="Presentación de SpaceFlow">
        <div class="sf-marca">
            <span class="sf-logo"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i></span>
            <span>
                <span class="sf-nombre"><?= NOMBRE_APP ?></span><br>
                <span class="sf-subnombre">Gestión institucional</span>
            </span>
        </div>

        <h2 class="sf-login-titulo">La gestión de tu comunidad, en un solo lugar.</h2>
        <p class="sf-login-descr">
            Plataforma institucional para administrar usuarios, equipos portátiles y
            préstamos con trazabilidad completa y control de acceso por roles.
        </p>

        <ul class="sf-login-lista">
            <li><i class="bi bi-shield-check" aria-hidden="true"></i> Acceso seguro con roles y permisos verificados</li>
            <li><i class="bi bi-laptop" aria-hidden="true"></i> Reserva de portátiles validando disponibilidad en tiempo real</li>
            <li><i class="bi bi-journal-text" aria-hidden="true"></i> Registro de actividad de cada operación del sistema</li>
        </ul>

        <p class="sf-login-pie">
            <span class="punto" aria-hidden="true"></span>
            <?= ORGANIZACION ?> · Correos institucionales @<?= DOMINIO_CORREO ?>
        </p>
    </section>

    <!-- Formulario -->
    <section class="sf-login-formulario" aria-label="Inicio de sesión">
        <div class="sf-login-caja">
            <h1>Bienvenido</h1>
            <p class="texto-auxiliar">Ingresa con tu correo institucional para continuar.</p>

            <?php if (!empty($error)): ?>
                <div class="alerta-sf alerta-<?= e($tipoError ?? 'error') ?> sf-login-alerta" role="alert" data-auto-cerrar>
                    <i class="bi <?= ($tipoError ?? 'error') === 'aviso' ? 'bi-info-circle' : 'bi-exclamation-circle' ?>" aria-hidden="true"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('index.php') ?>" data-cargando novalidate>
                <?php csrf_campo(); ?>

                <div class="mb-3">
                    <label for="correo" class="form-label">Correo institucional</label>
                    <div class="input-con-icone">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                        <input type="email" id="correo" name="correo" class="form-control"
                               placeholder="nombre@<?= DOMINIO_CORREO ?>"
                               value="<?= e($correoRecordado) ?>"
                               autocomplete="username" required
                               pattern="^[^@\s]+@<?= preg_quote(DOMINIO_CORREO, '/') ?>$">
                    </div>
                    <div class="texto-error-campo">Ingresa tu correo institucional (@<?= DOMINIO_CORREO ?>).</div>
                </div>

                <div class="mb-2">
                    <label for="clave" class="form-label">Contraseña</label>
                    <div class="input-con-icone">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input type="password" id="clave" name="clave" class="form-control clave-visible"
                               placeholder="Tu contraseña"
                               autocomplete="current-password" required minlength="6">
                        <button type="button" class="alternar-clave bi bi-eye"
                                data-alternar-clave="clave" aria-label="Mostrar u ocultar contraseña"></button>
                    </div>
                    <div class="texto-error-campo">La contraseña es obligatoria (mínimo 6 caracteres).</div>
                </div>

                <p class="texto-auxiliar mb-3">
                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                    Las cuentas las crea el administrador; no hay registro público.
                </p>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Ingresar
                </button>
            </form>
        </div>
    </section>
</div>

<script src="<?= asset('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/spaceflow.js') ?>"></script>
<script>
  // Refuerzo visual del patrón de correo institucional (el servidor revalida).
  (function () {
    const correo = document.getElementById('correo');
    if (!correo) return;
    const permitido = '@<?= DOMINIO_CORREO ?>';
    correo.addEventListener('input', function () {
      const valido = correo.value === '' || correo.value.toLowerCase().endsWith(permitido);
      correo.setCustomValidity(valido ? '' : 'Solo se permiten correos ' + permitido);
    });
  })();
</script>
</body>
</html>
