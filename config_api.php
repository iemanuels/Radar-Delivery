<?php
// config_api.php - API Backend para Configurações e Logs Dinâmicos do Banco de Dados
date_default_timezone_set('America/Manaus');
header('Content-Type: application/json; charset=UTF-8');

try {
    // Corrige e valida o caminho para o config.php dentro da pasta admin/
    if (file_exists('admin/config.php')) {
        require_once 'admin/config.php';
    } elseif (file_exists('../admin/config.php')) {
        require_once '../admin/config.php';
    } else {
        throw new Exception("O arquivo de configuração 'config.php' não foi encontrado na pasta admin/.");
    }

    if (!isset($pdo)) {
        throw new Exception("A conexão com o banco de dados (\$pdo) não está inicializada.");
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // ==========================================
    // DEFINIÇÃO DO LOJA_ID (MASTER vs USUÁRIO LOGADO)
    // ==========================================
    $loja_id = 0;

    if (isset($_GET['loja_id']) && !empty($_GET['loja_id']) && isset($_GET['token'])) {
        $loja_id = intval($_GET['loja_id']);
        $_SESSION['master_view_loja_id'] = $loja_id;
        $_SESSION['master_token'] = $_GET['token'];
    } elseif (isset($_SESSION['master_view_loja_id']) && isset($_SESSION['master_token'])) {
        $loja_id = intval($_SESSION['master_view_loja_id']);
    } else {
        if (file_exists('auth.php')) {
            require_once 'auth.php';
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

    $pdo->exec("SET time_zone = '-04:00'");

    $stmtLojaInfo = $pdo->prepare("SELECT nome_loja FROM lojas WHERE id = ? LIMIT 1");
    $stmtLojaInfo->execute([$loja_id]);
    $dadosLojaAtual = $stmtLojaInfo->fetch(PDO::FETCH_ASSOC);
    $nomeLojaAtual = $dadosLojaAtual['nome_loja'] ?? "Loja #{$loja_id}";

    // Garantir tabela de logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS sistema_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loja_id INT NOT NULL,
        nivel VARCHAR(20) DEFAULT 'INFO',
        mensagem TEXT NOT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;");

    function registrarLogDb($pdo, $loja_id, $nivel, $mensagem) {
        try {
            $stmt = $pdo->prepare("INSERT INTO sistema_logs (loja_id, nivel, mensagem) VALUES (?, ?, ?)");
            $stmt->execute([$loja_id, $nivel, $mensagem]);
        } catch (Exception $ex) {
            // Silencia erro de log
        }
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // 1. Obter logs recentes via GET
    if ($method === 'GET' && isset($_GET['acao']) && $_GET['acao'] === 'obter_logs') {
        $stmtLogs = $pdo->prepare("SELECT id, nivel, mensagem, criado_em FROM sistema_logs WHERE loja_id = ? ORDER BY criado_em DESC LIMIT 15");
        $stmtLogs->execute([$loja_id]);
        echo json_encode(['sucesso' => true, 'logs' => $stmtLogs->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 2. Ações via POST (Limpeza, Salvamentos e Uploads)
    if ($method === 'POST') {
        // Tratamento prioritário para limpeza de logs da loja atual
        $acaoPost = $_POST['acao'] ?? '';
        if ($acaoPost === 'limpar_logs' || (isset($_GET['acao']) && $_GET['acao'] === 'limpar_logs')) {
            $stmtDel = $pdo->prepare("DELETE FROM sistema_logs WHERE loja_id = ?");
            $stmtDel->execute([$loja_id]);

            registrarLogDb($pdo, $loja_id, 'DB_CLEAR', "Histórico de logs limpo para a loja '{$nomeLojaAtual}' (ID {$loja_id}).");

            echo json_encode(['sucesso' => true, 'mensagem' => 'Logs da loja limpos com sucesso']);
            exit;
        }

        // Atualização de campos genéricos da loja
        if (isset($_POST['ajax_salvar'])) {
            $campo = trim($_POST['campo'] ?? '');
            $valor = $_POST['valor'] ?? '';

            $camposPermitidos = [
                'nome_loja', 'cnpj', 'whatsapp', 'endereco', 'latitude', 'longitude',
                'forcar_fechado', 'horarios_funcionamento',
                'pagamento_dinheiro', 'pagamento_debito', 'pagamento_credito',
                'pagamento_pix', 'tipo_chave_pix', 'chave_pix', 'pix_copia_cola'
            ];

            if (in_array($campo, $camposPermitidos)) {
                if (($campo === 'latitude' || $campo === 'longitude') && trim($valor) === '') {
                    $stmt = $pdo->prepare("UPDATE lojas SET {$campo} = NULL WHERE id = ?");
                    $stmt->execute([$loja_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE lojas SET {$campo} = ? WHERE id = ?");
                    $stmt->execute([$valor, $loja_id]);
                }

                registrarLogDb($pdo, $loja_id, 'DB_UPDATE', "Loja '{$nomeLojaAtual}' (ID {$loja_id}) atualizada | Campo '{$campo}' alterado.");

                echo json_encode(['sucesso' => true, 'mensagem' => 'Atualizado com sucesso']);
                exit;
            } else {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Campo não permitido']);
                exit;
            }
        }

        // Salvar Mercado Pago
        if (isset($_POST['ajax_salvar_mp'])) {
            $access_token = trim($_POST['access_token'] ?? '');
            $public_key = trim($_POST['public_key'] ?? '');
            $webhook_secret = trim($_POST['webhook_secret'] ?? '');
            $ativo = isset($_POST['ativo']) ? intval($_POST['ativo']) : 0;

            $stmtCheck = $pdo->prepare("SELECT id FROM mp WHERE loja_id = ?");
            $stmtCheck->execute([$loja_id]);
            if ($stmtCheck->fetch()) {
                $stmtUp = $pdo->prepare("UPDATE mp SET access_token = ?, public_key = ?, webhook_secret = ?, ativo = ? WHERE loja_id = ?");
                $stmtUp->execute([$access_token, $public_key, $webhook_secret, $ativo, $loja_id]);
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO mp (loja_id, access_token, public_key, webhook_secret, ativo) VALUES (?, ?, ?, ?, ?)");
                $stmtIns->execute([$loja_id, $access_token, $public_key, $webhook_secret, $ativo]);
            }

            registrarLogDb($pdo, $loja_id, 'DB_MP', "Tabela 'mp' | Credenciais do Mercado Pago da loja '{$nomeLojaAtual}' atualizadas.");

            echo json_encode(['sucesso' => true, 'mensagem' => 'Salvo com sucesso']);
            exit;
        }

        // Upload de Logo
        if (isset($_POST['ajax_upload_logo'])) {
            if (isset($_POST['cropped_image'])) {
                $dataUrl = $_POST['cropped_image'];
                list($type, $dataUrl) = explode(';', $dataUrl);
                list(, $dataUrl)      = explode(',', $dataUrl);
                $decodedData = base64_decode($dataUrl);

                $newFileName = 'logo_' . $loja_id . '_' . time() . '.webp';
                $uploadFileDir = 'uploads/';
                
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }

                $dest_path = $uploadFileDir . $newFileName;
                if (file_put_contents($dest_path, $decodedData)) {
                    $stmt = $pdo->prepare("UPDATE lojas SET logo = ? WHERE id = ?");
                    $stmt->execute([$dest_path, $loja_id]);

                    registrarLogDb($pdo, $loja_id, 'DB_UPLOAD', "Logo da loja '{$nomeLojaAtual}' atualizada: {$dest_path}");

                    echo json_encode(['sucesso' => true, 'url' => $dest_path]);
                    exit;
                }
            }
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao processar imagem.']);
            exit;
        }
    }

    echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição inválida.']);

} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}