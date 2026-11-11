-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 11/11/2026 às 06:02
-- Versão do servidor: 10.11.19-MariaDB
-- Versão do PHP: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `xedd5cftfcii3alb_saas`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `tipo` enum('master','loja') DEFAULT 'loja',
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `admins`
--

INSERT INTO `admins` (`id`, `loja_id`, `tipo`, `nome`, `email`, `senha`, `criado_em`) VALUES
(5, 0, 'master', 'Emanuel', 'emanuelkey2002@icloud.com', '$2y$12$6ApRTXEZNKPQHZgWiqxFM.ozrTIQLhTwp6MYqlXHE..zm6vGAROzy', '2026-11-02 23:03:54'),
(7, 6, 'loja', 'Weronica', 'weronica@radar.com', '$2y$10$gRiRk1RkD4C0XsVbJIjuFuh5FFhAv/gNN.iOTskYPtkS3CZxSNfmm', '2026-11-10 01:06:57');

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `ordem` int(11) DEFAULT 0,
  `destaque` tinyint(1) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id`, `loja_id`, `nome`, `ordem`, `destaque`) VALUES
(1, 1, 'Padaria', 0, 1),
(2, 1, 'Bebidas', 0, 0),
(3, 1, 'Açougue', 0, 0),
(6, 2, 'Açougue', 0, 0),
(5, 1, 'Frutas/Verduras', 0, 0),
(7, 3, 'Pães', 0, 1),
(8, 3, 'Salgados', 0, 0),
(9, 3, 'Bolos', 0, 0),
(10, 4, 'Açougue', 0, 0),
(11, 4, 'Padaria', 0, 0),
(12, 4, 'Bebidas', 0, 0),
(13, 4, 'Hortifrúti', 0, 0),
(14, 4, 'Mercearia', 0, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`id`, `loja_id`, `nome`, `telefone`, `senha`, `criado_em`) VALUES
(4, 1, 'Emanuele', '92995262308', NULL, '2026-10-29 14:42:07'),
(2, 1, 'Emanuel', '92981028812', NULL, '2026-10-29 04:42:59'),
(5, 1, 'Mikaelen de Castro', '92992937408', NULL, '2026-10-30 19:48:30'),
(6, 2, 'Emanuel Silva', '92981028812', NULL, '2026-11-02 18:49:34'),
(7, 4, 'Emanuele Silva', '92995262308', NULL, '2026-11-03 02:04:30'),
(8, 4, 'Emanuel Silva', '92981028812', NULL, '2026-11-03 06:27:14');

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes_sistema`
--

CREATE TABLE `configuracoes_sistema` (
  `chave` varchar(100) NOT NULL,
  `valor` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `configuracoes_sistema`
--

INSERT INTO `configuracoes_sistema` (`chave`, `valor`) VALUES
('dias_teste_padrao', '7');

-- --------------------------------------------------------

--
-- Estrutura para tabela `enderecos_clientes`
--

CREATE TABLE `enderecos_clientes` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `rua` varchar(150) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `bairro` varchar(100) NOT NULL,
  `complemento` varchar(150) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `principal` tinyint(1) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `enderecos_clientes`
--

INSERT INTO `enderecos_clientes` (`id`, `cliente_id`, `rua`, `numero`, `bairro`, `complemento`, `latitude`, `longitude`, `principal`) VALUES
(1, 2, 'Rua Marcos Pozzetti', '188', 'Gilberto Mestrinho', 'Casa azul', -3.06268744, -59.91599461, 1),
(3, 4, 'Rua Jordão', '188', 'Gilberto Mestrinho', 'Academia fitness new vida', NULL, NULL, 1),
(4, 5, 'Rua Aruanã', '11', 'Gilberto Mestrinho', '', -3.07477920, -59.92393490, 1),
(5, 6, 'Rua Letícia', '188', 'Gilberto Mestrinho', '', -3.06770135, -59.91975093, 1),
(6, 7, 'Rua Jordão', '188', 'Gilberto Mestrinho', 'Academia fitness new vida', -3.06807390, -59.91897930, 1),
(7, 8, 'Rua Letícia', '188', 'Gilberto Mestrinho', 'Casa azul', -3.06770135, -59.91990477, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `entregadores`
--

CREATE TABLE `entregadores` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `veiculo` varchar(100) DEFAULT NULL,
  `placa` varchar(20) DEFAULT NULL,
  `status_entregador` enum('disponivel','em_rota','offline') DEFAULT 'disponivel',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `entregadores`
--

INSERT INTO `entregadores` (`id`, `loja_id`, `nome`, `telefone`, `senha`, `veiculo`, `placa`, `status_entregador`, `latitude`, `longitude`) VALUES
(1, 1, 'Emanuel Silva', '92981028812', '$2y$12$SyhVV.vZQXe3JLPFVOh5BO5knpQKf9n9QEIufcvMqXYnpWpW1W43C', 'XRE 190', 'JTA-T89', 'offline', -3.06803551, -59.91945300),
(2, 2, 'TESTE', '92981028812', '$2y$12$VAtekqkvsNb4ailjRwUoAu8nhKzvuvRrGLwtvvDRq/.0S1LEYL95q', 'XRE 190', 'JTA-T89', 'disponivel', NULL, NULL),
(3, 4, 'Emanuel', '92981028812', '$2y$10$MHJxD4TtD6QYB.bm5nVCzO6mWtdx81v043Zl1rAm5BDkkZiFf56PK', '', '', 'disponivel', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_pedido`
--

CREATE TABLE `itens_pedido` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `quantidade` decimal(10,3) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `itens_pedido`
--

INSERT INTO `itens_pedido` (`id`, `pedido_id`, `produto_id`, `quantidade`, `preco_unitario`, `subtotal`) VALUES
(75, 55, 11, 1.000, 4.75, 4.75),
(74, 55, 9, 1.000, 5.00, 5.00),
(73, 55, 15, 2.000, 32.00, 64.00),
(72, 54, 14, 1.000, 5.50, 5.50),
(71, 53, 13, 1.000, 7.75, 7.75),
(70, 53, 15, 1.000, 32.00, 32.00),
(69, 53, 14, 2.000, 5.50, 11.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `lojas`
--

CREATE TABLE `lojas` (
  `id` int(11) NOT NULL,
  `nome_loja` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) NOT NULL,
  `endereco` text NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `tipo_operacao` enum('ambas','fisica','online') DEFAULT 'ambas',
  `forcar_fechado` tinyint(1) DEFAULT 0,
  `horario_abertura` time DEFAULT '06:00:00',
  `horario_fechamento` time DEFAULT '22:00:00',
  `pagamento_dinheiro` tinyint(1) DEFAULT 1,
  `pagamento_debito` tinyint(1) DEFAULT 1,
  `pagamento_credito` tinyint(1) DEFAULT 1,
  `pagamento_pix` tinyint(1) DEFAULT 1,
  `tipo_chave_pix` varchar(20) DEFAULT 'CNPJ',
  `chave_pix` varchar(150) DEFAULT NULL,
  `pix_copia_cola` text DEFAULT NULL,
  `plano_id` int(11) DEFAULT 1,
  `data_vencimento` date DEFAULT NULL,
  `status_loja` enum('ativo','inativo','pendente','suspenso') DEFAULT 'ativo',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `ordenacao_produtos` varchar(50) DEFAULT 'az',
  `horarios_funcionamento` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `lojas`
--

INSERT INTO `lojas` (`id`, `nome_loja`, `slug`, `cnpj`, `whatsapp`, `endereco`, `latitude`, `longitude`, `logo`, `tipo_operacao`, `forcar_fechado`, `horario_abertura`, `horario_fechamento`, `pagamento_dinheiro`, `pagamento_debito`, `pagamento_credito`, `pagamento_pix`, `tipo_chave_pix`, `chave_pix`, `pix_copia_cola`, `plano_id`, `data_vencimento`, `status_loja`, `criado_em`, `ordenacao_produtos`, `horarios_funcionamento`) VALUES
(6, 'Weronica Select Naturais', 'Select_Naturais', NULL, '92993446887', 'Rua Tabatinga', NULL, NULL, NULL, 'ambas', 0, '06:00:00', '22:00:00', 1, 1, 1, 1, 'CNPJ', NULL, NULL, NULL, '2026-12-09', 'ativo', '2026-11-10 01:06:03', 'az', '{\"segunda\":{\"ativo\":1,\"abertura\":\"08:00\",\"fechamento\":\"21:30\",\"tem_almoco\":0,\"almoco_inicio\":\"12:00\",\"almoco_fim\":\"13:00\"},\"terca\":{\"ativo\":1,\"abertura\":\"08:00\",\"fechamento\":\"21:30\",\"tem_almoco\":0,\"almoco_inicio\":\"12:00\",\"almoco_fim\":\"13:00\"},\"quarta\":{\"ativo\":1,\"abertura\":\"08:00\",\"fechamento\":\"21:30\",\"tem_almoco\":0,\"almoco_inicio\":\"12:00\",\"almoco_fim\":\"13:00\"},\"quinta\":{\"ativo\":1,\"abertura\":\"08:00\",\"fechamento\":\"21:30\",\"tem_almoco\":0,\"almoco_inicio\":\"12:00\",\"almoco_fim\":\"13:00\"},\"sexta\":{\"ativo\":1,\"abertura\":\"08:00\",\"fechamento\":\"21:30\",\"tem_almoco\":0,\"almoco_inicio\":\"12:00\",\"almoco_fim\":\"13:00\"},\"sabado\":{\"ativo\":1,\"abertura\":\"08:00\",\"fechamento\":\"20:00\",\"tem_almoco\":0,\"almoco_inicio\":\"12:00\",\"almoco_fim\":\"13:00\"},\"domingo\":{\"ativo\":1,\"abertura\":\"08:00\",\"fechamento\":\"12:00\",\"tem_almoco\":0,\"almoco_inicio\":\"12:00\",\"almoco_fim\":\"13:00\"}}');

-- --------------------------------------------------------

--
-- Estrutura para tabela `lojas_pix`
--

CREATE TABLE `lojas_pix` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `pix_copia_cola` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mp`
--

CREATE TABLE `mp` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `access_token` text DEFAULT NULL,
  `public_key` text DEFAULT NULL,
  `webhook_secret` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 0,
  `atualizado_em` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `mp`
--

INSERT INTO `mp` (`id`, `loja_id`, `access_token`, `public_key`, `webhook_secret`, `ativo`, `atualizado_em`) VALUES
(1, 4, 'APP_USR-4106784606640868-110302-ce8d5e5bcccea27582a5b79b6674b8ed-1161338151', 'APP_USR-e450a303-975f-42b6-a80f-9ca8ad8d2ee1', NULL, 1, '2026-11-05 00:41:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `entregador_id` int(11) DEFAULT NULL,
  `codigo_pedido` varchar(20) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `taxa_entrega` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `forma_pagamento` enum('pix','dinheiro','cartao') NOT NULL,
  `troco` varchar(100) DEFAULT NULL,
  `status_pagamento` enum('pendente','aprovado','recebido') DEFAULT 'pendente',
  `status_pedido` enum('pendente','preparando','em_rota','entregue','cancelado') DEFAULT 'pendente',
  `status_entrega` enum('disponivel','aceito','em_rota','entregue') DEFAULT 'disponivel',
  `endereco_entrega` text NOT NULL,
  `cep` varchar(20) DEFAULT NULL,
  `rua` varchar(150) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `complemento` varchar(150) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `distancia_km` decimal(5,2) DEFAULT 0.00,
  `tempo_estimado_min` int(11) DEFAULT 0,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `txid_pix` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `pedidos`
--

INSERT INTO `pedidos` (`id`, `loja_id`, `cliente_id`, `entregador_id`, `codigo_pedido`, `subtotal`, `taxa_entrega`, `total`, `forma_pagamento`, `troco`, `status_pagamento`, `status_pedido`, `status_entrega`, `endereco_entrega`, `cep`, `rua`, `numero`, `bairro`, `complemento`, `latitude`, `longitude`, `distancia_km`, `tempo_estimado_min`, `criado_em`, `txid_pix`) VALUES
(42, 1, 2, NULL, '2783', 19.75, 1.50, 21.25, 'cartao', NULL, 'pendente', 'entregue', 'disponivel', 'Rua Jordão, 188 - Gilberto Mestrinho - Comp: Casa azul', '', 'Rua Jordão', '188', 'Gilberto Mestrinho', 'Casa azul', NULL, NULL, 0.17, 9, '2026-10-30 19:33:38', NULL),
(41, 1, 2, NULL, '7522', 147.00, 1.50, 148.50, 'dinheiro', '200', 'pendente', 'entregue', 'disponivel', 'Rua Jordão, 188 - Gilberto Mestrinho - Comp: Casa azul', '', 'Rua Jordão', '188', 'Gilberto Mestrinho', 'Casa azul', NULL, NULL, 0.17, 9, '2026-10-30 18:58:12', NULL),
(40, 1, 2, NULL, '6545', 117.60, 1.50, 119.10, 'pix', NULL, 'pendente', 'entregue', 'disponivel', 'Rua Jordão, 188 - Gilberto Mestrinho - Comp: Casa azul', '', 'Rua Jordão', '188', 'Gilberto Mestrinho', 'Casa azul', NULL, NULL, 0.17, 9, '2026-10-30 18:48:20', NULL),
(43, 1, 5, 1, '2292', 3.25, 1.50, 4.75, 'pix', NULL, 'pendente', 'entregue', 'entregue', 'Rua Aruanã, 11 - Gilberto Mestrinho', '', 'Rua Aruanã', '11', 'Gilberto Mestrinho', '', NULL, NULL, 0.83, 10, '2026-10-30 19:48:58', NULL),
(44, 1, 2, 1, '7926', 98.00, 1.50, 99.50, 'pix', NULL, 'pendente', 'entregue', 'entregue', 'Rua Jordão, 188 - Gilberto Mestrinho - Comp: Casa azul', '', 'Rua Jordão', '188', 'Gilberto Mestrinho', 'Casa azul', NULL, NULL, 0.17, 9, '2026-10-30 20:20:12', NULL),
(45, 1, 2, 1, '8712', 12.00, 1.50, 13.50, 'pix', NULL, 'pendente', 'entregue', 'entregue', 'Rua Violeta Bayma, 188 - Gilberto Mestrinho - Comp: Casa azul', '', 'Rua Violeta Bayma', '188', 'Gilberto Mestrinho', 'Casa azul', NULL, NULL, 0.89, 11, '2026-10-30 20:27:41', NULL),
(46, 1, 2, 1, '1505', 98.00, 2.50, 100.50, 'pix', NULL, 'pendente', 'entregue', 'entregue', 'Avenida Cosme Ferreira, 188 - São José Operário - Comp: Casa azul', '', 'Avenida Cosme Ferreira', '188', 'São José Operário', 'Casa azul', NULL, NULL, 2.49, 15, '2026-10-30 20:35:41', NULL),
(47, 1, 2, 1, '5949', 98.00, 2.50, 100.50, 'pix', NULL, 'pendente', 'entregue', 'aceito', 'Avenida Cosme Ferreira, 188 - Zumbi dos Palmares - Comp: Casa azul', '', 'Avenida Cosme Ferreira', '188', 'Zumbi dos Palmares', 'Casa azul', NULL, NULL, 2.50, 15, '2026-10-30 20:59:00', NULL),
(48, 1, 2, 1, '3246', 98.00, 2.50, 100.50, 'dinheiro', '', 'pendente', 'entregue', 'aceito', 'Rua Bom Intento, 188 - São José Operário - Comp: Casa azul', '', 'Rua Bom Intento', '188', 'São José Operário', 'Casa azul', -3.06607290, -59.93906506, 2.27, 15, '2026-10-30 21:02:48', NULL),
(49, 1, 2, 1, '4974', 98.00, 1.50, 99.50, 'pix', NULL, 'pendente', 'entregue', 'entregue', 'Rua Marcos Pozzetti, 188 - Gilberto Mestrinho - Comp: Casa azul', '', 'Rua Marcos Pozzetti', '188', 'Gilberto Mestrinho', 'Casa azul', -3.06268744, -59.91599461, 0.80, 10, '2026-11-02 16:06:03', NULL),
(50, 2, 6, 2, '7013', 38.00, 1.50, 39.50, 'dinheiro', '', 'pendente', 'entregue', 'aceito', 'Rua Letícia, 188 - Gilberto Mestrinho', '', 'Rua Letícia', '188', 'Gilberto Mestrinho', '', -3.06729852, -59.91947640, 0.40, 9, '2026-11-02 18:49:40', NULL),
(51, 2, 6, 2, '5229', 57.00, 1.50, 58.50, 'pix', NULL, 'pendente', 'entregue', 'aceito', 'Rua Letícia, 188 - Gilberto Mestrinho', '', 'Rua Letícia', '188', 'Gilberto Mestrinho', '', -3.06770135, -59.91975093, 0.36, 9, '2026-11-02 19:01:43', NULL),
(52, 2, 6, 2, '9596', 76.00, 1.50, 77.50, 'pix', NULL, 'pendente', 'entregue', 'entregue', 'Rua Letícia, 188 - Gilberto Mestrinho', '', 'Rua Letícia', '188', 'Gilberto Mestrinho', '', -3.06770135, -59.91975093, 0.36, 9, '2026-11-02 19:12:09', NULL),
(53, 4, 7, NULL, '7482', 50.75, 1.50, 52.25, 'dinheiro', '30', 'pendente', 'entregue', 'disponivel', 'Rua Jordão, 188 - Gilberto Mestrinho (CEP: 69006313) - Comp: Academia fitness new vida', '69006313', 'Rua Jordão', '188', 'Gilberto Mestrinho', 'Academia fitness new vida', -3.06807390, -59.91897930, 0.14, 8, '2026-11-03 02:04:52', NULL),
(54, 4, 7, NULL, '3595', 5.50, 1.50, 7.00, 'dinheiro', '50', 'pendente', 'entregue', 'disponivel', 'Rua Jordão, 188 - Gilberto Mestrinho (CEP: 69006313) - Comp: Academia fitness new vida', '69006313', 'Rua Jordão', '188', 'Gilberto Mestrinho', 'Academia fitness new vida', -3.06807390, -59.91897930, 0.14, 8, '2026-11-03 02:13:00', NULL),
(55, 4, 7, NULL, '2835', 73.75, 1.50, 75.25, 'cartao', NULL, 'pendente', 'entregue', 'disponivel', 'Rua Jordão, 188 - Gilberto Mestrinho (CEP: 69006313) - Comp: Academia fitness new vida', '69006313', 'Rua Jordão', '188', 'Gilberto Mestrinho', 'Academia fitness new vida', -3.06807390, -59.91897930, 0.14, 8, '2026-11-03 02:17:32', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `planos`
--

CREATE TABLE `planos` (
  `id` int(11) NOT NULL,
  `nome_plano` varchar(100) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `ciclo` enum('mensal','anual') DEFAULT 'mensal',
  `limite_produtos` int(11) DEFAULT 100,
  `descricao` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `planos`
--

INSERT INTO `planos` (`id`, `nome_plano`, `valor`, `ciclo`, `limite_produtos`, `descricao`) VALUES
(1, 'Mensal', 50.00, 'mensal', 100, ''),
(2, 'Vitalicio', 500.00, 'anual', 1000000, '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `nome` varchar(150) NOT NULL,
  `codigo_barras` varchar(50) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `estoque` decimal(10,3) DEFAULT 0.000,
  `estoque_minimo` decimal(10,3) DEFAULT 5.000,
  `foto` varchar(255) DEFAULT NULL,
  `status_produto` enum('disponivel','esgotado','inativo') DEFAULT 'disponivel',
  `unidade` varchar(10) DEFAULT 'un'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `loja_id`, `categoria_id`, `nome`, `codigo_barras`, `descricao`, `preco`, `estoque`, `estoque_minimo`, `foto`, `status_produto`, `unidade`) VALUES
(1, 1, 1, 'Pão Francês', '', '', 0.50, 10.000, 5.000, 'uploads/produto_1793244789_4665.jpg', 'disponivel', 'un'),
(3, 1, 2, 'Coca-Cola Zero', '', '2L', 12.00, 1.000, 5.000, 'uploads/produto_1793261462_9342.webp', 'disponivel', 'un'),
(2, 1, 1, 'Pão de Queijo', '', '60g', 0.65, 100.000, 5.000, 'uploads/produto_1793254750_2672.jpg', 'disponivel', 'un'),
(4, 1, 3, 'Picanha', '', '98 reais o Quilo', 98.00, 100.000, 5.000, 'uploads/produto_1793385761_2244.jpg', 'disponivel', 'kg'),
(5, 2, 6, 'Fraldinha', '', '', 38.00, 134.000, 5.000, 'uploads/produto_1793645312_3589.jpg', 'disponivel', 'kg'),
(6, 3, 7, 'Pão Massa Grossa', '', '', 0.50, 100.000, 5.000, 'uploads/produto_1793647803_9696.jpg', 'disponivel', 'un'),
(7, 4, 12, 'Cola-Cola', '', '350ml', 5.00, 100.000, 5.000, 'uploads/produto_1793669937_9900.png', 'disponivel', 'un'),
(8, 4, 12, 'Cola-Cola', '', '2L', 12.00, 100.000, 5.000, 'uploads/produto_1793670029_2635.webp', 'disponivel', 'un'),
(9, 4, 12, 'Pepsi', '', '350ml', 5.00, 100.000, 5.000, 'uploads/produto_1793670137_8457.webp', 'disponivel', 'un'),
(10, 4, 12, 'Pepsi', '', '2L', 11.00, 100.000, 5.000, 'uploads/produto_1793670168_5285.webp', 'disponivel', 'un'),
(11, 4, 14, 'Arroz', '', '1KG', 4.75, 100.000, 5.000, 'uploads/produto_1793670682_7358.webp', 'disponivel', 'un'),
(12, 4, 14, 'Café 3 Corações', '', '200g', 8.00, 0.000, 5.000, 'uploads/produto_1793670720_8487.png', 'disponivel', 'un'),
(13, 4, 14, 'Feijão Kicaldo', '', '1KG', 7.75, 10.000, 5.000, 'uploads/produto_1793670766_5619.jpg', 'disponivel', 'un'),
(14, 4, 14, 'Açucar', '', '1KG', 5.50, 10.000, 5.000, 'uploads/produto_1793670880_7233.avif', 'disponivel', 'un'),
(15, 4, 10, 'Coxão Molhe', '', '', 32.00, 100.000, 5.000, 'uploads/produto_1793671073_5515.jpg', 'disponivel', 'kg'),
(16, 4, 10, 'Alcatra', '', '', 35.25, 100.000, 5.000, 'uploads/produto_1793671124_3624.webp', 'disponivel', 'kg');

-- --------------------------------------------------------

--
-- Estrutura para tabela `sistema_logs`
--

CREATE TABLE `sistema_logs` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `nivel` varchar(20) DEFAULT 'INFO',
  `mensagem` text NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `sistema_logs`
--

INSERT INTO `sistema_logs` (`id`, `loja_id`, `nivel`, `mensagem`, `criado_em`) VALUES
(1, 1, 'SUCESSO', 'Campo \'forcar_fechado\' atualizado para: 0', '2026-10-31 00:42:27'),
(2, 1, 'SUCESSO', 'Campo \'forcar_fechado\' atualizado para: 1', '2026-10-31 00:42:28'),
(3, 1, 'DB_UPDATE', 'Tabela \'lojas\' | Campo \'forcar_fechado\' alterado para: 0', '2026-10-31 00:47:06'),
(4, 1, 'DB_UPDATE', 'Tabela \'lojas\' | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 00:47:07'),
(5, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 0', '2026-10-31 00:52:20'),
(6, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 00:52:28'),
(7, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 00:52:29'),
(8, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 0', '2026-10-31 00:52:32'),
(9, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 00:52:32'),
(10, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 0', '2026-10-31 00:52:40'),
(11, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 00:52:41'),
(12, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 0', '2026-10-31 00:59:51'),
(13, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 00:59:52'),
(14, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 0', '2026-10-31 01:00:03'),
(15, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 01:00:04'),
(16, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 01:00:06'),
(17, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 01:00:07'),
(18, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 0', '2026-10-31 01:00:08'),
(19, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 01:00:08'),
(20, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 01:29:46'),
(21, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 01:29:47'),
(22, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 0', '2026-10-31 01:29:51'),
(23, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 1', '2026-10-31 01:29:52'),
(24, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'forcar_fechado\' alterado para: 0', '2026-11-02 16:04:53'),
(25, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'latitude\' alterado para: -3.070398993755019', '2026-11-02 18:25:03'),
(26, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'longitude\' alterado para: -59.91897609117826', '2026-11-02 18:25:03'),
(27, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'endereco\' alterado para: Rua Magalhães, Vila Nova Vitória, Gilberto Mestr...', '2026-11-02 18:25:04'),
(28, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'endereco\' alterado para: Rua Nova Vitória, Vila Nova Vitória, Gilberto Me...', '2026-11-02 18:25:54'),
(29, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'latitude\' alterado para: -3.0708489579117053', '2026-11-02 18:25:54'),
(30, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'longitude\' alterado para: -59.91896831019387', '2026-11-02 18:25:54'),
(31, 1, 'DB_UPDATE', 'Loja atualizada | Campo \'endereco\' alterado para: Rua Nova Vitória, Vila Nova Vitória, Gilberto Me...', '2026-11-02 18:26:07'),
(32, 2, 'DB_UPDATE', 'Loja ID 2 atualizada | Campo \'longitude\' alterado para: -59.91898889212994', '2026-11-02 18:28:37'),
(33, 2, 'DB_UPDATE', 'Loja ID 2 atualizada | Campo \'latitude\' alterado para: -3.0708489579117053', '2026-11-02 18:28:37'),
(34, 2, 'DB_UPDATE', 'Loja ID 2 atualizada | Campo \'endereco\' alterado para: Rua Nova Vitória, Vila Nova Vitória, Gilberto Me...', '2026-11-02 18:28:37'),
(35, 2, 'DB_UPDATE', 'Loja ID 2 atualizada | Campo \'pagamento_dinheiro\' alterado para: 0', '2026-11-02 19:00:45'),
(36, 2, 'DB_UPDATE', 'Loja ID 2 atualizada | Campo \'pagamento_dinheiro\' alterado para: 1', '2026-11-02 19:00:46'),
(37, 2, 'DB_UPDATE', 'Loja ID 2 atualizada | Campo \'pagamento_credito\' alterado para: 0', '2026-11-02 19:00:48'),
(38, 2, 'DB_UPDATE', 'Loja ID 2 atualizada | Campo \'pagamento_credito\' alterado para: 1', '2026-11-02 19:00:49'),
(39, 2, 'DB_UPDATE', 'Loja ID 2 atualizada | Campo \'pagamento_pix\' alterado para: 0', '2026-11-02 19:00:51'),
(40, 2, 'DB_UPDATE', 'Loja ID 2 atualizada | Campo \'pagamento_pix\' alterado para: 1', '2026-11-02 19:00:52'),
(41, 2, 'DB_UPDATE', 'Loja ID 2 atualizada | Campo \'pagamento_debito\' alterado para: 0', '2026-11-02 19:00:54'),
(42, 2, 'DB_UPDATE', 'Loja ID 2 atualizada | Campo \'pagamento_debito\' alterado para: 1', '2026-11-02 19:00:55'),
(43, 3, 'DB_UPDATE', 'Loja ID 3 atualizada | Campo \'latitude\' alterado para: -3.069531205203297', '2026-11-02 19:26:10'),
(44, 3, 'DB_UPDATE', 'Loja ID 3 atualizada | Campo \'longitude\' alterado para: -59.91905262385928', '2026-11-02 19:26:11'),
(45, 3, 'DB_UPDATE', 'Loja ID 3 atualizada | Campo \'endereco\' alterado para: Rua Magalhães, Vila Nova Vitória, Gilberto Mestr...', '2026-11-02 19:26:11'),
(146, 6, 'DB_CLEAR', 'Histórico de logs limpo para a loja \'Weronica Select Naturais\' (ID 6).', '2026-11-10 15:57:50'),
(112, 4, 'DB_UPDATE', 'Loja \'M&N\' (ID 4) atualizada | Campo \'chave_pix\' alterado.', '2026-11-10 00:54:17'),
(111, 4, 'DB_UPDATE', 'Loja \'M&N\' (ID 4) atualizada | Campo \'chave_pix\' alterado.', '2026-11-10 00:54:15'),
(110, 4, 'DB_UPDATE', 'Loja \'M&N\' (ID 4) atualizada | Campo \'chave_pix\' alterado.', '2026-11-10 00:54:14'),
(97, 5, 'DB_UPLOAD', 'Logo da Loja ID 5 atualizada: uploads/logo_5_1794269755.webp', '2026-11-10 00:15:55'),
(98, 5, 'DB_UPDATE', 'Loja ID 5 atualizada | Campo \'latitude\' alterado.', '2026-11-10 00:16:58'),
(99, 5, 'DB_UPDATE', 'Loja ID 5 atualizada | Campo \'longitude\' alterado.', '2026-11-10 00:16:58'),
(100, 5, 'DB_UPDATE', 'Loja ID 5 atualizada | Campo \'endereco\' alterado.', '2026-11-10 00:16:58'),
(101, 5, 'DB_UPDATE', 'Loja ID 5 atualizada | Campo \'horarios_funcionamento\' alterado.', '2026-11-10 00:17:17'),
(109, 4, 'DB_UPDATE', 'Loja \'M&N\' (ID 4) atualizada | Campo \'chave_pix\' alterado.', '2026-11-10 00:54:13'),
(108, 4, 'DB_UPDATE', 'Loja \'M&N\' (ID 4) atualizada | Campo \'tipo_chave_pix\' alterado.', '2026-11-10 00:54:11'),
(107, 4, 'DB_UPDATE', 'Loja \'M&N\' (ID 4) atualizada | Campo \'pagamento_debito\' alterado.', '2026-11-10 00:47:43'),
(106, 4, 'DB_CLEAR', 'Histórico de logs limpo para a loja \'M&N\' (ID 4).', '2026-11-10 00:47:31');

-- --------------------------------------------------------

--
-- Estrutura para tabela `transacoes`
--

CREATE TABLE `transacoes` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `pedido_id` int(11) DEFAULT NULL,
  `tipo` enum('entrada','saida') NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `forma_pagamento` varchar(50) NOT NULL,
  `status` varchar(50) DEFAULT 'concluido',
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `transacoes`
--

INSERT INTO `transacoes` (`id`, `loja_id`, `pedido_id`, `tipo`, `descricao`, `valor`, `forma_pagamento`, `status`, `criado_em`) VALUES
(55, 4, 55, 'entrada', 'Pedido #2835', 75.25, 'cartao', 'concluido', '2026-11-03 02:17:32'),
(54, 4, 54, 'entrada', 'Pedido #3595', 7.00, 'dinheiro', 'concluido', '2026-11-03 02:13:00'),
(53, 4, 53, 'entrada', 'Pedido #7482', 52.25, 'dinheiro', 'concluido', '2026-11-03 02:04:52');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `loja_id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel` varchar(50) DEFAULT 'atendente',
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `loja_id`, `nome`, `email`, `senha`, `nivel`, `status`, `criado_em`) VALUES
(1, 1, 'Emanuel', 'Clashemanuel2002@gmail.com', '$2y$12$mgiizWYkryY08TgIfgBDfukpTUqfLbZ3USOfb.nY8ZDOI38dk3ycK', 'gerente', 'ativo', '2026-11-02 17:50:14');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `loja_id` (`loja_id`);

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`),
  ADD KEY `telefone` (`telefone`);

--
-- Índices de tabela `configuracoes_sistema`
--
ALTER TABLE `configuracoes_sistema`
  ADD PRIMARY KEY (`chave`);

--
-- Índices de tabela `enderecos_clientes`
--
ALTER TABLE `enderecos_clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Índices de tabela `entregadores`
--
ALTER TABLE `entregadores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`);

--
-- Índices de tabela `itens_pedido`
--
ALTER TABLE `itens_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `lojas`
--
ALTER TABLE `lojas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `lojas_pix`
--
ALTER TABLE `lojas_pix`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`);

--
-- Índices de tabela `mp`
--
ALTER TABLE `mp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `loja_id` (`loja_id`);

--
-- Índices de tabela `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `entregador_id` (`entregador_id`);

--
-- Índices de tabela `planos`
--
ALTER TABLE `planos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`),
  ADD KEY `categoria_id` (`categoria_id`);

--
-- Índices de tabela `sistema_logs`
--
ALTER TABLE `sistema_logs`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `transacoes`
--
ALTER TABLE `transacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loja_id` (`loja_id`),
  ADD KEY `pedido_id` (`pedido_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `loja_id` (`loja_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `enderecos_clientes`
--
ALTER TABLE `enderecos_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `entregadores`
--
ALTER TABLE `entregadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `itens_pedido`
--
ALTER TABLE `itens_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT de tabela `lojas`
--
ALTER TABLE `lojas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `lojas_pix`
--
ALTER TABLE `lojas_pix`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mp`
--
ALTER TABLE `mp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de tabela `planos`
--
ALTER TABLE `planos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `sistema_logs`
--
ALTER TABLE `sistema_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT de tabela `transacoes`
--
ALTER TABLE `transacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
