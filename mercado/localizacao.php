<?php
// mercado/localizacao.php

// Definir o fuso horário padrão para Manaus, Amazonas (UTC-4)
date_default_timezone_set('America/Manaus');

$currentPage = 'localizacao';
$erroSistema = null;

try {
    // Inclui o arquivo de configuração e conexão com o banco de dados
    require_once '../admin/config.php';

    // 0. Buscar Informações Básicas da Loja
    $loja_id = 1;
    $stmtLoja = $pdo->prepare("SELECT nome_loja FROM lojas WHERE id = ?");
    $stmtLoja->execute([$loja_id]);
    $lojaConfig = $stmtLoja->fetch(PDO::FETCH_ASSOC);

    $nomeLoja = $lojaConfig ? $lojaConfig['nome_loja'] : 'Mercado';

    // Captura o telefone passado via GET
    $telefone = isset($_GET['telefone']) ? trim($_GET['telefone']) : '';

    if (empty($telefone)) {
        header("Location: index.php");
        exit;
    }

    // Limpa o telefone para garantir busca correta no banco (apenas números)
    $telefoneLimpo = preg_replace('/[^0-9]/', '', $telefone);

    // 1. Buscar apenas os pedidos do cliente que NÃO foram entregues e nem cancelados
    $stmtPedidos = $pdo->prepare("
        SELECT p.*, c.nome as nome_cliente, c.telefone as telefone_cliente, 
               e.nome as nome_entregador, e.telefone as telefone_entregador, e.veiculo, e.placa
        FROM pedidos p
        JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN entregadores e ON p.entregador_id = e.id
        WHERE REGEXP_REPLACE(c.telefone, '[^0-9]', '') = ?
          AND p.status_pedido NOT IN ('entregue', 'cancelado')
        ORDER BY p.id DESC
    ");
    $stmtPedidos->execute([$telefoneLimpo]);
    $pedidosCliente = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

    // Define o pedido ativo com base estrita no ID passado via URL, senão usa o mais recente (primeiro)
    $pedidoIdEspecifico = isset($_GET['pedido']) ? intval($_GET['pedido']) : 0;
    $pedidoAtivo = null;

    if ($pedidoIdEspecifico > 0) {
        foreach ($pedidosCliente as $p) {
            if ($p['id'] == $pedidoIdEspecifico) {
                $pedidoAtivo = $p;
                break;
            }
        }
    }

    // Se não encontrou pelo ID específico ou nenhum foi passado, pega o primeiro (mais recente)
    if (!$pedidoAtivo) {
        $pedidoAtivo = $pedidosCliente[0] ?? null;
    }

    // 2. Buscar itens do pedido ativo
    $itensPedido = [];
    if ($pedidoAtivo) {
        $stmtItens = $pdo->prepare("
            SELECT ip.*, pr.nome as nome_produto 
            FROM itens_pedido ip
            LEFT JOIN produtos pr ON ip.produto_id = pr.id
            WHERE ip.pedido_id = ?
        ");
        $stmtItens->execute([$pedidoAtivo['id']]);
        $itensPedido = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
    }

    $status = $pedidoAtivo['status_pedido'] ?? 'pendente';

} catch (Exception $e) {
    $erroSistema = "Erro no Banco de Dados / SQL: " . $e->getMessage();
    $pedidosCliente = [];
    $pedidoAtivo = null;
    $itensPedido = [];
    $status = 'pendente';
}

// Função auxiliar em PHP para limpar zeros desnecessários nas quantidades (ex: 9.000 vira 9)
function formatarQtd($qtd) {
    $floatVal = floatval($qtd);
    if ($floatVal == intval($floatVal)) {
        return intval($floatVal);
    }
    return rtrim(rtrim(number_format($floatVal, 3, '.', ''), '0'), '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar Pedido | <?php echo htmlspecialchars($nomeLoja); ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-2xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="text-slate-500 hover:text-slate-800 p-1 rounded-lg" title="Voltar ao Início">
                <i data-lucide="home" class="w-5 h-5"></i>
            </a>
            <h1 class="font-bold text-slate-800 text-base">Meus Pedidos & Rastreio</h1>
            <div class="w-5"></div>
        </div>
    </header>

    <main class="flex-1 max-w-2xl w-full mx-auto p-4 sm:p-6 space-y-6">
        
        <?php if (!empty($erroSistema)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 p-4 rounded-2xl flex items-start gap-3 shadow-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-500 shrink-0 mt-0.5"></i>
                <div class="text-xs space-y-1 w-full overflow-hidden">
                    <span class="font-bold uppercase tracking-wider block">Identificador de Erro PHP / SQL</span>
                    <p class="font-mono bg-rose-100/60 p-2.5 rounded-lg text-rose-800 break-all"><?php echo htmlspecialchars($erroSistema); ?></p>
                </div>
            </div>
        <?php elseif (!$pedidoAtivo): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-700 p-6 rounded-2xl text-center space-y-3">
                <i data-lucide="info" class="w-8 h-8 text-amber-500 mx-auto"></i>
                <h3 class="font-bold text-sm">Nenhum pedido em aberto</h3>
                <p class="text-xs">Não encontramos pedidos ativos/pendentes associados ao telefone informado (<?php echo htmlspecialchars($telefone); ?>).</p>
                <div>
                    <a href="index.php" class="inline-block bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-xl text-xs font-semibold shadow-sm transition-colors">Voltar para a Loja</a>
                </div>
            </div>
        <?php else: ?>

            <!-- SELETOR DE PEDIDOS EM ABERTO -->
            <?php if (count($pedidosCliente) > 1): ?>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Pedidos em Andamento</span>
                        <span class="text-xs text-slate-400"><?php echo count($pedidosCliente); ?> pedidos</span>
                    </div>
                    <div id="scrollPedidos" class="flex gap-3 overflow-x-auto no-scrollbar py-1 scroll-smooth">
                        <?php foreach ($pedidosCliente as $cliPed): 
                            $isAtivo = ($cliPed['id'] == $pedidoAtivo['id']);
                            $st = $cliPed['status_pedido'];
                            
                            $badgeColor = "bg-amber-100 text-amber-700 border-amber-200";
                            if ($st === 'preparando') $badgeColor = "bg-orange-100 text-orange-700 border-orange-200";
                            elseif ($st === 'em_rota') $badgeColor = "bg-blue-100 text-blue-700 border-blue-200";
                        ?>
                            <a href="localizacao.php?telefone=<?php echo urlencode($telefone); ?>&pedido=<?php echo $cliPed['id']; ?>" 
                               onclick="salvarPosicaoScroll()"
                               class="shrink-0 w-48 p-3 rounded-2xl border transition-all text-left block relative shadow-sm <?php echo $isAtivo ? 'bg-white border-orange-500 ring-2 ring-orange-500/20 shadow-md' : 'bg-white/70 border-slate-200 hover:border-slate-300'; ?>">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="font-bold text-slate-800 text-xs">#<?php echo htmlspecialchars($cliPed['codigo_pedido'] ?? $cliPed['id']); ?></span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border <?php echo $badgeColor; ?>">
                                        <?php echo ucfirst($st); ?>
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 mb-1"><?php echo date('d/m H:i', strtotime($cliPed['criado_em'])); ?></p>
                                <p class="text-xs font-bold text-orange-600">R$ <?php echo number_format($cliPed['total'], 2, ',', '.'); ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php 
                $tituloStatus = "Pedido Recebido";
                $descStatus = "Aguardando o mercado confirmar e iniciar a separação.";
                $iconeStatus = "clock";
                $bgCard = "bg-amber-50 border-amber-200 text-amber-600";

                if ($status === 'preparando') {
                    $tituloStatus = "Separando os produtos!";
                    $descStatus = "O mercado já aceitou e está embalando seus itens.";
                    $iconeStatus = "package";
                    $bgCard = "bg-orange-50 border-orange-200 text-orange-600";
                } elseif ($status === 'em_rota') {
                    $tituloStatus = "Saiu para Entrega!";
                    $descStatus = "O entregador está a caminho do seu endereço.";
                    $iconeStatus = "bike";
                    $bgCard = "bg-blue-50 border-blue-200 text-blue-600";
                }
            ?>

            <!-- Card de Status Dinâmico -->
            <div id="cardStatusContainer" class="<?php echo $bgCard; ?> border p-5 rounded-2xl flex items-center gap-4 shadow-sm transition-all duration-300">
                <div id="statusIconBox" class="w-12 h-12 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-md">
                    <i data-lucide="<?php echo $iconeStatus; ?>" class="w-6 h-6"></i>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider block">Status Atual em Tempo Real</span>
                    <h3 id="statusTitulo" class="font-bold text-slate-800 text-lg"><?php echo $tituloStatus; ?></h3>
                    <p id="statusDesc" class="text-xs text-slate-500 mt-0.5"><?php echo $descStatus; ?></p>
                </div>
            </div>

            <!-- Card do Entregador Dinâmico -->
            <div id="entregadorCardContainer" class="<?php echo (!empty($pedidoAtivo['nome_entregador']) && $status === 'em_rota') ? '' : 'hidden'; ?> bg-white rounded-2xl border border-slate-200 shadow-sm p-4 flex items-center justify-between transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800 text-sm">Entregador: <span id="entregadorNome"><?php echo htmlspecialchars($pedidoAtivo['nome_entregador'] ?? ''); ?></span></h4>
                        <p class="text-xs text-slate-400"><span id="entregadorVeiculo"><?php echo htmlspecialchars($pedidoAtivo['veiculo'] ?? 'Moto'); ?></span> • Placa: <span id="entregadorPlaca"><?php echo htmlspecialchars($pedidoAtivo['placa'] ?? 'N/D'); ?></span></p>
                    </div>
                </div>
                <a id="entregadorWhatsapp" href="https://wa.me/55<?php echo preg_replace('/[^0-9]/', '', $pedidoAtivo['telefone_entregador'] ?? ''); ?>" target="_blank" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 p-2.5 rounded-xl transition-colors text-xs font-semibold flex items-center gap-1.5">
                    <i data-lucide="message-circle" class="w-4 h-4"></i> Contato
                </a>
            </div>

            <!-- Linha do Tempo (Timeline) Dinâmica -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
                <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-3">Linha do Tempo do Pedido</h3>

                <div id="timelineContainer" class="relative pl-6 space-y-8 before:content-[''] before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                    
                    <!-- Etapa 1: Pedido Realizado -->
                    <div class="relative flex items-start gap-4">
                        <span class="absolute -left-6 w-5 h-5 rounded-full bg-emerald-500 border-4 border-white flex items-center justify-center shadow-sm"></span>
                        <div>
                            <p class="font-bold text-slate-800 text-sm">Pedido Realizado</p>
                            <p class="text-xs text-slate-400">Registrado em <?php echo date('d/m/Y H:i', strtotime($pedidoAtivo['criado_em'])); ?></p>
                        </div>
                    </div>

                    <!-- Etapa 2: Em Separação -->
                    <div id="stepPreparando" class="relative flex items-start gap-4 <?php echo ($status == 'pendente') ? 'opacity-50' : ''; ?>">
                        <span id="dotPreparando" class="absolute -left-6 w-5 h-5 rounded-full <?php echo ($status == 'preparando') ? 'bg-amber-500 animate-pulse' : (($status == 'em_rota') ? 'bg-emerald-500' : 'bg-slate-300'); ?> border-4 border-white flex items-center justify-center shadow-sm"></span>
                        <div>
                            <p class="font-bold text-slate-800 text-sm">Separando os Produtos</p>
                            <p class="text-xs text-slate-400">O mercado está separando e embalando suas compras.</p>
                        </div>
                    </div>

                    <!-- Etapa 3: Saiu para Entrega -->
                    <div id="stepEmRota" class="relative flex items-start gap-4 <?php echo ($status == 'pendente' || $status == 'preparando') ? 'opacity-50' : ''; ?>">
                        <span id="dotEmRota" class="absolute -left-6 w-5 h-5 rounded-full <?php echo ($status == 'em_rota') ? 'bg-amber-500 animate-pulse' : 'bg-slate-300'; ?> border-4 border-white flex items-center justify-center shadow-sm"></span>
                        <div>
                            <p class="font-bold text-slate-800 text-sm">Saiu para Entrega</p>
                            <p class="text-xs text-slate-400">Entregador a caminho do endereço.</p>
                        </div>
                    </div>

                    <!-- Etapa 4: Entregue -->
                    <div id="stepEntregue" class="relative flex items-start gap-4 opacity-50">
                        <span id="dotEntregue" class="absolute -left-6 w-5 h-5 rounded-full bg-slate-300 border-4 border-white flex items-center justify-center shadow-sm"></span>
                        <div>
                            <p class="font-bold text-slate-800 text-sm">Pedido Entregue</p>
                            <p class="text-xs text-slate-400">Agradecemos a preferência!</p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Detalhes do Pedido e Endereço -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-3">Resumo da Compra (#<?php echo htmlspecialchars($pedidoAtivo['codigo_pedido'] ?? $pedidoAtivo['id']); ?>)</h3>
                <ul class="text-sm space-y-2 text-slate-600">
                    <?php foreach ($itensPedido as $item): ?>
                        <li class="flex justify-between">
                            <span><?php echo formatarQtd($item['quantidade']); ?>x <?php echo htmlspecialchars($item['nome_produto'] ?? 'Item'); ?></span> 
                            <span class="font-medium text-slate-800">R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?></span>
                        </li>
                    <?php endforeach; ?>
                    <li class="flex justify-between border-t border-slate-100 pt-2 text-xs text-slate-400">
                        <span>Taxa de Entrega</span> 
                        <span>R$ <?php echo number_format($pedidoAtivo['taxa_entrega'], 2, ',', '.'); ?></span>
                    </li>
                    <li class="flex justify-between pt-1 font-bold text-slate-800 text-base border-t border-slate-100 pt-2">
                        <span>Total Pago (<?php echo strtoupper($pedidoAtivo['forma_pagamento']); ?>)</span> 
                        <span class="text-orange-600">R$ <?php echo number_format($pedidoAtivo['total'], 2, ',', '.'); ?></span>
                    </li>
                </ul>

                <div class="border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <span class="font-semibold block text-slate-700 mb-1">Endereço de Entrega:</span>
                    <p><?php echo htmlspecialchars($pedidoAtivo['endereco_entrega']); ?></p>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <script>
        lucide.createIcons();

        function salvarPosicaoScroll() {
            const container = document.getElementById('scrollPedidos');
            if (container) {
                localStorage.setItem('scroll_pedidos_pos', container.scrollLeft);
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            const container = document.getElementById('scrollPedidos');
            if (container) {
                const savedScrollPos = localStorage.getItem('scroll_pedidos_pos');
                if (savedScrollPos !== null) {
                    container.scrollLeft = parseInt(savedScrollPos, 10);
                }
            }
        });

        <?php if ($pedidoAtivo): ?>
            let statusAtual = "<?php echo $status; ?>";
            const pedidoId = <?php echo $pedidoAtivo['id']; ?>;

            function atualizarTimeline(status) {
                const stepPreparando = document.getElementById('stepPreparando');
                const stepEmRota = document.getElementById('stepEmRota');
                const dotPreparando = document.getElementById('dotPreparando');
                const dotEmRota = document.getElementById('dotEmRota');

                if (!stepPreparando || !stepEmRota) return;

                stepPreparando.className = "relative flex items-start gap-4";
                stepEmRota.className = "relative flex items-start gap-4";
                
                dotPreparando.className = "absolute -left-6 w-5 h-5 rounded-full border-4 border-white flex items-center justify-center shadow-sm";
                dotEmRota.className = "absolute -left-6 w-5 h-5 rounded-full border-4 border-white flex items-center justify-center shadow-sm";

                if (status === 'pendente') {
                    stepPreparando.classList.add('opacity-50');
                    stepEmRota.classList.add('opacity-50');
                    dotPreparando.classList.add('bg-slate-300');
                    dotEmRota.classList.add('bg-slate-300');
                } else if (status === 'preparando') {
                    stepEmRota.classList.add('opacity-50');
                    dotPreparando.classList.add('bg-amber-500', 'animate-pulse');
                    dotEmRota.classList.add('bg-slate-300');
                } else if (status === 'em_rota') {
                    dotPreparando.classList.add('bg-emerald-500');
                    dotEmRota.classList.add('bg-amber-500', 'animate-pulse');
                }
            }

            // Polling dinâmico a cada 2 SEGUNDOS checando o status
            setInterval(() => {
                fetch(`api-localizacao.php?pedido_id=${pedidoId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status && data.status !== 'desconhecido') {
                            statusAtual = data.status;

                            const cardContainer = document.getElementById('cardStatusContainer');
                            const statusIconBox = document.getElementById('statusIconBox');
                            const statusTitulo = document.getElementById('statusTitulo');
                            const statusDesc = document.getElementById('statusDesc');

                            cardContainer.className = `${data.bgCard} border p-5 rounded-2xl flex items-center gap-4 shadow-sm transition-all duration-300`;
                            statusIconBox.innerHTML = `<i data-lucide="${data.iconeStatus}" class="w-6 h-6"></i>`;
                            statusTitulo.innerText = data.tituloStatus;
                            statusDesc.innerText = data.descStatus;
                            lucide.createIcons();

                            const entregadorCard = document.getElementById('entregadorCardContainer');
                            if (data.temEntregador && statusAtual === 'em_rota') {
                                entregadorCard.classList.remove('hidden');
                                document.getElementById('entregadorNome').innerText = data.nomeEntregador;
                                document.getElementById('entregadorVeiculo').innerText = data.veiculoEntregador;
                                document.getElementById('entregadorPlaca').innerText = data.placaEntregador;
                                document.getElementById('entregadorWhatsapp').href = `https://wa.me/55${data.telEntregador}`;
                            } else {
                                entregadorCard.classList.add('hidden');
                            }

                            atualizarTimeline(statusAtual);
                        }
                    })
                    .catch(err => console.error('Erro na requisição da API:', err));
            }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>