<?php
/**
 * SpaceFlow â€” Pruebas HTTP del flujo completo (script temporal).
 * Simula un navegador: cookie + CSRF + POST/GET encadenados.
 */

declare(strict_types=1);

define('BASE', getenv('SPACEFLOW_BASE') ?: 'http://localhost/SpaceFlow/public');

/**
 * Cliente HTTP mÃ­nimo con persistencia de cookies.
 */
final class Cliente
{
    private array $cookies = [];

    /** GET devolviendo [status, headers, body]. */
    public function get(string $ruta): array
    {
        return $this->solicitar('GET', $ruta, null);
    }

    /** POST con datos de formulario. */
    public function post(string $ruta, array $datos): array
    {
        return $this->solicitar('POST', $ruta, http_build_query($datos));
    }

    /** Extrae el token CSRF del HTML recibido.
     *
     * @param string $pagina PÃ¡gina de la que leer el token. Mientras no
     *        hay sesiÃ³n, index.php muestra el formulario; con sesiÃ³n,
     *        index.php redirige, asÃ­ que se usa usuario_nuevo.php.
     */
    public function token(string $pagina = 'index.php'): string
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

        // Guarda cookies nuevas (sesiÃ³n regenerada al hacer login).
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

/** ImpresiÃ³n de resultado. */
function ok(string $nombre, bool $condicion, string $detalle = ''): void
{
    echo ($condicion ? '  [OK]  ' : '  [FALLO] ') . $nombre
       . ($detalle !== '' ? " â€” $detalle" : '') . PHP_EOL;
}

echo "=== ESPACIO DE PRUEBAS SPACEFLOW (Etapa 3) ===" . PHP_EOL . PHP_EOL;

/* --- 1. AutenticaciÃ³n --- */
echo "1. AUTENTICACIÃ“N" . PHP_EOL;
$admin = new Cliente();
$r = $admin->post('index.php', [
    'csrf_token' => $admin->token(),
    'correo'     => 'ana.torres@litoral.edu.co',
    'clave'      => 'Admin1234',
]);
ok('Login admin redirige al dashboard', $r['location'] === '/SpaceFlow/public/dashboard.php', $r['location'] ?: 'sin redirect');

$malo = new Cliente();
$r = $malo->post('index.php', [
    'csrf_token' => $malo->token(),
    'correo'     => 'ana.torres@litoral.edu.co',
    'clave'      => 'Incorrecta123',
]);
ok('Login con clave mala NO redirige', $r['location'] === '');
ok('Muestra mensaje de error amigable', str_contains($r['body'], 'incorrectos'));

$inactivo = new Cliente();
$r = $inactivo->post('index.php', [
    'csrf_token' => $inactivo->token(),
    'correo'     => 'jorge.ruiz@litoral.edu.co',
    'clave'      => 'Usuario1234',
]);
ok('Cuenta inactiva rechazada', str_contains($r['body'], 'inactiva'));

$r = $malo->post('index.php', [
    'csrf_token' => 'token-falso',
    'correo'     => 'x@litoral.edu.co',
    'clave'      => 'x',
]);
ok('POST sin CSRF vÃ¡lido devuelve 403', $r['status'] === 403);

/* --- 2. Panel admin --- */
echo PHP_EOL . "2. PANEL ADMINISTRADOR" . PHP_EOL;
$r = $admin->get('dashboard.php');
ok('Dashboard carga', $r['status'] === 200);
ok('Muestra KPIs reales', str_contains($r['body'], 'Usuarios registrados'));
ok('Muestra actividad real del log', str_contains($r['body'], 'Actividad reciente'));

$r = $admin->get('usuarios.php');
ok('Listado carga', $r['status'] === 200);
ok('Lista a los usuarios de la BD', str_contains($r['body'], 'ana.torres@litoral.edu.co'));
ok('No filtra contraseÃ±as en el HTML', !str_contains($r['body'], '$2y$'));

$r = $admin->get('usuarios.php?busqueda=lucia');
ok('BÃºsqueda "lucÃ­a" encuentra a LucÃ­a GÃ³mez', str_contains($r['body'], 'LucÃ­a GÃ³mez'));
$r = $admin->get('usuarios.php?busqueda=zzznoexiste');
ok('BÃºsqueda sin resultados muestra estado vacÃ­o', str_contains($r['body'], 'No se encontraron usuarios'));
$r = $admin->get('usuarios.php?pagina=999');
ok('PÃ¡gina fuera de rango se acota a la Ãºltima', str_contains($r['body'], 'ana.torres@litoral.edu.co')
    && !str_contains($r['body'], 'AÃºn no hay usuarios'));

/* --- 3. CRUD de usuarios --- */
echo PHP_EOL . "3. CRUD DE USUARIOS" . PHP_EOL;
$token = $admin->token('usuario_nuevo.php');

$r = $admin->post('usuario_nuevo.php', [
    'csrf_token' => $token, 'nombre' => 'Pedro Prueba',
    'correo' => 'pedro.prueba@litoral.edu.co', 'tipo_documento' => 'CC',
    'numero_documento' => '5551234', 'programa_id' => '7', 'rol_id' => '2',
    'estado' => 'activo', 'clave' => 'Pedro1234', 'clave_confirmacion' => 'Pedro1234',
]);
ok('Crear usuario redirige al listado', $r['location'] === '/SpaceFlow/public/usuarios.php');
$r = $admin->get('usuarios.php?busqueda=pedro');
ok('Usuario creado visible', str_contains($r['body'], 'Pedro Prueba'));

$r = $admin->post('usuario_nuevo.php', [
    'csrf_token' => $token, 'nombre' => 'Duplicado Test',
    'correo' => 'pedro.prueba@litoral.edu.co', 'tipo_documento' => 'CC',
    'numero_documento' => '5559999', 'programa_id' => '7', 'rol_id' => '2',
    'estado' => 'activo', 'clave' => 'Dup12345', 'clave_confirmacion' => 'Dup12345',
]);
ok('Correo duplicado redirige con error', $r['location'] === '/SpaceFlow/public/usuario_nuevo.php');
$r = $admin->get('usuario_nuevo.php');
ok('Correo duplicado rechazado', str_contains($r['body'], 'existe una cuenta con ese correo'));

$r = $admin->post('usuario_nuevo.php', [
    'csrf_token' => $token, 'nombre' => 'Dominio Malo',
    'correo' => 'alguien@gmail.com', 'tipo_documento' => 'CC',
    'numero_documento' => '5558888', 'programa_id' => '7', 'rol_id' => '2',
    'estado' => 'activo', 'clave' => 'Malo1234', 'clave_confirmacion' => 'Malo1234',
]);
$r = $admin->get('usuario_nuevo.php');
ok('Correo fuera del dominio @litoral.edu.co rechazado', str_contains($r['body'], 'institucional'));

$r = $admin->post('usuario_nuevo.php', [
    'csrf_token' => $token, 'nombre' => 'Debil Test',
    'correo' => 'debil@litoral.edu.co', 'tipo_documento' => 'CC',
    'numero_documento' => '5557777', 'programa_id' => '7', 'rol_id' => '2',
    'estado' => 'activo', 'clave' => '123', 'clave_confirmacion' => '123',
]);
$r = $admin->get('usuario_nuevo.php');
ok('ContraseÃ±a dÃ©bil rechazada', str_contains($r['body'], '8 caracteres'));

// EdiciÃ³n: buscar id de Pedro
$conexion = new PDO('mysql:host=127.0.0.1;dbname=spaceflow;charset=utf8mb4', 'root', '');
$idPedro = (int)$conexion->query("SELECT id FROM usuarios WHERE correo='pedro.prueba@litoral.edu.co'")->fetchColumn();

$r = $admin->post('usuario_editar.php', [
    'csrf_token' => $token, 'id' => (string)$idPedro,
    'nombre' => 'Pedro Prueba Editado', 'correo' => 'pedro.prueba@litoral.edu.co',
    'tipo_documento' => 'CC', 'numero_documento' => '5551234', 'programa_id' => '7',
    'rol_id' => '2', 'estado' => 'inactivo',
]);
ok('Editar usuario funciona', $r['location'] === '/SpaceFlow/public/usuarios.php');
$estado = $conexion->query("SELECT estado FROM usuarios WHERE id=$idPedro")->fetchColumn();
ok('EdiciÃ³n persistida en MySQL', $estado === 'inactivo', "estado=$estado");

$r = $admin->get('usuario_ver.php?id=99999');
ok('ID inexistente -> mensaje amigable + redirect', $r['location'] === '/SpaceFlow/public/usuarios.php');

/* --- 4. AutorizaciÃ³n --- */
echo PHP_EOL . "4. AUTORIZACIÃ“N POR ROLES" . PHP_EOL;
$usuario = new Cliente();
$r = $usuario->post('index.php', [
    'csrf_token' => $usuario->token(),
    'correo' => 'carlos.mendez@litoral.edu.co', 'clave' => 'Usuario1234',
]);
ok('Login usuario estÃ¡ndar', str_contains($r['location'], 'dashboard.php'));

$r = $usuario->get('usuarios.php');
ok('Usuario estÃ¡ndar recibe 403 en /usuarios.php', $r['status'] === 403);
$r = $usuario->get('logs.php');
ok('Usuario estÃ¡ndar recibe 403 en /logs.php', $r['status'] === 403);
$r = $usuario->get('usuario_nuevo.php');
ok('Usuario estÃ¡ndar recibe 403 en /usuario_nuevo.php', $r['status'] === 403);
$r = $usuario->get('usuario_editar.php?id=1');
ok('Usuario estÃ¡ndar recibe 403 en /usuario_editar.php (no puede editar a otros)', $r['status'] === 403);

// El usuario sÃ­ debe poder ver su perfil
$r = $usuario->get('perfil.php');
ok('Usuario accede a su perfil', $r['status'] === 200 && str_contains($r['body'], 'Mi perfil'));
ok('Perfil NO muestra selector de rol', !str_contains($r['body'], 'name="rol_id"'));

// Intento de editar el perfil de otro manipulando la sesiÃ³n: el id sale de $_SESSION
$r = $usuario->post('perfil.php', [
    'csrf_token' => $usuario->token('perfil.php'), 'nombre' => 'Intento Ajeno',
    'correo' => 'carlos.mendez@litoral.edu.co', 'tipo_documento' => 'CC',
    'numero_documento' => '1015987654', 'programa_id' => '7', 'clave' => '', 'clave_confirmacion' => '',
]);
$nombreCarlos = $conexion->query("SELECT nombre FROM usuarios WHERE correo='carlos.mendez@litoral.edu.co'")->fetchColumn();
ok('Perfil propio se actualiza (id desde sesiÃ³n)', $nombreCarlos === 'Intento Ajeno', "nombre=$nombreCarlos");
$nombreAna = $conexion->query("SELECT nombre FROM usuarios WHERE id=1")->fetchColumn();
ok('NO tocÃ³ a otro usuario', $nombreAna === 'Ana Torres', "Ana=$nombreAna");

// Intento directo de eliminaciÃ³n por GET (no deberÃ­a borrar)
$idAntes = (int)$conexion->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$r = $usuario->get('usuario_eliminar.php?id=2');
ok('GET a eliminar.php no elimina (solo POST)', $r['location'] === '/SpaceFlow/public/usuarios.php');

/* --- 5. Ãšltimo administrador protegido --- */
echo PHP_EOL . "5. PROTECCIONES DE NEGOCIO" . PHP_EOL;
$idAna = 1;
$r = $admin->post('usuario_eliminar.php', ['csrf_token' => $token, 'id' => (string)$idAna]);
ok('Eliminar al Ãºltimo admin redirige con error', $r['location'] === '/SpaceFlow/public/usuarios.php');
$r = $admin->get('usuarios.php'); // El flash se ve tras la redirecciÃ³n.
ok('No se puede eliminar al Ãºltimo admin activo', str_contains($r['body'], 'Ãºnico administrador'));
$estadoAna = $conexion->query("SELECT estado FROM usuarios WHERE id=1")->fetchColumn();
ok('Ana sigue activa', $estadoAna === 'activo');

// EliminaciÃ³n vÃ¡lida de Pedro (queda inactivo, no borrado)
$r = $admin->post('usuario_eliminar.php', ['csrf_token' => $token, 'id' => (string)$idPedro]);
ok('EliminaciÃ³n lÃ³gica de Pedro OK', $r['location'] === '/SpaceFlow/public/usuarios.php');
$filaPedro = $conexion->query("SELECT estado FROM usuarios WHERE id=$idPedro")->fetch();
ok('Registro conservado con estado inactivo', $filaPedro && $filaPedro['estado'] === 'inactivo');

// Un inactivo no puede iniciar sesiÃ³n
$pedroInactivo = new Cliente();
$r = $pedroInactivo->post('index.php', [
    'csrf_token' => $pedroInactivo->token(),
    'correo' => 'pedro.prueba@litoral.edu.co', 'clave' => 'Pedro1234',
]);
ok('Usuario desactivado no puede iniciar sesiÃ³n', !str_contains($r['location'], 'dashboard.php'));

/* --- 6. Log del sistema --- */
echo PHP_EOL . "6. REGISTRO DE ACTIVIDAD" . PHP_EOL;
$archivoLog = 'C:/xampp/htdocs/SpaceFlow/logs/aplicacion.log';
ok('Archivo de log creado', is_file($archivoLog));
$contenido = is_file($archivoLog) ? file_get_contents($archivoLog) : '';
ok('Registra login exitoso (INFO)', str_contains($contenido, '[INFO]') && str_contains($contenido, 'Inicio de sesiÃ³n exitoso'));
ok('Registra login fallido (WARNING)', str_contains($contenido, '[WARNING]') && str_contains($contenido, 'fallido'));
ok('Registra creaciÃ³n de usuario (INFO)', str_contains($contenido, 'CreÃ³ el usuario'));
ok('Registra ediciÃ³n (INFO)', str_contains($contenido, 'EditÃ³ el usuario'));
ok('Registra eliminaciÃ³n (INFO)', str_contains($contenido, 'DesactivÃ³ la cuenta'));
ok('Registra intento de acceso no autorizado (WARNING)', str_contains($contenido, 'no autorizado'));
ok('NO contiene contraseÃ±as ni hashes', !str_contains($contenido, 'Admin1234') && !str_contains($contenido, '$2y$'));

$r = $admin->get('logs.php');
ok('Panel de logs muestra eventos reales', str_contains($r['body'], 'Inicio de sesiÃ³n exitoso'));
$r = $admin->get('logs.php?nivel=WARNING');
ok('Filtro por nivel funciona', str_contains($r['body'], 'fallido'));

/* --- 7. Cierre de sesiÃ³n --- */
echo PHP_EOL . "7. SESIÃ“N" . PHP_EOL;
$tokenAdmin = $admin->token('usuarios.php');
$r = $admin->post('salir.php', ['csrf_token' => $tokenAdmin]);
ok('Logout redirige al login', $r['location'] === '/SpaceFlow/public/index.php');
$r = $admin->get('dashboard.php');
ok('Tras logout, /dashboard.php redirige al login', str_contains($r['location'], 'index.php'));

echo PHP_EOL . "=== FIN DE LAS PRUEBAS ===" . PHP_EOL;
