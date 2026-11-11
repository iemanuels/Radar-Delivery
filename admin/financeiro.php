<?php
// admin/financeiro.php
require_once 'config.php';
$currentAdminPage = 'financeiro';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro do SaaS | <?= SAAS_NOME ?></title>
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
                <h1 class="text-lg font-bold text-slate-800">Financeiro Global do SaaS</h1>
            </div>
            <div>
                <button class="bg-orange-500 hover:bg-orange-600 text-white font-medium px-4 py-2 rounded-xl text-sm transition-colors shadow-sm shadow-orange-500/20 flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4"></i> Exportar Relatório
                </button>
            </div>
        </header>

        <main class="flex-1 p-6 space-y-6 max-w-7xl w-full mx-auto">
            
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Faturamento (Mês Atual)</span>
                    <p class="text-2xl font-bold text-slate-800 mt-2">R$ 3.580,00</p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Assinaturas Pendentes</span>
                    <p class="text-2xl font-bold text-amber-600 mt-2">R$ 294,00</p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Projeção MRR</span>
                    <p class="text-2xl font-bold text-emerald-600 mt-2">R$ 4.150,00</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800">Últimas Assinaturas Recebidas</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                                <th class="py-3 px-6">Transação ID</th>
                                <th class="py-3 px-6">Loja</th>
                                <th class="py-3 px-6">Plano</th>
                                <th class="py-3 px-6">Valor</th>
                                <th class="py-3 px-6">Status</th>
                                <th class="py-3 px-6 text-right">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600">
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-6 font-semibold text-slate-800">#TRX-8821</td>
                                <td class="py-4 px-6">Padaria do João</td>
                                <td class="py-4 px-6">Plano Pro</td>
                                <td class="py-4 px-6 font-semibold text-emerald-600">+ R$ 197,00</td>
                                <td class="py-4 px-6"><span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-semibold">Aprovado</span></td>
                                <td class="py-4 px-6 text-right text-slate-400 text-xs">Hoje, 08:30</td>
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
        const openBtn = document.getElementById('adminSidebarOverlay'); // Ajustado
        document.getElementById('openAdminSidebarBtn').addEventListener('click', () => { sidebar.classList.toggle('-translate-x-full'); overlay.classList.toggle('hidden'); });
        overlay.addEventListener('click', () => { sidebar.classList.toggle('-translate-x-full'); overlay.classList.toggle('hidden'); });
    </script>
</body>
</html>