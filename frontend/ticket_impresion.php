<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/conexion.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = strtoupper(trim($_GET['token'] ?? ''));
$orden = null;
$detalles = [];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM mantenimientos WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $orden = $stmt->fetch();
} elseif ($token !== '') {
    $stmt = $pdo->prepare('SELECT * FROM mantenimientos WHERE token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $orden = $stmt->fetch();
}

if ($orden) {
    $det = $pdo->prepare('
        SELECT concepto, costo
        FROM detalles_presupuesto
        WHERE mantenimiento_id = :mantenimiento_id
        ORDER BY id ASC
    ');
    $det->execute(['mantenimiento_id' => $orden['id']]);
    $detalles = $det->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket | Pc Salud</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; background: #fff; margin: 0; }
        .ticket { width: min(92%, 820px); margin: 24px auto; border: 1px solid #111; padding: 20px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 12px; }
        .title { font-size: 1.5rem; font-weight: 800; }
        .sub { color: #333; font-size: 0.9rem; }
        .block { margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #111; padding: 8px; text-align: left; font-size: 0.95rem; }
        th { background: #eee; }
        .total-row td { font-weight: 700; }
        .empty { padding: 12px; border: 1px dashed #444; }
        @media print {
            .ticket { margin: 0; width: 100%; border: none; }
        }
    </style>
</head>
<body>
    <main class="ticket">
        <?php if (!$orden): ?>
            <div class="empty">No se encontro el mantenimiento para imprimir el ticket.</div>
        <?php else: ?>
            <div class="header">
                <div>
                    <div class="title">PC SALUD</div>
                    <div class="sub">Recibo / Orden de Servicio</div>
                </div>
                <div class="sub">
                    <div><strong>Token:</strong> <?= htmlspecialchars($orden['token']) ?></div>
                    <div><strong>Fecha:</strong> <?= htmlspecialchars($orden['fecha_ingreso']) ?></div>
                </div>
            </div>

            <section class="block">
                <div><strong>Cliente:</strong> <?= htmlspecialchars($orden['nombre_cliente']) ?></div>
                <div><strong>WhatsApp:</strong> <?= htmlspecialchars($orden['whatsapp_cliente']) ?></div>
                <div><strong>Equipo:</strong> <?= htmlspecialchars($orden['marca']) ?> (<?= htmlspecialchars($orden['tipo_equipo']) ?>)</div>
                <div><strong>Estado:</strong> <?= htmlspecialchars($orden['estado']) ?></div>
            </section>

            <section class="block">
                <strong>Desglose de costos</strong>
                <table>
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Costo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($detalles)): ?>
                            <tr>
                                <td>Sin conceptos cargados</td>
                                <td>$ 0.00</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detalles as $detalle): ?>
                                <tr>
                                    <td><?= htmlspecialchars($detalle['concepto']) ?></td>
                                    <td>$ <?= htmlspecialchars((string)$detalle['costo']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td>Total</td>
                            <td>$ <?= htmlspecialchars((string)($orden['costo_estimado'] ?? '0.00')) ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
    </main>

    <script>
        window.onload = function() { window.print(); };
    </script>
</body>
</html>
