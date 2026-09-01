CREATE TABLE IF NOT EXISTS urgencias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dueno_id INT NOT NULL,
    mascota_id INT NOT NULL,
    motivo VARCHAR(500) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    minutos_llegada SMALLINT UNSIGNED NOT NULL,
    forma_pago VARCHAR(30) NOT NULL DEFAULT 'por_definir',
    estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    observacion_recepcion VARCHAR(500) NULL,
    atendida_por INT NULL,
    fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_urgencias_estado_fecha (estado, fecha_solicitud),
    INDEX idx_urgencias_dueno (dueno_id),
    INDEX idx_urgencias_mascota (mascota_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
