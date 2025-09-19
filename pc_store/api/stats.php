<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stats = [];

    // Total de usuários
    $query = "SELECT COUNT(*) as count FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total de produtos ativos
    $query = "SELECT COUNT(*) as count FROM products WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total de pedidos
    $query = "SELECT COUNT(*) as count FROM orders";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Receita total
    $query = "SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status != 'cancelled'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Estatísticas por tipo de usuário
    $query = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $user_types = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $user_types[$row['user_type']] = $row['count'];
    }
    $stats['user_types'] = $user_types;

    // Vendas por status
    $query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $order_status = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $order_status[$row['status']] = $row['count'];
    }
    $stats['order_status'] = $order_status;

    // Produtos com baixo estoque
    $query