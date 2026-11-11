<?php
// atualizar_posicao.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (file_exists('admin/config.php')) {
    require_once 'admin/config.php';
} elseif (file_exists('../admin/config.php')) {
    require_once '../admin/config.php';
}

header('Content-Type: application/json');

if (!isset($_SESSION['entregador_id'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$lat = $data['latitude'] ?? null;
$lng = $data['longitude'] ?? null;
$entregador_id = $_SESSION['entregador_id'];

if ($lat !== null && $lng !== null) {
    try {
        $stmt = $pdo->prepare("UPDATE entregadores SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->execute([$lat, $lng, $entregador_id]);
        echo json_encode(['sucesso' => true]);
    } catch (Exception $e) {
        echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
    }
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Coordenadas inválidas']);
}