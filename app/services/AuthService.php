<?php
/**
 * SpaceFlow — Servicio de autenticación.
 *
 * Responsable de verificar credenciales contra MySQL, gestionar la
 * sesión (regeneración de id, datos de usuario) y registrar los
 * intentos en el log.
 *
 * @package SpaceFlow\Services
 */

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CredencialesInvalidasException;
use App\Models\Usuario;
use App\Repositories\UsuarioRepository;

class AuthService
{
    /** Intentos fallidos máximos antes de bloquear temporalmente el login. */
    private const MAX_INTENTOS = 5;

    /** Segundos de bloqueo tras superar el máximo de intentos. */
    private const BLOQUEO_SEGUNDOS = 60;

    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly Logger $log
    ) {
    }

    /**
     * Inicia sesión si las credenciales son válidas.
     *
     * Flujo: bloqueo por intentos → correo → contraseña → estado →
     * regeneración del id de sesión → registro en log.
     *
     * @param string $correo Correo institucional.
     * @param string $clave Contraseña en texto plano (nunca se almacena).
     * @return Usuario Usuario autenticado.
     * @throws CredencialesInvalidasException Si algo falla o está bloqueado.
     */
    public function iniciarSesion(string $correo, string $clave): Usuario
    {
        $this->verificarBloqueo();

        $fila = $this->usuarios->buscarPorCorreo($correo);
        $hash = $fila['password_hash'] ?? '';

        // password_verify compara contra el hash almacenado sin
        // desencriptar nada (bcrypt).
        if ($fila === null || !password_verify($clave, $hash)) {
            $this->registrarIntentoFallido($correo);
            $this->log->advertencia(
                "Intento de inicio de sesión fallido para '{$correo}' (credenciales inválidas)."
            );
            throw new CredencialesInvalidasException();
        }

        if ($fila['estado'] !== 'activo') {
            $this->log->advertencia(
                "Intento de inicio de sesión de cuenta inactiva: '{$correo}'."
            );
            throw new CredencialesInvalidasException(
                'Tu cuenta está inactiva. Contacta al administrador.'
            );
        }

        $this->limpiarIntentos();

        // Regenera el id de sesión para evitar fijación (session fixation).
        session_regenerate_id(true);

        $usuario = $this->convertir($fila);

        $_SESSION['usuario'] = [
            'id'      => $usuario->getId(),
            'nombre'  => $usuario->getNombre(),
            'correo'  => $usuario->getCorreo(),
            'rol'     => $usuario->getRolNombre(),
            'rol_id'  => $usuario->getRolId(),
        ];

        $this->log->info("Inicio de sesión exitoso de '{$correo}'.");
        return $usuario;
    }

    /**
     * Destruye por completo la sesión del navegador.
     *
     * @return void
     */
    public function cerrarSesion(): void
    {
        if (!empty($_SESSION['usuario'])) {
            $this->log->info(
                'Cierre de sesión de \'' . $_SESSION['usuario']['correo'] . '\'.'
            );
        }
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }
        session_destroy();
    }

    /**
     * Registra un intento fallido y bloquea si se supera el máximo.
     *
     * @param string $correo Correo intentado.
     * @return void
     */
    private function registrarIntentoFallido(string $correo): void
    {
        $_SESSION['intentos_login'] = ($_SESSION['intentos_login'] ?? 0) + 1;
        $_SESSION['login_correo'] = $correo;

        if ($_SESSION['intentos_login'] >= self::MAX_INTENTOS) {
            $_SESSION['bloqueo_hasta'] = time() + self::BLOQUEO_SEGUNDOS;
            unset($_SESSION['intentos_login']);
            $this->log->advertencia(
                "Bloqueo temporal de {$this->obtenerCorreoSesion()} tras "
                . self::MAX_INTENTOS . ' intentos fallidos.'
            );
        }
    }

    /** Limpia el contador de intentos tras un login exitoso. */
    private function limpiarIntentos(): void
    {
        unset($_SESSION['intentos_login'], $_SESSION['bloqueo_hasta']);
    }

    /** Lanza excepción si aún está vigente el bloqueo temporal. */
    private function verificarBloqueo(): void
    {
        $hasta = $_SESSION['bloqueo_hasta'] ?? 0;
        if ($hasta > time()) {
            $segundos = $hasta - time();
            throw new CredencialesInvalidasException(
                "Demasiados intentos fallidos. Intenta de nuevo en {$segundos} segundos."
            );
        }
        unset($_SESSION['bloqueo_hasta']);
    }

    /** @return string Correo del intento actual para el mensaje de log. */
    private function obtenerCorreoSesion(): string
    {
        return $_SESSION['login_correo'] ?? 'desconocido';
    }

    /**
     * Convierte una fila de la base de datos en un modelo Usuario.
     *
     * @param array<string, mixed> $fila Fila con los campos de la consulta.
     * @return Usuario
     */
    private function convertir(array $fila): Usuario
    {
        return (new Usuario())
            ->setId((int)$fila['id'])
            ->setNombre((string)$fila['nombre'])
            ->setCorreo((string)$fila['correo'])
            ->setPasswordHash((string)$fila['password_hash'])
            ->setRolId((int)$fila['rol_id'])
            ->setRolNombre((string)$fila['rol_nombre'])
            ->setTipoDocumento((string)$fila['tipo_documento'])
            ->setNumeroDocumento((string)$fila['numero_documento'])
            ->setProgramaId($fila['programa_id'] !== null ? (int)$fila['programa_id'] : null)
            ->setEstado((string)$fila['estado'])
            ->setFechaCreacion((string)$fila['fecha_creacion']);
    }
}
