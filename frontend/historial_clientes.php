<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../backend/conexion.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login_admin.php');
    exit;
}

$whatsapp = trim($_GET['whatsapp'] ?? '');
$resultados = [];

if ($whatsapp !== '') {
    $stmt = $pdo->prepare('
        SELECT fecha_ingreso, marca, diagnostico_actual, estado
        FROM mantenimientos
        WHERE whatsapp_cliente = :whatsapp
        ORDER BY fecha_ingreso DESC, id DESC
    ');
    $stmt->execute(['whatsapp' => $whatsapp]);
    $resultados = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Clientes | Pc Salud</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="theme-switch">
        <span id="themeLabel">Modo claro</span>
        <input type="checkbox" id="themeToggle" aria-label="Cambiar tema">
    </div>
    <div class="admin-layout">
        <aside class="sidebar">
            <h2>Pc Salud</h2>
            <nav>
                <a href="panel_admin.php?seccion=crear">Crear nuevo mantenimiento</a>
                <a href="panel_admin.php?seccion=pendientes">Mantenimientos pendientes</a>
                <a href="historial_clientes.php">Historial por cliente</a>
                <a href="opiniones.php">Opiniones</a>
                <a href="cola_correos.php">Cola de correos</a>
                <a href="panel_admin.php?seccion=logout">Cerrar sesion</a>
            </nav>
        </aside>

        <main class="content">
            <h1 class="page-title">Historial por cliente</h1>
            <p class="page-subtitle">Consulta todas las ordenes por numero de WhatsApp.</p>

            <form method="get" class="readonly-box">
                <div class="field-group">
                    <label for="whatsapp">WhatsApp cliente</label>
                    <input id="whatsapp" name="whatsapp" value="<?= htmlspecialchars($whatsapp) ?>" placeholder="Ej: 31455555 o 5731455555">
                </div>
                <button class="btn btn-primary" type="submit">Buscar historial</button>
            </form>

            <?php if ($whatsapp !== ''): ?>
                <div class="table-wrap" style="margin-top:16px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Marca</th>
                                <th>Diagnostico final</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($resultados)): ?>
                                <tr>
                                    <td colspan="4">No hay mantenimientos para ese numero.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($resultados as $fila): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($fila['fecha_ingreso']) ?></td>
                                        <td><?= htmlspecialchars($fila['marca']) ?></td>
                                        <td><?= htmlspecialchars($fila['diagnostico_actual'] ?: 'Sin diagnostico') ?></td>
                                        <td><?= htmlspecialchars($fila['estado']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="js/ui.js"></script>
    <script>
        window.PcSaludUI.initTheme();
    </script>
</body>
</html>
