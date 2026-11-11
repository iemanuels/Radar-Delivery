<?php
// includes/menu.php
$currentPage = $currentPage ?? 'dashboard';

// Garante conexão e fuso horário se já não estiverem definidos na página principal
if (!isset($pdo)) {
    require_once __DIR__ . '/../admin/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// DEFINIÇÃO DINÂMICA DO LOJA_ID
// ==========================================
$loja_id = 0;

if (isset($_GET['loja_id']) && !empty($_GET['loja_id']) && isset($_GET['token'])) {
    $loja_id = intval($_GET['loja_id']);
    $_SESSION['master_view_loja_id'] = $loja_id;
    $_SESSION['master_token'] = $_GET['token'];
} elseif (isset($_SESSION['master_view_loja_id']) && isset($_SESSION['master_token'])) {
    $loja_id = intval($_SESSION['master_view_loja_id']);
} else {
    if (isset($_SESSION['loja_id']) && !empty($_SESSION['loja_id'])) {
        $loja_id = intval($_SESSION['loja_id']);
    } elseif (isset($_SESSION['admin_id'])) {
        try {
            $stmtAdminMenu = $pdo->prepare("SELECT loja_id, tipo FROM admins WHERE id = ? LIMIT 1");
            $stmtAdminMenu->execute([$_SESSION['admin_id']]);
            $dadosAdminMenu = $stmtAdminMenu->fetch(PDO::FETCH_ASSOC);
            
            if ($dadosAdminMenu) {
                $loja_id = intval($dadosAdminMenu['loja_id'] ?? 0);
                if ($dadosAdminMenu['tipo'] === 'master' && $loja_id === 0) {
                    $stmtPrimeiraLoja = $pdo->query("SELECT id FROM lojas ORDER BY id ASC LIMIT 1");
                    $loja_id = intval($stmtPrimeiraLoja->fetchColumn() ?: 1);
                }
            }
        } catch (Exception $e) {}
    }
}

// Fallback de segurança caso venha zerado
if ($loja_id <= 0) {
    $loja_id = 1;
}

// Buscar dados dinâmicos da loja e o plano associado
try {
    $stmtLojaMenu = $pdo->prepare("
        SELECT l.nome_loja, p.nome_plano 
        FROM lojas l 
        LEFT JOIN planos p ON l.plano_id = p.id 
        WHERE l.id = ?
    ");
    $stmtLojaMenu->execute([$loja_id]);
    $dadosLojaMenu = $stmtLojaMenu->fetch(PDO::FETCH_ASSOC);

    $nomeLojaMenu = $dadosLojaMenu['nome_loja'] ?? 'MercadoSaaS';
    $nomePlanoMenu = $dadosLojaMenu['nome_plano'] ?? 'Painel do Lojista';

    // Contar pedidos pendentes em tempo real para exibir no badge do menu
    $stmtPedidosMenu = $pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE loja_id = ? AND status_pedido = 'pendente'");
    $stmtPedidosMenu->execute([$loja_id]);
    $totalPendentesMenu = $stmtPedidosMenu->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Gerar iniciais para o avatar da loja dinamicamente
    $partesLoja = explode(' ', trim($nomeLojaMenu));
    $iniciaisLoja = mb_strtoupper(mb_substr($partesLoja[0], 0, 1));
    if (count($partesLoja) > 1) {
        $iniciaisLoja .= mb_strtoupper(mb_substr(end($partesLoja), 0, 1));
    }
} catch (Exception $e) {
    $nomeLojaMenu = 'MercadoSaaS';
    $nomePlanoMenu = 'Painel do Lojista';
    $totalPendentesMenu = 0;
    $iniciaisLoja = 'MS';
}
?>

<!-- Overlay para mobile quando o menu estiver aberto -->
<div id="sidebarOverlay" class="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm hidden md:hidden transition-opacity"></div>

<!-- Sidebar / Menu Lateral -->
<aside id="appSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-200 flex flex-col transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0 shadow-sm">
    
    <!-- Logo / Topo do Menu -->
    <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-orange-500 flex items-center justify-center text-white shadow-md shadow-orange-500/20">
                <i data-lucide="store" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="font-bold text-slate-800 text-lg leading-tight block">MercadoSaaS</span>
                <span class="text-xs text-slate-400 font-medium">Painel do Lojista</span>
            </div>
        </div>
        <!-- Botão fechar menu no mobile -->
        <button id="closeSidebarBtn" class="md:hidden text-slate-400 hover:text-slate-600 p-1 rounded-lg">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <!-- Links de Navegação -->
    <div class="flex-1 overflow-y-auto px-4 py-6 space-y-6">
        
        <!-- Principal -->
        <div>
            <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Geral</p>
            <nav class="space-y-1">
                <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentPage === 'dashboard' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    Dashboard
                </a>
                <a href="pedidos.php" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentPage === 'pedidos' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                        Pedidos
                    </div>
                    <!-- Badge Dinâmico com ID para atualização em tempo real -->
                    <span id="badgeMenuPendentes" class="bg-orange-500 text-white text-xs px-2.5 py-0.5 rounded-full font-bold <?= $totalPendentesMenu > 0 ? '' : 'hidden' ?>"><?= $totalPendentesMenu; ?></span>
                </a>
                <a href="produtos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentPage === 'produtos' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <i data-lucide="package" class="w-5 h-5"></i>
                    Produtos & Estoque
                </a>
            </nav>
        </div>

        <!-- Gestão -->
        <div>
            <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Operacional</p>
            <nav class="space-y-1">
                <a href="clientes.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentPage === 'clientes' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <i data-lucide="users" class="w-5 h-5"></i>
                    Clientes
                </a>
                <a href="entregadores.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentPage === 'entregas' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <i data-lucide="bike" class="w-5 h-5"></i>
                    Entregadores
                </a>
                <a href="financeiro.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentPage === 'financeiro' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <i data-lucide="wallet" class="w-5 h-5"></i>
                    Financeiro
                </a>
            </nav>
        </div>

        <!-- Configurações -->
        <div>
            <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Sistema</p>
            <nav class="space-y-1">
                <a href="configuracoes.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentPage === 'configuracoes' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <i data-lucide="settings" class="w-5 h-5"></i>
                    Configurações da Loja
                </a>
            </nav>
        </div>

    </div>

    <!-- Rodapé do Menu (Perfil / Sair com dados do DB) -->
    <div class="p-4 border-t border-slate-100">
        <div class="flex items-center gap-3 p-2 rounded-xl bg-slate-50">
            <div class="w-9 h-9 rounded-lg bg-orange-100 text-orange-600 font-bold flex items-center justify-center text-sm shrink-0">
                <?= htmlspecialchars($iniciaisLoja); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($nomeLojaMenu); ?></p>
                <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars($nomePlanoMenu); ?></p>
            </div>
            <a href="admin/logout.php" class="text-slate-400 hover:text-rose-600 transition-colors p-1" title="Sair">
                <i data-lucide="log-out" class="w-5 h-5"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Script para atualizar o badge do menu em tempo real -->
<script>
    function atualizarContadorMenu() {
        fetch('includes/menu-api.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'sucesso') {
                    const badge = document.getElementById('badgeMenuPendentes');
                    if (badge) {
                        if (data.total_pendentes > 0) {
                            badge.innerText = data.total_pendentes;
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                }
            })
            .catch(error => console.error('Erro ao atualizar contador do menu:', error));
    }

    // Consulta o menu-api.php a cada 4 segundos
    setInterval(atualizarContadorMenu, 4000);
</script>