<?php
/**
 * SpaceFlow — Helpers de presentación y seguridad de formularios.
 *
 * Funciones globales pequeñas y sin estado usadas por las vistas:
 * escape de salida, generación de URLs, carga de vistas y tokens CSRF.
 *
 * @package SpaceFlow\Helpers
 */

declare(strict_types=1);

/**
 * Escapa un valor para imprimirlo de forma segura dentro de HTML.
 *
 * Es la principal defensa contra XSS: TODA salida dinámica pasa por aquí.
 *
 * @param mixed $valor Valor a imprimir (cadenas, números, null...).
 * @return string Cadena apta para HTML.
 */
function e(mixed $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Construye una URL relativa a BASE_URL.
 *
 * @param string $ruta Ruta relativa, por ejemplo 'usuarios.php'.
 * @return string URL completa, por ejemplo '/SpaceFlow/public/usuarios.php'.
 */
function url(string $ruta): string
{
    return BASE_URL . '/' . ltrim($ruta, '/');
}

/**
 * Construye la URL de un recurso estático (CSS, JS, imágenes).
 *
 * @param string $ruta Ruta dentro de public/assets.
 * @return string URL del recurso.
 */
function asset(string $ruta): string
{
    return url('assets/' . ltrim($ruta, '/'));
}

/**
 * Renderiza una vista pasándole los datos como variables locales.
 *
 * @param string $nombre Ruta de la vista relativa a app/views (sin .php).
 * @param array  $datos  Datos que quedan disponibles dentro de la vista.
 * @return void
 */
function vista(string $nombre, array $datos = []): void
{
    $archivo = dirname(__DIR__) . '/views/' . $nombre . '.php';
    if (!is_file($archivo)) {
        throw new RuntimeException("La vista '{$nombre}' no existe.");
    }
    extract($datos, EXTR_SKIP);
    require $archivo;
}

/**
 * Renderiza una página del panel administrativo dentro del layout principal.
 *
 * El contenido de la vista se captura en un buffer y se entrega al layout
 * mediante la variable $contenido, evitando que las vistas conozcan el sidebar.
 *
 * @param string $nombre    Vista relativa a app/views.
 * @param array  $datos     Datos para la vista.
 * @param string $titulo    Título de la sección (topbar).
 * @param string $subtitulo Descripción breve de la sección.
 * @param string $pagina    Identificador de la sección activa del menú.
 * @return void
 */
function pagina(string $nombre, array $datos, string $titulo, string $subtitulo, string $pagina): void
{
    ob_start();
    vista($nombre, $datos);
    $contenido = ob_get_clean();

    vista('layouts/principal', [
        'contenido' => $contenido,
        'titulo'    => $titulo,
        'subtitulo' => $subtitulo,
        'pagina'    => $pagina,
    ]);
}

/**
 * Genera (o recupera) el token CSRF de la sesión actual.
 *
 * @return string Token CSRF.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Imprime el campo oculto con el token CSRF para incluirlo en los formularios.
 *
 * @return void
 */
function csrf_campo(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Valida el token CSRF recibido en un POST; devuelve false si no coincide.
 *
 * @param mixed $token Token enviado por el formulario.
 * @return bool
 */
function csrf_valido(mixed $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Guarda un mensaje flash (una sola visualización) en la sesión.
 *
 * @param string $tipo    Tipo del mensaje: 'exito' | 'error' | 'aviso'.
 * @param string $mensaje Texto legible para el usuario.
 * @return void
 */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/**
 * Obtiene y limpia los mensajes flash acumulados en la sesión.
 *
 * @return array<int, array{tipo: string, mensaje: string}>
 */
function flash_obtener(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}
