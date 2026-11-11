<?php
// mercado/carrinho.php

// Definir o fuso horário padrão para Manaus, Amazonas (UTC-4)
date_default_timezone_set('America/Manaus');

$currentPage = 'carrinho';

// Inclui o arquivo de configuração e conexão com o banco de dados
require_once '../admin/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['sacola'])) {
    $_SESSION['sacola'] = [];
}

$loja_id = 1;

// Busca as coordenadas da loja no banco de dados para usar no cálculo inteligente
$stmtLoja = $pdo->prepare("SELECT latitude, longitude FROM lojas WHERE id = ?");
$stmtLoja->execute([$loja_id]);
$dadosLoja = $stmtLoja->fetch(PDO::FETCH_ASSOC);

$lojaLat = $dadosLoja['latitude'] ?? -3.1190;
$lojaLng = $dadosLoja['longitude'] ?? -60.0217;

// Se a requisição for AJAX para autocompletar ruas (máximo 3 opções com lat/long)
if (isset($_GET['ajax_autocomplete_rua'])) {
    header('Content-Type: application/json');
    $termo = trim($_GET['termo'] ?? '');
    
    if (strlen($termo) < 3) {
        echo json_encode([]);
        exit;
    }

    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($termo . ", Manaus, Amazonas, Brasil") . "&limit=3&addressdetails=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MercadoSaaSApp/1.0');
    $response = curl_exec($ch);
    curl_close($ch);

    $sugestoes = [];
    if ($response) {
        $dados = json_decode($response, true);
        foreach ($dados as $item) {
            $address = $item['address'] ?? [];
            $rua = $address['road'] ?? $address['pedestrian'] ?? $address['suburb'] ?? $item['display_name'];
            $bairro = $address['suburb'] ?? $address['neighbourhood'] ?? $address['city_district'] ?? 'Manaus';
            
            $sugestoes[] = [
                'rua' => $rua,
                'bairro' => $bairro,
                'latitude' => $item['lat'],
                'longitude' => $item['lon'],
                'display' => "{$rua} - {$bairro}"
            ];
        }
    }

    echo json_encode($sugestoes);
    exit;
}

// Se a requisição for AJAX para alterar quantidade ou remover itens da sacola
if (isset($_GET['ajax_sacola']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $idItem = intval($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? '';

    if ($idItem > 0 && isset($_SESSION['sacola'][$idItem])) {
        if ($acao === 'mais') {
            $_SESSION['sacola'][$idItem]['quantidade']++;
        } elseif ($acao === 'menos') {
            $_SESSION['sacola'][$idItem]['quantidade']--;
            if ($_SESSION['sacola'][$idItem]['quantidade'] <= 0) {
                unset($_SESSION['sacola'][$idItem]);
            }
        } elseif ($acao === 'remover') {
            unset($_SESSION['sacola'][$idItem]);
        }
    }

    $subtotal = 0;
    foreach ($_SESSION['sacola'] as $item) {
        $subtotal += ($item['preco'] * $item['quantidade']);
    }

    echo json_encode([
        'sucesso' => true,
        'vazia' => count($_SESSION['sacola']) === 0,
        'subtotal' => number_format($subtotal, 2, ',', '.'),
        'quantidade_item' => isset($_SESSION['sacola'][$idItem]) ? $_SESSION['sacola'][$idItem]['quantidade'] : 0,
        'total_item' => isset($_SESSION['sacola'][$idItem]) ? number_format($_SESSION['sacola'][$idItem]['preco'] * $_SESSION['sacola'][$idItem]['quantidade'], 2, ',', '.') : '0,00'
    ]);
    exit;
}

// Se a requisição for AJAX para buscar cliente e endereço pelo WhatsApp
if (isset($_GET['ajax_busca_whatsapp'])) {
    header('Content-Type: application/json');
    $whatsapp = preg_replace('/\D/', '', $_GET['whatsapp']);

    if (empty($whatsapp)) {
        echo json_encode(['encontrado' => false]);
        exit;
    }

    $stmtCliente = $pdo->prepare("SELECT id, nome FROM clientes WHERE telefone = ? AND loja_id = ?");
    $stmtCliente->execute([$whatsapp, $loja_id]);
    $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        $stmtEnd = $pdo->prepare("SELECT rua, numero, bairro, complemento, latitude, longitude FROM enderecos_clientes WHERE cliente_id = ? ORDER BY id DESC LIMIT 1");
        $stmtEnd->execute([$cliente['id']]);
        $endereco = $stmtEnd->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'encontrado' => true,
            'nome' => trim($cliente['nome']),
            'endereco' => $endereco ? $endereco : null
        ]);
    } else {
        echo json_encode(['encontrado' => false]);
    }
    exit;
}

// Salvar dados de cliente, endereço, lat, long, taxa e tempo estimado na sessão e avançar para pagamento.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_endereco'])) {
    $nomeCompleto = trim($_POST['nome'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $telefoneLimpo = preg_replace('/\D/', '', $whatsapp);
    $cep = trim($_POST['cep'] ?? '');
    $rua = trim($_POST['rua'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $latitude = !empty($_POST['latitude']) ? trim($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? trim($_POST['longitude']) : null;
    $distanciaKm = floatval($_POST['distancia_km'] ?? 0);
    $taxaEntrega = floatval($_POST['taxa_entrega'] ?? 1.50);
    $tempoEstimadoMin = intval($_POST['tempo_estimado_min'] ?? 20);
    $concordouTaxa = isset($_POST['concordou_taxa']) ? 1 : 0;

    if ($concordouTaxa && !empty($telefoneLimpo) && !empty($nomeCompleto) && !empty($rua)) {
        $stmtCheck = $pdo->prepare("SELECT id FROM clientes WHERE telefone = ? AND loja_id = ?");
        $stmtCheck->execute([$telefoneLimpo, $loja_id]);
        $clienteExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($clienteExistente) {
            $clienteId = $clienteExistente['id'];
            $stmtUpd = $pdo->prepare("UPDATE clientes SET nome = ? WHERE id = ?");
            $stmtUpd->execute([$nomeCompleto, $clienteId]);
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO clientes (loja_id, nome, telefone) VALUES (?, ?, ?)");
            $stmtIns->execute([$loja_id, $nomeCompleto, $telefoneLimpo]);
            $clienteId = $pdo->lastInsertId();
        }

        $stmtEndCheck = $pdo->prepare("SELECT id FROM enderecos_clientes WHERE cliente_id = ? ORDER BY id DESC LIMIT 1");
        $stmtEndCheck->execute([$clienteId]);
        $endExistente = $stmtEndCheck->fetch(PDO::FETCH_ASSOC);

        if ($endExistente) {
            $stmtEndUpd = $pdo->prepare("UPDATE enderecos_clientes SET rua = ?, numero = ?, bairro = ?, complemento = ?, latitude = ?, longitude = ? WHERE id = ?");
            $stmtEndUpd->execute([$rua, $numero, $bairro, $complemento, $latitude, $longitude, $endExistente['id']]);
        } else {
            $stmtEndIns = $pdo->prepare("INSERT INTO enderecos_clientes (cliente_id, rua, numero, bairro, complemento, latitude, longitude, principal) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmtEndIns->execute([$clienteId, $rua, $numero, $bairro, $complemento, $latitude, $longitude]);
        }

        // Salva os dados na sessão para uso posterior na tela de pagamento/finalização do pedido
        $_SESSION['checkout_cliente_id'] = $clienteId;
        $_SESSION['cliente_nome'] = $nomeCompleto;
        $_SESSION['cliente_whatsapp'] = $telefoneLimpo;
        $_SESSION['checkout_endereco'] = [
            'cep' => $cep,
            'rua' => $rua,
            'numero' => $numero,
            'bairro' => $bairro,
            'complemento' => $complemento,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'distancia_km' => $distanciaKm,
            'taxa_entrega' => $taxaEntrega,
            'tempo_estimado_min' => $tempoEstimadoMin
        ];
    }

    header("Location: pagamento.php");
    exit;
}

$subtotal = 0;
foreach ($_SESSION['sacola'] as $item) {
    $subtotal += ($item['preco'] * $item['quantidade']);
}

$pedidoConcluido = isset($_GET['sucesso']) && $_GET['sucesso'] == '1';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sacola e Entrega | MercadoSaaS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- MODAL DE SELEÇÃO DE ENDEREÇO DIRETO NO MAPA (INTELIGENTE) -->
    <div id="modalLocalizacao" class="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-md hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-lg w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-5 h-5 text-orange-500"></i> Escolha o endereço direto no mapa
                </h3>
                <button type="button" onclick="fecharModalMapa()" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="space-y-3">
                <p class="text-xs text-slate-600 font-medium bg-orange-50 border border-orange-200 p-3 rounded-xl text-orange-900">
                    📍 Arraste o mapa ou clique no local exato da entrega. A latitude, longitude, rua e bairro serão preenchidos automaticamente!
                </p>
                <div id="mapaContainer" class="w-full h-72 rounded-2xl border border-slate-200 z-10"></div>
                
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs text-slate-700 space-y-1">
                    <p><strong>Rua detectada:</strong> <span id="mapaRuaDetectada" class="text-slate-900 font-semibold">Movimente o mapa...</span></p>
                    <p><strong>Bairro:</strong> <span id="mapaBairroDetectado" class="text-slate-900 font-semibold">-</span></p>
                </div>

                <button type="button" onclick="confirmarSelecaoMapa()" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3.5 rounded-xl text-sm transition-colors shadow-md shadow-orange-500/20 flex items-center justify-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Usar este Endereço Exato
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL SUCESSO DO PEDIDO -->
    <?php if ($pedidoConcluido): ?>
    <div id="modalSucessoPedido" class="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-md w-full p-6 sm:p-8 text-center space-y-6">
            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto shadow-inner">
                <i data-lucide="check-circle" class="w-8 h-8"></i>
            </div>
            <div class="space-y-2">
                <h2 class="text-xl font-bold text-slate-800">Pedido Realizado com Sucesso!</h2>
                <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
                    Para rastrear seu pedido em tempo real, basta clicar no <strong>botão na parte superior direita da tela</strong> e colocar o número do telefone.
                </p>
            </div>
            <button type="button" onclick="irParaLocalizacao()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-2xl text-sm transition-colors shadow-lg shadow-emerald-600/20 flex items-center justify-center gap-2">
                Entendido, ir para Rastreio <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-2xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="text-slate-500 hover:text-slate-800 p-1 rounded-lg">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <h1 id="tituloTopo" class="font-bold text-slate-800 text-base">Sua Sacola</h1>
            <div class="w-5"></div>
        </div>
    </header>

    <main class="flex-1 max-w-2xl w-full mx-auto p-4 sm:p-6 space-y-6">
        
        <div id="cardMensagemSistema" class="hidden bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-semibold flex items-center gap-2 shadow-sm transition-all">
            <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
            <span id="textoMensagemSistema"></span>
        </div>

        <!-- MÓDULO 1: SACOLA -->
        <div id="secaoSacola" class="space-y-6">
            <div id="containerListaSacola" class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100 p-4">
                <?php if (count($_SESSION['sacola']) > 0): ?>
                    <?php foreach ($_SESSION['sacola'] as $id => $item): ?>
                        <?php $totalItem = $item['preco'] * $item['quantidade']; ?>
                        <div id="item-sacola-<?php echo $id; ?>" class="py-4 first:pt-0 last:pb-0 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 overflow-hidden shrink-0 border border-slate-100">
                                    <?php if (!empty($item['foto']) && file_exists('../' . $item['foto'])): ?>
                                        <img src="../<?php echo htmlspecialchars($item['foto']); ?>" alt="" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i data-lucide="package" class="w-5 h-5 text-slate-400"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-bold text-slate-800 text-sm truncate"><?php echo htmlspecialchars($item['nome']); ?></h3>
                                    <p class="text-xs text-slate-500">R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?> cada</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden">
                                    <button type="button" onclick="alterarQuantidade(<?php echo $id; ?>, 'menos')" class="px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-600 font-bold">-</button>
                                    <span id="qtd-item-<?php echo $id; ?>" class="px-3 text-sm font-semibold text-slate-800"><?php echo $item['quantidade']; ?></span>
                                    <button type="button" onclick="alterarQuantidade(<?php echo $id; ?>, 'mais')" class="px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-600 font-bold">+</button>
                                </div>
                                <span id="total-item-<?php echo $id; ?>" class="font-bold text-slate-800 text-sm w-20 text-right">R$ <?php echo number_format($totalItem, 2, ',', '.'); ?></span>
                                <button type="button" onclick="alterarQuantidade(<?php echo $id; ?>, 'remover')" class="text-slate-400 hover:text-rose-600 transition-colors p-1" title="Remover">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="py-12 text-center space-y-3">
                        <i data-lucide="shopping-bag" class="w-12 h-12 text-slate-300 mx-auto"></i>
                        <p class="text-slate-600 font-medium">Sua sacola está vazia.</p>
                        <a href="index.php" class="inline-block bg-orange-500 hover:bg-orange-600 text-white font-semibold px-4 py-2 rounded-xl text-xs transition-colors">Voltar para a Loja</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($_SESSION['sacola']) > 0): ?>
                <div id="containerSubtotal" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3 text-sm">
                    <div class="flex justify-between text-slate-500">
                        <span>Subtotal dos itens</span>
                        <span id="textoSubtotal" class="font-medium text-slate-800">R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- MÓDULO 2: ENDEREÇO -->
        <div id="secaoEndereco" class="hidden space-y-6">
            <form id="formCheckout" action="carrinho.php" method="POST" class="space-y-4">
                <input type="hidden" name="acao_endereco" value="1">
                <input type="hidden" name="distancia_km" id="inputDistanciaKm" value="0">
                <input type="hidden" name="taxa_entrega" id="inputTaxaEntrega" value="1.50">
                <input type="hidden" name="tempo_estimado_min" id="inputTempoEstimado" value="20">
                <input type="hidden" name="latitude" id="inputLatitude" value="">
                <input type="hidden" name="longitude" id="inputLongitude" value="">

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                    <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-3 flex items-center gap-2">
                        <i data-lucide="user" class="w-4 h-4 text-orange-500"></i> Seus Dados
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Nome Completo</label>
                            <input type="text" name="nome" id="inputNome" placeholder="Ex: Emanuel Silva" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">WhatsApp (Busca Automática)</label>
                            <input type="text" name="whatsapp" id="inputWhatsapp" placeholder="(92) 99999-9999" maxlength="15" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-4 h-4 text-orange-500"></i> Onde vamos entregar?
                        </h3>
                        <button type="button" onclick="abrirModalMapaDireto()" class="text-xs bg-orange-50 hover:bg-orange-100 text-orange-600 font-semibold px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="map" class="w-3.5 h-3.5"></i> Escolher Endereço no Mapa
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">CEP <span class="text-slate-400 font-normal">(Opcional)</span></label>
                            <input type="text" name="cep" id="inputCep" onblur="buscarCepInteligente(this.value)" placeholder="00000-000" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Número</label>
                            <input type="text" name="numero" id="inputNumero" placeholder="Ex: 1520" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                    </div>

                    <!-- CAMPO DE RUA COM AUTOCOMPLETE INTELIGENTE (MÁXIMO 3 OPÇÕES) -->
                    <div class="relative">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Endereço / Rua <span class="text-slate-400 font-normal">(Digite para buscar sugestões)</span></label>
                        <input type="text" name="rua" id="inputRua" oninput="buscarSugestoesRua(this.value)" autocomplete="off" placeholder="Comece a digitar o nome da rua..." required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        
                        <!-- Caixa de Sugestões (Máx 3 opções) -->
                        <div id="listaSugestoesRua" class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-20 hidden divide-y divide-slate-100 overflow-hidden"></div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Bairro</label>
                            <input type="text" name="bairro" id="inputBairro" placeholder="Bairro" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Complemento / Referência</label>
                            <input type="text" name="complemento" id="inputComplemento" placeholder="Ex: Bloco B, Apto 202" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                        </div>
                    </div>
                </div>

                <!-- CARD DE CÁLCULO DE TAXA E TERMO DE CONCORDÂNCIA OBRIGATÓRIO -->
                <div id="cardCalculoTaxa" class="bg-amber-50 border border-amber-200 rounded-2xl p-5 space-y-4 hidden">
                    <div class="flex items-center gap-2 text-amber-800 font-bold text-sm">
                        <i data-lucide="map-pin" class="w-4 h-4 text-amber-600"></i>
                        <span>Cálculo de Distância e Taxa de Entrega</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="bg-white/70 p-3 rounded-xl border border-amber-100">
                            <span class="text-slate-400 block">Distância da Loja</span>
                            <span id="textoDistancia" class="font-bold text-slate-800 text-sm">0 km</span>
                        </div>
                        <div class="bg-white/70 p-3 rounded-xl border border-amber-100">
                            <span class="text-slate-400 block">Taxa de Entrega</span>
                            <span id="textoTaxaCalculada" class="font-bold text-emerald-600 text-sm">R$ 1,50</span>
                        </div>
                    </div>

                    <!-- CAIXA DE CONCORDÂNCIA OBRIGATÓRIA -->
                    <div class="pt-2 border-t border-amber-200/60 flex items-start gap-3">
                        <input type="checkbox" name="concordou_taxa" id="inputConcordouTaxa" required class="mt-0.5 w-4 h-4 text-orange-500 rounded border-slate-300 focus:ring-orange-500 cursor-pointer">
                        <label for="inputConcordouTaxa" class="text-xs text-amber-900 font-medium cursor-pointer leading-relaxed">
                            Estou de acordo com a taxa de entrega calculada com base na minha localização exata no mapa para prosseguir.
                        </label>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <?php if (count($_SESSION['sacola']) > 0 || $pedidoConcluido): ?>
        <div id="containerBotoesAcao" class="bg-white border-t border-slate-200 p-4 sticky bottom-0 shadow-lg <?php echo $pedidoConcluido ? 'hidden' : ''; ?>">
            <div class="max-w-2xl mx-auto flex gap-3">
                <button id="btnVoltar" type="button" onclick="voltarParaSacola()" class="hidden w-1/3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-3.5 rounded-xl text-sm transition-colors">
                    Voltar
                </button>
                <button id="btnAvancar" type="button" onclick="avancarParaEndereco()" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3.5 rounded-xl text-sm transition-colors shadow-md shadow-orange-500/20 flex items-center justify-center gap-2">
                    Próximo: Informar Endereço <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <script>
        lucide.createIcons();

        let etapaAtual = 'sacola';
        const inputWhatsapp = document.getElementById('inputWhatsapp');
        let mapaleaflet = null;
        let markerMapa = null;

        const lojaLat = <?php echo json_encode(floatval($lojaLat)); ?>;
        const lojaLng = <?php echo json_encode(floatval($lojaLng)); ?>;

        let tempLat = lojaLat !== 0 ? lojaLat : -3.1190;
        let tempLng = lojaLng !== 0 ? lojaLng : -60.0217;

        function irParaLocalizacao() {
            window.location.href = 'localizacao.php';
        }

        function mostrarMensagem(texto) {
            const cardMsg = document.getElementById('cardMensagemSistema');
            const textoMsg = document.getElementById('textoMensagemSistema');
            textoMsg.innerText = texto;
            cardMsg.classList.remove('hidden');
            setTimeout(() => { cardMsg.classList.add('hidden'); }, 4000);
        }

        function alterarQuantidade(idItem, acao) {
            const formData = new URLSearchParams();
            formData.append('id', idItem);
            formData.append('acao', acao);

            fetch('carrinho.php?ajax_sacola=1', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.sucesso) {
                    if (data.vazia) { location.reload(); return; }
                    if (acao === 'remover' || data.quantidade_item <= 0) {
                        const elementoItem = document.getElementById(`item-sacola-${idItem}`);
                        if (elementoItem) elementoItem.remove();
                    } else {
                        document.getElementById(`qtd-item-${idItem}`).innerText = data.quantidade_item;
                        document.getElementById(`total-item-${idItem}`).innerText = `R$ ${data.total_item}`;
                    }
                    document.getElementById('textoSubtotal').innerText = `R$ ${data.subtotal}`;
                }
            });
        }

        function calcularDistanciaHaversine(lat1, lon1, lat2, lon2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = 
                Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
                Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        function aplicarRegraTaxaPorDistancia(latCliente, lonCliente) {
            let distanciaKm = calcularDistanciaHaversine(lojaLat, lojaLng, latCliente, lonCliente);
            let taxa = 1.50;

            if (distanciaKm > 1.5 && distanciaKm <= 3.0) {
                let excedente = distanciaKm - 1.5;
                let blocos = Math.ceil(excedente / 0.5);
                taxa = 1.50 + (blocos * 0.50);
            } 
            else if (distanciaKm > 3.0 && distanciaKm <= 8.0) {
                let taxaBaseAte3 = 1.50 + (Math.ceil(1.5 / 0.5) * 0.50);
                let excedente3a8 = distanciaKm - 3.0;
                let blocos600m = Math.ceil(excedente3a8 / 0.6);
                taxa = taxaBaseAte3 + (blocos600m * 1.20);
            } 
            else if (distanciaKm > 8.0) {
                let taxaBaseAte8 = 1.50 + (Math.ceil(1.5 / 0.5) * 0.50) + (Math.ceil(5.0 / 0.6) * 1.20);
                let excedenteAcima8 = distanciaKm - 8.0;
                let kmAdicionais = Math.ceil(excedenteAcima8);
                taxa = taxaBaseAte8 + (kmAdicionais * 1.80);
            }

            let tempoTrajetoMin = Math.round((distanciaKm / 20) * 60);
            let tempoTotalMin = tempoTrajetoMin + 8;

            document.getElementById('cardCalculoTaxa').classList.remove('hidden');
            document.getElementById('textoDistancia').innerText = `${distanciaKm.toFixed(2)} km`;
            document.getElementById('textoTaxaCalculada').innerText = `R$ ${taxa.toFixed(2).replace('.', ',')}`;
            
            document.getElementById('inputDistanciaKm').value = distanciaKm.toFixed(2);
            document.getElementById('inputTaxaEntrega').value = taxa.toFixed(2);
            document.getElementById('inputTempoEstimado').value = tempoTotalMin;
        }

        // --- AUTOCOMPLETE DE RUA (MÁXIMO 3 OPÇÕES COM LAT E LONG) ---
        let timeoutBuscaRua = null;
        function buscarSugestoesRua(termo) {
            clearTimeout(timeoutBuscaRua);
            const container = document.getElementById('listaSugestoesRua');

            if (termo.length < 3) {
                container.classList.add('hidden');
                container.innerHTML = '';
                return;
            }

            timeoutBuscaRua = setTimeout(() => {
                fetch(`carrinho.php?ajax_autocomplete_rua=1&termo=${encodeURIComponent(termo)}`)
                    .then(res => res.json())
                    .then(sugestoes => {
                        container.innerHTML = '';
                        if (sugestoes.length > 0) {
                            sugestoes.slice(0, 3).forEach(item => {
                                const div = document.createElement('div');
                                div.className = 'p-3 hover:bg-orange-50 cursor-pointer text-xs transition-colors flex flex-col gap-0.5';
                                div.innerHTML = `
                                    <span class="font-bold text-slate-800">${item.rua}</span>
                                    <span class="text-slate-500">Bairro: ${item.bairro}</span>
                                `;
                                div.onclick = () => {
                                    document.getElementById('inputRua').value = item.rua;
                                    document.getElementById('inputBairro').value = item.bairro;
                                    document.getElementById('inputLatitude').value = item.latitude;
                                    document.getElementById('inputLongitude').value = item.longitude;

                                    tempLat = parseFloat(item.latitude);
                                    tempLng = parseFloat(item.longitude);

                                    aplicarRegraTaxaPorDistancia(tempLat, tempLng);
                                    container.classList.add('hidden');
                                    mostrarMensagem('Endereço e coordenadas selecionados com sucesso!');
                                };
                                container.appendChild(div);
                            });
                            container.classList.remove('hidden');
                        } else {
                            container.classList.add('hidden');
                        }
                    })
                    .catch(() => {
                        container.classList.add('hidden');
                    });
            }, 300);
        }

        // Fechar sugestões ao clicar fora
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#inputRua') && !e.target.closest('#listaSugestoesRua')) {
                document.getElementById('listaSugestoesRua').classList.add('hidden');
            }
        });

        // --- MAPA LEAFLET ---
        function abrirModalMapaDireto() {
            document.getElementById('modalLocalizacao').classList.remove('hidden');

            setTimeout(() => {
                if (!mapaleaflet) {
                    mapaleaflet = L.map('mapaContainer').setView([tempLat, tempLng], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(mapaleaflet);

                    markerMapa = L.marker([tempLat, tempLng], { draggable: true }).addTo(mapaleaflet);
                    
                    markerMapa.on('dragend', function() {
                        let pos = markerMapa.getLatLng();
                        atualizarEnderecoPorCoordenadas(pos.lat, pos.lng);
                    });

                    mapaleaflet.on('click', function(e) {
                        tempLat = e.latlng.lat;
                        tempLng = e.latlng.lng;
                        markerMapa.setLatLng([tempLat, tempLng]);
                        atualizarEnderecoPorCoordenadas(tempLat, tempLng);
                    });
                } else {
                    mapaleaflet.setView([tempLat, tempLng], 15);
                    markerMapa.setLatLng([tempLat, tempLng]);
                }
                mapaleaflet.invalidateSize();
                atualizarEnderecoPorCoordenadas(tempLat, tempLng);
            }, 300);
        }

        function atualizarEnderecoPorCoordenadas(lat, lng) {
            tempLat = lat;
            tempLng = lng;
            document.getElementById('mapaRuaDetectada').innerText = "Buscando endereço...";
            document.getElementById('mapaBairroDetectado').innerText = "...";

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(res => res.json())
                .then(data => {
                    let rua = data.address.road || data.address.suburb || data.address.pedestrian || 'Localização no Mapa';
                    let bairro = data.address.suburb || data.address.neighbourhood || data.address.city_district || 'Centro';
                    
                    window.tempRuaDetectada = rua;
                    window.tempBairroDetectado = bairro;

                    document.getElementById('mapaRuaDetectada').innerText = rua;
                    document.getElementById('mapaBairroDetectado').innerText = bairro;
                })
                .catch(() => {
                    window.tempRuaDetectada = "Localização no Mapa";
                    window.tempBairroDetectado = "Centro";
                    document.getElementById('mapaRuaDetectada').innerText = "Localização no Mapa";
                    document.getElementById('mapaBairroDetectado').innerText = "Centro";
                });
        }

        function confirmarSelecaoMapa() {
            document.getElementById('inputRua').value = window.tempRuaDetectada || 'Localização no Mapa';
            document.getElementById('inputBairro').value = window.tempBairroDetectado || 'Centro';
            document.getElementById('inputLatitude').value = tempLat;
            document.getElementById('inputLongitude').value = tempLng;

            aplicarRegraTaxaPorDistancia(tempLat, tempLng);
            fecharModalMapa();
            mostrarMensagem('Endereço e coordenadas exatas definidos com sucesso!');
        }

        function fecharModalMapa() {
            document.getElementById('modalLocalizacao').classList.add('hidden');
        }

        window.addEventListener('DOMContentLoaded', () => {
            const savedWhatsapp = localStorage.getItem('user_whatsapp');
            if (savedWhatsapp && inputWhatsapp) {
                inputWhatsapp.value = savedWhatsapp;
                buscarClientePorWhatsapp(savedWhatsapp);
            }
        });

        if (inputWhatsapp) {
            inputWhatsapp.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                if (value.length > 6) {
                    value = `(${value.slice(0,2)})${value.slice(2,7)}-${value.slice(7)}`;
                } else if (value.length > 2) {
                    value = `(${value.slice(0,2)})${value.slice(2)}`;
                } else if (value.length > 0) {
                    value = `(${value}`;
                }
                e.target.value = value;
                if (value.replace(/\D/g, '').length === 11) {
                    localStorage.setItem('user_whatsapp', value);
                    buscarClientePorWhatsapp(value);
                }
            });
        }

        function buscarClientePorWhatsapp(whatsapp) {
            fetch(`carrinho.php?ajax_busca_whatsapp=1&whatsapp=${encodeURIComponent(whatsapp)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.encontrado) {
                        if (data.nome) document.getElementById('inputNome').value = data.nome;
                        if (data.endereco) {
                            document.getElementById('inputRua').value = data.endereco.rua || '';
                            document.getElementById('inputNumero').value = data.endereco.numero || '';
                            document.getElementById('inputBairro').value = data.endereco.bairro || '';
                            document.getElementById('inputComplemento').value = data.endereco.complemento || '';
                            document.getElementById('inputLatitude').value = data.endereco.latitude || '';
                            document.getElementById('inputLongitude').value = data.endereco.longitude || '';
                            
                            if (data.endereco.latitude && data.endereco.longitude) {
                                tempLat = parseFloat(data.endereco.latitude);
                                tempLng = parseFloat(data.endereco.longitude);
                                aplicarRegraTaxaPorDistancia(tempLat, tempLng);
                            }
                        }
                    }
                });
        }

        function avancarParaEndereco() {
            if (etapaAtual === 'sacola') {
                document.getElementById('secaoSacola').classList.add('hidden');
                document.getElementById('secaoEndereco').classList.remove('hidden');
                document.getElementById('tituloTopo').innerText = 'Identificação e Entrega';
                document.getElementById('btnVoltar').classList.remove('hidden');
                document.getElementById('btnAvancar').classList.remove('w-full');
                document.getElementById('btnAvancar').classList.add('w-2/3');
                document.getElementById('btnAvancar').innerHTML = 'Confirmar e Ir para Pagamento <i data-lucide="arrow-right" class="w-4 h-4"></i>';
                lucide.createIcons();
                etapaAtual = 'endereco';
            } else {
                const nome = document.getElementById('inputNome').value;
                const whatsapp = document.getElementById('inputWhatsapp').value;
                const rua = document.getElementById('inputRua').value;
                const numero = document.getElementById('inputNumero').value;
                const bairro = document.getElementById('inputBairro').value;
                const concordou = document.getElementById('inputConcordouTaxa').checked;

                if (!nome || !whatsapp || !rua || !numero || !bairro) {
                    mostrarMensagem('Por favor, preencha todos os campos obrigatórios de identificação e endereço.');
                    return;
                }

                if (!concordou) {
                    mostrarMensagem('Você precisa concordar com a taxa de entrega para continuar.');
                    document.getElementById('inputConcordouTaxa').focus();
                    return;
                }

                document.getElementById('formCheckout').submit();
            }
        }

        function voltarParaSacola() {
            if (etapaAtual === 'endereco') {
                document.getElementById('secaoEndereco').classList.add('hidden');
                document.getElementById('secaoSacola').classList.remove('hidden');
                document.getElementById('tituloTopo').innerText = 'Sua Sacola';
                document.getElementById('btnVoltar').classList.add('hidden');
                document.getElementById('btnAvancar').classList.remove('w-2/3');
                document.getElementById('btnAvancar').classList.add('w-full');
                document.getElementById('btnAvancar').innerHTML = 'Próximo: Informar Endereço <i data-lucide="arrow-right" class="w-4 h-4"></i>';
                lucide.createIcons();
                etapaAtual = 'sacola';
            }
        }

        function buscarCepInteligente(cep) {
            let cepLimpo = cep.replace(/\D/g, '');
            if (cepLimpo.length === 8) {
                fetch(`https://viacep.com.br/ws/${cepLimpo}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('inputRua').value = data.logradouro;
                            document.getElementById('inputBairro').value = data.bairro;
                            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(data.logradouro + ', ' + data.bairro + ', Manaus')}`)
                            .then(res => res.json())
                            .then(geo => {
                                if (geo && geo.length > 0) {
                                    let lat = parseFloat(geo[0].lat);
                                    let lon = parseFloat(geo[0].lon);
                                    tempLat = lat;
                                    tempLng = lon;
                                    document.getElementById('inputLatitude').value = lat;
                                    document.getElementById('inputLongitude').value = lon;
                                    aplicarRegraTaxaPorDistancia(lat, lon);
                                }
                            });
                        } else {
                            mostrarMensagem('CEP não encontrado.');
                        }
                    });
            }
        }
    </script>
</body>
</html>