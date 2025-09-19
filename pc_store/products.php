<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Parâmetros de filtro
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$price_min = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$price_max = isset($_GET['price_max']) ? (float)$_GET['price_max'] : 99999;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';

// Construir query
$where_conditions = ["p.status = 'active'"];
$params = [];

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE :search OR p.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($price_min > 0) {
    $where_conditions[] = "p.price >= :price_min";
    $params[':price_min'] = $price_min;
}

if ($price_max < 99999) {
    $where_conditions[] = "p.price <= :price_max";
    $params[':price_max'] = $price_max;
}

// Ordenação
$order_by = "p.name ASC";
switch ($sort) {
    case 'price_asc':
        $order_by = "p.price ASC";
        break;
    case 'price_desc':
        $order_by = "p.price DESC";
        break;
    case 'newest':
        $order_by = "p.created_at DESC";
        break;
}

$where_clause = implode(" AND ", $where_conditions);

// Buscar produtos
$query = "SELECT p.*, c.name as category_name, u.name as seller_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN users u ON p.seller_id = u.id 
          WHERE $where_clause 
          ORDER BY $order_by";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar categorias para filtro
$query = "SELECT * FROM categories ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - PC Store</title>
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

        /* Filters */
        .filters {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Products Grid */
        .products-container {
            display: flex;
            gap: 2rem;
        }

        .products-sidebar {
            width: 300px;
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            height: fit-content;
            position: sticky;
            top: 120px;
        }

        .products-main {
            flex: 1;
        }

        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .products-count {
            color: #7f8c8d;
        }

        .sort-select {
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            background: white;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .product-image {
            height: 200px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .product-image:hover {
            transform: scale(1.05);
        }

        .product-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="circuit" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M0 10h20M10 0v20M5 5h10v10H5z" stroke="rgba(255,255,255,0.1)" stroke-width="0.5" fill="none"/></pattern></defs><rect width="100" height="100" fill="url(%23circuit)"/></svg>');
            opacity: 0.3;
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.9);
            color: #27ae60;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-category {
            color: #667eea;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .product-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .product-title a:hover {
            color: #667eea;
        }

        .product-card:hover .product-title a {
            color: #667eea;
        }

        .product-description {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #27ae60;
            margin-bottom: 1rem;
        }

        .product-seller {
            font-size: 0.8rem;
            color: #95a5a6;
            margin-bottom: 1rem;
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
        }

        .no-products {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .no-products i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 2rem;
        }

        .no-products h2 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .no-products p {
            color: #7f8c8d;
            margin-bottom: 2rem;
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
            .products-container {
                flex-direction: column;
            }
            
            .products-sidebar {
                width: 100%;
                position: static;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .main-content {
                padding: 1rem 0;
            }
            
            .filters {
                padding: 1rem;
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
                <li><a href="products.php" style="color: #667eea;">Produtos</a></li>
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

    <main class="main-content container">
        <div class="page-header">
            <h1><i class="fas fa-shopping-bag"></i> Nossos Produtos</h1>
            <p>Encontre os melhores PCs, periféricos e componentes</p>
        </div>

        <!-- Filtros -->
        <div class="filters">
            <form method="GET" id="filterForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="search">Buscar</label>
                        <input type="text" id="search" name="search" 
                               value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Nome ou descrição...">
                    </div>
                    
                    <div class="filter-group">
                        <label for="category">Categoria</label>
                        <select id="category" name="category">
                            <option value="">Todas as categorias</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" 
                                        <?= ($category_filter == $category['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="price_min">Preço Mínimo</label>
                        <input type="number" id="price_min" name="price_min" 
                               value="<?= $price_min > 0 ? $price_min : '' ?>" 
                               placeholder="R$ 0,00" step="0.01">
                    </div>
                    
                    <div class="filter-group">
                        <label for="price_max">Preço Máximo</label>
                        <input type="number" id="price_max" name="price_max" 
                               value="<?= $price_max < 99999 ? $price_max : '' ?>" 
                               placeholder="R$ 9999,99" step="0.01">
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="products-container">
            <div class="products-main">
                <div class="products-header">
                    <div class="products-count">
                        <?= count($products) ?> produto(s) encontrado(s)
                        <?php if (!empty($search)): ?>
                            para "<?= htmlspecialchars($search) ?>"
                        <?php endif; ?>
                    </div>
                    
                    <form method="GET" style="display: inline;">
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'sort'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <select name="sort" class="sort-select" onchange="this.form.submit()">
                            <option value="name" <?= ($sort === 'name') ? 'selected' : '' ?>>Nome A-Z</option>
                            <option value="price_asc" <?= ($sort === 'price_asc') ? 'selected' : '' ?>>Menor Preço</option>
                            <option value="price_desc" <?= ($sort === 'price_desc') ? 'selected' : '' ?>>Maior Preço</option>
                            <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Mais Recentes</option>
                        </select>
                    </form>
                </div>

                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <i class="fas fa-search"></i>
                        <h2>Nenhum produto encontrado</h2>
                        <p>Tente ajustar os filtros ou buscar por outros termos.</p>
                        <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-refresh"></i> Limpar Filtros
                        </a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <a href="product.php?id=<?= $product['id'] ?>" style="text-decoration: none;">
                                    <div class="product-image">
                                        <?php if ($product['stock_quantity'] > 0): ?>
                                            <div class="product-badge">Em Estoque</div>
                                        <?php else: ?>
                                            <div class="product-badge" style="background: rgba(231, 76, 60, 0.9); color: white;">Esgotado</div>
                                        <?php endif; ?>
                                        <i class="fas fa-desktop"></i>
                                    </div>
                                </a>
                                
                                <div class="product-info">
                                    <div class="product-category"><?= htmlspecialchars($product['category_name'] ?? 'Sem categoria') ?></div>
                                    <h3 class="product-title">
                                        <a href="product.php?id=<?= $product['id'] ?>" style="text-decoration: none; color: inherit;">
                                            <?= htmlspecialchars($product['name']) ?>
                                        </a>
                                    </h3>
                                    <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                                    <div class="product-price">R$ <?= number_format($product['price'], 2, ',', '.') ?></div>
                                    <div class="product-seller">
                                        <i class="fas fa-store"></i> 
                                        Por: <?= htmlspecialchars($product['seller_name'] ?? 'PC Store') ?>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <?php if ($product['stock_quantity'] > 0): ?>
                                            <button class="btn btn-primary" onclick="addToCart(<?= $product['id'] ?>)" style="flex: 1;">
                                                <i class="fas fa-cart-plus"></i> Adicionar
                                            </button>
                                        <?php else: ?>
                                            <button class="btn" style="background: #bdc3c7; color: white; flex: 1;" disabled>
                                                <i class="fas fa-times"></i> Esgotado
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="product.php?id=<?= $product['id'] ?>" class="btn btn-outline">
                                            <i class="fas fa-eye"></i> Detalhes
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Função para adicionar produto ao carrinho
        function addToCart(productId) {
            <?php if (isLoggedIn()): ?>
                fetch('api/add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: 1
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Produto adicionado ao carrinho!');
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

        // Função para atualizar contador do carrinho
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

        // Auto-submit filtros com delay
        let filterTimeout;
        document.querySelectorAll('#filterForm input').forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(() => {
                    document.getElementById('filterForm').submit();
                }, 1000);
            });
        });

        // Carregar contador do carrinho
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            
            // Adicionar indicador visual de que os produtos são clicáveis
            document.querySelectorAll('.product-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.cursor = 'pointer';
                });
                
                // Adicionar clique no card inteiro (exceto nos botões)
                card.addEventListener('click', function(e) {
                    // Não executar se clicou em um botão
                    if (e.target.closest('.btn') || e.target.closest('button')) {
                        return;
                    }
                    
                    const productLink = this.querySelector('.product-title a');
                    if (productLink) {
                        window.location.href = productLink.href;
                    }
                });
            });
        });
    </script>
</body>
</html>