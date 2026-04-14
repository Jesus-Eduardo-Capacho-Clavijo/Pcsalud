<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../backend/conexion.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login_admin.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfValidoCola(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
}

function reenviarCorreo(PDO $pdo, int $emailId): array
{
    $stmt = $pdo->prepare('SELECT * FROM emails_salida WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $emailId]);
    $email = $stmt->fetch();

    if (!$email) {
        return ['ok' => false, 'message' => 'Registro de correo no encontrado.'];
    }

    if (!filter_var((string)$email['destinatario'], FILTER_VALIDATE_EMAIL)) {
        $upd = $pdo->prepare('UPDATE emails_salida SET estado_envio = :estado_envio, mensaje_error = :mensaje_error WHERE id = :id');
        $upd->execute([
            'estado_envio' => 'fallido',
            'mensaje_error' => 'Correo invalido al reintentar',
            'id' => $emailId,
        ]);
        return ['ok' => false, 'message' => 'Correo invalido.'];
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8\r\n";
    $headers .= "From: Pc Salud <no-reply@pcsalud.local>\r\n";
    $enviado = @mail((string)$email['destinatario'], (string)$email['asunto'], (string)$email['cuerpo'], $headers);

    $upd = $pdo->prepare('
        UPDATE emails_salida
        SET estado_envio = :estado_envio, mensaje_error = :mensaje_error, enviado_en = :enviado_en
        WHERE id = :id
    ');
    $upd->execute([
        'estado_envio' => $enviado ? 'enviado' : 'fallido',
        'mensaje_error' => $enviado ? null : 'mail() retorno false en reintento',
        'enviado_en' => $enviado ? date('Y-m-d H:i:s') : null,
        'id' => $emailId,
    ]);

    return $enviado
        ? ['ok' => true, 'message' => 'Correo reenviado correctamente.']
        : ['ok' => false, 'message' => 'No se pudo reenviar el correo (mail() retorno false).'];
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidoCola()) {
        $error = 'Sesion invalida. Recarga la pagina e intenta nuevamente.';
    } else {
        $emailId = (int)($_POST['email_id'] ?? 0);
        if ($emailId > 0) {
            $res = reenviarCorreo($pdo, $emailId);
            if ($res['ok']) {
                $mensaje = $res['message'];
            } else {
                $error = $res['message'];
            }
        }
    }
}

$estadoFiltro = trim($_GET['estado'] ?? '');
$estadosValidos = ['pendiente', 'enviado', 'fallido'];

$sql = 'SELECT id, mantenimiento_id, destinatario, asunto, estado_envio, mensaje_error, creado_en, enviado_en
        FROM emails_salida';
$params = [];
if ($estadoFiltro !== '' && in_array($estadoFiltro, $estadosValidos, true)) {
    $sql .= ' WHERE estado_envio = :estado_envio';
    $params['estado_envio'] = $estadoFiltro;
}
$sql .= ' ORDER BY id DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$emails = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cola de Correos | Pc Salud</title>
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
            <h1 class="page-title">Cola de correos</h1>
            <p class="page-subtitle">Monitorea envios y reintenta correos fallidos.</p>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="get" class="readonly-box">
                <div class="field-group">
                    <label for="estado">Filtrar por estado</label>
                    <select id="estado" name="estado">
                        <option value="">Todos</option>
                        <?php foreach ($estadosValidos as $estado): ?>
                            <option value="<?= $estado ?>" <?= $estadoFiltro === $estado ? 'selected' : '' ?>><?= ucfirst($estado) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Aplicar filtro</button>
            </form>

            <div class="table-wrap" style="margin-top: 14px;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mantenimiento</th>
                            <th>Destinatario</th>
                            <th>Asunto</th>
                            <th>Estado</th>
                            <th>Error</th>
                            <th>Creado</th>
                            <th>Enviado</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($emails)): ?>
                            <tr><td colspan="9">No hay correos para este filtro.</td></tr>
                        <?php else: ?>
                            <?php foreach ($emails as $email): ?>
                                <tr>
                                    <td><?= (int)$email['id'] ?></td>
                                    <td><?= htmlspecialchars((string)($email['mantenimiento_id'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars($email['destinatario']) ?></td>
                                    <td><?= htmlspecialchars($email['asunto']) ?></td>
                                    <td><span class="mail-status mail-<?= htmlspecialchars($email['estado_envio']) ?>"><?= htmlspecialchars($email['estado_envio']) ?></span></td>
                                    <td><?= htmlspecialchars((string)($email['mensaje_error'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)$email['creado_en']) ?></td>
                                    <td><?= htmlspecialchars((string)($email['enviado_en'] ?? '-')) ?></td>
                                    <td>
                                        <?php if ($email['estado_envio'] === 'fallido'): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="email_id" value="<?= (int)$email['id'] ?>">
                                                <button class="btn btn-primary btn-sm" type="submit">Reintentar</button>
                                            </form>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
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
