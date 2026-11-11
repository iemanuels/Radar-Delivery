<?php
// visitante.php
// Sistema: DeliveryAm | Desenvolvido por Emanuel Silva | Blou Soluções - Manaus/AM

// Inclui o arquivo de conexão localizado na pasta admin
require_once 'admin/config.php';

$planos = [];
try {
    // Busca dinâmica dos planos cadastrados no banco de dados (tabela 'planos')
    $stmt = $pdo->query("SELECT id, nome_plano AS nome, descricao, valor AS preco, 
        CASE WHEN id = 2 THEN 1 ELSE 0 END AS destaque, 
        'Produtos ilimitados|Painel de pedidos|Suporte dedicado' AS recursos 
        FROM planos ORDER BY preco ASC");
    $planos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    // Fallback caso ocorra algum erro na consulta, mantendo o layout intacto
    $planos = [
        [
            'id' => 1,
            'nome' => 'Mensal',
            'descricao' => 'Ideal para pequenos comércios iniciando no delivery.',
            'preco' => 50.00,
            'destaque' => 0,
            'recursos' => 'Até 100 produtos|Painel de pedidos em tempo real|Gestão de clientes e estoque|Suporte por WhatsApp'
        ],
        [
            'id' => 2,
            'nome' => 'Vitalício',
            'descricao' => 'Para mercados e padarias que buscam máxima economia.',
            'preco' => 500.00,
            'destaque' => 1,
            'recursos' => 'Produtos ilimitados|Gestão completa de entregadores|Pix Copia e Cola avançado|Domínio personalizado'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeliveryAm | O Sistema Definitivo para Delivery de Mercados e Padarias</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }
        .animate-float {
            animation: float 4s ease-in-out infinite;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-orange-500 selection:text-white overflow-x-hidden">

    <!-- Header / Menu de Navegação Superior -->
    <header class="fixed top-0 inset-x-0 z-50 bg-white/85 backdrop-blur-md border-b border-slate-200/80 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between">
            
            <!-- Logo Empresa (Blou Soluções / DeliveryAm) -->
            <a href="#" class="flex items-center gap-3 group">
                <div class="w-11 h-11 rounded-2xl bg-orange-500 flex items-center justify-center text-white shadow-lg shadow-orange-500/25 transition-transform duration-300 group-hover:scale-110 overflow-hidden border border-orange-400">
                    <img src="uploads/blou.jpg" alt="Logo Blou Soluções" class="w-full h-full object-cover" onerror="this.onerror=null; this.parentNode.innerHTML='<i data-lucide=\'store\' class=\'w-5 h-5\'></i>'; lucide.createIcons();">
                </div>
                <div class="flex flex-col">
                    <span class="font-extrabold text-slate-900 text-lg sm:text-xl tracking-tight leading-none">DeliveryAm</span>
                    <span class="text-[10px] text-orange-600 font-semibold tracking-wider uppercase mt-0.5">Blou Soluções</span>
                </div>
            </a>

            <!-- Links Desktop -->
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600">
                <a href="#recursos" class="hover:text-orange-600 transition-colors">Recursos</a>
                <a href="#beneficios" class="hover:text-orange-600 transition-colors">Vantagens</a>
                <a href="#planos" class="hover:text-orange-600 transition-colors">Planos & Preços</a>
                <a href="#como-funciona" class="hover:text-orange-600 transition-colors">Como Funciona</a>
                <a href="#criador" class="hover:text-orange-600 transition-colors">Criador</a>
            </nav>

            <!-- Ações -->
            <div class="flex items-center gap-3">
                <a href="admin/painel.php" class="hidden sm:inline-flex text-sm font-semibold text-slate-700 hover:text-orange-600 px-4 py-2.5 transition-colors">
                    Entrar no Painel
                </a>
                <a href="#planos" class="bg-orange-500 hover:bg-orange-600 text-white text-xs sm:text-sm font-semibold px-4 sm:px-5 py-2.5 rounded-xl transition-all shadow-md shadow-orange-500/25 hover:shadow-lg hover:shadow-orange-500/35 hover:-translate-y-0.5">
                    Criar Minha Loja
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section (Apresentação Principal) -->
    <section class="pt-32 pb-20 md:pt-40 md:pb-32 relative overflow-hidden bg-gradient-to-b from-white via-slate-50 to-slate-100">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-orange-400/10 rounded-full blur-3xl pointer-events-none animate-pulse"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center relative z-10">
            
            <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                <div class="inline-flex items-center gap-2 bg-orange-50 border border-orange-200 text-orange-700 px-3.5 py-1.5 rounded-full text-xs font-semibold tracking-wide shadow-sm animate-bounce">
                    <i data-lucide="zap" class="w-3.5 h-3.5"></i> Desenvolvido em Manaus por Blou Soluções
                </div>
                
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 tracking-tight leading-[1.15]">
                    Digitalize seu Mercado ou Padaria e <span class="text-orange-600">Domine o Delivery</span>
                </h1>
                
                <p class="text-base sm:text-lg text-slate-600 max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                    Tenha sua própria plataforma de delivery profissional em minutos. Controle estoque, acompanhe pedidos em tempo real, gerencie entregadores e aumente seu faturamento sem taxas abusivas por venda.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 pt-2">
                    <a href="#planos" class="w-full sm:w-auto bg-orange-500 hover:bg-orange-600 text-white font-semibold px-8 py-4 rounded-xl transition-all shadow-lg shadow-orange-500/30 hover:shadow-xl hover:shadow-orange-500/40 hover:-translate-y-0.5 flex items-center justify-center gap-2 group">
                        Escolher Plano <i data-lucide="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-1"></i>
                    </a>
                    <a href="https://wa.me/5592981028812?text=Olá%20Emanuel,%20quero%20saber%20mais%20sobre%20o%20sistema%20DeliveryAm!" target="_blank" class="w-full sm:w-auto bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-semibold px-8 py-4 rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 hover:border-orange-300">
                        <i data-lucide="message-circle" class="w-4 h-4 text-emerald-600"></i> Falar com Emanuel
                    </a>
                </div>

                <div class="pt-6 flex flex-wrap items-center justify-center lg:justify-start gap-4 sm:gap-6 text-xs text-slate-500 font-medium">
                    <div class="flex items-center gap-1.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i> 0% taxa por venda</div>
                    <div class="flex items-center gap-1.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i> Pix automatizado</div>
                    <div class="flex items-center gap-1.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i> Suporte local em Manaus</div>
                </div>
            </div>

            <!-- Mockup Responsivo Multitelas -->
            <div class="lg:col-span-5 relative">
                <div class="absolute -inset-4 bg-orange-500/15 rounded-3xl blur-2xl -z-10 animate-pulse"></div>
                <div class="glass-card p-4 rounded-3xl border border-slate-200/80 shadow-2xl space-y-4 animate-float">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100 px-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-orange-600 flex items-center gap-1.5">
                            <i data-lucide="smartphone" class="w-4 h-4"></i> Multitelas Responsivo
                        </span>
                        <span class="bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-emerald-200">100% Otimizado</span>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2 items-center">
                        <div class="space-y-1 text-center transform transition-all hover:-translate-y-1">
                            <div class="bg-slate-900 p-1.5 rounded-xl shadow-md border border-slate-700">
                                <img src="uploads/pc.jpg" alt="Sistema no PC" class="w-full h-24 object-cover rounded-lg" onerror="this.src='https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=400&auto=format&fit=crop&q=60'">
                            </div>
                            <span class="text-[10px] font-semibold text-slate-500">Desktop</span>
                        </div>
                        <div class="space-y-1 text-center -mt-4 transform transition-all hover:-translate-y-1">
                            <div class="bg-slate-900 p-1.5 rounded-xl shadow-xl border border-slate-700 ring-2 ring-orange-500/50">
                                <img src="uploads/tablet.jpg" alt="Sistema no Tablet" class="w-full h-32 object-cover rounded-lg" onerror="this.src='https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=400&auto=format&fit=crop&q=60'">
                            </div>
                            <span class="text-[10px] font-semibold text-slate-700">Tablet</span>
                        </div>
                        <div class="space-y-1 text-center transform transition-all hover:-translate-y-1">
                            <div class="bg-slate-900 p-1.5 rounded-xl shadow-md border border-slate-700">
                                <img src="uploads/celular.jpg" alt="Sistema no Celular" class="w-full h-28 object-cover rounded-lg" onerror="this.src='https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=400&auto=format&fit=crop&q=60'">
                            </div>
                            <span class="text-[10px] font-semibold text-slate-500">Mobile</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Recursos Principais -->
    <section id="recursos" class="py-24 bg-white relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-16">
            <div class="text-center max-w-2xl mx-auto space-y-3">
                <span class="text-xs font-bold text-orange-600 uppercase tracking-wider">Tecnologia de Ponta</span>
                <h2 class="text-3xl font-extrabold text-slate-900">Tudo o que sua loja precisa para lucrar mais</h2>
                <p class="text-slate-600 text-sm">Ferramentas robustas, dinâmicas e intuitivas desenvolvidas especificamente para o setor alimentício e varejo.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200/80 hover:border-orange-500/50 transition-all duration-300 hover:shadow-xl hover:-translate-y-1.5 group">
                    <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center mb-6 shadow-md shadow-orange-500/20 group-hover:scale-110 transition-transform">
                        <i data-lucide="layout-dashboard" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 text-lg mb-2">Painel de Pedidos Ágil</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">Gerencie novos pedidos, separe itens, despache para entregadores e altere status em tempo real sem complicações.</p>
                </div>

                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200/80 hover:border-orange-500/50 transition-all duration-300 hover:shadow-xl hover:-translate-y-1.5 group">
                    <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center mb-6 shadow-md shadow-orange-500/20 group-hover:scale-110 transition-transform">
                        <i data-lucide="package" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 text-lg mb-2">Estoque e Produtos</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">Organize seu catálogo por categorias, controle quantidades disponíveis e receba alertas automáticos de itens esgotados.</p>
                </div>

                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200/80 hover:border-orange-500/50 transition-all duration-300 hover:shadow-xl hover:-translate-y-1.5 group">
                    <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center mb-6 shadow-md shadow-orange-500/20 group-hover:scale-110 transition-transform">
                        <i data-lucide="qr-code" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 text-lg mb-2">Pix Integrado & QR Code</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">Permita pagamentos via Pix com chave automatizada e Copia e Cola, além de opções de cartão e dinheiro na entrega.</p>
                </div>

                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200/80 hover:border-orange-500/50 transition-all duration-300 hover:shadow-xl hover:-translate-y-1.5 group">
                    <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center mb-6 shadow-md shadow-orange-500/20 group-hover:scale-110 transition-transform">
                        <i data-lucide="clock" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 text-lg mb-2">Funcionamento Automático</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">Defina horários de abertura e fechamento. O sistema abre e fecha sua loja digital de forma automática conforme sua rotina.</p>
                </div>

                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200/80 hover:border-orange-500/50 transition-all duration-300 hover:shadow-xl hover:-translate-y-1.5 group">
                    <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center mb-6 shadow-md shadow-orange-500/20 group-hover:scale-110 transition-transform">
                        <i data-lucide="bike" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 text-lg mb-2">Gestão de Entregadores</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">Cadastre sua frota de motoboys, acompanhe taxas acumuladas por entrega e otimize as rotas de despacho.</p>
                </div>

                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200/80 hover:border-orange-500/50 transition-all duration-300 hover:shadow-xl hover:-translate-y-1.5 group">
                    <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center mb-6 shadow-md shadow-orange-500/20 group-hover:scale-110 transition-transform">
                        <i data-lucide="smartphone" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-slate-900 text-lg mb-2">Vitrine Mobile Pronta</h3>
                    <p class="text-slate-600 text-sm leading-relaxed">Seus clientes acessam um cardápio limpo, rápido e moderno diretamente pelo celular, com sacola e linha do tempo do pedido.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Seção Benefícios / Como Funciona -->
    <section id="beneficios" class="py-24 bg-slate-900 text-white overflow-hidden relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="space-y-6">
                <span class="text-xs font-bold text-orange-400 uppercase tracking-wider">Simplicidade e Rapidez</span>
                <h2 class="text-3xl font-extrabold tracking-tight">Pronto para faturar no digital em apenas 3 passos</h2>
                
                <div class="space-y-5 pt-2">
                    <div class="flex items-start gap-4 transition-transform hover:translate-x-1 duration-200">
                        <div class="w-8 h-8 rounded-lg bg-orange-500 text-white font-bold flex items-center justify-center shrink-0 shadow-md">1</div>
                        <div>
                            <h4 class="font-bold text-white text-base">Cadastre sua Loja</h4>
                            <p class="text-xs sm:text-sm text-slate-400 mt-1">Insira o nome, endereço, CNPJ e configure seus horários de funcionamento e logotipo com facilidade.</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-4 transition-transform hover:translate-x-1 duration-200">
                        <div class="w-8 h-8 rounded-lg bg-orange-500 text-white font-bold flex items-center justify-center shrink-0 shadow-md">2</div>
                        <div>
                            <h4 class="font-bold text-white text-base">Adicione seus Produtos</h4>
                            <p class="text-xs sm:text-sm text-slate-400 mt-1">Cadastre itens, preços e ofertas divididos em categorias organizadas no painel administrativo.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 transition-transform hover:translate-x-1 duration-200">
                        <div class="w-8 h-8 rounded-lg bg-orange-500 text-white font-bold flex items-center justify-center shrink-0 shadow-md">3</div>
                        <div>
                            <h4 class="font-bold text-white text-base">Compartilhe o Link e Venda</h4>
                            <p class="text-xs sm:text-sm text-slate-400 mt-1">Envie o link do seu delivery no WhatsApp dos clientes e comece a receber pedidos instantaneamente.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative">
                <div class="absolute -inset-2 bg-orange-500/20 rounded-2xl blur-xl -z-10"></div>
                <div class="bg-slate-800 p-3 rounded-2xl border border-slate-700 shadow-2xl">
                    <img src="uploads/fotos.jpg" alt="Sistema de Delivery em Ação" class="w-full h-80 object-cover rounded-xl" onerror="this.src='https://images.unsplash.com/photo-1556742049-0a67d553c2a5?w=600&auto=format&fit=crop&q=60'">
                </div>
            </div>
        </div>
    </section>

    <!-- Seção de Planos e Preços (Dinâmica via Banco de Dados com redirecionamento para o WhatsApp) -->
    <section id="planos" class="py-24 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-16">
            <div class="text-center max-w-2xl mx-auto space-y-3">
                <span class="text-xs font-bold text-orange-600 uppercase tracking-wider">Investimento Inteligente</span>
                <h2 class="text-3xl font-extrabold text-slate-900">Planos transparentes que cabem no seu bolso</h2>
                <p class="text-slate-600 text-sm">Sem comissões por venda. Você paga apenas o valor fixo e lucra 100% sobre o seu faturamento.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <?php foreach ($planos as $p): 
                    $isDestaque = isset($p['destaque']) && $p['destaque'] == 1;
                    $isVitalicio = (strtolower($p['nome']) === 'vitalício' || strtolower($p['nome']) === 'vitalicio' || $p['id'] == 2);
                    $recursosList = explode('|', $p['recursos'] ?? '');
                    $nomePlano = htmlspecialchars($p['nome']);
                    $precoPlano = number_format($p['preco'], 2, ',', '.');
                    
                    // Texto do período conforme o plano
                    $textoperiodo = $isVitalicio ? 'vitalício (pagamento único)' : 'por mês (R$ ' . $precoPlano . '/mês)';
                    
                    // Mensagem do WhatsApp sem pedir o nome de cara
                    $mensagemWhats = urlencode("Olá Emanuel! Tenho interesse no plano *{$nomePlano}* (R$ {$precoPlano} - {$textoperiodo}) do sistema DeliveryAm. Qual o próximo passo para ativar minha loja?");
                    $linkWhatsApp = "https://wa.me/5592981028812?text=" . $mensagemWhats;
                ?>
                <div class="bg-white p-8 rounded-3xl <?php echo $isDestaque ? 'border-2 border-orange-500 shadow-xl transform lg:-translate-y-2 relative' : 'border border-slate-200 shadow-sm hover:shadow-lg'; ?> flex flex-col justify-between space-y-6 transition-all duration-300">
                    <?php if ($isDestaque): ?>
                        <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-orange-500 text-white text-[10px] font-bold px-3.5 py-1 rounded-full uppercase tracking-wider shadow-sm animate-pulse">
                            Mais Escolhido
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <h3 class="font-bold text-slate-900 text-lg mb-2"><?php echo $nomePlano; ?></h3>
                        <p class="text-xs text-slate-500 mb-6"><?php echo htmlspecialchars($p['descricao']); ?></p>
                        <div class="mb-6 flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-slate-900">R$ <?php echo $precoPlano; ?></span>
                            <span class="text-xs text-slate-400 font-medium">
                                <?php echo $isVitalicio ? '/vitalício' : '/mês'; ?>
                            </span>
                        </div>
                        <ul class="space-y-3 text-sm text-slate-600 border-t border-slate-100 pt-6">
                            <?php foreach ($recursosList as $rec): if(trim($rec) == '') continue; ?>
                                <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0"></i> <?php echo htmlspecialchars($rec); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <a href="<?php echo $linkWhatsApp; ?>" target="_blank" class="w-full <?php echo $isDestaque ? 'bg-orange-500 hover:bg-orange-600 text-white shadow-md shadow-orange-500/25' : 'bg-slate-100 hover:bg-slate-200 text-slate-800'; ?> font-semibold py-3.5 rounded-xl text-center text-sm transition-colors flex items-center justify-center gap-2">
                        <i data-lucide="message-circle" class="w-4 h-4 text-emerald-400"></i> Assinar <?php echo $nomePlano; ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- SEÇÃO EXCLUSIVA DO CRIADOR: EMANUEL SILVA / BLOU SOLUÇÕES (FINAL DA PÁGINA) -->
    <section id="criador" class="py-24 bg-white border-t border-slate-200 relative overflow-hidden">
        <div class="absolute right-0 bottom-0 w-96 h-96 bg-orange-500/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white p-8 sm:p-12 rounded-3xl shadow-2xl relative overflow-hidden border border-slate-700">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-orange-500/20 rounded-full blur-2xl"></div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
                    
                    <!-- Foto do Criador -->
                    <div class="lg:col-span-4 flex justify-center">
                        <div class="relative group">
                            <div class="absolute -inset-1.5 bg-gradient-to-r from-orange-500 to-amber-500 rounded-3xl blur opacity-75 group-hover:opacity-100 transition duration-300"></div>
                            <div class="relative w-40 h-40 sm:w-48 sm:h-48 rounded-2xl overflow-hidden border-2 border-slate-700 bg-slate-800 shadow-xl">
                                <img src="uploads/emanuel.webp" alt="Emanuel Silva - Desenvolvedor" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" onerror="this.src='https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400&auto=format&fit=crop&q=60'">
                            </div>
                        </div>
                    </div>

                    <!-- Informações do Criador -->
                    <div class="lg:col-span-8 space-y-4 text-center lg:text-left">
                        <div class="inline-flex items-center gap-2 bg-orange-500/20 border border-orange-500/30 text-orange-400 px-3 py-1 rounded-full text-xs font-semibold">
                            <i data-lucide="code" class="w-3.5 h-3.5"></i> Desenvolvedor Oficial
                        </div>

                        <h3 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                            Emanuel Silva <span class="text-orange-400">| Blou Soluções</span>
                        </h3>

                        <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                            Especialista em desenvolvimento de sistemas web de alta performance. Com 24 anos e baseado em <span class="text-orange-400 font-semibold">Manaus/AM</span>, criei o <span class="text-white font-bold">DeliveryAm</span> para revolucionar o mercado de entregas local com tecnologia rápida, segura e sem intermediários.
                        </p>

                        <div class="pt-2 flex flex-wrap items-center justify-center lg:justify-start gap-4">
                            <a href="https://wa.me/5592981028812?text=Olá%20Emanuel,%20vi%20o%20sistema%20DeliveryAm%20e%20gostaria%20de%20um%20projeto%20personalizado!" target="_blank" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs sm:text-sm px-6 py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-600/30 flex items-center gap-2">
                                <i data-lucide="message-circle" class="w-4 h-4"></i> WhatsApp: (92) 98102-8812
                            </a>
                            <div class="flex items-center gap-2 text-xs text-slate-400 bg-slate-800/80 px-4 py-3 rounded-xl border border-slate-700">
                                <i data-lucide="map-pin" class="w-4 h-4 text-orange-400"></i> Manaus - Amazonas
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Rodapé (Footer) -->
    <footer class="bg-slate-900 text-slate-400 py-12 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
            
            <div class="flex items-center gap-3 justify-center">
                <div class="w-8 h-8 rounded-lg bg-orange-500 flex items-center justify-center text-white font-bold overflow-hidden">
                    <img src="uploads/blou.jpg" alt="Logo" class="w-full h-full object-cover" onerror="this.parentNode.innerHTML='<i data-lucide=\'store\' class=\'w-4 h-4\'></i>'; lucide.createIcons();">
                </div>
                <span class="font-bold text-white text-base">DeliveryAm <span class="text-xs text-orange-500 font-normal">por Blou Soluções</span></span>
            </div>

            <p class="text-xs text-slate-500">
                &copy; 2026 DeliveryAm. Todos os direitos reservados. Desenvolvido com excelência em Manaus.
            </p>

            <div class="flex items-center gap-6 text-sm">
                <a href="admin/painel.php" class="hover:text-white transition-colors">Área do Lojista</a>
                <a href="#planos" class="hover:text-white transition-colors">Planos</a>
                <a href="#criador" class="hover:text-white transition-colors">Contato</a>
            </div>

        </div>
    </footer>

    <!-- Inicializar Ícones Lucide -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>