<?php
// admin/webhook.php
date_default_timezone_set('America/Manaus');
require_once 'config.php';

// Recebe o corpo da requisição enviada pelo Mercado Pago
$body = file_get_contents('php://input');
$data = json_decode($body, true);

// Grava um log básico opcional para depuração (pode remover se preferir)
if ($data) {
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - " . json_encode($data) . "\n", FILE_APPEND);
}

// Verifica se a notificação é referente a um pagamento
if (isset($data['type']) && $data['type'] === 'payment') {
    $paymentId = $data['data']['id'] ?? null;
} elseif (isset($data['action']) && $data['action'] === 'payment.created' || isset($data['action']) && $data['action'] === 'payment.updated') {
    $paymentId = $data['data']['id'] ?? null;
}

if (!$paymentId) {
    // Se vier em formato de query string (ex: ?topic=payment&id=12345)
    $paymentId = $_GET['id'] ?? $_GET['data_id'] ?? null;
}

if ($paymentId) {
    // Como o webhook é central, precisamos consultar todas as lojas ou tentar descobrir qual token usar.
    // O ideal é iterar pelas chaves da tabela 'mp' ou buscar o pagamento testando as credenciais ativas.
    $stmtMp = $pdo->query("SELECT loja_id, access_token FROM mp WHERE access_token IS NOT NULL AND access_token != ''");
    $credenciaisLojas = $stmtMp->fetchAll(PDO::FETCH_ASSOC);

    $pagamentoAprovado = false;
    $descricaoPagamento = '';
    $lojaIdEncontrada = null;

    foreach ($credenciaisLojas as $cred) {
        $tokenLoja = trim($cred['access_token']);
        $lojaIdEncontrada = $cred['loja_id'];

        $ch = curl_init("https://api.mercadopago.com/v1/payments/{$paymentId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $tokenLoja
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $paymentInfo = json_decode($response, true);
            if ($paymentInfo) {
                $status = $paymentInfo['status'] ?? '';
                $descricaoPagamento = $paymentInfo['description'] ?? '';
                
                // Se o pagamento foi aprovado pelo Mercado Pago
                if ($status === 'approved') {
                    $pagamentoAprovado = true;
                    break;
                }
            }
        }
    }

    if ($pagamentoAprovado) {
        // Tenta extrair o número do pedido da descrição (Ex: "Pedido #4821 - Loja #4" ou "Pedido #4821")
        if (preg_match('/#(\d+)/', $descricaoPagamento, $matches)) {
            $codigoPedido = $matches[1];

            // Atualiza o status do pedido no banco de dados para aprovado
            $stmtUpd = $pdo->prepare("
                UPDATE pedidos 
                SET status_pagamento = 'aprovado' 
                WHERE codigo_pedido = ? AND loja_id = ?
            ");
            $stmtUpd->execute([$codigoPedido, $lojaIdEncontrada]);

            // Atualiza também na tabela transações se necessário
            $stmtTrans = $pdo->prepare("
                UPDATE transacoes 
                SET status = 'concluido' 
                WHERE valor > 0 AND loja_id = ? AND descricao LIKE ?
            ");
            $stmtTrans->execute([$lojaIdEncontrada, "%#{$codigoPedido}%"]);
        }
    }
}

// Retorna o status 200 OK para confirmar ao Mercado Pago que a notificação foi recebida com sucesso
http_response_code(200);
echo json_encode(['status' => 'sucesso']);
exit;
?>