<?php
// icon.php - Exibe o favicon ou logo da loja logada/atual dinamicamente

try {
    // 1. Carrega a configuração (ajuste o caminho se necessário dependendo de onde o include for chamado)
    if (file_exists('admin/config.php')) {
        require_once 'admin/config.php';
    } elseif (file_exists('../admin/config.php')) {
        require_once '../admin/config.php';
    } else {
        throw new Exception("config.php não encontrado.");
    }

    if (!isset($pdo)) {
        throw new Exception("PDO não inicializado.");
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 2. Identifica o loja_id (mesma lógica robusta do seu painel)
    $loja_id = 0;

    if (isset($_GET['loja_id']) && !empty($_GET['loja_id'])) {
        $loja_id = intval($_GET['loja_id']);
        $_SESSION['master_view_loja_id'] = $loja_id;
    } elseif (isset($_SESSION['master_view_loja_id'])) {
        $loja_id = intval($_SESSION['master_view_loja_id']);
    } else {
        if (file_exists('auth.php')) {
            require_once 'auth.php';
        } elseif (file_exists('../auth.php')) {
            require_once '../auth.php';
        }

        if (isset($_SESSION['loja_id']) && !empty($_SESSION['loja_id'])) {
            $loja_id = intval($_SESSION['loja_id']);
        } elseif (isset($_SESSION['admin_id'])) {
            $stmtAdminLoja = $pdo->prepare("SELECT loja_id, tipo FROM admins WHERE id = ? LIMIT 1");
            $stmtAdminLoja->execute([$_SESSION['admin_id']]);
            $dadosAdmin = $stmtAdminLoja->fetch(PDO::FETCH_ASSOC);
            
            if ($dadosAdmin) {
                $loja_id = intval($dadosAdmin['loja_id'] ?? 0);
                if ($dadosAdmin['tipo'] === 'master' && $loja_id === 0) {
                    $stmtPrimeiraLoja = $pdo->query("SELECT id FROM lojas ORDER BY id ASC LIMIT 1");
                    $loja_id = intval($stmtPrimeiraLoja->fetchColumn() ?: 1);
                }
            }
        }
    }

    if ($loja_id <= 0) {
        $loja_id = 1;
    }

    // 3. Busca a logo da loja no banco de dados
    $stmtLoja = $pdo->prepare("SELECT logo FROM lojas WHERE id = ?");
    $stmtLoja->execute([$loja_id]);
    $loja = $stmtLoja->fetch(PDO::FETCH_ASSOC);

    $logoPath = $loja['logo'] ?? '';

    // 4. Se houver logo cadastrada e o arquivo existir fisicamente, exibe ele
    if (!empty($logoPath)) {
        // Remove barras iniciais se houver para garantir o caminho correto a partir da raiz
        $cleanPath = ltrim($logoPath, '/');
        
        // Verifica caminhos possíveis (raiz ou dentro da pasta admin)
        $caminhoReal = '';
        if (file_exists($cleanPath)) {
            $caminhoReal = $cleanPath;
        } elseif (file_exists('../' . $cleanPath)) {
            $caminhoReal = '../' . $cleanPath;
        }

        if ($caminhoReal && file_exists($caminhoReal)) {
            $ext = strtolower(pathinfo($caminhoReal, PATHINFO_EXTENSION));
            
            // Define o Content-Type correto com base na extensão
            $contentType = 'image/png';
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $contentType = 'image/jpeg';
            } elseif ($ext === 'webp') {
                $contentType = 'image/webp';
            } elseif ($ext === 'svg') {
                $contentType = 'image/svg+xml';
            } elseif ($ext === 'ico') {
                $contentType = 'image/x-icon';
            }

            header("Content-Type: " . $contentType);
            readfile($caminhoReal);
            exit;
        }
    }

} catch (Exception $e) {
    // Silencia ou trata erros caso necessário para não quebrar o header da página
}

// 5. Fallback padrão: Se não encontrar a logo, gera ou redireciona para um ícone/favicon padrão transparente ou cinza
header("HTTP/1.1 404 Not Found");
exit;