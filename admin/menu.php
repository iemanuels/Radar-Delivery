<?php
// admin/menu.php
// Certifique-se de que $admin_nome esteja definido na página que inclui este menu
$paginaAtual = basename($_SERVER['PHP_SELF']);
?>
<header class="bg-slate-900 text-white shadow-md sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        
        <!-- Logo e Título -->
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-orange-600 flex items-center justify-center font-bold text-white shadow-sm">
                <i data-lucide="shield-check" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-sm font-bold tracking-tight">Painel Master</h1>
                <p class="text-[10px] text-slate-400">Administração Global</p>
            </div>
        </div>

        <!-- Navegação Desktop -->
        <nav class="hidden md:flex items-center gap-1">
            <a href="painel.php" class="px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center gap-2 <?php echo $paginaAtual == 'painel.php' ? 'bg-slate-800 text-orange-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Painel
            </a>
            <a href="lojas.php" class="px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center gap-2 <?php echo $paginaAtual == 'lojas.php' ? 'bg-slate-800 text-orange-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <i data-lucide="store" class="w-4 h-4"></i> Lojas
            </a>
            <a href="usuarios.php" class="px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center gap-2 <?php echo $paginaAtual == 'usuarios.php' ? 'bg-slate-800 text-orange-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <i data-lucide="users" class="w-4 h-4"></i> Usuários
            </a>
            <a href="planos.php" class="px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center gap-2 <?php echo $paginaAtual == 'planos.php' ? 'bg-slate-800 text-orange-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <i data-lucide="credit-card" class="w-4 h-4"></i> Planos
            </a>
        </nav>

        <!-- Perfil e Logout -->
        <div class="flex items-center gap-4">
            <div class="hidden sm:flex flex-col text-right">
                <span class="text-xs text-slate-300">Olá, <strong class="text-white"><?php echo htmlspecialchars($admin_nome ?? 'Admin'); ?></strong></span>
            </div>
            <a href="logout.php" class="bg-slate-800 hover:bg-slate-700 text-rose-400 px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 border border-slate-700 shadow-sm">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Sair
            </a>
            <!-- Botão Menu Mobile -->
            <button id="menu-btn" class="md:hidden text-slate-300 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>
        </div>
    </div>

    <!-- Menu Mobile Dropdown -->
    <div id="mobile-menu" class="hidden md:hidden bg-slate-900 border-t border-slate-800 px-4 pt-2 pb-4 space-y-1">
        <a href="painel.php" class="px-3 py-2 rounded-lg text-xs font-medium flex items-center gap-2 <?php echo $paginaAtual == 'painel.php' ? 'bg-slate-800 text-orange-400' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Painel
        </a>
        <a href="lojas.php" class="px-3 py-2 rounded-lg text-xs font-medium flex items-center gap-2 <?php echo $paginaAtual == 'lojas.php' ? 'bg-slate-800 text-orange-400' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            <i data-lucide="store" class="w-4 h-4"></i> Lojas
        </a>
        <a href="usuarios.php" class="px-3 py-2 rounded-lg text-xs font-medium flex items-center gap-2 <?php echo $paginaAtual == 'usuarios.php' ? 'bg-slate-800 text-orange-400' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            <i data-lucide="users" class="w-4 h-4"></i> Usuários
        </a>
        <a href="planos.php" class="px-3 py-2 rounded-lg text-xs font-medium flex items-center gap-2 <?php echo $paginaAtual == 'planos.php' ? 'bg-slate-800 text-orange-400' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
            <i data-lucide="credit-card" class="w-4 h-4"></i> Planos
        </a>
    </div>
</header>

<script>
    document.getElementById('menu-btn').addEventListener('click', function() {
        const menu = document.getElementById('mobile-menu');
        menu.classList.toggle('hidden');
    });
</script>