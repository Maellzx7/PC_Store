<?php
require_once '../config/database.php';

requireLogin();
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Estatísticas do painel
$stats = [];

// Total de usuários
$query = "SELECT COUNT(*) as count FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total de produtos
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

// Produtos recentes
$query = "SELECT p.*, c.name as category_name, u.name as seller_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN users u ON p.seller_id = u.id 
          ORDER BY p.created_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pedidos recentes
$query = "SELECT o.*, u.name as user_name 
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          ORDER BY o.created_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - PC Store</title>
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

.admin-container {
    display: grid;
    grid-template-columns: 280px 1fr;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    background: linear-gradient(135deg, #2c3e50, #3498db);
    color: white;
    padding: 2rem 0;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    position: relative;
}

.admin-logo {
    text-align: center;
    padding: 0 2rem 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 2rem;
}

.admin-logo i {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.admin-logo h2 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.admin-logo p {
    opacity: 0.8;
    font-size: 0.9rem;
}

.nav-menu {
    list-style: none;
    padding: 0 1rem;
    margin-bottom: 1rem;
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

/* Botão de logout atualizado - menor e logo abaixo das configurações */
.logout-section {
    padding: 0 1rem;
    margin-top: 0.5rem;
}

.logout-section a {
    display: flex;
    align-items: center;
    padding: 0.6rem 0.8rem;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s ease;
    background: rgba(231, 76, 60, 0.2);
    font-size: 0.85rem;
    border: 1px solid rgba(231, 76, 60, 0.3);
}

.logout-section a:hover {
    background: rgba(231, 76, 60, 0.3);
    color: white;
    transform: translateX(2px);
}

.logout-section i {
    width: 14px;
    margin-right: 0.6rem;
    text-align: center;
    font-size: 0.8rem;
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
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.stat-card-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    display: inline-block;
}

.stat-card-users .stat-card-icon { color: #3498db; }
.stat-card-products .stat-card-icon { color: #2ecc71; }
.stat-card-orders .stat-card-icon { color: #e74c3c; }
.stat-card-revenue .stat-card-icon { color: #f39c12; }

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
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
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

@media (max-width: 1024px) {
    .admin-container {
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
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="admin-logo">
                <i class="fas fa-crown"></i>
                <h2>Admin Panel</h2>
                <p>PC Store Management</p>
            </div>
            
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-box"></i> Produtos</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categorias</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Usuários</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Pedidos</a></li>
                <li><a href="sellers.php"><i class="fas fa-store"></i> Vendedores</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Relatórios</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Configurações</a></li>
                
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
                <h1>Dashboard</h1>
                <p>Visão geral do sistema de e-commerce</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-card-users">
                    <div class="stat-card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['users']) ?></div>
                    <div class="stat-card-label">Usuários Cadastrados</div>
                </div>

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
                    <div class="stat-card-label">Pedidos Realizados</div>
                </div>

                <div class="stat-card stat-card-revenue">
                    <div class="stat-card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-card-value">R$ <?= number_format($stats['revenue'], 2, ',', '.') ?></div>
                    <div class="stat-card-label">Receita Total</div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="two-column">
                <!-- Recent Products -->
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-box"></i>
                        Produtos Recentes
                    </h2>
                    
                    <?php if (!empty($recent_products)): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Categoria</th>
                                    <th>Preço</th>
                                    <th>Vendedor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_products as $product): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($product['name']) ?></strong>
                                            <br>
                                            <small class="badge badge-success">
                                                <?= $product['stock_quantity'] ?> em estoque
                                            </small>
                                        </td>
                                        <td><?= htmlspecialchars($product['category_name'] ?? 'Sem categoria') ?></td>
                                        <td class="price">R$ <?= number_format($product['price'], 2, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($product['seller_name'] ?? 'Admin') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="products.php" class="btn btn-primary">
                                <i class="fas fa-eye"></i> Ver Todos os Produtos
                            </a>
                        </div>
                    <?php else: ?>
                        <p>Nenhum produto cadastrado ainda.</p>
                    <?php endif; ?>
                </div>

                <!-- Recent Orders -->
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-shopping-cart"></i>
                        Pedidos Recentes
                    </h2>
                    
                    <?php if (!empty($recent_orders)): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Cliente</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?= $order['id'] ?></strong></td>
                                        <td><?= htmlspecialchars($order['user_name']) ?></td>
                                        <td class="price">R$ <?= number_format($order['total_amount'], 2, ',', '.') ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch($order['status']) {
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
                                                    $status_text = ucfirst($order['status']);
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
                                <i class="fas fa-eye"></i> Ver Todos os Pedidos
                            </a>
                        </div>
                    <?php else: ?>
                        <p>Nenhum pedido realizado ainda.</p>
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
                    <a href="products.php?action=add" class="btn btn-primary" style="justify-content: center; padding: 2rem;">
                        <i class="fas fa-plus"></i> Adicionar Produto
                    </a>
                    <a href="categories.php?action=add" class="btn btn-primary" style="justify-content: center; padding: 2rem;">
                        <i class="fas fa-tag"></i> Nova Categoria
                    </a>
                    <a href="users.php?action=add_seller" class="btn btn-primary" style="justify-content: center; padding: 2rem;">
                        <i class="fas fa-user-tie"></i> Adicionar Vendedor
                    </a>
                    <a href="reports.php" class="btn btn-primary" style="justify-content: center; padding: 2rem;">
                        <i class="fas fa-chart-bar"></i> Ver Relatórios
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            fetch('api/stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelector('.stat-card-users .stat-card-value').textContent = 
                            new Intl.NumberFormat('pt-BR').format(data.stats.users);
                        document.querySelector('.stat-card-products .stat-card-value').textContent = 
                            new Intl.NumberFormat('pt-BR').format(data.stats.products);
                        document.querySelector('.stat-card-orders .stat-card-value').textContent = 
                            new Intl.NumberFormat('pt-BR').format(data.stats.orders);
                        document.querySelector('.stat-card-revenue .stat-card-value').textContent = 
                            'R$ ' + new Intl.NumberFormat('pt-BR', {minimumFractionDigits: 2}).format(data.stats.revenue);
                    }
                })
                .catch(error => console.error('Erro ao atualizar estatísticas:', error));
        }, 30000);

        // Mobile menu toggle (se necessário)
        function toggleMobileMenu() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-open');
        }
    </script>
</body>
</html>