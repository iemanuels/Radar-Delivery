<?php
// criar_pasta_loja.php - Cria a pasta da loja na raiz, duplica os arquivos modelo e atualiza todas as demais pastas de lojas existentes
date_default_timezone_set('America/Manaus');
header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'admin/config.php';

$loja_id = 0;
if (isset($_GET['loja_id']) && !empty($_GET['loja_id'])) {
    $loja_id = intval($_GET['loja_id']);
} elseif (isset($_SESSION['master_view_loja_id'])) {
    $loja_id = intval($_SESSION['master_view_loja_id']);
} elseif (isset($_SESSION['loja_id']) && !empty($_SESSION['loja_id'])) {
    $loja_id = intval($_SESSION['loja_id']);
} elseif (isset($_SESSION['admin_id'])) {
    $stmtAdminLoja = $pdo->prepare("SELECT loja_id, tipo FROM admins WHERE id = ? LIMIT 1");
    $stmtAdminLoja->execute([$_SESSION['admin_id']]);
    $dadosAdmin = $stmtAdminLoja->fetch(PDO::FETCH_ASSOC);
    if ($dadosAdmin) {
        $loja_id = intval($dadosAdmin['loja_id'] ?? 0);
        if ($dadosAdmin['tipo'] === 'master' && $loja_id === 0) {
            $loja_id = 1;
        }
    }
}

if ($loja_id <= 0) {
    $loja_id = 1;
}

try {
    // 1. Busca a loja solicitada no momento
    $stmt = $pdo->prepare("SELECT nome_loja, slug FROM lojas WHERE id = ?");
    $stmt->execute([$loja_id]);
    $loja = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loja) {
        throw new Exception("Loja não encontrada no banco de dados.");
    }

    $nomeLoja = $loja['nome_loja'];
    
    $slugLoja = !empty($loja['slug']) ? trim($loja['slug']) : '';
    if (empty($slugLoja) || $slugLoja === '0') {
        $slugLoja = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nomeLoja)));
    }
    if (empty($slugLoja)) {
        $slugLoja = 'loja' . $loja_id;
    }

    $pastaOrigem = __DIR__ . '/mercado';
    if (!is_dir($pastaOrigem)) {
        throw new Exception("A pasta modelo 'mercado/' não foi encontrada na raiz.");
    }

    // Função auxiliar para processar a criação/atualização de uma pasta de loja
    function processarPastaLoja($pDestino, $lId, $pOrigem) {
        if (!is_dir($pDestino)) {
            if (!mkdir($pDestino, 0755, true)) {
                return false;
            }
        }

        // Copia ou atualiza os arquivos da pasta modelo recursivamente
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pOrigem, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $subPath = $iterator->getSubPathName();
            $destinoItem = $pDestino . '/' . $subPath;

            if ($item->isDir()) {
                if (!is_dir($destinoItem)) {
                    mkdir($destinoItem, 0755, true);
                }
            } else {
                copy($item, $destinoItem);

                // Ajusta dinamicamente o $loja_id nos arquivos PHP copiados
                $conteudoArquivo = file_get_contents($destinoItem);
                if ($conteudoArquivo !== false) {
                    $conteudoAtualizado = preg_replace('/\$loja_id\s*=\s*\d+;/', "\$loja_id = {$lId};", $conteudoArquivo);
                    if ($conteudoAtualizado !== null) {
                        file_put_contents($destinoItem, $conteudoAtualizado);
                    }
                }
            }
        }

        // Garante atualização de todos os arquivos .php existentes recursivamente na pasta da loja
        $iteratorPhp = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pDestino, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iteratorPhp as $arqItem) {
            if ($arqItem->isFile() && pathinfo($arqItem->getFilename(), PATHINFO_EXTENSION) === 'php') {
                $arqPath = $arqItem->getRealPath();
                $conteudo = file_get_contents($arqPath);
                if ($conteudo !== false) {
                    $novoConteudo = preg_replace('/\$loja_id\s*=\s*\d+;/', "\$loja_id = {$lId};", $conteudo);
                    if ($novoConteudo !== null) {
                        file_put_contents($arqPath, $novoConteudo);
                    }
                }
            }
        }

        // Copia arquivos auxiliares se existirem na raiz
        $arquivosAuxiliares = ['atualizar_posicao.php', 'estoque-api.php', 'verificar_pagamento.php'];
        foreach ($arquivosAuxiliares as $arqAux) {
            $origemAux = __DIR__ . '/' . $arqAux;
            $destinoAux = $pDestino . '/' . $arqAux;
            if (file_exists($origemAux) && !file_exists($destinoAux)) {
                copy($origemAux, $destinoAux);
            }
        }

        return true;
    }

    // 2. Processa a pasta da loja atual solicitada
    $pastaDestinoAtual = __DIR__ . '/' . $slugLoja;
    processarPastaLoja($pastaDestinoAtual, $loja_id, $pastaOrigem);

    // 3. Atualiza TODAS as outras lojas cadastradas no banco que já possuem pastas criadas
    $stmtTodasLojas = $pdo->query("SELECT id, nome_loja, slug FROM lojas");
    $todasLojas = $stmtTodasLojas->fetchAll(PDO::FETCH_ASSOC);

    foreach ($todasLojas as $outraLoja) {
        $outraId = intval($outraLoja['id']);
        $outroSlug = !empty($outraLoja['slug']) ? trim($outraLoja['slug']) : '';
        if (empty($outroSlug) || $outroSlug === '0') {
            $outroSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $outraLoja['nome_loja'])));
        }
        if (empty($outroSlug)) {
            $outroSlug = 'loja' . $outraId;
        }

        $pastaOutraLoja = __DIR__ . '/' . $outroSlug;
        // Se a pasta dessa outra loja já existe, atualiza os arquivos e o ID dela
        if (is_dir($pastaOutraLoja)) {
            processarPastaLoja($pastaOutraLoja, $outraId, $pastaOrigem);
        }
    }

    // Endereço atualizado com o novo domínio
    $urlLoja = "https://radar.ksbyte.top/{$slugLoja}/index.php";

    echo json_encode([
        'sucesso' => true,
        'url_loja' => $urlLoja,
        'mensagem' => 'Pasta da loja criada e atualizada com sucesso!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage()
    ]);
}
?>