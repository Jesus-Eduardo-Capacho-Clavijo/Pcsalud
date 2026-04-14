<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../backend/conexion.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: panel_admin.php');
    exit;
}

$error = '';
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($usuario === '' || $password === '') {
        $error = 'Completa usuario y contrasena.';
    } else {
        $stmt = $pdo->prepare('SELECT id, usuario, password FROM administradores WHERE usuario = :usuario LIMIT 1');
        $stmt->execute(['usuario' => $usuario]);
        $admin = $stmt->fetch();

        if ($admin) {
            $storedPassword = (string)$admin['password'];
            $isValid = false;

            if (password_verify($password, $storedPassword)) {
                $isValid = true;
            } elseif ($password === $storedPassword) {
                $isValid = true;
                $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $pdo->prepare('UPDATE administradores SET password = :password WHERE id = :id');
                $update->execute([
                    'password' => $nuevoHash,
                    'id' => $admin['id'],
                ]);
            }

            if ($isValid) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_usuario'] = $admin['usuario'];
                header('Location: panel_admin.php');
                exit;
            }
        }

        $error = 'Credenciales invalidas.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin | Pc Salud</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="theme-switch">
        <span id="themeLabel">Modo claro</span>
        <input type="checkbox" id="themeToggle" aria-label="Cambiar tema">
    </div>
    <main class="page-container">
        <h1 class="page-title">Ingreso de administrador</h1>
        <p class="page-subtitle">Accede para gestionar mantenimientos.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="field-group">
                <label for="usuario">Usuario</label>
                <input id="usuario" name="usuario" value="<?= htmlspecialchars($usuario) ?>">
            </div>
            <div class="field-group">
                <label for="password">Contrasena</label>
                <input id="password" type="password" name="password">
            </div>
            <button class="btn btn-primary" type="submit">Ingresar</button>
        </form>

        <a class="back-link" href="../index.html">Volver al inicio</a>
    </main>
    <script src="js/ui.js"></script>
    <script>
        window.PcSaludUI.initTheme();
    </script>
</body>
</html>
