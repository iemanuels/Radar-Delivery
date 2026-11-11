<?php
// logout.php (Localizado na pasta admin/)

// Inicia a sessão para poder acessá-la e destruí-la
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa todas as variáveis de sessão
$_SESSION = array();

// Destrói a sessão no servidor
session_destroy();

// Redireciona para a tela de login (ajuste o caminho ../login.php se estiver dentro da pasta admin)
header('Location: ../login.php');
exit;