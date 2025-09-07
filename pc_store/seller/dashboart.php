<?php
require_once '../config/database.php';

requireLogin();
requireSeller();

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

// Estatísticas do vendedor
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

// Produtos do vendedor
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.seller_id = :seller_id 
          ORDER BY p.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':seller_id', $seller_id);
$stmt->execute();
$seller_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vendas recentes do vendedor
$query = "SELECT o.*, u.name as user_name, oi.quantity, oi.price, p.name as product_name
          FROM orders o 
          INNER JOIN order_items oi ON o.id = oi.order_id 
          INNER JOIN products p ON oi.product_id = p.id 
          INNER JOIN users u ON o.user_id = u.id 
          WHERE p.seller_id = :seller_id 
          ORDER BY o.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':seller_id', $seller_id);
$stmt->execute();
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Vendedor - PC Store</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .seller-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 2rem 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .seller-logo {
            text-align: center;
            padding: 0 2rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 2rem;
        }

        .seller-logo i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .seller-logo h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .seller-logo p {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .nav-menu {
            list-style: none;
            padding: 0 1rem;
        }

        .nav-menu li {
            margin-bottom: 0.5rem;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            padding: 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-menu i {
            width: 20px;
            margin-right: 1rem;
            text-align: center;
        }

        .logout-section {
            position: absolute;
            bottom: 2rem;
            left: 1rem;
            right: 1rem;
        }

        .logout-section a {
            display: flex;
            align-items: center;
            padding: 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            background: rgba(231, 76, 60, 0.2);
        }

        .logout-section a:hover {
            background: rgba(231, 76, 60, 0.3);
            color: white;
        }

        /* Main Content */
        .main-content {
            padding: 2rem;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }

        .stat-card-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: inline-block;
        }

        .stat-card-products .stat-card-icon { color: #27ae60; }
        .stat-card-orders .stat-card-icon { color: #3498db; }
        .stat-card-revenue .stat-card-icon { color: #f39c12; }
        .stat-card-stock .stat-card-icon { color: #e74c3c; }

        .stat-card-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .stat-card-label {
            color: #7f8c8d;
            font-size: 0.95rem;
        }

        /* Content Sections */
        .content-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #27ae60;
            color: #27ae60;
        }

        .btn-outline:hover {
            background: #27ae60;
            color: white;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .price {
            color: #27ae60;
            font-weight: bold;
        }

        .low-stock {
            color: #e74c3c;
            font-weight: bold;
        }

        @media (max-width: 1024px) {
            .seller-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .two-column {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .table {
                font-size: 0.9rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="seller-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="seller-logo">
                <i class="fas fa-store"></i>
                <h2>Painel Vendedor</h2>
                <p><?= htmlspecialchars($_SESSION['user_name']) ?></p>
            </div>
            
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-box"></i> Meus Produtos</a></li>
                <li><a href="add_product.php"><i class="fas fa-plus"></i> Adicionar Produto</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Vendas</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Relatórios</a></li>
                <li><a href="../index.php"><i class="fas fa-home"></i> Ver Loja</a></li>
            </ul>
            
            <div class="logout-section">
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>Dashboard do Vendedor</h1>
                <p>Gerencie seus produtos e acompanhe suas vendas</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-card-products">
                    <div class="stat-card-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['products']) ?></div>
                    <div class="stat-card-label">Produtos Ativos</div>
                </div>

                <div class="stat-card stat-card-orders">
                    <div class="stat-card-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['orders']) ?></div>
                    <div class="stat-card-label">Vendas Realizadas</div>
                </div>

                <div class="stat-card stat-card-revenue">
                    <div class="stat-card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-card-value">R$ <?= number_format($stats['revenue'], 2, ',', '.') ?></div>
                    <div class="stat-card-label">Receita Total</div>
                </div>

                <div class="stat-card stat-card-stock">
                    <div class="stat-card-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['low_stock']) ?></div>
                    <div class="stat-card-label">Baixo Estoque</div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="two-column">
                <!-- My Products -->
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-box"></i>
                        Meus Produtos
                    </h2>
                    
                    <?php if (!empty($seller_products)): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Categoria</th>
                                    <th>Preço</th>
                                    <th>Estoque</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($seller_products as $product): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($product['name']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($product['category_name'] ?? 'Sem categoria') ?></td>
                                        <td class="price">R$ <?= number_format($product['price'], 2, ',', '.') ?></td>
                                        <td>
                                            <?php if ($product['stock_quantity'] <= 5): ?>
                                                <span class="low-stock"><?= $product['stock_quantity'] ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-success"><?= $product['stock_quantity'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="products.php" class="btn btn-primary">
                                <i class="fas fa-eye"></i> Ver Todos
                            </a>
                            <a href="add_product.php" class="btn btn-outline">
                                <i class="fas fa-plus"></i> Adicionar
                            </a>
                        </div>
                    <?php else: ?>
                        <p>Você ainda não tem produtos cadastrados.</p>
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="add_product.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Cadastrar Primeiro Produto
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Sales -->
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-chart-line"></i>
                        Vendas Recentes
                    </h2>
                    
                    <?php if (!empty($recent_sales)): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Produto</th>
                                    <th>Cliente</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td><strong>#<?= $sale['id'] ?></strong></td>
                                        <td>
                                            <?= htmlspecialchars($sale['product_name']) ?>
                                            <br>
                                            <small>Qtd: <?= $sale['quantity'] ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($sale['user_name']) ?></td>
                                        <td class="price">R$ <?= number_format($sale['price'] * $sale['quantity'], 2, ',', '.') ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch($sale['status']) {
                                                case 'pending':
                                                    $status_class = 'badge-warning';
                                                    $status_text = 'Pendente';
                                                    break;
                                                case 'processing':
                                                    $status_class = 'badge-info';
                                                    $status_text = 'Processando';
                                                    break;
                                                case 'delivered':
                                                    $status_class = 'badge-success';
                                                    $status_text = 'Entregue';
                                                    break;
                                                default:
                                                    $status_class = 'badge-info';
                                                    $status_text = ucfirst($sale['status']);
                                            }
                                            ?>
                                            <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="orders.php" class="btn btn-primary">
                                <i class="fas fa-eye"></i> Ver Todas as Vendas
                            </a>
                        </div>
                    <?php else: ?>
                        <p>Nenhuma venda realizada ainda.</p>
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="add_product.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Cadastrar Produtos
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-bolt"></i>
                    Ações Rápidas
                </h2>
                
                <div class="stats-grid">
                    <a href="add_product.php" class="btn btn-primary" style="justify-content: center; padding: 2rem;">
                        <i class="fas fa-plus"></i> Adicionar Produto
                    </a>
                    <a href="products.php" class="btn btn-primary" style="justify-content: center; padding: 2rem;">
                        <i class="fas fa-edit"></i> Gerenciar Produtos
                    </a>
                    <a href="orders.php" class="btn btn-primary" style="justify-content: center; padding: 2rem;">
                        <i class="fas fa-shopping-cart"></i> Ver Vendas
                    </a>
                    <a href="reports.php" class="btn btn-primary" style="justify-content: center; padding: 2rem;">
                        <i class="fas fa-chart-bar"></i> Relatórios
                    </a>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <?php if ($stats['low_stock'] > 0): ?>
            <div class="content-section" style="border-left: 4px solid #e74c3c;">
                <h2 class="section-title" style="color: #e74c3c;">
                    <i class="fas fa-exclamation-triangle"></i>
                    Alerta de Estoque Baixo
                </h2>
                <p>Você tem <strong><?= $stats['low_stock'] ?></strong> produto(s) com estoque baixo (≤ 5 unidades).</p>
                <p>Acesse o <a href="products.php?filter=low_stock" style="color: #e74c3c;">gerenciamento de produtos</a> para reabastecer.</p>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            fetch('../api/seller_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelector('.stat-card-products .stat-card-value').textContent = 
                            new Intl.NumberFormat('pt-BR').format(data.stats.products);
                        document.querySelector('.stat-card-orders .stat-card-value').textContent = 
                            new Intl.NumberFormat('pt-BR').format(data.stats.orders);
                        document.querySelector('.stat-card-revenue .stat-card-value').textContent = 
                            'R$ ' + new Intl.NumberFormat('pt-BR', {minimumFractionDigits: 2}).format(data.stats.revenue);
                        document.querySelector('.stat-card-stock .stat-card-value').textContent = 
                            new Intl.NumberFormat('pt-BR').format(data.stats.low_stock);
                    }
                })
                .catch(error => console.error('Erro ao atualizar estatísticas:', error));
        }, 30000);

        // Check for low stock notifications
        document.addEventListener('DOMContentLoaded', function() {
            const lowStock = <?= $stats['low_stock'] ?>;
            if (lowStock > 0) {
                // Could show a notification here
                console.log(`Atenção: ${lowStock} produto(s) com estoque baixo`);
            }
        });
    </script>
</body>
</html>