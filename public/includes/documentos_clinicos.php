<?php

function asegurarTablaDocumentosClinicos(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_clinicos (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        mascota_id INT NOT NULL,
        tipo VARCHAR(30) NOT NULL,
        titulo VARCHAR(180) NOT NULL,
        descripcion VARCHAR(500) NULL,
        nombre_original VARCHAR(255) NOT NULL,
        nombre_guardado VARCHAR(100) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        tamano INT UNSIGNED NOT NULL,
        token_descarga CHAR(64) NOT NULL,
        subido_por INT NOT NULL,
        fecha_subida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_documentos_token (token_descarga),
        INDEX idx_documentos_mascota_fecha (mascota_id, fecha_subida)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function tiposDocumentoClinico(): array
{
    return [
        'receta' => 'Receta médica',
        'examen' => 'Examen médico',
        'radiografia' => 'Radiografía',
        'informe' => 'Informe clínico',
        'otro' => 'Otro documento',
    ];
}

function directorioDocumentosClinicos(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documentos';
}
