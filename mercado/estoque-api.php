<?php
// mercado/estoque-api.php
header('Content-Type: application/json');

require_once '../admin/config.php';

$loja_id = 1;

try {
    // 1. Buscar status atualizado da Loja
    $stmtLoja = $pdo->prepare("SELECT status_loja, forcar_fechado, horario_abertura, horario_fechamento FROM lojas WHERE id = ?");
    $stmtLoja->execute([$loja_id]);
    $loja = $stmtLoja->fetch(PDO::FETCH_ASSOC);

    $lojaAberta = true;
    if ($loja) {
        if ($loja['forcar_fechado'] == 1 || $loja['status_loja'] != 'ativo') {
            $lojaAberta = false;
        } else {
            $horaAtual = date('H:i:s');
            if ($horaAtual < $loja['horario_abertura'] || $horaAtual > $loja['horario_fechamento']) {
                $lojaAberta = false;
            }
        }
    }

    // 2. Buscar status dos produtos
    $stmtProd = $pdo->prepare("SELECT id, status_produto FROM produtos WHERE loja_id = ? AND status_produto != 'inativo'");
    $stmtProd->execute([$loja_id]);
    $produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

    $resultadoProdutos = [];
    foreach ($produtos as $prod) {
        $esgotado = ($prod['status_produto'] === 'esgotado');
        $resultadoProdutos[$prod['id']] = [
            'status_produto' => $prod['status_produto'],
            'esgotado' => $esgotado
        ];
    }

    echo json_encode([
        'sucesso' => true,
        'lojaAberta' => $lojaAberta,
        'produtos' => $resultadoProdutos
    ]);
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar dados'
    ]);
}