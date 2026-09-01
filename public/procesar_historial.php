<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/modulos_clinica.php';

if (!in_array($_SESSION['user_rol'], ['admin', 'veterinario'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: mascotas.php");
    exit;
}

$action     = trim($_POST['action']     ?? '');
$mascota_id = (int)($_POST['mascota_id'] ?? 0);
$pdo        = getDB();
asegurarModulosClinica($pdo);

$redirect = $mascota_id ? "historial.php?id=$mascota_id" : "mascotas.php";

function esVeterinarioActivo(PDO $pdo, string $nombre): bool {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nombre_completo = :nombre AND rol = 'veterinario' AND activo = 1 LIMIT 1");
    $stmt->execute([':nombre' => $nombre]);
    return (bool)$stmt->fetchColumn();
}

switch ($action) {

    case 'crear_consulta':
        $fecha      = trim($_POST['fecha_visita']  ?? '');
        $diag       = trim($_POST['diagnostico']   ?? '');
        $trat       = trim($_POST['tratamiento']   ?? '');
        $vet        = trim($_POST['veterinario']   ?? '');

        if (!$mascota_id || !$fecha || !$diag || !$trat || !$vet) {
            $_SESSION['mensaje_error'] = "Complete todos los campos de la consulta.";
            header("Location: $redirect"); exit;
        }
        if (!esVeterinarioActivo($pdo, $vet)) {
            $_SESSION['mensaje_error'] = "Seleccione un veterinario activo.";
            header("Location: $redirect"); exit;
        }
        try {
            $pdo->prepare("
                INSERT INTO historial_clinico (mascota_id, fecha_visita, diagnostico, tratamiento, veterinario)
                VALUES (:mid, :fv, :diag, :trat, :vet)
            ")->execute([':mid'=>$mascota_id, ':fv'=>$fecha, ':diag'=>$diag, ':trat'=>$trat, ':vet'=>$vet]);
            notificarDuenoMascota($pdo, $mascota_id, 'Nueva consulta registrada', 'Diagnóstico: ' . $diag . '. Tratamiento: ' . $trat . '.', 'consulta');
            $_SESSION['mensaje'] = "Consulta registrada correctamente.";
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['mensaje_error'] = "Error al registrar la consulta.";
        }
        header("Location: $redirect"); exit;

    case 'editar_consulta':
        $id    = (int)($_POST['id']          ?? 0);
        $fecha = trim($_POST['fecha_visita'] ?? '');
        $diag  = trim($_POST['diagnostico']  ?? '');
        $trat  = trim($_POST['tratamiento']  ?? '');
        $vet   = trim($_POST['veterinario']  ?? '');

        if (!$id || !$fecha || !$diag || !$trat || !$vet) {
            $_SESSION['mensaje_error'] = "Complete todos los campos.";
            header("Location: $redirect"); exit;
        }
        if (!esVeterinarioActivo($pdo, $vet)) {
            $_SESSION['mensaje_error'] = "Seleccione un veterinario activo.";
            header("Location: $redirect"); exit;
        }
        try {
            $pdo->prepare("
                UPDATE historial_clinico
                SET fecha_visita=:fv, diagnostico=:diag, tratamiento=:trat, veterinario=:vet
                WHERE id=:id AND mascota_id=:mid
            ")->execute([':fv'=>$fecha, ':diag'=>$diag, ':trat'=>$trat, ':vet'=>$vet, ':id'=>$id, ':mid'=>$mascota_id]);
            $_SESSION['mensaje'] = "Consulta actualizada correctamente.";
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['mensaje_error'] = "Error al actualizar la consulta.";
        }
        header("Location: $redirect"); exit;

    case 'eliminar_consulta':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { header("Location: $redirect"); exit; }
        try {
            $pdo->prepare("DELETE FROM historial_clinico WHERE id=:id AND mascota_id=:mid")
                ->execute([':id'=>$id, ':mid'=>$mascota_id]);
            $_SESSION['mensaje'] = "Consulta eliminada.";
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['mensaje_error'] = "Error al eliminar.";
        }
        header("Location: $redirect"); exit;

    case 'crear_vacuna':
        $nombre = trim($_POST['nombre_vacuna']      ?? '');
        $fecha  = trim($_POST['fecha_aplicacion']   ?? '');

        if (!$mascota_id || !$nombre || !$fecha) {
            $_SESSION['mensaje_error'] = "Complete los campos requeridos de la vacuna.";
            header("Location: $redirect"); exit;
        }
        try {
            $pdo->prepare("
                INSERT INTO vacunas (mascota_id, nombre_vacuna, fecha_aplicacion, fecha_proxima_dosis)
                VALUES (:mid, :nombre, :fa, :fp)
            ")->execute([
                ':mid'    => $mascota_id,
                ':nombre' => $nombre,
                ':fa'     => $fecha,
                ':fp'     => $_POST['fecha_proxima_dosis'] ?: null,
            ]);
            notificarDuenoMascota($pdo, $mascota_id, 'Nueva vacuna registrada', $nombre . ' aplicada el ' . date('d/m/Y', strtotime($fecha)) . '.', 'vacuna');
            $_SESSION['mensaje'] = "Vacuna registrada correctamente.";
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['mensaje_error'] = "Error al registrar la vacuna.";
        }
        header("Location: $redirect"); exit;

    case 'editar_vacuna':
        $id     = (int)($_POST['id']              ?? 0);
        $nombre = trim($_POST['nombre_vacuna']    ?? '');
        $fecha  = trim($_POST['fecha_aplicacion'] ?? '');

        if (!$id || !$nombre || !$fecha) {
            $_SESSION['mensaje_error'] = "Complete los campos requeridos.";
            header("Location: $redirect"); exit;
        }
        try {
            $pdo->prepare("
                UPDATE vacunas
                SET nombre_vacuna=:nombre, fecha_aplicacion=:fa, fecha_proxima_dosis=:fp
                WHERE id=:id AND mascota_id=:mid
            ")->execute([
                ':nombre' => $nombre,
                ':fa'     => $fecha,
                ':fp'     => $_POST['fecha_proxima_dosis'] ?: null,
                ':id'     => $id,
                ':mid'    => $mascota_id,
            ]);
            $_SESSION['mensaje'] = "Vacuna actualizada correctamente.";
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['mensaje_error'] = "Error al actualizar la vacuna.";
        }
        header("Location: $redirect"); exit;

    case 'eliminar_vacuna':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { header("Location: $redirect"); exit; }
        try {
            $pdo->prepare("DELETE FROM vacunas WHERE id=:id AND mascota_id=:mid")
                ->execute([':id'=>$id, ':mid'=>$mascota_id]);
            $_SESSION['mensaje'] = "Vacuna eliminada.";
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['mensaje_error'] = "Error al eliminar.";
        }
        header("Location: $redirect"); exit;

    default:
        header("Location: $redirect"); exit;
}
