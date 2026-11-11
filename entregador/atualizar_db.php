<?php
// atualizar_db.php
require_once 'admin/config.php';

try {
    // Função para verificar se uma coluna existe na tabela
    function colunaExiste($pdo, $tabela, $coluna) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tabela` LIKE ?");
        $stmt->execute([$coluna]);
        return $stmt->fetch() !== false;
    }

    $tabela = 'pedidos';
    $colunasParaAdicionar = [
        "`entregador_id` INT(11) DEFAULT NULL",
        "`status_entrega` ENUM('disponivel','aceito','em_rota','entregue') DEFAULT 'disponivel'",
        "`latitude` DECIMAL(10,8) DEFAULT NULL",
        "`longitude` DECIMAL(11,8) DEFAULT NULL",
        "`cep` VARCHAR(20) DEFAULT NULL",
        "`rua` VARCHAR(150) DEFAULT NULL",
        "`numero` VARCHAR(20) DEFAULT NULL",
        "`bairro` VARCHAR(100) DEFAULT NULL",
        "`complemento` VARCHAR(150) DEFAULT NULL",
        "`distancia_km` DECIMAL(5,2) DEFAULT 0.00",
        "`tempo_estimado_min` INT(11) DEFAULT 0"
    ];

    $adicionadas = 0;
    foreach ($colunasParaAdicionar as $definicao) {
        // Extrai o nome da coluna da definição
        preg_match('/`([^`]+)`/', $definicao, $matches);
        $nomeColuna = $matches[1] ?? '';

        if ($nomeColuna && !colunaExiste($pdo, $tabela, $nomeColuna)) {
            $sql = "ALTER TABLE `$tabela` ADD COLUMN $definicao";
            $pdo->exec($sql);
            echo "<p style='color:green;'>Coluna <b>{$nomeColuna}</b> adicionada com sucesso!</p>";
            $adicionadas++;
        } else {
            echo "<p style='color:gray;'>Coluna <b>{$nomeColuna}</b> já existe. Pulando...</p>";
        }
    }

    echo "<h3>Atualização concluída! Total de colunas novas adicionadas: {$adicionadas}.</h3>";
    echo '<p><a href="entregador/index.php">Ir para o App do Entregador</a></p>';

} catch (Exception $e) {
    echo "<h3 style='color:red;'>Erro ao atualizar o banco de dados:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>