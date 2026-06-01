<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Por favor, ingrese usuario y contraseña.";
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username AND activo = 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['nombre_completo'];
            $_SESSION['user_rol']  = $user['rol'];
            header("Location: index.php");
            exit;
        } else {
            // Intentar autenticación como dueño (por email)
            $stmt2 = $pdo->prepare("SELECT * FROM duenos WHERE email = :email AND password IS NOT NULL");
            $stmt2->execute([':email' => $username]);
            $dueno = $stmt2->fetch();
            if ($dueno && password_verify($password, $dueno['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $dueno['id'];
                $_SESSION['user_name'] = $dueno['nombre'];
                $_SESSION['user_rol']  = 'dueno';
                $_SESSION['dueno_id']  = $dueno['id'];
                header("Location: index.php");
                exit;
            }
            $error = "Usuario o contraseña incorrectos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — VetClinic Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-wrapper { width: 100%; max-width: 420px; }
        .login-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.45);
            overflow: hidden;
            background: #ffffff;
        }
        .login-header {
            background: linear-gradient(160deg, #1e293b 0%, #0f172a 100%);
            padding: 2.5rem 2rem 2.75rem;
            text-align: center;
            position: relative;
        }
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -1px; left: 0; right: 0;
            height: 22px;
            background: #ffffff;
            border-radius: 24px 24px 0 0;
        }
        .login-logo {
            width: 72px; height: 72px;
            background: rgba(59,130,246,0.15);
            border: 2px solid rgba(59,130,246,0.35);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.1rem;
            color: #60a5fa;
            margin-bottom: 1rem;
        }
        .login-header h1 {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
            letter-spacing: -0.5px;
        }
        .login-header p { color: #94a3b8; font-size: 0.83rem; margin: 0; }
        .login-body { padding: 2rem 2rem 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .input-group-text {
            background: #f1f5f9;
            border: 1.5px solid #e2e8f0;
            border-right: none;
            border-radius: 9px 0 0 9px;
            color: #94a3b8;
            font-size: 1rem;
            padding: 10px 14px;
        }
        .input-group .form-control {
            border: 1.5px solid #e2e8f0;
            border-left: none;
            border-radius: 0 9px 9px 0;
            font-size: 0.9rem;
            padding: 10px 13px;
            background: #fafafa;
            color: #1e293b;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .input-group .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
            background: #fff;
            outline: none;
        }
        .input-group:focus-within .input-group-text { border-color: #3b82f6; }
        .btn-login {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.2px;
            color: #fff;
            transition: all 0.2s;
            box-shadow: 0 4px 15px rgba(59,130,246,0.3);
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(59,130,246,0.4);
        }
        .login-alert {
            border: none;
            border-radius: 9px;
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #b91c1c;
            font-size: 0.85rem;
            padding: 10px 14px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .login-footer {
            text-align: center;
            padding: 0.9rem 2rem 1.4rem;
            color: #94a3b8;
            font-size: 0.76rem;
            border-top: 1px solid #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">

            <div class="login-header">
                <div class="login-logo">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>
                <h1>VetClinic Pro</h1>
                <p>Sistema de Gestión Veterinaria</p>
            </div>

            <div class="login-body">
                <?php if ($error): ?>
                <div class="login-alert" role="alert">
                    <i class="bi bi-exclamation-circle-fill"></i><?= e($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="login.php" autocomplete="off">
                    <div class="form-group">
                        <label class="form-label">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Ingrese su usuario" required autofocus>
                        </div>
                    </div>
                    <div class="form-group mb-4">
                        <label class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Ingrese su contraseña" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-login w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                    </button>
                </form>
            </div>

            <div class="login-footer">
                <i class="bi bi-shield-lock me-1"></i>Acceso restringido — Solo personal autorizado
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
