<?php
// configuracoes.php - Configurações da Loja em Tempo Real
$currentPage = 'configuracoes';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

try {
    if (file_exists('admin/config.php')) {
        require_once 'admin/config.php';
    } elseif (file_exists('../admin/config.php')) {
        require_once '../admin/config.php';
    } else {
        throw new Exception("O arquivo de configuração 'config.php' não foi encontrado na pasta admin/.");
    }

    if (!isset($pdo)) {
        throw new Exception("A conexão com o banco de dados (\$pdo) não está inicializada.");
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // ==========================================
    // DEFINIÇÃO DO LOJA_ID (MASTER vs USUÁRIO LOGADO)
    // ==========================================
    $isMasterAccess = false;
    $loja_id = 0;

    if (isset($_GET['loja_id']) && !empty($_GET['loja_id']) && isset($_GET['token'])) {
        $loja_id = intval($_GET['loja_id']);
        $isMasterAccess = true;
        $_SESSION['master_view_loja_id'] = $loja_id;
        $_SESSION['master_token'] = $_GET['token'];
    } elseif (isset($_SESSION['master_view_loja_id']) && isset($_SESSION['master_token'])) {
        $loja_id = intval($_SESSION['master_view_loja_id']);
        $isMasterAccess = true;
    } else {
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

    if ($loja_id <= 0) {
        $loja_id = 1;
    }

    $pdo->exec("SET time_zone = '-04:00'");

    $stmtLoja = $pdo->prepare("SELECT * FROM lojas WHERE id = ?");
    $stmtLoja->execute([$loja_id]);
    $loja = $stmtLoja->fetch(PDO::FETCH_ASSOC);

    if (!$loja) {
        throw new Exception("Nenhuma loja encontrada com o ID {$loja_id}.");
    }

    // Decodificar horários de funcionamento salvos em JSON
    $horariosDias = json_decode($loja['horarios_funcionamento'] ?? '', true);
    if (!is_array($horariosDias)) {
        $diasPadrao = ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'];
        $horariosDias = [];
        foreach ($diasPadrao as $d) {
            $horariosDias[$d] = [
                'ativo' => 1,
                'abertura' => '08:00',
                'fechamento' => '18:00',
                'tem_almoco' => 0,
                'almoco_inicio' => '12:00',
                'almoco_fim' => '13:00'
            ];
        }
    }

    // BUSCA DE LOGS RESUMIDA (Últimos 15, excluindo db_update se necessário)
    $stmtLogs = $pdo->prepare("SELECT id, nivel, mensagem, criado_em FROM sistema_logs WHERE loja_id = ? AND mensagem NOT LIKE '%[db_update]%' ORDER BY criado_em DESC LIMIT 15");
    $stmtLogs->execute([$loja_id]);
    $logsSistema = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $erroSistema = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Configurações da Loja | <?php echo htmlspecialchars($loja['nome_loja'] ?? 'Painel'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .cropper-view-box, .cropper-face { border-radius: 12px; }
        .mapa-container { height: 350px; width: 100%; border-radius: 0.75rem; z-index: 10; }
        @media (max-width: 768px) {
            input, select, textarea { font-size: 16px !important; }
        }
    </style>
    <link rel="icon" type="image/png" href="icon.php">
    <link rel="apple-touch-icon" href="icon.php">
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col md:flex-row">

    <!-- SKELETON LOADER SCREEN -->
    <div id="pageSkeleton" class="fixed inset-0 z-50 bg-slate-50 flex flex-col md:flex-row transition-opacity duration-500">
        <div class="hidden md:flex w-64 bg-white border-r border-slate-200 p-6 flex-col space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-slate-200 rounded-xl animate-pulse"></div>
                <div class="space-y-2 flex-1"><div class="h-4 bg-slate-200 rounded w-3/4 animate-pulse"></div><div class="h-3 bg-slate-200 rounded w-1/2 animate-pulse"></div></div>
            </div>
            <div class="space-y-3 pt-4"><div class="h-8 bg-slate-200 rounded-xl animate-pulse"></div><div class="h-8 bg-slate-200 rounded-xl animate-pulse"></div><div class="h-8 bg-slate-200 rounded-xl animate-pulse"></div></div>
        </div>
        <div class="flex-1 flex flex-col p-6 space-y-6">
            <div class="h-16 bg-white rounded-2xl border border-slate-200 shadow-sm animate-pulse flex items-center px-6">
                <div class="h-5 bg-slate-200 rounded w-48 animate-pulse"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="h-64 bg-white rounded-2xl border border-slate-200 shadow-sm animate-pulse p-6"></div>
                <div class="md:col-span-2 h-64 bg-white rounded-2xl border border-slate-200 shadow-sm animate-pulse p-6"></div>
            </div>
            <div class="h-48 bg-white rounded-2xl border border-slate-200 shadow-sm animate-pulse p-6"></div>
        </div>
    </div>

    <?php include 'includes/menu.php'; ?>

    <!-- CARD FLUTUANTE CENTRALIZADO DE NOTIFICAÇÃO (CHECK ANIMADO) -->
    <div id="saveIndicator" class="fixed top-5 left-1/2 -translate-x-1/2 z-50 transition-all duration-300 transform -translate-y-12 opacity-0 pointer-events-none">
        <div class="bg-slate-900/90 backdrop-blur-md text-white px-5 py-3 rounded-2xl shadow-xl border border-slate-700/60 flex items-center gap-3">
            <div class="w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-lg shadow-emerald-500/30 animate-bounce">
                <i data-lucide="check" class="w-4 h-4 stroke-[3]"></i>
            </div>
            <div>
                <p class="text-xs font-bold tracking-wide">Salvo com sucesso</p>
                <p class="text-[10px] text-slate-400"><?php echo htmlspecialchars($loja['nome_loja'] ?? 'Loja'); ?></p>
            </div>
        </div>
    </div>

    <div class="flex-1 flex flex-col md:pl-64 min-w-0">
        
        <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 px-4 sm:px-6 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <button id="openSidebarBtn" class="md:hidden text-slate-500 hover:text-slate-700 p-2 rounded-xl border border-slate-200 bg-white cursor-pointer">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <h1 class="text-base sm:text-lg font-bold text-slate-800 truncate">Configurações da Loja</h1>
            </div>
        </header>

        <main class="flex-1 p-3 sm:p-6 max-w-5xl w-full mx-auto space-y-6">
            
            <?php if (isset($erroSistema)): ?>
                <div class="bg-rose-50 border border-rose-200 rounded-2xl p-5 shadow-sm flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                    </div>
                    <div class="space-y-1 flex-1">
                        <h3 class="text-sm font-bold text-rose-800">Erro detectado no sistema</h3>
                        <p class="text-xs text-rose-600 font-mono bg-rose-100/60 p-2 rounded-lg border border-rose-200 mt-1">
                            <?php echo htmlspecialchars($erroSistema); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="space-y-6">

                <!-- 1. Informações Gerais & Logo -->
                <div class="bg-white p-4 sm:p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base border-b border-slate-100 pb-3">
                        <i data-lucide="store" class="w-5 h-5 text-orange-500"></i> Informações Principais
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="flex flex-col items-center justify-center border-2 border-dashed border-slate-200 rounded-2xl p-4 bg-slate-50 hover:bg-slate-100/50 transition-colors cursor-pointer relative overflow-hidden" onclick="document.getElementById('inputLogoFile').click();">
                            <?php if (!empty($loja['logo'])): ?>
                                <img id="previewLogo" src="<?php echo htmlspecialchars($loja['logo']); ?>" alt="Logo" class="w-20 h-20 rounded-xl object-cover mb-2 shadow-sm">
                            <?php else: ?>
                                <div id="previewLogoPlaceholder" class="w-20 h-20 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-xl mb-2">
                                    <i data-lucide="image" class="w-8 h-8"></i>
                                </div>
                            <?php endif; ?>
                            <span class="text-xs font-semibold text-slate-700">Alterar Logo</span>
                            <span class="text-[10px] text-orange-600 font-semibold mt-1 bg-orange-50 px-2 py-0.5 rounded-full border border-orange-200">Recomendado: 512x512 px</span>
                            <input type="file" id="inputLogoFile" accept="image/*" class="hidden" onchange="iniciarEditorCorte(event)">
                        </div>

                        <div class="md:col-span-2 space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Nome da Loja / Estabelecimento</label>
                                <input type="text" data-campo="nome_loja" value="<?php echo htmlspecialchars($loja['nome_loja'] ?? ''); ?>" oninput="salvarTempoReal(this)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">CNPJ / CPF</label>
                                    <input type="text" data-campo="cnpj" value="<?php echo htmlspecialchars($loja['cnpj'] ?? ''); ?>" oninput="salvarTempoReal(this)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">WhatsApp de Atendimento</label>
                                    <input type="text" data-campo="whatsapp" value="<?php echo htmlspecialchars($loja['whatsapp'] ?? ''); ?>" oninput="salvarTempoReal(this)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Endereço Completo</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="text" id="inputEndereco" data-campo="endereco" value="<?php echo htmlspecialchars($loja['endereco'] ?? ''); ?>" oninput="salvarTempoReal(this)" class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500" placeholder="Ex: Av. Magalhães - Gilberto Mestrinho">
                            <button type="button" onclick="abrirModalMapa()" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors flex items-center justify-center gap-2 shrink-0 shadow-sm cursor-pointer">
                                <i data-lucide="map-pin" class="w-4 h-4"></i>
                                <span>Definir no Mapa</span>
                            </button>
                        </div>
                        <input type="hidden" id="inputLatitude" data-campo="latitude" value="<?php echo htmlspecialchars($loja['latitude'] ?? ''); ?>">
                        <input type="hidden" id="inputLongitude" data-campo="longitude" value="<?php echo htmlspecialchars($loja['longitude'] ?? ''); ?>">
                    </div>
                </div>

                <!-- 2. Status e Horários de Funcionamento (Acordeon) -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <button type="button" onclick="toggleAcordeonHorarios()" class="w-full p-4 sm:p-6 flex items-center justify-between bg-white hover:bg-slate-50 transition-colors text-left focus:outline-none cursor-pointer">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center shrink-0">
                                <i data-lucide="clock" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">Horários de Funcionamento (Dia por Dia)</h3>
                                <p class="text-xs text-slate-500">Configure os horários detalhados de abertura, fechamento e intervalo de almoço.</p>
                            </div>
                        </div>
                        <i id="iconeSetaAcordeon" data-lucide="chevron-down" class="w-5 h-5 text-slate-400 transition-transform duration-200"></i>
                    </button>

                    <div id="conteudoAcordeonHorarios" class="hidden border-t border-slate-100 p-4 sm:p-6 space-y-4 bg-slate-50/50">
                        <div class="flex items-center justify-between p-4 rounded-xl bg-white border border-slate-200 gap-4">
                            <div>
                                <span class="font-semibold text-slate-800 text-sm block">Forçar Loja Fechada Manualmente</span>
                                <span class="text-xs text-slate-500">Se ativado, a loja fica fechada independentemente da programação horária.</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" data-campo="forcar_fechado" value="1" <?php echo (!empty($loja['forcar_fechado']) && intval($loja['forcar_fechado']) === 1) ? 'checked' : ''; ?> onchange="salvarCheckboxTempoReal(this)" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"></div>
                            </label>
                        </div>

                        <div class="space-y-3">
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
                            foreach ($diasSemanaNomes as $chaveDia => $nomeDia):
                                $infoDia = $horariosDias[$chaveDia] ?? ['ativo' => 1, 'abertura' => '08:00', 'fechamento' => '18:00', 'tem_almoco' => 0, 'almoco_inicio' => '12:00', 'almoco_fim' => '13:00'];
                                $temAlmocoChecked = !empty($infoDia['tem_almoco']) ? 'checked' : '';
                            ?>
                            <div class="p-4 rounded-xl border border-slate-200 bg-white space-y-3 shadow-xs" data-dia-config="<?php echo $chaveDia; ?>">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                    <span class="font-bold text-slate-800 text-sm"><?php echo $nomeDia; ?></span>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" class="w-4 h-4 text-orange-500 rounded border-slate-300 focus:ring-orange-500 dia-ativo-check" <?php echo (!empty($infoDia['ativo'])) ? 'checked' : ''; ?> onchange="salvarHorariosFuncionamento()">
                                        <span class="text-xs font-semibold text-slate-600">Aberto neste dia</span>
                                    </label>
                                </div>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Horário de Abertura</label>
                                        <input type="time" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-orange-500 dia-abertura" value="<?php echo htmlspecialchars($infoDia['abertura'] ?? '08:00'); ?>" onchange="salvarHorariosFuncionamento()">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Horário de Fechamento</label>
                                        <input type="time" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-orange-500 dia-fechamento" value="<?php echo htmlspecialchars($infoDia['fechamento'] ?? '18:00'); ?>" onchange="salvarHorariosFuncionamento()">
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-100">
                                    <label class="inline-flex items-center gap-2 cursor-pointer mb-2">
                                        <input type="checkbox" class="w-3.5 h-3.5 text-orange-500 rounded border-slate-300 focus:ring-orange-500 dia-tem-almoco-check" <?php echo $temAlmocoChecked; ?> onchange="toggleCamposAlmoco(this); salvarHorariosFuncionamento();">
                                        <span class="text-xs font-medium text-slate-700">Ativar intervalo de almoço/pausa neste dia</span>
                                    </label>

                                    <div class="grid grid-cols-2 gap-3 container-almoco-campos <?php echo empty($infoDia['tem_almoco']) ? 'hidden' : ''; ?>">
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Início Almoço</label>
                                            <input type="time" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-orange-500 dia-almoco-ini" value="<?php echo htmlspecialchars($infoDia['almoco_inicio'] ?? '12:00'); ?>" onchange="salvarHorariosFuncionamento()">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Fim Almoço</label>
                                            <input type="time" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-orange-500 dia-almoco-fim" value="<?php echo htmlspecialchars($infoDia['almoco_fim'] ?? '13:00'); ?>" onchange="salvarHorariosFuncionamento()">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 3. Integração Chave Pix Própria -->
                <div class="bg-white p-4 sm:p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base border-b border-slate-100 pb-3">
                        <i data-lucide="qr-code" class="w-5 h-5 text-orange-500"></i> Chave Pix de Recebimento
                    </h3>
                    <p class="text-xs text-slate-500">Informe os dados da sua chave Pix para que os clientes realizem o pagamento diretamente no delivery.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Tipo de Chave Pix</label>
                            <select data-campo="tipo_chave_pix" onchange="salvarTempoReal(this)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                                <?php $tPix = $loja['tipo_chave_pix'] ?? 'CNPJ'; ?>
                                <option value="CNPJ" <?php echo ($tPix === 'CNPJ') ? 'selected' : ''; ?>>CNPJ</option>
                                <option value="CPF" <?php echo ($tPix === 'CPF') ? 'selected' : ''; ?>>CPF</option>
                                <option value="EMAIL" <?php echo ($tPix === 'EMAIL') ? 'selected' : ''; ?>>E-mail</option>
                                <option value="TELEFONE" <?php echo ($tPix === 'TELEFONE') ? 'selected' : ''; ?>>Telefone</option>
                                <option value="ALEATORIA" <?php echo ($tPix === 'ALEATORIA') ? 'selected' : ''; ?>>Chave Aleatória</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Chave Pix</label>
                            <input type="text" data-campo="chave_pix" value="<?php echo htmlspecialchars($loja['chave_pix'] ?? ''); ?>" oninput="salvarTempoReal(this)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500" placeholder="Insira sua chave Pix aqui">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Payload / Código Pix Copia e Cola Padrão</label>
                        <textarea data-campo="pix_copia_cola" rows="3" oninput="salvarTempoReal(this)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500 font-mono text-xs" placeholder="00020126580014br.gov.bcb.pix..."><?php echo htmlspecialchars($loja['pix_copia_cola'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- 4. Formas de Pagamento para Delivery -->
                <div class="bg-white p-4 sm:p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base border-b border-slate-100 pb-3">
                        <i data-lucide="credit-card" class="w-5 h-5 text-orange-500"></i> Formas de Pagamento Aceitas
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex items-center justify-between p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">💵</div>
                                <div>
                                    <span class="font-semibold text-slate-800 text-sm block">Dinheiro (Troco)</span>
                                    <span class="text-xs text-slate-400">Levar troco na entrega</span>
                                </div>
                            </div>
                            <input type="checkbox" data-campo="pagamento_dinheiro" value="1" <?php echo (!empty($loja['pagamento_dinheiro']) && $loja['pagamento_dinheiro'] == 1) ? 'checked' : ''; ?> onchange="salvarCheckboxTempoReal(this)" class="w-4 h-4 text-orange-500 rounded border-slate-300 focus:ring-orange-500">
                        </div>

                        <div class="flex items-center justify-between p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold">💳</div>
                                <div>
                                    <span class="font-semibold text-slate-800 text-sm block">Cartão de Débito</span>
                                    <span class="text-xs text-slate-400">Maquininha na entrega</span>
                                </div>
                            </div>
                            <input type="checkbox" data-campo="pagamento_debito" value="1" <?php echo (!empty($loja['pagamento_debito']) && $loja['pagamento_debito'] == 1) ? 'checked' : ''; ?> onchange="salvarCheckboxTempoReal(this)" class="w-4 h-4 text-orange-500 rounded border-slate-300 focus:ring-orange-500">
                        </div>

                        <div class="flex items-center justify-between p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center font-bold">💳</div>
                                <div>
                                    <span class="font-semibold text-slate-800 text-sm block">Cartão de Crédito</span>
                                    <span class="text-xs text-slate-400">Maquininha na entrega</span>
                                </div>
                            </div>
                            <input type="checkbox" data-campo="pagamento_credito" value="1" <?php echo (!empty($loja['pagamento_credito']) && $loja['pagamento_credito'] == 1) ? 'checked' : ''; ?> onchange="salvarCheckboxTempoReal(this)" class="w-4 h-4 text-orange-500 rounded border-slate-300 focus:ring-orange-500">
                        </div>

                        <div class="flex items-center justify-between p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center font-bold">💠</div>
                                <div>
                                    <span class="font-semibold text-slate-800 text-sm block">Pix Manual / Chave</span>
                                    <span class="text-xs text-slate-400">Pagamento via Chave Pix</span>
                                </div>
                            </div>
                            <input type="checkbox" data-campo="pagamento_pix" value="1" <?php echo (!empty($loja['pagamento_pix']) && $loja['pagamento_pix'] == 1) ? 'checked' : ''; ?> onchange="salvarCheckboxTempoReal(this)" class="w-4 h-4 text-orange-500 rounded border-slate-300 focus:ring-orange-500">
                        </div>
                    </div>
                </div>

                <!-- 5. Logs do Banco de Dados em Tempo Real (Resumidos) -->
                <div class="bg-white p-4 sm:p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base">
                            <i data-lucide="terminal" class="w-5 h-5 text-orange-500"></i> Logs do Sistema (Loja Atual)
                        </h3>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="carregarLogsBanco()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer">
                                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Atualizar
                            </button>
                            <button type="button" onclick="abrirModalConfirmarLimpezaLogs()" class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Limpar Logs
                            </button>
                        </div>
                    </div>

                    <div id="containerLogs" class="bg-slate-900 rounded-xl p-4 font-mono text-[11px] text-slate-300 max-h-60 overflow-y-auto space-y-1.5 shadow-inner">
                        <?php if (!empty($logsSistema)): ?>
                            <?php foreach ($logsSistema as $log): ?>
                                <div class="flex items-center gap-2 border-b border-slate-800/60 pb-1">
                                    <span class="text-slate-500 shrink-0">[<?php echo htmlspecialchars($log['criado_em']); ?>]</span>
                                    <span class="font-bold text-emerald-400 shrink-0">[<?php echo htmlspecialchars($log['nivel']); ?>]</span>
                                    <span class="text-slate-300 truncate"><?php echo htmlspecialchars($log['mensagem']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-slate-500 text-center py-4">Nenhum registro de log recente para esta loja.</div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- MODAL CONFIRMAR LIMPEZA DE LOGS -->
    <div id="modalLimparLogs" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full p-5 sm:p-6 shadow-2xl border border-slate-100 flex flex-col space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Limpar logs desta loja?</h3>
                    <p class="text-xs text-slate-500">Esta ação apagará o histórico de logs exclusivamente para o ID desta loja.</p>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="fecharModalLimpezaLogs()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 cursor-pointer">Cancelar</button>
                <button type="button" onclick="executarLimpezaLogsLoja()" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold px-4 py-2 rounded-xl text-xs shadow-sm cursor-pointer">Sim, limpar</button>
            </div>
        </div>
    </div>

    <!-- MODAL MAPA -->
    <div id="modalMapaLoja" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full p-5 sm:p-6 shadow-2xl border border-slate-100 flex flex-col space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-5 h-5 text-orange-500"></i>
                    <h3 class="font-bold text-slate-800 text-base">Definir Localização Exata da Loja</h3>
                </div>
                <button type="button" onclick="fecharModalMapa()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="space-y-2">
                <label class="block text-xs font-semibold text-slate-500 uppercase">Buscar endereço:</label>
                <div class="flex gap-2">
                    <input type="text" id="inputBuscaEndereco" placeholder="Ex: Av. Magalhães, Manaus" class="flex-1 px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                    <button type="button" onclick="pesquisarEnderecoMapa()" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-semibold cursor-pointer">Buscar</button>
                </div>
            </div>

            <div id="mapaSelecao" class="mapa-container border border-slate-200 shadow-inner"></div>

            <div class="flex flex-col sm:flex-row items-center justify-between pt-2 gap-3">
                <span id="coordsSelecionadas" class="text-xs text-slate-500 font-mono">Nenhum ponto selecionado.</span>
                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button type="button" onclick="fecharModalMapa()" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 cursor-pointer">Cancelar</button>
                    <button type="button" onclick="salvarLocalizacaoMapa()" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-2.5 rounded-xl text-sm shadow-sm cursor-pointer">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CROPPER -->
    <div id="modalCropper" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-5 sm:p-6 shadow-2xl border border-slate-100 flex flex-col space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-bold text-slate-800 text-base">Ajustar Proporção da Logo (1:1)</h3>
                <button type="button" onclick="fecharModalCropper()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="w-full h-72 bg-slate-900 rounded-xl overflow-hidden flex items-center justify-center">
                <img id="imageToCrop" src="" class="max-w-full">
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="fecharModalCropper()" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 cursor-pointer">Cancelar</button>
                <button type="button" onclick="salvarLogoCortada()" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-2.5 rounded-xl text-sm shadow-sm cursor-pointer">Recortar e Salvar</button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        lucide.createIcons();

        // Remover Skeleton Loader após carregamento completo
        window.addEventListener('load', () => {
            const skeleton = document.getElementById('pageSkeleton');
            if (skeleton) {
                setTimeout(() => {
                    skeleton.classList.add('opacity-0', 'pointer-events-none');
                    setTimeout(() => skeleton.remove(), 400);
                }, 200);
            }
        });

        // Controle Unificado do Sidebar (Abrir / Fechar Mobile)
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const openBtn = document.getElementById('openSidebarBtn');
            const closeBtn = document.getElementById('closeSidebarBtn');

            function abrirMenu() {
                if (sidebar) sidebar.classList.remove('-translate-x-full');
                if (overlay) overlay.classList.remove('hidden');
            }

            function fecharMenu() {
                if (sidebar) sidebar.classList.add('-translate-x-full');
                if (overlay) overlay.classList.add('hidden');
            }

            if (openBtn) openBtn.addEventListener('click', abrirMenu);
            if (closeBtn) closeBtn.addEventListener('click', fecharMenu);
            if (overlay) overlay.addEventListener('click', fecharMenu);
        });

        let cropper = null;
        let mapaLoja = null;
        let marcadorLoja = null;
        let latTemp = <?php echo !empty($loja['latitude']) ? floatval($loja['latitude']) : -3.119034; ?>;
        let lngTemp = <?php echo !empty($loja['longitude']) ? floatval($loja['longitude']) : -60.021731; ?>;

        function toggleAcordeonHorarios() {
            const conteudo = document.getElementById('conteudoAcordeonHorarios');
            const seta = document.getElementById('iconeSetaAcordeon');
            conteudo.classList.toggle('hidden');
            seta.classList.toggle('rotate-180');
        }

        function toggleCamposAlmoco(checkbox) {
            const containerAlmoco = checkbox.closest('.space-y-3').querySelector('.container-almoco-campos');
            if (checkbox.checked) {
                containerAlmoco.classList.remove('hidden');
            } else {
                containerAlmoco.classList.add('hidden');
            }
        }

        let saveTimeout = null;
        function mostrarIndicadorSalvo() {
            const indicator = document.getElementById('saveIndicator');
            if (indicator) {
                if (saveTimeout) clearTimeout(saveTimeout);

                indicator.classList.remove('opacity-0', '-translate-y-12', 'pointer-events-none');
                indicator.classList.add('opacity-100', 'translate-y-0');

                saveTimeout = setTimeout(() => {
                    indicator.classList.remove('opacity-100', 'translate-y-0');
                    indicator.classList.add('opacity-0', '-translate-y-12', 'pointer-events-none');
                }, 2000);
            }
            carregarLogsBanco();
        }

        function salvarTempoReal(elemento) {
            const campo = elemento.getAttribute('data-campo');
            const valor = elemento.value;

            clearTimeout(elemento.dataset.timeoutId);
            elemento.dataset.timeoutId = setTimeout(() => {
                executarEnvioAjax(campo, valor);
            }, 300);
        }

        function salvarCheckboxTempoReal(elemento) {
            const campo = elemento.getAttribute('data-campo');
            const valor = elemento.checked ? '1' : '0';
            
            elemento.disabled = true; 
            executarEnvioAjax(campo, valor, () => {
                elemento.disabled = false;
            });
        }

        function salvarHorariosFuncionamento() {
            let horariosObj = {};
            document.querySelectorAll('[data-dia-config]').forEach(bloco => {
                const dia = bloco.getAttribute('data-dia-config');
                const temAlmocoCheck = bloco.querySelector('.dia-tem-almoco-check');
                horariosObj[dia] = {
                    ativo: bloco.querySelector('.dia-ativo-check').checked ? 1 : 0,
                    abertura: bloco.querySelector('.dia-abertura').value,
                    fechamento: bloco.querySelector('.dia-fechamento').value,
                    tem_almoco: temAlmocoCheck.checked ? 1 : 0,
                    almoco_inicio: bloco.querySelector('.dia-almoco-ini').value,
                    almoco_fim: bloco.querySelector('.dia-almoco-fim').value
                };
            });

            clearTimeout(window.timeoutHorarios);
            window.timeoutHorarios = setTimeout(() => {
                executarEnvioAjax('horarios_funcionamento', JSON.stringify(horariosObj));
            }, 400);
        }

        function executarEnvioAjax(campo, valor, callbackFim = null) {
            let formData = new URLSearchParams();
            formData.append('ajax_salvar', '1');
            formData.append('campo', campo);
            formData.append('valor', valor);

            fetch('config_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    mostrarIndicadorSalvo();
                } else {
                    console.error('Erro ao salvar campo ' + campo + ':', data.mensagem);
                }
                if (callbackFim) callbackFim();
            })
            .catch(err => {
                console.error('Erro na requisição:', err);
                if (callbackFim) callbackFim();
            });
        }

        function carregarLogsBanco() {
            fetch('config_api.php?acao=obter_logs')
            .then(res => res.json())
            .then(data => {
                if (data.sucesso && data.logs) {
                    const container = document.getElementById('containerLogs');
                    let html = '';
                    if (data.logs.length === 0) {
                        html = '<div class="text-slate-500 text-center py-4">Nenhum registro de log recente para esta loja.</div>';
                    } else {
                        data.logs.forEach(log => {
                            html += `
                                <div class="flex items-center gap-2 border-b border-slate-800/60 pb-1">
                                    <span class="text-slate-500 shrink-0">[${log.criado_em}]</span>
                                    <span class="font-bold text-emerald-400 shrink-0">[${log.nivel}]</span>
                                    <span class="text-slate-300 truncate">${log.mensagem}</span>
                                </div>
                            `;
                        });
                    }
                    container.innerHTML = html;
                }
            })
            .catch(err => console.error('Erro ao carregar logs:', err));
        }

        function abrirModalConfirmarLimpezaLogs() {
            document.getElementById('modalLimparLogs').classList.remove('hidden');
        }

        function fecharModalLimpezaLogs() {
            document.getElementById('modalLimparLogs').classList.add('hidden');
        }

        function executarLimpezaLogsLoja() {
            let formData = new URLSearchParams();
            formData.append('acao', 'limpar_logs');

            fetch('config_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                fecharModalLimpezaLogs();
                if (data.sucesso) {
                    carregarLogsBanco();
                    mostrarIndicadorSalvo();
                } else {
                    console.error('Erro ao limpar logs');
                }
            })
            .catch(err => console.error('Erro:', err));
        }

        setInterval(carregarLogsBanco, 4000);

        function abrirModalMapa() {
            document.getElementById('modalMapaLoja').classList.remove('hidden');
            setTimeout(() => {
                if (!mapaLoja) {
                    mapaLoja = L.map('mapaSelecao').setView([latTemp, lngTemp], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(mapaLoja);
                    marcadorLoja = L.marker([latTemp, lngTemp], { draggable: true }).addTo(mapaLoja);

                    marcadorLoja.on('dragend', function () {
                        const pos = marcadorLoja.getLatLng();
                        latTemp = pos.lat; lngTemp = pos.lng;
                        atualizarTextoCoords(latTemp, lngTemp);
                        buscarNomeEnderecoPorCoordenadas(latTemp, lngTemp);
                    });

                    mapaLoja.on('click', function (e) {
                        latTemp = e.latlng.lat; lngTemp = e.latlng.lng;
                        marcadorLoja.setLatLng([latTemp, lngTemp]);
                        atualizarTextoCoords(latTemp, lngTemp);
                        buscarNomeEnderecoPorCoordenadas(latTemp, lngTemp);
                    });
                } else {
                    mapaLoja.invalidateSize();
                    mapaLoja.setView([latTemp, lngTemp], 15);
                    marcadorLoja.setLatLng([latTemp, lngTemp]);
                }
                atualizarTextoCoords(latTemp, lngTemp);
            }, 200);
        }

        function fecharModalMapa() { document.getElementById('modalMapaLoja').classList.add('hidden'); }
        function atualizarTextoCoords(lat, lng) { document.getElementById('coordsSelecionadas').innerText = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`; }

        function pesquisarEnderecoMapa() {
            const termo = document.getElementById('inputBuscaEndereco').value;
            if (!termo) return;
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(termo)}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.length > 0) {
                        latTemp = parseFloat(data[0].lat); lngTemp = parseFloat(data[0].lon);
                        mapaLoja.setView([latTemp, lngTemp], 16);
                        marcadorLoja.setLatLng([latTemp, lngTemp]);
                        atualizarTextoCoords(latTemp, lngTemp);
                        document.getElementById('inputEndereco').value = data[0].display_name;
                    }
                });
        }

        function buscarNomeEnderecoPorCoordenadas(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.display_name) {
                        document.getElementById('inputEndereco').value = data.display_name;
                    }
                });
        }

        function salvarLocalizacaoMapa() {
            document.getElementById('inputLatitude').value = latTemp;
            document.getElementById('inputLongitude').value = lngTemp;
            Promise.all([
                executarEnvioAjax('latitude', latTemp),
                executarEnvioAjax('longitude', lngTemp),
                executarEnvioAjax('endereco', document.getElementById('inputEndereco').value)
            ]).then(() => {
                fecharModalMapa();
                mostrarIndicadorSalvo();
            });
        }

        function iniciarEditorCorte(event) {
            const files = event.target.files;
            if (files && files.length > 0) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const image = document.getElementById('imageToCrop');
                    image.src = e.target.result;
                    document.getElementById('modalCropper').classList.remove('hidden');
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(image, { aspectRatio: 1 / 1, viewMode: 1, autoCropArea: 1 });
                };
                reader.readAsDataURL(files[0]);
            }
        }

        function fecharModalCropper() {
            document.getElementById('modalCropper').classList.add('hidden');
            if (cropper) { cropper.destroy(); cropper = null; }
            document.getElementById('inputLogoFile').value = '';
        }

        function salvarLogoCortada() {
            if (cropper) {
                const canvas = cropper.getCroppedCanvas({ width: 512, height: 512 });
                let formData = new URLSearchParams();
                formData.append('ajax_upload_logo', '1');
                formData.append('cropped_image', canvas.toDataURL('image/webp'));

                fetch('config_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                })
                .then(res => res.json())
                .then(data => {
                    if (data.sucesso) {
                        fecharModalCropper();
                        const placeholder = document.getElementById('previewLogoPlaceholder');
                        if (placeholder) {
                            placeholder.outerHTML = `<img id="previewLogo" src="${data.url}?t=${Date.now()}" alt="Logo" class="w-20 h-20 rounded-xl object-cover mb-2 shadow-sm">`;
                        } else {
                            document.getElementById('previewLogo').src = `${data.url}?t=${Date.now()}`;
                        }
                        mostrarIndicadorSalvo();
                    }
                });
            }
        }
    </script>
</body>
</html>