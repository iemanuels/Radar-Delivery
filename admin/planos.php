<?php
// admin/planos.php
require_once 'auth_master.php';
require_once 'config.php';

$mensagem = '';
$tipoMensagem = '';

// Garantir que a tabela de configurações globais do sistema exista para o tempo de teste
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `configuracoes_sistema` (
          `chave` varchar(100) NOT NULL PRIMARY KEY,
          `valor` text NOT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
    ");
} catch (PDOException $e) {
    // Ignora se já existir
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_plano') {
        $id = $_POST['id'] ?? '';
        $nome_plano = trim($_POST['nome_plano']);
        $valor = floatval(str_replace(',', '.', $_POST['valor']));
        $ciclo = $_POST['ciclo'];
        $limite_produtos = intval($_POST['limite_produtos']);
        $descricao = trim($_POST['descricao']);

        try {
            if (!empty($id)) {
                $stmt = $pdo->prepare("UPDATE planos SET nome_plano = ?, valor = ?, ciclo = ?, limite_produtos = ?, descricao = ? WHERE id = ?");
                $stmt->execute([$nome_plano, $valor, $ciclo, $limite_produtos, $descricao, $id]);
                $mensagem = "Plano atualizado com sucesso!";
                $tipoMensagem = "sucesso";
            } else {
                $stmt = $pdo->prepare("INSERT INTO planos (nome_plano, valor, ciclo, limite_produtos, descricao) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nome_plano, $valor, $ciclo, $limite_produtos, $descricao]);
                $mensagem = "Novo plano criado com sucesso!";
                $tipoMensagem = "sucesso";
            }
        } catch (PDOException $e) {
            $mensagem = "Erro ao salvar plano: " . $e->getMessage();
            $tipoMensagem = "erro";
        }
    } elseif ($acao === 'excluir_plano') {
        $id = intval($_POST['id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM planos WHERE id = ?");
            $stmt->execute([$id]);
            $mensagem = "Plano removido com sucesso!";
            $tipoMensagem = "sucesso";
        } catch (PDOException $e) {
            $mensagem = "Erro ao remover plano: " . $e->getMessage();
            $tipoMensagem = "erro";
        }
    } elseif ($acao === 'salvar_config_teste') {
        $dias_teste = intval($_POST['dias_teste_padrao']);
        try {
            $stmt = $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES ('dias_teste_padrao', ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute([$dias_teste, $dias_teste]);
            $mensagem = "Tempo padrão de teste atualizado com sucesso!";
            $tipoMensagem = "sucesso";
        } catch (PDOException $e) {
            $mensagem = "Erro ao salvar configuração: " . $e->getMessage();
            $tipoMensagem = "erro";
        }
    }
}

// Buscar dias de teste configurados (padrão 7 dias se não definido)
$stmtConfig = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'dias_teste_padrao'");
$stmtConfig->execute();
$configTeste = $stmtConfig->fetch(PDO::FETCH_ASSOC);
$diasTestePadrao = $configTeste ? intval($configTeste['valor']) : 7;

// Buscar todos os planos cadastrados
$stmtPlanos = $pdo->query("SELECT * FROM planos ORDER BY id ASC");
$planos = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Planos e Testes | Painel Master</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Menu Superior Compartilhado -->
    <?php include 'menu.php'; ?>

    <!-- Conteúdo Principal -->
    <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">
        
        <!-- Cabeçalho -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-800 tracking-tight">Gestão de Planos e Período de Teste</h2>
                <p class="text-xs text-slate-500 mt-0.5">Configure os planos (Teste, Mensal, Vitalício) e ajuste o tempo de teste das novas lojas.</p>
            </div>
            <button onclick="abrirModalPlano()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2.5 rounded-xl text-xs font-semibold transition-colors flex items-center justify-center gap-2 shadow-sm">
                <i data-lucide="plus" class="w-4 h-4"></i> Criar Novo Plano
            </button>
        </div>

        <!-- Alertas -->
        <?php if (!empty($mensagem)): ?>
            <div class="p-4 rounded-xl text-xs font-medium flex items-center gap-2 border shadow-sm <?php echo $tipoMensagem === 'sucesso' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'; ?>">
                <i data-lucide="<?php echo $tipoMensagem === 'sucesso' ? 'check-circle-2' : 'alert-circle'; ?>" class="w-4 h-4 shrink-0"></i>
                <span><?php echo htmlspecialchars($mensagem); ?></span>
            </div>
        <?php endif; ?>

        <!-- Card de Configuração Manual do Tempo de Teste -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center gap-3 mb-4 border-b border-slate-100 pb-3">
                <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center border border-orange-100">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Configuração do Período de Teste Manual</h3>
                    <p class="text-xs text-slate-400">Defina por quanto tempo (em dias) novos cadastros ficam experimentando o sistema.</p>
                </div>
            </div>

            <form action="planos.php" method="POST" class="flex flex-col sm:flex-row items-end gap-4">
                <input type="hidden" name="acao" value="salvar_config_teste">
                <div class="w-full sm:w-72">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Dias de Teste Padrão</label>
                    <input type="number" name="dias_teste_padrao" value="<?php echo $diasTestePadrao; ?>" min="1" max="365" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                </div>
                <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-2 shadow-sm h-10">
                    <i data-lucide="save" class="w-4 h-4"></i> Salvar Configuração
                </button>
            </form>
        </div>

        <!-- Lista de Planos em Cards / Grid -->
        <div>
            <h3 class="text-base font-bold text-slate-800 mb-4">Planos Cadastrados (Teste, Mensal e Vitalício)</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php if (count($planos) > 0): ?>
                    <?php foreach ($planos as $plano): ?>
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between relative hover:border-slate-300 transition-colors">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <span class="inline-flex items-center gap-1.5 bg-orange-50 text-orange-700 border border-orange-100 px-3 py-1 rounded-full text-xs font-semibold">
                                        <i data-lucide="tag" class="w-3.5 h-3.5"></i> <?php echo htmlspecialchars($plano['nome_plano']); ?>
                                    </span>
                                    <span class="text-xs font-medium text-slate-400 capitalize">Ciclo: <?php echo htmlspecialchars($plano['ciclo']); ?></span>
                                </div>

                                <div class="mb-4">
                                    <h4 class="text-3xl font-extrabold text-slate-800">
                                        R$ <?php echo number_format($plano['valor'], 2, ',', '.'); ?>
                                    </h4>
                                    <p class="text-xs text-slate-400 mt-1">Limite de produtos: <strong class="text-slate-700"><?php echo $plano['limite_produtos'] >= 9999 ? 'Ilimitado' : $plano['limite_produtos']; ?></strong></p>
                                </div>

                                <p class="text-xs text-slate-600 mb-6 bg-slate-50 p-3 rounded-xl border border-slate-100 min-h-[50px]">
                                    <?php echo htmlspecialchars($plano['descricao'] ?: 'Sem descrição detalhada.'); ?>
                                </p>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                                <button onclick='editarPlano(<?php echo json_encode($plano); ?>)' class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Editar
                                </button>
                                <button onclick='excluirPlano(<?php echo $plano['id']; ?>, "<?php echo htmlspecialchars($plano['nome_plano']); ?>")' class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Excluir
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-3 bg-white p-12 rounded-2xl border border-slate-200 text-center text-slate-400 text-sm">
                        Nenhum plano cadastrado. Clique em "Criar Novo Plano" para começar.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- Modal Criar / Editar Plano -->
    <div id="modalPlano" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 id="modalTitulo" class="text-base font-bold text-slate-800">Criar Novo Plano</h3>
                <button onclick="fecharModalPlano()" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form action="planos.php" method="POST" class="space-y-4">
                <input type="hidden" name="acao" value="salvar_plano">
                <input type="hidden" id="plano_id" name="id" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nome do Plano (ex: Teste, Mensal, Vitalício)</label>
                    <input type="text" id="nome_plano" name="nome_plano" required placeholder="Ex: Mensal" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Valor (R$)</label>
                        <input type="text" id="valor" name="valor" required placeholder="49.90" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Ciclo de Cobrança</label>
                        <select id="ciclo" name="ciclo" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                            <option value="mensal">Mensal</option>
                            <option value="anual">Anual / Vitalício</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Limite de Produtos</label>
                    <input type="number" id="limite_produtos" name="limite_produtos" value="100" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Descrição</label>
                    <textarea id="descricao" name="descricao" rows="3" placeholder="Detalhes ou vantagens do plano..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-orange-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="button" onclick="fecharModalPlano()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition-colors shadow-sm">
                        Salvar Plano
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formulário Oculto para Exclusão -->
    <form id="formExcluir" action="planos.php" method="POST" class="hidden">
        <input type="hidden" name="acao" value="excluir_plano">
        <input type="hidden" id="excluir_id" name="id" value="">
    </form>

    <footer class="mt-auto py-6 text-center text-xs text-slate-400 border-t border-slate-200 bg-white">
        Painel Master &copy; <?php echo date('Y'); ?> - Todos os direitos reservados.
    </footer>

    <script>
        lucide.createIcons();

        function abrirModalPlano() {
            document.getElementById('modalTitulo').innerText = 'Criar Novo Plano';
            document.getElementById('plano_id').value = '';
            document.getElementById('nome_plano').value = '';
            document.getElementById('valor').value = '';
            document.getElementById('ciclo').value = 'mensal';
            document.getElementById('limite_produtos').value = '100';
            document.getElementById('descricao').value = '';
            document.getElementById('modalPlano').classList.remove('hidden');
            document.getElementById('modalPlano').classList.add('flex');
        }

        function editarPlano(plano) {
            document.getElementById('modalTitulo').innerText = 'Editar Plano #' + plano.id;
            document.getElementById('plano_id').value = plano.id;
            document.getElementById('nome_plano').value = plano.nome_plano;
            document.getElementById('valor').value = plano.valor;
            document.getElementById('ciclo').value = plano.ciclo;
            document.getElementById('limite_produtos').value = plano.limite_produtos;
            document.getElementById('descricao').value = plano.descricao;
            document.getElementById('modalPlano').classList.remove('hidden');
            document.getElementById('modalPlano').classList.add('flex');
        }

        function fecharModalPlano() {
            document.getElementById('modalPlano').classList.remove('flex');
            document.getElementById('modalPlano').classList.add('hidden');
        }

        function excluirPlano(id, nome) {
            if (confirm('Tem certeza que deseja excluir o plano "' + nome + '"?')) {
                document.getElementById('excluir_id').value = id;
                document.getElementById('formExcluir').submit();
            }
        }
    </script>
</body>
</html>