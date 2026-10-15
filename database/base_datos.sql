-- ==================================================================
-- SpaceFlow — Script de base de datos
-- Institución Educativa Litoral · Plataforma de gestión
--
-- Contiene: base de datos, tablas, restricciones, índices y datos
-- iniciales de prueba (roles, programas, usuarios y equipos).
--
-- Importar desde phpMyAdmin o desde la consola:
--   C:\xampp\mysql\bin\mysql.exe -u root < base_datos.sql
-- ==================================================================

CREATE DATABASE IF NOT EXISTS spaceflow
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE spaceflow;

-- ------------------------------------------------------------------
-- Tabla: roles
-- Catálogo de roles del sistema (autorización basada en roles).
-- ------------------------------------------------------------------
DROP TABLE IF EXISTS reservas;
DROP TABLE IF EXISTS equipos;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS programas;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(50)  NOT NULL,
    descripcion VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB;

-- ------------------------------------------------------------------
-- Tabla: programas
-- Catálogo académico de la institución (Técnicas, Tecnologías y
-- Profesionales).
-- ------------------------------------------------------------------
CREATE TABLE programas (
    id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    nivel  ENUM('tecnica','tecnologia','profesional') NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_programas_nombre (nombre)
) ENGINE=InnoDB;

-- ------------------------------------------------------------------
-- Tabla: usuarios
-- Cuentas de la plataforma. El borrado es LÓGICO: se cambia el estado
-- a 'inactivo' para conservar la traza de auditoría y las reservas
-- asociadas (justificación documentada en el README).
-- ------------------------------------------------------------------
CREATE TABLE usuarios (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre             VARCHAR(100) NOT NULL,
    correo             VARCHAR(150) NOT NULL,
    password_hash      VARCHAR(255) NOT NULL,
    rol_id             INT UNSIGNED NOT NULL,
    tipo_documento     ENUM('CC','TI','CE','PP') NOT NULL DEFAULT 'CC',
    numero_documento   VARCHAR(20)  NOT NULL,
    programa_id        INT UNSIGNED NULL,
    estado             ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    fecha_creacion     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP   NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_correo (correo),
    UNIQUE KEY uq_usuarios_documento (numero_documento),
    KEY idx_usuarios_rol (rol_id),
    KEY idx_usuarios_estado (estado),
    KEY idx_usuarios_nombre (nombre),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id)
        REFERENCES roles (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_usuarios_programa FOREIGN KEY (programa_id)
        REFERENCES programas (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------------
-- Tabla: equipos
-- Portátiles disponibles para préstamo.
-- ------------------------------------------------------------------
CREATE TABLE equipos (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo      VARCHAR(30)  NOT NULL,
    nombre      VARCHAR(100) NOT NULL,
    marca       VARCHAR(60)  NULL,
    estado      ENUM('disponible','prestado','mantenimiento','baja') NOT NULL DEFAULT 'disponible',
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_equipos_codigo (codigo),
    KEY idx_equipos_estado (estado)
) ENGINE=InnoDB;

-- ------------------------------------------------------------------
-- Tabla: reservas
-- Préstamos de portátiles por bloques horarios (máximo 6 horas).
--
-- Ciclo de vida del préstamo (decisión de diseño documentada):
--   activa     solicitud confirmada; el estudiante aún no recoge el equipo
--   prestamo   el ADMIN entregó físicamente el equipo (equipo -> prestado)
--   entregada  el ADMIN confirmó la devolución (equipo -> disponible)
--   cancelada  anulada por el estudiante (solo la suya) o por el admin
--   vencida    pasó la hora de fin sin que se recogiera (activa -> vencida)
--
-- La entrega y la devolución las marca SIEMPRE el administrador: un
-- estudiante no puede declarar que devolvió un equipo que la biblioteca
-- no ha recibido. El solapamiento se valida en el servicio PHP antes de
-- insertar (consultas preparadas), no a nivel de base de datos.
-- ------------------------------------------------------------------
CREATE TABLE reservas (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id    INT UNSIGNED NOT NULL,
    equipo_id     INT UNSIGNED NOT NULL,
    fecha         DATE         NOT NULL,
    hora_inicio   TIME         NOT NULL,
    hora_fin      TIME         NOT NULL,
    estado        ENUM('activa','prestamo','entregada','cancelada','vencida') NOT NULL DEFAULT 'activa',
    motivo        VARCHAR(255) NULL,
    fecha_creacion TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reservas_equipo_fecha (equipo_id, fecha),
    KEY idx_reservas_usuario (usuario_id),
    KEY idx_reservas_estado (estado),
    CONSTRAINT fk_reservas_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reservas_equipo FOREIGN KEY (equipo_id)
        REFERENCES equipos (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ==================================================================
-- Datos iniciales
-- ==================================================================

INSERT INTO roles (nombre, descripcion) VALUES
('admin',    'Administrador de la plataforma: gestiona usuarios, equipos, reservas y consulta los registros.'),
('usuario',  'Usuario institucional: consulta su perfil y reserva equipos disponibles.');

INSERT INTO programas (nombre, nivel) VALUES
('Técnica Profesional en Procesos Administrativos', 'tecnica'),
('Técnica Profesional en Operaciones Contables y Financieras', 'tecnica'),
('Técnica Profesional de Procesos Administrativos en Salud', 'tecnica'),
('Técnica Profesional de Seguridad y Salud en el Trabajo', 'tecnica'),
('Técnica Profesional en Producción Publicitaria Digital', 'tecnica'),
('Técnica Profesional en Procesos de Marketing Digital', 'tecnica'),
('Técnica Profesional en Programación Web', 'tecnica'),
('Técnica Profesional en Operaciones de Redes de Datos', 'tecnica'),
('Técnica Profesional en Operaciones Logísticas', 'tecnica'),
('Técnica Profesional de Operaciones en Comercio Exterior', 'tecnica'),
('Técnica Profesional de Procesos Administrativos Portuarios', 'tecnica'),
('Técnica Profesional de Procesos Administrativos en Hotelería y Turismo', 'tecnica'),
('Tecnología en Gestión Administrativa', 'tecnologia'),
('Tecnología en Gestión de la Seguridad y Salud en el Trabajo', 'tecnologia'),
('Tecnología en Gestión de Marketing y Ventas Digitales', 'tecnologia'),
('Tecnología en Desarrollo de Software', 'tecnologia'),
('Tecnología en Gestión de Redes de Datos', 'tecnologia'),
('Administración de Empresas', 'profesional'),
('Ingeniería de Sistemas', 'profesional'),
('Profesional Marketing y Negocios Digitales', 'profesional'),
('Administración en Seguridad y Salud en el Trabajo', 'profesional');

-- Usuarios de prueba.
-- Contraseñas (documentadas solo en el README, aquí solo vive el hash):
--   admin@litoral.edu.co    -> Admin1234
--   usuario@litoral.edu.co  -> Usuario1234
--   carlos.mendez@litoral.edu.co -> Usuario1234
-- Los hashes se generaron con password_hash(PASSWORD_DEFAULT).
INSERT INTO usuarios (nombre, correo, password_hash, rol_id, tipo_documento, numero_documento, programa_id, estado) VALUES
('Ana Torres',    'ana.torres@litoral.edu.co',    '$2y$10$wv6PwLjG5AJfuJoXGOrIPOn3C70jAcBOo6of9QGDlpvOzmBBo4D5K', 1, 'CC', '1032456789', 16, 'activo'),
('Carlos Méndez', 'carlos.mendez@litoral.edu.co', '$2y$10$L37oc1KmmWTcxnt8TWDrF..gMGcgAD8aGWkzrO8c0ZbTU0lC444Oa', 2, 'CC', '1015987654', 7,  'activo'),
('Lucía Gómez',   'lucia.gomez@litoral.edu.co',   '$2y$10$L37oc1KmmWTcxnt8TWDrF..gMGcgAD8aGWkzrO8c0ZbTU0lC444Oa', 2, 'TI', '1123456789', 15, 'activo'),
('Jorge Ruiz',    'jorge.ruiz@litoral.edu.co',    '$2y$10$L37oc1KmmWTcxnt8TWDrF..gMGcgAD8aGWkzrO8c0ZbTU0lC444Oa', 2, 'CC', '80123456',   13, 'inactivo'),
('María Fernanda López', 'maria.lopez@litoral.edu.co', '$2y$10$L37oc1KmmWTcxnt8TWDrF..gMGcgAD8aGWkzrO8c0ZbTU0lC444Oa', 2, 'CC', '1098765432', 19, 'activo'),
('Diego Herrera', 'diego.herrera@litoral.edu.co', '$2y$10$L37oc1KmmWTcxnt8TWDrF..gMGcgAD8aGWkzrO8c0ZbTU0lC444Oa', 2, 'CE', 'CE998877',   17, 'activo');

-- Equipos de prueba
INSERT INTO equipos (codigo, nombre, marca, estado) VALUES
('PORT-001', 'Portátil Lenovo IdeaPad 3',    'Lenovo',       'disponible'),
('PORT-002', 'Portátil Lenovo IdeaPad 3',    'Lenovo',       'disponible'),
('PORT-003', 'Portátil HP 245 G8',           'HP',           'disponible'),
('PORT-004', 'Portátil HP 245 G8',           'HP',           'prestado'),
('PORT-005', 'Portátil Dell Latitude 5420',  'Dell',         'disponible'),
('PORT-006', 'Portátil Acer Aspire 5',       'Acer',         'mantenimiento'),
('PORT-007', 'Portátil ASUS VivoBook 15',    'ASUS',         'disponible'),
('PORT-008', 'Portátil Macbook Air M1',      'Apple',        'disponible');

-- Reservas de prueba. Se generan con fechas relativas a HOY para que el
-- módulo se pueda demostrar siempre: una pasada (entregada), una de hoy
-- en curso (prestamo, para ver la cuenta regresiva y los avisos), una
-- de hoy aún activa (pendiente de recogida) y una de mañana.
INSERT INTO reservas (usuario_id, equipo_id, fecha, hora_inicio, hora_fin, estado, motivo) VALUES
(2, 4, CURDATE(), '08:00:00', '12:00:00', 'prestamo',  'Trabajo de grado — en préstamo ahora'),
(3, 5, CURDATE() - INTERVAL 2 DAY, '08:00:00', '10:00:00', 'entregada', 'Proyecto de investigación'),
(5, 3, CURDATE(), '14:00:00', '17:00:00', 'activa',  'Taller práctico de programación'),
(6, 7, CURDATE() + INTERVAL 1 DAY, '08:00:00', '10:00:00', 'activa', 'Presentación de proyecto final');
