<?php
// entregadores.php
$currentPage = 'entregas';

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

// ==========================================
// ENDPOINT AJAX PARA ATUALIZAÇÃO EM TEMPO REAL
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    $hojeAjax = date('Y-m-d');

    // Métricas
    $stmtResumo = $pdo->prepare("
        SELECT 
            COUNT(id) as total_online,
            SUM(CASE WHEN status_entregador = 'em_rota' THEN 1 ELSE 0 END) as em_rota
        FROM entregadores 
        WHERE loja_id = ? AND status_entregador != 'offline'
    ");
    $stmtResumo->execute([$loja_id]);
    $resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC);

    $stmtEntregasHoje = $pdo->prepare("
        SELECT COUNT(id) as total_hoje 
        FROM pedidos 
        WHERE loja_id = ? AND status_pedido = 'entregue' AND DATE(criado_em) = ?
    ");
    $stmtEntregasHoje->execute([$loja_id, $hojeAjax]);
    $entregasHoje = $stmtEntregasHoje->fetch(PDO::FETCH_ASSOC)['total_hoje'] ?? 0;

    // Lista de Entregadores
    $stmtEntregadores = $pdo->prepare("
        SELECT e.*, 
               (SELECT COUNT(p.id) FROM pedidos p WHERE p.entregador_id = e.id AND p.status_pedido = 'entregue' AND DATE(p.criado_em) = ?) as entregas_hoje,
               p.id as pedido_id, p.codigo_pedido, p.endereco_entrega, p.total as pedido_total, p.status_pedido
        FROM entregadores e
        LEFT JOIN pedidos p ON p.entregador_id = e.id AND p.status_pedido = 'em_rota'
        WHERE e.loja_id = ?
        ORDER BY e.id DESC
    ");
    $stmtEntregadores->execute([$hojeAjax, $loja_id]);
    $entregadores = $stmtEntregadores->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'total_online' => intval($resumo['total_online'] ?? 0),
        'em_rota' => intval($resumo['em_rota'] ?? 0),
        'entregas_hoje' => intval($entregasHoje),
        'entregadores' => $entregadores
    ]);
    exit;
}

// Buscar dados reais da loja no banco de dados (Endereço e Coordenadas)
$stmtLoja = $pdo->prepare("SELECT * FROM lojas WHERE id = ?");
$stmtLoja->execute([$loja_id]);
$loja = $stmtLoja->fetch(PDO::FETCH_ASSOC);

// Coordenadas e Endereço Reais da Loja (Fallback para Manaus se estiver vazio)
$lojaLat = !empty($loja['latitude']) ? floatval($loja['latitude']) : -3.119034;
$lojaLng = !empty($loja['longitude']) ? floatval($loja['longitude']) : -60.021731;
$lojaEndereco = !empty($loja['endereco']) ? $loja['endereco'] : 'Endereço da Loja';

// Processar ações via POST (Cadastrar ou Editar Entregador com Senha Única)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nome = trim($_POST['nome']);
    $telefone = trim($_POST['telefone']);
    $veiculo = trim($_POST['veiculo']);
    $placa = trim($_POST['placa']);
    $status_entregador = trim($_POST['status_entregador']);
    
    // Senha única do entregador para o app
    $senha = trim($_POST['senha']);
    $senhaHash = !empty($senha) ? password_hash($senha, PASSWORD_DEFAULT) : null;

    if ($acao === 'cadastrar') {
        $stmt = $pdo->prepare("INSERT INTO entregadores (loja_id, nome, telefone, senha, veiculo, placa, status_entregador) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$loja_id, $nome, $telefone, $senhaHash, $veiculo, $placa, $status_entregador]);
        header("Location: entregadores.php?sucesso=cadastrado");
        exit;
    } elseif ($acao === 'editar' && $id > 0) {
        if (!empty($senha)) {
            $stmt = $pdo->prepare("UPDATE entregadores SET nome = ?, telefone = ?, senha = ?, veiculo = ?, placa = ?, status_entregador = ? WHERE id = ? AND loja_id = ?");
            $stmt->execute([$nome, $telefone, $senhaHash, $veiculo, $placa, $status_entregador, $id, $loja_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE entregadores SET nome = ?, telefone = ?, veiculo = ?, placa = ?, status_entregador = ? WHERE id = ? AND loja_id = ?");
            $stmt->execute([$nome, $telefone, $veiculo, $placa, $status_entregador, $id, $loja_id]);
        }
        header("Location: entregadores.php?sucesso=editado");
        exit;
    }
} elseif (isset($_GET['excluir'])) {
    $idExcluir = intval($_GET['excluir']);
    $stmt = $pdo->prepare("DELETE FROM entregadores WHERE id = ? AND loja_id = ?");
    $stmt->execute([$idExcluir, $loja_id]);
    header("Location: entregadores.php?sucesso=excluido");
    exit;
}

// 1. Métricas Iniciais da Frota
$hoje = date('Y-m-d');
$stmtResumo = $pdo->prepare("
    SELECT 
        COUNT(id) as total_online,
        SUM(CASE WHEN status_entregador = 'em_rota' THEN 1 ELSE 0 END) as em_rota
    FROM entregadores 
    WHERE loja_id = ? AND status_entregador != 'offline'
");
$stmtResumo->execute([$loja_id]);
$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC);

$totalOnline = $resumo['total_online'] ?? 0;
$totalEmRota = $resumo['em_rota'] ?? 0;

$stmtEntregasHoje = $pdo->prepare("
    SELECT COUNT(id) as total_hoje 
    FROM pedidos 
    WHERE loja_id = ? AND status_pedido = 'entregue' AND DATE(criado_em) = ?
");
$stmtEntregasHoje->execute([$loja_id, $hoje]);
$entregasHoje = $stmtEntregasHoje->fetch(PDO::FETCH_ASSOC)['total_hoje'] ?? 0;

// 2. Buscar Lista Inicial de Entregadores
$stmtEntregadores = $pdo->prepare("
    SELECT e.*, 
           (SELECT COUNT(p.id) FROM pedidos p WHERE p.entregador_id = e.id AND p.status_pedido = 'entregue' AND DATE(p.criado_em) = ?) as entregas_hoje,
           p.id as pedido_id, p.codigo_pedido, p.endereco_entrega, p.total as pedido_total, p.status_pedido
    FROM entregadores e
    LEFT JOIN pedidos p ON p.entregador_id = e.id AND p.status_pedido = 'em_rota'
    WHERE e.loja_id = ?
    ORDER BY e.id DESC
");
$stmtEntregadores->execute([$hoje, $loja_id]);
$entregadores = $stmtEntregadores->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Entregadores & Delivery | <?php echo htmlspecialchars($loja['nome_loja'] ?? 'Painel'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .mapa-modal { height: 400px; width: 100%; border-radius: 0.75rem; z-index: 10; }
        .moto-icon { background: #f97316; border: 2px solid white; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 6px rgba(0,0,0,0.3); color: white; }
    </style>
    <!-- Ícone da Loja Dinâmico -->
    <link rel="icon" type="image/png" href="icon.php">
    <link rel="apple-touch-icon" href="icon.php">
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col md:flex-row">

    <!-- Menu Lateral Unificado -->
    <?php include 'includes/menu.php'; ?>

    <!-- Conteúdo Principal -->
    <div class="flex-1 flex flex-col md:pl-64 min-w-0">
        
        <!-- Topbar -->
        <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 px-4 sm:px-6 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <button id="openSidebarBtn" class="md:hidden text-slate-500 hover:text-slate-700 p-2 rounded-xl border border-slate-200 bg-white">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <h1 class="text-base sm:text-lg font-bold text-slate-800">Entregadores & Delivery</h1>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="abrirModalCadastro()" class="bg-orange-500 hover:bg-orange-600 text-white font-medium px-4 py-2 rounded-xl text-sm transition-colors shadow-sm shadow-orange-500/20 flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>Novo Entregador</span>
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 p-3 sm:p-6 space-y-6 max-w-7xl w-full mx-auto">
            
            <?php if (isset($_GET['sucesso'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="check-circle-2" class="w-4 h-4 shrink-0"></i>
                    <span>Operação realizada com sucesso no sistema.</span>
                </div>
            <?php endif; ?>

            <!-- Cards Resumo da Frota -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Entregadores Ativos</span>
                    <p id="cardTotalOnline" class="text-2xl font-bold text-slate-800 mt-2"><?php echo $totalOnline; ?></p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Em Rota no Momento</span>
                    <p id="cardTotalEmRota" class="text-2xl font-bold text-amber-600 mt-2"><?php echo $totalEmRota; ?></p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Entregas Realizadas Hoje</span>
                    <p id="cardEntregasHoje" class="text-2xl font-bold text-emerald-600 mt-2"><?php echo $entregasHoje; ?></p>
                </div>
            </div>

            <!-- Grid de Entregadores (Com Skeleton Loading Inicial) -->
            <div id="gridEntregadores" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Skeleton State Inicial -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4 animate-pulse">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-slate-200 shrink-0"></div>
                            <div class="space-y-2">
                                <div class="w-28 h-4 bg-slate-200 rounded"></div>
                                <div class="w-20 h-3 bg-slate-200 rounded"></div>
                            </div>
                        </div>
                        <div class="w-20 h-6 bg-slate-200 rounded-full"></div>
                    </div>
                    <div class="h-12 bg-slate-100 rounded-xl"></div>
                    <div class="grid grid-cols-2 gap-2 pt-2">
                        <div class="h-12 bg-slate-100 rounded-xl"></div>
                        <div class="h-12 bg-slate-100 rounded-xl"></div>
                    </div>
                    <div class="flex gap-2 pt-2 border-t border-slate-100">
                        <div class="w-9 h-9 bg-slate-200 rounded-xl"></div>
                        <div class="flex-1 h-9 bg-slate-200 rounded-xl"></div>
                        <div class="w-9 h-9 bg-slate-200 rounded-xl"></div>
                        <div class="w-9 h-9 bg-slate-200 rounded-xl"></div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4 animate-pulse hidden md:block">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-slate-200 shrink-0"></div>
                            <div class="space-y-2">
                                <div class="w-28 h-4 bg-slate-200 rounded"></div>
                                <div class="w-20 h-3 bg-slate-200 rounded"></div>
                            </div>
                        </div>
                        <div class="w-20 h-6 bg-slate-200 rounded-full"></div>
                    </div>
                    <div class="h-12 bg-slate-100 rounded-xl"></div>
                    <div class="grid grid-cols-2 gap-2 pt-2">
                        <div class="h-12 bg-slate-100 rounded-xl"></div>
                        <div class="h-12 bg-slate-100 rounded-xl"></div>
                    </div>
                    <div class="flex gap-2 pt-2 border-t border-slate-100">
                        <div class="w-9 h-9 bg-slate-200 rounded-xl"></div>
                        <div class="flex-1 h-9 bg-slate-200 rounded-xl"></div>
                        <div class="w-9 h-9 bg-slate-200 rounded-xl"></div>
                        <div class="w-9 h-9 bg-slate-200 rounded-xl"></div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4 animate-pulse hidden lg:block">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-slate-200 shrink-0"></div>
                            <div class="space-y-2">
                                <div class="w-28 h-4 bg-slate-200 rounded"></div>
                                <div class="w-20 h-3 bg-slate-200 rounded"></div>
                            </div>
                        </div>
                        <div class="w-20 h-6 bg-slate-200 rounded-full"></div>
                    </div>
                    <div class="h-12 bg-slate-100 rounded-xl"></div>
                    <div class="grid grid-cols-2 gap-2 pt-2">
                        <div class="h-12 bg-slate-100 rounded-xl"></div>
                        <div class="h-12 bg-slate-100 rounded-xl"></div>
                    </div>
                    <div class="flex gap-2 pt-2 border-t border-slate-100">
                        <div class="w-9 h-9 bg-slate-200 rounded-xl"></div>
                        <div class="flex-1 h-9 bg-slate-200 rounded-xl"></div>
                        <div class="w-9 h-9 bg-slate-200 rounded-xl"></div>
                        <div class="w-9 h-9 bg-slate-200 rounded-xl"></div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- MODAL DE MAPA INDIVIDUAL AO VIVO -->
    <div id="modalMapaEntregador" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full flex flex-col shadow-xl border border-slate-100 overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-white shrink-0">
                <div class="flex items-center gap-2">
                    <i data-lucide="navigation" class="w-5 h-5 text-orange-500"></i>
                    <h3 id="tituloMapaModal" class="text-base font-bold text-slate-800">Rastreamento ao Vivo</h3>
                </div>
                <button onclick="fecharMapaEntregador()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-4 space-y-3">
                <div id="infoRotaDetalhe" class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs space-y-1">
                    <!-- Preenchido via JS -->
                </div>
                <div id="mapaIndividual" class="mapa-modal border border-slate-200"></div>
            </div>
            <div class="flex items-center justify-end px-6 py-3 border-t border-slate-100 bg-white shrink-0">
                <button type="button" onclick="fecharMapaEntregador()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-colors">Fechar</button>
            </div>
        </div>
    </div>

    <!-- MODAL DE CADASTRO / EDIÇÃO -->
    <div id="modalEntregador" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-xl border border-slate-100 my-auto overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-white shrink-0">
                <h3 id="modalTitulo" class="text-base sm:text-lg font-bold text-slate-800">Novo Entregador</h3>
                <button onclick="fecharModalEntregador()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1 space-y-4">
                <form id="formEntregador" action="entregadores.php" method="POST" class="space-y-4">
                    <input type="hidden" name="acao" id="formAcao" value="cadastrar">
                    <input type="hidden" name="id" id="formId">

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Nome Completo</label>
                        <input type="text" name="nome" id="formNome" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Telefone / WhatsApp</label>
                            <input type="text" name="telefone" id="formTelefone" required placeholder="(92) 99999-9999" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Status</label>
                            <select name="status_entregador" id="formStatus" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                                <option value="disponivel">Disponível</option>
                                <option value="em_rota">Em Rota</option>
                                <option value="offline">Offline</option>
                            </select>
                        </div>
                    </div>

                    <div class="p-4 bg-orange-50/50 border border-orange-100 rounded-xl space-y-3">
                        <h4 class="text-xs font-bold text-orange-800 uppercase tracking-wider flex items-center gap-1.5">
                            <i data-lucide="key" class="w-4 h-4"></i> Senha Única de Acesso ao Aplicativo
                        </h4>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Senha / PIN do Entregador</label>
                            <input type="text" name="senha" id="formSenha" placeholder="Ex: 1234 ou senha única" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                            <p class="text-[11px] text-slate-400 mt-1">O entregador digitará apenas esta senha no aplicativo para entrar direto na sua conta.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Veículo</label>
                            <input type="text" name="veiculo" id="formVeiculo" placeholder="Ex: Honda CG 160" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Placa</label>
                            <input type="text" name="placa" id="formPlaca" placeholder="Ex: JXQ-4192" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                    </div>
                </form>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-white shrink-0">
                <button type="button" onclick="fecharModalEntregador()" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Cancelar</button>
                <button type="submit" form="formEntregador" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-2 rounded-xl text-sm transition-colors shadow-sm shadow-orange-500/20">Salvar Entregador</button>
            </div>
        </div>
    </div>

    <!-- MODAL DE EXCLUSÃO -->
    <div id="modalExclusao" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full p-6 space-y-4 shadow-xl border border-slate-100 text-center my-auto">
            <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
                <i data-lucide="alert-triangle" class="w-6 h-6"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-800">Excluir Entregador</h3>
                <p id="textoExclusao" class="text-xs text-slate-500 mt-1">Tem certeza que deseja remover este entregador permanentemente?</p>
            </div>
            <div class="flex items-center gap-2 pt-2">
                <button onclick="fecharModalExclusao()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2 rounded-xl text-xs transition-colors">Cancelar</button>
                <a id="linkConfirmarExclusao" href="#" class="flex-1 bg-rose-600 hover:bg-rose-700 text-white font-semibold py-2 rounded-xl text-xs transition-colors text-center">Sim, Excluir</a>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        lucide.createIcons();

        // Controle do Menu Lateral Mobile
        const sidebar = document.getElementById('appSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const openSidebarBtn = document.getElementById('openSidebarBtn');
        const closeSidebarBtn = document.getElementById('closeSidebarBtn');

        function abrirMenuMobile() {
            if (sidebar && sidebarOverlay) {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.remove('hidden');
            }
        }

        function fecharMenuMobile() {
            if (sidebar && sidebarOverlay) {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            }
        }

        if (openSidebarBtn) openSidebarBtn.addEventListener('click', abrirMenuMobile);
        if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', fecharMenuMobile);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', fecharMenuMobile);

        const lojaLat = <?php echo json_encode(floatval($lojaLat)); ?>;
        const lojaLng = <?php echo json_encode(floatval($lojaLng)); ?>;
        const lojaNome = <?php echo json_encode($loja['nome_loja'] ?? 'Loja'); ?>;
        const lojaEndereco = <?php echo json_encode($lojaEndereco); ?>;

        let mapaIndividual = null;
        let entregadorAtivoModal = null;

        function calcularDistanciaMinutos(lat1, lon1, lat2, lon2) {
            if (!lat1 || !lon1 || !lat2 || !lon2) return { km: 0, min: 0 };
            const R = 6371; 
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            const distancia = R * c; 
            const minutos = Math.round((distancia / 25) * 60);
            return { km: distancia.toFixed(1), min: Math.max(minutos, 2) };
        }

        const motoIcon = L.divIcon({
            className: 'custom-moto-icon',
            html: '<div class="moto-icon" style="width:36px; height:36px;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/></svg></div>',
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });

        function abrirMapaEntregador(ent) {
            entregadorAtivoModal = ent;
            document.getElementById('tituloMapaModal').innerText = `Rastreamento ao Vivo: ${ent.nome}`;
            
            atualizarDetalhesModalInfo(ent);
            document.getElementById('modalMapaEntregador').classList.remove('hidden');

            setTimeout(() => {
                if (!mapaIndividual) {
                    mapaIndividual = L.map('mapaIndividual').setView([lojaLat, lojaLng], 14);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(mapaIndividual);
                } else {
                    mapaIndividual.invalidateSize();
                }

                redesenharMarcadoresMapa(ent);
            }, 200);
        }

        function atualizarDetalhesModalInfo(ent) {
            let infoHtml = `<strong>Ponto de Partida (Loja):</strong> ${lojaEndereco}<br>`;
            if (ent.codigo_pedido) {
                infoHtml += `<strong>Pedido Atual:</strong> #${ent.codigo_pedido} | <strong>Endereço de Entrega:</strong> ${ent.endereco_entrega}`;
            } else {
                infoHtml += `<span class="text-amber-600">Este entregador não está em rota no momento (aguardando pedido).</span>`;
            }
            document.getElementById('infoRotaDetalhe').innerHTML = infoHtml;
        }

        function redesenharMarcadoresMapa(ent) {
            if (!mapaIndividual) return;

            mapaIndividual.eachLayer((layer) => {
                if (layer instanceof L.Marker) {
                    mapaIndividual.removeLayer(layer);
                }
            });

            L.marker([lojaLat, lojaLng]).addTo(mapaIndividual)
                .bindPopup(`<b>${lojaNome}</b><br>${lojaEndereco}`);

            let centroLat = lojaLat;
            let centroLng = lojaLng;
            let temPosicaoEntregador = false;

            if (ent.latitude && ent.longitude) {
                const entLat = parseFloat(ent.latitude);
                const entLng = parseFloat(ent.longitude);
                const calc = calcularDistanciaMinutos(lojaLat, lojaLng, entLat, entLng);

                L.marker([entLat, entLng], { icon: motoIcon }).addTo(mapaIndividual)
                    .bindPopup(`<b>${ent.nome}</b> (Ao Vivo)<br>Status: ${ent.status_entregador}<br>Est. ${calc.min} min (${calc.km} km da loja)`)
                    .openPopup();

                centroLat = entLat;
                centroLng = entLng;
                temPosicaoEntregador = true;
            }

            mapaIndividual.setView([centroLat, centroLng], temPosicaoEntregador ? 16 : 14);
        }

        function fecharMapaEntregador() {
            entregadorAtivoModal = null;
            document.getElementById('modalMapaEntregador').classList.add('hidden');
        }

        // Carrega dados iniciais via AJAX logo após o DOM carregar (substituindo o skeleton inicial instantaneamente)
        function carregarDadosEntregadores(isInitial = false) {
            fetch('entregadores.php?ajax=1')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('cardTotalOnline').innerText = data.total_online;
                    document.getElementById('cardTotalEmRota').innerText = data.em_rota;
                    document.getElementById('cardEntregasHoje').innerText = data.entregas_hoje;

                    let gridHtml = '';
                    if (data.entregadores.length > 0) {
                        data.entregadores.forEach(ent => {
                            let badgeClass = "bg-emerald-50 text-emerald-700 border-emerald-200";
                            let dotClass = "bg-emerald-500";
                            let labelStatus = "Disponível";

                            if (ent.status_entregador == 'em_rota') {
                                badgeClass = "bg-amber-50 text-amber-700 border-amber-200";
                                dotClass = "bg-amber-500";
                                labelStatus = "Em Rota";
                            } else if (ent.status_entregador == 'offline') {
                                badgeClass = "bg-slate-100 text-slate-600 border-slate-200";
                                dotClass = "bg-slate-400";
                                labelStatus = "Offline";
                            }

                            let iniciais = ent.nome.substring(0, 1).toUpperCase();
                            let telefoneLimpo = ent.telefone.replace(/[^0-9]/g, '');

                            if (entregadorAtivoModal && entregadorAtivoModal.id == ent.id) {
                                entregadorAtivoModal = ent;
                                atualizarDetalhesModalInfo(ent);
                                redesenharMarcadoresMapa(ent);
                            }

                            gridHtml += `
                                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4 flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <div class="w-12 h-12 rounded-2xl bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-lg shrink-0">
                                                    ${iniciais}
                                                </div>
                                                <div>
                                                    <h4 class="font-bold text-slate-800">${escapeHtml(ent.nome)}</h4>
                                                    <p class="text-xs text-slate-400">${escapeHtml(ent.veiculo || 'Veículo')} • Placa: ${escapeHtml(ent.placa || 'N/D')}</p>
                                                </div>
                                            </div>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border ${badgeClass}">
                                                <span class="w-1.5 h-1.5 rounded-full ${dotClass}"></span> ${labelStatus}
                                            </span>
                                        </div>

                                        ${ent.pedido_id ? `
                                            <div class="mt-4 p-3 bg-amber-50/60 border border-amber-200 rounded-xl space-y-1 text-xs">
                                                <div class="flex justify-between font-bold text-amber-900">
                                                    <span>Pedido #${escapeHtml(ent.codigo_pedido)}</span>
                                                    <span>R$ ${Number(ent.pedido_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                                                </div>
                                                <p class="text-slate-600"><strong class="text-slate-700">Endereço:</strong> ${escapeHtml(ent.endereco_entrega)}</p>
                                            </div>
                                        ` : ''}

                                        <div class="border-t border-slate-100 mt-4 pt-3 grid grid-cols-2 gap-2 text-sm">
                                            <div class="bg-slate-50 p-2.5 rounded-xl">
                                                <span class="text-xs text-slate-400 block">Entregas Hoje</span>
                                                <span class="font-bold text-slate-800">${ent.entregas_hoje} entregas</span>
                                            </div>
                                            <div class="bg-slate-50 p-2.5 rounded-xl">
                                                <span class="text-xs text-slate-400 block">Senha App</span>
                                                <span class="font-semibold text-slate-700 text-xs truncate block">${ent.senha ? '•••• (Definida)' : 'Pendente'}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex gap-2 pt-2 border-t border-slate-100">
                                        <button onclick='abrirMapaEntregador(${JSON.stringify(ent)})' class="p-2 bg-orange-50 hover:bg-orange-100 text-orange-600 rounded-xl transition-colors" title="Ver no Mapa ao Vivo">
                                            <i data-lucide="map" class="w-4 h-4"></i>
                                        </button>
                                        <a href="https://wa.me/55${telefoneLimpo}" target="_blank" class="flex-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold py-2 rounded-xl text-xs transition-colors text-center flex items-center justify-center gap-1.5">
                                            <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> WhatsApp
                                        </a>
                                        <button onclick='abrirModalEdicao(${JSON.stringify(ent)})' class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition-colors" title="Editar">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="abrirModalExclusao(${ent.id}, '${escapeHtml(ent.nome).replace(/'/g, "\\'")}')" class="p-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl transition-colors" title="Excluir">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        gridHtml = `
                            <div class="col-span-full bg-white p-12 rounded-2xl border border-slate-200 text-center space-y-2">
                                <i data-lucide="users" class="w-10 h-10 text-slate-300 mx-auto"></i>
                                <p class="text-slate-600 font-medium">Nenhum entregador cadastrado.</p>
                                <p class="text-xs text-slate-400">Cadastre entregadores para gerenciar suas rotas de entrega.</p>
                            </div>
                        `;
                    }
                    document.getElementById('gridEntregadores').innerHTML = gridHtml;
                    lucide.createIcons();
                })
                .catch(err => console.error('Erro na atualização em tempo real:', err));
        }

        // Executa a chamada inicial ao carregar a página e configura o polling de 5 segundos
        document.addEventListener("DOMContentLoaded", () => {
            carregarDadosEntregadores(true);
            setInterval(() => carregarDadosEntregadores(false), 5000);
        });

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        const modalEntregador = document.getElementById('modalEntregador');
        const modalExclusao = document.getElementById('modalExclusao');

        function abrirModalCadastro() {
            document.getElementById('modalTitulo').innerText = 'Novo Entregador';
            document.getElementById('formAcao').value = 'cadastrar';
            document.getElementById('formId').value = '';
            document.getElementById('formNome').value = '';
            document.getElementById('formTelefone').value = '';
            document.getElementById('formSenha').value = '';
            document.getElementById('formVeiculo').value = '';
            document.getElementById('formPlaca').value = '';
            document.getElementById('formStatus').value = 'disponivel';
            modalEntregador.classList.remove('hidden');
        }

        function abrirModalEdicao(ent) {
            document.getElementById('modalTitulo').innerText = 'Editar Entregador';
            document.getElementById('formAcao').value = 'editar';
            document.getElementById('formId').value = ent.id;
            document.getElementById('formNome').value = ent.nome;
            document.getElementById('formTelefone').value = ent.telefone || '';
            document.getElementById('formSenha').value = ''; 
            document.getElementById('formVeiculo').value = ent.veiculo || '';
            document.getElementById('formPlaca').value = ent.placa || '';
            document.getElementById('formStatus').value = ent.status_entregador || 'disponivel';
            modalEntregador.classList.remove('hidden');
        }

        function fecharModalEntregador() {
            modalEntregador.classList.add('hidden');
        }

        function abrirModalExclusao(id, nome) {
            document.getElementById('textoExclusao').innerText = `Tem certeza que deseja remover "${nome}" permanentemente?`;
            document.getElementById('linkConfirmarExclusao').href = `entregadores.php?excluir=${id}`;
            modalExclusao.classList.remove('hidden');
        }

        function fecharModalExclusao() {
            modalExclusao.classList.add('hidden');
        }
    </script>
</body>
</html>