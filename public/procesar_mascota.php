<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    
    try {
        // Iniciar transacción para asegurar que ambas tabdas se llenen o ninguna (ACID)
        $pdo->beginTransaction();

        // 1. Insertar Dueño
        $stmtDueno = $pdo->prepare("INSERT INTO duenos (nombre, telefono) VALUES (:nombre, :telefono)");
        $stmtDueno->execute([
            ':nombre'   => $_POST['dueno_nombre'],
            ':telefono' => $_POST['dueno_telefono']
        ]);
        $dueno_id = $pdo->lastInsertId();

        // 2. Insertar Mascota
        $stmtMascota = $pdo->prepare("
            INSERT INTO mascotas (dueno_id, identificador, nombre, especie, raza, peso, fecha_nacimiento) 
            VALUES (:dueno_id, :identificador, :nombre, :especie, :raza, :peso, :fecha_nacimiento)
        ");
        $stmtMascota->execute([
            ':dueno_id'         => $dueno_id,
            ':identificador'    => $_POST['identificador'],
            ':nombre'           => $_POST['nombre'],
            ':especie'          => $_POST['especie'],
            ':raza'             => $_POST['raza'],
            ':peso'             => $_POST['peso'],
            ':fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null
        ]);

        $pdo->commit();
        
        $_SESSION['mensaje'] = "Registro guardado exitosamente.";
        header("Location: mascotas.php");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack(); // Revertir cambios si algo falla
        error_log("Error al registrar: " . $e->getMessage());
        die("Error al guardar el registro. Intente de nuevo.");
    }
} else {
    header("Location: index.php");
    exit;
}