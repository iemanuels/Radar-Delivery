<?php
// produtos.php
$currentPage = 'produtos';

// Define fuso horário de Manaus
date_default_timezone_set('America/Manaus');

// Inclui o arquivo de configuração e conexão com o banco de dados
require_once 'admin/config.php';

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
    require_once 'auth.php';

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

// Processar alteração de ordenação dos produtos via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar_ordenacao_produtos') {
    $novaOrdenacao = $_POST['ordenacao_produtos'] ?? 'az';
    $stmtUpOrd = $pdo->prepare("UPDATE lojas SET ordenacao_produtos = ? WHERE id = ?");
    $stmtUpOrd->execute([$novaOrdenacao, $loja_id]);
    header("Location: produtos.php?sucesso=ordenacao");
    exit;
}

// Processar alteração de destaque da categoria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar_destaque_categoria') {
    $categoriaDestaqueId = !empty($_POST['categoria_destaque']) ? intval($_POST['categoria_destaque']) : null;
    $pdo->prepare("UPDATE categorias SET destaque = 0 WHERE loja_id = ?")->execute([$loja_id]);
    if ($categoriaDestaqueId) {
        $stmtDestaque = $pdo->prepare("UPDATE categorias SET destaque = 1 WHERE id = ? AND loja_id = ?");
        $stmtDestaque->execute([$categoriaDestaqueId, $loja_id]);
    }
    header("Location: produtos.php?sucesso=categoria_destaque");
    exit;
}

// Processar requisição AJAX para alternar o status do produto rapidamente
if (isset($_POST['acao_ajax']) && $_POST['acao_ajax'] === 'toggle_status') {
    $idProd = intval($_POST['id']);
    $novoStatus = trim($_POST['status_produto']);
    if (in_array($novoStatus, ['disponivel', 'esgotado'])) {
        $stmtStatus = $pdo->prepare("UPDATE produtos SET status_produto = ? WHERE id = ? AND loja_id = ?");
        $stmtStatus->execute([$novoStatus, $idProd, $loja_id]);
        echo json_encode(['sucesso' => true]);
        exit;
    }
    echo json_encode(['sucesso' => false]);
    exit;
}

// Processar ações via POST (Categorias e Produtos)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cadastrar Categoria
    if (isset($_POST['acao']) && $_POST['acao'] === 'cadastrar_categoria') {
        $nomeCategoria = trim($_POST['nome_categoria']);
        if (!empty($nomeCategoria)) {
            $stmtCat = $pdo->prepare("INSERT INTO categorias (loja_id, nome, ordem, destaque) VALUES (?, ?, 0, 0)");
            $stmtCat->execute([$loja_id, $nomeCategoria]);
            header("Location: produtos.php?sucesso=categoria_cadastrada");
            exit;
        }
    }

    // Editar Categoria
    if (isset($_POST['acao']) && $_POST['acao'] === 'editar_categoria') {
        $idCat = intval($_POST['id_categoria']);
        $nomeCategoria = trim($_POST['nome_categoria']);
        if ($idCat > 0 && !empty($nomeCategoria)) {
            $stmtEditCat = $pdo->prepare("UPDATE categorias SET nome = ? WHERE id = ? AND loja_id = ?");
            $stmtEditCat->execute([$nomeCategoria, $idCat, $loja_id]);
            header("Location: produtos.php?sucesso=categoria_editada");
            exit;
        }
    }

    // Cadastrar ou Editar Produto
    if (isset($_POST['acao']) && in_array($_POST['acao'], ['cadastrar', 'editar'])) {
        $acao = $_POST['acao'];
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $nome = trim($_POST['nome']);
        $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
        $codigo_barras = trim($_POST['codigo_barras']);
        $preco = str_replace(',', '.', trim($_POST['preco']));
        
        $estoque = str_replace(',', '.', trim($_POST['estoque'] ?? 0));
        $estoque_minimo = str_replace(',', '.', trim($_POST['estoque_minimo'] ?? 5));
        $unidade = trim($_POST['unidade'] ?? 'un');
        
        $descricao = trim($_POST['descricao'] ?? '');
        $status_produto = ($acao === 'cadastrar') ? 'disponivel' : (trim($_POST['status_produto'] ?? 'disponivel'));

        $fotoPath = $_POST['foto_atual'] ?? null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $pastaUploads = 'uploads/';
            if (!is_dir($pastaUploads)) {
                mkdir($pastaUploads, 0755, true);
            }
            $extensao = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $nomeArquivo = 'produto_' . time() . '_' . rand(1000, 9999) . '.' . $extensao;
            $caminhoCompleto = $pastaUploads . $nomeArquivo;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $caminhoCompleto)) {
                $fotoPath = $caminhoCompleto;
            }
        }

        if ($acao === 'cadastrar') {
            $stmt = $pdo->prepare("INSERT INTO produtos (loja_id, categoria_id, nome, codigo_barras, descricao, preco, estoque, estoque_minimo, unidade, foto, status_produto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$loja_id, $categoria_id, $nome, $codigo_barras, $descricao, $preco, $estoque, $estoque_minimo, $unidade, $fotoPath, $status_produto]);
            header("Location: produtos.php?sucesso=cadastrado");
            exit;
        } elseif ($acao === 'editar' && $id > 0) {
            $stmt = $pdo->prepare("UPDATE produtos SET categoria_id = ?, nome = ?, codigo_barras = ?, descricao = ?, preco = ?, estoque = ?, estoque_minimo = ?, unidade = ?, foto = ?, status_produto = ? WHERE id = ? AND loja_id = ?");
            $stmt->execute([$categoria_id, $nome, $codigo_barras, $descricao, $preco, $estoque, $estoque_minimo, $unidade, $fotoPath, $status_produto, $id, $loja_id]);
            header("Location: produtos.php?sucesso=editado");
            exit;
        }
    }
} 

// Excluir Produto ou Categoria via GET
if (isset($_GET['excluir'])) {
    $idExcluir = intval($_GET['excluir']);
    $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ? AND loja_id = ?");
    $stmt->execute([$idExcluir, $loja_id]);
    header("Location: produtos.php?sucesso=excluido");
    exit;
} elseif (isset($_GET['excluir_categoria'])) {
    $idCatExcluir = intval($_GET['excluir_categoria']);
    $pdo->prepare("UPDATE produtos SET categoria_id = NULL WHERE categoria_id = ? AND loja_id = ?")->execute([$idCatExcluir, $loja_id]);
    $stmtCatDel = $pdo->prepare("DELETE FROM categorias WHERE id = ? AND loja_id = ?");
    $stmtCatDel->execute([$idCatExcluir, $loja_id]);
    header("Location: produtos.php?sucesso=categoria_excluida");
    exit;
}

// Buscar configuração da loja para ordenação padrão
$stmtLojaConfig = $pdo->prepare("SELECT ordenacao_produtos FROM lojas WHERE id = ?");
$stmtLojaConfig->execute([$loja_id]);
$lojaConfig = $stmtLojaConfig->fetch(PDO::FETCH_ASSOC);
$ordenacaoAtual = $lojaConfig['ordenacao_produtos'] ?? 'az';

// Capturar filtros
$categoriaFiltro = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// 1. Buscar Categorias da Loja ordenadas pelo destaque
$stmtCategorias = $pdo->prepare("SELECT * FROM categorias WHERE loja_id = ? ORDER BY destaque DESC, ordem ASC, nome ASC");
$stmtCategorias->execute([$loja_id]);
$categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

// Contagem total de produtos
$stmtTotalGeral = $pdo->prepare("SELECT COUNT(id) as total FROM produtos WHERE loja_id = ?");
$stmtTotalGeral->execute([$loja_id]);
$totalGeralProdutos = $stmtTotalGeral->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 2. Construir consulta dos produtos com base na ordenação escolhida
$sql = "
    SELECT p.*, c.nome as nome_categoria,
    (SELECT COALESCE(SUM(ip.quantidade), 0) FROM itens_pedido ip WHERE ip.produto_id = p.id) as total_vendido
    FROM produtos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.loja_id = ?
";
$params = [$loja_id];

if ($categoriaFiltro > 0) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = $categoriaFiltro;
}

if (!empty($busca)) {
    $sql .= " AND (p.nome LIKE ? OR p.codigo_barras LIKE ?)";
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

switch ($ordenacaoAtual) {
    case 'za':
        $sql .= " ORDER BY p.nome DESC";
        break;
    case 'mais_vendidos':
        $sql .= " ORDER BY total_vendido DESC, p.nome ASC";
        break;
    case 'menor_preco':
        $sql .= " ORDER BY p.preco ASC";
        break;
    case 'maior_preco':
        $sql .= " ORDER BY p.preco DESC";
        break;
    case 'az':
    default:
        $sql .= " ORDER BY p.nome ASC";
        break;
}

$stmtProdutos = $pdo->prepare($sql);
$stmtProdutos->execute($params);
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Produtos e Estoque | Painel Administrativo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        @media (max-width: 768px) {
            input, select, textarea { font-size: 16px !important; }
        }
    </style>
    <!-- Ícone da Loja Dinâmico -->
    <link rel="icon" type="image/png" href="icon.php">
    <link rel="apple-touch-icon" href="icon.php">
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col md:flex-row">

    <!-- Menu Lateral -->
    <?php include 'includes/menu.php'; ?>

    <!-- Conteúdo Principal -->
    <div class="flex-1 flex flex-col md:pl-64 min-w-0">
        
        <!-- Topbar -->
        <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 px-4 sm:px-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button id="openSidebarBtn" class="md:hidden text-slate-500 hover:text-slate-700 p-2 rounded-xl border border-slate-200 bg-white active:bg-slate-100">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <h1 class="text-base sm:text-lg font-bold text-slate-800 truncate">Produtos & Estoque</h1>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="abrirModalGerenciarCategorias()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium px-3 py-2 rounded-xl text-xs sm:text-sm transition-colors flex items-center gap-1.5">
                    <i data-lucide="folder-cog" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">Categorias</span>
                </button>
                <button onclick="abrirModalCadastro()" class="bg-orange-500 hover:bg-orange-600 text-white font-medium px-3 sm:px-4 py-2 rounded-xl text-xs sm:text-sm transition-colors shadow-sm shadow-orange-500/20 flex items-center gap-1.5">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span class="whitespace-nowrap">Novo Produto</span>
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 p-3 sm:p-6 space-y-4 sm:space-y-6 max-w-7xl w-full mx-auto">
            
            <!-- SKELETON LOADER (Visível ao carregar a página) -->
            <div id="skeletonLoader" class="space-y-6 animate-pulse">
                <!-- Skeleton do Painel Superior -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <div class="h-4 bg-slate-200 rounded w-48"></div>
                        <div class="h-10 bg-slate-100 rounded-xl w-full"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="h-4 bg-slate-200 rounded w-48"></div>
                        <div class="h-10 bg-slate-100 rounded-xl w-full"></div>
                    </div>
                </div>

                <!-- Skeleton da Barra de Filtros -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                    <div class="flex items-center gap-2 overflow-x-auto pb-2 lg:pb-0">
                        <div class="h-9 bg-slate-100 rounded-xl w-28 shrink-0"></div>
                        <div class="h-9 bg-slate-100 rounded-xl w-32 shrink-0"></div>
                        <div class="h-9 bg-slate-100 rounded-xl w-32 shrink-0"></div>
                    </div>
                    <div class="h-9 bg-slate-100 rounded-xl w-full lg:w-72 shrink-0"></div>
                </div>

                <!-- Skeleton da Tabela de Produtos -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden p-4 sm:p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="h-4 bg-slate-200 rounded w-32"></div>
                        <div class="h-4 bg-slate-200 rounded w-24"></div>
                    </div>
                    <div class="space-y-3">
                        <?php for($i = 0; $i < 5; $i++): ?>
                        <div class="flex items-center justify-between py-2 border-b border-slate-50">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-slate-200 rounded-xl shrink-0"></div>
                                <div class="space-y-1.5">
                                    <div class="h-4 bg-slate-200 rounded w-40"></div>
                                    <div class="h-3 bg-slate-100 rounded w-24"></div>
                                </div>
                            </div>
                            <div class="h-4 bg-slate-200 rounded w-20 hidden sm:block"></div>
                            <div class="h-4 bg-slate-200 rounded w-16"></div>
                            <div class="h-6 bg-slate-200 rounded-full w-12"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- CONTEÚDO REAL DA PÁGINA (Oculto inicialmente para exibir o Skeleton) -->
            <div id="pageContent" class="space-y-4 sm:space-y-6 hidden opacity-0 transition-opacity duration-300">

                <!-- Mensagens de Feedback com Auto-Remoção em 3 Segundos -->
                <?php if (isset($_GET['sucesso'])): ?>
                    <div id="cardMensagemSucesso" class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2 transition-opacity duration-500 shadow-sm">
                        <i data-lucide="check-circle-2" class="w-4 h-4 shrink-0"></i>
                        <span>Operação realizada com sucesso no sistema.</span>
                    </div>
                <?php endif; ?>

                <!-- Painel de Controle de Autonomia (Responsivo Ajustado) -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm grid grid-cols-1 lg:grid-cols-2 gap-4">
                    
                    <!-- Definir Categoria em Destaque -->
                    <form action="produtos.php" method="POST" class="flex flex-col justify-between space-y-2">
                        <input type="hidden" name="acao" value="atualizar_destaque_categoria">
                        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                            <i data-lucide="star" class="w-3.5 h-3.5 text-amber-500 shrink-0"></i> Categoria em Destaque (Primeiro Lugar)
                        </label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <select name="categoria_destaque" class="w-full sm:flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500 min-w-0">
                                <option value="">-- Nenhuma Categoria em Destaque --</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $cat['destaque'] == 1 ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nome']); ?> <?php echo $cat['destaque'] == 1 ? '(Destaque Atual)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="w-full sm:w-auto bg-slate-800 hover:bg-slate-900 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors flex items-center justify-center gap-1.5 shrink-0">
                                <i data-lucide="check" class="w-4 h-4"></i> Aplicar
                            </button>
                        </div>
                    </form>

                    <!-- Definir Ordenação dos Produtos -->
                    <form action="produtos.php" method="POST" class="flex flex-col justify-between space-y-2">
                        <input type="hidden" name="acao" value="atualizar_ordenacao_produtos">
                        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                            <i data-lucide="arrow-down-up" class="w-3.5 h-3.5 text-orange-500 shrink-0"></i> Organização dos Itens para o Cliente
                        </label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <select name="ordenacao_produtos" class="w-full sm:flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500 min-w-0">
                                <option value="az" <?php echo $ordenacaoAtual == 'az' ? 'selected' : ''; ?>>Ordem Alfabética (A a Z)</option>
                                <option value="za" <?php echo $ordenacaoAtual == 'za' ? 'selected' : ''; ?>>Ordem Alfabética Inversa (Z a A)</option>
                                <option value="mais_vendidos" <?php echo $ordenacaoAtual == 'mais_vendidos' ? 'selected' : ''; ?>>Mais Vendidos (Relevância)</option>
                                <option value="menor_preco" <?php echo $ordenacaoAtual == 'menor_preco' ? 'selected' : ''; ?>>Menor Preço</option>
                                <option value="maior_preco" <?php echo $ordenacaoAtual == 'maior_preco' ? 'selected' : ''; ?>>Maior Preço</option>
                            </select>
                            <button type="submit" class="w-full sm:w-auto bg-orange-500 hover:bg-orange-600 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors flex items-center justify-center gap-1.5 shrink-0">
                                <i data-lucide="check" class="w-4 h-4"></i> Organizar
                            </button>
                        </div>
                    </form>

                </div>

                <!-- Barra de Ações e Filtros de Categoria -->
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="flex items-center gap-2 w-full overflow-x-auto pb-2 lg:pb-0 scrollbar-thin">
                        <a href="produtos.php" class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-colors whitespace-nowrap flex items-center gap-2 shrink-0 <?php echo $categoriaFiltro === 0 ? 'bg-orange-50 text-orange-600' : 'text-slate-600 hover:bg-slate-50'; ?>">
                            <i data-lucide="layout-grid" class="w-4 h-4"></i> Todos (<?php echo $totalGeralProdutos; ?>)
                        </a>
                        <?php foreach ($categorias as $cat): ?>
                            <a href="produtos.php?categoria=<?php echo $cat['id']; ?>" class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium transition-colors whitespace-nowrap flex items-center gap-2 shrink-0 <?php echo $categoriaFiltro === intval($cat['id']) ? 'bg-orange-50 text-orange-600' : 'text-slate-600 hover:bg-slate-50'; ?>">
                                <?php if ($cat['destaque'] == 1): ?>
                                    <i data-lucide="star" class="w-3.5 h-3.5 text-amber-500 fill-amber-500"></i>
                                <?php else: ?>
                                    <i data-lucide="folder" class="w-3.5 h-3.5 text-slate-400"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($cat['nome']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <form action="produtos.php" method="GET" class="relative w-full lg:w-72 shrink-0">
                        <?php if ($categoriaFiltro > 0): ?>
                            <input type="hidden" name="categoria" value="<?php echo $categoriaFiltro; ?>">
                        <?php endif; ?>
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </span>
                        <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Pesquisar por nome ou código..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                    </form>
                </div>

                <!-- Tabela de Produtos -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm min-w-[700px]">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                                    <th class="py-3 px-4 sm:px-6">Produto</th>
                                    <th class="py-3 px-4 sm:px-6">Categoria</th>
                                    <th class="py-3 px-4 sm:px-6">Preço Base</th>
                                    <th class="py-3 px-4 sm:px-6">Estoque Atual</th>
                                    <th class="py-3 px-4 sm:px-6">Status (Ativo / Esgotado)</th>
                                    <th class="py-3 px-4 sm:px-6 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-600">
                                <?php if (count($produtos) > 0): ?>
                                    <?php foreach ($produtos as $prod): ?>
                                        <?php 
                                            $unidade = $prod['unidade'] ?? 'un';
                                            $siglaUnidade = ($unidade === 'kg') ? 'kg' : 'un';
                                            $estoqueFormatado = ($unidade === 'kg') ? number_format($prod['estoque'], 3, ',', '.') : intval($prod['estoque']);
                                        ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="py-3.5 px-4 sm:px-6">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 overflow-hidden shrink-0">
                                                        <?php if (!empty($prod['foto']) && file_exists($prod['foto'])): ?>
                                                            <img src="<?php echo htmlspecialchars($prod['foto']); ?>" alt="" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <i data-lucide="package" class="w-5 h-5 text-slate-400"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="font-semibold text-slate-800 truncate max-w-[180px] sm:max-w-xs"><?php echo htmlspecialchars($prod['nome']); ?></p>
                                                        <p class="text-xs text-slate-400">Cód: <?php echo htmlspecialchars($prod['codigo_barras'] ?? 'N/D'); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3.5 px-4 sm:px-6 text-slate-500 font-medium"><?php echo htmlspecialchars($prod['nome_categoria'] ?? 'Geral'); ?></td>
                                            <td class="py-3.5 px-4 sm:px-6 font-semibold text-slate-800 whitespace-nowrap">
                                                R$ <?php echo number_format($prod['preco'], 2, ',', '.'); ?> <span class="text-xs font-normal text-slate-400">/<?php echo $siglaUnidade; ?></span>
                                            </td>
                                            <td class="py-3.5 px-4 sm:px-6 whitespace-nowrap">
                                                <?php if ($prod['estoque'] <= 0): ?>
                                                    <span class="font-medium text-rose-600 bg-rose-50 px-2 py-0.5 rounded-md text-xs">0 <?php echo $siglaUnidade; ?></span>
                                                <?php elseif ($prod['estoque'] <= $prod['estoque_minimo']): ?>
                                                    <span class="font-medium text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md text-xs"><?php echo $estoqueFormatado; ?> <?php echo $siglaUnidade; ?> (Baixo)</span>
                                                <?php else: ?>
                                                    <span class="font-medium text-slate-800 text-xs sm:text-sm"><?php echo $estoqueFormatado; ?> <?php echo $siglaUnidade; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3.5 px-4 sm:px-6 whitespace-nowrap">
                                                <?php $isChecked = ($prod['status_produto'] === 'disponivel'); ?>
                                                <label class="relative inline-flex items-center cursor-pointer select-none">
                                                    <input type="checkbox" value="" class="sr-only peer toggle-status-input" data-id="<?php echo $prod['id']; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                                    <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                                                    <span class="ml-2.5 text-xs font-semibold toggle-label <?php echo $isChecked ? 'text-emerald-600' : 'text-slate-400'; ?>">
                                                        <?php echo $isChecked ? 'Ativo' : 'Esgotado'; ?>
                                                    </span>
                                                </label>
                                            </td>
                                            <td class="py-3.5 px-4 sm:px-6 text-right whitespace-nowrap">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button onclick='abrirModalEdicao(<?php echo json_encode($prod); ?>)' class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors" title="Editar">
                                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                                    </button>
                                                    <button onclick="abrirModalExclusao(<?php echo $prod['id']; ?>, '<?php echo htmlspecialchars($prod['nome'], ENT_QUOTES); ?>')" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-colors" title="Excluir">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-slate-400">Nenhum produto cadastrado ou encontrado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- MODAL DE CADASTRO / EDIÇÃO DE PRODUTO -->
    <div id="modalProduto" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-lg w-full max-h-[92vh] flex flex-col shadow-xl border border-slate-100 my-auto overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 sm:px-6 py-4 bg-white shrink-0">
                <h3 id="modalTitulo" class="text-base sm:text-lg font-bold text-slate-800">Adicionar Novo Produto</h3>
                <button onclick="fecharModalProduto()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-5 sm:p-6 overflow-y-auto flex-1 space-y-4">
                <form id="formProduto" action="produtos.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="acao" id="formAcao" value="cadastrar">
                    <input type="hidden" name="id" id="formId">
                    <input type="hidden" name="foto_atual" id="formFotoAtual">

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Nome do Produto</label>
                        <input type="text" name="nome" id="formNome" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Categoria</label>
                            <select name="categoria_id" id="formCategoria" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                                <option value="">Selecione...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Código de Barras</label>
                            <input type="text" name="codigo_barras" id="formCodigo" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Unidade de Venda</label>
                            <select name="unidade" id="formUnidade" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500" onchange="atualizarLabelEstoque()">
                                <option value="un">Unidade (un)</option>
                                <option value="kg">Quilograma (kg)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1" id="labelPreco">Preço (R$)</label>
                            <input type="text" name="preco" id="formPreco" required placeholder="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1" id="labelEstoque">Estoque</label>
                            <input type="text" step="0.001" name="estoque" id="formEstoque" value="0" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Estoque Mínimo de Alerta</label>
                            <input type="text" step="0.001" name="estoque_minimo" id="formEstoqueMinimo" value="5" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Foto do Produto</label>
                        <input type="file" name="foto" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Descrição</label>
                        <textarea name="descricao" id="formDescricao" rows="2" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500"></textarea>
                    </div>
                </form>
            </div>
            <div class="flex items-center justify-end gap-3 px-5 sm:px-6 py-4 border-t border-slate-100 bg-white shrink-0">
                <button type="button" onclick="fecharModalProduto()" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Cancelar</button>
                <button type="submit" form="formProduto" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-2.5 rounded-xl text-sm transition-colors shadow-sm shadow-orange-500/20">Salvar Produto</button>
            </div>
        </div>
    </div>

    <!-- MODAL DE GERENCIAR / CADASTRAR / EDITAR CATEGORIAS -->
    <div id="modalGerenciarCategorias" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 space-y-4 shadow-xl border border-slate-100 my-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-bold text-slate-800">Gerenciar Categorias</h3>
                <button onclick="fecharModalGerenciarCategorias()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Formulário para Adicionar Nova Categoria -->
            <form action="produtos.php" method="POST" class="space-y-2.5 bg-slate-50 p-4 rounded-xl border border-slate-200">
                <input type="hidden" name="acao" value="cadastrar_categoria">
                <p class="text-xs font-bold text-slate-700 uppercase tracking-wider">Adicionar Nova Categoria</p>
                <div class="flex gap-2">
                    <input type="text" name="nome_categoria" required placeholder="Ex: Bebidas, Açougue..." class="flex-1 px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-4 py-2.5 rounded-xl text-xs transition-colors shadow-sm whitespace-nowrap">Adicionar</button>
                </div>
            </form>

            <!-- Lista de Categorias Existentes para Editar/Excluir -->
            <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                <p class="text-xs font-bold text-slate-700 uppercase tracking-wider">Categorias Cadastradas</p>
                <?php if (count($categorias) > 0): ?>
                    <?php foreach ($categorias as $cat): ?>
                        <div class="flex items-center justify-between p-3 bg-white border border-slate-200 rounded-xl shadow-sm">
                            <span class="text-sm font-medium text-slate-800 truncate pr-2"><?php echo htmlspecialchars($cat['nome']); ?></span>
                            <div class="flex items-center gap-1 shrink-0">
                                <button onclick="prepararEdicaoCategoria(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['nome'], ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="Editar Nome">
                                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                                </button>
                                <button onclick="abrirModalExclusaoCategoria(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['nome'], ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Excluir Categoria">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-xs text-slate-400 text-center py-4">Nenhuma categoria cadastrada.</p>
                <?php endif; ?>
            </div>

            <div class="flex justify-end pt-2">
                <button type="button" onclick="fecharModalGerenciarCategorias()" class="px-4 py-2.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors">Fechar</button>
            </div>
        </div>
    </div>

    <!-- MODAL DE EDITAR CATEGORIA (NOME) -->
    <div id="modalEditarCategoria" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full p-5 sm:p-6 space-y-4 shadow-xl border border-slate-100 my-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-bold text-slate-800">Editar Categoria</h3>
                <button onclick="fecharModalEditarCategoria()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form action="produtos.php" method="POST" class="space-y-4">
                <input type="hidden" name="acao" value="editar_categoria">
                <input type="hidden" name="id_categoria" id="editIdCategoria">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Novo Nome</label>
                    <input type="text" name="nome_categoria" id="editNomeCategoria" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="fecharModalEditarCategoria()" class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Cancelar</button>
                    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-4 py-2.5 rounded-xl text-xs transition-colors shadow-sm">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE CONFIRMAÇÃO DE EXCLUSÃO (PRODUTO) -->
    <div id="modalExclusao" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full p-6 space-y-4 shadow-xl border border-slate-100 text-center my-auto">
            <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
                <i data-lucide="alert-triangle" class="w-6 h-6"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-800">Excluir Produto</h3>
                <p id="textoExclusao" class="text-xs text-slate-500 mt-1">Tem certeza que deseja remover este item permanentemente?</p>
            </div>
            <div class="flex items-center gap-2 pt-2">
                <button onclick="fecharModalExclusao()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 rounded-xl text-xs transition-colors">Cancelar</button>
                <a id="linkConfirmarExclusao" href="#" class="flex-1 bg-rose-600 hover:bg-rose-700 text-white font-semibold py-2.5 rounded-xl text-xs transition-colors text-center">Sim, Excluir</a>
            </div>
        </div>
    </div>

    <!-- MODAL DE CONFIRMAÇÃO DE EXCLUSÃO (CATEGORIA) -->
    <div id="modalExclusaoCategoria" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full p-6 space-y-4 shadow-xl border border-slate-100 text-center my-auto">
            <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
                <i data-lucide="alert-triangle" class="w-6 h-6"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-800">Excluir Categoria</h3>
                <p id="textoExclusaoCategoria" class="text-xs text-slate-500 mt-1">Deseja remover esta categoria? Os produtos vinculados ficarão sem categoria.</p>
            </div>
            <div class="flex items-center gap-2 pt-2">
                <button onclick="fecharModalExclusaoCategoria()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 rounded-xl text-xs transition-colors">Cancelar</button>
                <a id="linkConfirmarExclusaoCategoria" href="#" class="flex-1 bg-rose-600 hover:bg-rose-700 text-white font-semibold py-2.5 rounded-xl text-xs transition-colors text-center">Sim, Excluir</a>
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
            }, 300); // Pequeno delay fluido de 300ms
        });

        // Auto-remoção do aviso de sucesso após 3 segundos
        setTimeout(() => {
            const cardSucesso = document.getElementById('cardMensagemSucesso');
            if (cardSucesso) {
                cardSucesso.style.opacity = '0';
                setTimeout(() => cardSucesso.remove(), 500);
            }
        }, 3000);

        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const openBtn = document.getElementById('openSidebarBtn');
        function toggleSidebar() { if(sidebar) sidebar.classList.toggle('-translate-x-full'); if(overlay) overlay.classList.toggle('hidden'); }
        if(openBtn) openBtn.addEventListener('click', toggleSidebar);
        if(overlay) overlay.addEventListener('click', toggleSidebar);

        const modalProduto = document.getElementById('modalProduto');
        const modalGerenciarCategorias = document.getElementById('modalGerenciarCategorias');
        const modalEditarCategoria = document.getElementById('modalEditarCategoria');
        const modalExclusao = document.getElementById('modalExclusao');
        const modalExclusaoCategoria = document.getElementById('modalExclusaoCategoria');

        function atualizarLabelEstoque() {
            const unidade = document.getElementById('formUnidade').value;
            const labelPreco = document.getElementById('labelPreco');
            const labelEstoque = document.getElementById('labelEstoque');
            
            if (unidade === 'kg') {
                labelPreco.innerText = 'Preço por Kg (R$)';
                labelEstoque.innerText = 'Estoque em Kg (Ex: 3.500)';
            } else {
                labelPreco.innerText = 'Preço Unitário (R$)';
                labelEstoque.innerText = 'Estoque (Unidades)';
            }
        }

        function abrirModalCadastro() {
            document.getElementById('modalTitulo').innerText = 'Adicionar Novo Produto';
            document.getElementById('formAcao').value = 'cadastrar';
            document.getElementById('formId').value = '';
            document.getElementById('formNome').value = '';
            document.getElementById('formCategoria').value = '';
            document.getElementById('formCodigo').value = '';
            document.getElementById('formPreco').value = '';
            document.getElementById('formEstoque').value = '0';
            document.getElementById('formEstoqueMinimo').value = '5';
            document.getElementById('formUnidade').value = 'un';
            document.getElementById('formDescricao').value = '';
            document.getElementById('formFotoAtual').value = '';
            atualizarLabelEstoque();
            modalProduto.classList.remove('hidden');
        }

        function abrirModalEdicao(prod) {
            document.getElementById('modalTitulo').innerText = 'Editar Produto';
            document.getElementById('formAcao').value = 'editar';
            document.getElementById('formId').value = prod.id;
            document.getElementById('formNome').value = prod.nome;
            document.getElementById('formCategoria').value = prod.categoria_id || '';
            document.getElementById('formCodigo').value = prod.codigo_barras || '';
            document.getElementById('formPreco').value = prod.preco;
            document.getElementById('formEstoque').value = prod.estoque;
            document.getElementById('formEstoqueMinimo').value = prod.estoque_minimo || 5;
            document.getElementById('formUnidade').value = prod.unidade || 'un';
            document.getElementById('formDescricao').value = prod.descricao || '';
            document.getElementById('formFotoAtual').value = prod.foto || '';
            atualizarLabelEstoque();
            modalProduto.classList.remove('hidden');
        }

        function fecharModalProduto() { modalProduto.classList.add('hidden'); }

        function abrirModalGerenciarCategorias() { modalGerenciarCategorias.classList.remove('hidden'); }
        function fecharModalGerenciarCategorias() { modalGerenciarCategorias.classList.add('hidden'); }

        function prepararEdicaoCategoria(id, nome) {
            document.getElementById('editIdCategoria').value = id;
            document.getElementById('editNomeCategoria').value = nome;
            modalGerenciarCategorias.classList.add('hidden');
            modalEditarCategoria.classList.remove('hidden');
        }
        function fecharModalEditarCategoria() { modalEditarCategoria.classList.add('hidden'); }

        function abrirModalExclusao(id, nome) {
            document.getElementById('textoExclusao').innerText = `Tem certeza que deseja remover "${nome}" permanentemente?`;
            document.getElementById('linkConfirmarExclusao').href = `produtos.php?excluir=${id}`;
            modalExclusao.classList.remove('hidden');
        }
        function fecharModalExclusao() { modalExclusao.classList.add('hidden'); }

        function abrirModalExclusaoCategoria(id, nome) {
            document.getElementById('textoExclusaoCategoria').innerText = `Tem certeza que deseja excluir a categoria "${nome}"?`;
            document.getElementById('linkConfirmarExclusaoCategoria').href = `produtos.php?excluir_categoria=${id}`;
            modalGerenciarCategorias.classList.add('hidden');
            modalExclusaoCategoria.classList.remove('hidden');
        }
        function fecharModalExclusaoCategoria() { modalExclusaoCategoria.classList.add('hidden'); }

        // Script AJAX para troca de status do produto
        document.querySelectorAll('.toggle-status-input').forEach(input => {
            input.addEventListener('change', function() {
                const produtoId = this.getAttribute('data-id');
                const novoStatus = this.checked ? 'disponivel' : 'esgotado';
                const labelSpan = this.parentElement.querySelector('.toggle-label');

                if (this.checked) {
                    labelSpan.textContent = 'Ativo';
                    labelSpan.className = 'ml-2.5 text-xs font-semibold text-emerald-600 toggle-label';
                } else {
                    labelSpan.textContent = 'Esgotado';
                    labelSpan.className = 'ml-2.5 text-xs font-semibold text-slate-400 toggle-label';
                }

                const formData = new FormData();
                formData.append('acao_ajax', 'toggle_status');
                formData.append('id', produtoId);
                formData.append('status_produto', novoStatus);

                fetch('produtos.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.sucesso) {
                        console.error('Erro ao atualizar status.');
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                });
            });
        });
    </script>
</body>
</html>