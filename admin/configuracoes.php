<?php
// admin/configuracoes.php
require_once 'config.php';
$currentAdminPage = 'configuracoes';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações Master | <?= SAAS_NOME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex">

    <!-- Menu Admin -->
    <?php include 'includes/menu.php'; ?>

    <!-- Conteúdo Principal -->
    <div class="flex-1 flex flex-col md:pl-64 min-w-0">
        
        <!-- Topbar Master -->
        <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 px-6 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-4">
                <button id="openAdminSidebarBtn" class="md:hidden text-slate-500 hover:text-slate-700 p-1.5 rounded-lg border border-slate-200 bg-white">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <h1 class="text-lg font-bold text-slate-800">Configurações Gerais do SaaS</h1>
            </div>
            <div>
                <button type="submit" form="formMasterConfig" class="bg-orange-500 hover:bg-orange-600 text-white font-medium px-5 py-2 rounded-xl text-sm transition-colors shadow-md shadow-orange-500/20 flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Salvar Alterações
                </button>
            </div>
        </header>

        <!-- Corpo Principal -->
        <main class="flex-1 p-6 max-w-5xl w-full mx-auto">
            
            <form id="formMasterConfig" action="salvar_master.php" method="POST" class="space-y-6">

                <!-- 1. Identidade do SaaS (Whitelabel) -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base border-b border-slate-100 pb-3">
                        <i data-lucide="globe" class="w-5 h-5 text-orange-500"></i> Identidade & Whitelabel
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Nome do SaaS</label>
                            <input type="text" name="saas_nome" value="MercadoSaaS" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">E-mail de Suporte Global</label>
                            <input type="email" name="saas_email" value="suporte@mercadosaas.com" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Domínio Principal da Plataforma</label>
                        <input type="text" name="saas_dominio" value="https://mercadosaas.com" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                    </div>
                </div>

                <!-- 2. Configurações de Cadastro & Trial -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base border-b border-slate-100 pb-3">
                        <i data-lucide="user-plus" class="w-5 h-5 text-orange-500"></i> Parâmetros de Novos Cadastros
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Dias de Teste Gratuito (Trial) Padrão</label>
                            <input type="number" name="trial_dias" value="7" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Aprovação Automática de Lojas</label>
                            <select name="aprovacao_auto" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                                <option value="1" selected>Sim (Liberar acesso imediato após cadastro)</option>
                                <option value="0">Não (Requer aprovação manual do Super Admin)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 3. Gateway de Pagamento Master (Assinaturas dos Lojistas) -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base border-b border-slate-100 pb-3">
                        <i data-lucide="credit-card" class="w-5 h-5 text-orange-500"></i> Gateway Global (Cobrança dos Lojistas)
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Gateway Ativo para Assinaturas</label>
                            <select name="gateway_ativo" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                                <option value="mercadopago" selected>Mercado Pago (Pix / Cartão)</option>
                                <option value="stripe">Stripe</option>
                                <option value="asaas">Asaas</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Access Token / Chave de API Global</label>
                            <input type="password" name="gateway_token" value="APP_USR-88239482309482-xxxxxxxx" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono text-xs focus:outline-none focus:border-orange-500">
                        </div>
                    </div>
                </div>

            </form>

        </main>
    </div>

    <script>
        lucide.createIcons();
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('adminSidebarOverlay');
        const openBtn = document.getElementById('openAdminSidebarBtn');
        const closeBtn = document.getElementById('closeAdminSidebarBtn');

        function toggleAdminSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        openBtn.addEventListener('click', toggleAdminSidebar);
        closeBtn.addEventListener('click', toggleAdminSidebar);
        overlay.addEventListener('click', toggleAdminSidebar);
    </script>
</body>
</html>