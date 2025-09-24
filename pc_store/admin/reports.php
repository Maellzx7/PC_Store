<?php
require_once '../config/database.php';

requireLogin();
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Parâmetros de filtro
$period = isset($_GET['period']) ? sanitize($_GET['period']) : 'last_30_days';
$report_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'sales';

// Definir período
$date_condition = '';
switch ($period) {
    case 'today':
        $date_condition = "DATE(created_at) = CURDATE()";
        break;
    case 'yesterday':
        $date_condition = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'last_7_days':
        $date_condition = "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'last_30_days':
        $date_condition = "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'this_month':
        $date_condition = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
        break;
    case 'last_month':
        $date_condition = "MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        break;
    case 'this_year':
        $date_condition = "YEAR(created_at) = YEAR(CURDATE())";
        break;
    default:
        $date_condition = "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

// Relatório de Vendas
$sales_data = [];
if ($report_type === 'sales') {
    // Vendas por dia
    $query = "SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue
              FROM orders 
              WHERE $date_condition AND status != 'cancelled'
              GROUP BY DATE(created_at)
              ORDER BY date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Top produtos
$top_products = [];
$query = "SELECT p.name, p.price, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as revenue
          FROM products p
          INNER JOIN order_items oi ON p.id = oi.product_id
          INNER JOIN orders o ON oi.order_id = o.id
          WHERE o.$date_condition AND o.status != 'cancelled'
          GROUP BY p.id, p.name, p.price
          ORDER BY total_sold DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top categorias
$top_categories = [];
$query = "SELECT c.name, COUNT(oi.id) as total_items, SUM(oi.quantity * oi.price) as revenue
          FROM categories c
          INNER JOIN products p ON c.id = p.category_id
          INNER JOIN order_items oi ON p.id = oi.product_id
          INNER JOIN orders o ON oi.order_id = o.id
          WHERE o.$date_condition AND o.status != 'cancelled'
          GROUP BY c.id, c.name
          ORDER BY revenue DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$top_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vendedores performance
$seller_performance = [];
$query = "SELECT u.name, u.email,
          COUNT(DISTINCT o.id) as total_orders,
          SUM(oi.quantity) as items_sold,
          SUM(oi.quantity * oi.price) as revenue
          FROM users u
          INNER JOIN products p ON u.id = p.seller_id
          INNER JOIN order_items oi ON p.id = oi.product_id
          INNER JOIN orders o ON oi.order_id = o.id
          WHERE o.$date_condition AND o.status != 'cancelled' AND u.user_type IN ('seller', 'admin')
          GROUP BY u.id, u.name, u.email
          ORDER BY revenue DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$seller_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas gerais
$general_stats = [];

// Total de vendas no período
$query = "SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_revenue
          FROM orders 
          WHERE $date_condition AND status != 'cancelled'";
$stmt = $db->prepare($query);
$stmt->execute();
$general_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Novos clientes no período
$query = "SELECT COUNT(*) as new_customers
          FROM users 
          WHERE $date_condition AND user_type = 'user'";
$stmt = $db->prepare($query);
$stmt->execute();
$general_stats['new_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['new_customers'];

// Produtos cadastrados no período
$query = "SELECT COUNT(*) as new_products
          FROM products 
          WHERE $date_condition AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$general_stats['new_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['new_products'];

// Ticket médio
$general_stats['avg_order_value'] = $general_stats['total_orders'] > 0 
    ? $general_stats['total_revenue'] / $general_stats['total_orders'] 
    : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - PC Store Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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

        /* Main Content */
        .main-content {
            padding: 2rem;
            overflow-y: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .page-title p {
            color: #7f8c8d;
            font-size: 1.1rem;
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

        .filter-group select {
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .btn {
            padding: 0.8rem 1.5rem;
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
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
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
            background: linear-gradient(135deg, #3498db, #2c3e50);
        }

        .stat-card-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: inline-block;
        }

        .stat-card-orders .stat-card-icon { color: #3498db; }
        .stat-card-revenue .stat-card-icon { color: #27ae60; }
        .stat-card-customers .stat-card-icon { color: #e74c3c; }
        .stat-card-products .stat-card-icon { color: #f39c12; }

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

        /* Charts and Tables */
        .reports-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .report-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .report-section.full-width {
            grid-column: 1 / -1;
        }

        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .table td {
            font-size: 0.9rem;
        }

        .price {
            color: #27ae60;
            font-weight: bold;
        }

        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #3498db, #5dade2);
            color: white;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .admin-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .reports-grid {
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
            
            .page-title h1 {
                font-size: 2rem;
            }
            
            .export-buttons {
                flex-direction: column;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
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
            </div>
            
            <ul class="nav-menu">
                <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-box"></i> Produtos</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categorias</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Usuários</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Pedidos</a></li>
                <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Relatórios</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Relatórios</h1>
                    <p>Análise detalhada de performance da loja</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="period">Período</label>
                            <select id="period" name="period">
                                <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Hoje</option>
                                <option value="yesterday" <?= $period === 'yesterday' ? 'selected' : '' ?>>Ontem</option>
                                <option value="last_7_days" <?= $period === 'last_7_days' ? 'selected' : '' ?>>Últimos 7 dias</option>
                                <option value="last_30_days" <?= $period === 'last_30_days' ? 'selected' : '' ?>>Últimos 30 dias</option>
                                <option value="this_month" <?= $period === 'this_month' ? 'selected' : '' ?>>Este mês</option>
                                <option value="last_month" <?= $period === 'last_month' ? 'selected' : '' ?>>Mês passado</option>
                                <option value="this_year" <?= $period === 'this_year' ? 'selected' : '' ?>>Este ano</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="type">Tipo de Relatório</label>
                            <select id="type" name="type">
                                <option value="sales" <?= $report_type === 'sales' ? 'selected' : '' ?>>Vendas</option>
                                <option value="products" <?= $report_type === 'products' ? 'selected' : '' ?>>Produtos</option>
                                <option value="customers" <?= $report_type === 'customers' ? 'selected' : '' ?>>Clientes</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-chart-bar"></i> Gerar Relatório
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Export Buttons -->
            <div class="export-buttons">
                <button class="btn btn-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
                <button class="btn btn-info" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </button>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-card-orders">
                    <div class="stat-card-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($general_stats['total_orders']) ?></div>
                    <div class="stat-card-label">Total de Pedidos</div>
                </div>

                <div class="stat-card stat-card-revenue">
                    <div class="stat-card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-card-value">R$ <?= number_format($general_stats['total_revenue'], 2, ',', '.') ?></div>
                    <div class="stat-card-label">Receita Total</div>
                </div>

                <div class="stat-card stat-card-customers">
                    <div class="stat-card-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($general_stats['new_customers']) ?></div>
                    <div class="stat-card-label">Novos Clientes</div>
                </div>

                <div class="stat-card stat-card-products">
                    <div class="stat-card-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-card-value">R$ <?= number_format($general_stats['avg_order_value'], 2, ',', '.') ?></div>
                    <div class="stat-card-label">Ticket Médio</div>
                </div>
            </div>

            <!-- Charts and Tables -->
            <div class="reports-grid">
                <!-- Sales Chart -->
                <?php if (!empty($sales_data)): ?>
                <div class="report-section full-width">
                    <h2 class="section-title">
                        <i class="fas fa-chart-line"></i>
                        Vendas por Dia
                    </h2>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Top Products -->
                <div class="report-section">
                    <h2 class="section-title">
                        <i class="fas fa-medal"></i>
                        Top Produtos
                    </h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Vendidos</th>
                                <th>Receita</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($top_products, 0, 5) as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= $product['total_sold'] ?></td>
                                    <td class="price">R$ <?= number_format($product['revenue'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Top Categories -->
                <div class="report-section">
                    <h2 class="section-title">
                        <i class="fas fa-tags"></i>
                        Top Categorias
                    </h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Categoria</th>
                                <th>Itens</th>
                                <th>Receita</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($top_categories, 0, 5) as $category): ?>
                                <tr>
                                    <td><?= htmlspecialchars($category['name']) ?></td>
                                    <td><?= $category['total_items'] ?></td>
                                    <td class="price">R$ <?= number_format($category['revenue'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Seller Performance -->
            <div class="report-section">
                <h2 class="section-title">
                    <i class="fas fa-users"></i>
                    Performance dos Vendedores
                </h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th>E-mail</th>
                            <th>Pedidos</th>
                            <th>Itens Vendidos</th>
                            <th>Receita</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($seller_performance as $seller): ?>
                            <tr>
                                <td><?= htmlspecialchars($seller['name']) ?></td>
                                <td><?= htmlspecialchars($seller['email']) ?></td>
                                <td><?= $seller['total_orders'] ?></td>
                                <td><?= $seller['items_sold'] ?></td>
                                <td class="price">R$ <?= number_format($seller['revenue'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        // Chart.js - Sales Chart
        <?php if (!empty($sales_data)): ?>
        const salesData = <?= json_encode($sales_data) ?>;
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('pt-BR');
                }),
                datasets: [
                    {
                        label: 'Receita (R$)',
                        data: salesData.map(item => parseFloat(item.revenue)),
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Pedidos',
                        data: salesData.map(item => parseInt(item.orders)),
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Receita: R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                } else {
                                    return 'Pedidos: ' + context.parsed.y;
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Data'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Receita (R$)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Número de Pedidos'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        <?php endif; ?>

        // Auto-submit filters
        document.querySelectorAll('#period, #type').forEach(element => {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Export functions
        function exportToExcel() {
            alert('Funcionalidade de exportação para Excel em desenvolvimento!');
            // Implementar exportação real
        }

        function exportToPDF() {
            alert('Funcionalidade de exportação para PDF em desenvolvimento!');
            // Implementar exportação real
        }

        // Print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                .sidebar, .export-buttons, .filters {
                    display: none !important;
                }
                .admin-container {
                    grid-template-columns: 1fr !important;
                }
                .main-content {
                    padding: 0 !important;
                }
                .report-section, .stat-card {
                    break-inside: avoid;
                    box-shadow: none !important;
                    border: 1px solid #ddd !important;
                }
            }
        `;
        document.head.appendChild(style);

        // Real-time updates (optional)
        function refreshReports() {
            const currentUrl = new URL(window.location);
            fetch(currentUrl.pathname + currentUrl.search)
                .then(response => response.text())
                .then(html => {
                    // Real-time updates (optional)
        function refreshReports() {
            const currentUrl = new URL(window.location);
            fetch(currentUrl.pathname + currentUrl.search)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update stats cards
                    const statsGrid = document.querySelector('.stats-grid');
                    const newStatsGrid = doc.querySelector('.stats-grid');
                    if (statsGrid && newStatsGrid) {
                        statsGrid.innerHTML = newStatsGrid.innerHTML;
                    }
                    
                    // Update tables
                    const tables = document.querySelectorAll('.table tbody');
                    const newTables = doc.querySelectorAll('.table tbody');
                    tables.forEach((table, index) => {
                        if (newTables[index]) {
                            table.innerHTML = newTables[index].innerHTML;
                        }
                    });
                    
                    console.log('Relatórios atualizados em tempo real');
                })
                .catch(error => {
                    console.error('Erro ao atualizar relatórios:', error);
                });
        }

        // Auto-refresh every 5 minutes (optional)
        // setInterval(refreshReports, 300000);

        // Smooth scrolling for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Loading states for buttons
        function addLoadingState(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            button.disabled = true;
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 2000);
        }

        // Add loading states to export buttons
        document.querySelector('[onclick="exportToExcel()"]').addEventListener('click', function() {
            addLoadingState(this);
        });

        document.querySelector('[onclick="exportToPDF()"]').addEventListener('click', function() {
            addLoadingState(this);
        });

        // Tooltips for stats cards
        function addTooltips() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                const label = card.querySelector('.stat-card-label').textContent;
                const value = card.querySelector('.stat-card-value').textContent;
                card.title = `${label}: ${value}`;
            });
        }

        // Initialize tooltips
        addTooltips();

        // Format numbers on load
        function formatNumbers() {
            const priceElements = document.querySelectorAll('.price');
            priceElements.forEach(element => {
                const text = element.textContent;
                if (text.includes('R$')) {
                    element.style.fontWeight = 'bold';
                    element.style.color = '#27ae60';
                }
            });
        }

        formatNumbers();

        // Responsive chart handling
        function handleResponsiveChart() {
            const chartContainer = document.querySelector('.chart-container');
            if (chartContainer && window.innerWidth < 768) {
                chartContainer.style.height = '250px';
            } else if (chartContainer) {
                chartContainer.style.height = '300px';
            }
        }

        window.addEventListener('resize', handleResponsiveChart);
        handleResponsiveChart();

        // Performance monitoring
        if (window.performance && window.performance.mark) {
            window.performance.mark('reports-loaded');
            
            window.addEventListener('load', function() {
                setTimeout(function() {
                    const perfData = window.performance.getEntriesByType('navigation')[0];
                    if (perfData) {
                        console.log(`Página carregada em ${Math.round(perfData.loadEventEnd - perfData.fetchStart)}ms`);
                    }
                }, 0);
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + P for print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            
            // Ctrl + E for Excel export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportToExcel();
            }
            
            // Ctrl + R for refresh (override default)
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshReports();
            }
        });

        // Data validation and error handling
        function validateData() {
            const statValues = document.querySelectorAll('.stat-card-value');
            statValues.forEach(value => {
                const text = value.textContent.trim();
                if (text === '' || text === 'NaN' || text === 'undefined') {
                    value.textContent = '0';
                    value.parentElement.style.opacity = '0.6';
                }
            });
        }

        validateData();

        // Add animation delays for stats cards
        function animateStatsCards() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }

        // Initialize animations on load
        document.addEventListener('DOMContentLoaded', animateStatsCards);
    </script>
</body>
</html>

<?php
// Additional PHP functions for future use

// Function to export data to CSV format
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

// Function to generate PDF reports (requires TCPDF or similar)
function generatePDFReport($data, $title = 'Relatório') {
    // This would require a PDF library like TCPDF
    // Implementation would depend on the chosen library
    
    // Example structure:
    /*
    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    
    // Add data to PDF
    foreach ($data as $row) {
        // Add row data
    }
    
    $pdf->Output($title . '.pdf', 'D');
    */
}

// Function to calculate growth percentage
function calculateGrowth($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    
    return round((($current - $previous) / $previous) * 100, 2);
}

// Function to get previous period data for comparison
function getPreviousPeriodData($period, $db) {
    $previous_condition = '';
    
    switch ($period) {
        case 'today':
            $previous_condition = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'yesterday':
            $previous_condition = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 2 DAY)";
            break;
        case 'last_7_days':
            $previous_condition = "created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'last_30_days':
            $previous_condition = "created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'this_month':
            $previous_condition = "MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            break;
        default:
            $previous_condition = "created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    }
    
    $query = "SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_revenue
              FROM orders 
              WHERE $previous_condition AND status != 'cancelled'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle AJAX requests for real-time updates
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    
    $response = [
        'stats' => $general_stats,
        'top_products' => array_slice($top_products, 0, 5),
        'top_categories' => array_slice($top_categories, 0, 5),
        'sales_data' => $sales_data,
        'timestamp' => time()
    ];
    
    echo json_encode($response);
    exit;
}

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $export_data = [];
    
    switch ($_GET['data']) {
        case 'products':
            $export_data = $top_products;
            break;
        case 'categories':
            $export_data = $top_categories;
            break;
        case 'sellers':
            $export_data = $seller_performance;
            break;
        case 'sales':
            $export_data = $sales_data;
            break;
    }
    
    if ($export_type === 'csv' && !empty($export_data)) {
        exportToCSV($export_data, 'relatorio_' . $_GET['data'] . '_' . date('Y-m-d'));
    }
}
?>