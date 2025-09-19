<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['updated' => false, 'message' => 'Usuário não logado']);
    exit();
}

$user_id = $_SESSION['user_id'];
$database = new Database();
$db = $database->getConnection();

try {
    // Verificar se há pedidos atualizados nos últimos 5 minutos
    $query = "SELECT COUNT(*) as count 
              FROM orders 
              WHERE user_id = :user_id 
              AND updated_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
              AND updated_at != created_at";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_updates = $result['count'] > 0;
    
    // Se houver atualizações, buscar detalhes
    $updated_orders = [];
    if ($has_updates) {
        $query = "SELECT id, status, updated_at 
                  FROM orders 
                  WHERE user_id = :user_id 
                  AND updated_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                  AND updated_at != created_at
                  ORDER BY updated_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $updated_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'updated' => $has_updates,
        'count' => $result['count'],
        'orders' => $updated_orders
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao verificar atualizações: " . $e->getMessage());
    
    echo json_encode([
        'updated' => false,
        'message' => 'Erro interno do servidor'
    ]);
}
?>