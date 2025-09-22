<?php
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$query = "SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$orders_with_items = [];
foreach ($orders as $order) {
    $query = "SELECT oi.*, p.name as product_name, p.image_url 
              FROM order_items oi 
              INNER JOIN products p ON oi.product_id = p.id 
              WHERE oi.order_id = :order_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $order['id']);
    $stmt->execute();
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $orders_with_items[] = $order;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Pedidos - PC Store</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #667eea;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: #667eea;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-size: 3rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #7f8c8d;
            font-size: 1.2rem;
        }

        .orders-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .order-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #ecf0f1;
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .order-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .order-date {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .order-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background: #cce7ff;
            color: #0066cc;
        }

        .status-shipped {
            background: #d4edda;
            color: #155724;
        }

        .status-delivered {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .order-total {
            font-size: 1.5rem;
            font-weight: bold;
            color: #27ae60;
        }

        .order-items {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 15px;
        }

        .item-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .item-quantity {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .item-price {
            font-weight: bold;
            color: #27ae60;
            font-size: 1.2rem;
        }

        .order-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #ecf0f1;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .empty-orders {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .empty-orders i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 2rem;
        }

        .empty-orders h2 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .empty-orders p {
            color: #7f8c8d;
            margin-bottom: 2rem;
        }

        .timeline {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
        }

        .timeline-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .timeline-step.completed {
            color: #27ae60;
        }

        .timeline-step.current {
            color: #3498db;
            font-weight: bold;
        }

        .timeline-step.pending {
            color: #bdc3c7;
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .order-header {
                flex-direction: column;
                align-items: start;
                gap: 1rem;
            }
            
            .order-item {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .order-actions {
                flex-direction: column;
            }
            
            .main-content {
                padding: 1rem 0;
            }
            
            .order-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-desktop"></i> PC Store
            </a>
            
            <ul class="nav-links">
                <li><a href="index.php">Início</a></li>
                <li><a href="products.php">Produtos</a></li>
                <li><a href="cart.php">Carrinho</a></li>
                <li><a href="orders.php" style="color: #667eea;">Meus Pedidos</a></li>
            </ul>
            
            <div>
                <span>Olá, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <a href="logout.php" class="btn btn-primary btn-small">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </nav>
    </header>

    <main class="main-content container">
        <div class="page-header">
            <h1><i class="fas fa-box"></i> Meus Pedidos</h1>
            <p>Acompanhe o status dos seus pedidos</p>
        </div>

        <?php if (empty($orders_with_items)): ?>
            <div class="empty-orders">
                <i class="fas fa-shopping-bag"></i>
                <h2>Você ainda não fez nenhum pedido</h2>
                <p>Que tal dar uma olhada em nossos produtos incríveis?</p>
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i> Ver Produtos
                </a>
            </div>
        <?php else: ?>
            <div class="orders-container">
                <?php foreach ($orders_with_items as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <div class="order-number">
                                    Pedido #<?= sprintf('%06d', $order['id']) ?>
                                </div>
                                <div class="order-date">
                                    Realizado em <?= date('d/m/Y \à\s H:i', strtotime($order['created_at'])) ?>
                                </div>
                            </div>
                            
                            <div class="order-status">
                                <?php
                                $status_class = '';
                                $status_text = '';
                                $status_icon = '';
                                
                                switch($order['status']) {
                                    case 'pending':
                                        $status_class = 'status-pending';
                                        $status_text = 'Pendente';
                                        $status_icon = 'fas fa-clock';
                                        break;
                                    case 'processing':
                                        $status_class = 'status-processing';
                                        $status_text = 'Processando';
                                        $status_icon = 'fas fa-cog';
                                        break;
                                    case 'shipped':
                                        $status_class = 'status-shipped';
                                        $status_text = 'Enviado';
                                        $status_icon = 'fas fa-truck';
                                        break;
                                    case 'delivered':
                                        $status_class = 'status-delivered';
                                        $status_text = 'Entregue';
                                        $status_icon = 'fas fa-check-circle';
                                        break;
                                    case 'cancelled':
                                        $status_class = 'status-cancelled';
                                        $status_text = 'Cancelado';
                                        $status_icon = 'fas fa-times-circle';
                                        break;
                                }
                                ?>
                                
                                <span class="status-badge <?= $status_class ?>">
                                    <i class="<?= $status_icon ?>"></i>
                                    <?= $status_text ?>
                                </span>
                                
                                <div class="order-total">
                                    R$ <?= number_format($order['total_amount'], 2, ',', '.') ?>
                                </div>
                            </div>
                        </div>

                        <!-- Timeline do pedido -->
                        <div class="timeline">
                            <div class="timeline-step <?= in_array($order['status'], ['pending', 'processing', 'shipped', 'delivered']) ? 'completed' : 'pending' ?>">
                                <i class="fas fa-check-circle"></i> Confirmado
                            </div>
                            <div class="timeline-step <?= in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'completed' : ($order['status'] === 'pending' ? 'current' : 'pending') ?>">
                                <i class="fas fa-cog"></i> Processando
                            </div>
                            <div class="timeline-step <?= in_array($order['status'], ['shipped', 'delivered']) ? 'completed' : ($order['status'] === 'processing' ? 'current' : 'pending') ?>">
                                <i class="fas fa-truck"></i> Enviado
                            </div>
                            <div class="timeline-step <?= $order['status'] === 'delivered' ? 'completed' : ($order['status'] === 'shipped' ? 'current' : 'pending') ?>">
                                <i class="fas fa-home"></i> Entregue
                            </div>
                        </div>

                        <div class="order-items">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="order-item">
                                    <div class="item-image">
                                        <i class="fas fa-desktop"></i>
                                    </div>
                                    <div class="item-details">
                                        <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                        <div class="item-quantity">
                                            Quantidade: <?= $item['quantity'] ?> × R$ <?= number_format($item['price'], 2, ',', '.') ?>
                                        </div>
                                    </div>
                                    <div class="item-price">
                                        R$ <?= number_format($item['price'] * $item['quantity'], 2, ',', '.') ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="order-actions">
                            <a href="order-details.php?id=<?= $order['id'] ?>" class="btn btn-outline">
                                <i class="fas fa-eye"></i> Ver Detalhes
                            </a>
                            
                            <?php if ($order['status'] === 'delivered'): ?>
                                <button class="btn btn-success" onclick="rateOrder(<?= $order['id'] ?>)">
                                    <i class="fas fa-star"></i> Avaliar Pedido
                                </button>
                            <?php endif; ?>
                            
                            <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                                <button class="btn" style="background: #e74c3c; color: white;" onclick="cancelOrder(<?= $order['id'] ?>)">
                                    <i class="fas fa-times"></i> Cancelar Pedido
                                </button>
                            <?php endif; ?>

                            <button class="btn btn-outline" onclick="reorderItems(<?= $order['id'] ?>)">
                                <i class="fas fa-redo"></i> Comprar Novamente
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function rateOrder(orderId) {
            alert('Funcionalidade de avaliação em desenvolvimento!');
            // Implementar modal de avaliação
        }

        function cancelOrder(orderId) {
            if (confirm('Tem certeza que deseja cancelar este pedido?')) {
                fetch('api/cancel_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: orderId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Pedido cancelado com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao cancelar pedido: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao cancelar pedido');
                });
            }
        }

        function reorderItems(orderId) {
            fetch('api/reorder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Produtos adicionados ao carrinho!');
                    window.location.href = 'cart.php';
                } else {
                    alert('Erro ao adicionar produtos: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar pedido');
            });
        }

        // Atualizar status dos pedidos em tempo real (opcional)
        function checkOrderUpdates() {
            fetch('api/check_order_updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.updated) {
                        location.reload();
                    }
                })
                .catch(error => console.error('Erro:', error));
        }

        // Verificar atualizações a cada 30 segundos
        setInterval(checkOrderUpdates, 30000);
    </script>
</body>
</html>