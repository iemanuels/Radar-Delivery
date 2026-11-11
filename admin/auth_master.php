<?php
// admin/auth_master.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado e se o tipo é 'master'
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'master') {
    // Redireciona para o login na raiz do projeto (ajuste '../login.php' se necessário)
    header("Location: ../login.php?erro=acesso_negado");
    exit;
}
?>