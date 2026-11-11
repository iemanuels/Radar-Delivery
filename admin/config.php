<?php
// admin/config.php

// Definir o fuso horário padrão para Manaus, Amazonas (UTC-4)
date_default_timezone_set('America/Manaus');

// Iniciar sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações do Banco de Dados MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'xedd5cftfcii3alb_saas');
define('DB_PASS', 'Teclado2026@');
define('DB_NAME', 'xedd5cftfcii3alb_saas');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Configurações Globais do SaaS (Super Admin)
define('SAAS_NOME', 'MercadoSaaS');
define('SAAS_VERSAO', '2.5.0-PRO');
define('SAAS_URL', 'http://localhost/seu-projeto');
define('SAAS_EMAIL_SUPORTE', 'suporte@mercadosaas.com');

// Configurações de Planos e Assinaturas padrão
define('PLANO_TRIAL_DIAS', 7);
define('COMISSAO_PADRAO_GATEWAY', 2.99); // % por transação processada nas lojas