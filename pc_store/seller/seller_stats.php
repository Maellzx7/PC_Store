<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isSeller()) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

try {
    $stats = [];

    // Total de produtos do vendedor
    $query = "SELECT COUNT(*) as count FROM products WHERE seller_id = :seller_id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':seller_id', $seller_id);
    $stmt->execute();
    $stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total de vendas
    $query = "SELECT COUNT(DISTINCT o.id) as count 
              FROM orders o 
              INNER JOIN order_items oi ON o.id = oi.order_id 
              INNER JOIN products p ON oi.product_id = p.id 
              WHERE p.seller_id = :seller_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':seller_id', $seller_id);
    $stmt->execute();
    $stats['orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Receita do vendedor
    $query = "SELECT COALESCE(SUM(oi.price * oi.quantity), 0) as total 
              FROM order_items oi 
              INNER JOIN products p ON oi.product_id = p.id 
              INNER JOIN orders o ON oi.order_id = o.id 
              WHERE p.seller_id = :seller_id AND o.status != 'cancelled'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':seller_id', $seller_id);
    $stmt->execute();
    $stats['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Produtos com baixo estoque
    $query = "SELECT COUNT(*) as count FROM products WHERE seller_id = :seller_id AND stock_quantity <= 5 AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':seller_id', $seller_id);
    $stmt->execute();
    $stats['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor'
    ]);
}
?>