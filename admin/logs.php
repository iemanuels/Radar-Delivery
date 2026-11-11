<?php
// admin/logs.php
require_once 'config.php';
$currentAdminPage = 'logs';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs & Segurança | <?= SAAS_NOME ?></title>
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
                <h1 class="text-lg font-bold text-slate-800">Logs de Auditoria & Segurança</h1>
            </div>
            <div>
                <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium px-4 py-2 rounded-xl text-sm transition-colors flex items-center gap-2">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Limpar Logs Antigos
                </button>
            </div>
        </header>

        <main class="flex-1 p-6 space-y-6 max-w-7xl w-full mx-auto">
            
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800">Eventos Recentes do Sistema</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                                <th class="py-3 px-6">Tipo</th>
                                <th class="py-3 px-6">Descrição da Ação</th>
                                <th class="py-3 px-6">Endereço IP</th>
                                <th class="py-3 px-6 text-right">Data / Hora</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600 font-mono text-xs">
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3 px-6"><span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-semibold">LOGIN_SUCCESS</span></td>
                                <td class="py-3 px-6 text-slate-800">Super Admin realizou login com sucesso.</td>
                                <td class="py-3 px-6 text-slate-500">127.0.0.1</td>
                                <td class="py-3 px-6 text-right text-slate-400">Hoje, 08:00:12</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3 px-6"><span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded font-semibold">STORE_RENEW</span></td>
                                <td class="py-3 px-6 text-slate-800">Assinatura da loja "Padaria do João" renovada por 30 dias.</td>
                                <td class="py-3 px-6 text-slate-500">177.18.xx.xx</td>
                                <td class="py-3 px-6 text-right text-slate-400">Ontem, 14:22:45</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3 px-6"><span class="bg-amber-50 text-amber-700 px-2 py-0.5 rounded font-semibold">CONFIG_UPDATE</span></td>
                                <td class="py-3 px-6 text-slate-800">Chaves de API do Gateway atualizadas pelo painel master.</td>
                                <td class="py-3 px-6 text-slate-500">127.0.0.1</td>
                                <td class="py-3 px-6 text-right text-slate-400">25/10/2026 11:10</td>
                            </tr>
                        </tbody>
                    </table>
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