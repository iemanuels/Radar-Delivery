<?php
// logout.php
session_start();
require_once 'admin/config.php';

try {
    if (isset($_SESSION['entregador_id'])) {
        $entregador_id = $_SESSION['entregador_id'];
        // Atualiza o entregador para offline ao sair
        $stmt = $pdo->prepare("UPDATE entregadores SET status_entregador = 'offline' WHERE id = ?");
        $stmt->execute([$entregador_id]);
    }
} catch (Exception $e) {
    // Caso ocorra algum erro na conexão, prossegue com a destruição da sessão
}

// Destroi todas as variáveis de sessão e a própria sessão
$_SESSION = [];
session_unset();
session_destroy();

// Redireciona de volta para o index
header("Location: entregador/");
exit;
?>