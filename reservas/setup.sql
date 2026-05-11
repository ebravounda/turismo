-- ══════════════════════════════════════════════════════════════
--  TukTuk Norris — Script de instalación de base de datos
--  Importa este archivo desde phpMyAdmin en Plesk
--  Versión: 1.0
-- ══════════════════════════════════════════════════════════════

-- 1. Crear la base de datos (si no existe ya)
CREATE DATABASE IF NOT EXISTS `tuktuk_norris`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `tuktuk_norris`;

-- 2. Tabla principal de reservas
CREATE TABLE IF NOT EXISTS `reservas` (
    `id`         VARCHAR(20)                                  NOT NULL,
    `nombre`     VARCHAR(100)                                 NOT NULL,
    `email`      VARCHAR(150)                                 NOT NULL,
    `telefono`   VARCHAR(30)                                  NOT NULL,
    `fecha`      DATE                                         NOT NULL,
    `hora`       TIME                                         NOT NULL,
    `personas`   TINYINT UNSIGNED                             NOT NULL DEFAULT 1,
    `tipo_tour`  VARCHAR(100)                                 NOT NULL,
    `idioma`     VARCHAR(30)                                  NOT NULL DEFAULT 'Español',
    `mensaje`    TEXT                                         NULL,
    `estado`     ENUM('pendiente','confirmada','cancelada')   NOT NULL DEFAULT 'pendiente',
    `created_at` DATETIME                                     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_fecha`   (`fecha`),
    INDEX `idx_estado`  (`estado`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla de administradores (opcional, para futuras mejoras)
CREATE TABLE IF NOT EXISTS `admins` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(50)     NOT NULL UNIQUE,
    `password`   VARCHAR(255)    NOT NULL,  -- bcrypt hash
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 4. Datos de prueba (puedes eliminar estas líneas en producción)
INSERT INTO `reservas` (`id`, `nombre`, `email`, `telefono`, `fecha`, `hora`, `personas`, `tipo_tour`, `idioma`, `mensaje`, `estado`) VALUES
('RES-DEMO01', 'María García', 'maria@ejemplo.es',    '+34 600 111 222', CURDATE(),              '10:00', 2, 'Tour Centro Histórico', 'Español', 'Primera vez en Málaga, muy emocionada!', 'confirmada'),
('RES-DEMO02', 'John Smith',   'john@example.com',    '+44 7700 900123', CURDATE(),              '12:00', 4, 'Tour Playa y Puerto',   'English', 'Family with 2 children aged 8 and 10',  'pendiente'),
('RES-DEMO03', 'Pierre Dupont','pierre@exemple.fr',   '+33 6 12 34 56 78', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00', 2, 'Tour Ruta Picasso', 'Français', '', 'pendiente'),
('RES-DEMO04', 'Ana Martínez', 'ana@ejemplo.es',      '+34 655 333 444', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '18:00', 3, 'Tour Atardecer',    'Español', 'Es para un cumpleaños', 'confirmada');

-- ══════════════════════════════════════════════════════════════
--  Consultas útiles para administración
-- ══════════════════════════════════════════════════════════════

-- Ver reservas de hoy:
-- SELECT * FROM reservas WHERE fecha = CURDATE() ORDER BY hora;

-- Ver reservas pendientes:
-- SELECT * FROM reservas WHERE estado = 'pendiente' ORDER BY fecha, hora;

-- Confirmar una reserva:
-- UPDATE reservas SET estado = 'confirmada' WHERE id = 'RES-XXXXXXXX';

-- Estadísticas por tour:
-- SELECT tipo_tour, COUNT(*) as total, SUM(personas) as total_personas
-- FROM reservas WHERE estado != 'cancelada'
-- GROUP BY tipo_tour ORDER BY total DESC;
