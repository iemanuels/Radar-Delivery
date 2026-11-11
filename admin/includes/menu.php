<?php
// admin/includes/menu.php
$currentAdminPage = $currentAdminPage ?? 'painel';
?>

<!-- Overlay Mobile -->
<div id="adminSidebarOverlay" class="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm hidden md:hidden transition-opacity"></div>

<!-- Sidebar Admin -->
<aside id="adminSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 text-slate-300 flex flex-col transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0 shadow-xl">
    
    <!-- Topo / Logo -->
    <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-orange-500 flex items-center justify-center text-white font-bold shadow-lg shadow-orange-500/30">
                👑
            </div>
            <div>
                <span class="font-bold text-white text-lg leading-tight block">SaaS Master</span>
                <span class="text-xs text-orange-400 font-medium">Painel Administrativo</span>
            </div>
        </div>
        <button id="closeAdminSidebarBtn" class="md:hidden text-slate-400 hover:text-white p-1 rounded-lg">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <!-- Links de Navegação -->
    <div class="flex-1 overflow-y-auto px-4 py-6 space-y-6">
        
        <!-- Visão Geral -->
        <div>
            <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Principal</p>
            <nav class="space-y-1">
                <a href="painel.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentAdminPage === 'painel' ? 'bg-orange-500 text-white font-semibold shadow-md shadow-orange-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    Dashboard Master
                </a>
                <a href="lojas.php" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentAdminPage === 'lojas' ? 'bg-orange-500 text-white font-semibold shadow-md shadow-orange-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="store" class="w-5 h-5"></i>
                        Lojas Cadastradas
                    </div>
                    <span class="bg-slate-800 text-orange-400 text-xs px-2 py-0.5 rounded-full font-bold">18</span>
                </a>
                <a href="planos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentAdminPage === 'planos' ? 'bg-orange-500 text-white font-semibold shadow-md shadow-orange-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="credit-card" class="w-5 h-5"></i>
                    Planos & Assinaturas
                </a>
            </nav>
        </div>

        <!-- Financeiro & Operacional -->
        <div>
            <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Monetização</p>
            <nav class="space-y-1">
                <a href="financeiro.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentAdminPage === 'financeiro' ? 'bg-orange-500 text-white font-semibold shadow-md shadow-orange-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                    Financeiro do SaaS
                </a>
                <a href="gateway.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentAdminPage === 'gateway' ? 'bg-orange-500 text-white font-semibold shadow-md shadow-orange-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="cpu" class="w-5 h-5"></i>
                    Gateway de Pagamento
                </a>
            </nav>
        </div>

        <!-- Configurações do Sistema -->
        <div>
            <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Sistema</p>
            <nav class="space-y-1">
                <a href="configuracoes.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentAdminPage === 'configuracoes' ? 'bg-orange-500 text-white font-semibold shadow-md shadow-orange-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="settings" class="w-5 h-5"></i>
                    Configurações & Whitelabel
                </a>
                <a href="logs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $currentAdminPage === 'logs' ? 'bg-orange-500 text-white font-semibold shadow-md shadow-orange-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="shield-alert" class="w-5 h-5"></i>
                    Logs & Segurança
                </a>
            </nav>
        </div>

    </div>

    <!-- Rodapé Admin -->
    <div class="p-4 border-t border-slate-800">
        <div class="flex items-center gap-3 p-2 rounded-xl bg-slate-800/50">
            <div class="w-9 h-9 rounded-lg bg-orange-500/20 text-orange-400 font-bold flex items-center justify-center text-sm">
                SA
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white truncate">Super Admin</p>
                <p class="text-xs text-slate-400 truncate">master@mercadosaas.com</p>
            </div>
            <a href="logout.php" class="text-slate-400 hover:text-rose-400 transition-colors p-1" title="Sair">
                <i data-lucide="log-out" class="w-5 h-5"></i>
            </a>
        </div>
    </div>
</aside>