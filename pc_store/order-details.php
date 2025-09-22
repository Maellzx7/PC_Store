<?php
require_once 'config/database.php';

requireLogin();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: orders.php');
    exit();
}

$order_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];
$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM orders WHERE id = :order_id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header('Location: orders.php');
    exit();
}

$order = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT oi.*, p.name as product_name, p.description, p.image_url, c.name as category_name, u.name as seller_name
          FROM order_items oi 
          INNER JOIN products p ON oi.product_id = p.id 
          LEFT JOIN categories c ON p.category_id = c.id
          LEFT JOIN users u ON p.seller_id = u.id
          WHERE oi.order_id = :order_id
          ORDER BY oi.id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->execute();
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT name, email FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$shipping = $subtotal > 200 ? 0 : 15.00;
$total = $order['total_amount'];

$status_info = [
    'pending' => [
        'text' => 'Aguardando Pagamento',
        'icon' => 'fas fa-clock',
        'color' => '#f39c12',
        'bg' => '#fff3cd',
        'description' => 'Seu pedido foi recebido e está aguardando confirmação do pagamento.'
    ],
    'processing' => [
        'text' => 'Processando',
        'icon' => 'fas fa-cog',
        'color' => '#3498db',
        'bg' => '#d1ecf1',
        'description' => 'Pagamento confirmado! Estamos separando seus produtos.'
    ],
    'shipped' => [
        'text' => 'Enviado',
        'icon' => 'fas fa-truck',
        'color' => '#27ae60',
        'bg' => '#d4edda',
        'description' => 'Seu pedido foi enviado e está a caminho!'
    ],
    'delivered' => [
        'text' => 'Entregue',
        'icon' => 'fas fa-check-circle',
        'color' => '#28a745',
        'bg' => '#d4edda',
        'description' => 'Pedido entregue com sucesso!'
    ],
    'cancelled' => [
        'text' => 'Cancelado',
        'icon' => 'fas fa-times-circle',
        'color' => '#dc3545',
        'bg' => '#f8d7da',
        'description' => 'Este pedido foi cancelado.'
    ]
];

$current_status = $status_info[$order['status']];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Pedido #<?= sprintf('%06d', $order['id']) ?> - PC Store</title>
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

        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
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

        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 2rem;
            color: #7f8c8d;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .order-number {
            font-size: 3rem;
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .order-date {
            color: #7f8c8d;
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }

        /* Status Section */
        .status-section {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .status-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem 3rem;
            border-radius: 50px;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .status-description {
            font-size: 1.1rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Timeline */
        .timeline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin: 3rem 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 4px;
            background: #ecf0f1;
            transform: translateY(-50%);
            z-index: 1;
        }

        .timeline-step {
            background: white;
            border-radius: 50%;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            border: 4px solid #ecf0f1;
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .timeline-step.completed {
            background: #27ae60;
            border-color: #27ae60;
            color: white;
        }

        .timeline-step.current {
            background: #3498db;
            border-color: #3498db;
            color: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(52, 152, 219, 0); }
            100% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0); }
        }

        .timeline-label {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 1rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            color: #666;
            white-space: nowrap;
        }

        .timeline-step.completed .timeline-label {
            color: #27ae60;
        }

        .timeline-step.current .timeline-label {
            color: #3498db;
        }

        /* Order Details Layout */
        .details-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .details-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Order Items */
        .order-item {
            display: flex;
            align-items: center;
            gap: 2rem;
            padding: 2rem 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            flex-shrink: 0;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .item-category {
            color: #667eea;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .item-seller {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .item-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .item-pricing {
            text-align: right;
            flex-shrink: 0;
        }

        .item-quantity {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 0.5rem;
        }

        .item-unit-price {
            font-size: 1rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .item-total-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #27ae60;
        }

        /* Summary */
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total {
            border-top: 2px solid #ecf0f1;
            margin-top: 1rem;
            padding-top: 2rem;
            font-size: 1.3rem;
            font-weight: bold;
        }

        .summary-label {
            color: #7f8c8d;
            font-weight: 500;
        }

        .summary-value {
            color: #2c3e50;
            font-weight: 600;
        }

        .summary-row.total .summary-value {
            color: #27ae60;
            font-size: 2rem;
        }

        /* Customer Info */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #7f8c8d;
            font-weight: 500;
            min-width: 120px;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 600;
            text-align: right;
        }

        /* Actions */
        .actions-section {
            margin-top: 2rem;
            text-align: center;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .details-layout {
                grid-template-columns: 1fr;
            }
            
            .timeline {
                flex-wrap: wrap;
                gap: 2rem;
            }
            
            .timeline::before {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .order-number {
                font-size: 2rem;
            }
            
            .page-header,
            .status-section,
            .details-section {
                padding: 2rem;
            }
            
            .order-item {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .item-pricing {
                text-align: center;
            }
            
            .timeline {
                justify-content: center;
            }
            
            .timeline-step {
                width: 60px;
                height: 60px;
                font-size: 1.2rem;
            }
        }

        .print-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .print-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }

        @media print {
            .print-button, nav, .actions-section {
                display: none !important;
            }
            
            body {
                background: white !important;
            }
            
            .details-section, .status-section, .page-header {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
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
                <li><a href="orders.php">Meus Pedidos</a></li>
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
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Início</a> > 
            <a href="orders.php">Meus Pedidos</a> > 
            Pedido #<?= sprintf('%06d', $order['id']) ?>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="order-number">Pedido #<?= sprintf('%06d', $order['id']) ?></h1>
            <p class="order-date">
                Realizado em <?= date('d/m/Y \à\s H:i', strtotime($order['created_at'])) ?>
                <?php if ($order['updated_at'] != $order['created_at']): ?>
                    • Última atualização: <?= date('d/m/Y \à\s H:i', strtotime($order['updated_at'])) ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Status Section -->
        <div class="status-section">
            <div class="status-header">
                <div class="status-badge" style="background: <?= $current_status['bg'] ?>; color: <?= $current_status['color'] ?>;">
                    <i class="<?= $current_status['icon'] ?>"></i>
                    <?= $current_status['text'] ?>
                </div>
                <p class="status-description"><?= $current_status['description'] ?></p>
            </div>

            <!-- Timeline -->
            <div class="timeline">
                <div class="timeline-step <?= in_array($order['status'], ['pending', 'processing', 'shipped', 'delivered']) ? 'completed' : 'pending' ?>">
                    <i class="fas fa-check"></i>
                    <div class="timeline-label">Confirmado</div>
                </div>
                
                <div class="timeline-step <?= in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'completed' : ($order['status'] === 'pending' ? 'current' : 'pending') ?>">
                    <i class="fas fa-cog"></i>
                    <div class="timeline-label">Processando</div>
                </div>
                
                <div class="timeline-step <?= in_array($order['status'], ['shipped', 'delivered']) ? 'completed' : ($order['status'] === 'processing' ? 'current' : 'pending') ?>">
                    <i class="fas fa-truck"></i>
                    <div class="timeline-label">Enviado</div>
                </div>
                
                <div class="timeline-step <?= $order['status'] === 'delivered' ? 'completed' : ($order['status'] === 'shipped' ? 'current' : 'pending') ?>">
                    <i class="fas fa-home"></i>
                    <div class="timeline-label">Entregue</div>
                </div>
            </div>
        </div>

        <!-- Details Layout -->
        <div class="details-layout">
            <!-- Order Items -->
            <div class="details-section">
                <h2 class="section-title">
                    <i class="fas fa-box"></i>
                    Itens do Pedido
                </h2>

                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <div class="item-image">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($item['product_name']) ?>"
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 15px;"
                                     onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=fas fa-desktop style=font-size:2rem;color:white></i>';">
                            <?php else: ?>
                                <i class="fas fa-desktop"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="item-details">
                            <h3 class="item-name"><?= htmlspecialchars($item['product_name']) ?></h3>
                            <div class="item-category">
                                <i class="fas fa-tag"></i>
                                <?= htmlspecialchars($item['category_name'] ?? 'Categoria não informada') ?>
                            </div>
                            <div class="item-seller">
                                <i class="fas fa-store"></i>
                                Vendido por: <?= htmlspecialchars($item['seller_name'] ?? 'PC Store') ?>
                            </div>
                            <p class="item-description">
                                <?= htmlspecialchars(substr($item['description'] ?? '', 0, 150)) ?>
                                <?= strlen($item['description'] ?? '') > 150 ? '...' : '' ?>
                            </p>
                        </div>
                        
                        <div class="item-pricing">
                            <div class="item-quantity">Qtd: <?= $item['quantity'] ?></div>
                            <div class="item-unit-price">R$ <?= number_format($item['price'], 2, ',', '.') ?> cada</div>
                            <div class="item-total-price">R$ <?= number_format($item['price'] * $item['quantity'], 2, ',', '.') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Order Summary -->
            <div class="details-section">
                <h2 class="section-title">
                    <i class="fas fa-receipt"></i>
                    Resumo do Pedido
                </h2>

                <div class="summary-row">
                    <span class="summary-label">Subtotal</span>
                    <span class="summary-value">R$ <?= number_format($subtotal, 2, ',', '.') ?></span>
                </div>

                <div class="summary-row">
                    <span class="summary-label">Frete</span>
                    <span class="summary-value">
                        <?= $shipping == 0 ? 'GRÁTIS' : 'R$ ' . number_format($shipping, 2, ',', '.') ?>
                    </span>
                </div>

                <div class="summary-row total">
                    <span class="summary-label">Total</span>
                    <span class="summary-value">R$ <?= number_format($total, 2, ',', '.') ?></span>
                </div>

                <!-- Customer Info -->
                <h3 class="section-title" style="margin-top: 2rem;">
                    <i class="fas fa-user"></i>
                    Informações do Cliente
                </h3>

                <div class="info-row">
                    <span class="info-label">Nome:</span>
                    <span class="info-value"><?= htmlspecialchars($user_data['name']) ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">E-mail:</span>
                    <span class="info-value"><?= htmlspecialchars($user_data['email']) ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">Pagamento:</span>
                    <span class="info-value">
                        <?php
                        switch($order['payment_method']) {
                            case 'credit_card':
                                echo '<i class="fas fa-credit-card"></i> Cartão de Crédito';
                                break;
                            case 'debit_card':
                                echo '<i class="fas fa-credit-card"></i> Cartão de Débito';
                                break;
                            case 'pix':
                                echo '<i class="fab fa-pix"></i> PIX';
                                break;
                            case 'boleto':
                                echo '<i class="fas fa-barcode"></i> Boleto';
                                break;
                            default:
                                echo $order['payment_method'] ?? 'Não informado';
                        }
                        ?>
                    </span>
                </div>

                <?php if ($order['shipping_address']): ?>
                    <div class="info-row">
                        <span class="info-label">Endereço:</span>
                        <span class="info-value"><?= htmlspecialchars($order['shipping_address']) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="actions-section">
                    <a href="orders.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Voltar aos Pedidos
                    </a>

                    <?php if ($order['status'] === 'delivered'): ?>
                        <button class="btn btn-success" onclick="rateOrder(<?= $order['id'] ?>)">
                            <i class="fas fa-star"></i> Avaliar Pedido
                        </button>
                    <?php endif; ?>

                    <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                        <button class="btn btn-danger" onclick="cancelOrder(<?= $order['id'] ?>)">
                            <i class="fas fa-times"></i> Cancelar Pedido
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Print Button -->
    <button class="print-button" onclick="window.print()" title="Imprimir Pedido">
        <i class="fas fa-print"></i>
    </button>

    <script>
        function rateOrder(orderId) {
            alert('Funcionalidade de avaliação em desenvolvimento!');
            // Implementar modal de avaliação
        }

        function cancelOrder(orderId) {
            if (confirm('Tem certeza que deseja cancelar este pedido?\n\nEsta ação não pode ser desfeita.')) {
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

        // Smooth scroll para timeline
        document.addEventListener('DOMContentLoaded', function() {
            const currentStep = document.querySelector('.timeline-step.current');
            if (currentStep) {
                currentStep.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // P para imprimir
            if (e.key === 'p' && e.ctrlKey) {
                e.preventDefault();
                window.print();
            }
            
            // ESC para voltar
            if (e.key === 'Escape') {
                window.location.href = 'orders.php';
            }
        });

        // Auto-refresh status (opcional)
        function checkOrderStatus() {
            fetch('api/order_status.php?id=<?= $order['id'] ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.status !== '<?= $order['status'] ?>') {
                        location.reload();
                    }
                })
                .catch(error => console.error('Erro ao verificar status:', error));
        }

        // Verificar mudanças de status a cada 60 segundos
        setInterval(checkOrderStatus, 60000);

        // Animação de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.details-section, .status-section, .page-header');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    section.style.transition = 'all 0.6s ease';
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>