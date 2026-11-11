<?php
// loca-api.php - API Dinâmica para Atualização de Pedidos em Rota
header('Content-Type: application/json; charset=utf-8');

// Define fuso horário para Manaus
date_default_timezone_set('America/Manaus');

$loja_id = isset($_GET['loja_id']) ? intval($_GET['loja_id']) : 1;
$pedidosEmRota = [];

try {
    if (file_exists('admin/config.php')) {
        require_once 'admin/config.php';
    } elseif (file_exists('../admin/config.php')) {
        require_once '../admin/config.php';
    } else {
        throw new Exception("Configuração não encontrada.");
    }

    if (!isset($pdo)) {
        throw new Exception("Erro de conexão PDO.");
    }

    $pdo->exec("SET time_zone = '-04:00'");

    $stmtPedidosRota = $pdo->prepare("
        SELECT p.*, c.nome as nome_cliente, c.telefone as telefone_cliente, e.nome as nome_entregador 
        FROM pedidos p
        INNER JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN entregadores e ON p.entregador_id = e.id
        WHERE p.loja_id = ? AND p.status_pedido = 'em_rota'
        ORDER BY p.criado_em ASC
    ");
    $stmtPedidosRota->execute([$loja_id]);
    $pedidosEmRota = $stmtPedidosRota->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'sucesso' => true,
        'total' => count($pedidosEmRota),
        'pedidos' => $pedidosEmRota
    ]);

} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage(),
        'pedidos' => []
    ]);
}