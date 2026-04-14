<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/conexion.php';

$token = '';
$error = '';
$resultado = null;
$detallesPresupuesto = [];
$estadosFlujo = [
    'Recibido',
    'En Revisión',
    'Esperando Repuestos',
    'Reparando',
    'Listo para entregar',
    'Entregado',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = strtoupper(trim($_POST['token'] ?? ''));

    if ($token === '') {
        $error = 'Debes ingresar un token.';
    } else {
        $stmt = $pdo->prepare('
            SELECT
                id,
                token,
                marca,
                diagnostico_actual,
                fecha_entrega,
                estado,
                costo_estimado,
                estado_presupuesto,
                ruta_imagen,
                estrellas,
                comentario_cliente
            FROM mantenimientos
            WHERE token = :token
            LIMIT 1
        ');
        $stmt->execute(['token' => $token]);
        $resultado = $stmt->fetch();

        if (!$resultado) {
            $error = 'Token no encontrado. Verifica e intenta nuevamente.';
        } else {
            $detallesStmt = $pdo->prepare('
                SELECT concepto, costo
                FROM detalles_presupuesto
                WHERE mantenimiento_id = (
                    SELECT id FROM mantenimientos WHERE token = :token LIMIT 1
                )
                ORDER BY id ASC
            ');
            $detallesStmt->execute(['token' => $token]);
            $detallesPresupuesto = $detallesStmt->fetchAll();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta Cliente | Pc Salud</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="theme-switch">
        <span id="themeLabel">Modo claro</span>
        <input type="checkbox" id="themeToggle" aria-label="Cambiar tema">
    </div>
    <main class="page-container">
        <h1 class="page-title">Consulta de mantenimiento</h1>
        <p class="page-subtitle">Ingresa tu token para ver el estado actual.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="field-group">
                <label for="token">Token</label>
                <input id="token" name="token" maxlength="10" value="<?= htmlspecialchars($token) ?>" placeholder="Ej: A1B2C3D4E5">
            </div>
            <button class="btn btn-primary" type="submit">Buscar</button>
        </form>

        <?php if ($resultado): ?>
            <div class="readonly-box">
                <p><strong>Marca:</strong> <?= htmlspecialchars($resultado['marca']) ?></p>
                <p><strong>Diagnostico actual:</strong></p>
                <p><?= nl2br(htmlspecialchars($resultado['diagnostico_actual'] ?: 'Aun no hay diagnostico registrado.')) ?></p>
                <br>
                <p><strong>Fecha estimada de entrega:</strong> <?= htmlspecialchars($resultado['fecha_entrega']) ?></p>
                <p><strong>Estado actual:</strong> <?= htmlspecialchars($resultado['estado']) ?></p>
            </div>

            <?php $estadoActualIndex = array_search($resultado['estado'], $estadosFlujo, true); ?>
            <section class="progress-wrapper">
                <h3>Progreso del mantenimiento</h3>
                <div class="progress-steps">
                    <?php foreach ($estadosFlujo as $index => $estadoPaso): ?>
                        <div class="step <?= $estadoActualIndex !== false && $index <= $estadoActualIndex ? 'active' : '' ?>">
                            <span class="dot"></span>
                            <small><?= htmlspecialchars($estadoPaso) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="budget-box" id="budgetBox">
                <h3>Estado del presupuesto</h3>
                <?php if ($resultado['costo_estimado'] !== null && (float)$resultado['costo_estimado'] > 0): ?>
                    <?php if (!empty($detallesPresupuesto)): ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th>Costo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detallesPresupuesto as $detalle): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($detalle['concepto']) ?></td>
                                            <td>$ <?= htmlspecialchars((string)$detalle['costo']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td><strong>Total</strong></td>
                                        <td><strong>$ <?= htmlspecialchars((string)$resultado['costo_estimado']) ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if ($resultado['estado_presupuesto'] === 'Pendiente'): ?>
                        <p id="budgetMessage">Total estimado: <strong>$ <?= htmlspecialchars((string)$resultado['costo_estimado']) ?></strong></p>
                        <div class="actions" id="budgetActions">
                            <button class="btn btn-success" type="button" id="btnAprobar">Aprobar Presupuesto</button>
                            <button class="btn btn-danger" type="button" id="btnRechazar">Rechazar</button>
                        </div>
                    <?php else: ?>
                        <p id="budgetMessage">Total estimado: <strong>$ <?= htmlspecialchars((string)$resultado['costo_estimado']) ?></strong></p>
                        <p class="budget-status"><strong>Estado:</strong> <?= htmlspecialchars($resultado['estado_presupuesto']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p id="budgetMessage">Aun no se ha cargado un costo estimado.</p>
                <?php endif; ?>
            </section>

            <?php if (!empty($resultado['ruta_imagen'])): ?>
                <section class="readonly-box" style="margin-top:12px;">
                    <p><strong>Evidencia fotografica:</strong></p>
                    <img src="<?= htmlspecialchars($resultado['ruta_imagen']) ?>" alt="Evidencia del tecnico" class="evidence-image">
                </section>
            <?php endif; ?>

            <?php if (($resultado['estado'] ?? '') === 'Entregado'): ?>
                <section class="rating-box" id="ratingBox">
                    <h3>Califica nuestro servicio</h3>
                    <?php if (!empty($resultado['estrellas'])): ?>
                        <p><strong>Calificacion enviada:</strong> <?= (int)$resultado['estrellas'] ?> / 5</p>
                        <p><strong>Comentario:</strong> <?= nl2br(htmlspecialchars((string)($resultado['comentario_cliente'] ?? 'Sin comentario'))) ?></p>
                    <?php else: ?>
                        <div class="stars-input">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?= $i ?>" name="estrellas" value="<?= $i ?>">
                                <label for="star<?= $i ?>">&#9733;</label>
                            <?php endfor; ?>
                        </div>
                        <div class="field-group">
                            <label for="comentario_cliente">Comentario</label>
                            <textarea id="comentario_cliente" placeholder="Cuentanos tu experiencia"></textarea>
                        </div>
                        <button class="btn btn-primary" type="button" id="btnEnviarCalificacion">Enviar calificacion</button>
                        <p id="ratingStatus" class="budget-status"></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <a class="back-link" href="../index.html">Volver al inicio</a>
    </main>
    <script src="js/ui.js"></script>
    <script>
        window.PcSaludUI.initTheme();
        (function () {
            const token = <?= json_encode((string)($resultado['token'] ?? '')) ?>;
            const btnAprobar = document.getElementById('btnAprobar');
            const btnRechazar = document.getElementById('btnRechazar');
            const budgetActions = document.getElementById('budgetActions');
            const budgetMessage = document.getElementById('budgetMessage');
            const budgetBox = document.getElementById('budgetBox');
            const btnEnviarCalificacion = document.getElementById('btnEnviarCalificacion');
            const ratingStatus = document.getElementById('ratingStatus');

            if (token && btnAprobar && btnRechazar && budgetActions && budgetMessage && budgetBox) {
                async function enviar(decision) {
                    try {
                        const res = await window.PcSaludUI.enviarDecisionPresupuesto({
                            token: token,
                            decision: decision
                        });

                        budgetActions.style.display = 'none';
                        const estado = res.estado_presupuesto || decision;
                        const estadoHtml = '<p class="budget-status"><strong>Estado:</strong> ' + estado + '</p>';
                        budgetBox.insertAdjacentHTML('beforeend', estadoHtml);
                    } catch (e) {
                        alert('No fue posible registrar tu decision. Intenta nuevamente.');
                    }
                }

                btnAprobar.addEventListener('click', function () {
                    enviar('Aprobado');
                });

                btnRechazar.addEventListener('click', function () {
                    enviar('Rechazado');
                });
            }

            if (token && btnEnviarCalificacion) {
                btnEnviarCalificacion.addEventListener('click', async function () {
                    const estrellasInput = document.querySelector('input[name="estrellas"]:checked');
                    const comentario = document.getElementById('comentario_cliente')?.value || '';
                    if (!estrellasInput) {
                        alert('Selecciona una cantidad de estrellas.');
                        return;
                    }

                    try {
                        const res = await fetch('../backend/procesar_calificacion.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                token: token,
                                estrellas: parseInt(estrellasInput.value, 10),
                                comentario_cliente: comentario
                            })
                        });
                        const data = await res.json();
                        if (!res.ok || !data.ok) {
                            throw new Error(data.message || 'Error al guardar calificacion');
                        }
                        btnEnviarCalificacion.disabled = true;
                        if (ratingStatus) {
                            ratingStatus.textContent = 'Gracias. Tu calificacion fue registrada.';
                        }
                    } catch (err) {
                        alert('No se pudo guardar la calificacion. Intenta nuevamente.');
                    }
                });
            }
        })();
    </script>
</body>
</html>
