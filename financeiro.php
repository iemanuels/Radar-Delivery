<?php
// financeiro.php
$currentPage = 'financeiro';

// Define obrigatoriamente o fuso horário para Manaus / Amazonas
date_default_timezone_set('America/Manaus');

// Variável para armazenar possíveis erros de banco de dados
$erroBanco = null;
$faturamentoHoje = 0;
$faturamentoMes = 0;
$taxasMes = 0;
$transacoes = [];

try {
    // Inclui o arquivo de configuração e conexão com o banco de dados
    require_once 'admin/config.php';

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

    // Fallback de segurança
    if ($loja_id <= 0) {
        $loja_id = 1;
    }

    // ==========================================
    // ENDPOINT AJAX PARA ATUALIZAÇÃO EM TEMPO REAL
    // ==========================================
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json; charset=utf-8');
        
        $hojeAjax = date('Y-m-d');
        $inicioMesAjax = date('Y-m-01');
        $fimMesAjax = date('Y-m-t');

        // Métricas
        $stmtFiltroHoje = $pdo->prepare("
            SELECT COALESCE(SUM(total), 0) as faturamento_hoje 
            FROM pedidos 
            WHERE loja_id = ? AND DATE(criado_em) = ? AND status_pedido != 'cancelado'
        ");
        $stmtFiltroHoje->execute([$loja_id, $hojeAjax]);
        $fatHoje = $stmtFiltroHoje->fetch(PDO::FETCH_ASSOC)['faturamento_hoje'] ?? 0;

        $stmtFiltroMes = $pdo->prepare("
            SELECT COALESCE(SUM(total), 0) as faturamento_mes 
            FROM pedidos 
            WHERE loja_id = ? AND DATE(criado_em) BETWEEN ? AND ? AND status_pedido != 'cancelado'
        ");
        $stmtFiltroMes->execute([$loja_id, $inicioMesAjax, $fimMesAjax]);
        $fatMes = $stmtFiltroMes->fetch(PDO::FETCH_ASSOC)['faturamento_mes'] ?? 0;

        $stmtTaxasMes = $pdo->prepare("
            SELECT COALESCE(SUM(taxa_entrega), 0) as total_taxas 
            FROM pedidos 
            WHERE loja_id = ? AND DATE(criado_em) BETWEEN ? AND ? AND status_pedido != 'cancelado'
        ");
        $stmtTaxasMes->execute([$loja_id, $inicioMesAjax, $fimMesAjax]);
        $taxasM = $stmtTaxasMes->fetch(PDO::FETCH_ASSOC)['total_taxas'] ?? 0;

        // Transações
        $stmtTransacoes = $pdo->prepare("
            SELECT t.*, c.nome as nome_cliente 
            FROM transacoes t
            LEFT JOIN pedidos p ON t.pedido_id = p.id
            LEFT JOIN clientes c ON p.cliente_id = c.id
            WHERE t.loja_id = ?
            ORDER BY t.criado_em DESC
            LIMIT 10
        ");
        $stmtTransacoes->execute([$loja_id]);
        $listaTransacoes = $stmtTransacoes->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'faturamento_hoje' => floatval($fatHoje),
            'faturamento_mes' => floatval($fatMes),
            'taxas_mes' => floatval($taxasM),
            'transacoes' => $listaTransacoes
        ]);
        exit;
    }

    // Datas de referência para carregamento padrão
    $hoje = date('Y-m-d');
    $inicioMes = date('Y-m-01');
    $fimMes = date('Y-m-t');

    // 1. Métricas Financeiras Reais da Loja
    $stmtFiltroHoje = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as faturamento_hoje 
        FROM pedidos 
        WHERE loja_id = ? AND DATE(criado_em) = ? AND status_pedido != 'cancelado'
    ");
    $stmtFiltroHoje->execute([$loja_id, $hoje]);
    $faturamentoHoje = $stmtFiltroHoje->fetch(PDO::FETCH_ASSOC)['faturamento_hoje'] ?? 0;

    $stmtFiltroMes = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as faturamento_mes 
        FROM pedidos 
        WHERE loja_id = ? AND DATE(criado_em) BETWEEN ? AND ? AND status_pedido != 'cancelado'
    ");
    $stmtFiltroMes->execute([$loja_id, $inicioMes, $fimMes]);
    $faturamentoMes = $stmtFiltroMes->fetch(PDO::FETCH_ASSOC)['faturamento_mes'] ?? 0;

    $stmtTaxasMes = $pdo->prepare("
        SELECT COALESCE(SUM(taxa_entrega), 0) as total_taxas 
        FROM pedidos 
        WHERE loja_id = ? AND DATE(criado_em) BETWEEN ? AND ? AND status_pedido != 'cancelado'
    ");
    $stmtTaxasMes->execute([$loja_id, $inicioMes, $fimMes]);
    $taxasMes = $stmtTaxasMes->fetch(PDO::FETCH_ASSOC)['total_taxas'] ?? 0;

    // 2. Transações Recentes da Loja
    $stmtTransacoes = $pdo->prepare("
        SELECT t.*, c.nome as nome_cliente 
        FROM transacoes t
        LEFT JOIN pedidos p ON t.pedido_id = p.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE t.loja_id = ?
        ORDER BY t.criado_em DESC
        LIMIT 10
    ");
    $stmtTransacoes->execute([$loja_id]);
    $transacoes = $stmtTransacoes->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $erroBanco = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro | MercadoSaaS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
    <!-- Ícone da Loja Dinâmico -->
    <link rel="icon" type="image/png" href="icon.php">
    <link rel="apple-touch-icon" href="icon.php">
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex">

    <!-- Menu Lateral -->
    <?php include 'includes/menu.php'; ?>

    <!-- Conteúdo Principal -->
    <div class="flex-1 flex flex-col md:pl-64 min-w-0">
        
        <!-- Topbar -->
        <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 px-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button id="openSidebarBtn" class="md:hidden text-slate-500 hover:text-slate-700 p-1.5 rounded-lg border border-slate-200 bg-white">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <h1 class="text-lg font-bold text-slate-800">Controle Financeiro</h1>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="abrirModalRelatorio()" class="bg-orange-500 hover:bg-orange-600 text-white font-medium px-4 py-2 rounded-xl text-sm transition-colors shadow-sm shadow-orange-500/20 flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    Exportar Relatório
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 p-6 space-y-6 max-w-7xl w-full mx-auto">
            
            <!-- IDENTIFICADOR DE ERROS (Card organizado caso ocorra falha no banco) -->
            <?php if ($erroBanco): ?>
                <div class="bg-rose-50 border border-rose-200 rounded-2xl p-5 shadow-sm space-y-2">
                    <div class="flex items-center gap-3 text-rose-700 font-bold">
                        <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0"></i>
                        <span>Erro ao carregar os dados do banco de dados</span>
                    </div>
                    <p class="text-xs text-rose-600 font-mono bg-white/60 p-3 rounded-xl border border-rose-100">
                        <?php echo htmlspecialchars($erroBanco); ?>
                    </p>
                    <p class="text-xs text-slate-500">Verifique se as tabelas necessárias existem no banco de dados e se o arquivo de configuração está correto.</p>
                </div>
            <?php endif; ?>

            <!-- Cards de Resumo Financeiro -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Faturamento Hoje</span>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <i data-lucide="trending-up" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p id="cardFaturamentoHoje" class="text-2xl font-bold text-slate-800 mt-4">R$ <?php echo number_format($faturamentoHoje, 2, ',', '.'); ?></p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Faturamento do Mês</span>
                        <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                            <i data-lucide="wallet" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p id="cardFaturamentoMes" class="text-2xl font-bold text-slate-800 mt-4">R$ <?php echo number_format($faturamentoMes, 2, ',', '.'); ?></p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Taxas de Entrega</span>
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i data-lucide="bike" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p id="cardTaxasMes" class="text-2xl font-bold text-slate-800 mt-4">R$ <?php echo number_format($taxasMes, 2, ',', '.'); ?></p>
                </div>
            </div>

            <!-- Histórico de Transações (Com Skeleton Loading Inicial) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-800">Transações Recentes</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Entradas via gateway de pagamento e caixa direto</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                                <th class="py-3 px-6">ID / Referência</th>
                                <th class="py-3 px-6">Cliente</th>
                                <th class="py-3 px-6">Forma de Pagamento</th>
                                <th class="py-3 px-6">Valor</th>
                                <th class="py-3 px-6">Status</th>
                                <th class="py-3 px-6 text-right">Data / Hora</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaTransacoesBody" class="divide-y divide-slate-100 text-slate-600">
                            <!-- Skeleton State Inicial -->
                            <tr class="animate-pulse">
                                <td class="py-4 px-6"><div class="w-20 h-4 bg-slate-200 rounded"></div></td>
                                <td class="py-4 px-6"><div class="w-32 h-4 bg-slate-200 rounded"></div></td>
                                <td class="py-4 px-6"><div class="w-24 h-4 bg-slate-200 rounded"></div></td>
                                <td class="py-4 px-6"><div class="w-20 h-4 bg-slate-200 rounded"></div></td>
                                <td class="py-4 px-6"><div class="w-16 h-6 bg-slate-200 rounded-full"></div></td>
                                <td class="py-4 px-6 text-right"><div class="w-24 h-4 bg-slate-200 rounded ml-auto"></div></td>
                            </tr>
                            <tr class="animate-pulse">
                                <td class="py-4 px-6"><div class="w-20 h-4 bg-slate-200 rounded"></div></td>
                                <td class="py-4 px-6"><div class="w-28 h-4 bg-slate-200 rounded"></div></td>
                                <td class="py-4 px-6"><div class="w-20 h-4 bg-slate-200 rounded"></div></td>
                                <td class="py-4 px-6"><div class="w-20 h-4 bg-slate-200 rounded"></div></td>
                                <td class="py-4 px-6"><div class="w-16 h-6 bg-slate-200 rounded-full"></div></td>
                                <td class="py-4 px-6 text-right"><div class="w-24 h-4 bg-slate-200 rounded ml-auto"></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- MODAL DE EXPORTAR RELATÓRIO -->
    <div id="modalRelatorio" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] flex flex-col shadow-xl border border-slate-100 my-auto overflow-hidden">
            
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-white shrink-0">
                <h3 class="text-base font-bold text-slate-800">Exportar Relatório Financeiro</h3>
                <button onclick="fecharModalRelatorio()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1 space-y-4">
                <form id="formRelatorio" action="#" method="GET" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Período do Relatório</label>
                        <select name="periodo" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                            <option value="hoje">Hoje</option>
                            <option value="mes_atual" selected>Mês Atual</option>
                            <option value="personalizado">Personalizado</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Data Inicial</label>
                            <input type="date" name="data_inicio" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Data Final</label>
                            <input type="date" name="data_fim" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Formato do Arquivo</label>
                        <select name="formato" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                            <option value="pdf">Documento PDF</option>
                            <option value="csv">Planilha CSV (Excel)</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-white shrink-0">
                <button type="button" onclick="fecharModalRelatorio()" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Cancelar</button>
                <button type="button" onclick="gerarRelatorio()" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-2 rounded-xl text-sm transition-colors shadow-sm shadow-orange-500/25">Gerar Relatório</button>
            </div>
        </div>
    </div>

    <!-- Script de Controle -->
    <script>
        lucide.createIcons();

        // Menu Mobile
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const openBtn = document.getElementById('openSidebarBtn');
        function toggleSidebar() { sidebar.classList.toggle('-translate-x-full'); if(overlay) overlay.classList.toggle('hidden'); }
        if(openBtn) openBtn.addEventListener('click', toggleSidebar);
        if(overlay) overlay.addEventListener('click', toggleSidebar);

        // Função de Atualização em Tempo Real via AJAX
        function carregarDadosFinanceiros() {
            fetch('financeiro.php?ajax=1')
                .then(response => response.json())
                .then(data => {
                    // Atualiza Cards de Resumo
                    document.getElementById('cardFaturamentoHoje').innerText = 'R$ ' + Number(data.faturamento_hoje).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    document.getElementById('cardFaturamentoMes').innerText = 'R$ ' + Number(data.faturamento_mes).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    document.getElementById('cardTaxasMes').innerText = 'R$ ' + Number(data.taxas_mes).toLocaleString('pt-BR', {minimumFractionDigits: 2});

                    // Atualiza Tabela de Transações
                    let tbodyHtml = '';
                    if (data.transacoes && data.transacoes.length > 0) {
                        data.transacoes.forEach(trx => {
                            let dotColor = 'bg-amber-500';
                            if (trx.forma_pagamento == 'pix') dotColor = 'bg-emerald-500';
                            else if (trx.forma_pagamento == 'cartao') dotColor = 'bg-blue-500';

                            let isEntrada = trx.tipo == 'entrada';
                            let valorClass = isEntrada ? 'text-emerald-600' : 'text-rose-600';
                            let valorSinal = isEntrada ? '+ ' : '- ';
                            let valorFormatado = Number(trx.valor).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                            
                            let dataFormatada = '';
                            if (trx.criado_em) {
                                let dt = new Date(trx.criado_em);
                                dataFormatada = dt.toLocaleDateString('pt-BR') + ' ' + dt.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
                            }

                            tbodyHtml += `
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-4 px-6 font-semibold text-slate-800">#TRX-${trx.id}</td>
                                    <td class="py-4 px-6">${escapeHtml(trx.nome_cliente || 'Cliente Geral')}</td>
                                    <td class="py-4 px-6 flex items-center gap-2 capitalize">
                                        <span class="w-2 h-2 rounded-full ${dotColor}"></span> 
                                        ${escapeHtml(trx.forma_pagamento)}
                                    </td>
                                    <td class="py-4 px-6 font-semibold ${valorClass}">
                                        ${valorSinal}R$ ${valorFormatado}
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-semibold capitalize">
                                            ${escapeHtml(trx.status)}
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-right text-slate-400 text-xs">
                                        ${dataFormatada}
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        tbodyHtml = `
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">Nenhuma transação registrada no período.</td>
                            </tr>
                        `;
                    }

                    document.getElementById('tabelaTransacoesBody').innerHTML = tbodyHtml;
                    lucide.createIcons();
                })
                .catch(err => console.error('Erro ao atualizar dados financeiros:', err));
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // Executa carga inicial e agenda polling a cada 5 segundos
        document.addEventListener("DOMContentLoaded", () => {
            carregarDadosFinanceiros();
            setInterval(carregarDadosFinanceiros, 5000);
        });

        // Modal de Relatório
        const modalRelatorio = document.getElementById('modalRelatorio');

        function abrirModalRelatorio() {
            modalRelatorio.classList.remove('hidden');
        }

        function fecharModalRelatorio() {
            modalRelatorio.classList.add('hidden');
        }

        function gerarRelatorio() {
            fecharModalRelatorio();
        }
    </script>
</body>
</html>