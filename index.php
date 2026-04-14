<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pc Salud</title>
    <link rel="stylesheet" href="frontend/css/styles.css">
</head>
<body class="centered">
    <div class="theme-switch">
        <span id="themeLabel">Modo claro</span>
        <input type="checkbox" id="themeToggle" aria-label="Cambiar tema">
    </div>
    <main class="home-container">
        <h1>Pc Salud</h1>
        <p>Sistema de mantenimiento de computadoras</p>

        <div class="buttons">
            <a class="btn-main btn-admin" href="frontend/login_admin.php">INGRESAR COMO ADMIN</a>
            <a class="btn-main btn-cliente" href="frontend/consulta_cliente.php">INGRESAR COMO CLIENTE</a>
        </div>
    </main>
    <script src="frontend/js/ui.js"></script>
    <script>
        window.PcSaludUI.initTheme();
    </script>
</body>
</html>
