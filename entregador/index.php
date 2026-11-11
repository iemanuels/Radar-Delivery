<?php
// entregador/index.php - Painel e Aplicativo do Entregador
session_start();
$currentPage = 'entregador';

// Ativar exibição de erros temporariamente para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$erroSistema = null;
$sucessoMensagem = null;
$erroLogin = null;

// Define obrigatoriamente o fuso horário para Manaus / Amazonas
date_default_timezone_set('America/Manaus');

$loja_id = 1; 
$nomeLoja = 'Minha Loja';
$lojaAberta = false;

// Variáveis de localização padrão da loja
$lojaLat = -3.1190;
$lojaLon = -60.0217;
$lojaEnderecoStr = 'Endereço da Loja';

try {
    if (file_exists('admin/config.php')) {
        require_once 'admin/config.php';
    } elseif (file_exists('../admin/config.php')) {
        require_once '../admin/config.php';
    } elseif (file_exists('../../admin/config.php')) {
        require_once '../../admin/config.php';
    } else {
        throw new Exception("O arquivo de configuração 'config.php' não foi encontrado.");
    }

    if (!isset($pdo)) {
        throw new Exception("A conexão com o banco de dados (\$pdo) não foi inicializada corretamente em config.php.");
    }

    $pdo->exec("SET time_zone = '-04:00'");

    // Se o entregador já estiver logado, recuperamos o loja_id dele do banco de dados para segurança
    if (isset($_SESSION['entregador_id'])) {
        $stmtSessaoEntregador = $pdo->prepare("SELECT loja_id FROM entregadores WHERE id = ? LIMIT 1");
        $stmtSessaoEntregador->execute([$_SESSION['entregador_id']]);
        $dadosEntregadorSessao = $stmtSessaoEntregador->fetch(PDO::FETCH_ASSOC);
        if ($dadosEntregadorSessao) {
            $loja_id = intval($dadosEntregadorSessao['loja_id']);
        }
    }

    // Processar Ação de Login por Senha Única (Busca em todas as lojas ou valida por PIN)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'login') {
        $senhaDigitada = trim($_POST['senha']);
        
        if (empty($senhaDigitada)) {
            $erroLogin = "Digite a sua senha de acesso.";
        } else {
            // Busca todos os entregadores cadastrados que possuem senha definida
            $stmtEntregadores = $pdo->prepare("SELECT * FROM entregadores WHERE senha IS NOT NULL");
            $stmtEntregadores->execute();
            $todosEntregadores = $stmtEntregadores->fetchAll(PDO::FETCH_ASSOC);

            $entregadorEncontrado = null;
            foreach ($todosEntregadores as $ent) {
                if (password_verify($senhaDigitada, $ent['senha'])) {
                    $entregadorEncontrado = $ent;
                    break;
                }
            }

            if ($entregadorEncontrado) {
                $_SESSION['entregador_id'] = $entregadorEncontrado['id'];
                $_SESSION['entregador_nome'] = $entregadorEncontrado['nome'];
                $_SESSION['entregador_loja_id'] = $entregadorEncontrado['loja_id'];
                
                $pdo->prepare("UPDATE entregadores SET status_entregador = 'disponivel' WHERE id = ?")->execute([$entregadorEncontrado['id']]);
                
                header("Location: index.php");
                exit;
            } else {
                $erroLogin = "Senha inválida. Verifique seu PIN com o lojista.";
            }
        }
    }

    // Se estiver logado via sessão, garante o loja_id correto do entregador
    if (isset($_SESSION['entregador_id'])) {
        $entregador_id = $_SESSION['entregador_id'];
        if (isset($_SESSION['entregador_loja_id'])) {
            $loja_id = intval($_SESSION['entregador_loja_id']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'aceitar_pedido') {
            $pedido_id = intval($_POST['pedido_id']);
            
            $stmtAceite = $pdo->prepare("UPDATE pedidos SET entregador_id = ?, status_entrega = 'aceito' WHERE id = ? AND loja_id = ? AND (entregador_id IS NULL OR entregador_id = 0)");
            if ($stmtAceite->execute([$entregador_id, $pedido_id, $loja_id])) {
                $pdo->prepare("UPDATE entregadores SET status_entregador = 'em_rota' WHERE id = ?")->execute([$entregador_id]);
                header("Location: index.php?sucesso=" . urlencode("Pedido #{$pedido_id} aceito com sucesso!"));
                exit;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'rejeitar_pedido') {
            header("Location: index.php?sucesso=" . urlencode("Pedido rejeitado."));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'marcar_entregue') {
            $pedido_id = intval($_POST['pedido_id']);
            
            $stmtUpdate = $pdo->prepare("UPDATE pedidos SET status_pedido = 'entregue', status_entrega = 'entregue' WHERE id = ? AND entregador_id = ? AND loja_id = ?");
            if ($stmtUpdate->execute([$pedido_id, $entregador_id, $loja_id])) {
                $stmtRestantes = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE entregador_id = ? AND loja_id = ? AND status_entrega != 'entregue' AND status_pedido != 'entregue'");
                $stmtRestantes->execute([$entregador_id, $loja_id]);
                if ($stmtRestantes->fetchColumn() == 0) {
                    $pdo->prepare("UPDATE entregadores SET status_entregador = 'disponivel' WHERE id = ?")->execute([$entregador_id]);
                }

                header("Location: index.php?sucesso=" . urlencode("Entrega concluída com sucesso!"));
                exit;
            }
        }
    }

    if (isset($_GET['sucesso'])) {
        $sucessoMensagem = $_GET['sucesso'];
    }

    // Buscar Informações e Coordenadas Reais da Loja Correta
    $stmtLoja = $pdo->prepare("SELECT nome_loja, status_loja, forcar_fechado, horario_abertura, horario_fechamento, endereco, latitude, longitude FROM lojas WHERE id = ?");
    $stmtLoja->execute([$loja_id]);
    $loja = $stmtLoja->fetch(PDO::FETCH_ASSOC);

    if ($loja) {
        $nomeLoja = $loja['nome_loja'];
        $lojaEnderecoStr = $loja['endereco'] ?? 'Endereço da Loja';
        if (!empty($loja['latitude'])) $lojaLat = floatval($loja['latitude']);
        if (!empty($loja['longitude'])) $lojaLon = floatval($loja['longitude']);
    }

    // --- ENDPOINT AJAX PARA ATUALIZAÇÃO FLUIDA E ROTERIZAÇÃO INTELIGENTE ---
    if (isset($_GET['ajax_atualizacao']) && isset($_SESSION['entregador_id'])) {
        header('Content-Type: application/json; charset=utf-8');
        $entregador_id = $_SESSION['entregador_id'];

        // Pedidos Disponíveis EXCLUSIVOS da Loja do Entregador
        $stmtDisp = $pdo->prepare("
            SELECT p.*, c.nome as nome_cliente, c.telefone as telefone_cliente 
            FROM pedidos p
            INNER JOIN clientes c ON p.cliente_id = c.id
            WHERE p.loja_id = ? AND p.status_pedido IN ('preparando', 'pendente') AND (p.entregador_id IS NULL OR p.entregador_id = 0) AND (p.status_entrega IS NULL OR p.status_entrega = 'disponivel')
            ORDER BY p.criado_em ASC
        ");
        $stmtDisp->execute([$loja_id]);
        $pedidosDispJson = $stmtDisp->fetchAll(PDO::FETCH_ASSOC);

        // Meus Pedidos na Loja
        $stmtMeus = $pdo->prepare("
            SELECT p.*, c.nome as nome_cliente, c.telefone as telefone_cliente 
            FROM pedidos p
            INNER JOIN clientes c ON p.cliente_id = c.id
            WHERE p.loja_id = ? AND p.entregador_id = ? AND p.status_entrega IN ('aceito', 'em_rota') AND p.status_pedido != 'entregue'
            ORDER BY p.criado_em ASC
        ");
        $stmtMeus->execute([$loja_id, $entregador_id]);
        $pedidosMeusJson = $stmtMeus->fetchAll(PDO::FETCH_ASSOC);

        // Otimização estrita de rotas
        if (count($pedidosMeusJson) > 1 && !empty($lojaLat) && !empty($lojaLon)) {
            foreach ($pedidosMeusJson as &$pedidoItem) {
                $latDest = !empty($pedidoItem['latitude']) ? floatval($pedidoItem['latitude']) : $lojaLat;
                $lonDest = !empty($pedidoItem['longitude']) ? floatval($pedidoItem['longitude']) : $lojaLon;
                $earthRadius = 6371;
                $dLat = deg2rad($latDest - $lojaLat);
                $dLon = deg2rad($lonDest - $lojaLon);
                $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lojaLat)) * cos(deg2rad($latDest)) * sin($dLon/2) * sin($dLon/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                $pedidoItem['_distancia_otimizada'] = $earthRadius * $c;
            }
            unset($pedidoItem);

            usort($pedidosMeusJson, function($a, $b) {
                return $a['_distancia_otimizada'] <=> $b['_distancia_otimizada'];
            });
        }

        echo json_encode([
            'sucesso' => true,
            'disponiveis' => $pedidosDispJson,
            'meus' => $pedidosMeusJson
        ]);
        exit;
    }

    // Carregamento inicial PHP padrão
    $pedidosDisponiveis = [];
    $pedidosMeus = [];
    if (isset($_SESSION['entregador_id'])) {
        $entregador_id = $_SESSION['entregador_id'];

        $stmtDisp = $pdo->prepare("
            SELECT p.*, c.nome as nome_cliente, c.telefone as telefone_cliente 
            FROM pedidos p
            INNER JOIN clientes c ON p.cliente_id = c.id
            WHERE p.loja_id = ? AND p.status_pedido IN ('preparando', 'pendente') AND (p.entregador_id IS NULL OR p.entregador_id = 0) AND (p.status_entrega IS NULL OR p.status_entrega = 'disponivel')
            ORDER BY p.criado_em ASC
        ");
        $stmtDisp->execute([$loja_id]);
        $pedidosDisponiveis = $stmtDisp->fetchAll(PDO::FETCH_ASSOC);

        $stmtMeus = $pdo->prepare("
            SELECT p.*, c.nome as nome_cliente, c.telefone as telefone_cliente 
            FROM pedidos p
            INNER JOIN clientes c ON p.cliente_id = c.id
            WHERE p.loja_id = ? AND p.entregador_id = ? AND p.status_entrega IN ('aceito', 'em_rota') AND p.status_pedido != 'entregue'
            ORDER BY p.criado_em ASC
        ");
        $stmtMeus->execute([$loja_id, $entregador_id]);
        $pedidosMeus = $stmtMeus->fetchAll(PDO::FETCH_ASSOC);

        if (count($pedidosMeus) > 1 && !empty($lojaLat) && !empty($lojaLon)) {
            foreach ($pedidosMeus as &$pedidoItem) {
                $latDest = !empty($pedidoItem['latitude']) ? floatval($pedidoItem['latitude']) : $lojaLat;
                $lonDest = !empty($pedidoItem['longitude']) ? floatval($pedidoItem['longitude']) : $lojaLon;
                $earthRadius = 6371;
                $dLat = deg2rad($latDest - $lojaLat);
                $dLon = deg2rad($lonDest - $lojaLon);
                $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lojaLat)) * cos(deg2rad($latDest)) * sin($dLon/2) * sin($dLon/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                $pedidoItem['_distancia_otimizada'] = $earthRadius * $c;
            }
            unset($pedidoItem);
            usort($pedidosMeus, function($a, $b) {
                return $a['_distancia_otimizada'] <=> $b['_distancia_otimizada'];
            });
        }
    }

} catch (Exception $e) {
    $erroSistema = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App do Entregador | <?php echo htmlspecialchars($nomeLoja); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        #map { height: 100%; min-height: 450px; border-radius: 1rem; z-index: 10; }
        .moto-icon { background: #f97316; border: 2px solid white; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 6px rgba(0,0,0,0.3); color: white; }
        .loja-icon { background: #0f172a; border: 2px solid white; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 6px rgba(0,0,0,0.3); color: white; }
        .cliente-icon { background: #3b82f6; border: 2px solid white; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 6px rgba(0,0,0,0.3); color: white; font-weight: bold; font-size: 11px; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <?php if (!isset($_SESSION['entregador_id'])): ?>
        <div class="flex-1 flex items-center justify-center p-4 bg-slate-900">
            <div class="bg-white rounded-3xl p-8 max-w-md w-full shadow-2xl space-y-6 text-center border border-slate-100">
                <div class="w-16 h-16 rounded-2xl bg-orange-100 text-orange-600 flex items-center justify-center mx-auto shadow-inner">
                    <i data-lucide="bike" class="w-8 h-8"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-800">App do Entregador</h2>
                    <p class="text-xs text-orange-600 font-semibold mt-1"><?php echo htmlspecialchars($nomeLoja); ?></p>
                </div>

                <?php if ($erroLogin): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 p-3 rounded-xl text-xs font-medium">
                        <?php echo htmlspecialchars($erroLogin); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4 text-left">
                    <input type="hidden" name="acao" value="login">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Senha / PIN Único</label>
                        <input type="password" name="senha" required placeholder="Digite sua senha de acesso" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500 text-center tracking-widest font-bold">
                    </div>
                    <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 rounded-xl text-sm transition-colors shadow-lg shadow-orange-500/30 flex items-center justify-center gap-2">
                        <i data-lucide="log-in" class="w-4 h-4"></i> Entrar no App
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>

        <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 px-6 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center font-bold">
                    <i data-lucide="bike" class="w-5 h-5"></i>
                </div>
                <div>
                    <h1 class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($_SESSION['entregador_nome']); ?> <span class="text-xs font-normal text-slate-500">(<?php echo htmlspecialchars($nomeLoja); ?>)</span></h1>
                    <p class="text-[11px] text-emerald-600 font-medium flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Online (Loja Vinculada ID #<?php echo $loja_id; ?>)
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="../logout.php" class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5">
                    <i data-lucide="log-out" class="w-4 h-4"></i> Sair
                </a>
            </div>
        </header>

        <main class="flex-1 p-6 space-y-6 max-w-7xl w-full mx-auto">
            
            <?php if ($sucessoMensagem): ?>
                <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 shadow-sm flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                        </div>
                        <p class="text-xs font-semibold text-emerald-800"><?php echo htmlspecialchars($sucessoMensagem); ?></p>
                    </div>
                    <a href="index.php" class="text-emerald-500 hover:text-emerald-800 p-1"><i data-lucide="x" class="w-4 h-4"></i></a>
                </div>
            <?php endif; ?>

            <!-- ABAS DE NAVEGAÇÃO -->
            <div class="flex gap-2 border-b border-slate-200 pb-3">
                <button onclick="mudarAba('disponiveis')" id="btnTabDisp" class="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-orange-500 text-white shadow-sm">
                    Disponíveis para Aceitar (<span id="contadorDisp"><?php echo count($pedidosDisponiveis); ?></span>)
                </button>
                <button onclick="mudarAba('meus')" id="btnTabMeus" class="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-slate-200 text-slate-700">
                    Minhas Rotas Aceitas (<span id="contadorMeus"><?php echo count($pedidosMeus); ?></span>)
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-5 space-y-4">
                    
                    <!-- PEDIDOS DISPONÍVEIS -->
                    <div id="aba-disponiveis" class="space-y-4">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pedidos Disponíveis na Loja <?php echo htmlspecialchars($nomeLoja); ?></h3>
                        <div id="listaPedidosDisponiveis" class="space-y-4">
                            <?php if (count($pedidosDisponiveis) > 0): ?>
                                <?php foreach ($pedidosDisponiveis as $pedido): ?>
                                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg">#<?php echo htmlspecialchars($pedido['codigo_pedido']); ?></span>
                                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-orange-50 text-orange-700 border border-orange-200">Disponível para Entrega</span>
                                        </div>

                                        <div class="space-y-1">
                                            <h4 class="font-bold text-slate-800 text-sm">Cliente: <?php echo htmlspecialchars($pedido['nome_cliente']); ?></h4>
                                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 flex items-center gap-2">
                                                <i data-lucide="map-pin" class="w-4 h-4 text-orange-500 shrink-0"></i>
                                                <span><?php echo htmlspecialchars($pedido['rua'] . ', ' . $pedido['numero'] . ' - ' . $pedido['bairro']); ?></span>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-100">
                                            <span class="text-slate-500">Valor: <strong class="text-slate-800">R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></strong></span>
                                            
                                            <div class="flex items-center gap-2">
                                                <form method="POST">
                                                    <input type="hidden" name="acao" value="rejeitar_pedido">
                                                    <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                                    <button type="submit" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-2 rounded-xl text-xs transition-colors">
                                                        Rejeitar
                                                    </button>
                                                </form>

                                                <form method="POST">
                                                    <input type="hidden" name="acao" value="aceitar_pedido">
                                                    <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                                    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-4 py-2 rounded-xl text-xs transition-colors shadow-sm flex items-center gap-1.5">
                                                        <i data-lucide="check" class="w-4 h-4"></i> Aceitar
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center space-y-2">
                                    <i data-lucide="inbox" class="w-8 h-8 text-slate-300 mx-auto"></i>
                                    <p class="text-xs text-slate-500 font-medium">Nenhum pedido disponível no momento.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- MEUS PEDIDOS EM ROTA -->
                    <div id="aba-meus" class="space-y-4 hidden">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Rotas Otimizadas por Coordenadas</h3>
                            <?php if (count($pedidosMeus) > 1): ?>
                                <span class="text-[10px] bg-emerald-100 text-emerald-800 px-2.5 py-1 rounded-full font-bold">✨ Roteirização Automática Ativa</span>
                            <?php endif; ?>
                        </div>

                        <div id="listaMeusPedidos" class="space-y-4">
                            <?php if (count($pedidosMeus) > 0): ?>
                                <?php $ordemSequencia = 1; foreach ($pedidosMeus as $pedido): ?>
                                    <div class="bg-white rounded-2xl border border-orange-200 shadow-sm p-5 space-y-4 relative overflow-hidden">
                                        <div class="absolute top-0 right-0 bg-orange-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl">
                                            Parada #<?php echo $ordemSequencia++; ?>
                                        </div>

                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold px-2.5 py-1 bg-orange-100 text-orange-800 rounded-lg">#<?php echo htmlspecialchars($pedido['codigo_pedido']); ?></span>
                                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200">Aceito (Em Rota)</span>
                                        </div>

                                        <div class="space-y-1">
                                            <h4 class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($pedido['nome_cliente']); ?></h4>
                                            <p class="text-xs text-slate-700 flex items-start gap-1.5 font-medium">
                                                <i data-lucide="map-pin" class="w-4 h-4 text-orange-500 shrink-0 mt-0.5"></i>
                                                <span><?php echo htmlspecialchars($pedido['endereco_entrega']); ?></span>
                                            </p>
                                        </div>

                                        <div class="pt-2">
                                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($pedido['latitude'] . ',' . $pedido['longitude']); ?>" target="_blank" class="w-full flex items-center justify-center gap-1.5 bg-orange-600 hover:bg-orange-700 text-white py-2.5 px-3 rounded-xl text-xs font-semibold transition-colors shadow-sm">
                                                <i data-lucide="navigation" class="w-3.5 h-3.5"></i> Navegar por Coordenadas Exatas (GPS)
                                            </a>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2 pt-1">
                                            <a href="https://wa.me/55<?php echo preg_replace('/\D/', '', $pedido['telefone_cliente']); ?>" target="_blank" class="flex items-center justify-center gap-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 py-2 px-3 rounded-xl text-xs font-semibold">
                                                <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> WhatsApp
                                            </a>
                                            <form method="POST">
                                                <input type="hidden" name="acao" value="marcar_entregue">
                                                <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                                <button type="submit" class="w-full flex items-center justify-center gap-1.5 bg-slate-800 hover:bg-slate-900 text-white py-2 px-3 rounded-xl text-xs font-semibold">
                                                    <i data-lucide="check-check" class="w-3.5 h-3.5"></i> Concluído
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center space-y-2">
                                    <i data-lucide="bike" class="w-8 h-8 text-slate-300 mx-auto"></i>
                                    <p class="text-xs text-slate-500 font-medium">Você não tem entregas ativas no momento. Aceite um pedido disponível.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <div class="lg:col-span-7 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm h-[500px] lg:h-auto flex flex-col">
                    <div class="mb-2 flex items-center justify-between text-xs text-slate-500">
                        <span id="status-gps" class="flex items-center gap-1.5 text-amber-600 font-medium">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> Sincronizando GPS...
                        </span>
                        <span id="coords-gps">-</span>
                    </div>
                    <div id="map" class="flex-1"></div>
                </div>

            </div>

        </main>
    <?php endif; ?>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        lucide.createIcons();

        function mudarAba(aba) {
            const btnDisp = document.getElementById('btnTabDisp');
            const btnMeus = document.getElementById('btnTabMeus');
            const divDisp = document.getElementById('aba-disponiveis');
            const divMeus = document.getElementById('aba-meus');

            if (aba === 'disponiveis') {
                btnDisp.className = "px-4 py-2 rounded-xl text-xs font-bold transition-all bg-orange-500 text-white shadow-sm";
                btnMeus.className = "px-4 py-2 rounded-xl text-xs font-bold transition-all bg-slate-200 text-slate-700";
                divDisp.classList.remove('hidden');
                divMeus.classList.add('hidden');
            } else {
                btnMeus.className = "px-4 py-2 rounded-xl text-xs font-bold transition-all bg-orange-500 text-white shadow-sm";
                btnDisp.className = "px-4 py-2 rounded-xl text-xs font-bold transition-all bg-slate-200 text-slate-700";
                divMeus.classList.remove('hidden');
                divDisp.classList.add('hidden');
            }
        }

        const lojaLat = <?php echo floatval($lojaLat); ?>;
        const lojaLon = <?php echo floatval($lojaLon); ?>;
        const nomeLojaStr = <?php echo json_encode($nomeLoja); ?>;
        const lojaEnderecoStr = <?php echo json_encode($lojaEnderecoStr); ?>;

        let map = L.map('map').setView([lojaLat, lojaLon], 14);
        let markerEntregador = null;
        let markersClientes = [];

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        const lojaIcon = L.divIcon({
            className: 'custom-loja-icon',
            html: '<div class="loja-icon" style="width:36px; height:36px;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>',
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });

        L.marker([lojaLat, lojaLon], { icon: lojaIcon }).addTo(map)
            .bindPopup(`<b>${nomeLojaStr} (Loja)</b><br>${lojaEnderecoStr}`);

        const motoIcon = L.divIcon({
            className: 'custom-moto-icon',
            html: '<div class="moto-icon" style="width:38px; height:38px;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/></svg></div>',
            iconSize: [38, 38],
            iconAnchor: [19, 19]
        });

        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;

                    document.getElementById('status-gps').innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500"></span> GPS Conectado';
                    document.getElementById('coords-gps').innerText = `${lat.toFixed(4)}, ${lon.toFixed(4)}`;

                    if (!markerEntregador) {
                        markerEntregador = L.marker([lat, lon], { icon: motoIcon }).addTo(map)
                            .bindPopup("<b>Sua Localização Atual</b>");
                    } else {
                        markerEntregador.setLatLng([lat, lon]);
                    }
                },
                (error) => { console.warn("GPS Indisponível"); },
                { enableHighAccuracy: true }
            );
        }

        <?php if (isset($_SESSION['entregador_id'])): ?>
        setInterval(() => {
            fetch('index.php?ajax_atualizacao=1')
                .then(res => res.json())
                .then(data => {
                    if (data.sucesso) {
                        atualizarInterfacePedidos(data.disponiveis, data.meus);
                    }
                })
                .catch(err => console.error("Erro na sincronização:", err));
        }, 2000);
        <?php endif; ?>

        function atualizarInterfacePedidos(disponiveis, meus) {
            document.getElementById('contadorDisp').innerText = disponiveis.length;
            document.getElementById('contadorMeus').innerText = meus.length;

            let htmlDisp = '';
            if (disponiveis.length > 0) {
                disponiveis.forEach(pedido => {
                    htmlDisp += `
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg">#${pedido.codigo_pedido}</span>
                                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-orange-50 text-orange-700 border border-orange-200">Disponível para Entrega</span>
                            </div>
                            <div class="space-y-1">
                                <h4 class="font-bold text-slate-800 text-sm">Cliente: ${pedido.nome_cliente}</h4>
                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 flex items-center gap-2">
                                    <i data-lucide="map-pin" class="w-4 h-4 text-orange-500 shrink-0"></i>
                                    <span>${pedido.rua}, ${pedido.numero} - ${pedido.bairro}</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-100">
                                <span class="text-slate-500">Valor: <strong class="text-slate-800">R$ ${Number(pedido.total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong></span>
                                <div class="flex items-center gap-2">
                                    <form method="POST">
                                        <input type="hidden" name="acao" value="rejeitar_pedido">
                                        <input type="hidden" name="pedido_id" value="${pedido.id}">
                                        <button type="submit" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-2 rounded-xl text-xs transition-colors">Rejeitar</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="acao" value="aceitar_pedido">
                                        <input type="hidden" name="pedido_id" value="${pedido.id}">
                                        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-4 py-2 rounded-xl text-xs transition-colors shadow-sm flex items-center gap-1.5">
                                            <i data-lucide="check" class="w-4 h-4"></i> Aceitar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>`;
                });
            } else {
                htmlDisp = `
                    <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center space-y-2">
                        <i data-lucide="inbox" class="w-8 h-8 text-slate-300 mx-auto"></i>
                        <p class="text-xs text-slate-500 font-medium">Nenhum pedido disponível no momento.</p>
                    </div>`;
            }
            document.getElementById('listaPedidosDisponiveis').innerHTML = htmlDisp;

            let htmlMeus = '';
            markersClientes.forEach(m => map.removeLayer(m));
            markersClientes = [];

            if (meus.length > 0) {
                let ordem = 1;
                meus.forEach(pedido => {
                    htmlMeus += `
                        <div class="bg-white rounded-2xl border border-orange-200 shadow-sm p-5 space-y-4 relative overflow-hidden">
                            <div class="absolute top-0 right-0 bg-orange-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-xl">Parada #${ordem}</div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold px-2.5 py-1 bg-orange-100 text-orange-800 rounded-lg">#${pedido.codigo_pedido}</span>
                                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200">Aceito (Em Rota)</span>
                            </div>
                            <div class="space-y-1">
                                <h4 class="font-bold text-slate-800 text-base">${pedido.nome_cliente}</h4>
                                <p class="text-xs text-slate-700 flex items-start gap-1.5 font-medium">
                                    <i data-lucide="map-pin" class="w-4 h-4 text-orange-500 shrink-0 mt-0.5"></i>
                                    <span>${pedido.endereco_entrega}</span>
                                </p>
                            </div>
                            <div class="pt-2">
                                <a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(pedido.latitude + ',' + pedido.longitude)}" target="_blank" class="w-full flex items-center justify-center gap-1.5 bg-orange-600 hover:bg-orange-700 text-white py-2.5 px-3 rounded-xl text-xs font-semibold transition-colors shadow-sm">
                                    <i data-lucide="navigation" class="w-3.5 h-3.5"></i> Navegar por Coordenadas Exatas (GPS)
                                </a>
                            </div>
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <a href="https://wa.me/55${pedido.telefone_cliente.replace(/\D/g, '')}" target="_blank" class="flex items-center justify-center gap-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 py-2 px-3 rounded-xl text-xs font-semibold">
                                    <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> WhatsApp
                                </a>
                                <form method="POST">
                                    <input type="hidden" name="acao" value="marcar_entregue">
                                    <input type="hidden" name="pedido_id" value="${pedido.id}">
                                    <button type="submit" class="w-full flex items-center justify-center gap-1.5 bg-slate-800 hover:bg-slate-900 text-white py-2 px-3 rounded-xl text-xs font-semibold">
                                        <i data-lucide="check-check" class="w-3.5 h-3.5"></i> Concluído
                                    </button>
                                </form>
                            </div>
                        </div>`;

                    if (pedido.latitude && pedido.longitude) {
                        let cLat = parseFloat(pedido.latitude);
                        let cLon = parseFloat(pedido.longitude);

                        const customClienteIcon = L.divIcon({
                            className: 'custom-cliente-icon',
                            html: `<div class="cliente-icon" style="width:36px; height:36px;">#${ordem}</div>`,
                            iconSize: [36, 36],
                            iconAnchor: [18, 18]
                        });

                        let markerCli = L.marker([cLat, cLon], { icon: customClienteIcon }).addTo(map)
                            .bindPopup(`<b>Parada #${ordem}: ${pedido.nome_cliente}</b><br>${pedido.endereco_entrega}`);
                        markersClientes.push(markerCli);
                    }
                    ordem++;
                });
            } else {
                htmlMeus = `
                    <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center space-y-2">
                        <i data-lucide="bike" class="w-8 h-8 text-slate-300 mx-auto"></i>
                        <p class="text-xs text-slate-500 font-medium">Você não tem entregas ativas no momento. Aceite um pedido disponível.</p>
                    </div>`;
            }
            document.getElementById('listaMeusPedidos').innerHTML = htmlMeus;
            lucide.createIcons();
        }
    </script>
</body>
</html>