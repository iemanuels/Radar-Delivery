<?php
// mercado/api-localizacao.php
require_once '../admin/config.php';

header('Content-Type: application/json');

$pedido_id = isset($_GET['pedido_id']) ? intval($_GET['pedido_id']) : 0;

if ($pedido_id <= 0) {
    echo json_encode(['status' => 'desconhecido', 'temEntregador' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, e.nome as nome_entregador, e.telefone as telefone_entregador, e.veiculo, e.placa, e.latitude as ent_lat, e.longitude as ent_lon 
        FROM pedidos p
        LEFT JOIN entregadores e ON p.entregador_id = e.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode(['status' => 'desconhecido', 'temEntregador' => false]);
        exit;
    }

    $status = $pedido['status_pedido'] ?? 'pendente';

    // Padrões visuais
    $tituloStatus = "Pedido Recebido";
    $descStatus = "Aguardando o mercado confirmar e iniciar a separação.";
    $iconeStatus = "clock";
    $bgCard = "bg-amber-50 border-amber-200 text-amber-600";

    if ($status === 'preparando') {
        $tituloStatus = "Separando os produtos!";
        $descStatus = "O mercado já aceitou e está embalando seus itens.";
        $iconeStatus = "package";
        $bgCard = "bg-orange-50 border-orange-200 text-orange-600";
    } elseif ($status === 'em_rota') {
        $tituloStatus = "Saiu para Entrega!";
        $descStatus = "O entregador está a caminho do seu endereço.";
        $iconeStatus = "bike";
        $bgCard = "bg-blue-50 border-blue-200 text-blue-600";
    } elseif ($status === 'entregue') {
        $tituloStatus = "Pedido Entregue!";
        $descStatus = "Agradecemos a preferência! Volte sempre.";
        $iconeStatus = "check-circle-2";
        $bgCard = "bg-emerald-50 border-emerald-200 text-emerald-600";
    } elseif ($status === 'cancelado') {
        $tituloStatus = "Pedido Cancelado";
        $descStatus = "Este pedido foi cancelado.";
        $iconeStatus = "x-circle";
        $bgCard = "bg-rose-50 border-rose-200 text-rose-600";
    }

    echo json_encode([
        'status' => $status,
        'tituloStatus' => $tituloStatus,
        'descStatus' => $descStatus,
        'iconeStatus' => $iconeStatus,
        'bgCard' => $bgCard,
        'temEntregador' => !empty($pedido['nome_entregador']),
        'nomeEntregador' => $pedido['nome_entregador'] ?? '',
        'veiculoEntregador' => $pedido['veiculo'] ?? 'Moto',
        'placaEntregador' => $pedido['placa'] ?? 'N/D',
        'telEntregador' => preg_replace('/[^0-9]/', '', $pedido['telefone_entregador'] ?? ''),
        'entregadorLat' => floatval($pedido['ent_lat'] ?? 0),
        'entregadorLon' => floatval($pedido['ent_lon'] ?? 0)
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'desconhecido', 'erro' => $e->getMessage()]);
}
exit;