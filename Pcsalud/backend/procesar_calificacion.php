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
$estrellas = (int)($data['estrellas'] ?? 0);
$comentario = trim((string)($data['comentario_cliente'] ?? ''));

if ($token === '' || $estrellas < 1 || $estrellas > 5) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Datos invalidos']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, estado FROM mantenimientos WHERE token = :token LIMIT 1');
$stmt->execute(['token' => $token]);
$mantenimiento = $stmt->fetch();

if (!$mantenimiento) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Token no encontrado']);
    exit;
}

if (($mantenimiento['estado'] ?? '') !== 'Entregado') {
    http_response_code(409);
    echo json_encode(['ok' => false, 'message' => 'Solo se puede calificar cuando el estado es Entregado']);
    exit;
}

$update = $pdo->prepare('
    UPDATE mantenimientos
    SET estrellas = :estrellas, comentario_cliente = :comentario_cliente
    WHERE id = :id
');
$update->execute([
    'estrellas' => $estrellas,
    'comentario_cliente' => $comentario,
    'id' => (int)$mantenimiento['id'],
]);

echo json_encode(['ok' => true, 'message' => 'Calificacion guardada']);
