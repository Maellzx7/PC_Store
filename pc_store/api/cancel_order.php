<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID do pedido não informado']);
    exit();
}

$order_id = (int)$input['order_id'];
$user_id = $_SESSION['user_id'];

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();
    
    // Verificar se o pedido pertence ao usuário e pode ser cancelado
    $query = "SELECT id, status FROM orders WHERE id = :order_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Pedido não encontrado');
    }
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar se o pedido pode ser cancelado
    if (!in_array($order['status'], ['pending', 'processing'])) {
        throw new Exception('Este pedido não pode ser cancelado');
    }
    
    // Buscar itens do pedido para restaurar o estoque
    $query = "SELECT oi.product_id, oi.quantity 
              FROM order_items oi 
              WHERE oi.order_id = :order_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Restaurar estoque dos produtos
    foreach ($order_items as $item) {
        $query = "UPDATE products 
                  SET stock_quantity = stock_quantity + :quantity 
                  WHERE id = :product_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':quantity', $item['quantity']);
        $stmt->bindParam(':product_id', $item['product_id']);
        $stmt->execute();
    }
    
    // Cancelar o pedido
    $query = "UPDATE orders SET status = 'cancelled' WHERE id = :order_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    
    // Log da ação (se tabela de logs existir)
    try {
        $query = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                  VALUES (:user_id, 'cancel_order', :description, :ip)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $description = "Pedido #" . sprintf('%06d', $order_id) . " cancelado pelo usuário";
        $stmt->bindParam(':description', $description);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bindParam(':ip', $ip);
        $stmt->execute();
    } catch (Exception $e) {
        // Ignorar erro de log, não é crítico
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Pedido cancelado com sucesso',
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Erro ao cancelar pedido: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>