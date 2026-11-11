<?php
// dashboard.php
$currentPage = 'dashboard';

// Define obrigatoriamente o fuso horário para Manaus / Amazonas
date_default_timezone_set('America/Manaus');

// Inclui o arquivo de configuração e conexão com o banco de dados
require_once 'admin/config.php';

// Garante que o MySQL utilize o fuso horário de Manaus (-04:00)
try {
    $pdo->exec("SET time_zone = '-04:00'");
} catch (Exception $e) {}

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

// Fallback de segurança caso o loja_id venha zerado
if ($loja_id <= 0) {
    $loja_id = 1;
}

// Salva na sessão global para que as APIs auxiliares saibam qual loja consultar
$_SESSION['current_loja_id'] = $loja_id;

// ==========================================
// BUSCA DE DADOS DA LOJA
// ==========================================
$stmtLoja = $pdo->prepare("
    SELECT l.*, p.nome_plano 
    FROM lojas l 
    LEFT JOIN planos p ON l.plano_id = p.id 
    WHERE l.id = ?
");
$stmtLoja->execute([$loja_id]);
$loja = $stmtLoja->fetch(PDO::FETCH_ASSOC);

$nomeLoja = $loja ? $loja['nome_loja'] : 'Minha Loja';
$statusLoja = $loja ? $loja['status_loja'] : 'ativo';
$nomePlano = $loja['nome_plano'] ?? 'Teste / Gratuito';
$dataVencimento = $loja['data_vencimento'] ?? null;

// Slug limpo e garantindo minúsculas sem perder os caracteres e acentuações convertidas
$slugLojaUrl = strtolower(trim(preg_replace('/[^a-z0-9]+/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nomeLoja))));
if (empty($slugLojaUrl)) {
    $slugLojaUrl = 'loja' . $loja_id;
}

// Verificar se a loja está aberta considerando o horário de Manaus e status
$lojaAberta = true;
$motivoFechamento = "";

if ($loja) {
    if ($statusLoja !== 'ativo') {
        $lojaAberta = false;
        $motivoFechamento = "Loja Inativa / Suspensa no Sistema";
    } elseif ($loja['forcar_fechado'] == 1) {
        $lojaAberta = false;
        $motivoFechamento = "Fechada manualmente pelo lojista";
    } else {
        $horaAtual = date('H:i:s');
        if ($horaAtual < $loja['horario_abertura'] || $horaAtual > $loja['horario_fechamento']) {
            $lojaAberta = false;
            $motivoFechamento = "Fora do horário de expediente (" . date('H:i', strtotime($loja['horario_abertura'])) . " às " . date('H:i', strtotime($loja['horario_fechamento'])) . ")";
        }
    }
}

// ==========================================
// MÉTRICAS E PEDIDOS DA LOJA ESPECÍFICA
// ==========================================
$hoje = date('Y-m-d');
$stmtMetricas = $pdo->prepare("
    SELECT 
        COALESCE(SUM(total), 0) as total_vendas, 
        COUNT(id) as total_pedidos,
        COALESCE(AVG(total), 0) as ticket_medio
    FROM pedidos 
    WHERE loja_id = ? AND DATE(criado_em) = ? AND status_pedido != 'cancelado'
");
$stmtMetricas->execute([$loja_id, $hoje]);
$metricas = $stmtMetricas->fetch(PDO::FETCH_ASSOC);

$vendasHoje = $metricas['total_vendas'];
$pedidosHoje = $metricas['total_pedidos'];
$ticketMedio = $metricas['ticket_medio'];

// Entregadores em Rota / Ativos da Loja
$stmtEntregadores = $pdo->prepare("
    SELECT COUNT(id) as em_rota 
    FROM entregadores 
    WHERE loja_id = ? AND status_entregador = 'em_rota'
");
$stmtEntregadores->execute([$loja_id]);
$entregadoresData = $stmtEntregadores->fetch(PDO::FETCH_ASSOC);
$entregadoresEmRota = $entregadoresData ? $entregadoresData['em_rota'] : 0;

// Listar Últimos Pedidos da Loja com dados do cliente
$stmtPedidos = $pdo->prepare("
    SELECT p.*, c.nome as nome_cliente 
    FROM pedidos p
    INNER JOIN clientes c ON p.cliente_id = c.id
    WHERE p.loja_id = ?
    ORDER BY p.criado_em DESC
    LIMIT 5
");
$stmtPedidos->execute([$loja_id]);
$ultimosPedidos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

// Contagem inicial de pedidos pendentes da loja
$stmtNaoLidasInit = $pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE loja_id = ? AND status_pedido = 'pendente'");
$stmtNaoLidasInit->execute([$loja_id]);
$totalNaoLidasInit = $stmtNaoLidasInit->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard | <?php echo htmlspecialchars($nomeLoja); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
    <!-- Ícone da Loja Dinâmico -->
    <link rel="icon" type="image/png" href="icon.php">
    <link rel="apple-touch-icon" href="icon.php">
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Wrapper Geral para empilhar caso haja Modo Master -->
    <div class="min-h-screen flex flex-col md:flex-row w-full relative">

        <!-- Menu Lateral Unificado -->
        <?php include 'includes/menu.php'; ?>

        <!-- Conteúdo Principal -->
        <div class="flex-1 flex flex-col md:pl-64 min-w-0">
            
            <!-- Aviso Superior se Acesso Master Ativo -->
            <?php if ($isMasterAccess): ?>
            <div class="bg-slate-900 text-white px-4 py-2.5 flex items-center justify-between text-xs shadow-md z-20">
                <div class="flex items-center gap-2 max-w-7xl mx-auto w-full flex-wrap sm:flex-nowrap">
                    <span class="bg-orange-600 text-white font-bold px-2 py-0.5 rounded text-[10px] uppercase tracking-wider shrink-0">Modo Master</span>
                    <span class="truncate">Você está acessando como <strong>Administrador Global</strong> visualizando a loja: <strong><?php echo htmlspecialchars($nomeLoja); ?></strong> (ID: #<?php echo $loja_id; ?>)</span>
                    <a href="admin/lojas.php" class="ml-auto bg-slate-800 hover:bg-slate-700 text-orange-400 px-3 py-1 rounded-lg font-semibold transition-colors border border-slate-700 flex items-center gap-1 shrink-0">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar ao Painel Master
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Topbar Superior -->
            <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 px-4 sm:px-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button id="openSidebarBtn" class="md:hidden text-slate-500 hover:text-slate-700 p-2 rounded-xl border border-slate-200 bg-white">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    <h1 class="text-base sm:text-lg font-bold text-slate-800">Visão Geral</h1>
                </div>

                <!-- Status da Loja na Topbar & Botão Sutil de Compartilhar -->
                <div class="flex items-center gap-3 relative">
                    
                    <!-- BOTÃO SUTIL DE COMPARTILHAR LOJA -->
                    <button type="button" onclick="gerarCompartilharLoja()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 border border-slate-200">
                        <i data-lucide="share-2" class="w-3.5 h-3.5 text-orange-500"></i>
                        <span>Compartilhar Loja</span>
                    </button>

                    <?php if ($statusLoja !== 'ativo'): ?>
                        <div class="hidden sm:flex items-center gap-2 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-full">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            <span class="text-xs font-semibold text-rose-700">Loja Inativa / Suspensa</span>
                        </div>
                    <?php elseif ($lojaAberta): ?>
                        <div class="hidden sm:flex items-center gap-2 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-full">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-semibold text-emerald-700">Loja Aberta</span>
                        </div>
                    <?php else: ?>
                        <div class="hidden sm:flex items-center gap-2 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-full" title="<?php echo htmlspecialchars($motivoFechamento); ?>">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span class="text-xs font-semibold text-amber-700">Fechada</span>
                        </div>
                    <?php endif; ?>

                    <!-- Notificações -->
                    <div class="relative">
                        <button id="btnNotificacoes" onclick="toggleDropdownNotificacoes()" class="w-10 h-10 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:bg-slate-50 relative transition-colors focus:outline-none">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                            <span id="badgeNotificacao" class="absolute top-2 right-2 w-2.5 h-2.5 bg-orange-500 rounded-full ring-2 ring-white <?php echo $totalNaoLidasInit > 0 ? '' : 'hidden'; ?>"></span>
                        </button>

                        <div id="dropdownNotificacoes" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-xl border border-slate-100 z-50 overflow-hidden transform transition-all">
                            <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="bell" class="w-4 h-4 text-orange-500"></i>
                                    <h3 class="font-bold text-slate-800 text-sm">Notificações</h3>
                                </div>
                                <span class="text-[10px] font-semibold bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full">Atividade Recente</span>
                            </div>
                            <div id="listaNotificacoes" class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                                <div class="p-6 text-center text-slate-400 text-xs">Carregando notificações...</div>
                            </div>
                            <div class="p-3 border-t border-slate-100 text-center bg-slate-50/50">
                                <a href="pedidos.php" class="text-xs font-semibold text-orange-600 hover:text-orange-700">Ver todos os pedidos</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ================= SKELETON LOADER (ESTADO DE CARREGAMENTO) ================= -->
            <div id="skeleton-loader" class="flex-1 p-3 sm:p-6 space-y-6 max-w-7xl w-full mx-auto animate-pulse">
                <!-- Boas-vindas Skeleton -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="space-y-2">
                        <div class="h-6 bg-slate-200 rounded-lg w-48"></div>
                        <div class="h-4 bg-slate-100 rounded-lg w-32"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-7 bg-slate-200 rounded-xl w-28"></div>
                        <div class="h-7 bg-slate-200 rounded-xl w-32"></div>
                    </div>
                </div>

                <!-- Cards de Métricas Skeleton -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php for($i=0; $i<4; $i++): ?>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="h-3 bg-slate-200 rounded w-20"></div>
                            <div class="w-10 h-10 rounded-xl bg-slate-100"></div>
                        </div>
                        <div class="h-7 bg-slate-200 rounded w-28"></div>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- Tabela Skeleton -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1.5">
                            <div class="h-5 bg-slate-200 rounded w-32"></div>
                            <div class="h-3 bg-slate-100 rounded w-48"></div>
                        </div>
                        <div class="h-4 bg-slate-200 rounded w-16"></div>
                    </div>
                    <div class="space-y-3 pt-2">
                        <?php for($i=0; $i<5; $i++): ?>
                        <div class="h-12 bg-slate-100 rounded-xl w-full"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Corpo Principal (Conteúdo Real Oculto Inicialmente) -->
            <main id="main-content" class="flex-1 p-3 sm:p-6 space-y-6 max-w-7xl w-full mx-auto hidden">
                
                <!-- Boas-vindas e Status Detalhado -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">Olá, <?php echo htmlspecialchars($nomeLoja); ?></h2>
                        <p class="text-sm text-slate-500 mt-0.5">Visão operacional da loja em Manaus.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-xl text-xs font-semibold border border-slate-200">
                            Plano: <?php echo htmlspecialchars($nomePlano); ?>
                        </span>
                        <span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-xl text-xs font-semibold border border-slate-200">
                            Vencimento: <?php echo !empty($dataVencimento) ? date('d/m/Y', strtotime($dataVencimento)) : 'Vitalício'; ?>
                        </span>
                    </div>
                </div>

                <!-- Cards de Métricas -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Vendas Hoje</span>
                            <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                                <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-slate-800">R$ <?php echo number_format($vendasHoje, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pedidos Hoje</span>
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-slate-800"><?php echo $pedidosHoje; ?></span>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Ticket Médio</span>
                            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                                <i data-lucide="trending-up" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-slate-800">R$ <?php echo number_format($ticketMedio, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Em Rota de Entrega</span>
                            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <i data-lucide="bike" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-slate-800"><?php echo $entregadoresEmRota; ?></span>
                            <span class="text-xs font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Ativo</span>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Últimos Pedidos -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-800">Últimos Pedidos</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Pedidos recebidos em tempo real na plataforma</p>
                        </div>
                        <a href="pedidos.php" class="text-sm font-semibold text-orange-600 hover:text-orange-700">Ver todos</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                                    <th class="py-3 px-6">Pedido</th>
                                    <th class="py-3 px-6">Cliente</th>
                                    <th class="py-3 px-6">Forma Pagto</th>
                                    <th class="py-3 px-6">Total</th>
                                    <th class="py-3 px-6">Status</th>
                                    <th class="py-3 px-6 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-600">
                                <?php if (count($ultimosPedidos) > 0): ?>
                                    <?php foreach ($ultimosPedidos as $pedido): ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="py-4 px-6 font-semibold text-slate-800">#<?php echo htmlspecialchars($pedido['codigo_pedido']); ?></td>
                                            <td class="py-4 px-6"><?php echo htmlspecialchars($pedido['nome_cliente']); ?></td>
                                            <td class="py-4 px-6 text-slate-500 uppercase"><?php echo htmlspecialchars($pedido['forma_pagamento']); ?></td>
                                            <td class="py-4 px-6 font-medium text-slate-800">R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></td>
                                            <td class="py-4 px-6">
                                                <?php 
                                                    $status = $pedido['status_pedido'];
                                                    $badgeClass = "bg-slate-50 text-slate-700 border-slate-200";
                                                    $dotClass = "bg-slate-500";
                                                    $labelStatus = ucfirst($status);

                                                    if ($status == 'preparando') {
                                                        $badgeClass = "bg-amber-50 text-amber-700 border-amber-200"; $dotClass = "bg-amber-500"; $labelStatus = "Preparando";
                                                    } elseif ($status == 'em_rota') {
                                                        $badgeClass = "bg-blue-50 text-blue-700 border-blue-200"; $dotClass = "bg-blue-500"; $labelStatus = "Em Rota";
                                                    } elseif ($status == 'entregue') {
                                                        $badgeClass = "bg-emerald-50 text-emerald-700 border-emerald-200"; $dotClass = "bg-emerald-500"; $labelStatus = "Entregue";
                                                    } elseif ($status == 'pendente') {
                                                        $badgeClass = "bg-orange-50 text-orange-700 border-orange-200"; $dotClass = "bg-orange-500"; $labelStatus = "Pendente";
                                                    } elseif ($status == 'cancelado') {
                                                        $badgeClass = "bg-rose-50 text-rose-700 border-rose-200"; $dotClass = "bg-rose-500"; $labelStatus = "Cancelado";
                                                    }
                                                ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border <?php echo $badgeClass; ?>">
                                                    <span class="w-1.5 h-1.5 rounded-full <?php echo $dotClass; ?>"></span>
                                                    <?php echo $labelStatus; ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6 text-right">
                                                <a href="pedidos.php" class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors inline-block" title="Ver Pedidos">
                                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-slate-400">Nenhum pedido recente encontrado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Script de Notificações, Compartilhamento e Skeleton Loader -->
    <script>
        lucide.createIcons();

        // Simula transição suave do Skeleton para o Conteúdo real assim que a página carregar
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const skeleton = document.getElementById('skeleton-loader');
                const mainContent = document.getElementById('main-content');
                if (skeleton && mainContent) {
                    skeleton.style.display = 'none';
                    mainContent.classList.remove('hidden');
                    lucide.createIcons(); // Recria os ícones dentro do conteúdo principal revelado
                }
            }, 350); // Pequeno atraso elegante de 350ms
        });

        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const openBtn = document.getElementById('openSidebarBtn');
        const closeBtn = document.getElementById('closeSidebarBtn');

        if (openBtn && sidebar) {
            function toggleSidebar() {
                sidebar.classList.toggle('-translate-x-full');
                if (overlay) overlay.classList.toggle('hidden');
            }
            openBtn.addEventListener('click', toggleSidebar);
            if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', toggleSidebar);
            if (overlay) overlay.addEventListener('click', toggleSidebar);
        }

        function gerarCompartilharLoja() {
            fetch('criar_pasta_loja.php')
            .then(res => res.json())
            .then(data => {
                if (data.sucesso) {
                    window.open(data.url_loja, '_blank');
                } else {
                    alert('Erro ao configurar pasta da loja: ' + data.mensagem);
                }
            })
            .catch(err => {
                console.error('Erro:', err);
                window.open('http://deliveryam.rf.gd/<?php echo htmlspecialchars($slugLojaUrl); ?>', '_blank');
            });
        }

        let dropdownAberto = false;
        function toggleDropdownNotificacoes() {
            const dropdown = document.getElementById('dropdownNotificacoes');
            dropdownAberto = !dropdownAberto;
            if (dropdownAberto) {
                dropdown.classList.remove('hidden');
                carregarNotificacoes(true);
            } else {
                dropdown.classList.add('hidden');
            }
        }

        window.addEventListener('click', function(e) {
            const container = document.getElementById('btnNotificacoes');
            const dropdown = document.getElementById('dropdownNotificacoes');
            if (container && dropdown && !container.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
                dropdownAberto = false;
            }
        });

        function carregarNotificacoes(marcarVisto = false) {
            fetch('notificacoes_api.php')
                .then(res => res.json())
                .then(data => {
                    if (!data.sucesso) return;
                    const badge = document.getElementById('badgeNotificacao');
                    const lista = document.getElementById('listaNotificacoes');

                    if (data.total_nao_lidas > 0 && !marcarVisto) {
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }

                    if (marcarVisto && data.total_nao_lidas > 0) {
                        let formData = new URLSearchParams();
                        formData.append('marcar_vistas', '1');
                        fetch('notificacoes_api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: formData.toString()
                        }).then(() => badge.classList.add('hidden'));
                    }

                    if (data.notificacoes && data.notificacoes.length > 0) {
                        let html = '';
                        data.notificacoes.forEach(n => {
                            let icone = 'info';
                            let corIcone = 'bg-slate-50 text-slate-600';
                            if (n.tipo === 'novo_pedido') { icone = 'shopping-bag'; corIcone = 'bg-orange-50 text-orange-600'; }
                            else if (n.tipo === 'em_rota') { icone = 'bike'; corIcone = 'bg-blue-50 text-blue-600'; }
                            
                            html += `
                                <div class="p-3.5 hover:bg-slate-50 transition-colors flex items-start gap-3 text-xs">
                                    <div class="w-8 h-8 rounded-xl ${corIcone} flex items-center justify-center shrink-0 mt-0.5">
                                        <i data-lucide="${icone}" class="w-4 h-4"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-slate-700 leading-snug">${n.mensagem}</p>
                                        <span class="text-[10px] text-slate-400 mt-1 block">${n.criado_em}</span>
                                    </div>
                                </div>
                            `;
                        });
                        lista.innerHTML = html;
                        lucide.createIcons();
                    } else {
                        lista.innerHTML = `<div class="p-6 text-center text-slate-400 text-xs">Nenhuma notificação recente.</div>`;
                    }
                });
        }
    </script>
</body>
</html>