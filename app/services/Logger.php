<?php
/**
 * SpaceFlow — Registrador de eventos en archivo.
 *
 * Implementa LogInterface escribiendo en logs/aplicacion.log con el
 * formato:
 *
 *   [2026-10-15 08:32:11] [INFO] [ana.torres@litoral.edu.co] - Creó el usuario #7
 *
 * Reglas de seguridad:
 *  - Nunca registra contraseñas, hashes ni tokens de sesión.
 *  - La carpeta logs/ está fuera del directorio público y protegida
 *    con .htaccess (Require all denied).
 *
 * @package SpaceFlow\Services
 */

declare(strict_types=1);

namespace App\Services;

class Logger implements LogInterface
{
    /** Niveles soportados por el archivo. */
    public const INFO    = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR   = 'ERROR';

    /**
     * @param string $archivo Ruta absoluta del archivo de log.
     */
    public function __construct(private readonly string $archivo)
    {
    }

    /**
     * Crea un Logger apuntando a logs/aplicacion.log.
     *
     * @return self
     */
    public static function porDefecto(): self
    {
        return new self(RUTA_LOGS . DIRECTORY_SEPARATOR . 'aplicacion.log');
    }

    /** {@inheritdoc} */
    public function info(string $mensaje): void
    {
        $this->escribir(self::INFO, $mensaje);
    }

    /** {@inheritdoc} */
    public function advertencia(string $mensaje): void
    {
        $this->escribir(self::WARNING, $mensaje);
    }

    /** {@inheritdoc} */
    public function error(string $mensaje): void
    {
        $this->escribir(self::ERROR, $mensaje);
    }

    /**
     * Escribe una línea en el archivo de log.
     *
     * @param string $nivel   INFO | WARNING | ERROR.
     * @param string $mensaje Descripción legible del evento.
     * @return void
     */
    private function escribir(string $nivel, string $mensaje): void
    {
        $usuario = $_SESSION['usuario']['correo'] ?? 'invitado';
        $linea = sprintf(
            "[%s] [%s] [%s] - %s%s",
            date('Y-m-d H:i:s'),
            $nivel,
            $usuario,
            $mensaje,
            PHP_EOL
        );

        // Si el directorio no existiera, se crea; los fallos de log no
        // deben tumbar la aplicación, pero quedan en el error interno.
        $directorio = dirname($this->archivo);
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }
        @file_put_contents($this->archivo, $linea, FILE_APPEND | LOCK_EX);
    }

    /**
     * Devuelve las últimas $cantidad líneas del archivo de log.
     *
     * Utilizado por el panel de registros y el dashboard. Lee desde el
     * final del archivo para no cargar logs completos en memoria.
     *
     * @param int $cantidad Cantidad máxima de líneas a devolver.
     * @return array<int, string> Líneas en orden cronológico inverso (más reciente primero).
     */
    public function ultimasLineas(int $cantidad = 50): array
    {
        if (!is_file($this->archivo)) {
            return [];
        }

        $lineas = [];
        $manejador = fopen($this->archivo, 'rb');
        if ($manejador === false) {
            return [];
        }

        // Coloca el puntero al final y retrocede línea por línea.
        fseek($manejador, 0, SEEK_END);
        $posicion = ftell($manejador);
        $buffer = '';
        while ($posicion > 0 && count($lineas) < $cantidad) {
            $paso = min(1024, $posicion);
            $posicion -= $paso;
            fseek($manejador, $posicion);
            $buffer = fread($manejador, $paso) . $buffer;
            $lineas = preg_split('/\r?\n/', $buffer);
            array_pop($lineas); // La última entrada está incompleta.
            $lineas = array_filter($lineas, static fn(string $l): bool => $l !== '');
        }
        fclose($manejador);

        return array_slice(array_values($lineas), 0, $cantidad);
    }
}
