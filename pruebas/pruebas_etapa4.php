<?php
/**
 * SpaceFlow â€” Pruebas HTTP de la Etapa 4 (equipos + reservas).
 * Simula un navegador: cookie + CSRF + POST/GET encadenados,
 * y verifica los efectos reales en MySQL.
 */

declare(strict_types=1);

define('BASE', getenv('SPACEFLOW_BASE') ?: 'http://localhost/SpaceFlow/public');

$GLOBALS['__ok'] = 0;
$GLOBALS['__fallos'] = 0;

/** Cliente HTTP mÃ­nimo con persistencia de cookies. */
final class Cliente
{
    private array $cookies = [];

    public function get(string $ruta): array
    {
        return $this->solicitar('GET', $ruta, null);
    }

    public function post(string $ruta, array $datos): array
    {
        return $this->solicitar('POST', $ruta, http_build_query($datos));
    }

    /** Extrae el token CSRF de una pÃ¡gina con formulario. */
    public function token(string $pagina): string
    {
        $r = $this->get($pagina);
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $r['body'], $m);
        return $m[1] ?? '';
    }

    private function solicitar(string $metodo, string $ruta, ?string $cuerpo): array
    {
        $cookie = implode('; ', array_map(
            static fn(string $k, string $v): string => "$k=$v",
            array_keys($this->cookies),
            $this->cookies
        ));

        $opciones = [
            'http' => [
                'method'  => $metodo,
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n",
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ];
        if ($cuerpo !== null) {
            $opciones['http']['content'] = $cuerpo;
        }

        $contexto = stream_context_create($opciones);
        $body = file_get_contents(BASE . '/' . $ruta, false, $contexto);
        $cabeceras = $http_response_header ?? [];

        foreach ($cabeceras as $cabecera) {
            if (stripos($cabecera, 'Set-Cookie:') === 0) {
                $par = explode(';', substr($cabecera, 11))[0];
                [$k, $v] = explode('=', $par, 2);
                $this->cookies[trim($k)] = trim($v);
            }
        }

        $estado = 0;
        $ubicacion = '';
        foreach ($cabeceras as $cabecera) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $cabecera, $m)) {
                $estado = (int)$m[1];
            }
            if (stripos($cabecera, 'Location:') === 0) {
                $ubicacion = trim(substr($cabecera, 9));
            }
        }

        return ['status' => $estado, 'location' => $ubicacion, 'body' => (string)$body];
    }
}

/** ImpresiÃ³n de resultado + conteo global. */
function ok(string $nombre, bool $condicion, string $detalle = ''): void
{
    if ($condicion) {
        $GLOBALS['__ok']++;
    } else {
        $GLOBALS['__fallos']++;
    }
    echo ($condicion ? '  [OK]  ' : '  [FALLO] ') . $nombre
       . ($detalle !== '' ? " â€” $detalle" : '') . PHP_EOL;
}

/** Convierte un Location absoluto en ruta relativa para el cliente. */
function rel(array $r): string
{
    return str_replace('/SpaceFlow/public/', '', $r['location']);
}

echo "=== SPACEFLOW â€” PRUEBAS ETAPA 4 (equipos y reservas) ===" . PHP_EOL . PHP_EOL;

/** Base de datos para verificaciones directas. */
$bd = new PDO('mysql:host=127.0.0.1;dbname=spaceflow;charset=utf8mb4', 'root', '');
$bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$equipoId = static fn(string $codigo): int => (int)$bd
    ->query("SELECT id FROM equipos WHERE codigo = " . $bd->quote($codigo))->fetchColumn();
$estadoEquipo = static fn(string $codigo): string => (string)$bd
    ->query("SELECT estado FROM equipos WHERE codigo = " . $bd->quote($codigo))->fetchColumn();
$estadoReserva = static fn(int $id): string => (string)$bd
    ->query("SELECT estado FROM reservas WHERE id = $id")->fetchColumn();
/** Extrae la reserva reciÃ©n creada por un usuario en una fecha/hora concretos. */
$buscarReserva = static function (int $usuarioId, string $fecha, string $inicio): int {
    $GLOBALS['__bd'] ??= new PDO('mysql:host=127.0.0.1;dbname=spaceflow;charset=utf8mb4', 'root', '');
    $q = $GLOBALS['__bd']->prepare('SELECT id FROM reservas WHERE usuario_id = ? AND fecha = ? AND hora_inicio = ? ORDER BY id DESC LIMIT 1');
    $q->execute([$usuarioId, $fecha, $inicio]);
    return (int)$q->fetchColumn();
};
$GLOBALS['__bd'] = $bd;

$manana  = date('Y-m-d', strtotime('+1 day'));
$enDos    = date('Y-m-d', strtotime('+2 days'));
$ochoDias = date('Y-m-d', strtotime('+8 days'));

/* ==================================================================
   1. RBAC DEL CATÃLOGO DE EQUIPOS
   ================================================================== */
echo "1. RBAC EQUIPOS" . PHP_EOL;

$admin = new Cliente();
$r = $admin->post('index.php', [
    'csrf_token' => $admin->token('index.php'),
    'correo'     => 'ana.torres@litoral.edu.co',
    'clave'      => 'Admin1234',
]);
ok('Login administrador', $r['location'] === '/SpaceFlow/public/dashboard.php');

$carlos = new Cliente();
$r = $carlos->post('index.php', [
    'csrf_token' => $carlos->token('index.php'),
    'correo'     => 'carlos.mendez@litoral.edu.co',
    'clave'      => 'Usuario1234',
]);
ok('Login estudiante (Carlos)', $r['location'] === '/SpaceFlow/public/dashboard.php');

$maria = new Cliente();
$r = $maria->post('index.php', [
    'csrf_token' => $maria->token('index.php'),
    'correo'     => 'maria.lopez@litoral.edu.co',
    'clave'      => 'Usuario1234',
]);
ok('Login estudiante 2 (MarÃ­a)', $r['location'] === '/SpaceFlow/public/dashboard.php');

$r = $carlos->get('equipos.php');
ok('Estudiante NO accede al catÃ¡logo (403)', $r['status'] === 403, 'status=' . $r['status']);
$r = $carlos->post('equipo_nuevo.php', ['csrf_token' => $carlos->token('reserva_nueva.php')]);
ok('Estudiante NO puede crear equipos (403)', $r['status'] === 403, 'status=' . $r['status']);

/* ==================================================================
   2. CRUD DE EQUIPOS (administrador)
   ================================================================== */
echo PHP_EOL . "2. CRUD EQUIPOS (admin)" . PHP_EOL;

$r = $admin->get('equipos.php');
ok('Listado de equipos carga', $r['status'] === 200);
ok('Muestra el catÃ¡logo real', str_contains($r['body'], 'PORT-001'));

$r = $admin->post('equipo_nuevo.php', [
    'csrf_token' => $admin->token('equipo_nuevo.php'),
    'codigo'     => 'PORT-999',
    'nombre'     => 'PortÃ¡til de prueba Etapa 4',
    'marca'      => 'Genius',
    'estado'     => 'disponible',
]);
ok('Crear equipo redirige al listado', $r['location'] === '/SpaceFlow/public/equipos.php', $r['location']);
$id999 = $equipoId('PORT-999');
ok('Persiste en MySQL', $id999 > 0);

$r = $admin->post('equipo_nuevo.php', [
    'csrf_token' => $admin->token('equipo_nuevo.php'),
    'codigo'     => 'PORT-001',
    'nombre'     => 'Duplicado',
    'estado'     => 'disponible',
]);
ok('CÃ³digo duplicado NO crea', $r['location'] === '/SpaceFlow/public/equipo_nuevo.php');
$r = $admin->get(rel($r));
ok('Muestra error amigable de duplicado', str_contains($r['body'], 'Ya existe un equipo con el cÃ³digo PORT-001'));

$r = $admin->post('equipo_editar.php', [
    'csrf_token' => $admin->token('equipo_editar.php?id=' . $id999),
    'id'         => $id999,
    'codigo'     => 'PORT-999',
    'nombre'     => 'PortÃ¡til Etapa 4 editado',
    'marca'      => 'Genius',
    'estado'     => 'mantenimiento',
]);
ok('Editar equipo redirige al listado', $r['location'] === '/SpaceFlow/public/equipos.php');
$nombre999 = (string)$bd->query("SELECT nombre FROM equipos WHERE id = $id999")->fetchColumn();
ok('EdiciÃ³n persistida', $nombre999 === 'PortÃ¡til Etapa 4 editado', $nombre999);

$r = $admin->post('equipo_editar.php', [
    'csrf_token' => $admin->token('equipo_editar.php?id=' . $id999),
    'id'         => $id999,
    'codigo'     => 'PORT-001',
    'nombre'     => 'Intento duplicado',
    'estado'     => 'disponible',
]);
ok('Editar con cÃ³digo ajeno rechazado', $r['location'] === '/SpaceFlow/public/equipo_editar.php?id=' . $id999);

// CSRF faltante.
$r = $admin->post('equipo_nuevo.php', [
    'codigo' => 'PORT-777', 'nombre' => 'Sin CSRF', 'estado' => 'disponible',
]);
ok('POST sin token CSRF devuelve 403', $r['status'] === 403);

// Un equipo fÃ­sico en prÃ©stamo no se puede eliminar (regla de coherencia).
$r = $admin->post('equipo_eliminar.php?id=' . $equipoId('PORT-004'), [
    'csrf_token' => $admin->token('equipos.php'),
]);
$r = $admin->get('equipos.php');
ok('Equipo prestado: eliminaciÃ³n bloqueada con aviso',
    str_contains($r['body'], 'Registra la devoluciÃ³n antes de eliminarlo'));
ok('Equipo prestado: estado intacto', $estadoEquipo('PORT-004') === 'prestado');

// Eliminar equipo CON historial de reservas (ya no prestado) -> baja lÃ³gica.
$bd->exec("UPDATE equipos SET estado = 'disponible' WHERE codigo = 'PORT-004'");
$r = $admin->post('equipo_eliminar.php?id=' . $equipoId('PORT-004'), [
    'csrf_token' => $admin->token('equipos.php'),
]);
ok('Equipo con reservas: redirige con mensaje', $r['location'] === '/SpaceFlow/public/equipos.php');
ok('Equipo con reservas: baja lÃ³gica (sigue la fila)',
    $estadoEquipo('PORT-004') === 'baja', 'estado=' . $estadoEquipo('PORT-004'));

// Eliminar equipo SIN historial -> borrado fÃ­sico.
$r = $admin->post('equipo_eliminar.php?id=' . $id999, [
    'csrf_token' => $admin->token('equipos.php'),
]);
ok('Equipo sin reservas: borrado fÃ­sico',
    (int)$bd->query("SELECT COUNT(*) FROM equipos WHERE id = $id999")->fetchColumn() === 0);

/* ==================================================================
   3. REGLAS DE NEGOCIO DE RESERVAS
   ================================================================== */
echo PHP_EOL . "3. REGLAS DE RESERVA" . PHP_EOL;

$r = $carlos->get('reserva_nueva.php');
ok('Formulario de reserva carga', $r['status'] === 200);
ok('Ofrece la franja de la biblioteca', str_contains($r['body'], 'Ver disponibilidad'));
ok('Muestra portÃ¡tiles disponibles reales', str_contains($r['body'], 'PORT-001'));

// Reserva vÃ¡lida (maÃ±ana, 2 h, PORT-001).
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $manana,
    'hora_inicio' => '08:00',
    'duracion'    => '2',
    'equipo_id'   => $equipoId('PORT-001'),
    'motivo'      => 'Prueba Etapa 4',
]);
ok('Reserva vÃ¡lida redirige al listado', $r['location'] === '/SpaceFlow/public/reservas.php', $r['location']);
$idA = $buscarReserva(2, $manana, '08:00:00');
ok('Se guardÃ³ con estado activa', $estadoReserva($idA) === 'activa', $estadoReserva($idA));

// Solapamiento del MISMO equipo en la MISMA franja.
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $manana,
    'hora_inicio' => '08:00',
    'duracion'    => '2',
    'equipo_id'   => $equipoId('PORT-001'),
]);
ok('Solapamiento rechazado (redirect)', str_contains($r['location'], 'reserva_nueva.php'));
$r = $carlos->get(rel($r));
ok('Mensaje de solapamiento amigable', str_contains($r['body'], 'ya estÃ¡ reservado en ese horario'));

// Franja adyacente (10:00-12:00) NO entra en conflicto.
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $manana,
    'hora_inicio' => '10:00',
    'duracion'    => '2',
    'equipo_id'   => $equipoId('PORT-001'),
]);
ok('Franja adyacente permitida', $r['location'] === '/SpaceFlow/public/reservas.php', $r['location']);
$idAdyacente = $buscarReserva(2, $manana, '10:00:00');

// El usuario cancela SU propia reserva.
$r = $carlos->post('reserva_cancelar.php?id=' . $idAdyacente, [
    'csrf_token' => $carlos->token('reservas.php'),
]);
ok('Cancelar propia reserva redirige', $r['location'] === '/SpaceFlow/public/reservas.php');
ok('Estado cancelada en MySQL', $estadoReserva($idAdyacente) === 'cancelada');

// DuraciÃ³n mÃ¡xima (7 horas).
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $manana,
    'hora_inicio' => '08:00',
    'duracion'    => '7',
    'equipo_id'   => $equipoId('PORT-002'),
]);
$r = $carlos->get(rel($r));
ok('DuraciÃ³n > 6 h rechazada', str_contains($r['body'], 'La duraciÃ³n debe ser de 1 a 6 horas'));

// AntelaciÃ³n mÃ¡xima (8 dÃ­as).
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $ochoDias,
    'hora_inicio' => '08:00',
    'duracion'    => '2',
    'equipo_id'   => $equipoId('PORT-002'),
]);
$r = $carlos->get(rel($r));
ok('AntelaciÃ³n > 7 dÃ­as rechazada', str_contains($r['body'], '7 dÃ­as de anticipaciÃ³n'));

// Inicio fuera de la franja (05:00).
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $manana,
    'hora_inicio' => '05:00',
    'duracion'    => '2',
    'equipo_id'   => $equipoId('PORT-002'),
]);
$r = $carlos->get(rel($r));
ok('Inicio antes de las 6:00 rechazado', str_contains($r['body'], 'atiende de 6:00 a. m. a 6:00 p. m.'));

// Inicio fuera del bloque horario (08:30).
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $manana,
    'hora_inicio' => '08:30',
    'duracion'    => '2',
    'equipo_id'   => $equipoId('PORT-002'),
]);
$r = $carlos->get(rel($r));
ok('Inicio con minutos rechazado', str_contains($r['body'], 'bloques completos de una hora'));

// Fin despuÃ©s de las 18:00 (17:00 + 2 h).
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $manana,
    'hora_inicio' => '17:00',
    'duracion'    => '2',
    'equipo_id'   => $equipoId('PORT-002'),
]);
$r = $carlos->get(rel($r));
ok('Fin despuÃ©s de las 18:00 rechazado', str_contains($r['body'], 'no puede terminar despuÃ©s de las 6:00 p. m.'));

// Equipo en mantenimiento no es reservable.
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $manana,
    'hora_inicio' => '09:00',
    'duracion'    => '2',
    'equipo_id'   => $equipoId('PORT-006'),
]);
$r = $carlos->get(rel($r));
ok('Equipo en mantenimiento rechazado', str_contains($r['body'], 'estÃ¡ en mantenimiento'));

// Un usuario no puede cruzar dos portÃ¡tiles en el mismo horario.
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $manana,
    'hora_inicio' => '09:00',
    'duracion'    => '1',
    'equipo_id'   => $equipoId('PORT-003'),
]);
$r = $carlos->get(rel($r));
ok('Usuario con horario cruzado rechazado', str_contains($r['body'], 'Ya tienes una reserva que se superpone'));

// DuraciÃ³n mÃ¡xima vÃ¡lida (6 h) en otro dÃ­a.
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $enDos,
    'hora_inicio' => '08:00',
    'duracion'    => '6',
    'equipo_id'   => $equipoId('PORT-005'),
]);
ok('Reserva de 6 h exactas permitida', $r['location'] === '/SpaceFlow/public/reservas.php', $r['location']);
$idSeis = $buscarReserva(2, $enDos, '08:00:00');
ok('Guardada 08:00-14:00', (bool)$bd->query("SELECT COUNT(*) FROM reservas WHERE id = $idSeis AND hora_fin = '14:00:00'")->fetchColumn());

// Mismo dÃ­a con inicio ya pasado (solo si la hora local lo permite).
if ((int)date('H') >= 7) {
    $r = $carlos->post('reserva_nueva.php', [
        'csrf_token'  => $carlos->token('reserva_nueva.php'),
        'fecha'       => date('Y-m-d'),
        'hora_inicio' => '06:00',
        'duracion'    => '1',
        'equipo_id'   => $equipoId('PORT-002'),
    ]);
    $r = $carlos->get(rel($r));
    ok('Mismo dÃ­a con inicio pasado rechazado', str_contains($r['body'], 'no puede ser anterior a la hora actual'));
} else {
    ok('Mismo dÃ­a con inicio pasado rechazado', true, 'omitido (antes de las 7:00 a. m.)');
}

// El formulario de hoy muestra la advertencia de disponibilidad.
$r = $carlos->get('reserva_nueva.php?fecha=' . date('Y-m-d') . '&hora_inicio=10:00&duracion=2');
ok('Advertencia visible para reservas de hoy', str_contains($r['body'], 'disponibilidad actual'));

/* ==================================================================
   4. RBAC DE RESERVAS
   ================================================================== */
echo PHP_EOL . "4. RBAC RESERVAS" . PHP_EOL;

$r = $maria->get('reservas.php');
ok('Listado de estudiante carga', $r['status'] === 200);
ok('Ve sus reservas', str_contains($r['body'], 'PORT-003'));
ok('NO ve reservas de otros', !str_contains($r['body'], 'PORT-004') && !str_contains($r['body'], 'Carlos MÃ©ndez'));

$r = $admin->get('reservas.php');
ok('Admin ve todas las reservas', str_contains($r['body'], 'Carlos MÃ©ndez'));

$r = $maria->post('reserva_cancelar.php?id=' . $idA, [
    'csrf_token' => $maria->token('reserva_nueva.php'),
]);
ok('Estudiante B cancelando reserva de A -> 403', $r['status'] === 403, 'status=' . $r['status']);
ok('La reserva de A sigue activa en MySQL', $estadoReserva($idA) === 'activa');

$r = $carlos->post('reserva_entregar.php?id=' . $idA, [
    'csrf_token' => $carlos->token('reservas.php'),
]);
ok('Estudiante no puede registrar entregas (403)', $r['status'] === 403);

/* ==================================================================
   5. ENTREGA Y DEVOLUCIÃ“N (solo administrador)
   ================================================================== */
echo PHP_EOL . "5. ENTREGA / DEVOLUCIÃ“N (admin)" . PHP_EOL;

$r = $admin->post('reserva_entregar.php?id=' . $idA, [
    'csrf_token' => $admin->token('reservas.php'),
]);
ok('Admin registra entrega', $r['location'] === '/SpaceFlow/public/reservas.php');
ok('Reserva pasa a prestamo', $estadoReserva($idA) === 'prestamo', $estadoReserva($idA));
ok('Equipo pasa a prestado', $estadoEquipo('PORT-001') === 'prestado', $estadoEquipo('PORT-001'));

// Mientras estÃ¡ prestado, su horario sigue ocupado para los demÃ¡s.
$r = $maria->post('reserva_nueva.php', [
    'csrf_token'  => $maria->token('reserva_nueva.php'),
    'fecha'       => $manana,
    'hora_inicio' => '09:00',
    'duracion'    => '1',
    'equipo_id'   => $equipoId('PORT-001'),
]);
$r = $maria->get(rel($r));
ok('Prestamo en curso sigue bloqueando horario', str_contains($r['body'], 'ya estÃ¡ reservado en ese horario'));

$r = $admin->post('reserva_devolver.php?id=' . $idA, [
    'csrf_token' => $admin->token('reservas.php'),
]);
ok('Admin registra devoluciÃ³n', $r['location'] === '/SpaceFlow/public/reservas.php');
ok('Reserva pasa a entregada', $estadoReserva($idA) === 'entregada', $estadoReserva($idA));
ok('Equipo vuelve a disponible', $estadoEquipo('PORT-001') === 'disponible', $estadoEquipo('PORT-001'));

$r = $admin->post('reserva_entregar.php?id=' . $idA, [
    'csrf_token' => $admin->token('reservas.php'),
]);
$r = $admin->get('reservas.php');
ok('Reentregar una entrega rechazado', str_contains($r['body'], 'ya fue entregada'));

$r = $admin->post('reserva_devolver.php?id=' . $idSeis, [
    'csrf_token' => $admin->token('reservas.php'),
]);
$r = $admin->get('reservas.php');
ok('Devolver algo no entregado rechazado', str_contains($r['body'], 'aÃºn no se ha entregado'));

// El administrador cancela una reserva de otro usuario.
$r = $carlos->post('reserva_nueva.php', [
    'csrf_token'  => $carlos->token('reserva_nueva.php'),
    'fecha'       => $manana,
    'hora_inicio' => '06:00',
    'duracion'    => '1',
    'equipo_id'   => $equipoId('PORT-002'),
]);
$idAjena = $buscarReserva(2, $manana, '06:00:00');
ok('Nueva reserva para probar cancelaciÃ³n admin', $idAjena > 0);

$r = $admin->post('reserva_cancelar.php?id=' . $idAjena, [
    'csrf_token' => $admin->token('reservas.php'),
]);
ok('Admin cancela reserva de otro', $r['location'] === '/SpaceFlow/public/reservas.php');
ok('CancelaciÃ³n admin persistida', $estadoReserva($idAjena) === 'cancelada');

/* ==================================================================
   6. VENCIMIENTO AUTOMÃTICO (ejecuciÃ³n perezosa)
   ================================================================== */
echo PHP_EOL . "6. VENCIMIENTO AUTOMÃTICO" . PHP_EOL;

$bd->exec("INSERT INTO reservas (usuario_id, equipo_id, fecha, hora_inicio, hora_fin, estado, motivo)
           VALUES (2, " . $equipoId('PORT-008') . ", CURDATE() - INTERVAL 1 DAY,
                   '08:00:00', '10:00:00', 'activa', 'prueba vencida')");
$idVencida = (int)$bd->lastInsertId();

$r = $admin->get('reservas.php');
ok('La consulta marca vencidas las activas caducadas',
    $estadoReserva($idVencida) === 'vencida', $estadoReserva($idVencida));

$r = $admin->post('reserva_entregar.php?id=' . $idVencida, [
    'csrf_token' => $admin->token('reservas.php'),
]);
$r = $admin->get('reservas.php');
ok('No se puede entregar una reserva vencida', str_contains($r['body'], 'venciÃ³ sin recogerse'));

/* ==================================================================
   7. FILTROS Y BÃšSQUEDA DE RESERVAS
   ================================================================== */
echo PHP_EOL . "7. FILTROS DE RESERVAS" . PHP_EOL;

$r = $admin->get('reservas.php?busqueda=carlos');
ok('BÃºsqueda por usuario encuentra sus reservas', str_contains($r['body'], 'PORT-001'));
$r = $admin->get('reservas.php?busqueda=zzznoexiste');
ok('BÃºsqueda sin resultados muestra estado vacÃ­o', str_contains($r['body'], 'No se encontraron reservas'));
$r = $admin->get('reservas.php?estado=cancelada');
ok('Filtro por estado funciona', str_contains($r['body'], 'Cancelada'));
$r = $admin->get('reservas.php?pagina=999');
ok('PÃ¡gina fuera de rango se acota (200)', $r['status'] === 200);

/* ==================================================================
   8. ENDPOINT JSON DE NOTIFICACIONES
   ================================================================== */
echo PHP_EOL . "8. NOTIFICACIONES JSON" . PHP_EOL;

$anonimo = new Cliente();
$r = $anonimo->get('notificaciones.php');
ok('Sin sesiÃ³n redirige al login', $r['status'] === 302);

$r = $admin->get('notificaciones.php');
$jsonAdmin = json_decode($r['body'], true);
ok('Respuesta JSON vÃ¡lida (admin)', is_array($jsonAdmin), substr($r['body'], 0, 80));
ok('Incluye hora del servidor', isset($jsonAdmin['servidor']));
ok('Panel con por-vencer y atrasados', array_key_exists('vencer', $jsonAdmin) && array_key_exists('atrasados', $jsonAdmin));
ok('Reservas propias del admin', array_key_exists('propias', $jsonAdmin));

$r = $carlos->get('notificaciones.php');
$jsonCarlos = json_decode($r['body'], true);
ok('Respuesta JSON vÃ¡lida (estudiante)', is_array($jsonCarlos));
ok('Estudiante ve sus reservas de hoy', isset($jsonCarlos['propias']) && is_array($jsonCarlos['propias']));
ok('Estudiante NO recibe el panel admin', !array_key_exists('vencer', $jsonCarlos));
if ($jsonCarlos['propias'] !== []) {
    $p = $jsonCarlos['propias'][0];
    ok('Cada fila expone segundos restantes', array_key_exists('segundos_restantes', $p)
        && is_int($p['segundos_restantes']));
}

/* ==================================================================
   9. DASHBOARD POR ROLES
   ================================================================== */
echo PHP_EOL . "9. DASHBOARD" . PHP_EOL;

$r = $admin->get('dashboard.php');
ok('KPIs de biblioteca', str_contains($r['body'], 'Equipos disponibles'));
ok('KPI reservas del dÃ­a', str_contains($r['body'], 'Reservas activas hoy'));
ok('Panel "PrÃ³ximas a vencer"', str_contains($r['body'], 'PrÃ³ximas a vencer'));
ok('Panel con polling data-attribute', str_contains($r['body'], 'data-panel-vencimientos'));

$r = $carlos->get('dashboard.php');
ok('Estudiante: tarjeta "Mis prÃ³ximas reservas"', str_contains($r['body'], 'Mis prÃ³ximas reservas'));
ok('Estudiante ve su reserva real', str_contains($r['body'], 'PORT-004') || str_contains($r['body'], 'PORT-001'));
ok('Estudiante NO ve panel admin', !str_contains($r['body'], 'data-panel-vencimientos'));
ok('Contenedor de toasts en el layout', str_contains($r['body'], 'data-toasts'));

/* ==================================================================
   10. REGISTRO DE ACTIVIDAD (logs)
   ================================================================== */
echo PHP_EOL . "10. LOGS" . PHP_EOL;

$logs = (string)file_get_contents('C:\\xampp\\htdocs\\SpaceFlow\\logs\\aplicacion.log');
ok('INFO: creaciÃ³n de reserva', str_contains($logs, 'CreÃ³ la reserva #'));
ok('INFO: entrega registrada', str_contains($logs, 'RegistrÃ³ la entrega del equipo'));
ok('INFO: devoluciÃ³n registrada', str_contains($logs, 'RegistrÃ³ la devoluciÃ³n del equipo'));
ok('INFO: cancelaciÃ³n registrada', str_contains($logs, 'CancelÃ³ la reserva #'));
ok('WARNING: intento de cancelaciÃ³n ajena', str_contains($logs, 'intentÃ³ cancelar la reserva #'));
ok('Sin volcado de datos sensibles en la acciÃ³n', !str_contains($logs, 'password_hash'));

/* ==================================================================
   RESUMEN
   ================================================================== */
echo PHP_EOL . '=== RESUMEN: ' . $GLOBALS['__ok'] . ' OK, '
    . $GLOBALS['__fallos'] . ' FALLO(S) ===' . PHP_EOL;
exit($GLOBALS['__fallos'] > 0 ? 1 : 0);
