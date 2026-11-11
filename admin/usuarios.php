<?php
// admin/usuarios.php

// Ativa exibição de erros para debug e monitoramento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Capturador amigável de erros do PHP para exibição no card em PT-BR
$errosSistemaPHP = [];
set_error_handler(function($severity, $message, $file, $line) use (&$errosSistemaPHP) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    $tipoNome = "Erro Desconhecido";
    switch ($severity) {
        case E_ERROR:             $tipoNome = "Erro Fatal"; break;
        case E_WARNING:           $tipoNome = "Aviso / Alerta"; break;
        case E_PARSE:             $tipoNome = "Erro de Sintaxe (Parse)"; break;
        case E_NOTICE:            $tipoNome = "Notificação (Notice)"; break;
        case E_CORE_ERROR:        $tipoNome = "Erro de Núcleo (Core)"; break;
        case E_USER_ERROR:        $tipoNome = "Erro do Usuário"; break;
        case E_USER_WARNING:      $tipoNome = "Aviso do Usuário"; break;
        case E_USER_NOTICE:       $tipoNome = "Notificação do Usuário"; break;
        case E_STRICT:            $tipoNome = "Aviso de Boas Práticas"; break;
        case E_RECOVERABLE_ERROR: $tipoNome = "Erro Recuperável"; break;
        case E_DEPRECATED:        $tipoNome = "Recurso Obsoleto"; break;
    }
    $errosSistemaPHP[] = [
        'tipo' => $tipoNome,
        'mensagem' => $message,
        'arquivo' => basename($file),
        'linha' => $line
    ];
    return true;
});

session_start();
require_once 'auth_master.php';
require_once 'config.php';

$mensagem = '';
$erro = '';

// ==========================================
// PROCESSAMENTO DAS AÇÕES (CRUD)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. CADASTRAR
    if ($acao === 'cadastrar') {
        $nome    = trim($_POST['nome'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $senha   = $_POST['senha'] ?? '';
        $tipo    = $_POST['tipo'] ?? 'loja';
        $loja_id = ($tipo === 'master') ? 0 : (int) ($_POST['loja_id'] ?? 0);

        if (!empty($nome) && !empty($email) && !empty($senha)) {
            try {
                // Verificar se o e-mail já existe no sistema
                $stmtCheck = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->fetch()) {
                    $erro = 'Este e-mail já está cadastrado no sistema.';
                } else {
                    // Se for lojista, validar o limite de 5 usuários por loja
                    if ($tipo === 'loja' && $loja_id > 0) {
                        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE loja_id = ?");
                        $stmtCount->execute([$loja_id]);
                        $totalLoja = $stmtCount->fetchColumn();

                        if ($totalLoja >= 5) {
                            throw new Exception("Esta loja já atingiu o limite máximo de 5 usuários vinculados.");
                        }
                    }

                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmtInsert = $pdo->prepare("INSERT INTO admins (loja_id, tipo, nome, email, senha, criado_em) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmtInsert->execute([$loja_id, $tipo, $nome, $email, $senhaHash]);
                    $mensagem = 'Usuário cadastrado com sucesso!';
                }
            } catch (Exception $e) {
                $erro = 'Erro ao cadastrar usuário: ' . $e->getMessage();
            }
        } else {
            $erro = 'Preencha todos os campos obrigatórios para o cadastro.';
        }
    }

    // 2. EDITAR
    if ($acao === 'editar') {
        $id      = (int) ($_POST['id'] ?? 0);
        $nome    = trim($_POST['nome'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $senha   = trim($_POST['senha'] ?? '');
        $tipo    = $_POST['tipo'] ?? 'loja';
        $loja_id = ($tipo === 'master') ? 0 : (int) ($_POST['loja_id'] ?? 0);

        if ($id > 0 && !empty($nome) && !empty($email)) {
            try {
                $stmtCheck = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1");
                $stmtCheck->execute([$email, $id]);
                if ($stmtCheck->fetch()) {
                    $erro = 'Este e-mail já está sendo utilizado por outro usuário.';
                } else {
                    if ($tipo === 'loja' && $loja_id > 0) {
                        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE loja_id = ? AND id != ?");
                        $stmtCount->execute([$loja_id, $id]);
                        $totalLoja = $stmtCount->fetchColumn();

                        if ($totalLoja >= 5) {
                            throw new Exception("Esta loja já atingiu o limite máximo de 5 usuários vinculados.");
                        }
                    }

                    if (!empty($senha)) {
                        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                        $stmtUpdate = $pdo->prepare("UPDATE admins SET nome = ?, email = ?, senha = ?, tipo = ?, loja_id = ? WHERE id = ?");
                        $stmtUpdate->execute([$nome, $email, $senhaHash, $tipo, $loja_id, $id]);
                    } else {
                        $stmtUpdate = $pdo->prepare("UPDATE admins SET nome = ?, email = ?, tipo = ?, loja_id = ? WHERE id = ?");
                        $stmtUpdate->execute([$nome, $email, $tipo, $loja_id, $id]);
                    }
                    $mensagem = 'Usuário atualizado com sucesso!';
                }
            } catch (Exception $e) {
                $erro = 'Erro ao atualizar: ' . $e->getMessage();
            }
        } else {
            $erro = 'Preencha os campos obrigatórios para a edição.';
        }
    }

    // 3. EXCLUIR
    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                $stmtDel->execute([$id]);
                $mensagem = 'Usuário excluído com sucesso!';
            } catch (Exception $e) {
                $erro = 'Erro ao excluir usuário: ' . $e->getMessage();
            }
        }
    }
}

// Buscar lojas ativas para os selects de vínculo
$lojas = [];
try {
    $stmtLojas = $pdo->query("SELECT id, nome_loja FROM lojas ORDER BY nome_loja ASC");
    $lojas = $stmtLojas->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Buscar administradores e lojistas cadastrados integrados com o nome da respectiva loja
$admins = [];
try {
    $stmtAdmins = $pdo->query("
        SELECT a.*, l.nome_loja 
        FROM admins a 
        LEFT JOIN lojas l ON a.loja_id = l.id 
        ORDER BY a.id DESC
    ");
    $admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $erro = 'Erro ao carregar usuários: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Usuários | Painel Master</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }

        /* Evita o zoom automático do iPhone (Safari iOS) em inputs, selects e textareas menores que 16px */
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
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Menu Superior Compartilhado Master -->
    <?php include 'menu.php'; ?>

    <!-- Conteúdo Principal -->
    <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">

        <!-- Cabeçalho e Botão Novo -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-800">Gerenciamento de Usuários</h2>
                <p class="text-xs text-slate-400 mt-0.5">Cadastre até 5 usuários por loja ou configure acessos Master globais na plataforma.</p>
            </div>
            <button onclick="abrirModalCadastrar()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2 transition-colors shadow-sm">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Novo Usuário
            </button>
        </div>

        <!-- CARD ORGANIZADO DE ERROS PHP / DEBUG -->
        <?php if (!empty($errosSistemaPHP)): ?>
            <div class="bg-rose-50 border border-rose-200 rounded-2xl p-5 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-rose-200 pb-3">
                    <div class="flex items-center gap-2 text-rose-800 font-bold text-xs uppercase tracking-wider">
                        <i data-lucide="bug" class="w-4 h-4"></i>
                        <span>Depurador de Erros PHP (<?php echo count($errosSistemaPHP); ?>)</span>
                    </div>
                    <span class="text-[11px] text-rose-600 font-medium">Monitoramento Ativo</span>
                </div>
                <div class="space-y-2">
                    <?php foreach ($errosSistemaPHP as $errPhp): ?>
                        <div class="bg-white border border-rose-100 rounded-xl p-3 shadow-sm text-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <span class="bg-rose-100 text-rose-800 font-bold px-2 py-0.5 rounded text-[10px] mr-2">
                                    <?php echo htmlspecialchars($errPhp['tipo']); ?>
                                </span>
                                <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($errPhp['mensagem']); ?></span>
                            </div>
                            <div class="text-[11px] text-slate-400 font-mono">
                                Arquivo: <strong class="text-slate-600"><?php echo htmlspecialchars($errPhp['arquivo']); ?></strong> (Linha: <strong class="text-slate-600"><?php echo $errPhp['linha']; ?></strong>)
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- CARD DE SUCESSO -->
        <?php if (!empty($mensagem)): ?>
            <div id="cardSucesso" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-start gap-3 shadow-sm transition-opacity duration-500">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-emerald-800 text-sm">Sucesso!</h3>
                    <p class="text-xs text-emerald-700 mt-0.5"><?php echo htmlspecialchars($mensagem); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- CARD DE ERRO DE NEGÓCIO -->
        <?php if (!empty($erro)): ?>
            <div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 flex items-start gap-3 shadow-sm">
                <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-rose-800 text-sm">Atenção / Erro Detectado</h3>
                    <p class="text-xs text-rose-700 mt-0.5 break-words font-mono bg-rose-100/50 p-2 rounded-lg border border-rose-200/50">
                        <?php echo htmlspecialchars($erro); ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tabela de Usuários -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 font-semibold text-xs uppercase tracking-wider">
                            <th class="py-3 px-6">ID</th>
                            <th class="py-3 px-6">Nome / E-mail</th>
                            <th class="py-3 px-6">Tipo</th>
                            <th class="py-3 px-6">Loja Vinculada</th>
                            <th class="py-3 px-6 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-600">
                        <?php if (count($admins) > 0): ?>
                            <?php foreach ($admins as $adm): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="py-4 px-6 font-bold text-slate-800">#<?php echo $adm['id']; ?></td>
                                    <td class="py-4 px-6">
                                        <span class="font-semibold text-slate-800 block"><?php echo htmlspecialchars($adm['nome']); ?></span>
                                        <span class="text-xs text-slate-400"><?php echo htmlspecialchars($adm['email']); ?></span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php if (($adm['tipo'] ?? '') === 'master'): ?>
                                            <span class="bg-purple-50 text-purple-700 border border-purple-200 px-2.5 py-1 rounded-full text-xs font-semibold">Master</span>
                                        <?php else: ?>
                                            <span class="bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-full text-xs font-semibold">Lojista</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 font-medium text-slate-700">
                                        <?php echo (($adm['tipo'] ?? '') === 'master') ? '<em class="text-slate-400">Plataforma Global</em>' : htmlspecialchars($adm['nome_loja'] ?? 'Nenhuma'); ?>
                                    </td>
                                    <td class="py-4 px-6 text-right space-x-2">
                                        <button onclick='abrirModalEditar(<?php echo json_encode($adm); ?>)' class="p-1.5 bg-amber-50 text-amber-600 hover:bg-amber-100 rounded-xl transition-colors inline-block" title="Editar Usuário">
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick='abrirModalExcluir(<?php echo $adm['id']; ?>, "<?php echo addslashes($adm['nome']); ?>")' class="p-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-xl transition-colors inline-block" title="Excluir Usuário">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400 text-sm">Nenhum usuário cadastrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- ========================================== -->
    <!-- MODAL: CADASTRAR / EDITAR -->
    <!-- ========================================== -->
    <div id="modalUsuario" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-100 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 id="modalTitulo" class="font-bold text-slate-800 text-base">Novo Usuário</h3>
                <button onclick="fecharModal('modalUsuario')" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form action="usuarios.php" method="POST" class="space-y-4">
                <input type="hidden" name="acao" id="formAcao" value="cadastrar">
                <input type="hidden" name="id" id="formId" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Nome Completo</label>
                    <input type="text" name="nome" id="formNome" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">E-mail</label>
                    <input type="email" name="email" id="formEmail" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Senha <span id="labelSenhaInfo" class="text-[10px] text-slate-400 font-normal"></span></label>
                    <input type="password" name="senha" id="formSenha" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 text-sm" placeholder="Mínimo de caracteres">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Tipo de Usuário</label>
                    <select name="tipo" id="formTipo" onchange="alternarBlocoLoja()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 text-sm bg-white">
                        <option value="loja">Lojista (Vinculado à Loja)</option>
                        <option value="master">Master (Administrador Geral)</option>
                    </select>
                </div>

                <div id="formBlocoLoja">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Vincular à Loja (Máx 5 por loja)</label>
                    <select name="loja_id" id="formLojaId" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 text-sm bg-white">
                        <option value="">Selecione a Loja</option>
                        <?php foreach ($lojas as $l): ?>
                            <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['nome_loja']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="fecharModal('modalUsuario')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50">Cancelar</button>
                    <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-5 py-2 rounded-xl text-xs font-semibold shadow-sm">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL: CONFIRMAR EXCLUSÃO -->
    <!-- ========================================== -->
    <div id="modalExcluir" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-xl border border-slate-100 space-y-4 text-center">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center mx-auto">
                <i data-lucide="alert-triangle" class="w-6 h-6"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 text-base">Excluir Usuário</h3>
                <p class="text-xs text-slate-500 mt-1">Tem certeza que deseja excluir o acesso de <strong id="nomeExcluir" class="text-slate-800"></strong>? Esta ação não pode ser desfeita.</p>
            </div>

            <form action="usuarios.php" method="POST" class="flex items-center justify-center gap-3 pt-2">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" id="idExcluir">
                <button type="button" onclick="fecharModal('modalExcluir')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-2 rounded-xl text-xs font-semibold shadow-sm">Sim, Excluir</button>
            </form>
        </div>
    </div>

    <!-- SCRIPTS DE CONTROLE DOS MODAIS -->
    <script>
        lucide.createIcons();

        // Faz o card de sucesso sumir suavemente após 3 segundos (3000ms)
        window.addEventListener('DOMContentLoaded', () => {
            const cardSucesso = document.getElementById('cardSucesso');
            if (cardSucesso) {
                setTimeout(() => {
                    cardSucesso.style.opacity = '0';
                    setTimeout(() => {
                        cardSucesso.remove();
                    }, 500); // Remove do DOM após o fade-out de 0.5s
                }, 3000);
            }
        });

        function abrirModalCadastrar() {
            document.getElementById('modalTitulo').innerText = 'Cadastrar Novo Usuário';
            document.getElementById('formAcao').value = 'cadastrar';
            document.getElementById('formId').value = '';
            document.getElementById('formNome').value = '';
            document.getElementById('formEmail').value = '';
            document.getElementById('formSenha').value = '';
            document.getElementById('formSenha').required = true;
            document.getElementById('labelSenhaInfo').innerText = '';
            document.getElementById('formTipo').value = 'loja';
            document.getElementById('formLojaId').value = '';
            alternarBlocoLoja();

            document.getElementById('modalUsuario').classList.remove('hidden');
            document.getElementById('modalUsuario').classList.add('flex');
        }

        function abrirModalEditar(adm) {
            document.getElementById('modalTitulo').innerText = 'Editar Usuário';
            document.getElementById('formAcao').value = 'editar';
            document.getElementById('formId').value = adm.id;
            document.getElementById('formNome').value = adm.nome;
            document.getElementById('formEmail').value = adm.email;
            document.getElementById('formSenha').value = '';
            document.getElementById('formSenha').required = false;
            document.getElementById('labelSenhaInfo').innerText = '(Deixe em branco para não alterar)';
            document.getElementById('formTipo').value = adm.tipo || 'loja';
            document.getElementById('formLojaId').value = adm.loja_id || '';
            alternarBlocoLoja();

            document.getElementById('modalUsuario').classList.remove('hidden');
            document.getElementById('modalUsuario').classList.add('flex');
        }

        function abrirModalExcluir(id, nome) {
            document.getElementById('idExcluir').value = id;
            document.getElementById('nomeExcluir').innerText = nome;
            document.getElementById('modalExcluir').classList.remove('hidden');
            document.getElementById('modalExcluir').classList.add('flex');
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('flex');
            document.getElementById(modalId).classList.add('hidden');
        }

        function alternarBlocoLoja() {
            const tipo = document.getElementById('formTipo').value;
            const bloco = document.getElementById('formBlocoLoja');
            if (tipo === 'master') {
                bloco.style.display = 'none';
            } else {
                bloco.style.display = 'block';
            }
        }
    </script>
</body>
</html>