<?php
// admin/gateway.php
require_once 'config.php';
$currentAdminPage = 'gateway';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gateway de Pagamento | <?= SAAS_NOME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex">

    <?php include 'includes/menu.php'; ?>

    <div class="flex-1 flex flex-col md:pl-64 min-w-0">
        
        <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 px-6 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-4">
                <button id="openAdminSidebarBtn" class="md:hidden text-slate-500 hover:text-slate-700 p-1.5 rounded-lg border border-slate-200 bg-white">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <h1 class="text-lg font-bold text-slate-800">Gateway de Pagamento Master</h1>
            </div>
            <div>
                <button class="bg-orange-500 hover:bg-orange-600 text-white font-medium px-5 py-2 rounded-xl text-sm transition-colors shadow-sm shadow-orange-500/20 flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Salvar Chaves
                </button>
            </div>
        </header>

        <main class="flex-1 p-6 max-w-4xl w-full mx-auto">
            
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                <h3 class="font-bold text-slate-800 text-base border-b border-slate-100 pb-3 flex items-center gap-2">
                    <i data-lucide="cpu" class="w-5 h-5 text-orange-500"></i> Provedor Ativo para Cobrança de Lojistas
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Gateway Principal</label>
                        <select class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                            <option value="mercadopago" selected>Mercado Pago (Pix e Cartão de Crédito)</option>
                            <option value="stripe">Stripe</option>
                            <option value="asaas">Asaas</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Public Key / Client ID</label>
                            <input type="text" value="APP_USR-984320984320984320" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono text-xs focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Access Token / Secret Key</label>
                            <input type="password" value="APP_USR-88239482309482-xxxxxxxx" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono text-xs focus:outline-none focus:border-orange-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">URL de Webhook (Notificações de Pagamento)</label>
                        <input type="text" readonly value="<?= SAAS_URL ?>/admin/webhook.php" class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm font-mono text-xs text-slate-500">
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        lucide.createIcons();
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('adminSidebarOverlay');
        document.getElementById('openAdminSidebarBtn').addEventListener('click', () => { sidebar.classList.toggle('-translate-x-full'); overlay.classList.toggle('hidden'); });
        overlay.addEventListener('click', () => { sidebar.classList.toggle('-translate-x-full'); overlay.classList.toggle('hidden'); });
    </script>
</body>
</html>