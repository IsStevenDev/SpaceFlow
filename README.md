# SpaceFlow

**Plataforma web de gestión universitaria** — Institución Educativa Litoral
Curso: *Programación Web II* · PHP 8 + MySQL (XAMPP)

SpaceFlow reúne un **Sistema de Gestión de Usuarios (SGU)** completo con un
**módulo de reserva de portátiles de la biblioteca**, que incluye avisos en
tiempo real (por cuenta regresiva) de los préstamos que están por terminar.

---

## 1. Requisitos

| Componente | Versión mínima |
|------------|----------------|
| XAMPP (PHP + MariaDB + Apache) | PHP **8.2** o superior, MariaDB 10.4+ |
| Navegador | Chrome, Edge, Firefox o Safari actualizados |
| Resolución | Escritorio, tableta o móvil (diseño responsive) |

No requiere Node.js, Composer ni frameworks: todo el código es propio
(PHP 8 con PDO, HTML5, CSS3 y JavaScript vanilla; Bootstrap 5 solo como
base, con hoja de estilo personalizada).

---

## 2. Instalación en XAMPP (paso a paso)

1. **Copiar el proyecto** a la carpeta de sitios del servidor:

   ```
   C:\xampp\htdocs\SpaceFlow
   ```

2. **Arrancar los servicios** desde el panel de XAMPP: **Apache** y **MySQL**.

3. **Crear la base de datos** con la semilla incluida. Cualquiera de las dos
   opciones:

   * *phpMyAdmin* (`http://localhost/phpmyadmin`):
     pestaña **Importar** → seleccionar `database/base_datos.sql` →
     **Continuar** (el script crea la base `spaceflow`, las tablas y los
     datos de ejemplo).
   * *Consola* (recomendada para conservar los acentos):

     ```
     C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < C:\xampp\htdocs\SpaceFlow\database\base_datos.sql
     ```

4. **Crear el archivo de credenciales**. No viaja en el repositorio (contiene
   datos de acceso); copia la plantilla incluida:

   ```
   copy database\config.ejemplo.php app\config\conexion.php
   ```

   Apunta por defecto a los valores de XAMPP (`root`, sin contraseña,
   `127.0.0.1:3306`); si tu MySQL usa otro usuario o clave, ajústalo ahí.

5. **Abrir la aplicación**:

   ```
   http://localhost/SpaceFlow/public
   ```

6. Iniciar sesión con las credenciales de la sección siguiente.

> ⚠️ **¿Aparece "El servicio no está disponible en este momento" al iniciar
> sesión?** Casi siempre significa que falta `app/config/conexion.php` (paso 4)
> o que la base de datos no fue importada (paso 3). El detalle exacto queda en
> `logs/aplicacion.log`.

> Si renombras la carpeta `SpaceFlow`, cambia la constante `BASE_URL` en
> `app/config/config.php` (por ejemplo `/MiProyecto/public`).

---

## 3. Credenciales de prueba

| Rol | Correo | Contraseña | Perfil |
|-----|--------|------------|--------|
| Administrador | `ana.torres@litoral.edu.co` | `Admin1234` | Gestión total (usuarios, equipos, entregas) |
| Estudiante | `carlos.mendez@litoral.edu.co` | `Usuario1234` | Reserva y cancelación de portátiles |
| Estudiante | `maria.lopez@litoral.edu.co` | `Usuario1234` | Reserva y cancelación de portátiles |

Los correos institucionales siempre terminan en `@litoral.edu.co`
(validación en servidor **y** en el navegador). Existe además el usuario
`jorge.ruiz@litoral.edu.co` (inactivo) para demostrar el bloqueo de acceso.

---

## 4. Estructura del proyecto

```
SpaceFlow/
├── app/                    ← fuera del alcance web (protegido con .htaccess)
│   ├── config/             Constantes, conexión PDO y Database (singleton)
│   ├── controllers/        Acciones HTTP por módulo
│   ├── exceptions/         9 excepciones propias (herencia + interfaz de negocio)
│   ├── helpers/            vistas.php: e(), url(), asset(), vista(), pagina(), flash()
│   ├── middleware/         Auth (sesiones + RBAC) y Csrf (tokens por formulario)
│   ├── models/             Usuario, Equipo, Reserva (POO: constantes y helpers)
│   ├── repositories/       Acceso a datos 100 % con sentencias preparadas
│   ├── services/           Reglas de negocio, Logger y LogInterface
│   └── views/              Plantillas (layouts, usuarios, equipos, reservas, logs)
├── database/
│   ├── base_datos.sql      Esquema + semilla (21 programas, 8 equipos, usuarios)
│   └── config.ejemplo.php  Plantilla de configuración de BD
├── logs/
│   ├── aplicacion.log      Registro de actividad (creado en tiempo de ejecución)
│   └── .htaccess           Deniega el acceso web directo
├── pruebas/                Suites de pruebas HTTP (herramienta de desarrollo)
├── public/                 ← único directorio accesible por URL
│   ├── *.php               Una página por acción (enrutado plano)
│   └── assets/             CSS, JS, favicon y Bootstrap/Bootstrap Icons locales
└── README.md
```

---

## 5. Módulos

### 5.1 Sistema de gestión de usuarios (SGU)
* Alta, edición, visualización y **eliminación lógica** (baja) de usuarios.
* Campos del estudiante: nombre, tipo de documento (CC/TI/CE/PAS), número de
  documento, correo institucional y **programa académico** (21 programas:
  12 técnicas, 5 tecnológicas y 4 profesionales).
* Perfil editable por el propio usuario (cambio de contraseña con verificación
  de la actual).
* Panel de **logs de actividad** con filtros por nivel e usuario.
* Protección del último administrador activo (no puede quedarse sin admin).

### 5.2 Reserva de portátiles de la biblioteca
* Ambos roles crean reservas; el estudiante solo ve y cancela **las suyas**.
* Catálogo CRUD de equipos (solo administrador), con código único
  (`PORT-00X`), marca, estado y protección del historial de reservas.
* Estados del ciclo de vida:

  ```
  activa ──entrega(admin)──▶ prestamo ──devolución(admin)──▶ entregada
    │                            │
    └──▶ cancelada (dueño o admin)   └──▶ vencida (automática si no se recoge)
  ```

  * El estudiante **nunca** marca entregas ni devoluciones: son acciones
    exclusivas del administrador, verificadas en PHP.
  * `vencida` se aplica de forma **perezosa**: cada consulta relevante marca
    primero las reservas activas cuya hora de fin ya pasó (no depende de cron).

### 5.3 Avisos "tu tiempo está por terminar"
* Cada fila del sistema expone `segundos_restantes` calculados **en el
  servidor** (endpoint JSON `notificaciones.php`), por lo que el navegador
  nunca decide con su propio reloj.
* **Estudiante**: contador regresivo en sus tarjetas y avisos flotantes
  (toasts) al faltar 30 minutos (ámbar) y 10 minutos (rojo, persistente).
* **Administrador**: panel "Próximas a vencer" + "vencidos sin devolver",
  refrescado con *polling* cada 30 segundos (sin WebSockets).
* Todo el contenido dinámico se genera con `textContent`/nodos DOM
  (protección XSS), y las animaciones respetan `prefers-reduced-motion`.

---

## 6. Reglas de negocio de la biblioteca

| Regla | Detalle |
|-------|---------|
| Franja horaria | De 6:00 a. m. a 6:00 p. m., en **bloques completos de una hora** |
| Duración | De 1 a **6 horas** por reserva |
| Antelación | Máximo **7 días**; el mismo día está permitido con advertencia visible |
| Mismo día | La hora de inicio debe ser la hora actual o posterior; la reserva depende de la disponibilidad del momento |
| Solapamiento | Un equipo nunca puede tener dos reservas que se crucen (bloques contiguos, p. ej. 10–12 y 12–14, sí se permiten); además, **un estudiante no puede tener dos reservas superpuestas** |
| Equipos | Solo los `disponible` y `prestado` son reservables (los de mantenimiento/baja no) |
| Cancelación | Solo el propietario (o el administrador) puede cancelar, y únicamente si sigue `activa` |
| Equipo eliminado | Con historial de reservas se da de **baja lógica** (nunca se borra el historial); sin historial se borra físicamente |

---

## 7. Seguridad implementada

* Contraseñas con `password_hash()` / `password_verify()`.
* `session_regenerate_id(true)` al iniciar y cerrar sesión; sesión con
  expiración por inactividad (30 min).
* **CSRF** con token por formulario (`Csrf::validar()` en todas las acciones
  POST).
* **RBAC** verificado en PHP en cada acción (páginas **y** servicios), con
  registro `WARNING` de cada intento no autorizado.
* Consultas 100 % con **sentencias preparadas** de PDO
  (`ATTR_EMULATE_PREPARES = false`).
* Escape de toda salida con `e()` (HTML), incluso en los datos inyectados al
  JavaScript.
* Directorios `app/`, `database/`, `logs/` y `pruebas/` protegidos por
  `.htaccess`; solo `public/` es accesible por URL.
* Validación de correo institucional `@litoral.edu.co` en cliente y servidor.

---

## 8. Registro de actividad (`logs/aplicacion.log`)

Cada evento se escribe con el formato:

```
[2026-10-15 09:14:02] [INFO] [ana.torres@litoral.edu.co] - Creó el usuario 'Pedro Prueba' (pedro.prueba@litoral.edu.co).
[2026-10-15 09:20:41] [WARNING] [maria.lopez@litoral.edu.co] - Intento de acceso no autorizado: 'maria...' intentó cancelar la reserva #3 de 'Carlos Méndez'.
[2026-10-15 09:31:15] [ERROR] [sistema] - Excepción no controlada (...)
```

* Niveles: `INFO`, `WARNING`, `ERROR`.
* **Nunca** escribe contraseñas, hashes ni tokens.
* El panel `/logs.php` (solo administrador) lo consulta con paginación y
  filtros; la carpeta está bloqueada por `.htaccess`.

---

## 9. Pruebas automatizadas (opcional)

En `pruebas/` hay tres suites HTTP que simulan un navegador real
(cookies + CSRF + redirecciones) y verifican los efectos en MySQL:

| Suite | Qué cubre | Resultado en la entrega |
|-------|-----------|-------------------------|
| `pruebas_etapa3.php` | SGU: login, CRUD de usuarios, roles, CSRF, logs, sesión | **47/47** |
| `pruebas_etapa4.php` | Equipos + reservas: RBAC, solapamiento, franja 6–18 h, 6 h máx., 7 días, vencimiento automático, entrega/devolución, JSON de notificaciones | **89/89** |
| `smoke_visual.php` | Assets, animaciones, toasts, cuenta regresiva y estructura de páginas | **36/36** |

Para ejecutarlas (con Apache corriendo):

```
cd C:\xampp\htdocs\SpaceFlow
C:\xampp\php\php.exe pruebas\pruebas_etapa4.php
```

Por defecto apuntan a `http://localhost/SpaceFlow/public`; si usas otro
nombre de carpeta u otro puerto, define la variable de entorno
`SPACEFLOW_BASE` antes de ejecutarlas. **Advertencia:** las suites crean y
borran datos de prueba; para volver a la semilla limpia basta reimportar
`database/base_datos.sql`.

### Checklist de pruebas manuales sugeridas

1. **Permisos**: iniciar sesión como estudiante y escribir a mano
   `usuarios.php`, `equipos.php` y `logs.php` en la barra de direcciones →
   deben mostrar la página de *Acceso denegado* (403) y registrar un
   `WARNING` en el log.
2. **Errores amigables**: crear un usuario con un correo que no sea
   `@litoral.edu.co`, un equipo con código duplicado y una reserva que cruce
   otro horario → mensajes claros, sin rastros de SQL ni excepciones.
3. **CSRF**: recargar el navegador tras enviar un formulario (botón "atrás")
   → debe rechazarse con 403.
4. **Logs**: tras realizar varias acciones, entrar a `/logs.php` y filtrar
   por nivel; comprobar que no aparecen contraseñas ni tokens.
5. **Notificaciones**: abrir la sesión del estudiante con una reserva hoy y
   acercarse a la hora de fin (o reducir `MINUTOS_AVISO_TEMPRANO` en
   `app/config/config.php` para simularlo) → toast ámbar y luego rojo.
6. **Sesión**: dejar abierta la pestaña 30 minutos sin tocar → la siguiente
   acción vuelve al login.

---

## 10. Solución de problemas

| Síntoma | Causa probable y solución |
|---------|---------------------------|
| `http://localhost/SpaceFlow/public` da 403/404 | Verifica que la carpeta esté en `C:\xampp\htdocs\` y que Apache esté activo |
| Página en blanco o error de BD | Revisa `app/config/conexion.php` y que MySQL esté corriendo |
| Acentos "Ã" por todos lados | Importa el SQL con `--default-character-set=utf8mb4` (consola) o verifica el `utf8mb4` del DSN |
| No llegan los avisos | El *polling* usa `fetch`; revisa la consola del navegador (F12) y que `notificaciones.php` responda con JSON |
| Sin permisos para escribir logs | Da permisos de escritura a la carpeta `logs/` (Windows: propiedades de la carpeta) |
| Renombré la carpeta del proyecto | Actualiza `BASE_URL` en `app/config/config.php` |

---

## 11. Mapa a los requisitos del curso

| Requisito | Dónde está |
|-----------|------------|
| POO: encapsulamiento, herencia, clase abstracta, interfaz | `app/models`, `app/exceptions` (`SpaceFlowException` abstracta; `LogInterface` implementada por `Logger`), servicios con constructores inyectados |
| Excepciones propias con mensajes amigables | `ValidacionException`, `ReservaInvalidaException`, `SolapamientoException`, `EquipoNoEncontradoException`, `UsuarioNoEncontradoException`, `AccesoDenegadoException`, `ConexionException`, `CredencialesInvalidasException` — todas con `try/catch` en los controladores |
| CRUD completo con prepared statements | `UsuarioController` + `EquipoController` + `ReservaController` sobre sus repositorios |
| Autenticación y RBAC | `Auth`, `Csrf`, `AuthService` (hash + regeneración de sesión) |
| Registro en archivo | `app/services/Logger.php` → `logs/aplicacion.log` |
| PHPDoc | Todos los archivos: cabecera de módulo, `@param`, `@return`, `@throws` |

---

*SpaceFlow — Institución Educativa Litoral · Programación Web II*
