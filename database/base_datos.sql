CREATE DATABASE IF NOT EXISTS reservas_deportivas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE reservas_deportivas;
SET time_zone = '-05:00';

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(10) NOT NULL,
    correo VARCHAR(150) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    rol ENUM('usuario', 'admin') NOT NULL DEFAULT 'usuario',
    PRIMARY KEY (id),
    UNIQUE KEY usuario_telefono (telefono),
    UNIQUE KEY usuario_correo (correo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS escenarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    unidad_deportiva VARCHAR(100) NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    superficie VARCHAR(80) DEFAULT NULL,
    barrio VARCHAR(80) DEFAULT NULL,
    comuna VARCHAR(40) DEFAULT NULL,
    direccion VARCHAR(180) DEFAULT NULL,
    capacidad INT UNSIGNED DEFAULT NULL,
    hora_apertura TIME DEFAULT NULL,
    hora_cierre TIME DEFAULT NULL,
    costo_hora DECIMAL(10, 2) DEFAULT 0,
    imagen VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY escenario_nombre_unidad (nombre, unidad_deportiva),
    KEY escenario_filtros (tipo, comuna, activo)
) ENGINE=InnoDB;

-- Si se importa sobre la versión anterior, se conserva su tabla de reservas
-- con otro nombre. No se asignan reservas antiguas a usuarios arbitrariamente.
DROP PROCEDURE IF EXISTS preparar_tabla_reservas;
DELIMITER $$
CREATE PROCEDURE preparar_tabla_reservas()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'reservas'
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'reservas'
          AND column_name = 'id_usuario'
    ) THEN
        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'reservas_anteriores'
        ) THEN
            RENAME TABLE reservas TO reservas_anteriores;
        ELSE
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Ya existe reservas_anteriores; revise o respalde las reservas antes de importar.';
        END IF;
    END IF;
END$$
DELIMITER ;
CALL preparar_tabla_reservas();
DROP PROCEDURE preparar_tabla_reservas;

CREATE TABLE IF NOT EXISTS reservas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_escenario INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    estado ENUM('Activa', 'Modificada', 'Cancelada') NOT NULL DEFAULT 'Activa',
    PRIMARY KEY (id),
    KEY reserva_horario (id_escenario, fecha, estado, hora_inicio, hora_fin),
    KEY reserva_usuario (id_usuario, fecha, hora_inicio),
    CONSTRAINT chk_reserva_duracion
        CHECK (hora_fin = ADDTIME(hora_inicio, '01:00:00')),
    CONSTRAINT fk_reservas_escenarios_v2
        FOREIGN KEY (id_escenario) REFERENCES escenarios (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservas_usuarios_v2
        FOREIGN KEY (id_usuario) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Cuentas de ejemplo. Las contraseñas ya están cifradas con password_hash().
INSERT IGNORE INTO usuarios (nombre, telefono, correo, contrasena, rol)
VALUES
    ('Administración', '3000000001', 'admin@reservas.test',
     '$2y$10$ozVBOLeS6v3H0Q2V5HBO4eeBMzQl/BTXcf2JMKoX9vdmlDGjQJxwW', 'admin'),
    ('Usuario de prueba', '3000000002', 'usuario@reservas.test',
     '$2y$10$jDDCaddfk2jATCJSX2BgaOW3KJkRWykHDzqaKQ0RjP88T.GgVLI8G', 'usuario');

-- Solo se cargan escenarios de las unidades indicadas.
-- Los nombres de cada espacio deben confirmarse con la unidad correspondiente.
-- Horarios de ejemplo solicitados para la demostración académica; NO son horarios oficiales.
-- VERIFICAR: confirmar todos los horarios con cada unidad antes de usar el sistema en la realidad.
-- Las tarifas, superficies y capacidades desconocidas se dejan NULL.
-- Los datos conocidos de Belén y El Raizal se limitan a los indicados para el proyecto.
INSERT IGNORE INTO escenarios
    (nombre, unidad_deportiva, tipo, superficie, barrio, comuna, direccion,
     capacidad, hora_apertura, hora_cierre, costo_hora, imagen)
VALUES
    -- VERIFICAR: confirmar que este escenario existe y su nombre oficial.
    ('Cancha de fútbol', 'Atanasio Girardot', 'Fútbol',
     NULL, 'Estadio', '11', NULL, NULL, '07:00:00', '22:00:00', NULL, 'img/futbol.jpg'),
    -- VERIFICAR: confirmar que esta piscina existe en la unidad.
    ('Piscina - Atanasio Girardot', 'Atanasio Girardot', 'Piscina',
     NULL, 'Estadio', '11', NULL, NULL, '06:00:00', '18:00:00', NULL, 'img/piscina.jpg'),
    -- VERIFICAR: confirmar que esta cancha y disciplina existen en la unidad.
    ('Cancha de tenis - Atanasio Girardot', 'Atanasio Girardot', 'Tenis',
     NULL, 'Estadio', '11', NULL, NULL, '08:00:00', '21:00:00', NULL, 'img/tenis.jpg'),
    -- VERIFICAR: confirmar existencia y nombre oficial de cada espacio de Belén.
    ('Cancha de baloncesto', 'Belén', 'Baloncesto',
     NULL, 'Belén', '16', 'CR 69 # 30A 130', NULL, '08:00:00', '21:00:00', NULL, 'img/baloncesto.jpg'),
    ('Cancha de voleibol', 'Belén', 'Voleibol',
     NULL, 'Belén', '16', 'CR 69 # 30A 130', NULL, '08:00:00', '21:00:00', NULL, 'img/voleibol.jpg'),
    -- VERIFICAR: confirmar existencia y características de la piscina de Belén.
    ('Piscina', 'Belén', 'Piscina',
     NULL, 'Belén', '16', 'CR 69 # 30A 130', NULL, '06:00:00', '18:00:00', NULL, 'img/piscina.jpg'),
    -- VERIFICAR: confirmar existencia del escenario y datos oficiales de La Floresta.
    ('Cancha sintética - La Floresta', 'La Floresta', 'Fútbol',
     NULL, 'La Floresta', '12', NULL, NULL, '07:00:00', '22:00:00', NULL, 'img/futbol.jpg'),
    -- VERIFICAR: confirmar existencia del escenario y datos oficiales de Castilla.
    ('Cancha múltiple - Castilla', 'Castilla', 'Baloncesto',
     NULL, 'Castilla', '5', NULL, NULL, '08:00:00', '21:00:00', NULL, 'img/baloncesto.jpg'),
    -- VERIFICAR: confirmar nombre del escenario y datos de ubicación de San Cristóbal.
    ('Cancha de fútbol - San Cristóbal', 'San Cristóbal', 'Fútbol',
     NULL, 'San Cristóbal', NULL, NULL, NULL, '07:00:00', '22:00:00', NULL, 'img/futbol.jpg'),
    -- VERIFICAR: confirmar existencia del escenario y datos oficiales de Miraflores.
    ('Cancha múltiple - Miraflores', 'Miraflores', 'Voleibol',
     NULL, 'Miraflores', '9', NULL, NULL, '08:00:00', '21:00:00', NULL, 'img/voleibol.jpg'),
    -- VERIFICAR: confirmar nombre oficial, dirección, comuna y características de El Raizal.
    ('Cancha sintética El Raizal', 'El Raizal', 'Fútbol',
     NULL, 'Manrique', '3', NULL, NULL, '07:00:00', '22:00:00', NULL, 'img/futbol.jpg'),
    -- VERIFICAR: confirmar existencia del escenario y sus características.
    ('Cancha múltiple - Belén', 'Belén', 'Tenis',
     NULL, 'Belén', '16', 'CR 69 # 30A 130', NULL, '08:00:00', '21:00:00', NULL, 'img/tenis.jpg');

-- Reservas demostrativas con fechas relativas. Verifique horarios antes de usarlas.
INSERT INTO reservas (id_escenario, id_usuario, fecha, hora_inicio, hora_fin, estado)
SELECT e.id, u.id, CURDATE() + INTERVAL 2 DAY, '10:00:00', '11:00:00', 'Activa'
FROM escenarios e
INNER JOIN usuarios u ON u.correo = 'usuario@reservas.test'
WHERE e.nombre = 'Cancha de fútbol'
  AND e.unidad_deportiva = 'Atanasio Girardot'
  AND NOT EXISTS (
      SELECT 1
      FROM reservas r
      WHERE r.id_escenario = e.id
        AND r.fecha = CURDATE() + INTERVAL 2 DAY
        AND r.hora_inicio = '10:00:00'
        AND r.estado <> 'Cancelada'
  );

INSERT INTO reservas (id_escenario, id_usuario, fecha, hora_inicio, hora_fin, estado)
SELECT e.id, u.id, CURDATE() + INTERVAL 3 DAY, '14:00:00', '15:00:00', 'Activa'
FROM escenarios e
INNER JOIN usuarios u ON u.correo = 'usuario@reservas.test'
WHERE e.nombre = 'Piscina - Atanasio Girardot'
  AND e.unidad_deportiva = 'Atanasio Girardot'
  AND NOT EXISTS (
      SELECT 1
      FROM reservas r
      WHERE r.id_escenario = e.id
        AND r.fecha = CURDATE() + INTERVAL 3 DAY
        AND r.hora_inicio = '14:00:00'
        AND r.estado <> 'Cancelada'
  );
