<?php
// auth.php
// Inicia a sessão caso ainda não tenha sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado e se possui uma loja associada na sessão
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['loja_id'])) {
    // Redireciona para o login se não estiver autenticado
    header('Location: login.php');
    exit;
}

// Variáveis globais úteis disponíveis para qualquer arquivo que incluir o auth.php
$admin_id = (int) $_SESSION['admin_id'];
$admin_nome = $_SESSION['admin_nome'] ?? 'Administrador';
$admin_email = $_SESSION['admin_email'] ?? '';
$loja_id = (int) $_SESSION['loja_id']; // <-- Chave de isolamento multi-loja