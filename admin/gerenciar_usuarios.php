<?php
// admin/gerenciar_usuarios.php

// Ativa exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
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
                $stmtCheck = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->fetch()) {
                    $erro = 'Este e-mail já está cadastrado no sistema.';
                } else {
                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmtInsert = $pdo->prepare("INSERT INTO admins (loja_id, tipo, nome, email, senha, criado_em) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmtInsert->execute([$loja_id, $tipo, $nome, $email, $senhaHash]);
                    $mensagem = 'Usuário cadastrado com sucesso!';
                }
            } catch (Exception $e) {
                $erro = 'Erro no banco de dados: ' . $e->getMessage();
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
                // Verifica se o e-mail já pertence a outro usuário
                $stmtCheck = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1");
                $stmtCheck->execute([$email, $id]);
                if ($stmtCheck->fetch()) {
                    $erro = 'Este e-mail já está sendo utilizado por outro usuário.';
                } else {
                    if (!empty($senha)) {
                        // Atualiza com nova senha
                        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                        $stmtUpdate = $pdo->prepare("UPDATE admins SET nome = ?, email = ?, senha = ?, tipo = ?, loja_id = ? WHERE id = ?");
                        $stmtUpdate->execute([$nome, $email, $senhaHash, $tipo, $loja_id, $id]);
                    } else {
                        // Mantém a senha antiga
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

// Buscar lojas para os selects
$lojas = [];
try {
    $stmtLojas = $pdo->query("SELECT id, nome_loja FROM lojas ORDER BY nome_loja ASC");
    $lojas = $stmtLojas->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Buscar todos os administradores cadastrados
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
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Topbar -->
    <header class="h-16 bg-slate-900 text-white px-6 flex items-center justify-between shadow-md">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-orange-600 flex items-center justify-center font-bold text-white">M</div>
            <div>
                <h1 class="text-sm font-bold">Painel Master</h1>
                <p class="text-[10px] text-slate-400">Controle de Acessos e Usuários</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <a href="painel.php" class="text-xs text-slate-300 hover:text-white transition-colors">Voltar ao Início</a>
            <a href="../logout.php" class="bg-slate-800 hover:bg-slate-700 text-rose-400 px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 border border-slate-700">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Sair
            </a>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">

        <!-- Cabeçalho e Botão Novo -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-800">Gerenciamento de Usuários</h2>
                <p class="text-xs text-slate-400 mt-0.5">Cadastre, edite ou remova acessos Master e Lojistas da plataforma.</p>
            </div>
            <button onclick="abrirModalCadastrar()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2 transition-colors shadow-sm">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Novo Usuário
            </button>
        </div>

        <!-- CARD DE SUCESSO -->
        <?php if (!empty($mensagem)): ?>
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-start gap-3 shadow-sm">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-emerald-800 text-sm">Sucesso!</h3>
                    <p class="text-xs text-emerald-700 mt-0.5"><?php echo htmlspecialchars($mensagem); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- CARD DE ERRO -->
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
                                        <?php if ($adm['tipo'] === 'master'): ?>
                                            <span class="bg-purple-50 text-purple-700 border border-purple-200 px-2.5 py-1 rounded-full text-xs font-semibold">Master</span>
                                        <?php else: ?>
                                            <span class="bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-full text-xs font-semibold">Lojista</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 font-medium text-slate-700">
                                        <?php echo ($adm['tipo'] === 'master') ? '<em class="text-slate-400">Plataforma Global</em>' : htmlspecialchars($adm['nome_loja'] ?? 'Nenhuma'); ?>
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

            <form action="gerenciar_usuarios.php" method="POST" class="space-y-4">
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
                        <option value="loja">Cliente / Lojista</option>
                        <option value="master">Master (Administrador Geral)</option>
                    </select>
                </div>

                <div id="formBlocoLoja">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Vincular à Loja</label>
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

            <form action="gerenciar_usuarios.php" method="POST" class="flex items-center justify-center gap-3 pt-2">
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
            document.getElementById('formTipo').value = adm.tipo;
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
