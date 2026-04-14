<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../backend/conexion.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login_admin.php');
    exit;
}

$stmt = $pdo->query('
    SELECT nombre_cliente, marca, estrellas, comentario_cliente, fecha_ingreso
    FROM mantenimientos
    WHERE estrellas IS NOT NULL
    ORDER BY id DESC
');
$opiniones = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opiniones | Pc Salud</title>
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
            <h1 class="page-title">Opiniones de clientes</h1>
            <p class="page-subtitle">Feedback enviado por clientes al finalizar servicios.</p>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Equipo</th>
                            <th>Estrellas</th>
                            <th>Comentario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($opiniones)): ?>
                            <tr><td colspan="5">Aun no hay opiniones registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($opiniones as $opinion): ?>
                                <tr>
                                    <td><?= htmlspecialchars($opinion['fecha_ingreso']) ?></td>
                                    <td><?= htmlspecialchars($opinion['nombre_cliente']) ?></td>
                                    <td><?= htmlspecialchars($opinion['marca']) ?></td>
                                    <td><?= str_repeat('★', (int)$opinion['estrellas']) ?> (<?= (int)$opinion['estrellas'] ?>/5)</td>
                                    <td><?= htmlspecialchars($opinion['comentario_cliente'] ?: 'Sin comentario') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script src="js/ui.js"></script>
    <script>
        window.PcSaludUI.initTheme();
    </script>
</body>
</html>
