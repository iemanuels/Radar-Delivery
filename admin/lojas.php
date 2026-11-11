<?php
// admin/lojas.php
require_once 'auth_master.php';
require_once 'config.php';

// Garantir que a tabela de configurações globais exista para ler os dias de teste padrão
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `configuracoes_sistema` (
          `chave` varchar(100) NOT NULL PRIMARY KEY,
          `valor` text NOT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
    ");
} catch (PDOException $e) {}

// Buscar dias de teste padrão configurados (padrão 7 dias)
$stmtConfig = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'dias_teste_padrao'");
$stmtConfig->execute();
$configTeste = $stmtConfig->fetch(PDO::FETCH_ASSOC);
$diasTestePadrao = $configTeste ? intval($configTeste['valor']) : 7;

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_loja') {
        $id = $_POST['id'] ?? '';
        $nome_loja = trim($_POST['nome_loja']);
        $slug = trim($_POST['slug']);
        $whatsapp = trim($_POST['whatsapp']);
        $endereco = trim($_POST['endereco']);
        $plano_id = (!empty($_POST['plano_id'])) ? intval($_POST['plano_id']) : null;
        $status_loja = $_POST['status_loja'];

        // Cálculo automático da data de vencimento com base no plano selecionado
        if (!empty($plano_id)) {
            // Buscar dados do plano para ver se é mensal ou anual/vitalício
            $stmtPlanoInfo = $pdo->prepare("SELECT ciclo FROM planos WHERE id = ?");
            $stmtPlanoInfo->execute([$plano_id]);
            $infoPlano = $stmtPlanoInfo->fetch(PDO::FETCH_ASSOC);

            if ($infoPlano && $infoPlano['ciclo'] === 'anual') {
                $data_vencimento = null; // Vitalício / Sem vencimento próximo
            } else {
                // Plano Mensal (30 dias a partir de hoje)
                $data_vencimento = date('Y-m-d', strtotime('+30 days'));
            }
        } else {
            // Plano Teste / Gratuito (usa os dias configurados manualmente no sistema)
            $dias_teste_input = intval($_POST['dias_teste'] ?? $diasTestePadrao);
            $data_vencimento = date('Y-m-d', strtotime("+{$dias_teste_input} days"));
        }

        try {
            if (!empty($id)) {
                // Se for edição, permite manter ou atualizar se solicitado
                $stmt = $pdo->prepare("UPDATE lojas SET nome_loja = ?, slug = ?, whatsapp = ?, endereco = ?, plano_id = ?, status_loja = ?, data_vencimento = ? WHERE id = ?");
                $stmt->execute([$nome_loja, $slug, $whatsapp, $endereco, $plano_id, $status_loja, $data_vencimento, $id]);
                
                $mensagem = "Loja atualizada com sucesso!";
                $tipoMensagem = "sucesso";
            } else {
                // Inserir nova loja
                $stmt = $pdo->prepare("INSERT INTO lojas (nome_loja, slug, whatsapp, endereco, plano_id, status_loja, data_vencimento) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nome_loja, $slug, $whatsapp, $endereco, $plano_id, $status_loja, $data_vencimento]);
                
                $mensagem = "Nova loja cadastrada com sucesso!";
                $tipoMensagem = "sucesso";
            }
        } catch (PDOException $e) {
            $mensagem = "Erro ao salvar loja: " . $e->getMessage();
            $tipoMensagem = "erro";
        }
    } elseif ($acao === 'excluir_loja') {
        $id = intval($_POST['id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM lojas WHERE id = ?");
            $stmt->execute([$id]);
            $mensagem = "Loja removida com sucesso!";
            $tipoMensagem = "sucesso";
        } catch (PDOException $e) {
            $mensagem = "Erro ao remover loja: " . $e->getMessage();
            $tipoMensagem = "erro";
        }
    }
}

// Buscar planos disponíveis para os selects
$stmtPlanos = $pdo->query("SELECT * FROM planos ORDER BY id ASC");
$planos = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);

// Buscar valores dos planos para cálculo em JavaScript se necessário
$planosValores = [];
foreach ($planos as $p) {
    $planosValores[$p['id']] = [
        'nome' => $p['nome_plano'],
        'valor' => floatval($p['valor'])
    ];
}

// Buscar todas as lojas cadastradas no sistema com o administrador/responsável correspondente
$stmtLojas = $pdo->query("
    SELECT l.*, 
           COALESCE(p.nome_plano, 'Teste / Gratuito') as nome_plano_atual,
           COALESCE(p.valor, 0.00) as plano_valor,
           (SELECT a.nome FROM admins a WHERE a.loja_id = l.id AND a.tipo = 'loja' LIMIT 1) as admin_nome,
           (SELECT COUNT(*) FROM pedidos pe WHERE pe.loja_id = l.id) as total_pedidos,
           (SELECT COALESCE(SUM(total), 0) FROM pedidos pe WHERE pe.loja_id = l.id AND pe.status_pedido != 'cancelado') as faturamento_total
    FROM lojas l
    LEFT JOIN planos p ON l.plano_id = p.id
    ORDER BY l.id DESC
");
$lojas = $stmtLojas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Lojas | Painel Master</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }

        @media screen and (max-width: 768px) {
            input[type="text"],
            input[type="number"],
            input[type="email"],
            input[type="password"],
            select,
            textarea {
                font-size: 16px !important;
            }
        }

        /* Estilos específicos para impressão/geração de PDF estilo boleto/cobrança bancária */
        @media print {
            body * {
                visibility: hidden;
            }
            #modalImpressaoFatura, #modalImpressaoFatura * {
                visibility: visible;
            }
            #modalImpressaoFatura {
                position: fixed;
                left: 0;
                top: 0;
                width: 100vw;
                height: 100vh;
                background: white !important;
                display: flex !important;
                align-items: center;
                justify-content: center;
                backdrop-filter: none !important;
                z-index: 9999;
                padding: 0;
            }
            .fatura-boleto-container {
                border: 2px solid #0f172a !important;
                box-shadow: none !important;
                max-width: 100% !important;
                width: 100% !important;
                height: 100% !important;
                border-radius: 0 !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Menu Superior Compartilhado -->
    <?php include 'menu.php'; ?>

    <!-- Conteúdo Principal -->
    <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">
        
        <!-- Cabeçalho e Botão Novo -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-800 tracking-tight">Gestão de Lojas e Planos</h2>
                <p class="text-xs text-slate-500 mt-0.5">Cadastre novas lojas, configure prazos de teste automáticos e acompanhe vencimentos.</p>
            </div>
            <button onclick="abrirModalNovaLoja()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2.5 rounded-xl text-xs font-semibold transition-colors flex items-center justify-center gap-2 shadow-sm">
                <i data-lucide="plus" class="w-4 h-4"></i> Cadastrar Nova Loja
            </button>
        </div>

        <!-- Alertas de Feedback -->
        <?php if (!empty($mensagem)): ?>
            <div class="p-4 rounded-xl text-xs font-medium flex items-center gap-2 border shadow-sm <?php echo $tipoMensagem === 'sucesso' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'; ?>">
                <i data-lucide="<?php echo $tipoMensagem === 'sucesso' ? 'check-circle-2' : 'alert-circle'; ?>" class="w-4 h-4 shrink-0"></i>
                <span><?php echo htmlspecialchars($mensagem); ?></span>
            </div>
        <?php endif; ?>

        <!-- Tabela de Lojas -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Lojas Registradas</h3>
                    <p class="text-xs text-slate-400">Total de <?php echo count($lojas); ?> lojas no sistema.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50/75 border-b border-slate-100 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                            <th class="py-3.5 px-6">ID</th>
                            <th class="py-3.5 px-6">Loja / Responsável</th>
                            <th class="py-3.5 px-6">WhatsApp</th>
                            <th class="py-3.5 px-6">Plano / Status / Vencimento</th>
                            <th class="py-3.5 px-6">Pedidos</th>
                            <th class="py-3.5 px-6">Faturamento</th>
                            <th class="py-3.5 px-6 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-600">
                        <?php if (count($lojas) > 0): ?>
                            <?php foreach ($lojas as $loja): ?>
                                <?php 
                                    // Cálculo de proximidade do vencimento
                                    $badgeVencimentoClass = "text-slate-700";
                                    $textoVencimentoAviso = "";
                                    $vencimentoFormatado = 'Vitalício / Indeterminado';
                                    if (!empty($loja['data_vencimento'])) {
                                        $hoje = new DateTime();
                                        $venc = new DateTime($loja['data_vencimento']);
                                        $diff = $hoje->diff($venc)->days;
                                        $isPassado = $hoje > $venc;
                                        $vencimentoFormatado = date('d/m/Y', strtotime($loja['data_vencimento']));

                                        if ($isPassado) {
                                            $badgeVencimentoClass = "bg-rose-100 text-rose-800 font-bold px-1.5 py-0.5 rounded text-[10px]";
                                            $textoVencimentoAviso = " (Vencido!)";
                                        } elseif ($diff <= 3) {
                                            $badgeVencimentoClass = "bg-amber-100 text-amber-800 font-bold px-1.5 py-0.5 rounded text-[10px]";
                                            $textoVencimentoAviso = " (Vence em {$diff}d)";
                                        }
                                    }

                                    // Paleta de cores para avatares
                                    $paletaCoresClaras = [
                                        ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'border' => 'border-orange-200'],
                                        ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-200'],
                                        ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'border' => 'border-purple-200'],
                                        ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-200'],
                                        ['bg' => 'bg-pink-50', 'text' => 'text-pink-600', 'border' => 'border-pink-200'],
                                        ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-200'],
                                        ['bg' => 'bg-teal-50', 'text' => 'text-teal-600', 'border' => 'border-teal-200'],
                                        ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'border' => 'border-indigo-200']
                                    ];
                                    $corAleatoria = $paletaCoresClaras[$loja['id'] % count($paletaCoresClaras)];
                                    $nomeLojaTrim = trim($loja['nome_loja']);
                                    $iniciaisLoja = mb_strtoupper(mb_substr($nomeLojaTrim, 0, 2));

                                    // Dados para Cobrança
                                    $valorPlano = floatval($loja['plano_valor']);
                                    if ($valorPlano <= 0) $valorPlano = 49.90; // Valor padrão caso seja teste
                                    
                                    // Limpar WhatsApp para link
                                    $zapLimpo = preg_replace('/[^0-9]/', '', $loja['whatsapp']);
                                    if (strlen($zapLimpo) <= 11 && strpos($zapLimpo, '55') !== 0) {
                                        $zapLimpo = '55' . $zapLimpo;
                                    }

                                    // Mensagem do WhatsApp com apenas "Emanuel"
                                    $msgZap = urlencode("Olá *" . $loja['nome_loja'] . "*,\n\nAqui é o *Emanuel*. Passando para lembrar sobre a fatura do seu sistema no valor de *R$ " . number_format($valorPlano, 2, ',', '.') . "* com vencimento em *" . $vencimentoFormatado . "*.\n\n*Chave Pix (Celular):* (92) 98102-8812\nFavorecido: Emanuel Silva da Silva\n\nPor favor, envie o comprovante por aqui após o pagamento. Obrigado!");
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-4 px-6 font-bold text-slate-800">#<?php echo $loja['id']; ?></td>
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-3">
                                            <?php if (!empty($loja['logo']) && file_exists('../' . $loja['logo'])): ?>
                                                <img src="../<?php echo htmlspecialchars($loja['logo']); ?>" alt="Logo" class="w-10 h-10 rounded-xl object-cover border border-slate-200 shadow-xs shrink-0">
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-xl <?php echo $corAleatoria['bg'] . ' ' . $corAleatoria['text'] . ' ' . $corAleatoria['border']; ?> border flex items-center justify-center font-bold text-xs tracking-wider shadow-xs shrink-0">
                                                    <?php echo $iniciaisLoja; ?>
                                                </div>
                                            <?php endif; ?>

                                            <div>
                                                <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($loja['nome_loja']); ?></div>
                                                <div class="text-[11px] text-slate-400">Admin: <span class="text-slate-600 font-medium"><?php echo htmlspecialchars($loja['admin_nome'] ?? 'Não vinculado'); ?></span></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 text-slate-500 font-medium"><?php echo htmlspecialchars($loja['whatsapp']); ?></td>
                                    <td class="py-4 px-6">
                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex items-center gap-1.5">
                                                <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-2.5 py-0.5 rounded-md text-xs font-medium border border-slate-200">
                                                    <i data-lucide="tag" class="w-3 h-3 text-slate-400"></i> 
                                                    <?php echo htmlspecialchars($loja['nome_plano_atual']); ?>
                                                </span>
                                                <?php if ($loja['status_loja'] === 'ativo'): ?>
                                                    <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full text-[10px] font-semibold">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Ativo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded-full text-[10px] font-semibold">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Inativo
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[11px] text-slate-500 flex items-center gap-1">
                                                <i data-lucide="calendar" class="w-3 h-3 text-slate-400"></i>
                                                Vencimento: 
                                                <span class="<?php echo $badgeVencimentoClass; ?>">
                                                    <?php echo $vencimentoFormatado . $textoVencimentoAviso; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 font-medium text-slate-800"><?php echo $loja['total_pedidos']; ?></td>
                                    <td class="py-4 px-6 font-semibold text-slate-800">R$ <?php echo number_format($loja['faturamento_total'], 2, ',', '.'); ?></td>
                                    <td class="py-4 px-6 text-right space-x-1 whitespace-nowrap">
                                        <!-- Botão WhatsApp Cobrança -->
                                        <?php if (!empty($loja['whatsapp'])): ?>
                                            <a href="https://wa.me/<?php echo $zapLimpo; ?>?text=<?php echo $msgZap; ?>" target="_blank" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-600 p-2 rounded-xl transition-colors inline-flex items-center justify-center" title="Enviar Cobrança via WhatsApp">
                                                <i data-lucide="message-circle" class="w-4 h-4"></i>
                                            </a>
                                        <?php endif; ?>

                                        <!-- Botão Gerar PDF Cobrança Pix Estilo Boleto -->
                                        <button onclick="abrirModalPdf('<?php echo htmlspecialchars($loja['nome_loja'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($loja['admin_nome'] ?? 'Responsável', ENT_QUOTES); ?>', '<?php echo number_format($valorPlano, 2, ',', '.'); ?>', '<?php echo $vencimentoFormatado; ?>', '<?php echo $loja['id']; ?>')" class="bg-purple-50 hover:bg-purple-100 text-purple-600 p-2 rounded-xl transition-colors inline-flex items-center justify-center" title="Gerar Fatura / Boleto em PDF (Pix)">
                                            <i data-lucide="file-text" class="w-4 h-4"></i>
                                        </button>

                                        <!-- Botão de Visualização (Olho) -->
                                        <?php 
                                            $tokenAleatorio = bin2hex(random_bytes(8));
                                            $urlDashboard = "../dashboard.php?loja_id=" . $loja['id'] . "&token=" . $tokenAleatorio;
                                        ?>
                                        <a href="<?php echo $urlDashboard; ?>" target="_blank" class="bg-blue-50 hover:bg-blue-100 text-blue-600 p-2 rounded-xl transition-colors inline-flex items-center justify-center" title="Acessar Dashboard da Loja">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>

                                        <button onclick='editarLoja(<?php echo json_encode($loja); ?>)' class="bg-slate-100 hover:bg-slate-200 text-slate-700 p-2 rounded-xl transition-colors inline-flex items-center justify-center" title="Editar Loja">
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </button>
                                        
                                        <button onclick='excluirLoja(<?php echo $loja['id']; ?>, "<?php echo htmlspecialchars($loja['nome_loja']); ?>")' class="bg-rose-50 hover:bg-rose-100 text-rose-600 p-2 rounded-xl transition-colors inline-flex items-center justify-center" title="Excluir Loja">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400 text-sm">
                                    Nenhuma loja cadastrada no sistema.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Modal para Visualização e Impressão de Fatura / Boleto Bancário -->
    <div id="modalImpressaoFatura" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div class="fatura-boleto-container bg-white rounded-2xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-slate-300 space-y-6 relative font-sans">
            <button onclick="fecharModalPdf()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 no-print">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>

            <!-- Cabeçalho Institucional Estilo Boleto -->
            <div class="border-b-2 border-slate-800 pb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-base tracking-widest">
                        ES
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 uppercase tracking-wide">Emanuel Silva da Silva</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Documento de Cobrança / Fatura de Sistema</p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="inline-block bg-slate-100 text-slate-800 border border-slate-300 text-xs font-bold px-3 py-1 rounded">
                        FATURA #<span id="faturaNumeroId"></span>
                    </span>
                    <div class="text-[11px] text-slate-500 mt-1">Data de Emissão: <span id="faturaData"></span></div>
                </div>
            </div>

            <!-- Dados do Beneficiário e Pagador (Grid Estilo Boleto) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 space-y-1">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Beneficiário (Credor)</span>
                    <div class="font-bold text-slate-800">Emanuel Silva da Silva</div>
                    <div class="text-slate-600">Chave Pix (Celular): <strong>(92) 98102-8812</strong></div>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 space-y-1">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Pagador (Cliente)</span>
                    <div class="font-bold text-slate-800" id="faturaLoja"></div>
                    <div class="text-slate-600">Responsável: <span id="faturaResponsavel"></span></div>
                </div>
            </div>

            <!-- Tabela de Valores e Vencimento -->
            <div class="border border-slate-300 rounded-lg overflow-hidden text-xs">
                <div class="bg-slate-100 border-b border-slate-300 px-4 py-2 grid grid-cols-12 font-bold text-slate-700 uppercase tracking-wider text-[11px]">
                    <div class="col-span-6">Descrição do Serviço / Histórico</div>
                    <div class="col-span-3 text-center">Vencimento</div>
                    <div class="col-span-3 text-right">Valor (R$)</div>
                </div>
                <div class="px-4 py-3 grid grid-cols-12 items-center text-slate-800 text-sm font-medium">
                    <div class="col-span-6 text-xs">Renovação Mensal / Assinatura de Plataforma SaaS</div>
                    <div class="col-span-3 text-center font-semibold text-slate-600 text-xs" id="faturaVencimento"></div>
                    <div class="col-span-3 text-right font-bold text-slate-900">R$ <span id="faturaValor"></span></div>
                </div>
            </div>

            <!-- Resumo / Total -->
            <div class="flex justify-between items-center bg-slate-900 text-white px-5 py-3 rounded-xl">
                <span class="text-xs font-semibold uppercase tracking-wider">Valor Total do Documento</span>
                <span class="text-lg font-extrabold tracking-tight">R$ <span id="faturaValorTotal"></span></span>
            </div>

            <!-- Bloco Pix Formal / Instruções -->
            <div class="border-2 border-emerald-600/40 bg-emerald-50/50 rounded-xl p-4 space-y-2 text-xs">
                <div class="font-bold text-emerald-900 uppercase tracking-wide flex items-center gap-2 text-sm">
                    <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i> Pagamento Instantâneo via Pix
                </div>
                <p class="text-slate-600">Utilize a chave abaixo no aplicativo do seu banco para realizar a transferência instantânea:</p>
                <div class="bg-white border border-emerald-200 rounded-lg p-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 shadow-xs">
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase font-semibold block">Chave Pix (Celular)</span>
                        <strong class="text-slate-900 text-sm font-mono">(92) 98102-8812</strong>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase font-semibold block">Favorecido</span>
                        <strong class="text-slate-800 text-xs">Emanuel Silva da Silva</strong>
                    </div>
                </div>
            </div>

            <!-- Instruções de Baixa -->
            <div class="text-[11px] text-slate-500 text-center border-t border-slate-200 pt-3">
                Instruções de entrega: após efetuar o pagamento, envie o comprovante digital via WhatsApp para o número <strong>(92) 98102-8812</strong> para a ativação/baixa imediata da fatura.
            </div>

            <!-- Ações do Modal -->
            <div class="flex items-center justify-end gap-3 pt-2 no-print">
                <button type="button" onclick="fecharModalPdf()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-xs font-semibold transition-colors">
                    Fechar
                </button>
                <button type="button" onclick="window.print()" class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-2 shadow-sm">
                    <i data-lucide="printer" class="w-4 h-4"></i> Imprimir / Salvar PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Cadastro / Edição de Loja -->
    <div id="modalLoja" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 id="modalTitulo" class="text-base font-bold text-slate-800">Cadastrar Nova Loja</h3>
                <button onclick="fecharModalLoja()" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form action="lojas.php" method="POST" class="space-y-4">
                <input type="hidden" name="acao" value="salvar_loja">
                <input type="hidden" id="loja_id" name="id" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nome da Loja</label>
                    <input type="text" id="nome_loja" name="nome_loja" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Slug (URL amigável)</label>
                        <input type="text" id="slug" name="slug" required placeholder="ex: minha-loja" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">WhatsApp</label>
                        <input type="text" id="whatsapp" name="whatsapp" required placeholder="92991234567" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Endereço Completo</label>
                    <textarea id="endereco" name="endereco" rows="2" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Plano da Loja</label>
                        <select id="plano_id" name="plano_id" onchange="verificarPlanoSelecionado()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                            <option value="">Teste / Gratuito</option>
                            <?php foreach ($planos as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nome_plano']); ?> (R$ <?php echo number_format($p['valor'], 2, ',', '.'); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Status da Loja</label>
                        <select id="status_loja" name="status_loja" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>

                <!-- Campo dinâmico para dias de teste se for plano gratuito -->
                <div id="bloco_dias_teste">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Tempo de Teste (em dias)</label>
                    <input type="number" id="dias_teste" name="dias_teste" value="<?php echo $diasTestePadrao; ?>" min="1" max="365" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                    <p class="text-[11px] text-slate-400 mt-1">Calcula automaticamente o vencimento a partir de hoje com base nestes dias.</p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="button" onclick="fecharModalLoja()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-colors shadow-sm">
                        Salvar Loja
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formulário oculto para exclusão -->
    <form id="formExcluir" action="lojas.php" method="POST" class="hidden">
        <input type="hidden" name="acao" value="excluir_loja">
        <input type="hidden" id="excluir_id" name="id" value="">
    </form>

    <footer class="mt-auto py-6 text-center text-xs text-slate-400 border-t border-slate-200 bg-white">
        Painel Master &copy; <?php echo date('Y'); ?> Todos os direitos reservados.
    </footer>

    <script>
        lucide.createIcons();

        function verificarPlanoSelecionado() {
            const planoId = document.getElementById('plano_id').value;
            const blocoDiasTeste = document.getElementById('bloco_dias_teste');
            if (planoId === "") {
                blocoDiasTeste.style.display = 'block';
            } else {
                blocoDiasTeste.style.display = 'none';
            }
        }

        function abrirModalNovaLoja() {
            document.getElementById('modalTitulo').innerText = 'Cadastrar Nova Loja';
            document.getElementById('loja_id').value = '';
            document.getElementById('nome_loja').value = '';
            document.getElementById('slug').value = '';
            document.getElementById('whatsapp').value = '';
            document.getElementById('endereco').value = '';
            document.getElementById('plano_id').value = '';
            document.getElementById('status_loja').value = 'ativo';
            document.getElementById('dias_teste').value = '<?php echo $diasTestePadrao; ?>';
            verificarPlanoSelecionado();
            document.getElementById('modalLoja').classList.remove('hidden');
            document.getElementById('modalLoja').classList.add('flex');
        }

        function editarLoja(loja) {
            document.getElementById('modalTitulo').innerText = 'Editar Loja #' + loja.id;
            document.getElementById('loja_id').value = loja.id;
            document.getElementById('nome_loja').value = loja.nome_loja;
            document.getElementById('slug').value = loja.slug;
            document.getElementById('whatsapp').value = loja.whatsapp;
            document.getElementById('endereco').value = loja.endereco;
            document.getElementById('plano_id').value = loja.plano_id || '';
            document.getElementById('status_loja').value = loja.status_loja;
            verificarPlanoSelecionado();
            document.getElementById('modalLoja').classList.remove('hidden');
            document.getElementById('modalLoja').classList.add('flex');
        }

        function fecharModalLoja() {
            document.getElementById('modalLoja').classList.remove('flex');
            document.getElementById('modalLoja').classList.add('hidden');
        }

        function excluirLoja(id, nome) {
            if (confirm('Tem certeza que deseja excluir a loja "' + nome + '"? Todos os dados associados serão perdidos.')) {
                document.getElementById('excluir_id').value = id;
                document.getElementById('formExcluir').submit();
            }
        }

        // Funções para gerenciar o modal de impressão do Boleto/Fatura
        function abrirModalPdf(nomeLoja, responsavel, valor, vencimento, idLoja) {
            document.getElementById('faturaLoja').innerText = nomeLoja;
            document.getElementById('faturaResponsavel').innerText = responsavel;
            document.getElementById('faturaValor').innerText = valor;
            document.getElementById('faturaValorTotal').innerText = valor;
            document.getElementById('faturaVencimento').innerText = vencimento;
            document.getElementById('faturaNumeroId').innerText = '2026' + String(idLoja).padStart(4, '0');
            document.getElementById('faturaData').innerText = new Date().toLocaleDateString('pt-BR');

            const modal = document.getElementById('modalImpressaoFatura');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            lucide.createIcons();
        }

        function fecharModalPdf() {
            const modal = document.getElementById('modalImpressaoFatura');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    </script>
</body>
</html>