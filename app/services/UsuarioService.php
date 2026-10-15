<?php
/**
 * SpaceFlow — Servicio de gestión de usuarios.
 *
 * Concentra las reglas de negocio y las validaciones del SGU:
 * dominio institucional del correo, unicidad, longitud de contraseña,
 * permisos por rol y borrado lógico. Las excepciones personalizadas
 * permiten al controlador reaccionar sin conocer los detalles.
 *
 * @package SpaceFlow\Services
 */

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AccesoDenegadoException;
use App\Exceptions\UsuarioNoEncontradoException;
use App\Exceptions\ValidacionException;
use App\Models\Usuario;
use App\Repositories\UsuarioRepository;

class UsuarioService
{
    public function __construct(
        private readonly UsuarioRepository $repositorio,
        private readonly Logger $log
    ) {
    }

    /* ---------------------------------------------------------------
     * Consultas
     * --------------------------------------------------------------- */

    /**
     * Lista usuarios con filtros y paginación (solo para administradores).
     *
     * @return array{usuarios: array<int, array<string, mixed>>, total: int, pagina: int}
     *         La página devuelta está siempre acotada al rango válido.
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function listarParaAdministrador(string $busqueda, ?int $rolId, string $estado, int $pagina): array
    {
        $this->requerirAdministrador('listar usuarios');

        $porPagina = 10;
        $total = $this->repositorio->contarFiltrados($busqueda, $rolId, $estado);

        // Acota la página al rango real antes de calcular el OFFSET,
        // para que ?pagina=999 nunca devuelva una tabla vacía.
        $totalPaginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $totalPaginas);

        $desplazamiento = ($pagina - 1) * $porPagina;

        return [
            'usuarios' => $this->repositorio->listar($busqueda, $rolId, $estado, $porPagina, $desplazamiento),
            'total'    => $total,
            'pagina'   => $pagina,
        ];
    }

    /**
     * Busca un usuario por id o lanza la excepción correspondiente.
     *
     * @param int $id Identificador.
     * @return array<string, mixed> Datos del usuario con rol y programa.
     * @throws UsuarioNoEncontradoException Si no existe.
     */
    public function obtener(int $id): array
    {
        $usuario = $this->repositorio->buscarCompleto($id);
        if ($usuario === null) {
            throw new UsuarioNoEncontradoException($id);
        }
        return $usuario;
    }

    /**
     * Estadísticas del dashboard. Requiere rol administrador.
     *
     * @return array{estadisticas: array<string, int>,
     *               distribucion: array<int, array<string, mixed>>}
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function estadisticas(): array
    {
        $this->requerirAdministrador('consultar estadísticas');
        return [
            'estadisticas' => $this->repositorio->contarPorEstado(),
            'distribucion' => $this->repositorio->contarPorRol(),
        ];
    }

    /* ---------------------------------------------------------------
     * Escritura
     * --------------------------------------------------------------- */

    /**
     * Cuenta un usuario nuevo tras validar todos los datos.
     *
     * @param array<string, mixed> $datos Datos crudos del formulario.
     * @return int Id del usuario creado.
     * @throws ValidacionException Si algún dato no cumple las reglas.
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function crear(array $datos): int
    {
        $this->requerirAdministrador('crear usuarios');

        $limpios = $this->validar($datos);

        if ($this->repositorio->correoExiste($limpios['correo'])) {
            throw new ValidacionException('Ya existe una cuenta con ese correo institucional.');
        }
        if ($this->repositorio->documentoExiste($limpios['numero_documento'])) {
            throw new ValidacionException('Ya existe un usuario con ese número de identificación.');
        }

        // password_hash con PASSWORD_DEFAULT (bcrypt): jamás texto plano.
        $limpios['password_hash'] = password_hash($datos['clave'], PASSWORD_DEFAULT);

        $id = $this->repositorio->crear($limpios);
        $this->log->info("Creó el usuario '{$limpios['correo']}' (id #{$id}).");
        return $id;
    }

    /**
     * Actualiza los datos de un usuario existente.
     *
     * @param int $id Identificador a editar.
     * @param array<string, mixed> $datos Datos del formulario.
     * @return void
     * @throws ValidacionException Si los datos no son válidos.
     * @throws UsuarioNoEncontradoException Si no existe.
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function editar(int $id, array $datos): void
    {
        $this->requerirAdministrador('editar usuarios');

        $existentes = $this->obtener($id);
        $limpios = $this->validar($datos, $id);

        if ($this->repositorio->correoExiste($limpios['correo'], $id)) {
            throw new ValidacionException('Ya existe otra cuenta con ese correo institucional.');
        }
        if ($this->repositorio->documentoExiste($limpios['numero_documento'], $id)) {
            throw new ValidacionException('Otro usuario ya usa ese número de identificación.');
        }

        // Protección: no se puede quitar el último administrador activo.
        $rolAnterior = (int)$existentes['rol_id'];
        $estadoAnterior = $existentes['estado'];
        $pierdeAdmin = $rolAnterior === 1
            && ($limpios['rol_id'] !== 1 || $limpios['estado'] !== 'activo');
        if ($pierdeAdmin && $this->repositorio->contarAdministradoresActivos($id) === 0) {
            throw new ValidacionException(
                'No se puede quitar el rol de administrador al último administrador activo.'
            );
        }

        $this->repositorio->actualizar($id, $limpios);
        $this->log->info("Editó el usuario '{$limpios['correo']}' (id #{$id}).");
    }

    /**
     * Desactiva un usuario (borrado lógico).
     *
     * @param int $id Identificador.
     * @return void
     * @throws UsuarioNoEncontradoException Si no existe.
     * @throws ValidacionException Si es el último administrador activo.
     * @throws AccesoDenegadoException Si el rol no está autorizado.
     */
    public function eliminar(int $id): void
    {
        $this->requerirAdministrador('eliminar usuarios');

        $usuario = $this->obtener($id);

        $esAdminActivo = (int)$usuario['rol_id'] === 1 && $usuario['estado'] === 'activo';
        if ($esAdminActivo && $this->repositorio->contarAdministradoresActivos($id) === 0) {
            throw new ValidacionException(
                'No se puede eliminar al único administrador activo del sistema.'
            );
        }

        $this->repositorio->desactivar($id);
        $this->log->info("Desactivó la cuenta de '{$usuario['correo']}' (id #{$id}).");
    }

    /* ---------------------------------------------------------------
     * Perfil personal
     * --------------------------------------------------------------- */

    /**
     * Permite a un usuario actualizar su propio perfil (sin rol ni estado).
     *
     * @param int $idDueño Id del usuario dueño del perfil.
     * @param array<string, mixed> $datos Datos del formulario.
     * @return void
     * @throws ValidacionException Si los datos no son válidos.
     * @throws UsuarioNoEncontradoException Si no existe.
     */
    public function actualizarPerfilPropio(int $idDueño, array $datos): void
    {
        $existentes = $this->obtener($idDueño);

        $errores = [];
        $nombre = trim((string)($datos['nombre'] ?? ''));
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
            $errores[] = 'El nombre debe tener entre 3 y 100 caracteres.';
        }

        $correo = strtolower(trim((string)($datos['correo'] ?? '')));
        if (!$this->correoValido($correo)) {
            $errores[] = 'Ingresa un correo institucional válido.';
        } elseif ($this->repositorio->correoExiste($correo, $idDueño)) {
            $errores[] = 'Otro usuario ya está usando ese correo.';
        }

        $numero = trim((string)($datos['numero_documento'] ?? ''));
        if (!preg_match('/^[0-9A-Za-z]{5,15}$/', $numero)) {
            $errores[] = 'El número de identificación debe tener entre 5 y 15 caracteres.';
        } elseif ($this->repositorio->documentoExiste($numero, $idDueño)) {
            $errores[] = 'Otro usuario ya está usando ese número de identificación.';
        }

        $tipoDocumento = strtoupper(trim((string)($datos['tipo_documento'] ?? '')));
        if (!in_array($tipoDocumento, ['CC', 'TI', 'CE', 'PP'], true)) {
            $errores[] = 'Selecciona un tipo de identificación válido.';
        }

        $programaId = (int)($datos['programa_id'] ?? 0);
        if ($programaId <= 0) {
            $errores[] = 'Selecciona tu programa académico.';
        }

        // Cambio de contraseña opcional: si se envía, se valida todo el flujo.
        $claveNueva = (string)($datos['clave'] ?? '');
        if ($claveNueva !== '') {
            if (!password_verify((string)($datos['clave_actual'] ?? ''), $existentes['password_hash'])) {
                $errores[] = 'La contraseña actual no coincide.';
            }
            $errores = array_merge($errores, $this->validarClave($claveNueva, (string)($datos['clave_confirmacion'] ?? '')));
        }

        if ($errores !== []) {
            throw new ValidacionException($errores);
        }

        $this->repositorio->actualizar($idDueño, [
            'nombre'           => $nombre,
            'correo'           => $correo,
            'rol_id'           => (int)$existentes['rol_id'],
            'tipo_documento'   => $tipoDocumento,
            'numero_documento' => $numero,
            'programa_id'      => $programaId,
            'estado'           => (string)$existentes['estado'],
        ]);

        if ($claveNueva !== '') {
            $this->repositorio->actualizarPassword(
                $idDueño,
                password_hash($claveNueva, PASSWORD_DEFAULT)
            );
        }

        // Mantiene la sesión sincronizada con los datos nuevos.
        $_SESSION['usuario']['nombre'] = $nombre;
        $_SESSION['usuario']['correo'] = $correo;

        $this->log->info('Actualizó su propio perfil (id #' . $idDueño . ').');
    }

    /* ---------------------------------------------------------------
     * Validaciones internas
     * --------------------------------------------------------------- */

    /**
     * Valida y normaliza los datos de un formulario de usuario.
     *
     * @param array<string, mixed> $datos Datos crudos.
     * @param int|null $excluirId Id a excluir de las comprobaciones de unicidad.
     * @return array<string, mixed> Datos limpios.
     * @throws ValidacionException Con todos los errores encontrados.
     */
    private function validar(array $datos, ?int $excluirId = null): array
    {
        $errores = [];

        $nombre = trim((string)($datos['nombre'] ?? ''));
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
            $errores[] = 'El nombre debe tener entre 3 y 100 caracteres.';
        }

        $correo = strtolower(trim((string)($datos['correo'] ?? '')));
        if (!$this->correoValido($correo)) {
            $errores[] = 'El correo debe ser institucional (@' . DOMINIO_CORREO . ').';
        }

        $numero = trim((string)($datos['numero_documento'] ?? ''));
        if (!preg_match('/^[0-9A-Za-z]{5,15}$/', $numero)) {
            $errores[] = 'El número de identificación debe tener entre 5 y 15 caracteres alfanuméricos.';
        }

        $tipoDocumento = strtoupper(trim((string)($datos['tipo_documento'] ?? '')));
        if (!in_array($tipoDocumento, ['CC', 'TI', 'CE', 'PP'], true)) {
            $errores[] = 'Selecciona un tipo de identificación válido.';
        }

        $rolId = (int)($datos['rol_id'] ?? 0);
        if (!in_array($rolId, [1, 2], true)) {
            $errores[] = 'Selecciona un rol válido.';
        }

        $programaId = (int)($datos['programa_id'] ?? 0);
        if ($programaId <= 0) {
            $errores[] = 'Selecciona un programa académico.';
        }

        $estado = (string)($datos['estado'] ?? 'activo');
        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            $errores[] = 'Selecciona un estado válido.';
        }

        // En edición la contraseña es opcional; en creación, obligatoria.
        $clave = (string)($datos['clave'] ?? '');
        if ($excluirId === null) {
            $errores = array_merge($errores, $this->validarClave(
                $clave,
                (string)($datos['clave_confirmacion'] ?? '')
            ));
        } elseif ($clave !== '') {
            $errores = array_merge($errores, $this->validarClave(
                $clave,
                (string)($datos['clave_confirmacion'] ?? '')
            ));
        }

        if ($errores !== []) {
            throw new ValidacionException($errores);
        }

        return [
            'nombre'           => $nombre,
            'correo'           => $correo,
            'rol_id'           => $rolId,
            'tipo_documento'   => $tipoDocumento,
            'numero_documento' => $numero,
            'programa_id'      => $programaId,
            'estado'           => $estado,
        ];
    }

    /**
     * Valida la política de contraseñas.
     *
     * Reglas: mínimo 8 caracteres, una mayúscula y un número.
     *
     * @return array<int, string> Errores encontrados (vacío si es válida).
     */
    private function validarClave(string $clave, string $confirmacion): array
    {
        $errores = [];
        if (mb_strlen($clave) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $clave) || !preg_match('/[0-9]/', $clave)) {
            $errores[] = 'La contraseña debe incluir al menos una mayúscula y un número.';
        }
        if ($clave !== $confirmacion) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
        return $errores;
    }

    /**
     * Verifica que el correo termine en el dominio institucional.
     *
     * @param string $correo Correo a validar.
     * @return bool
     */
    private function correoValido(string $correo): bool
    {
        $patron = '/^[^@\s]+@' . preg_quote(DOMINIO_CORREO, '/') . '$/i';
        return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false
            && preg_match($patron, $correo) === 1;
    }

    /**
     * Comprueba que la sesión actual pertenece a un administrador.
     *
     * @param string $accion Descripción de la operación (para el log).
     * @return void
     * @throws AccesoDenegadoException Si no está autorizado.
     */
    private function requerirAdministrador(string $accion): void
    {
        $rolId = $_SESSION['usuario']['rol_id'] ?? 0;
        if ($rolId !== 1) {
            $correo = $_SESSION['usuario']['correo'] ?? 'invitado';
            $this->log->advertencia(
                "Intento de acceso no autorizado: '{$correo}' intentó {$accion}."
            );
            throw new AccesoDenegadoException(
                'Tu rol no tiene permiso para realizar esta operación.'
            );
        }
    }
}
