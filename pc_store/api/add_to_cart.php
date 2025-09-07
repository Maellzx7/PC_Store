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

if (!isset($input['product_id']) || !isset($input['quantity'])) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit();
}

$product_id = (int)$input['product_id'];
$quantity = (int)$input['quantity'];
$user_id = $_SESSION['user_id'];

if ($quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Quantidade deve ser maior que zero']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Verificar se o produto existe e tem estoque suficiente
    $query = "SELECT id, name, stock_quantity FROM products WHERE id = :product_id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
        exit();
    }
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product['stock_quantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Estoque insuficiente']);
        exit();
    }
    
    // Verificar se já existe no carrinho
    $query = "SELECT id, quantity FROM cart WHERE user_id = :user_id AND product_id = :product_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Atualizar quantidade existente
        $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        if ($new_quantity > $product['stock_quantity']) {
            echo json_encode(['success' => false, 'message' => 'Quantidade total excede o estoque disponível']);
            exit();
        }
        
        $query = "UPDATE cart SET quantity = :quantity WHERE id = :cart_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':quantity', $new_quantity);
        $stmt->bindParam(':cart_id', $cart_item['id']);
        $stmt->execute();
    } else {
        // Adicionar novo item ao carrinho
        $query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Produto adicionado ao carrinho']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>