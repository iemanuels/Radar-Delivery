<?php
// includes/menu-api.php

// Define obrigatoriamente o fuso horário para Manaus / Amazonas
date_default_timezone_set('America/Manaus');

// Inclui o arquivo de configuração e conexão com o banco de dados
require_once __DIR__ . '/../admin/config.php';

// Garante que o retorno seja tratado como JSON
header('Content-Type: application/json; charset=utf-8');

$loja_id = 1; // Ajuste conforme a sessão do lojista se necessário

try {
    // Consulta a quantidade de pedidos pendentes no banco de dados
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE loja_id = ? AND status_pedido = 'pendente'");
    $stmt->execute([$loja_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalPendentes = intval($resultado['total'] ?? 0);

    echo json_encode([
        'status' => 'sucesso',
        'total_pendentes' => $totalPendentes
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'total_pendentes' => 0,
        'mensagem' => $e->getMessage()
    ]);
}