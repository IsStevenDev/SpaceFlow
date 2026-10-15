<?php
/**
 * SpaceFlow â€” Smoke visual: assets y marcadores de UI de la Etapa 4.
 */

declare(strict_types=1);

define('BASE', getenv('SPACEFLOW_BASE') ?: 'http://localhost/SpaceFlow/public');

$GLOBALS['__ok'] = 0;
$GLOBALS['__fallos'] = 0;

function ok(string $nombre, bool $condicion, string $detalle = ''): void
{
    if ($condicion) { $GLOBALS['__ok']++; } else { $GLOBALS['__fallos']++; }
    echo ($condicion ? '  [OK]  ' : '  [FALLO] ') . $nombre
       . ($detalle !== '' ? " â€” $detalle" : '') . PHP_EOL;
}

/** Cliente mÃ­nimo con cookies. */
final class Cliente
{
    private array $cookies = [];

    public function get(string $ruta): array
    {
        $cookie = implode('; ', array_map(
            static fn(string $k, string $v): string => "$k=$v",
            array_keys($this->cookies), $this->cookies
        ));
        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Cookie: $cookie\r\n",
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        $body = file_get_contents(BASE . '/' . $ruta, false, $ctx);
        $estado = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) { $estado = (int)$m[1]; }
            if (stripos($h, 'Set-Cookie:') === 0) {
                $par = explode(';', substr($h, 11))[0];
                [$k, $v] = explode('=', $par, 2);
                $this->cookies[trim($k)] = trim($v);
            }
        }
        return ['status' => $estado, 'body' => (string)$body];
    }

    public function post(string $ruta, array $datos): array
    {
        $this->get('index.php');
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $this->get('index.php')['body'], $m);
        $cookie = implode('; ', array_map(
            static fn(string $k, string $v): string => "$k=$v",
            array_keys($this->cookies), $this->cookies
        ));
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
            'content' => http_build_query($datos + ['csrf_token' => $m[1] ?? '']),
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        file_get_contents(BASE . '/' . $ruta, false, $ctx);
        foreach ($http_response_header ?? [] as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) {
                $par = explode(';', substr($h, 11))[0];
                [$k, $v] = explode('=', $par, 2);
                $this->cookies[trim($k)] = trim($v);
            }
        }
        return ['status' => 200, 'body' => ''];
    }
}

echo '=== SMOKE VISUAL / UI (Etapa 4) ===' . PHP_EOL . PHP_EOL;

/* 1. Assets estÃ¡ticos. */
$css = file_get_contents(BASE . '/assets/css/spaceflow.css');
$js  = file_get_contents(BASE . '/assets/js/spaceflow.js');
ok('CSS carga (200)', is_string($css) && strlen($css) > 5000);
ok('JS carga (200)', is_string($js) && strlen($js) > 5000);

/* 2. Animaciones sutiles en CSS. */
ok('Keyframes de apariciÃ³n escalonada', str_contains($css, '@keyframes sf-aparecer'));
ok('Entrada de toasts deslizando', str_contains($css, '@keyframes sf-deslizar-derecha') && str_contains($css, '.sf-toast'));
ok('Badge crÃ­tico con latido', str_contains($css, '@keyframes sf-latido-rojo'));
ok('Soporte prefers-reduced-motion', str_contains($css, 'prefers-reduced-motion'));
ok('Hover con elevaciÃ³n en tarjetas', str_contains($css, ':hover') && str_contains($css, 'translateY'));

/* 3. LÃ³gica de JS: cuenta regresiva, polling y toasts. */
ok('JS consulta el endpoint de notificaciones', str_contains($js, 'urlNotificaciones') && str_contains($js, 'fetch('));
ok('Polling cada 30 s (30000 ms)', str_contains($js, '30000'));
ok('Cuenta regresiva en lazos de badges', str_contains($js, 'cuenta') && str_contains($js, 'segundos_restantes'));
ok('Sistema de toasts', str_contains($js, 'toast'));

/* 4. PÃ¡ginas reales con sesiÃ³n. */
$admin = new Cliente();
$admin->post('index.php', ['correo' => 'ana.torres@litoral.edu.co', 'clave' => 'Admin1234']);

foreach (['dashboard.php', 'equipos.php', 'reservas.php', 'reserva_nueva.php'] as $pagina) {
    $r = $admin->get($pagina);
    ok("$pagina carga (200)", $r['status'] === 200, 'status=' . $r['status']);
    ok("$pagina usa la hoja SpaceFlow", str_contains($r['body'], 'spaceflow.css'));
    ok("$pagina incluye Inter (Google Fonts)", str_contains($r['body'], 'Inter'));
    ok("$pagina tiene contenedor de toasts", str_contains($r['body'], 'data-toasts'));
}

$carlos = new Cliente();
$carlos->post('index.php', ['correo' => 'carlos.mendez@litoral.edu.co', 'clave' => 'Usuario1234']);
$r = $carlos->get('reservas.php');
ok('Listado estudiante: badges de cuenta regresiva', str_contains($r['body'], 'data-cuenta-regresiva'));
ok('Listado estudiante: acciones con CSRF oculto', str_contains($r['body'], 'csrf_token'));
ok('Listado estudiante: formulario de reserva visible', str_contains($r['body'], 'reserva_nueva.php'));

$r = $admin->get('reservas.php');
ok('Listado admin: panel de entrega/devoluciÃ³n', str_contains($r['body'], 'reserva_entregar.php') && str_contains($r['body'], 'reserva_devolver.php'));
ok('Listado admin: filtros + bÃºsqueda', str_contains($r['body'], 'busqueda') && str_contains($r['body'], 'estado'));
ok('Listado admin: badges de estado con clases sf', preg_match('/badge-(activo|prestamo|entregada|cancelada|vencido)/', $r['body']) === 1);

$r = $admin->get('dashboard.php');
ok('Layout expone data-url-notificaciones', str_contains($r['body'], 'data-url-notificaciones'));
ok('Dashboard: KPIs con clase animada .kpi', str_contains($r['body'], 'class="kpi"'));
ok('Dashboard: contadores animados data-contar', str_contains($r['body'], 'data-contar='));

echo PHP_EOL . '=== RESUMEN: ' . $GLOBALS['__ok'] . ' OK, ' . $GLOBALS['__fallos'] . ' FALLO(S) ===' . PHP_EOL;
exit($GLOBALS['__fallos'] > 0 ? 1 : 0);
