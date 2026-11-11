<?php
// mercado/api_index.php
header('Content-Type: application/json; charset=utf-8');

require_once '../admin/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$loja_id = 4;

// Buscar configurações da loja
$stmtLojaConfig = $pdo->prepare("SELECT * FROM lojas WHERE id = ?");
$stmtLojaConfig->execute([$loja_id]);
$loja = $stmtLojaConfig->fetch(PDO::FETCH_ASSOC);

if (!$loja) {
    echo json_encode(['sucesso' => false, 'erro' => 'Loja não encontrada.']);
    exit;
}

$ordenacaoAtual = $loja['ordenacao_produtos'] ?? 'az';

// Função para verificar se a loja está aberta (consistente com o modal de informações)
function verificarLojaAberta($loja) {
    if ($loja['forcar_fechado'] == 1 || $loja['status_loja'] != 'ativo') {
        return false;
    }

    date_default_timezone_set('America/Manaus');
    $diaAtualInglesMap = [
        'Monday' => 'segunda',
        'Tuesday' => 'terca',
        'Wednesday' => 'quarta',
        'Thursday' => 'quinta',
        'Friday' => 'sexta',
        'Saturday' => 'sabado',
        'Sunday' => 'domingo'
    ];
    $diaHojeKey = $diaAtualInglesMap[date('l')] ?? '';
    $horaAtual = date('H:i:s');

    $horariosDias = [];
    if (!empty($loja['horarios_funcionamento'])) {
        $horariosDias = json_decode($loja['horarios_funcionamento'], true);
    }

    // Se houver configuração detalhada dia a dia
    if (isset($horariosDias[$diaHojeKey])) {
        $infoDia = $horariosDias[$diaHojeKey];
        if (!isset($infoDia['ativo']) || intval($infoDia['ativo']) !== 1) {
            return false;
        }
        $abertura = $infoDia['abertura'] ?? '08:00';
        $fechamento = $infoDia['fechamento'] ?? '18:00';

        // Verificar horário de almoço se houver
        if (!empty($infoDia['tem_almoco']) && intval($infoDia['tem_almoco']) === 1) {
            $almocoInicio = $infoDia['almoco_inicio'] ?? '';
            $almocoFim = $infoDia['almoco_fim'] ?? '';
            if (!empty($almocoInicio) && !empty($almocoFim) && $horaAtual >= $almocoInicio && $horaAtual <= $almocoFim) {
                return false;
            }
        }

        return ($horaAtual >= $abertura && $horaAtual <= $fechamento);
    }

    // Fallback para horários gerais padrão da tabela lojas
    if ($horaAtual < $loja['horario_abertura'] || $horaAtual > $loja['horario_fechamento']) {
        return false;
    }

    return true;
}

$lojaAberta = verificarLojaAberta($loja);

// AÇÃO 1: Filtrar / Buscar Produtos via AJAX
if (isset($_GET['ajax_produtos'])) {
    $categoriaFiltro = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

    $sqlCat = "SELECT * FROM categorias WHERE loja_id = ?";
    $paramsCat = [$loja_id];
    if ($categoriaFiltro > 0) {
        $sqlCat .= " AND id = ?";
        $paramsCat[] = $categoriaFiltro;
    }
    $sqlCat .= " ORDER BY destaque DESC, ordem ASC, nome ASC";
    
    $stmtCat = $pdo->prepare($sqlCat);
    $stmtCat->execute($paramsCat);
    $categoriasVitrine = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    $html = '';

    if (count($categoriasVitrine) > 0) {
        foreach ($categoriasVitrine as $cat) {
            $catId = $cat['id'];
            $catNome = $cat['nome'];

            $sqlProd = "
                SELECT p.*, 
                (SELECT COALESCE(SUM(ip.quantidade), 0) FROM itens_pedido ip WHERE ip.produto_id = p.id) as total_vendido
                FROM produtos p
                WHERE p.loja_id = ? AND p.categoria_id = ? AND p.status_produto != 'inativo'
            ";
            $paramsProd = [$loja_id, $catId];

            if (!empty($busca)) {
                $sqlProd .= " AND (p.nome LIKE ? OR p.codigo_barras LIKE ?)";
                $paramsProd[] = "%{$busca}%";
                $paramsProd[] = "%{$busca}%";
            }

            switch ($ordenacaoAtual) {
                case 'za':
                    $sqlProd .= " ORDER BY p.nome DESC";
                    break;
                case 'mais_vendidos':
                    $sqlProd .= " ORDER BY total_vendido DESC, p.nome ASC";
                    break;
                case 'menor_preco':
                    $sqlProd .= " ORDER BY p.preco ASC";
                    break;
                case 'maior_preco':
                    $sqlProd .= " ORDER BY p.preco DESC";
                    break;
                case 'az':
                default:
                    $sqlProd .= " ORDER BY p.nome ASC";
                    break;
            }

            $stmtProd = $pdo->prepare($sqlProd);
            $stmtProd->execute($paramsProd);
            $produtosCat = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

            if (count($produtosCat) > 0) {
                $html .= '<div class="space-y-3 mb-6">';
                $html .= '<div class="flex items-center gap-2 border-b border-slate-200 pb-2">';
                if ($cat['destaque'] == 1) {
                    $html .= '<i data-lucide="star" class="w-4 h-4 text-amber-500 fill-amber-500"></i>';
                } else {
                    $html .= '<i data-lucide="folder" class="w-4 h-4 text-orange-500"></i>';
                }
                $html .= '<h3 class="font-bold text-slate-800 text-base">' . htmlspecialchars($catNome) . '</h3>';
                if ($cat['destaque'] == 1) {
                    $html .= '<span class="text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full uppercase tracking-wider">Destaque</span>';
                }
                $html .= '</div>';

                $html .= '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">';
                foreach ($produtosCat as $prod) {
                    $esgotado = ($prod['status_produto'] === 'esgotado');
                    $unidade = $prod['unidade'] ?? 'un';
                    $siglaUnidade = ($unidade === 'kg') ? 'kg' : 'un';
                    
                    $fotoUrl = '';
                    if (!empty($prod['foto'])) {
                        if (file_exists('../' . $prod['foto'])) {
                            $fotoUrl = '../' . $prod['foto'];
                        } elseif (file_exists($prod['foto'])) {
                            $fotoUrl = $prod['foto'];
                        }
                    }
                    
                    $html .= '<div id="card-produto-' . $prod['id'] . '"';
                    $html .= ' data-id="' . $prod['id'] . '"';
                    $html .= ' data-nome="' . htmlspecialchars($prod['nome'], ENT_QUOTES) . '"';
                    $html .= ' data-preco="' . $prod['preco'] . '"';
                    $html .= ' data-unidade="' . $unidade . '"';
                    $html .= ' data-status="' . $prod['status_produto'] . '"';
                    $html .= ' data-foto="' . htmlspecialchars($prod['foto'] ?? '', ENT_QUOTES) . '"';
                    $html .= ' data-descricao="' . htmlspecialchars($prod['descricao'] ?? 'Produto fresco e selecionado.', ENT_QUOTES) . '"';
                    $html .= ' onclick="clicarCardProduto(this)"';
                    $html .= ' class="produto-card bg-white p-4 rounded-2xl border border-slate-200 shadow-sm transition-all flex items-center justify-between gap-4 group relative ' . ($esgotado ? 'opacity-60 bg-slate-50/80 cursor-not-allowed' : 'hover:border-orange-300 hover:shadow-md cursor-pointer') . '">';
                    
                    $html .= '<div class="selo-esgotado-container">';
                    if ($esgotado) {
                        $html .= '<span class="selo-esgotado absolute top-3 right-3 bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-md uppercase tracking-wider shadow-sm">Esgotado</span>';
                    }
                    $html .= '</div>';

                    $html .= '<div class="w-20 h-20 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 overflow-hidden shrink-0 border border-slate-100">';
                    if (!empty($fotoUrl)) {
                        $html .= '<img src="' . htmlspecialchars($fotoUrl) . '" alt="" loading="lazy" decoding="async" class="w-full h-full object-cover">';
                    } else {
                        $html .= '<i data-lucide="package" class="w-6 h-6 text-slate-400"></i>';
                    }
                    $html .= '</div>';

                    $html .= '<div class="flex-1 min-w-0">';
                    $html .= '<h4 class="font-bold text-slate-800 text-sm truncate ' . (!$esgotado ? 'group-hover:text-orange-600' : '') . ' transition-colors">' . htmlspecialchars($prod['nome']) . '</h4>';
                    $html .= '<p class="text-xs text-slate-400 mt-0.5 line-clamp-1">' . htmlspecialchars($prod['descricao'] ?? 'Produto fresco e selecionado.') . '</p>';
                    $html .= '<p class="text-sm font-bold text-slate-800 mt-2">R$ ' . number_format($prod['preco'], 2, ',', '.') . ' <span class="text-xs font-normal text-slate-400">/' . $siglaUnidade . '</span></p>';
                    $html .= '</div>';

                    $html .= '</div>';
                }
                $html .= '</div>';
                $html .= '</div>';
            }
        }
    }

    if (empty($html)) {
        $html .= '<div class="bg-white p-12 rounded-2xl border border-slate-200 text-center space-y-2">';
        $html .= '<i data-lucide="search-x" class="w-10 h-10 text-slate-300 mx-auto"></i>';
        $html .= '<p class="text-slate-600 font-medium">Nenhum produto encontrado.</p>';
        $html .= '<p class="text-xs text-slate-400">Tente buscar por outro termo ou categoria.</p>';
        $html .= '</div>';
    }

    echo json_encode(['sucesso' => true, 'html' => $html]);
    exit;
}

// AÇÃO 2: Adicionar Produto à Sacola via AJAX
if (isset($_POST['ajax_adicionar']) && isset($_POST['produto_id']) && isset($_POST['quantidade'])) {
    $produtoId = intval($_POST['produto_id']);
    $quantidade = floatval(str_replace(',', '.', $_POST['quantidade']));

    if ($produtoId > 0 && $quantidade > 0) {
        $stmtProd = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND loja_id = ?");
        $stmtProd->execute([$produtoId, $loja_id]);
        $produtoInfo = $stmtProd->fetch(PDO::FETCH_ASSOC);

        if ($produtoInfo && $produtoInfo['status_produto'] === 'disponivel') {
            if (!isset($_SESSION['sacola'])) {
                $_SESSION['sacola'] = [];
            }

            if (isset($_SESSION['sacola'][$produtoId])) {
                $_SESSION['sacola'][$produtoId]['quantidade'] += $quantidade;
            } else {
                $_SESSION['sacola'][$produtoId] = [
                    'id' => $produtoInfo['id'],
                    'nome' => $produtoInfo['nome'],
                    'preco' => $produtoInfo['preco'],
                    'unidade' => $produtoInfo['unidade'] ?? 'un',
                    'foto' => $produtoInfo['foto'],
                    'quantidade' => $quantidade
                ];
            }
        }
    }

    $totalItensSacola = 0;
    $valorTotalSacola = 0;
    if (isset($_SESSION['sacola'])) {
        foreach ($_SESSION['sacola'] as $item) {
            $totalItensSacola += 1;
            $valorTotalSacola += ($item['preco'] * $item['quantidade']);
        }
    }

    echo json_encode([
        'sucesso' => true,
        'totalItens' => count($_SESSION['sacola']),
        'valorTotal' => number_format($valorTotalSacola, 2, ',', '.')
    ]);
    exit;
}

// AÇÃO 3: Sincronização em Tempo Real (Estoque + Status de Abertura Dinâmico)
if (isset($_GET['ajax_status_tempo_real'])) {
    $stmtProdStatus = $pdo->prepare("SELECT id, status_produto FROM produtos WHERE loja_id = ?");
    $stmtProdStatus->execute([$loja_id]);
    $produtosDB = $stmtProdStatus->fetchAll(PDO::FETCH_ASSOC);

    $produtosStatusMap = [];
    foreach ($produtosDB as $p) {
        $esgotado = ($p['status_produto'] === 'esgotado');
        $produtosStatusMap[$p['id']] = [
            'status_produto' => $p['status_produto'],
            'esgotado' => $esgotado
        ];
    }

    echo json_encode([
        'sucesso' => true,
        'lojaAberta' => $lojaAberta,
        'produtos' => $produtosStatusMap
    ]);
    exit;
}