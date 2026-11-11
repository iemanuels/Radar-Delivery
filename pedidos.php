<?php
// pedidos.php
$currentPage = 'pedidos';

// Define obrigatoriamente o fuso horário para Manaus / Amazonas
date_default_timezone_set('America/Manaus');

// Inclui o arquivo de configuração e conexão com o banco de dados
require_once 'admin/config.php';

// Garante que o MySQL utilize o fuso horário de Manaus (-04:00)
try {
    $pdo->exec("SET time_zone = '-04:00'");
} catch (Exception $e) {
    // Caso o servidor de banco de dados não permita alterar a timezone global
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// DEFINIÇÃO DO LOJA_ID (MASTER vs USUÁRIO LOGADO)
// ==========================================
$isMasterAccess = false;
$loja_id = 0;

// 1. Verifica se veio por token/URL de Administrador Master
if (isset($_GET['loja_id']) && !empty($_GET['loja_id']) && isset($_GET['token'])) {
    $loja_id = intval($_GET['loja_id']);
    $isMasterAccess = true;
    $_SESSION['master_view_loja_id'] = $loja_id;
    $_SESSION['master_token'] = $_GET['token'];
} elseif (isset($_SESSION['master_view_loja_id']) && isset($_SESSION['master_token'])) {
    $loja_id = intval($_SESSION['master_view_loja_id']);
    $isMasterAccess = true;
} else {
    // 2. Acesso normal: Requer autenticação do usuário comum/lojista
    require_once 'auth.php';

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

// Fallback de segurança
if ($loja_id <= 0) {
    $loja_id = 1;
}

// ==========================================
// GERAÇÃO DE PDF PARA IMPRESSORA TÉRMICA
// ==========================================
if (isset($_GET['acao']) && $_GET['acao'] === 'imprimir_pdf' && isset($_GET['id'])) {
    $idPedido = intval($_GET['id']);

    // Busca dados da loja
    $stmtLoja = $pdo->prepare("SELECT * FROM lojas WHERE id = ?");
    $stmtLoja->execute([$loja_id]);
    $loja = $stmtLoja->fetch(PDO::FETCH_ASSOC);

    // Busca dados do pedido e cliente restrito à loja atual
    $stmtPedido = $pdo->prepare("
        SELECT p.*, c.nome as nome_cliente, c.telefone as telefone_cliente, e.nome as nome_entregador,
                TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) as minutos_decorridos
        FROM pedidos p
        INNER JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN entregadores e ON p.entregador_id = e.id
        WHERE p.id = ? AND p.loja_id = ?
    ");
    $stmtPedido->execute([$idPedido, $loja_id]);
    $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        die("Pedido não encontrado.");
    }

    // Busca itens do pedido com suporte completo a decimais e unidades (kg/un)
    $stmtItens = $pdo->prepare("
        SELECT ip.*, pr.nome as nome_produto, pr.unidade as unidade_produto 
        FROM itens_pedido ip 
        LEFT JOIN produtos pr ON ip.produto_id = pr.id 
        WHERE ip.pedido_id = ?
    ");
    $stmtItens->execute([$idPedido]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    // HTML otimizado para Impressora Térmica
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Imprimir Pedido #<?php echo htmlspecialchars($pedido['codigo_pedido']); ?></title>
        <style>
            @page {
                size: 80mm auto;
                margin: 0;
            }
            body {
                font-family: 'Courier New', Courier, monospace;
                font-size: 12px;
                line-height: 1.2;
                color: #000;
                background: #fff;
                margin: 0;
                padding: 5mm;
                width: 70mm;
            }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .font-bold { font-weight: bold; }
            .uppercase { text-transform: uppercase; }
            .line { border-bottom: 1px dashed #000; margin: 8px 0; }
            table { width: 100%; border-collapse: collapse; }
            th, td { font-size: 11px; padding: 2px 0; vertical-align: top; }
            .no-print { margin-top: 15px; text-align: center; }
            .btn { background: #000; color: #fff; padding: 8px 15px; border: none; cursor: pointer; font-weight: bold; width: 100%; border-radius: 4px; }
            @media print {
                .no-print { display: none; }
            }
        </style>
    </head>
    <body onload="window.print();">
        <div class="text-center">
            <div class="font-bold" style="font-size: 14px;"><?php echo htmlspecialchars($loja['nome_loja'] ?? 'COMPROVANTE DE PEDIDO'); ?></div>
            <?php if (!empty($loja['cnpj'])): ?>
                <div>CNPJ: <?php echo htmlspecialchars($loja['cnpj']); ?></div>
            <?php endif; ?>
            <div><?php echo htmlspecialchars($loja['endereco'] ?? ''); ?></div>
            <div>WhatsApp: <?php echo htmlspecialchars($loja['whatsapp'] ?? ''); ?></div>
        </div>

        <div class="line"></div>

        <div class="text-center font-bold" style="font-size: 13px;">
            PEDIDO #<?php echo htmlspecialchars($pedido['codigo_pedido']); ?>
        </div>
        <div><strong>Data/Hora:</strong> <?php echo date('d/m/Y H:i:s', strtotime($pedido['criado_em'])); ?></div>

        <div class="line"></div>

        <div><strong>CLIENTE:</strong> <?php echo htmlspecialchars($pedido['nome_cliente']); ?></div>
        <div><strong>Tel:</strong> <?php echo htmlspecialchars($pedido['telefone_cliente']); ?></div>
        <div><strong>Endereço:</strong> <?php echo htmlspecialchars($pedido['endereco_entrega']); ?></div>

        <div class="line"></div>

        <div class="font-bold">ITENS DO PEDIDO:</div>
        <table>
            <thead>
                <tr>
                    <th style="text-align: left;">QTD / PRODUTO</th>
                    <th class="text-right">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                    <?php 
                        $unidade = $item['unidade_produto'] ?? 'un';
                        $qtdVal = floatval($item['quantidade']);
                        if ($unidade === 'kg') {
                            $qtdFormatada = number_format($qtdVal, 3, ',', '.') . ' kg';
                        } else {
                            $qtdFormatada = intval($qtdVal) . ' un';
                        }
                    ?>
                    <tr>
                        <td><?php echo $qtdFormatada; ?> <?php echo htmlspecialchars($item['nome_produto'] ?? 'Item'); ?></td>
                        <td class="text-right">R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="line"></div>

        <table>
            <tr>
                <td>Subtotal dos Itens:</td>
                <td class="text-right">R$ <?php echo number_format($pedido['subtotal'], 2, ',', '.'); ?></td>
            </tr>
            <tr>
                <td>Taxa de Entrega:</td>
                <td class="text-right">R$ <?php echo number_format($pedido['taxa_entrega'], 2, ',', '.'); ?></td>
            </tr>
            <tr class="font-bold" style="font-size: 13px;">
                <td>TOTAL GERAL:</td>
                <td class="text-right">R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></td>
            </tr>
        </table>

        <div class="line"></div>

        <div><strong>Forma de Pagamento:</strong> <span class="uppercase"><?php echo htmlspecialchars($pedido['forma_pagamento']); ?></span></div>
        <?php if (!empty($pedido['troco'])): ?>
            <div><strong>Troco para:</strong> <?php echo htmlspecialchars($pedido['troco']); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($pedido['nome_entregador'])): ?>
            <div><strong>Entregador:</strong> <?php echo htmlspecialchars($pedido['nome_entregador']); ?></div>
        <?php endif; ?>

        <div class="line"></div>
        <div class="text-center" style="font-size: 10px; margin-top: 10px;">
            Obrigado pela preferência!<br>
            Powered by MercadoSaaS
        </div>

        <div class="no-print">
            <button class="btn" onclick="window.print()">IMPRIMIR NOVAMENTE</button>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ==========================================
// PROCESSAMENTO DE AÇÕES VIA AJAX
// ==========================================
if (isset($_GET['acao']) && isset($_GET['id'])) {
    $idPedido = intval($_GET['id']);
    $novaAcao = $_GET['acao'];
    
    $statusPermitidos = ['preparando', 'em_rota', 'entregue', 'cancelado'];
    if (in_array($novaAcao, $statusPermitidos)) {
        $stmtUpdate = $pdo->prepare("UPDATE pedidos SET status_pedido = ? WHERE id = ? AND loja_id = ?");
        $stmtUpdate->execute([$novaAcao, $idPedido, $loja_id]);
    }
    
    echo json_encode(['status' => 'sucesso']);
    exit;
}

// Salvar o tempo limite de alerta configurado pelo gerente via AJAX
if (isset($_POST['salvar_tempo_alerta'])) {
    $tempoMinutos = intval($_POST['tempo_alerta']);
    $_SESSION['tempo_alerta_preparo'] = $tempoMinutos;
    echo json_encode(['status' => 'sucesso']);
    exit;
}

$tempoAlertaLimite = $_SESSION['tempo_alerta_preparo'] ?? 6;

// Se a requisição for para atualizar o painel em tempo real via AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $statusFiltroAjax = isset($_GET['status']) ? $_GET['status'] : 'todos';
    $buscaAjax = isset($_GET['busca']) ? trim($_GET['busca']) : '';

    $stmtContagem = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status_pedido != 'cancelado' AND status_pedido != 'entregue' THEN 1 ELSE 0 END) as total_geral,
            SUM(CASE WHEN status_pedido = 'pendente' THEN 1 ELSE 0 END) as total_pendentes,
            SUM(CASE WHEN status_pedido = 'preparando' THEN 1 ELSE 0 END) as total_preparando,
            SUM(CASE WHEN status_pedido = 'em_rota' THEN 1 ELSE 0 END) as total_em_rota,
            SUM(CASE WHEN status_pedido = 'entregue' THEN 1 ELSE 0 END) as total_entregues,
            SUM(CASE WHEN status_pedido = 'cancelado' THEN 1 ELSE 0 END) as total_cancelados
        FROM pedidos 
        WHERE loja_id = ?
    ");
    $stmtContagem->execute([$loja_id]);
    $contagens = $stmtContagem->fetch(PDO::FETCH_ASSOC);

    $sql = "
        SELECT p.*, c.nome as nome_cliente, e.nome as nome_entregador,
               TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) as minutos_decorridos
        FROM pedidos p
        INNER JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN entregadores e ON p.entregador_id = e.id
        WHERE p.loja_id = ?
    ";
    $params = [$loja_id];

    if ($statusFiltroAjax === 'todos') {
        $sql .= " AND p.status_pedido != 'cancelado' AND p.status_pedido != 'entregue'";
    } elseif ($statusFiltroAjax !== 'todos') {
        $sql .= " AND p.status_pedido = ?";
        $params[] = $statusFiltroAjax;
    }

    if (!empty($buscaAjax)) {
        $sql .= " AND (p.codigo_pedido LIKE ? OR c.nome LIKE ?)";
        $params[] = "%{$buscaAjax}%";
        $params[] = "%{$buscaAjax}%";
    }

    $sql .= " ORDER BY 
                FIELD(p.status_pedido, 'pendente', 'preparando', 'em_rota', 'entregue', 'cancelado'),
                (p.status_pedido = 'preparando' AND TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) >= ?) DESC,
                p.criado_em DESC";
    $params[] = $tempoAlertaLimite;

    $stmtPedidos = $pdo->prepare($sql);
    $stmtPedidos->execute($params);
    $pedidos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

    ob_start();
    ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="gridPedidos">
        <?php if (count($pedidos) > 0): ?>
            <?php foreach ($pedidos as $pedido): ?>
                <?php 
                    $status = $pedido['status_pedido'];
                    $minutos = intval($pedido['minutos_decorridos']);
                    $emAlerta = ($status === 'preparando' && $minutos >= $tempoAlertaLimite);

                    $borderColor = "border-slate-200";
                    $headerBg = "bg-slate-50 border-slate-100";
                    $badgeBg = "bg-slate-500 text-white";
                    $labelStatus = ucfirst($status);

                    if ($emAlerta) {
                        $borderColor = "border-rose-400 ring-2 ring-rose-100 animate-pulse";
                        $headerBg = "bg-rose-500 text-white border-rose-500";
                        $badgeBg = "bg-white text-rose-600";
                        $labelStatus = "ATRASADO (" . $minutos . " min)";
                    } elseif ($status == 'pendente') {
                        $borderColor = "border-amber-300 ring-2 ring-amber-100";
                        $headerBg = "bg-amber-50 border-amber-100";
                        $badgeBg = "bg-amber-500 text-white";
                        $labelStatus = "Novo / Pendente";
                    } elseif ($status == 'preparando') {
                        $borderColor = "border-blue-200";
                        $headerBg = "bg-blue-50 border-blue-100";
                        $badgeBg = "bg-blue-500 text-white";
                        $labelStatus = "Preparando (" . $minutos . "m)";
                    } elseif ($status == 'em_rota') {
                        $borderColor = "border-purple-200";
                        $headerBg = "bg-purple-50 border-purple-100";
                        $badgeBg = "bg-purple-500 text-white";
                        $labelStatus = "Em Rota";
                    } elseif ($status == 'entregue') {
                        $borderColor = "border-emerald-200";
                        $headerBg = "bg-emerald-50 border-emerald-100";
                        $badgeBg = "bg-emerald-500 text-white";
                        $labelStatus = "Entregue";
                    } elseif ($status == 'cancelado') {
                        $borderColor = "border-rose-200";
                        $headerBg = "bg-rose-50 border-rose-100";
                        $badgeBg = "bg-rose-500 text-white";
                        $labelStatus = "Cancelado";
                    }

                    $stmtItens = $pdo->prepare("
                        SELECT ip.*, pr.nome as nome_produto, pr.unidade as unidade_produto 
                        FROM itens_pedido ip 
                        LEFT JOIN produtos pr ON ip.produto_id = pr.id 
                        WHERE ip.pedido_id = ?
                    ");
                    $stmtItens->execute([$pedido['id']]);
                    $itensPedido = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div class="bg-white rounded-2xl border <?php echo $borderColor; ?> shadow-sm overflow-hidden flex flex-col justify-between transition-all">
                    <div>
                        <div class="p-4 <?php echo $headerBg; ?> border-b flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold <?php echo $emAlerta ? 'text-white' : 'text-slate-800'; ?>">#<?php echo htmlspecialchars($pedido['codigo_pedido']); ?></span>
                                <span class="text-xs <?php echo $badgeBg; ?> px-2.5 py-0.5 rounded-full font-bold shadow-sm"><?php echo $labelStatus; ?></span>
                            </div>
                            <span class="text-xs <?php echo $emAlerta ? 'text-white/80' : 'text-slate-500'; ?> font-medium flex items-center gap-1 shrink-0">
                                <i data-lucide="clock" class="w-3.5 h-3.5"></i> 
                                <?php echo date('d/m H:i', strtotime($pedido['criado_em'])); ?>
                            </span>
                        </div>

                        <div class="p-4 sm:p-5 space-y-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h4 class="font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($pedido['nome_cliente']); ?></h4>
                                    <p class="text-xs text-slate-400 mt-0.5 break-words"><?php echo htmlspecialchars($pedido['endereco_entrega']); ?></p>
                                </div>
                                <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded-lg uppercase shrink-0"><?php echo htmlspecialchars($pedido['forma_pagamento']); ?></span>
                            </div>

                            <?php if (!empty($pedido['troco'])): ?>
                                <div class="text-xs text-amber-700 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200 font-medium">
                                    Troco para: <strong><?php echo htmlspecialchars($pedido['troco']); ?></strong>
                                </div>
                            <?php endif; ?>

                            <div class="border-t border-slate-100 pt-3 space-y-2">
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Itens do Pedido</p>
                                <ul class="text-sm space-y-1.5 text-slate-600">
                                    <?php if (count($itensPedido) > 0): ?>
                                        <?php foreach ($itensPedido as $item): ?>
                                            <?php 
                                                $unidade = $item['unidade_produto'] ?? 'un';
                                                $qtdVal = floatval($item['quantidade']);
                                                if ($unidade === 'kg') {
                                                    $qtdFormatada = number_format($qtdVal, 3, ',', '.') . ' kg';
                                                } else {
                                                    $qtdFormatada = intval($qtdVal) . ' un';
                                                }
                                            ?>
                                            <li class="flex justify-between items-center gap-2">
                                                <div class="min-w-0 flex items-center gap-1">
                                                    <span class="font-bold text-slate-800 bg-orange-50 text-orange-700 px-2 py-0.5 rounded text-xs shrink-0"><?php echo $qtdFormatada; ?></span>
                                                    <span class="truncate"><?php echo htmlspecialchars($item['nome_produto'] ?? 'Produto'); ?></span>
                                                </div>
                                                <span class="font-medium text-slate-800 shrink-0">R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="text-xs text-slate-400">Nenhum item detalhado.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <?php if (!empty($pedido['nome_entregador'])): ?>
                                <div class="text-xs text-slate-500 bg-slate-50 p-2 rounded-lg flex items-center gap-1.5">
                                    <i data-lucide="bike" class="w-3.5 h-3.5 text-purple-600 shrink-0"></i>
                                    <span class="truncate">Entregador: <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($pedido['nome_entregador']); ?></span></span>
                                </div>
                            <?php endif; ?>

                            <div class="border-t border-slate-100 pt-3 flex justify-between items-center">
                                <span class="text-xs text-slate-500 font-medium">Total com Entrega</span>
                                <span class="text-base font-bold text-slate-800">R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-50 border-t border-slate-100 flex gap-2">
                        <?php if ($status == 'pendente'): ?>
                            <button onclick="atualizarStatus(<?php echo $pedido['id']; ?>, 'cancelado')" class="flex-1 bg-rose-50 hover:bg-rose-100 text-rose-600 font-semibold py-2 rounded-xl text-xs transition-colors text-center cursor-pointer">Recusar</button>
                            <button onclick="atualizarStatus(<?php echo $pedido['id']; ?>, 'preparando')" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 rounded-xl text-xs transition-colors shadow-sm shadow-orange-500/20 text-center cursor-pointer">Aceitar Pedido</button>
                        <?php elseif ($status == 'preparando'): ?>
                            <button onclick="imprimirPedido(<?php echo $pedido['id']; ?>)" class="bg-slate-800 hover:bg-slate-900 text-white font-semibold px-3 py-2 rounded-xl text-xs transition-colors shadow-sm flex items-center justify-center gap-1 cursor-pointer shrink-0" title="Imprimir Nota">
                                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                            </button>
                            <button onclick="atualizarStatus(<?php echo $pedido['id']; ?>, 'em_rota')" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-xl text-xs transition-colors shadow-sm text-center cursor-pointer">Despachar</button>
                        <?php elseif ($status == 'em_rota'): ?>
                            <button onclick="imprimirPedido(<?php echo $pedido['id']; ?>)" class="bg-slate-800 hover:bg-slate-900 text-white font-semibold px-3 py-2 rounded-xl text-xs transition-colors shadow-sm flex items-center justify-center gap-1 cursor-pointer shrink-0" title="Imprimir Nota">
                                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                            </button>
                            <button onclick="atualizarStatus(<?php echo $pedido['id']; ?>, 'entregue')" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 rounded-xl text-xs transition-colors shadow-sm text-center cursor-pointer">Marcar Entregue</button>
                        <?php elseif ($status == 'entregue'): ?>
                            <button onclick="imprimirPedido(<?php echo $pedido['id']; ?>)" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold px-3 py-2 rounded-xl text-xs transition-colors flex items-center justify-center gap-1 cursor-pointer" title="Imprimir Nota">
                                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Re-imprimir
                            </button>
                            <div class="flex-1 text-center text-xs font-semibold text-slate-400 py-1">Finalizado</div>
                        <?php else: ?>
                            <div class="w-full text-center text-xs font-semibold text-slate-400 py-1">Pedido Finalizado</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full bg-white p-12 rounded-2xl border border-slate-200 text-center space-y-2">
                <i data-lucide="inbox" class="w-10 h-10 text-slate-300 mx-auto"></i>
                <p class="text-slate-600 font-medium">Nenhum pedido encontrado nesta categoria.</p>
                <p class="text-xs text-slate-400">Os pedidos recebidos aparecerão aqui em tempo real.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $htmlPedidos = ob_get_clean();

    echo json_encode([
        'totais' => [
            'todos' => $contagens['total_geral'] ?? 0,
            'pendente' => $contagens['total_pendentes'] ?? 0,
            'preparando' => $contagens['total_preparando'] ?? 0,
            'em_rota' => $contagens['total_em_rota'] ?? 0,
            'entregue' => $contagens['total_entregues'] ?? 0,
            'cancelado' => $contagens['total_cancelados'] ?? 0,
        ],
        'total_pendentes' => $contagens['total_pendentes'] ?? 0,
        'html' => $htmlPedidos
    ]);
    exit;
}

$statusFiltro = isset($_GET['status']) ? $_GET['status'] : 'todos';
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos | MercadoSaaS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
    <!-- Ícone da Loja Dinâmico -->
    <link rel="icon" type="image/png" href="icon.php">
    <link rel="apple-touch-icon" href="icon.php">
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex overflow-x-hidden">

    <!-- Menu Lateral -->
    <?php include 'includes/menu.php'; ?>

    <!-- Conteúdo Principal -->
    <div class="flex-1 flex flex-col md:pl-64 min-w-0 w-full overflow-x-hidden">
        
        <!-- Topbar Ajustada para Responsividade e Prevenção de Zoom no iOS -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 px-4 sm:px-6 py-3.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-start">
                <div class="flex items-center gap-3">
                    <button id="openSidebarBtn" class="md:hidden text-slate-500 hover:text-slate-700 p-1.5 rounded-lg border border-slate-200 bg-white shrink-0">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    <h1 class="text-base sm:text-lg font-bold text-slate-800 truncate">Gerenciamento de Pedidos</h1>
                </div>
            </div>
            
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap w-full sm:w-auto justify-between sm:justify-end">
                <!-- Input de minutos com text-base para evitar zoom automático no iOS (Safari) -->
                <div class="flex items-center gap-1.5 sm:gap-2 bg-slate-100 px-2.5 sm:px-3 py-1.5 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 shrink-0">
                    <i data-lucide="timer" class="w-4 h-4 text-orange-500 shrink-0"></i>
                    <span class="hidden xs:inline">Alerta:</span>
                    <input type="number" id="inputTempoAlerta" value="<?php echo $tempoAlertaLimite; ?>" min="1" max="60" onchange="salvarTempoAlerta(this.value)" class="w-12 text-center bg-white border border-slate-300 rounded-lg py-0.5 text-base sm:text-xs font-bold">
                    <span>min</span>
                </div>

                <span id="badgeNovosPedidos" class="inline-flex items-center gap-1.5 sm:gap-2 bg-orange-50 border border-orange-200 px-3 py-1.5 rounded-full text-xs font-semibold text-orange-700 shrink-0">
                    <i data-lucide="bell-ring" class="w-3.5 h-3.5 animate-bounce text-orange-600"></i> 
                    <span><span id="txtTotalPendentes">0</span> Novos</span>
                </span>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 p-4 sm:p-6 space-y-6 max-w-[1920px] w-full mx-auto">
            
            <!-- Filtros de Status e Busca com Rolagem Suave no Mobile -->
            <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-2 overflow-x-auto pb-2 lg:pb-0 no-scrollbar" id="containerFiltros">
                    <button onclick="mudarFiltro('todos')" data-status="todos" class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-colors filter-btn cursor-pointer whitespace-nowrap shrink-0">Todos (<span id="count-todos">0</span>)</button>
                    <button onclick="mudarFiltro('pendente')" data-status="pendente" class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors filter-btn cursor-pointer whitespace-nowrap shrink-0">Pendentes (<span id="count-pendente">0</span>)</button>
                    <button onclick="mudarFiltro('preparando')" data-status="preparando" class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors filter-btn cursor-pointer whitespace-nowrap shrink-0">Em Preparo (<span id="count-preparando">0</span>)</button>
                    <button onclick="mudarFiltro('em_rota')" data-status="em_rota" class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors filter-btn cursor-pointer whitespace-nowrap shrink-0">Em Rota (<span id="count-em_rota">0</span>)</button>
                    <button onclick="mudarFiltro('entregue')" data-status="entregue" class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors filter-btn cursor-pointer whitespace-nowrap shrink-0">Concluídos (<span id="count-entregue">0</span>)</button>
                    <button onclick="mudarFiltro('cancelado')" data-status="cancelado" class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors filter-btn cursor-pointer whitespace-nowrap shrink-0">Cancelados (<span id="count-cancelado">0</span>)</button>
                </div>
                
                <div class="relative w-full lg:w-72 shrink-0">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
                    <input type="text" id="inputBusca" oninput="buscarPedidosTempoReal()" placeholder="Buscar pedido ou cliente..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                </div>
            </div>

            <!-- Container Dinâmico dos Pedidos (Com Skeleton Loading Inicial) -->
            <div id="containerPedidos">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 animate-pulse">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
                        <div>
                            <div class="p-4 bg-slate-100 border-b border-slate-200 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="h-4 bg-slate-300 rounded w-16"></div>
                                    <div class="h-5 bg-slate-300 rounded-full w-20"></div>
                                </div>
                                <div class="h-4 bg-slate-300 rounded w-12"></div>
                            </div>
                            <div class="p-4 sm:p-5 space-y-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="space-y-1.5 w-full">
                                        <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                                        <div class="h-3 bg-slate-200 rounded w-1/2"></div>
                                    </div>
                                    <div class="h-6 bg-slate-200 rounded w-16 shrink-0"></div>
                                </div>
                                <div class="border-t border-slate-100 pt-3 space-y-2">
                                    <div class="h-3 bg-slate-200 rounded w-24"></div>
                                    <div class="space-y-2">
                                        <div class="h-3.5 bg-slate-200 rounded w-full"></div>
                                        <div class="h-3.5 bg-slate-200 rounded w-4/5"></div>
                                    </div>
                                </div>
                                <div class="border-t border-slate-100 pt-3 flex justify-between items-center">
                                    <div class="h-3 bg-slate-200 rounded w-20"></div>
                                    <div class="h-4 bg-slate-200 rounded w-16"></div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 bg-slate-50 border-t border-slate-100 flex gap-2">
                            <div class="h-8 bg-slate-200 rounded-xl flex-1"></div>
                            <div class="h-8 bg-slate-200 rounded-xl flex-1"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

        </main>
    </div>

    <!-- Script de Controle -->
    <script>
        function tocarAlertaSonoro() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                function tocarBiPe(tempoInicio, freq) {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'square';
                    osc.frequency.value = freq;
                    gain.gain.setValueAtTime(1.0, ctx.currentTime + tempoInicio);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + tempoInicio + 0.15);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(ctx.currentTime + tempoInicio);
                    osc.stop(ctx.currentTime + tempoInicio + 0.15);
                }
                tocarBiPe(0.0, 783.99);
                tocarBiPe(0.2, 1046.50);
                tocarBiPe(0.4, 783.99);
                tocarBiPe(0.6, 1046.50);
            } catch(e) {}
        }

        let ultimoTotalPendentes = 0;
        let statusAtualFiltro = new URLSearchParams(window.location.search).get('status') || 'todos';

        lucide.createIcons();
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const openBtn = document.getElementById('openSidebarBtn');
        function toggleSidebar() { if(sidebar) sidebar.classList.toggle('-translate-x-full'); if(overlay) overlay.classList.toggle('hidden'); }
        if(openBtn) openBtn.addEventListener('click', toggleSidebar);
        if(overlay) overlay.addEventListener('click', toggleSidebar);

        function renderSkeletonCards() {
            let skeletonHtml = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 animate-pulse">';
            for (let i = 0; i < 4; i++) {
                skeletonHtml += `
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
                    <div>
                        <div class="p-4 bg-slate-100 border-b border-slate-200 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="h-4 bg-slate-300 rounded w-16"></div>
                                <div class="h-5 bg-slate-300 rounded-full w-20"></div>
                            </div>
                            <div class="h-4 bg-slate-300 rounded w-12"></div>
                        </div>
                        <div class="p-4 sm:p-5 space-y-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="space-y-1.5 w-full">
                                    <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                                    <div class="h-3 bg-slate-200 rounded w-1/2"></div>
                                </div>
                                <div class="h-6 bg-slate-200 rounded w-16 shrink-0"></div>
                            </div>
                            <div class="border-t border-slate-100 pt-3 space-y-2">
                                <div class="h-3 bg-slate-200 rounded w-24"></div>
                                <div class="space-y-2">
                                    <div class="h-3.5 bg-slate-200 rounded w-full"></div>
                                    <div class="h-3.5 bg-slate-200 rounded w-4/5"></div>
                                </div>
                            </div>
                            <div class="border-t border-slate-100 pt-3 flex justify-between items-center">
                                <div class="h-3 bg-slate-200 rounded w-20"></div>
                                <div class="h-4 bg-slate-200 rounded w-16"></div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 bg-slate-50 border-t border-slate-100 flex gap-2">
                        <div class="h-8 bg-slate-200 rounded-xl flex-1"></div>
                        <div class="h-8 bg-slate-200 rounded-xl flex-1"></div>
                    </div>
                </div>`;
            }
            skeletonHtml += '</div>';
            return skeletonHtml;
        }

        function mudarFiltro(status) {
            statusAtualFiltro = status;
            window.history.pushState({}, '', `pedidos.php?status=${status}`);
            
            document.querySelectorAll('.filter-btn').forEach(btn => {
                if (btn.getAttribute('data-status') === status) {
                    btn.className = "px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-colors bg-orange-500 text-white shadow-sm shadow-orange-500/20 filter-btn cursor-pointer whitespace-nowrap shrink-0";
                } else {
                    btn.className = "px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors text-slate-600 hover:bg-slate-100 filter-btn cursor-pointer whitespace-nowrap shrink-0";
                }
            });

            document.getElementById('containerPedidos').innerHTML = renderSkeletonCards();
            buscarPedidosTempoReal();
        }

        function atualizarStatus(idPedido, novoStatus) {
            fetch(`pedidos.php?id=${idPedido}&acao=${novoStatus}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'sucesso') {
                        buscarPedidosTempoReal();
                    }
                })
                .catch(error => console.error('Erro ao atualizar status:', error));
        }

        function imprimirPedido(idPedido) {
            window.open(`pedidos.php?acao=imprimir_pdf&id=${idPedido}`, '_blank');
        }

        function salvarTempoAlerta(minutos) {
            let formData = new URLSearchParams();
            formData.append('salvar_tempo_alerta', '1');
            formData.append('tempo_alerta', minutos);

            fetch('pedidos.php', {
                method: 'POST',
                body: formData
            }).then(() => buscarPedidosTempoReal());
        }

        let isFirstLoad = true;

        function buscarPedidosTempoReal() {
            const busca = document.getElementById('inputBusca').value;

            // Se for chamada automática em segundo plano (intervalo), não sobrescreve com skeleton para evitar flash na tela
            if (!isFirstLoad && document.getElementById('gridPedidos') === null) {
                document.getElementById('containerPedidos').innerHTML = renderSkeletonCards();
            }

            fetch(`pedidos.php?ajax=1&status=${statusAtualFiltro}&busca=${encodeURIComponent(busca)}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('containerPedidos').innerHTML = data.html;
                    document.getElementById('txtTotalPendentes').innerText = data.total_pendentes;

                    if (data.totais) {
                        for (const [key, value] of Object.entries(data.totais)) {
                            const badgeCount = document.getElementById(`count-${key}`);
                            if (badgeCount) badgeCount.innerText = value;
                        }
                    }

                    if (data.total_pendentes > ultimoTotalPendentes && ultimoTotalPendentes !== 0) {
                        tocarAlertaSonoro();
                    }
                    ultimoTotalPendentes = data.total_pendentes;
                    isFirstLoad = false;

                    lucide.createIcons();
                })
                .catch(error => console.error('Erro no tempo real:', error));
        }

        mudarFiltro(statusAtualFiltro);
        buscarPedidosTempoReal();
        setInterval(buscarPedidosTempoReal, 4000);
    </script>
</body>
</html>