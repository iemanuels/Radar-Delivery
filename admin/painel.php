<?php
// admin/painel.php
require_once 'auth_master.php';
require_once 'config.php'; // Caminho relativo para a config a partir da pasta admin

// Buscar todas as lojas cadastradas no sistema
$stmtLojas = $pdo->query("
    SELECT l.*, 
           (SELECT COUNT(*) FROM pedidos p WHERE p.loja_id = l.id) as total_pedidos,
           (SELECT COALESCE(SUM(total), 0) FROM pedidos p WHERE p.loja_id = l.id AND p.status_pedido != 'cancelado') as faturamento_total
    FROM lojas l
    ORDER BY l.id DESC
");
$lojas = $stmtLojas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Master | Administração Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Incluindo o Menu Superior Compartilhado (com o nome e logout) -->
    <?php include 'menu.php'; ?>

    <!-- Conteúdo Principal -->
    <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">
        
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Lojas Cadastradas no Sistema</h2>
                <p class="text-xs text-slate-400 mt-0.5">Gerencie todas as lojas e parceiros ativos na plataforma.</p>
            </div>
            <div class="bg-orange-50 text-orange-700 px-3 py-1.5 rounded-xl text-xs font-semibold border border-orange-100">
                Total de Lojas: <?php echo count($lojas); ?>
            </div>
        </div>

        <!-- Tabela de Lojas -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                            <th class="py-3 px-6">ID</th>
                            <th class="py-3 px-6">Nome da Loja</th>
                            <th class="py-3 px-6">WhatsApp</th>
                            <th class="py-3 px-6">Status</th>
                            <th class="py-3 px-6">Pedidos</th>
                            <th class="py-3 px-6">Faturamento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-600">
                        <?php if (count($lojas) > 0): ?>
                            <?php foreach ($lojas as $loja): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-4 px-6 font-bold text-slate-800">#<?php echo $loja['id']; ?></td>
                                    <td class="py-4 px-6 font-semibold text-slate-800"><?php echo htmlspecialchars($loja['nome_loja']); ?></td>
                                    <td class="py-4 px-6 text-slate-500"><?php echo htmlspecialchars($loja['whatsapp']); ?></td>
                                    <td class="py-4 px-6">
                                        <?php if ($loja['status_loja'] === 'ativo'): ?>
                                            <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 rounded-full text-xs font-semibold">Ativo</span>
                                        <?php else: ?>
                                            <span class="bg-rose-50 text-rose-700 border border-rose-200 px-2.5 py-1 rounded-full text-xs font-semibold">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 font-medium text-slate-800"><?php echo $loja['total_pedidos']; ?></td>
                                    <td class="py-4 px-6 font-semibold text-slate-800">R$ <?php echo number_format($loja['faturamento_total'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 text-sm">Nenhuma loja cadastrada no sistema.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <footer class="mt-auto py-6 text-center text-xs text-slate-400 border-t border-slate-200 bg-white">
        Painel Master &copy; <?php echo date('Y'); ?> - Todos os direitos reservados.
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>