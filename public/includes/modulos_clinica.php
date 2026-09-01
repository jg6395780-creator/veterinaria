<?php

function asegurarModulosClinica(PDO $pdo): void
{
    $consultas = [
        "CREATE TABLE IF NOT EXISTS citas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dueno_id INT NOT NULL,
            mascota_id INT NOT NULL,
            veterinario_id INT NULL,
            fecha_hora DATETIME NOT NULL,
            duracion_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            motivo VARCHAR(500) NOT NULL,
            estado VARCHAR(24) NOT NULL DEFAULT 'solicitada',
            observacion VARCHAR(500) NULL,
            creada_por VARCHAR(20) NOT NULL DEFAULT 'personal',
            fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_citas_fecha_estado (fecha_hora, estado),
            INDEX idx_citas_mascota (mascota_id),
            INDEX idx_citas_veterinario (veterinario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS notificaciones (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dueno_id INT NULL,
            usuario_id INT NULL,
            titulo VARCHAR(160) NOT NULL,
            mensaje VARCHAR(500) NOT NULL,
            tipo VARCHAR(30) NOT NULL DEFAULT 'info',
            enlace VARCHAR(255) NULL,
            leida TINYINT(1) NOT NULL DEFAULT 0,
            fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notificaciones_dueno (dueno_id, leida, fecha_creacion),
            INDEX idx_notificaciones_usuario (usuario_id, leida, fecha_creacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS inventario (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(180) NOT NULL,
            categoria VARCHAR(50) NOT NULL,
            sku VARCHAR(60) NULL,
            stock DECIMAL(10,2) NOT NULL DEFAULT 0,
            stock_minimo DECIMAL(10,2) NOT NULL DEFAULT 0,
            unidad VARCHAR(30) NOT NULL DEFAULT 'unidad',
            fecha_vencimiento DATE NULL,
            costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_inventario_sku (sku),
            INDEX idx_inventario_alertas (activo, fecha_vencimiento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS recetas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            mascota_id INT NOT NULL,
            veterinario_id INT NOT NULL,
            diagnostico VARCHAR(500) NULL,
            medicamento VARCHAR(180) NOT NULL,
            dosis VARCHAR(120) NOT NULL,
            frecuencia VARCHAR(120) NOT NULL,
            duracion VARCHAR(120) NOT NULL,
            indicaciones VARCHAR(700) NULL,
            fecha_emision DATE NOT NULL,
            activa TINYINT(1) NOT NULL DEFAULT 1,
            fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_recetas_mascota_fecha (mascota_id, fecha_emision)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS presupuestos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            mascota_id INT NOT NULL,
            creado_por INT NOT NULL,
            concepto VARCHAR(220) NOT NULL,
            detalle VARCHAR(800) NULL,
            monto DECIMAL(12,2) NOT NULL,
            abonado DECIMAL(12,2) NOT NULL DEFAULT 0,
            caja_id INT NULL,
            estado VARCHAR(24) NOT NULL DEFAULT 'pendiente',
            fecha_emision DATE NOT NULL,
            fecha_vencimiento DATE NULL,
            fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_presupuestos_estado_fecha (estado, fecha_emision),
            INDEX idx_presupuestos_mascota (mascota_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS hospitalizaciones (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            mascota_id INT NOT NULL,
            veterinario_id INT NULL,
            box VARCHAR(50) NOT NULL,
            motivo VARCHAR(500) NOT NULL,
            indicaciones VARCHAR(800) NULL,
            estado VARCHAR(24) NOT NULL DEFAULT 'hospitalizado',
            fecha_ingreso DATETIME NOT NULL,
            fecha_alta DATETIME NULL,
            fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_hospitalizaciones_estado (estado, fecha_ingreso),
            INDEX idx_hospitalizaciones_mascota (mascota_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS configuracion_clinica (
            clave VARCHAR(80) PRIMARY KEY,
            valor TEXT NULL,
            fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS auditoria (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NULL,
            modulo VARCHAR(50) NOT NULL,
            accion VARCHAR(80) NOT NULL,
            registro_id INT NULL,
            detalle VARCHAR(500) NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_auditoria_fecha (fecha),
            INDEX idx_auditoria_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ,"CREATE TABLE IF NOT EXISTS carnets_mascotas (
            mascota_id INT NOT NULL PRIMARY KEY,
            token CHAR(64) NOT NULL,
            fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_carnet_token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ,"CREATE TABLE IF NOT EXISTS pagos_webpay (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            presupuesto_id INT UNSIGNED NOT NULL,
            dueno_id INT NOT NULL,
            buy_order VARCHAR(26) NOT NULL,
            session_id VARCHAR(61) NOT NULL,
            token_ws VARCHAR(64) NULL,
            monto DECIMAL(12,2) NOT NULL,
            estado VARCHAR(24) NOT NULL DEFAULT 'iniciado',
            codigo_autorizacion VARCHAR(20) NULL,
            ultimos_cuatro VARCHAR(4) NULL,
            respuesta_json TEXT NULL,
            fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_pagos_webpay_buy_order (buy_order),
            UNIQUE KEY uq_pagos_webpay_token (token_ws),
            INDEX idx_pagos_webpay_dueno (dueno_id, fecha_creacion),
            INDEX idx_pagos_webpay_presupuesto (presupuesto_id, estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($consultas as $sql) {
        $pdo->exec($sql);
    }

    $columnaFoto = $pdo->query("SHOW COLUMNS FROM mascotas LIKE 'foto_url'")->fetch();
    if (!$columnaFoto) {
        $pdo->exec("ALTER TABLE mascotas ADD COLUMN foto_url VARCHAR(500) NULL AFTER fecha_nacimiento");
    }

    $columnaCajaPresupuesto = $pdo->query("SHOW COLUMNS FROM presupuestos LIKE 'caja_id'")->fetch();
    if (!$columnaCajaPresupuesto) {
        $pdo->exec('ALTER TABLE presupuestos ADD COLUMN caja_id INT NULL AFTER abonado');
    }
}

function csrfModulo(): string
{
    if (empty($_SESSION['csrf_modulos'])) {
        $_SESSION['csrf_modulos'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_modulos'];
}

function validarCsrfModulo(): bool
{
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_modulos'] ?? '', (string)$_POST['csrf_token']);
}

function registrarAuditoria(PDO $pdo, string $modulo, string $accion, ?int $registroId = null, ?string $detalle = null): void
{
    $stmt = $pdo->prepare('INSERT INTO auditoria (usuario_id, modulo, accion, registro_id, detalle) VALUES (:usuario, :modulo, :accion, :registro, :detalle)');
    $stmt->execute([
        ':usuario' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
        ':modulo' => $modulo,
        ':accion' => $accion,
        ':registro' => $registroId,
        ':detalle' => $detalle,
    ]);
}

function crearNotificacion(PDO $pdo, ?int $duenoId, ?int $usuarioId, string $titulo, string $mensaje, string $tipo = 'info', ?string $enlace = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notificaciones (dueno_id, usuario_id, titulo, mensaje, tipo, enlace) VALUES (:dueno, :usuario, :titulo, :mensaje, :tipo, :enlace)');
    $stmt->execute([
        ':dueno' => $duenoId,
        ':usuario' => $usuarioId,
        ':titulo' => $titulo,
        ':mensaje' => $mensaje,
        ':tipo' => $tipo,
        ':enlace' => $enlace,
    ]);
}

function notificarDuenoMascota(PDO $pdo, int $mascotaId, string $titulo, string $mensaje, string $tipo = 'info'): void
{
    $stmt = $pdo->prepare('SELECT dueno_id FROM mascotas WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $mascotaId]);
    $duenoId = (int)$stmt->fetchColumn();
    if ($duenoId > 0) {
        crearNotificacion($pdo, $duenoId, null, $titulo, $mensaje, $tipo);
    }
}

function exigirRoles(array $roles): void
{
    if (!in_array($_SESSION['user_rol'] ?? '', $roles, true)) {
        header('Location: index.php');
        exit;
    }
}
