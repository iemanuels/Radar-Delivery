<?php
// mercado/endereco.php
session_start();
require_once '../admin/config.php';

$loja_id = 1; 

// Se a requisição for AJAX para buscar cliente e endereço pelo WhatsApp
if (isset($_GET['ajax_busca_whatsapp'])) {
    header('Content-Type: application/json');
    $whatsapp = preg_replace('/\D/', '', $_GET['whatsapp']);

    if (empty($whatsapp)) {
        echo json_encode(['encontrado' => false]);
        exit;
    }

    $stmtCliente = $pdo->prepare("SELECT id, nome FROM clientes WHERE telefone = ? AND loja_id = ?");
    $stmtCliente->execute([$whatsapp, $loja_id]);
    $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        $stmtEnd = $pdo->prepare("SELECT rua, numero, bairro, complemento FROM enderecos_clientes WHERE cliente_id = ? ORDER BY id DESC LIMIT 1");
        $stmtEnd->execute([$cliente['id']]);
        $endereco = $stmtEnd->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'encontrado' => true,
            'nome' => explode(' ', trim($cliente['nome']))[0],
            'endereco' => $endereco ? $endereco : null
        ]);
    } else {
        echo json_encode(['encontrado' => false]);
    }
    exit;
}

// Processamento do formulário ao avançar para pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeCompleto = trim($_POST['nome'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $rua = trim($_POST['rua'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');

    $telefoneLimpo = preg_replace('/\D/', '', $whatsapp);

    if (!empty($telefoneLimpo) && !empty($nomeCompleto) && !empty($rua)) {
        $stmtCheck = $pdo->prepare("SELECT id FROM clientes WHERE telefone = ? AND loja_id = ?");
        $stmtCheck->execute([$telefoneLimpo, $loja_id]);
        $clienteExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($clienteExistente) {
            $clienteId = $clienteExistente['id'];
            $stmtUpd = $pdo->prepare("UPDATE clientes SET nome = ? WHERE id = ?");
            $stmtUpd->execute([$nomeCompleto, $clienteId]);
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO clientes (loja_id, nome, telefone) VALUES (?, ?, ?)");
            $stmtIns->execute([$loja_id, $nomeCompleto, $telefoneLimpo]);
            $clienteId = $pdo->lastInsertId();
        }

        $stmtEndCheck = $pdo->prepare("SELECT id FROM enderecos_clientes WHERE cliente_id = ? ORDER BY id DESC LIMIT 1");
        $stmtEndCheck->execute([$clienteId]);
        $endExistente = $stmtEndCheck->fetch(PDO::FETCH_ASSOC);

        if ($endExistente) {
            $stmtEndUpd = $pdo->prepare("UPDATE enderecos_clientes SET rua = ?, numero = ?, bairro = ?, complemento = ? WHERE id = ?");
            $stmtEndUpd->execute([$rua, $numero, $bairro, $complemento, $endExistente['id']]);
        } else {
            $stmtEndIns = $pdo->prepare("INSERT INTO enderecos_clientes (cliente_id, rua, numero, bairro, complemento, principal) VALUES (?, ?, ?, ?, ?, 1)");
            $stmtEndIns->execute([$clienteId, $rua, $numero, $bairro, $complemento]);
        }

        $_SESSION['checkout_cliente_id'] = $clienteId;
        $_SESSION['checkout_cliente_nome'] = $nomeCompleto;
        $_SESSION['checkout_whatsapp'] = $telefoneLimpo;
        $_SESSION['checkout_endereco'] = "$rua, $numero - $bairro " . ($complemento ? "($complemento)" : "");

        header("Location: pagamento.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Endereço de Entrega | MercadoSaaS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-2xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="carrinho.php" class="text-slate-500 hover:text-slate-800 p-1 rounded-lg">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <h1 class="font-bold text-slate-800 text-base">Identificação e Entrega</h1>
            <div class="w-5"></div>
        </div>
    </header>

    <main class="flex-1 max-w-2xl w-full mx-auto p-4 sm:p-6 space-y-6">
        
        <form action="endereco.php" method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
            
            <!-- 1. Nome do Cliente -->
            <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-3 flex items-center gap-2">
                <i data-lucide="user" class="w-4 h-4 text-orange-500"></i> Seus Dados
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Primeiro Nome</label>
                    <input type="text" id="inputNome" name="nome" required placeholder="Ex: Emanuel" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">WhatsApp (Busca automática)</label>
                    <input type="text" id="inputWhatsapp" name="whatsapp" required placeholder="(92)99999-9999" maxlength="14" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                </div>
            </div>

            <!-- 2. Endereço de Entrega -->
            <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-3 pt-2 flex items-center gap-2">
                <i data-lucide="map-pin" class="w-4 h-4 text-orange-500"></i> Onde vamos entregar?
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Rua / Avenida</label>
                    <input type="text" id="inputRua" name="rua" required placeholder="Ex: Rua Itaituba" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Número</label>
                    <input type="text" id="inputNumero" name="numero" required placeholder="Ex: 45" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Bairro</label>
                    <input type="text" id="inputBairro" name="bairro" required placeholder="Ex: Gilberto Mestrinho" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Complemento / Referência</label>
                    <input type="text" id="inputComplemento" name="complemento" placeholder="Ex: Próximo à feira" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-orange-500">
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3.5 rounded-xl text-sm transition-colors shadow-md shadow-orange-500/20 flex items-center justify-center gap-2">
                    Continuar para Pagamento <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>

        </form>

    </main>

    <script>
        lucide.createIcons();

        const inputWhatsapp = document.getElementById('inputWhatsapp');
        
        // Lembrar WhatsApp do dispositivo ao carregar
        window.addEventListener('DOMContentLoaded', () => {
            const savedWhatsapp = localStorage.getItem('user_whatsapp');
            if (savedWhatsapp) {
                inputWhatsapp.value = savedWhatsapp;
                buscarClientePorWhatsapp(savedWhatsapp);
            }
        });

        // Máscara e formatação automática para o WhatsApp: (00)00000-0000
        inputWhatsapp.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);

            if (value.length > 6) {
                value = `(${value.slice(0,2)})${value.slice(2,7)}-${value.slice(7)}`;
            } else if (value.length > 2) {
                value = `(${value.slice(0,2)})${value.slice(2)}`;
            } else if (value.length > 0) {
                value = `(${value}`;
            }
            e.target.value = value;

            const apenasNumeros = value.replace(/\D/g, '');
            if (apenasNumeros.length === 11) {
                localStorage.setItem('user_whatsapp', value);
                buscarClientePorWhatsapp(value);
            }
        });

        function buscarClientePorWhatsapp(whatsapp) {
            fetch(`endereco.php?ajax_busca_whatsapp=1&whatsapp=${encodeURIComponent(whatsapp)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.encontrado) {
                        if (data.nome) {
                            document.getElementById('inputNome').value = data.nome;
                        }
                        if (data.endereco) {
                            document.getElementById('inputRua').value = data.endereco.rua || '';
                            document.getElementById('inputNumero').value = data.endereco.numero || '';
                            document.getElementById('inputBairro').value = data.endereco.bairro || '';
                            document.getElementById('inputComplemento').value = data.endereco.complemento || '';
                        }
                    }
                })
                .catch(err => console.error('Erro ao buscar dados do cliente:', err));
        }
    </script>
</body>
</html>