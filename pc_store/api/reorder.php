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
    // Verificar se o pedido pertence ao usuário
    $query = "SELECT id FROM orders WHERE id = :order_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Pedido não encontrado');
    }
    
    // Buscar itens do pedido original
    $query = "SELECT oi.product_id, oi.quantity, p.name, p.price, p.stock_quantity, p.status
              FROM order_items oi 
              INNER JOIN products p ON oi.product_id = p.id
              WHERE oi.order_id = :order_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($order_items)) {
        throw new Exception('Nenhum item encontrado no pedido');
    }
    
    $added_items = 0;
    $unavailable_items = [];
    $insufficient_stock = [];
    
    foreach ($order_items as $item) {
        // Verificar se o produto ainda está disponível
        if ($item['status'] !== 'active') {
            $unavailable_items[] = $item['name'];
            continue;
        }
        
        // Verificar estoque
        if ($item['stock_quantity'] < $item['quantity']) {
            $insufficient_stock[] = [
                'name' => $item['name'],
                'requested' => $item['quantity'],
                'available' => $item['stock_quantity']
            ];
            
            // Adicionar a quantidade disponível se houver
            if ($item['stock_quantity'] > 0) {
                $quantity_to_add = $item['stock_quantity'];
            } else {
                continue;
            }
        } else {
            $quantity_to_add = $item['quantity'];
        }
        
        // Verificar se já existe no carrinho
        $query = "SELECT id, quantity FROM cart WHERE user_id = :user_id AND product_id = :product_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':product_id', $item['product_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Atualizar quantidade no carrinho
            $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_quantity = min($cart_item['quantity'] + $quantity_to_add, $item['stock_quantity']);
            
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
            $stmt->bindParam(':product_id', $item['product_id']);
            $stmt->bindParam(':quantity', $quantity_to_add);
            $stmt->execute();
        }
        
        $added_items++;
    }
    
    // Preparar mensagem de resposta
    $message = '';
    
    if ($added_items > 0) {
        $message = "$added_items item(s) adicionado(s) ao carrinho.";
    }
    
    if (!empty($unavailable_items)) {
        $message .= "\nItens não disponíveis: " . implode(', ', $unavailable_items);
    }
    
    if (!empty($insufficient_stock)) {
        $message .= "\nItens com estoque limitado: ";
        foreach ($insufficient_stock as $stock_info) {
            $message .= "\n- {$stock_info['name']}: solicitado {$stock_info['requested']}, disponível {$stock_info['available']}";
        }
    }
    
    if ($added_items === 0) {
        throw new Exception('Nenhum item pôde ser adicionado ao carrinho.');
    }
    
    echo json_encode([
        'success' => true,
        'message' => trim($message),
        'added_items' => $added_items,
        'unavailable_items' => count($unavailable_items),
        'insufficient_stock' => count($insufficient_stock)
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao reordenar itens: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>