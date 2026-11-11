<?php
// mercado/pagamento.php
$currentPage = 'pagamento';

// Configura o fuso horário para Manaus / Amazonas
date_default_timezone_set('America/Manaus');

// Inclui o arquivo de configuração e conexão com o banco de dados
require_once '../admin/config.php';

try {
    $pdo->exec("SET time_zone = '-04:00'");
} catch (Exception $e) {}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se a sacola estiver vazia e não houver pedido concluído, redireciona para a vitrine
if ((!isset($_SESSION['sacola']) || count($_SESSION['sacola']) === 0) && !isset($_SESSION['ultimo_pedido_id'])) {
    header("Location: index.php");
    exit;
}

// Identificar a loja ativa de forma robusta com base no slug da URL ou sessão
$loja_id = intval($_SESSION['loja_id'] ?? 0);
if ($loja_id <= 0) {
    $pathParts = explode('/', trim($_SERVER['PHP_SELF'], '/'));
    if (count($pathParts) > 1 && $pathParts[0] !== 'mercado') {
        $slugPasta = $pathParts[0];
        $stmtSlug = $pdo->prepare("SELECT id FROM lojas WHERE slug = ? LIMIT 1");
        $stmtSlug->execute([$slugPasta]);
        $resSlug = $stmtSlug->fetch(PDO::FETCH_ASSOC);
        if ($resSlug) {
            $loja_id = intval($resSlug['id']);
        }
    }
}
if ($loja_id <= 0) {
    $loja_id = 4; // Fallback padrão
}
$_SESSION['loja_id'] = $loja_id;

// Buscar dados da loja
$stmtLoja = $pdo->prepare("SELECT * FROM lojas WHERE id = ?");
$stmtLoja->execute([$loja_id]);
$dadosLoja = $stmtLoja->fetch(PDO::FETCH_ASSOC);

$whatsappLoja = preg_replace('/\D/', '', $dadosLoja['whatsapp'] ?? '');
$tipoChavePix = $dadosLoja['tipo_chave_pix'] ?? 'Chave Pix';
$chavePixLoja = $dadosLoja['chave_pix'] ?? '';
$pixCopiaColaBanco = $dadosLoja['pix_copia_cola'] ?? '';

// Resgatar os dados do cliente e endereço salvos na sessão
$nomeCliente = $_SESSION['cliente_nome'] ?? $_SESSION['checkout_endereco']['nome'] ?? 'Cliente';
$whatsappCliente = $_SESSION['cliente_whatsapp'] ?? $_SESSION['checkout_endereco']['whatsapp'] ?? '(92) 99999-9999';

// Calcular subtotal
$subtotal = 0;
if (isset($_SESSION['sacola'])) {
    foreach ($_SESSION['sacola'] as $item) {
        $subtotal += ($item['preco'] * $item['quantidade']);
    }
}

// Resgatar dados de entrega salvos na sessão
$taxaEntrega = isset($_SESSION['checkout_endereco']['taxa_entrega']) ? floatval($_SESSION['checkout_endereco']['taxa_entrega']) : 1.50;
$distanciaKm = isset($_SESSION['checkout_endereco']['distancia_km']) ? floatval($_SESSION['checkout_endereco']['distancia_km']) : 0.00;
$tempoEstimadoMin = isset($_SESSION['checkout_endereco']['tempo_estimado_min']) ? intval($_SESSION['checkout_endereco']['tempo_estimado_min']) : 20;
$totalGeral = $subtotal + $taxaEntrega;

$pedidoConcluidoId = null;
$erroFinalizacao = null;
$codigoPedidoCriado = null;

// Processar a finalização do pedido via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forma_pagamento'])) {
    $formaPagamento = $_POST['forma_pagamento']; 
    $trocoPara = trim($_POST['troco_para'] ?? '');
    
    $nomeClientePost = trim($_POST['nome_cliente'] ?? $nomeCliente);
    $whatsappClientePost = preg_replace('/\D/', '', $_POST['whatsapp_cliente'] ?? $whatsappCliente);

    $_SESSION['cliente_nome'] = $nomeClientePost;
    $_SESSION['cliente_whatsapp'] = $whatsappClientePost;

    $end = $_SESSION['checkout_endereco'] ?? [];
    $rua = $end['rua'] ?? 'Rua não informada';
    $numero = $end['numero'] ?? 'S/N';
    $bairro = $end['bairro'] ?? 'Bairro não informado';
    $cep = $end['cep'] ?? '';
    $complemento = $end['complemento'] ?? '';
    $latitude = !empty($end['latitude']) ? $end['latitude'] : null;
    $longitude = !empty($end['longitude']) ? $end['longitude'] : null;

    $enderecoCompleto = "{$rua}, {$numero} - {$bairro}";
    if (!empty($cep)) { $enderecoCompleto .= " (CEP: {$cep})"; }
    if (!empty($complemento)) { $enderecoCompleto .= " - Comp: {$complemento}"; }

    try {
        $pdo->beginTransaction();

        // 1. Localizar ou inserir o cliente corretamente
        $stmtCliente = $pdo->prepare("SELECT id FROM clientes WHERE loja_id = ? AND telefone = ? LIMIT 1");
        $stmtCliente->execute([$loja_id, $whatsappClientePost]);
        $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

        if ($cliente) {
            $clienteId = $cliente['id'];
            $stmtUpd = $pdo->prepare("UPDATE clientes SET nome = ? WHERE id = ?");
            $stmtUpd->execute([$nomeClientePost, $clienteId]);
        } else {
            $stmtNovoCliente = $pdo->prepare("INSERT INTO clientes (loja_id, nome, telefone) VALUES (?, ?, ?)");
            $stmtNovoCliente->execute([$loja_id, $nomeClientePost, $whatsappClientePost]);
            $clienteId = $pdo->lastInsertId();
        }

        // 2. Gerar Código do Pedido único
        $codigoPedido = rand(1000, 9999);
        $statusPagamento = 'pendente';

        // 3. Inserir na tabela pedidos
        $stmtPedido = $pdo->prepare("
            INSERT INTO pedidos (
                loja_id, cliente_id, codigo_pedido, subtotal, taxa_entrega, total, 
                forma_pagamento, troco, status_pagamento, status_pedido, 
                endereco_entrega, cep, rua, numero, bairro, complemento, 
                latitude, longitude, distancia_km, tempo_estimado_min
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtPedido->execute([
            $loja_id, 
            $clienteId, 
            $codigoPedido, 
            $subtotal, 
            $taxaEntrega, 
            $totalGeral, 
            $formaPagamento, 
            ($formaPagamento === 'dinheiro' ? $trocoPara : null), 
            $statusPagamento, 
            $enderecoCompleto,
            $cep,
            $rua,
            $numero,
            $bairro,
            $complemento,
            $latitude,
            $longitude,
            $distanciaKm,
            $tempoEstimadoMin
        ]);
        $pedidoId = $pdo->lastInsertId();
        $_SESSION['ultimo_pedido_id'] = $pedidoId;

        // 4. Inserir itens do pedido
        if (isset($_SESSION['sacola'])) {
            foreach ($_SESSION['sacola'] as $item) {
                $subtotalItem = $item['preco'] * $item['quantidade'];
                $stmtItem = $pdo->prepare("
                    INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, subtotal) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtItem->execute([
                    $pedidoId, 
                    $item['id'], 
                    $item['quantidade'], 
                    $item['preco'], 
                    $subtotalItem
                ]);
            }
        }

        // 5. Inserir transação financeira inicial
        $stmtTransacao = $pdo->prepare("
            INSERT INTO transacoes (loja_id, pedido_id, tipo, descricao, valor, forma_pagamento, status) 
            VALUES (?, ?, 'entrada', ?, ?, ?, 'concluido')
        ");
        $stmtTransacao->execute([
            $loja_id, 
            $pedidoId, 
            "Pedido #{$codigoPedido}", 
            $totalGeral, 
            $formaPagamento
        ]);

        $pdo->commit();

        // Limpar sacola e dados temporários de checkout
        unset($_SESSION['sacola']);
        unset($_SESSION['checkout_endereco']);
        $pedidoConcluidoId = $pedidoId;
        $codigoPedidoCriado = $codigoPedido;

    } catch (Exception $e) {
        $pdo->rollBack();
        $erroFinalizacao = "Erro ao processar pedido: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forma de Pagamento | <?php echo htmlspecialchars($dadosLoja['nome_loja'] ?? 'Delivery'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- MODAL DE SUCESSO / INSTRUÇÕES PIX E WHATSAPP -->
    <?php if ($pedidoConcluidoId): ?>
    <div id="modalCompraFeita" class="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-md w-full p-6 sm:p-8 text-center space-y-5 my-auto transform animate-in fade-in zoom-in-95 duration-200">
            
            <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto shadow-inner">
                <i data-lucide="check-circle" class="w-7 h-7"></i>
            </div>
            
            <div class="space-y-1">
                <h2 class="text-lg font-bold text-slate-800">Pedido #<?php echo $codigoPedidoCriado; ?> Realizado!</h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    <?php if (isset($_POST['forma_pagamento']) && $_POST['forma_pagamento'] === 'pix'): ?>
                        Realize o pagamento via Pix abaixo e envie o comprovante para o WhatsApp da loja para agilizar a entrega.
                    <?php else: ?>
                        Seu pedido foi registrado com sucesso e já está sendo preparado!
                    <?php endif; ?>
                </p>
            </div>

            <?php if (isset($_POST['forma_pagamento']) && $_POST['forma_pagamento'] === 'pix'): ?>
                <div class="space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200 text-left">
                    <div class="space-y-1">
                        <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block"><?php echo htmlspecialchars($tipoChavePix); ?>:</label>
                        <div class="flex gap-2">
                            <input type="text" id="inputChavePixModal" readonly value="<?php echo htmlspecialchars($chavePixLoja); ?>" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-mono text-slate-600 focus:outline-none">
                            <button type="button" onclick="copiarTexto('inputChavePixModal')" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-colors shrink-0 flex items-center gap-1 shadow-sm">
                                <i data-lucide="copy" class="w-3.5 h-3.5"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($pixCopiaColaBanco)): ?>
                    <div class="space-y-1">
                        <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Pix Copia e Cola:</label>
                        <div class="flex gap-2">
                            <input type="text" id="inputPixModal" readonly value="<?php echo htmlspecialchars($pixCopiaColaBanco); ?>" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-mono text-slate-600 focus:outline-none">
                            <button type="button" onclick="copiarTexto('inputPixModal')" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-colors shrink-0 flex items-center gap-1 shadow-sm">
                                <i data-lucide="copy" class="w-3.5 h-3.5"></i> Copiar
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($whatsappLoja)): ?>
                    <a href="https://wa.me/55<?php echo $whatsappLoja; ?>?text=Olá,%20segue%20o%20comprovante%20do%20pedido%20%23<?php echo $codigoPedidoCriado; ?>" target="_blank" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-xl text-xs transition-colors flex items-center justify-center gap-2 shadow-sm mt-2">
                        <i data-lucide="message-circle" class="w-4 h-4"></i> Enviar Comprovante no WhatsApp
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <button type="button" onclick="irParaRastreio(<?php echo $pedidoConcluidoId; ?>)" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-3.5 rounded-2xl text-sm transition-colors shadow-lg flex items-center justify-center gap-2">
                Acompanhar Pedido <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-2xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="carrinho.php" class="text-slate-500 hover:text-slate-800 p-1 rounded-lg">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <h1 class="font-bold text-slate-800 text-base">Forma de Pagamento</h1>
            <div class="w-5"></div>
        </div>
    </header>

    <main class="flex-1 max-w-2xl w-full mx-auto p-4 sm:p-6 space-y-6">
        
        <?php if (isset($erroFinalizacao)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-sm">
                <?php echo htmlspecialchars($erroFinalizacao); ?>
            </div>
        <?php endif; ?>

        <!-- Alerta de Erro se não selecionar pagamento -->
        <div id="alertaPagamento" class="hidden bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-xs font-semibold flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
            <span>Por favor, selecione uma forma de pagamento para continuar.</span>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-400 block">Total a Pagar (com entrega)</span>
                <span class="text-lg font-bold text-slate-800">R$ <?php echo number_format($totalGeral, 2, ',', '.'); ?></span>
            </div>
            <div class="text-right">
                <span class="text-xs text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full font-semibold"><?php echo htmlspecialchars($dadosLoja['nome_loja'] ?? 'Loja'); ?></span>
            </div>
        </div>

        <form action="pagamento.php" method="POST" id="formPagamento" onsubmit="return validarFormulario(event)" class="space-y-4">
            
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-3 flex items-center gap-2">
                    <i data-lucide="user" class="w-4 h-4 text-orange-500"></i> Conferência dos Seus Dados
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Nome Cadastrado:</label>
                        <input type="text" name="nome_cliente" value="<?php echo htmlspecialchars($nomeCliente); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">WhatsApp / Telefone:</label>
                        <input type="text" name="whatsapp_cliente" value="<?php echo htmlspecialchars($whatsappCliente); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-3 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-4 h-4 text-orange-500"></i> Escolha como deseja pagar
                    </span>
                </h3>

                <div class="space-y-3">
                    <!-- Pix -->
                    <label class="flex items-center justify-between p-4 rounded-xl border-2 border-slate-200 hover:border-orange-500 bg-white cursor-pointer transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center font-bold">
                                <i data-lucide="qr-code" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-800 text-sm">Pix</span>
                                    <span class="text-[10px] bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded-full">Recomendado</span>
                                </div>
                                <span class="text-xs text-slate-500">Pagamento direto com a chave da loja</span>
                            </div>
                        </div>
                        <input type="radio" name="forma_pagamento" value="pix" onchange="toggleOpcoesPagamento('pix')" class="text-orange-500 focus:ring-orange-500">
                    </label>

                    <!-- Bloco Pix Cadastrado no DB (Oculto inicialmente) -->
                    <div id="blocoPix" class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3 hidden">
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-slate-600 block"><?php echo htmlspecialchars($tipoChavePix); ?> da Loja:</label>
                            <div class="flex gap-2">
                                <input type="text" id="inputChavePixPrincipal" readonly value="<?php echo htmlspecialchars($chavePixLoja); ?>" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs font-mono text-slate-600">
                                <button type="button" onclick="copiarTexto('inputChavePixPrincipal')" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-xs font-semibold transition-colors shrink-0 flex items-center gap-1">
                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i> Copiar Chave
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($pixCopiaColaBanco)): ?>
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-slate-600 block">Pix Copia e Cola:</label>
                            <div class="flex gap-2">
                                <input type="text" id="inputPixCopiaCola" readonly value="<?php echo htmlspecialchars($pixCopiaColaBanco); ?>" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs font-mono text-slate-500">
                                <button type="button" onclick="copiarTexto('inputPixCopiaCola')" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-xs font-semibold transition-colors shrink-0 flex items-center gap-1">
                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i> Copiar
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <p class="text-[11px] text-amber-600 font-medium bg-amber-50 p-2 rounded-lg border border-amber-200">
                            * Após realizar o pagamento, finalize o pedido e envie o comprovante diretamente para o WhatsApp da loja.
                        </p>
                    </div>

                    <!-- Dinheiro -->
                    <label class="flex items-center justify-between p-4 rounded-xl border-2 border-slate-200 hover:border-slate-300 bg-white cursor-pointer transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                                <i data-lucide="banknote" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="font-bold text-slate-800 text-sm block">Dinheiro</span>
                                <span class="text-xs text-slate-500">Pagamento na entrega</span>
                            </div>
                        </div>
                        <input type="radio" name="forma_pagamento" value="dinheiro" onchange="toggleOpcoesPagamento('dinheiro')" class="text-orange-500 focus:ring-orange-500">
                    </label>

                    <!-- Bloco Troco -->
                    <div id="blocoTroco" class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2 hidden">
                        <label class="text-xs font-semibold text-slate-600 block">Precisa de troco para quanto?</label>
                        <input type="text" name="troco_para" placeholder="Ex: R$ 50,00" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                    </div>

                    <!-- Cartão na Entrega -->
                    <label class="flex items-center justify-between p-4 rounded-xl border-2 border-slate-200 hover:border-slate-300 bg-white cursor-pointer transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                                <i data-lucide="credit-card" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="font-bold text-slate-800 text-sm block">Cartão (Débito ou Crédito)</span>
                                <span class="text-xs text-slate-500">Maquininha na entrega</span>
                            </div>
                        </div>
                        <input type="radio" name="forma_pagamento" value="cartao" onchange="toggleOpcoesPagamento('cartao')" class="text-orange-500 focus:ring-orange-500">
                    </label>

                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3.5 rounded-xl text-sm transition-colors shadow-md shadow-orange-500/20 flex items-center justify-center gap-2">
                    Finalizar Pedido <i data-lucide="check-circle" class="w-4 h-4"></i>
                </button>
            </div>

        </form>
    </main>

    <script>
        lucide.createIcons();

        function irParaRastreio(pedidoId) {
            window.location.href = 'localizacao.php?pedido=' + pedidoId;
        }

        function toggleOpcoesPagamento(tipo) {
            const blocoPix = document.getElementById('blocoPix');
            const blocoTroco = document.getElementById('blocoTroco');
            document.getElementById('alertaPagamento').classList.add('hidden');

            if (tipo === 'pix') {
                blocoPix.classList.remove('hidden');
                blocoTroco.classList.add('hidden');
            } else if (tipo === 'dinheiro') {
                blocoPix.classList.add('hidden');
                blocoTroco.classList.remove('hidden');
            } else {
                blocoPix.classList.add('hidden');
                blocoTroco.classList.add('hidden');
            }
        }

        function validarFormulario(event) {
            const radios = document.querySelectorAll('input[name="forma_pagamento"]');
            let selecionado = false;
            radios.forEach(r => {
                if (r.checked) selecionado = true;
            });

            if (!selecionado) {
                event.preventDefault();
                const alerta = document.getElementById('alertaPagamento');
                alerta.classList.remove('hidden');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
            return true;
        }

        function copiarTexto(idInput) {
            const input = document.getElementById(idInput);
            if (input) {
                input.select();
                navigator.clipboard.writeText(input.value);
            }
        }
    </script>
</body>
</html>