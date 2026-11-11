<?php
// clientes.php
$currentPage = 'clientes';

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

// Se for uma requisição AJAX para buscar histórico do cliente
if (isset($_GET['ajax_historico']) && isset($_GET['cliente_id'])) {
    $clienteId = intval($_GET['cliente_id']);
    
    $stmtHist = $pdo->prepare("
        SELECT p.*, 
               (SELECT GROUP_CONCAT(CONCAT(ip.quantidade, 'x ', pr.nome) SEPARATOR ', ') 
                FROM itens_pedido ip 
                LEFT JOIN produtos pr ON ip.produto_id = pr.id 
                WHERE ip.pedido_id = p.id) as itens_descricao
        FROM pedidos p
        WHERE p.cliente_id = ? AND p.loja_id = ?
        ORDER BY p.criado_em DESC
    ");
    $stmtHist->execute([$clienteId, $loja_id]);
    $historico = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($historico);
    exit;
}

// Termo de busca (se houver)
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// Buscar total de clientes cadastrados
$stmtTotal = $pdo->prepare("SELECT COUNT(*) as total FROM clientes WHERE loja_id = ?");
$stmtTotal->execute([$loja_id]);
$totalCadastrados = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Consulta principal dos clientes com estatísticas de pedidos e endereço principal
$sql = "
    SELECT c.*, 
           (SELECT COUNT(p.id) FROM pedidos p WHERE p.cliente_id = c.id AND p.loja_id = ?) as total_pedidos,
           (SELECT SUM(p.total) FROM pedidos p WHERE p.cliente_id = c.id AND p.loja_id = ? AND p.status_pedido != 'cancelado') as total_gasto,
           e.rua, e.numero, e.bairro, e.complemento
    FROM clientes c
    LEFT JOIN enderecos_clientes e ON e.cliente_id = c.id AND e.principal = 1
    WHERE c.loja_id = ?
";

$params = [$loja_id, $loja_id, $loja_id];

if (!empty($busca)) {
    $sql .= " AND (c.nome LIKE ? OR c.telefone LIKE ?)";
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

$sql .= " ORDER BY c.criado_em DESC";

$stmtClientes = $pdo->prepare($sql);
$stmtClientes->execute($params);
$clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes | MercadoSaaS</title>
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
                <h1 class="text-lg font-bold text-slate-800">Base de Clientes</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-full">Total: <?php echo $totalCadastrados; ?> cadastrados</span>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 p-6 space-y-6 max-w-7xl w-full mx-auto">
            
            <!-- SKELETON LOADER (Visível ao carregar a página) -->
            <div id="skeletonLoader" class="space-y-6 animate-pulse">
                <!-- Skeleton da Barra de Pesquisa -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="h-5 bg-slate-200 rounded w-40"></div>
                    <div class="h-10 bg-slate-100 rounded-xl w-full sm:w-72"></div>
                </div>

                <!-- Skeleton da Tabela de Clientes -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="h-4 bg-slate-200 rounded w-28"></div>
                        <div class="h-4 bg-slate-200 rounded w-20"></div>
                    </div>
                    <div class="space-y-4">
                        <?php for($i = 0; $i < 5; $i++): ?>
                        <div class="flex items-center justify-between py-2 border-b border-slate-50">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-slate-200 rounded-xl shrink-0"></div>
                                <div class="space-y-1.5">
                                    <div class="h-4 bg-slate-200 rounded w-36"></div>
                                    <div class="h-3 bg-slate-100 rounded w-24"></div>
                                </div>
                            </div>
                            <div class="h-4 bg-slate-200 rounded w-28 hidden sm:block"></div>
                            <div class="h-4 bg-slate-200 rounded w-20 hidden md:block"></div>
                            <div class="h-4 bg-slate-200 rounded w-16"></div>
                            <div class="h-4 bg-slate-200 rounded w-20"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- CONTEÚDO REAL DA PÁGINA (Oculto inicialmente para exibir o Skeleton) -->
            <div id="pageContent" class="space-y-6 hidden opacity-0 transition-opacity duration-300">

                <!-- Barra de Pesquisa -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-slate-800">Lista de Compradores</h3>
                    </div>
                    <form method="GET" action="clientes.php" class="relative w-full sm:w-72">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </span>
                        <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar por nome ou telefone..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                    </form>
                </div>

                <!-- Tabela de Clientes -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                                    <th class="py-3 px-6">Cliente</th>
                                    <th class="py-3 px-6">Contato / WhatsApp</th>
                                    <th class="py-3 px-6">Endereço Principal</th>
                                    <th class="py-3 px-6">Pedidos</th>
                                    <th class="py-3 px-6">Total Gasto</th>
                                    <th class="py-3 px-6 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-600">
                                <?php if (count($clientes) > 0): ?>
                                    <?php foreach ($clientes as $cli): 
                                        // Iniciais para o avatar
                                        $partesNome = explode(' ', trim($cli['nome']));
                                        $iniciais = mb_strtoupper(mb_substr($partesNome[0], 0, 1));
                                        if (count($partesNome) > 1) {
                                            $iniciais .= mb_strtoupper(mb_substr(end($partesNome), 0, 1));
                                        }

                                        // Formatar endereço
                                        $enderecoStr = "Endereço não informado";
                                        if (!empty($cli['rua'])) {
                                            $enderecoStr = "{$cli['rua']}, {$cli['numero']} - {$cli['bairro']}";
                                        }

                                        // Cores dinâmicas para o avatar baseadas no ID
                                        $cores = ['bg-orange-100 text-orange-600', 'bg-blue-100 text-blue-600', 'bg-purple-100 text-purple-600', 'bg-emerald-100 text-emerald-600'];
                                        $corAvatar = $cores[$cli['id'] % count($cores)];
                                    ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="py-4 px-6 flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl <?php echo $corAvatar; ?> font-bold flex items-center justify-center text-sm shrink-0">
                                                <?php echo $iniciais; ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($cli['nome']); ?></p>
                                                <p class="text-xs text-slate-400">Cliente desde <?php echo date('M/Y', strtotime($cli['criado_em'])); ?></p>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 font-medium text-slate-700"><?php echo htmlspecialchars($cli['telefone']); ?></td>
                                        <td class="py-4 px-6 text-slate-500 text-xs"><?php echo htmlspecialchars($enderecoStr); ?></td>
                                        <td class="py-4 px-6 font-semibold text-slate-800"><?php echo intval($cli['total_pedidos']); ?> pedido(s)</td>
                                        <td class="py-4 px-6 font-semibold text-emerald-600">R$ <?php echo number_format($cli['total_gasto'] ?? 0, 2, ',', '.'); ?></td>
                                        <td class="py-4 px-6 text-right">
                                            <button onclick="abrirHistorico(<?php echo $cli['id']; ?>, '<?php echo htmlspecialchars($cli['nome'], ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition-colors cursor-pointer" title="Ver Histórico">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-slate-400">Nenhum cliente encontrado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- MODAL DE HISTÓRICO DO CLIENTE -->
    <div id="modalHistorico" class="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-md hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-2xl w-full p-6 sm:p-8 space-y-6 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between border-b pb-4">
                <div>
                    <h3 id="modalNomeCliente" class="text-lg font-bold text-slate-800">Histórico de Compras</h3>
                    <p class="text-xs text-slate-400">Acompanhe todos os pedidos realizados por este cliente</p>
                </div>
                <button onclick="fecharHistorico()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div id="conteudoHistorico" class="overflow-y-auto space-y-3 flex-1 pr-1">
                <!-- Preenchido via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Script de Controle -->
    <script>
        lucide.createIcons();

        // Gerenciador do Skeleton Loader na carga inicial
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const skeleton = document.getElementById('skeletonLoader');
                const content = document.getElementById('pageContent');
                if (skeleton && content) {
                    skeleton.style.display = 'none';
                    content.classList.remove('hidden');
                    setTimeout(() => {
                        content.classList.remove('opacity-0');
                        lucide.createIcons(); // Recria os ícones após exibir o conteúdo real
                    }, 50);
                }
            }, 300); // Delay fluido de 300ms
        });

        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const openBtn = document.getElementById('openSidebarBtn');
        function toggleSidebar() { if(sidebar) sidebar.classList.toggle('-translate-x-full'); if(overlay) overlay.classList.toggle('hidden'); }
        if(openBtn) openBtn.addEventListener('click', toggleSidebar);
        if(overlay) overlay.addEventListener('click', toggleSidebar);

        function abrirHistorico(clienteId, nomeCliente) {
            document.getElementById('modalNomeCliente').innerText = `Histórico de: ${nomeCliente}`;
            const container = document.getElementById('conteudoHistorico');
            container.innerHTML = '<p class="text-center text-slate-400 py-6">Carregando histórico...</p>';
            
            document.getElementById('modalHistorico').classList.remove('hidden');
            document.getElementById('modalHistorico').classList.add('flex');

            fetch(`clientes.php?ajax_historico=1&cliente_id=${clienteId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) {
                        container.innerHTML = '<p class="text-center text-slate-400 py-6">Este cliente ainda não realizou nenhum pedido.</p>';
                        return;
                    }

                    let html = '';
                    data.forEach(pedido => {
                        let badgeColor = 'bg-slate-100 text-slate-600';
                        if (pedido.status_pedido === 'pendente') badgeColor = 'bg-amber-100 text-amber-700';
                        else if (pedido.status_pedido === 'preparando') badgeColor = 'bg-blue-100 text-blue-700';
                        else if (pedido.status_pedido === 'em_rota') badgeColor = 'bg-purple-100 text-purple-700';
                        else if (pedido.status_pedido === 'entregue') badgeColor = 'bg-emerald-100 text-emerald-700';
                        else if (pedido.status_pedido === 'cancelado') badgeColor = 'bg-rose-100 text-rose-700';

                        html += `
                            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-800">Pedido #${pedido.codigo_pedido}</span>
                                    <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold ${badgeColor}">${pedido.status_pedido.toUpperCase()}</span>
                                </div>
                                <p class="text-xs text-slate-500"><strong>Itens:</strong> ${pedido.itens_descricao || 'Nenhum item detalhado'}</p>
                                <div class="flex justify-between items-center text-xs text-slate-400 pt-1 border-t border-slate-200/60">
                                    <span>Data: ${new Date(pedido.criado_em).toLocaleString('pt-BR')}</span>
                                    <span class="font-bold text-slate-800 text-sm">R$ ${parseFloat(pedido.total).toFixed(2).replace('.', ',')}</span>
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                })
                .catch(err => {
                    container.innerHTML = '<p class="text-center text-rose-500 py-6">Erro ao carregar histórico.</p>';
                });
        }

        function fecharHistorico() {
            document.getElementById('modalHistorico').classList.remove('flex');
            document.getElementById('modalHistorico').classList.add('hidden');
        }
    </script>
</body>
</html>