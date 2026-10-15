<?php
/**
 * SpaceFlow — Configuración global de la aplicación.
 *
 * Centraliza las constantes de identidad, rutas y parámetros de seguridad
 * para que no queden valores "mágicos" repartidos por el código.
 *
 * @package SpaceFlow\Config
 */

declare(strict_types=1);

/* ------------------------------------------------------------------
 * Identidad
 * ------------------------------------------------------------------ */

/** Nombre comercial del producto. */
const NOMBRE_APP = 'SpaceFlow';

/** Institución a la que pertenece la plataforma. */
const ORGANIZACION = 'Institución Educativa Litoral';

/** Dominio institucional permitido en los correos (@litoral.edu.co). */
const DOMINIO_CORREO = 'litoral.edu.co';

/** Duración máxima (en horas) de un préstamo de equipo. */
const MAX_HORAS_PRESTAMO = 6;

/** Franja horaria de la biblioteca: apertura (6:00 a. m.). */
const HORA_BIBLIOTECA_INICIO = 6;

/** Franja horaria de la biblioteca: cierre (6:00 p. m.). */
const HORA_BIBLIOTECA_FIN = 18;

/** Días máximos de anticipación para solicitar una reserva. */
const DIAS_ANTELACION_MAX = 7;

/** Minutos antes del fin de la reserva para el aviso temprano (ámbar). */
const MINUTOS_AVISO_TEMPRANO = 30;

/** Minutos antes del fin de la reserva para el aviso crítico (rojo). */
const MINUTOS_AVISO_TARDIO = 10;

/* ------------------------------------------------------------------
 * Rutas (ajustar si el proyecto se sirve desde otra carpeta)
 * ------------------------------------------------------------------ */

/** Ruta URL base desde la que se sirve la aplicación en Apache/XAMPP. */
define('BASE_URL', '/SpaceFlow/public');

/** Ruta física absoluta de la carpeta public. */
define('RUTA_PUBLIC', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public');

/** Ruta física absoluta de la carpeta de logs (fuera del webroot). */
define('RUTA_LOGS', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs');

/* ------------------------------------------------------------------
 * Seguridad de la sesión
 * ------------------------------------------------------------------ */

/** Segundos de inactividad antes de que la sesión caduque (30 min). */
const TIEMPO_SESION = 1800;

/** Zona horaria del sistema (Colombia). */
const ZONA_HORARIA = 'America/Bogota';
