<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$exito = false;
$solicitud = null;

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'El enlace de restablecimiento no es válido.';
} else {
    $pdo = getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS restablecimientos_duenos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dueno_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expira_en DATETIME NOT NULL,
        usado_en DATETIME NULL,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_restablecimiento_dueno (dueno_id),
        FOREIGN KEY (dueno_id) REFERENCES duenos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("SELECT r.id, r.dueno_id
        FROM restablecimientos_duenos r
        JOIN duenos d ON d.id = r.dueno_id
        WHERE r.token_hash = :token_hash
          AND r.usado_en IS NULL
          AND r.expira_en > NOW()
          AND d.password IS NOT NULL
        LIMIT 1");
    $stmt->execute([':token_hash' => hash('sha256', $token)]);
    $solicitud = $stmt->fetch();

    if (!$solicitud) {
        $error = 'Este enlace ya fue usado o venció. Solicita uno nuevo desde la aplicación.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirmacion = $_POST['confirmacion'] ?? '';

        if (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif (!hash_equals($password, $confirmacion)) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE duenos SET password = :password WHERE id = :id')
                    ->execute([':password' => password_hash($password, PASSWORD_DEFAULT), ':id' => $solicitud['dueno_id']]);
                $pdo->prepare('UPDATE restablecimientos_duenos SET usado_en = NOW() WHERE id = :id')
                    ->execute([':id' => $solicitud['id']]);
                $pdo->commit();
                $exito = true;
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('Cambio de contraseña de dueño: ' . $e->getMessage());
                $error = 'No fue posible cambiar la contraseña. Intenta nuevamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña — VetClinic Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
    <main class="card border-0 shadow-sm" style="max-width:460px;width:100%;border-radius:18px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock-fill text-primary" style="font-size:2.5rem;"></i>
                <h1 class="h4 mt-3 mb-1">Crear nueva contraseña</h1>
                <p class="text-muted small mb-0">Acceso de dueño de mascota</p>
            </div>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success">Tu contraseña fue actualizada correctamente. Ya puedes volver a la aplicación.</div>
                <a href="login.php" class="btn btn-primary w-100">Iniciar sesión</a>
            <?php elseif (!$error || $solicitud): ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="password" class="form-control mb-3" minlength="8" required>
                    <label class="form-label">Confirmar contraseña</label>
                    <input type="password" name="confirmacion" class="form-control mb-4" minlength="8" required>
                    <button type="submit" class="btn btn-primary w-100">Guardar nueva contraseña</button>
                </form>
            <?php else: ?>
                <p class="text-muted text-center mb-0">Vuelve a la aplicación y solicita un nuevo enlace.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
