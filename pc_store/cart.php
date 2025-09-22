<?php
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update':
                $cart_id = (int)$_POST['cart_id'];
                $quantity = (int)$_POST['quantity'];
                
                if ($quantity > 0) {
                    $query = "UPDATE cart SET quantity = :quantity WHERE id = :cart_id AND user_id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':quantity', $quantity);
                    $stmt->bindParam(':cart_id', $cart_id);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                }
                break;
                
            case 'remove':
                $cart_id = (int)$_POST['cart_id'];
                
                $query = "DELETE FROM cart WHERE id = :cart_id AND user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':cart_id', $cart_id);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                break;
                
            case 'clear':
                $query = "DELETE FROM cart WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                break;
        }
        
        header('Location: cart.php');
        exit();
    }
}

$query = "SELECT c.id as cart_id, c.quantity, p.id, p.name, p.price, p.stock_quantity, cat.name as category_name
          FROM cart c 
          INNER JOIN products p ON c.product_id = p.id 
          LEFT JOIN categories cat ON p.category_id = cat.id 
          WHERE c.user_id = :user_id AND p.status = 'active'
          ORDER BY c.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = $subtotal > 200 ? 0 : 15.00; 
$total = $subtotal + $shipping;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho - PC Store</title>
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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
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

        /* Cart Layout */
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .cart-items {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .cart-summary {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 120px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 80px 1fr auto auto;
            gap: 1.5rem;
            align-items: center;
            padding: 2rem 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
        }

        .item-info h3 {
            color: #2c3e50;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .item-category {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .item-price {
            color: #27ae60;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f8f9fa;
            border-radius: 25px;
            padding: 0.5rem;
        }

        .quantity-btn {
            width: 35px;
            height: 35px;
            border: none;
            background: #667eea;
            color: white;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: #5a6fd8;
            transform: scale(1.1);
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            border: none;
            background: transparent;
            font-weight: bold;
            font-size: 1rem;
        }

        .item-total {
            text-align: right;
        }

        .item-total .price {
            color: #27ae60;
            font-weight: bold;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }

        /* Summary */
        .summary-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 2rem;
            text-align: center;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }

        .summary-row.total {
            border-top: 2px solid #ecf0f1;
            padding-top: 1rem;
            margin-top: 1rem;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .summary-row .label {
            color: #7f8c8d;
        }

        .summary-row .value {
            color: #2c3e50;
            font-weight: 600;
        }

        .summary-row.total .value {
            color: #27ae60;
            font-size: 1.5rem;
        }

        .free-shipping {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 10px;
            margin: 1rem 0;
            text-align: center;
            font-size: 0.9rem;
        }

        .shipping-info {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 10px;
            margin: 1rem 0;
            text-align: center;
            font-size: 0.9rem;
        }

        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .empty-cart i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 2rem;
        }

        .empty-cart h2 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .empty-cart p {
            color: #7f8c8d;
            margin-bottom: 2rem;
        }

        @media (max-width: 1024px) {
            .cart-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .cart-summary {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .cart-item {
                grid-template-columns: 60px 1fr;
                gap: 1rem;
            }
            
            .quantity-controls,
            .item-total {
                grid-column: 1 / -1;
                justify-self: center;
                margin-top: 1rem;
            }
            
            .main-content {
                padding: 1rem 0;
            }
            
            .cart-items,
            .cart-summary {
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
                <li><a href="categories.php">Categorias</a></li>
            </ul>
            
            <div>
                <span>Olá, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <a href="logout.php" class="btn btn-outline btn-small">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </nav>
    </header>

    <main class="main-content container">
        <div class="page-header">
            <h1><i class="fas fa-shopping-cart"></i> Meu Carrinho</h1>
            <p>Revise seus itens antes de finalizar a compra</p>
        </div>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Seu carrinho está vazio</h2>
                <p>Adicione alguns produtos incríveis à sua coleção!</p>
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i> Ver Produtos
                </a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <h2>Itens do Carrinho (<?= count($cart_items) ?>)</h2>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Tem certeza que deseja limpar o carrinho?')">
                                <i class="fas fa-trash"></i> Limpar Carrinho
                            </button>
                        </form>
                    </div>

                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <i class="fas fa-desktop"></i>
                            </div>
                            
                            <div class="item-info">
                                <h3><?= htmlspecialchars($item['name']) ?></h3>
                                <div class="item-category"><?= htmlspecialchars($item['category_name']) ?></div>
                                <div class="item-price">R$ <?= number_format($item['price'], 2, ',', '.') ?></div>
                                <?php if ($item['stock_quantity'] < $item['quantity']): ?>
                                    <small style="color: #e74c3c;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Estoque insuficiente (apenas <?= $item['stock_quantity'] ?> disponível)
                                    </small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="quantity-controls">
                                <form method="POST" style="display: contents;">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                    
                                    <button type="submit" name="quantity" value="<?= max(1, $item['quantity'] - 1) ?>" class="quantity-btn">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    
                                    <input type="number" name="quantity" value="<?= $item['quantity'] ?>" 
                                           min="1" max="<?= $item['stock_quantity'] ?>" 
                                           class="quantity-input" 
                                           onchange="this.form.submit()">
                                    
                                    <button type="submit" name="quantity" value="<?= min($item['stock_quantity'], $item['quantity'] + 1) ?>" 
                                            class="quantity-btn" <?= ($item['quantity'] >= $item['stock_quantity']) ? 'disabled' : '' ?>>
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </form>
                            </div>
                            
                            <div class="item-total">
                                <div class="price">R$ <?= number_format($item['price'] * $item['quantity'], 2, ',', '.') ?></div>
                                <form method="POST" style="margin-top: 0.5rem;">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-small" 
                                            onclick="return confirm('Remover item do carrinho?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h2 class="summary-title">Resumo do Pedido</h2>
                    
                    <div class="summary-row">
                        <span class="label">Subtotal (<?= count($cart_items) ?> itens)</span>
                        <span class="value">R$ <?= number_format($subtotal, 2, ',', '.') ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="label">Frete</span>
                        <span class="value">
                            <?php if ($shipping == 0): ?>
                                <span style="color: #27ae60;">GRÁTIS</span>
                            <?php else: ?>
                                R$ <?= number_format($shipping, 2, ',', '.') ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <?php if ($shipping == 0): ?>
                        <div class="free-shipping">
                            <i class="fas fa-truck"></i>
                            Parabéns! Você ganhou frete grátis!
                        </div>
                    <?php else: ?>
                        <div class="shipping-info">
                            <i class="fas fa-info-circle"></i>
                            Adicione mais R$ <?= number_format(200 - $subtotal, 2, ',', '.') ?> e ganhe frete grátis!
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-row total">
                        <span class="label">Total</span>
                        <span class="value">R$ <?= number_format($total, 2, ',', '.') ?></span>
                    </div>
                    
                    <a href="checkout.php" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 2rem; padding: 1rem;">
                        <i class="fas fa-credit-card"></i>
                        Finalizar Compra
                    </a>
                    
                    <a href="products.php" class="btn btn-outline" style="width: 100%; justify-content: center; margin-top: 1rem;">
                        <i class="fas fa-arrow-left"></i>
                        Continuar Comprando
                    </a>
                    
                    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #ecf0f1;">
                        <h4 style="color: #2c3e50; margin-bottom: 1rem;">Formas de Pagamento</h4>
                        <div style="display: flex; gap: 0.5rem; font-size: 1.5rem; color: #7f8c8d;">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fab fa-cc-amex"></i>
                            <i class="fas fa-barcode"></i>
                            <i class="fab fa-pix" style="color: #32BCAD;"></i>
                        </div>
                        
                        <div style="margin-top: 1rem; font-size: 0.9rem; color: #7f8c8d;">
                            <i class="fas fa-shield-alt" style="color: #27ae60;"></i>
                            Compra 100% segura e protegida
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Auto-save quantity changes
        document.querySelectorAll('.quantity-input').forEach(input => {
            let timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    this.form.submit();
                }, 1000);
            });
        });

        // Prevent form submission if quantity is invalid
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const quantityInput = this.querySelector('.quantity-input');
                if (quantityInput) {
                    const value = parseInt(quantityInput.value);
                    const max = parseInt(quantityInput.getAttribute('max'));
                    const min = parseInt(quantityInput.getAttribute('min'));
                    
                    if (value > max || value < min) {
                        e.preventDefault();
                        alert(`Quantidade deve estar entre ${min} e ${max}`);
                        quantityInput.focus();
                        return false;
                    }
                }
            });
        });

        // Update cart count in real time
        function updateCartCount() {
            fetch('api/cart_count.php')
                .then(response => response.json())
                .then(data => {
                    const countElement = document.querySelector('.cart-count');
                    if (countElement) {
                        countElement.textContent = data.count;
                    }
                })
                .catch(error => console.error('Erro:', error));
        }

        // Show loading state during form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.style.opacity = '0.7';
                    submitBtn.style.pointerEvents = 'none';
                }
            });
        });

        // Smooth animations for quantity changes
        document.querySelectorAll('.quantity-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter to checkout
            if (e.ctrlKey && e.key === 'Enter') {
                const checkoutBtn = document.querySelector('a[href="checkout.php"]');
                if (checkoutBtn) {
                    checkoutBtn.click();
                }
            }
        });

        // Warn before leaving if cart has items
        <?php if (!empty($cart_items)): ?>
        window.addEventListener('beforeunload', function(e) {
            const forms = document.querySelectorAll('form');
            let isSubmitting = false;
            
            forms.forEach(form => {
                if (form.classList.contains('submitting')) {
                    isSubmitting = true;
                }
            });
            
            if (!isSubmitting && !window.location.href.includes('checkout')) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>