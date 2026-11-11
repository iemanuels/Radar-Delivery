<?php
// notificacoes_api.php
date_default_timezone_set('America/Manaus');
require_once 'admin/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$loja_id = $_SESSION['current_loja_id'] ?? $_SESSION['loja_id'] ?? 1;

header('Content-Type: application/json; charset=utf-8');

try {
    // Ação para marcar como vistas/lidas (opcional)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_vistas'])) {
        // Exemplo: atualizar status de notificações se houver tabela própria, ou ignorar se basear em pedidos
        echo json_encode(['sucesso' => true]);
        exit;
    }

    // Busca os pedidos recentes da loja para montar as notificações em tempo real
    $stmt = $pdo->prepare("
        SELECT id, codigo_pedido, status_pedido, criado_em 
        FROM pedidos 
        WHERE loja_id = ? 
        ORDER BY criado_em DESC 
        LIMIT 10
    ");
    $stmt->execute([$loja_id]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notificacoes = [];
    $totalNaoLidas = 0;

    foreach ($pedidos as $p) {
        $tipo = 'novo_pedido';
        $mensagem = "Novo pedido #{$p['codigo_pedido']} recebido.";

        if ($p['status_pedido'] === 'em_rota') {
            $tipo = 'em_rota';
            $mensagem = "Pedido #{$p['codigo_pedido']} saiu para entrega.";
        } elseif ($p['status_pedido'] === 'entregue') {
            $tipo = 'info';
            $mensagem = "Pedido #{$p['codigo_pedido']} foi entregue com sucesso.";
        }

        // Se o pedido estiver pendente, conta como não lido/ativo na notificação
        if ($p['status_pedido'] === 'pendente') {
            $totalNaoLidas++;
        }

        $notificacoes[] = [
            'tipo' => $tipo,
            'mensagem' => $mensagem,
            'criado_em' => date('d/m/Y H:i', strtotime($p['criado_em']))
        ];
    }

    echo json_encode([
        'sucesso' => true,
        'total_nao_lidas' => $totalNaoLidas,
        'notificacoes' => $notificacoes
    ]);

} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage()
    ]);
}