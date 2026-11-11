<?php
// cad.php (Localizado dentro da pasta admin/)

// Ativa a exibição de erros do PHP na tela para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Correção do caminho: Como o cad.php já está em /admin/, chamamos config.php diretamente
require_once 'config.php';

$mensagem = '';
$erro = '';

// Buscar lojas cadastradas para o caso de o usuário ser do tipo 'loja'
$lojas = [];
try {
    $stmtLojas = $pdo->query("SELECT id, nome_loja FROM lojas ORDER BY nome_loja ASC");
    $lojas = $stmtLojas->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $erro = "Erro ao buscar lojas: " . $e->getMessage();
}

// Processar o cadastro de um novo administrador/usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tipo  = $_POST['tipo'] ?? 'loja';
    $loja_id = ($tipo === 'master') ? 0 : (int) ($_POST['loja_id'] ?? 0);

    if (!empty($nome) && !empty($email) && !empty($senha)) {
        try {
            // Verificar se o e-mail já existe
            $stmtCheck = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $erro = 'Este e-mail já está cadastrado no sistema.';
            } else {
                // Criptografar a senha com segurança
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                // Inserir no banco
                $stmtInsert = $pdo->prepare("INSERT INTO admins (loja_id, tipo, nome, email, senha, criado_em) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmtInsert->execute([$loja_id, $tipo, $nome, $email, $senhaHash]);

                $mensagem = 'Usuário cadastrado com sucesso!';
            }
        } catch (Exception $e) {
            $erro = 'Erro no banco de dados: ' . $e->getMessage();
        }
    } else {
        $erro = 'Preencha todos os campos obrigatórios.';
    }
}

// Buscar todos os administradores cadastrados para exibição
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
    if (empty($erro)) {
        $erro = 'Erro ao carregar usuários (Verifique se a tabela admins possui a estrutura correta): ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Usuários | Sistema</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen p-4 sm:p-8 flex flex-col items-center">

    <div class="max-w-4xl w-full space-y-6">
        
        <!-- Cabeçalho -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Gerenciamento de Acessos</h1>
                <p class="text-xs text-slate-400 mt-0.5">Cadastre usuários Master ou vincule lojistas às suas respectivas lojas.</p>
            </div>
            <a href="../login.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-2">
                <i data-lucide="log-in" class="w-4 h-4"></i> Ir para o Login
            </a>
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

        <!-- CARD DE ERRO DO PHP / SISTEMA -->
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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Formulário de Cadastro -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4 md:col-span-1">
                <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-3">Novo Usuário</h3>
                
                <form action="cad.php" method="POST" class="space-y-3">
                    <input type="hidden" name="acao" value="cadastrar">

                    <div>
                        <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Nome Completo</label>
                        <input type="text" name="nome" required class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 text-xs">
                    </div>

                    <div>
                        <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">E-mail de Acesso</label>
                        <input type="email" name="email" required class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 text-xs">
                    </div>

                    <div>
                        <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Senha</label>
                        <input type="password" name="senha" required class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 text-xs">
                    </div>

                    <div>
                        <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Tipo de Usuário</label>
                        <select name="tipo" id="tipoUsuario" onchange="alternarLoja()" class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 text-xs bg-white">
                            <option value="loja">Cliente / Lojista</option>
                            <option value="master">Master (Administrador Geral)</option>
                        </select>
                    </div>

                    <div id="blocoLoja">
                        <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Vincular à Loja</label>
                        <select name="loja_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 text-xs bg-white">
                            <option value="">Selecione a Loja</option>
                            <?php foreach ($lojas as $l): ?>
                                <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['nome_loja']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2.5 rounded-xl transition-colors text-xs shadow-sm mt-2">
                        Cadastrar Usuário
                    </button>
                </form>
            </div>

            <!-- Listagem de Usuários -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden md:col-span-2 flex flex-col">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800 text-sm">Usuários Cadastrados</h3>
                    <p class="text-[11px] text-slate-400">Relação de contas com acesso ao painel</p>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 font-semibold uppercase tracking-wider">
                                <th class="py-3 px-4">Nome / E-mail</th>
                                <th class="py-3 px-4">Tipo</th>
                                <th class="py-3 px-4">Loja Vinculada</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600">
                            <?php if (count($admins) > 0): ?>
                                <?php foreach ($admins as $adm): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="py-3 px-4">
                                            <span class="font-bold text-slate-800 block"><?php echo htmlspecialchars($adm['nome']); ?></span>
                                            <span class="text-[11px] text-slate-400"><?php echo htmlspecialchars($adm['email']); ?></span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php if ($adm['tipo'] === 'master'): ?>
                                                <span class="bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded-full font-semibold">Master</span>
                                            <?php else: ?>
                                                <span class="bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full font-semibold">Lojista</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4 font-medium text-slate-700">
                                            <?php echo ($adm['tipo'] === 'master') ? '<em>Plataforma Global (Todas)</em>' : htmlspecialchars($adm['nome_loja'] ?? 'Nenhuma'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-slate-400">Nenhum usuário cadastrado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>

    <script>
        lucide.createIcons();

        function alternarLoja() {
            const tipo = document.getElementById('tipoUsuario').value;
            const blocoLoja = document.getElementById('blocoLoja');
            if (tipo === 'master') {
                blocoLoja.style.display = 'none';
            } else {
                blocoLoja.style.display = 'block';
            }
        }
    </script>
</body>
</html>