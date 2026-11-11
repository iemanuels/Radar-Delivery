<?php
// mercado/index.php
$currentPage = 'vitrine';

require_once '../admin/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$loja_id = 4;

// Buscar dados da loja para renderização inicial
$stmtLojaConfig = $pdo->prepare("SELECT * FROM lojas WHERE id = ?");
$stmtLojaConfig->execute([$loja_id]);
$loja = $stmtLojaConfig->fetch(PDO::FETCH_ASSOC);

$nomeLoja = $loja ? $loja['nome_loja'] : 'Padaria & Mercado';
$whatsappLoja = $loja['whatsapp'] ?? '';

$horariosDias = [];
if ($loja && !empty($loja['horarios_funcionamento'])) {
    $horariosDias = json_decode($loja['horarios_funcionamento'], true);
}

// Função de verificação alinhada com o modal
function verificarLojaAbertaInicial($loja) {
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

    if (isset($horariosDias[$diaHojeKey])) {
        $infoDia = $horariosDias[$diaHojeKey];
        if (!isset($infoDia['ativo']) || intval($infoDia['ativo']) !== 1) {
            return false;
        }
        $abertura = $infoDia['abertura'] ?? '08:00';
        $fechamento = $infoDia['fechamento'] ?? '18:00';

        if (!empty($infoDia['tem_almoco']) && intval($infoDia['tem_almoco']) === 1) {
            $almocoInicio = $infoDia['almoco_inicio'] ?? '';
            $almocoFim = $infoDia['almoco_fim'] ?? '';
            if (!empty($almocoInicio) && !empty($almocoFim) && $horaAtual >= $almocoInicio && $horaAtual <= $almocoFim) {
                return false;
            }
        }

        return ($horaAtual >= $abertura && $horaAtual <= $fechamento);
    }

    if ($horaAtual < $loja['horario_abertura'] || $horaAtual > $loja['horario_fechamento']) {
        return false;
    }

    return true;
}

$lojaAberta = $loja ? verificarLojaAbertaInicial($loja) : false;

// Contar itens na sacola
$totalItensSacola = isset($_SESSION['sacola']) ? count($_SESSION['sacola']) : 0;
$valorTotalSacola = 0;
if (isset($_SESSION['sacola'])) {
    foreach ($_SESSION['sacola'] as $item) {
        $valorTotalSacola += ($item['preco'] * $item['quantidade']);
    }
}

// Logo e Favicon
$logoLoja = '';
if (!empty($loja['logo'])) {
    if (file_exists('../' . $loja['logo'])) {
        $logoLoja = '../' . $loja['logo'];
    } elseif (file_exists($loja['logo'])) {
        $logoLoja = $loja['logo'];
    }
}

// Buscar Categorias para os botões de filtro rápido
$stmtCategorias = $pdo->prepare("SELECT * FROM categorias WHERE loja_id = ? ORDER BY destaque DESC, ordem ASC, nome ASC");
$stmtCategorias->execute([$loja_id]);
$categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nomeLoja); ?> | Delivery</title>
    
    <?php if (!empty($logoLoja)): ?>
        <link rel="icon" type="image/webp" href="<?php echo htmlspecialchars($logoLoja); ?>">
    <?php endif; ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .scrollbar-none::-webkit-scrollbar { display: none; }
        .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Topbar do Cliente -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-500 flex items-center justify-center text-white font-bold shadow-md shadow-orange-500/20 overflow-hidden shrink-0">
                    <?php if (!empty($logoLoja)): ?>
                        <img src="<?php echo htmlspecialchars($logoLoja); ?>" alt="Logo" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i data-lucide="store" class="w-5 h-5"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <h1 class="font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($nomeLoja); ?></h1>
                        <button onclick="abrirModalInfoLoja()" class="text-slate-400 hover:text-orange-500 transition-colors p-0.5 rounded-full" title="Informações da Loja">
                            <i data-lucide="info" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div id="statusLojaBadge" class="flex items-center gap-1.5 text-xs font-medium <?php echo $lojaAberta ? 'text-emerald-600' : 'text-rose-600'; ?>">
                        <span id="statusLojaPonto" class="w-2 h-2 rounded-full <?php echo $lojaAberta ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'; ?>"></span> 
                        <span id="statusLojaTexto"><?php echo $lojaAberta ? 'Aberto' : 'Fechado no momento'; ?></span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="abrirModalAcompanhar()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3.5 py-2 rounded-xl text-sm font-semibold transition-colors flex items-center gap-1.5">
                    <i data-lucide="map-pin" class="w-4 h-4 text-orange-500"></i>
                    <span class="hidden sm:inline">Acompanhar</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Aviso de Loja Fechada (Banner Superior Opcional) -->
    <div id="avisoLojaFechada" class="<?php echo $lojaAberta ? 'hidden' : ''; ?> bg-rose-500 text-white text-xs font-semibold py-2 px-4 text-center sticky top-16 z-20 shadow-sm flex items-center justify-center gap-2">
        <i data-lucide="clock" class="w-4 h-4"></i>
        <span>Estamos fechados no momento. Não é possível realizar novos pedidos agora.</span>
    </div>

    <!-- Container de Filtros e Busca Fixos -->
    <div class="sticky top-16 z-20 bg-slate-50/95 backdrop-blur-md pt-3 pb-2 border-b border-slate-200/60 shadow-sm transition-all">
        <main class="max-w-4xl w-full mx-auto px-4 sm:px-6 space-y-2.5">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </span>
                <input type="text" id="inputBusca" placeholder="Buscar carnes, pães, bebidas..." oninput="filtrarProdutosDinamico()" class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-base sm:text-sm focus:outline-none focus:border-orange-500 shadow-sm">
            </div>

            <div id="listaCategorias" class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
                <button type="button" onclick="selecionarCategoria(0, this)" class="categoria-btn px-4 py-2 rounded-xl text-sm font-semibold transition-colors shrink-0 bg-orange-500 text-white shadow-sm shadow-orange-500/20 flex items-center gap-1.5" data-id="0">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i> Todos
                </button>
                <?php foreach ($categorias as $cat): ?>
                    <button type="button" onclick="selecionarCategoria(<?php echo $cat['id']; ?>, this)" class="categoria-btn px-4 py-2 rounded-xl text-sm font-medium transition-colors shrink-0 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 flex items-center gap-1.5" data-id="<?php echo $cat['id']; ?>">
                        <?php if ($cat['destaque'] == 1): ?>
                            <i data-lucide="star" class="w-3.5 h-3.5 text-amber-500 fill-amber-500"></i>
                        <?php else: ?>
                            <i data-lucide="folder" class="w-3.5 h-3.5 text-slate-400"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($cat['nome']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- Conteúdo Principal / Prateleiras por Categoria -->
    <main class="flex-1 max-w-4xl w-full mx-auto p-4 sm:p-6 space-y-6">
        <div id="container-categorias-produtos">
            <!-- Carregado via api_index.php -->
        </div>
    </main>

    <!-- BOTÃO WHATSAPP -->
    <?php if (!empty($whatsappLoja)): ?>
        <?php 
            $whatsappLimpo = preg_replace('/\D/', '', $whatsappLoja);
            if (strlen($whatsappLimpo) <= 11 && substr($whatsappLimpo, 0, 2) !== '55') {
                $whatsappLimpo = '55' . $whatsappLimpo;
            }
        ?>
        <a href="https://wa.me/<?php echo htmlspecialchars($whatsappLimpo); ?>?text=Olá,%20gostaria%20de%20tirar%20uma%20dúvida%20sobre%20o%20delivery." target="_blank" class="fixed bottom-20 right-4 z-40 bg-emerald-500 hover:bg-emerald-600 text-white p-3.5 rounded-full shadow-lg shadow-emerald-500/30 transition-all flex items-center justify-center group" title="Fale conosco pelo WhatsApp">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
            </svg>
        </a>
    <?php endif; ?>

    <!-- MODAL DE INFORMAÇÕES DA LOJA (SINCRONIZADO) -->
    <div id="modalInfoLoja" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-xl border border-slate-100 my-auto overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-white shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center font-bold">
                        <i data-lucide="store" class="w-4 h-4"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Sobre a Loja</h3>
                </div>
                <button onclick="fecharModalInfoLoja()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1 space-y-5 text-sm">
                <div class="flex flex-col items-center justify-center text-center pb-4 border-b border-slate-100">
                    <div class="w-20 h-20 rounded-2xl bg-orange-500 flex items-center justify-center text-white font-bold shadow-md shadow-orange-500/20 overflow-hidden mb-3 border-2 border-slate-100">
                        <?php if (!empty($logoLoja)): ?>
                            <img src="<?php echo htmlspecialchars($logoLoja); ?>" alt="Logo da Loja" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i data-lucide="store" class="w-8 h-8"></i>
                        <?php endif; ?>
                    </div>
                    <h4 class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($nomeLoja); ?></h4>
                    <p class="text-xs text-slate-400 mt-0.5">Seu delivery de confiança</p>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-slate-600 font-medium">Status do Estabelecimento</span>
                        <span id="modalStatusLojaBadge" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $lojaAberta ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                            <span id="modalStatusLojaPonto" class="w-1.5 h-1.5 rounded-full <?php echo $lojaAberta ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'; ?>"></span>
                            <span id="modalStatusLojaTexto"><?php echo $lojaAberta ? 'Aberto Agora' : 'Fechado'; ?></span>
                        </span>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center shrink-0 mt-0.5">
                            <i data-lucide="map-pin" class="w-4 h-4 text-orange-500"></i>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Endereço</p>
                            <p class="text-slate-700 font-medium mt-0.5"><?php echo htmlspecialchars($loja['endereco'] ?? 'Endereço não informado'); ?></p>
                        </div>
                    </div>

                    <!-- Horários de Funcionamento Detalhados -->
                    <div class="space-y-2 pt-2 border-t border-slate-100">
                        <div class="flex items-center gap-2 mb-3">
                            <i data-lucide="clock" class="w-4 h-4 text-orange-500"></i>
                            <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Horários de Funcionamento</h5>
                        </div>

                        <?php 
                        $diasSemanaNomes = [
                            'segunda' => 'Segunda-feira',
                            'terca' => 'Terça-feira',
                            'quarta' => 'Quarta-feira',
                            'quinta' => 'Quinta-feira',
                            'sexta' => 'Sexta-feira',
                            'sabado' => 'Sábado',
                            'domingo' => 'Domingo'
                        ];

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
                        ?>

                        <div class="space-y-2">
                            <?php foreach ($diasSemanaNomes as $chaveDia => $nomeDia): 
                                $infoDia = $horariosDias[$chaveDia] ?? null;
                                $isHoje = ($chaveDia === $diaHojeKey);
                            ?>
                                <div class="flex items-center justify-between p-2.5 rounded-xl border <?php echo $isHoje ? 'bg-orange-50/70 border-orange-200 font-semibold' : 'bg-slate-50/60 border-slate-100 text-slate-600'; ?>">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs <?php echo $isHoje ? 'text-orange-800 font-bold' : 'text-slate-700'; ?>">
                                            <?php echo $nomeDia; ?>
                                            <?php if ($isHoje): ?>
                                                <span class="ml-1 text-[10px] bg-orange-500 text-white px-1.5 py-0.5 rounded-md uppercase">Hoje</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="text-right text-xs">
                                        <?php if ($infoDia && isset($infoDia['ativo']) && intval($infoDia['ativo']) === 1): ?>
                                            <span class="text-slate-700 font-medium">
                                                <?php echo htmlspecialchars($infoDia['abertura'] ?? '08:00'); ?> às <?php echo htmlspecialchars($infoDia['fechamento'] ?? '18:00'); ?>
                                            </span>
                                            <?php if (!empty($infoDia['tem_almoco']) && intval($infoDia['tem_almoco']) === 1): ?>
                                                <span class="block text-[10px] text-slate-400">Almoço: <?php echo htmlspecialchars($infoDia['almoco_inicio']); ?> às <?php echo htmlspecialchars($infoDia['almoco_fim']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-rose-500 font-medium">Fechado</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-white shrink-0">
                <button type="button" onclick="fecharModalInfoLoja()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL DE QUANTIDADE / PESO -->
    <div id="modalQuantidade" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] flex flex-col shadow-xl border border-slate-100 my-auto overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-white shrink-0">
                <h3 id="modalTituloQuantidade" class="text-base font-bold text-slate-800">Adicionar à Sacola</h3>
                <button onclick="fecharModalQuantidade()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-1 space-y-4">
                <div class="flex items-center gap-4">
                    <div id="modalFotoContainer" class="w-16 h-16 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 overflow-hidden shrink-0 border border-slate-100">
                        <i data-lucide="package" class="w-6 h-6 text-slate-400"></i>
                    </div>
                    <div>
                        <h4 id="modalNomeProduto" class="font-bold text-slate-800 text-sm">Nome do Produto</h4>
                        <p id="modalPrecoUnitarioTexto" class="text-xs text-orange-600 font-semibold mt-0.5">R$ 0,00</p>
                    </div>
                </div>
                <div class="border-t border-slate-100 pt-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span id="labelQuantidadeTitulo" class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Quantidade</span>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="alterarQtd(-1)" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center font-bold transition-colors">
                                <i data-lucide="minus" class="w-4 h-4"></i>
                            </button>
                            <input type="text" id="inputQuantidade" value="1" oninput="calcularTotalRealTime()" class="w-20 text-center font-bold text-slate-800 bg-slate-50 border border-slate-200 rounded-xl py-1.5 text-base sm:text-sm focus:outline-none focus:border-orange-500">
                            <button type="button" onclick="alterarQtd(1)" class="w-9 h-9 rounded-xl bg-orange-50 hover:bg-orange-100 text-orange-600 flex items-center justify-center font-bold transition-colors">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    <div id="atalhosKgContainer" class="hidden grid grid-cols-4 gap-2 pt-1">
                        <button type="button" onclick="definirPeso(0.250)" class="bg-slate-100 hover:bg-orange-50 hover:text-orange-600 text-slate-700 text-xs font-semibold py-1.5 rounded-lg transition-colors">250g</button>
                        <button type="button" onclick="definirPeso(0.500)" class="bg-slate-100 hover:bg-orange-50 hover:text-orange-600 text-slate-700 text-xs font-semibold py-1.5 rounded-lg transition-colors">500g</button>
                        <button type="button" onclick="definirPeso(1.000)" class="bg-slate-100 hover:bg-orange-50 hover:text-orange-600 text-slate-700 text-xs font-semibold py-1.5 rounded-lg transition-colors">1 kg</button>
                        <button type="button" onclick="definirPeso(2.000)" class="bg-slate-100 hover:bg-orange-50 hover:text-orange-600 text-slate-700 text-xs font-semibold py-1.5 rounded-lg transition-colors">2 kg</button>
                    </div>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total do Item</span>
                    <span id="modalTotalCalculado" class="text-lg font-bold text-slate-800">R$ 0,00</span>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-white shrink-0">
                <button type="button" onclick="fecharModalQuantidade()" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Cancelar</button>
                <button type="button" id="btnConfirmarAdicao" onclick="confirmarAdicaoCarrinho()" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-2 rounded-xl text-sm transition-colors shadow-sm shadow-orange-500/20 flex items-center gap-2">
                    <i data-lucide="shopping-bag" class="w-4 h-4"></i> Adicionar
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL PARA ACOMPANHAR PEDIDOS -->
    <div id="modalAcompanhar" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4">
        <div class="bg-white rounded-2xl max-w-xs w-full p-5 shadow-xl border border-slate-100 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800">Acompanhar Pedido</h3>
                <button onclick="fecharModalAcompanhar()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <input type="text" id="inputTelefoneAcompanhar" placeholder="(00)00000-0000" maxlength="14" oninput="mascaraTelefone(this)" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-base sm:text-sm focus:outline-none focus:border-orange-500 font-semibold text-center tracking-wider">
            <button type="button" onclick="irParaLocalizacao()" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors shadow-sm shadow-orange-500/20 flex items-center justify-center gap-1.5">
                Ver <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </div>
    </div>

    <!-- Rodapé Flutuante do Carrinho -->
    <div class="sticky bottom-0 bg-white border-t border-slate-200 p-4 shadow-lg z-20">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <div>
                <p id="footerQtdItens" class="text-xs text-slate-400"><?php echo $totalItensSacola; ?> itens na sacola</p>
                <p id="footerValorTotal" class="text-base font-bold text-slate-800">R$ <?php echo number_format($valorTotalSacola, 2, ',', '.'); ?></p>
            </div>
            <a href="carrinho.php" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-3 rounded-xl text-sm shadow-md shadow-orange-500/20 transition-colors flex items-center gap-2">
                Ver Sacola <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>
    </div>

    <!-- Script de Controle Dinâmico -->
    <script>
        lucide.createIcons();

        let produtoSelecionadoId = null;
        let precoUnitarioAtual = 0;
        let unidadeProdutoAtual = 'un';
        let categoriaAtual = 0;
        let timeoutBusca = null;
        let lojaEstaAbertaGlobal = <?php echo $lojaAberta ? 'true' : 'false'; ?>;

        const containerCategoriasProdutos = document.getElementById('container-categorias-produtos');
        const modalQuantidade = document.getElementById('modalQuantidade');
        const modalInfoLoja = document.getElementById('modalInfoLoja');
        const modalNomeProduto = document.getElementById('modalNomeProduto');
        const modalPrecoUnitarioTexto = document.getElementById('modalPrecoUnitarioTexto');
        const modalTotalCalculado = document.getElementById('modalTotalCalculado');
        const inputQuantidade = document.getElementById('inputQuantidade');
        const modalFotoContainer = document.getElementById('modalFotoContainer');
        const atalhosKgContainer = document.getElementById('atalhosKgContainer');
        const labelQuantidadeTitulo = document.getElementById('labelQuantidadeTitulo');
        const modalAcompanhar = document.getElementById('modalAcompanhar');
        const inputTelefoneAcompanhar = document.getElementById('inputTelefoneAcompanhar');
        const inputBusca = document.getElementById('inputBusca');
        const avisoLojaFechada = document.getElementById('avisoLojaFechada');

        function mostrarSkeletons() {
            let htmlSkeleton = '';
            for (let i = 0; i < 2; i++) {
                htmlSkeleton += `
                    <div class="space-y-3 mb-6 animate-pulse">
                        <div class="h-5 bg-slate-200 rounded w-1/4 mb-3"></div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between gap-4">
                                <div class="w-20 h-20 bg-slate-200 rounded-xl shrink-0"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                                    <div class="h-3 bg-slate-200 rounded w-1/2"></div>
                                    <div class="h-4 bg-slate-200 rounded w-1/4 mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            containerCategoriasProdutos.innerHTML = htmlSkeleton;
        }

        function carregarProdutosDinamicos() {
            mostrarSkeletons();
            const buscaVal = inputBusca.value.trim();
            let url = `api_index.php?ajax_produtos=1&categoria=${categoriaAtual}&busca=${encodeURIComponent(buscaVal)}`;

            fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    containerCategoriasProdutos.innerHTML = data.html;
                    lucide.createIcons();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar produtos:', error);
                containerCategoriasProdutos.innerHTML = '<div class="text-center text-slate-500 py-8">Erro ao carregar os produtos. Tente novamente.</div>';
            });
        }

        function selecionarCategoria(idCat, elementoBtn) {
            categoriaAtual = idCat;
            document.querySelectorAll('.categoria-btn').forEach(btn => {
                btn.classList.remove('bg-orange-500', 'text-white', 'shadow-sm', 'shadow-orange-500/20', 'font-semibold');
                btn.classList.add('bg-white', 'border', 'border-slate-200', 'text-slate-600', 'hover:bg-slate-50', 'font-medium');
            });
            elementoBtn.classList.remove('bg-white', 'border', 'border-slate-200', 'text-slate-600', 'hover:bg-slate-50', 'font-medium');
            elementoBtn.classList.add('bg-orange-500', 'text-white', 'shadow-sm', 'shadow-orange-500/20', 'font-semibold');
            carregarProdutosDinamicos();
        }

        function filtrarProdutosDinamico() {
            clearTimeout(timeoutBusca);
            timeoutBusca = setTimeout(() => {
                carregarProdutosDinamicos();
            }, 300);
        }

        carregarProdutosDinamicos();

        function abrirModalInfoLoja() { modalInfoLoja.classList.remove('hidden'); }
        function fecharModalInfoLoja() { modalInfoLoja.classList.add('hidden'); }

        function clicarCardProduto(card) {
            if (!lojaEstaAbertaGlobal) {
                alert('A loja está fechada no momento. Não é possível adicionar itens ao carrinho.');
                return;
            }

            const esgotado = card.getAttribute('data-status') === 'esgotado';
            if (esgotado) return;

            produtoSelecionadoId = card.getAttribute('data-id');
            precoUnitarioAtual = parseFloat(card.getAttribute('data-preco'));
            unidadeProdutoAtual = card.getAttribute('data-unidade') || 'un';
            const nome = card.getAttribute('data-nome');
            const foto = card.getAttribute('data-foto');

            modalNomeProduto.innerText = nome;
            modalPrecoUnitarioTexto.innerText = `R$ ${precoUnitarioAtual.toFixed(2).replace('.', ',')} /${unidadeProdutoAtual}`;

            if (unidadeProdutoAtual === 'kg') {
                labelQuantidadeTitulo.innerText = 'Quantidade (Quilos)';
                inputQuantidade.value = '1,000';
                atalhosKgContainer.classList.remove('hidden');
            } else {
                labelQuantidadeTitulo.innerText = 'Quantidade (Unidades)';
                inputQuantidade.value = '1';
                atalhosKgContainer.classList.add('hidden');
            }

            if (foto) {
                modalFotoContainer.innerHTML = `<img src="../${foto}" loading="lazy" decoding="async" class="w-full h-full object-cover">`;
            } else {
                modalFotoContainer.innerHTML = `<i data-lucide="package" class="w-6 h-6 text-slate-400"></i>`;
                lucide.createIcons();
            }

            calcularTotalRealTime();
            modalQuantidade.classList.remove('hidden');
        }

        function fecharModalQuantidade() { modalQuantidade.classList.add('hidden'); }

        function alterarQtd(valor) {
            if (unidadeProdutoAtual === 'kg') {
                let atual = parseFloat(inputQuantidade.value.replace(',', '.')) || 1.000;
                atual += (valor * 0.500);
                if (atual < 0.100) atual = 0.100;
                inputQuantidade.value = atual.toFixed(3).replace('.', ',');
            } else {
                let qtdAtual = parseInt(inputQuantidade.value) || 1;
                qtdAtual += valor;
                if (qtdAtual < 1) qtdAtual = 1;
                inputQuantidade.value = qtdAtual;
            }
            calcularTotalRealTime();
        }

        function definirPeso(pesoKg) {
            inputQuantidade.value = pesoKg.toFixed(3).replace('.', ',');
            calcularTotalRealTime();
        }

        function calcularTotalRealTime() {
            let valStr = inputQuantidade.value.replace(',', '.');
            let qtd = parseFloat(valStr) || 0;
            if (qtd < 0) qtd = 0;
            let total = qtd * precoUnitarioAtual;
            modalTotalCalculado.innerText = `R$ ${total.toFixed(2).replace('.', ',')}`;
        }

        function confirmarAdicaoCarrinho() {
            if (!lojaEstaAbertaGlobal) {
                alert('A loja está fechada no momento.');
                fecharModalQuantidade();
                return;
            }

            let valStr = inputQuantidade.value.replace(',', '.');
            let qtdFinal = parseFloat(valStr) || 0;

            if (qtdFinal <= 0) {
                alert('Informe uma quantidade válida.');
                return;
            }

            let formData = new URLSearchParams();
            formData.append('ajax_adicionar', '1');
            formData.append('produto_id', produtoSelecionadoId);
            formData.append('quantidade', qtdFinal);

            fetch('api_index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    document.getElementById('footerQtdItens').innerText = `${data.totalItens} itens na sacola`;
                    document.getElementById('footerValorTotal').innerText = `R$ ${data.valorTotal}`;
                    fecharModalQuantidade();

                    const footer = document.querySelector('.sticky.bottom-0');
                    footer.classList.add('bg-orange-50');
                    setTimeout(() => footer.classList.remove('bg-orange-50'), 400);
                } else {
                    alert(data.mensagem || 'Não foi possível adicionar o item.');
                }
            })
            .catch(error => console.error('Erro ao adicionar:', error));
        }

        // Sincronização automática de status da loja e estoque em tempo real
        function atualizarDadosEmTempoReal() {
            fetch('api_index.php?ajax_status_tempo_real=1')
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    lojaEstaAbertaGlobal = data.lojaAberta;
                    const statusBadge = document.getElementById('statusLojaBadge');
                    const statusPonto = document.getElementById('statusLojaPonto');
                    const statusTexto = document.getElementById('statusLojaTexto');

                    const modalBadge = document.getElementById('modalStatusLojaBadge');
                    const modalPonto = document.getElementById('modalStatusLojaPonto');
                    const modalTexto = document.getElementById('modalStatusLojaTexto');

                    if (lojaEstaAbertaGlobal) {
                        statusBadge.className = "flex items-center gap-1.5 text-xs font-medium text-emerald-600";
                        statusPonto.className = "w-2 h-2 rounded-full bg-emerald-500 animate-pulse";
                        statusTexto.innerText = "Aberto";

                        modalBadge.className = "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200";
                        modalPonto.className = "w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse";
                        modalTexto.innerText = "Aberto Agora";

                        avisoLojaFechada.classList.add('hidden');
                    } else {
                        statusBadge.className = "flex items-center gap-1.5 text-xs font-medium text-rose-600";
                        statusPonto.className = "w-2 h-2 rounded-full bg-rose-500";
                        statusTexto.innerText = "Fechado no momento";

                        modalBadge.className = "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200";
                        modalPonto.className = "w-1.5 h-1.5 rounded-full bg-rose-500";
                        modalTexto.innerText = "Fechado";

                        avisoLojaFechada.classList.remove('hidden');

                        if (!modalQuantidade.classList.contains('hidden')) {
                            fecharModalQuantidade();
                        }
                    }

                    const produtosApi = data.produtos;
                    for (const id in produtosApi) {
                        const info = produtosApi[id];
                        const card = document.getElementById(`card-produto-${id}`);
                        
                        if (card) {
                            card.setAttribute('data-status', info.status_produto);
                            const seloContainer = card.querySelector('.selo-esgotado-container');
                            const tituloCard = card.querySelector('h4');

                            if (info.esgotado) {
                                card.classList.add('opacity-60', 'bg-slate-50/80', 'cursor-not-allowed');
                                card.classList.remove('hover:border-orange-300', 'hover:shadow-md', 'cursor-pointer');
                                if (tituloCard) tituloCard.classList.remove('group-hover:text-orange-600');
                                
                                if (seloContainer && !seloContainer.querySelector('.selo-esgotado')) {
                                    seloContainer.innerHTML = `<span class="selo-esgotado absolute top-3 right-3 bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-md uppercase tracking-wider shadow-sm">Esgotado</span>`;
                                }
                            } else {
                                card.classList.remove('opacity-60', 'bg-slate-50/80', 'cursor-not-allowed');
                                card.classList.add('hover:border-orange-300', 'hover:shadow-md', 'cursor-pointer');
                                if (tituloCard) tituloCard.classList.add('group-hover:text-orange-600');
                                
                                if (seloContainer) {
                                    seloContainer.innerHTML = '';
                                }
                            }

                            if (!modalQuantidade.classList.contains('hidden') && produtoSelecionadoId == id && info.esgotado) {
                                fecharModalQuantidade();
                                alert('Este item acabou de ser marcado como esgotado.');
                            }
                        }
                    }
                }
            })
            .catch(error => console.error('Erro na sincronização em tempo real:', error));
        }

        setInterval(atualizarDadosEmTempoReal, 4000);

        function abrirModalAcompanhar() { modalAcompanhar.classList.remove('hidden'); inputTelefoneAcompanhar.focus(); }
        function fecharModalAcompanhar() { modalAcompanhar.classList.add('hidden'); }

        function mascaraTelefone(input) {
            let valor = input.value.replace(/\D/g, '');
            if (valor.length > 11) valor = valor.slice(0, 11);
            if (valor.length > 6) {
                input.value = `(${valor.slice(0, 2)})${valor.slice(2, 7)}-${valor.slice(7)}`;
            } else if (valor.length > 2) {
                input.value = `(${valor.slice(0, 2)})${valor.slice(2)}`;
            } else if (valor.length > 0) {
                input.value = `(${valor}`;
            } else {
                input.value = '';
            }
        }

        function irParaLocalizacao() {
            let telefoneFormatado = inputTelefoneAcompanhar.value.trim();
            let telefoneLimpo = telefoneFormatado.replace(/\D/g, '');

            if (telefoneLimpo.length < 11) {
                alert('Informe o telefone completo no formato (00)00000-0000.');
                inputTelefoneAcompanhar.focus();
                return;
            }

            window.location.href = `localizacao.php?telefone=${encodeURIComponent(telefoneLimpo)}`;
        }
    </script>
</body>
</html>