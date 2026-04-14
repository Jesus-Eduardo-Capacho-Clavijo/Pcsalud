<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo no permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);

$token = strtoupper(trim((string)($data['token'] ?? '')));
$decision = trim((string)($data['decision'] ?? ''));

if ($token === '' || !in_array($decision, ['Aprobado', 'Rechazado'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Datos invalidos']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, estado_presupuesto FROM mantenimientos WHERE token = :token LIMIT 1');
$stmt->execute(['token' => $token]);
$mantenimiento = $stmt->fetch();

if (!$mantenimiento) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Token no encontrado']);
    exit;
}

if ($mantenimiento['estado_presupuesto'] !== 'Pendiente') {
    echo json_encode([
        'ok' => true,
        'message' => 'El presupuesto ya fue procesado.',
        'estado_presupuesto' => $mantenimiento['estado_presupuesto'],
    ]);
    exit;
}

$update = $pdo->prepare('UPDATE mantenimientos SET estado_presupuesto = :estado_presupuesto WHERE id = :id');
$update->execute([
    'estado_presupuesto' => $decision,
    'id' => (int)$mantenimiento['id'],
]);

echo json_encode([
    'ok' => true,
    'message' => 'Decision registrada correctamente.',
    'estado_presupuesto' => $decision,
]);
