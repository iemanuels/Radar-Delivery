<?php
// mercado/verificar_pagamento.php
header('Content-Type: application/json');
require_once '../admin/config.php';

$pedidoId = intval($_GET['pedido_id'] ?? 0);

if ($pedidoId <= 0) {
    echo json_encode(['status_pagamento' => 'pendente']);
    exit;
}

$stmt = $pdo->prepare("SELECT status_pagamento FROM pedidos WHERE id = ? LIMIT 1");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pedido) {
    echo json_encode([
        'status_pagamento' => $pedido['status_pagamento'] // 'pendente', 'aprovado', etc.
    ]);
} else {
    echo json_encode(['status_pagamento' => 'pendente']);
}
exit;