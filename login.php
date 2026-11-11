<?php
// login.php
session_start();
require_once 'admin/config.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!empty($email) && !empty($senha)) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($senha, $admin['senha'])) {
            // Salva dados na sessão
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_nome'] = $admin['nome'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['loja_id'] = $admin['loja_id'] ?? 0;
            $_SESSION['tipo'] = $admin['tipo'] ?? 'loja'; // Define se é 'master' ou 'loja'

            // Direcionamento com base no tipo de usuário
            if ($admin['tipo'] === 'master') {
                header('Location: admin/painel.php');
                exit;
            } else {
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $erro = 'E-mail ou senha incorretos.';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sistema Multi-Loja</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        /* Força a fonte no mobile a ter 16px ou mais para evitar o zoom automático no iOS/iPhone */
        @media screen and (max-width: 768px) {
            input[type="email"],
            input[type="password"],
            input[type="text"] {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center min-h-screen px-4">

    <!-- Caixa de Login -->
    <div class="max-w-md w-full bg-white rounded-3xl border border-slate-100 shadow-xl p-8 space-y-6 animate-fade-in relative overflow-hidden">
        
        <!-- Detalhe decorativo superior -->
        <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-orange-500 to-amber-500"></div>

        <!-- Cabeçalho com Ícone -->
        <div class="text-center space-y-2 pt-2">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-orange-50 text-orange-600 mb-1 shadow-inner">
                <i class="fa-solid fa-store text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800">Acesso ao Sistema</h1>
            <p class="text-sm text-slate-500">Entre com suas credenciais para continuar</p>
        </div>

        <!-- Mensagem de Erro -->
        <?php if (!empty($erro)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 p-3.5 rounded-xl text-xs font-medium text-center flex items-center justify-center gap-2 animate-pulse">
                <i class="fa-solid fa-circle-exclamation text-sm"></i>
                <span><?php echo htmlspecialchars($erro); ?></span>
            </div>
        <?php endif; ?>

        <!-- Formulário -->
        <form action="login.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">E-mail</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <i class="fa-regular fa-envelope"></i>
                    </span>
                    <input type="email" name="email" required placeholder="seu@email.com" 
                        class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 text-sm md:text-sm transition-all">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider">Senha</label>
                    <button type="button" onclick="abrirModal()" class="text-xs font-medium text-orange-600 hover:text-orange-700 transition-colors">
                        Esqueceu a senha?
                    </button>
                </div>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <i class="fa-regular fa-lock"></i>
                    </span>
                    <input type="password" name="senha" id="senha" required placeholder="••••••••" 
                        class="w-full pl-10 pr-12 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 text-sm md:text-sm transition-all">
                    <!-- Botão Olho para ver/ocultar senha -->
                    <button type="button" onclick="toggleSenha()" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 transition-colors">
                        <i id="icone-olho" class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-semibold py-3 rounded-xl transition-all duration-200 text-sm shadow-md shadow-orange-600/20 hover:shadow-lg hover:shadow-orange-600/30 flex items-center justify-center gap-2 group">
                <span>Entrar</span>
                <i class="fa-solid fa-arrow-right-to-bracket text-xs group-hover:translate-x-1 transition-transform"></i>
            </button>
        </form>

        <!-- Rodapé discreto -->
        <div class="text-center pt-2">
            <p class="text-xs text-slate-400">Ambiente Seguro &bull; Multi-Loja</p>
        </div>
    </div>

    <!-- Modal de Recuperação de Senha -->
    <div id="modal-senha" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden transition-opacity opacity-0">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 space-y-5 shadow-2xl border border-slate-100 transform scale-95 transition-transform duration-200" id="modal-conteudo">
            <div class="text-center space-y-3">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-orange-50 text-orange-600 shadow-inner">
                    <i class="fa-solid fa-headset text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800">Esqueceu a senha?</h3>
                <p class="text-sm text-slate-500 leading-relaxed">
                    Para redefinir sua senha, por favor, entre em contato com o suporte técnico ou com quem lhe vendeu/forneceu o sistema.
                </p>
            </div>
            <div>
                <button type="button" onclick="fecharModal()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 rounded-xl transition-colors text-sm">
                    Entendi
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts de Interação (Olho da Senha & Modal) -->
    <script>
        // Função para Alternar Visibilidade da Senha
        function toggleSenha() {
            const inputSenha = document.getElementById('senha');
            const iconeOlho = document.getElementById('icone-olho');

            if (inputSenha.type === 'password') {
                inputSenha.type = 'text';
                iconeOlho.classList.remove('fa-eye');
                iconeOlho.classList.add('fa-eye-slash');
            } else {
                inputSenha.type = 'password';
                iconeOlho.classList.remove('fa-eye-slash');
                iconeOlho.classList.add('fa-eye');
            }
        }

        // Funções do Modal
        const modal = document.getElementById('modal-senha');
        const modalConteudo = document.getElementById('modal-conteudo');

        function abrirModal() {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modalConteudo.classList.remove('scale-95');
                modalConteudo.classList.add('scale-100');
            }, 10);
        }

        function fecharModal() {
            modal.classList.add('opacity-0');
            modalConteudo.classList.remove('scale-100');
            modalConteudo.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 200);
        }

        // Fechar modal ao clicar fora dele
        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                fecharModal();
            }
        });
    </script>
</body>
</html>