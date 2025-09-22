<?php
require_once 'config/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: products.php');
    exit();
}

$product_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

$query = "SELECT p.*, c.name as category_name, u.name as seller_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN users u ON p.seller_id = u.id 
          WHERE p.id = :product_id AND p.status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(':product_id', $product_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header('Location: products.php');
    exit();
}

$product = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.category_id = :category_id AND p.id != :product_id AND p.status = 'active'
          ORDER BY RAND() 
          LIMIT 4";
$stmt = $db->prepare($query);
$stmt->bindParam(':category_id', $product['category_id']);
$stmt->bindParam(':product_id', $product_id);
$stmt->execute();
$related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - PC Store</title>
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

        .user-menu {
            display: flex;
            gap: 1rem;
            align-items: center;
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

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 15px;
        }

        /* Breadcrumb */
        .breadcrumb {
            padding: 1rem 0;
            color: #7f8c8d;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Product Details */
        .product-detail {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            margin: 2rem 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .product-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: start;
        }

        .product-image-section {
            position: relative;
        }

        .product-main-image {
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            position: relative;
            overflow: hidden;
        }

        .product-main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 20px;
        }

        .product-main-image .placeholder-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2;
        }

        .product-main-image .placeholder-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.8), rgba(118, 75, 162, 0.8));
            z-index: 1;
        }

        .product-main-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="circuit" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M0 10h20M10 0v20M5 5h10v10H5z" stroke="rgba(255,255,255,0.1)" stroke-width="0.5" fill="none"/></pattern></defs><rect width="100" height="100" fill="url(%23circuit)"/></svg>');
            opacity: 0.3;
        }

        .product-badges {
            position: absolute;
            top: 1rem;
            left: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .product-badge {
            background: rgba(255, 255, 255, 0.9);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .badge-stock {
            color: #27ae60;
        }

        .badge-low-stock {
            color: #f39c12;
        }

        .badge-out-stock {
            color: #e74c3c;
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .product-category {
            color: #667eea;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }

        .product-title {
            font-size: 2.5rem;
            color: #2c3e50;
            font-weight: 700;
            line-height: 1.2;
        }

        .product-price {
            font-size: 3rem;
            color: #27ae60;
            font-weight: bold;
            margin: 1rem 0;
        }

        .product-seller {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .product-description {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.8;
            margin: 2rem 0;
        }

        .product-specs {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 15px;
            margin: 2rem 0;
        }

        .specs-title {
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 2rem 0;
        }

        .quantity-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border-radius: 25px;
            padding: 0.5rem;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
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

        .quantity-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }

        .quantity-input {
            width: 80px;
            text-align: center;
            border: none;
            background: transparent;
            font-weight: bold;
            font-size: 1.2rem;
            margin: 0 1rem;
        }

        .stock-info {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin: 2rem 0;
        }

        .security-info {
            background: #e8f5e8;
            border: 1px solid #c3e6cb;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #155724;
            text-align: center;
        }

        /* Related Products */
        .related-section {
            margin: 4rem 0;
        }

        .section-title {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 2rem;
            text-align: center;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .related-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .related-image {
            height: 150px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }

        .related-info {
            padding: 1.5rem;
        }

        .related-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .related-price {
            color: #27ae60;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .cart-icon {
            position: relative;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        @media (max-width: 1024px) {
            .product-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .related-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .product-detail {
                padding: 2rem;
                margin: 1rem 0;
            }
            
            .product-title {
                font-size: 1.8rem;
            }
            
            .product-price {
                font-size: 2rem;
            }
            
            .product-main-image {
                height: 250px;
                font-size: 2.5rem;
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
                <li><a href="about.php">Sobre</a></li>
            </ul>
            
            <div class="user-menu">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="admin/dashboard.php" class="btn btn-outline btn-small">
                            <i class="fas fa-cog"></i> Admin
                        </a>
                    <?php elseif (isSeller()): ?>
                        <a href="seller/dashboard.php" class="btn btn-outline btn-small">
                            <i class="fas fa-store"></i> Vendedor
                        </a>
                    <?php endif; ?>
                    
                    <a href="cart.php" class="btn btn-outline btn-small cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count">0</span>
                    </a>
                    
                    <span>Olá, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?></span>
                    <a href="logout.php" class="btn btn-primary btn-small">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">
                        <i class="fas fa-sign-in-alt"></i> Entrar
                    </a>
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Cadastrar
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Início</a> > 
            <a href="products.php">Produtos</a> > 
            <?php if ($product['category_name']): ?>
                <a href="products.php?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a> > 
            <?php endif; ?>
            <?= htmlspecialchars($product['name']) ?>
        </div>

        <!-- Product Details -->
        <div class="product-detail">
            <div class="product-layout">
                <div class="product-image-section">
                    <div class="product-main-image">
                        <div class="product-badges">
                            <?php if ($product['stock_quantity'] > 10): ?>
                                <div class="product-badge badge-stock">
                                    <i class="fas fa-check"></i> Em Estoque
                                </div>
                            <?php elseif ($product['stock_quantity'] > 0): ?>
                                <div class="product-badge badge-low-stock">
                                    <i class="fas fa-exclamation"></i> Últimas Unidades
                                </div>
                            <?php else: ?>
                                <div class="product-badge badge-out-stock">
                                    <i class="fas fa-times"></i> Esgotado
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 onerror="showPlaceholder(this)">
                        <?php else: ?>
                            <div class="placeholder-overlay"></div>
                            <i class="fas fa-desktop placeholder-icon"></i>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="product-info">
                    <div class="product-category">
                        <?= htmlspecialchars($product['category_name'] ?? 'Produto') ?>
                    </div>
                    
                    <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                    
                    <div class="product-price">
                        R$ <?= number_format($product['price'], 2, ',', '.') ?>
                    </div>
                    
                    <div class="product-seller">
                        <i class="fas fa-store"></i>
                        <strong>Vendido por:</strong> <?= htmlspecialchars($product['seller_name'] ?? 'PC Store') ?>
                    </div>

                    <?php if ($product['stock_quantity'] > 0): ?>
                        <div class="quantity-selector">
                            <span class="quantity-label">Quantidade:</span>
                            <div class="quantity-controls">
                                <button type="button" class="quantity-btn" onclick="decreaseQuantity()">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?= $product['stock_quantity'] ?>">
                                <button type="button" class="quantity-btn" onclick="increaseQuantity()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="stock-info">
                                <?= $product['stock_quantity'] ?> unidade(s) disponível(eis)
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button class="btn btn-success btn-large" onclick="addToCart(<?= $product['id'] ?>)" style="flex: 1;">
                                <i class="fas fa-cart-plus"></i>
                                Adicionar ao Carrinho
                            </button>
                            <button class="btn btn-primary btn-large" onclick="buyNow(<?= $product['id'] ?>)">
                                <i class="fas fa-bolt"></i>
                                Comprar Agora
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="action-buttons">
                            <button class="btn btn-large" style="background: #bdc3c7; color: white; cursor: not-allowed; flex: 1;" disabled>
                                <i class="fas fa-times"></i>
                                Produto Esgotado
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="security-info">
                        <i class="fas fa-shield-alt"></i>
                        <strong>Compra Segura:</strong> Seus dados protegidos e garantia de entrega
                    </div>
                </div>
            </div>

            <div class="product-specs">
                <h2 class="specs-title">
                    <i class="fas fa-info-circle"></i>
                    Descrição do Produto
                </h2>
                <div class="product-description">
                    <?= nl2br(htmlspecialchars($product['description'])) ?>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
            <div class="related-section">
                <h2 class="section-title">Produtos Relacionados</h2>
                <div class="related-grid">
                    <?php foreach ($related_products as $related): ?>
                        <div class="related-card">
                            <div class="related-image">
                                <i class="fas fa-desktop"></i>
                            </div>
                            <div class="related-info">
                                <h3 class="related-title">
                                    <a href="product-details.php?id=<?= $related['id'] ?>" style="text-decoration: none; color: inherit;">
                                        <?= htmlspecialchars($related['name']) ?>
                                    </a>
                                </h3>
                                <div class="related-price">
                                    R$ <?= number_format($related['price'], 2, ',', '.') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Função para mostrar placeholder quando imagem falha
        function showPlaceholder(img) {
            const container = img.parentElement;
            img.style.display = 'none';
            
            if (!container.querySelector('.placeholder-overlay')) {
                const overlay = document.createElement('div');
                overlay.className = 'placeholder-overlay';
                container.appendChild(overlay);
                
                const icon = document.createElement('i');
                icon.className = 'fas fa-desktop placeholder-icon';
                icon.style.fontSize = '4rem';
                icon.style.position = 'relative';
                icon.style.zIndex = '2';
                container.appendChild(icon);
            }
        }

        let currentQuantity = 1;
        const maxQuantity = <?= $product['stock_quantity'] ?>;

        function updateQuantity() {
            document.getElementById('quantity').value = currentQuantity;
            
            // Atualizar estados dos botões
            const decreaseBtn = document.querySelector('.quantity-btn:first-child');
            const increaseBtn = document.querySelector('.quantity-btn:last-child');
            
            decreaseBtn.disabled = currentQuantity <= 1;
            increaseBtn.disabled = currentQuantity >= maxQuantity;
        }

        function decreaseQuantity() {
            if (currentQuantity > 1) {
                currentQuantity--;
                updateQuantity();
            }
        }

        function increaseQuantity() {
            if (currentQuantity < maxQuantity) {
                currentQuantity++;
                updateQuantity();
            }
        }

        // Event listener para input manual
        document.getElementById('quantity').addEventListener('change', function() {
            const value = parseInt(this.value);
            if (value >= 1 && value <= maxQuantity) {
                currentQuantity = value;
            } else if (value < 1) {
                currentQuantity = 1;
            } else {
                currentQuantity = maxQuantity;
            }
            updateQuantity();
        });

        function addToCart(productId) {
            <?php if (isLoggedIn()): ?>
                const quantity = currentQuantity;
                
                fetch('api/add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: quantity
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Feedback visual
                        const btn = event.target.closest('button');
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-check"></i> Adicionado!';
                        btn.style.background = '#27ae60';
                        
                        setTimeout(() => {
                            btn.innerHTML = originalText;
                            btn.style.background = '';
                        }, 2000);
                        
                        updateCartCount();
                    } else {
                        alert('Erro ao adicionar produto: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao adicionar produto ao carrinho');
                });
            <?php else: ?>
                alert('Faça login para adicionar produtos ao carrinho');
                window.location.href = 'login.php';
            <?php endif; ?>
        }

        function buyNow(productId) {
            <?php if (isLoggedIn()): ?>
                // Adicionar ao carrinho e redirecionar para checkout
                addToCart(productId);
                setTimeout(() => {
                    window.location.href = 'checkout.php';
                }, 1000);
            <?php else: ?>
                alert('Faça login para comprar');
                window.location.href = 'login.php';
            <?php endif; ?>
        }

        function updateCartCount() {
            <?php if (isLoggedIn()): ?>
                fetch('api/cart_count.php')
                    .then(response => response.json())
                    .then(data => {
                        document.querySelector('.cart-count').textContent = data.count;
                    })
                    .catch(error => console.error('Erro:', error));
            <?php endif; ?>
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            updateQuantity();
            updateCartCount();
        });

        // Teclas de atalho
        document.addEventListener('keydown', function(e) {
            if (e.key === '+' || e.key === '=') {
                e.preventDefault();
                increaseQuantity();
            } else if (e.key === '-') {
                e.preventDefault();
                decreaseQuantity();
            }
        });
    </script>
</body>
</html>