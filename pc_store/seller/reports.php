<?php
require_once '../config/database.php';

requireLogin();
requireSeller();

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

// Filtros para relatórios
$period_filter = $_GET['period'] ?? '30'; // 7, 30, 90, 365 dias ou 'custom'
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Configurar período baseado no filtro
$where_date = '';
$params = [':seller_id' => $seller_id];

if ($period_filter === 'custom' && !empty($date_from) && !empty($date_to)) {
    $where_date = "AND DATE(o.created_at) BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $date_from;
    $params[':date_to'] = $date_to;
} elseif (is_numeric($period_filter)) {
    $where_date = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
    $params[':days'] = (int)$period_filter;
}

// Estatísticas gerais do período
$stats_query = "SELECT 
                  COUNT(DISTINCT o.id) as total_orders,
                  COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue,
                  COALESCE(AVG(oi.price * oi.quantity), 0) as avg_order_value,
                  COUNT(DISTINCT oi.product_id) as products_sold,
                  SUM(oi.quantity) as total_quantity_sold
                FROM orders o 
                INNER JOIN order_items oi ON o.id = oi.order_id 
                INNER JOIN products p ON oi.product_id = p.id 
                WHERE p.seller_id = :seller_id AND o.status != 'cancelled' {$where_date}";

$stats_stmt = $db->prepare($stats_query);
foreach ($params as $key => $value) {
    $stats_stmt->bindValue($key, $value);
}
$stats_stmt->execute();
$period_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Vendas por dia (últimos 30 dias ou período customizado)
$daily_sales_query = "SELECT 
                        DATE(o.created_at) as sale_date,
                        COUNT(DISTINCT o.id) as orders_count,
                        COALESCE(SUM(oi.price * oi.quantity), 0) as daily_revenue
                      FROM orders o 
                      INNER JOIN order_items oi ON o.id = oi.order_id 
                      INNER JOIN products p ON oi.product_id = p.id 
                      WHERE p.seller_id = :seller_id AND o.status != 'cancelled' {$where_date}
                      GROUP BY DATE(o.created_at)
                      ORDER BY sale_date DESC
                      LIMIT 30";

$daily_stmt = $db->prepare($daily_sales_query);
foreach ($params as $key => $value) {
    $daily_stmt->bindValue($key, $value);
}
$daily_stmt->execute();
$daily_sales = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

// Produtos mais vendidos
$top_products_query = "SELECT 
                        p.name,
                        p.price,
                        SUM(oi.quantity) as total_sold,
                        COALESCE(SUM(oi.price * oi.quantity), 0) as product_revenue
                      FROM order_items oi 
                      INNER JOIN products p ON oi.product_id = p.id 
                      INNER JOIN orders o ON oi.order_id = o.id
                      WHERE p.seller_id = :seller_id AND o.status != 'cancelled' {$where_date}
                      GROUP BY p.id, p.name, p.price
                      ORDER BY total_sold DESC
                      LIMIT 10";

$top_products_stmt = $db->prepare($top_products_query);
foreach ($params as $key => $value) {
    $top_products_stmt->bindValue($key, $value);
}
$top_products_stmt->execute();
$top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorias mais vendidas
$categories_query = "SELECT 
                      c.name as category_name,
                      COUNT(DISTINCT oi.id) as items_sold,
                      SUM(oi.quantity) as total_quantity,
                      COALESCE(SUM(oi.price * oi.quantity), 0) as category_revenue
                    FROM order_items oi 
                    INNER JOIN products p ON oi.product_id = p.id 
                    INNER JOIN orders o ON oi.order_id = o.id
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.seller_id = :seller_id AND o.status != 'cancelled' {$where_date}
                    GROUP BY c.id, c.name
                    ORDER BY category_revenue DESC";

$categories_stmt = $db->prepare($categories_query);
foreach ($params as $key => $value) {
    $categories_stmt->bindValue($key, $value);
}
$categories_stmt->execute();
$categories_data = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Status dos pedidos
$status_query = "SELECT 
                  o.status,
                  COUNT(*) as status_count,
                  COALESCE(SUM(oi.price * oi.quantity), 0) as status_revenue
                FROM orders o 
                INNER JOIN order_items oi ON o.id = oi.order_id 
                INNER JOIN products p ON oi.product_id = p.id 
                WHERE p.seller_id = :seller_id {$where_date}
                GROUP BY o.status
                ORDER BY status_count DESC";

$status_stmt = $db->prepare($status_query);
foreach ($params as $key => $value) {
    $status_stmt->bindValue($key, $value);
}
$status_stmt->execute();
$status_data = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

// Comparação com período anterior
$comparison_params = [':seller_id' => $seller_id];
$comparison_where = '';

if ($period_filter === 'custom' && !empty($date_from) && !empty($date_to)) {
    $date_from_obj = new DateTime($date_from);
    $date_to_obj = new DateTime($date_to);
    $diff = $date_from_obj->diff($date_to_obj)->days;
    
    $comparison_date_from = $date_from_obj->sub(new DateInterval("P{$diff}D"))->format('Y-m-d');
    $comparison_date_to = $date_to_obj->sub(new DateInterval("P{$diff}D"))->format('Y-m-d');
    
    $comparison_where = "AND DATE(o.created_at) BETWEEN :comp_date_from AND :comp_date_to";
    $comparison_params[':comp_date_from'] = $comparison_date_from;
    $comparison_params[':comp_date_to'] = $comparison_date_to;
} elseif (is_numeric($period_filter)) {
    $days = (int)$period_filter;
    $comparison_where = "AND o.created_at BETWEEN DATE_SUB(NOW(), INTERVAL " . ($days * 2) . " DAY) AND DATE_SUB(NOW(), INTERVAL {$days} DAY)";
}

$comparison_query = "SELECT 
                      COUNT(DISTINCT o.id) as prev_orders,
                      COALESCE(SUM(oi.price * oi.quantity), 0) as prev_revenue
                    FROM orders o 
                    INNER JOIN order_items oi ON o.id = oi.order_id 
                    INNER JOIN products p ON oi.product_id = p.id 
                    WHERE p.seller_id = :seller_id AND o.status != 'cancelled' {$comparison_where}";

$comparison_stmt = $db->prepare($comparison_query);
foreach ($comparison_params as $key => $value) {
    $comparison_stmt->bindValue($key, $value);
}
$comparison_stmt->execute();
$comparison_stats = $comparison_stmt->fetch(PDO::FETCH_ASSOC);

// Calcular percentuais de mudança
$orders_change = 0;
$revenue_change = 0;

if ($comparison_stats['prev_orders'] > 0) {
    $orders_change = (($period_stats['total_orders'] - $comparison_stats['prev_orders']) / $comparison_stats['prev_orders']) * 100;
}

if ($comparison_stats['prev_revenue'] > 0) {
    $revenue_change = (($period_stats['total_revenue'] - $comparison_stats['prev_revenue']) / $comparison_stats['prev_revenue']) * 100;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - PC Store</title>
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
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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
            flex: 1;
            display: flex;
            flex-direction: column;
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
            margin-top: auto;
            padding: 1rem;
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
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .logout-section a:hover {
            background: rgba(231, 76, 60, 0.3);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.2);
        }

        .logout-section i {
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

        /* Filters */
        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .filters-title {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #27ae60;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }

        .custom-dates {
            display: none;
        }

        .custom-dates.show {
            display: contents;
        }

        .filters-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
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
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
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
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-card-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: inline-block;
        }

        .stat-card-orders .stat-card-icon { color: #27ae60; }
        .stat-card-revenue .stat-card-icon { color: #3498db; }
        .stat-card-average .stat-card-icon { color: #f39c12; }
        .stat-card-products .stat-card-icon { color: #e74c3c; }

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

        .stat-card-change {
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .change-positive {
            color: #27ae60;
        }

        .change-negative {
            color: #e74c3c;
        }

        .change-neutral {
            color: #7f8c8d;
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

        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 2rem;
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

        .progress-bar {
            background: #ecf0f1;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            transition: width 0.3s ease;
        }

        .price {
            color: #27ae60;
            font-weight: bold;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .metric-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .metric-label {
            color: #2c3e50;
            font-weight: 500;
        }

        .metric-value {
            color: #27ae60;
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
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 300px;
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
                <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-box"></i> Meus Produtos</a></li>
                <li><a href="add_product.php"><i class="fas fa-plus"></i> Adicionar Produto</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Vendas</a></li>
                <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Relatórios</a></li>
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
                <h1><i class="fas fa-chart-bar"></i> Relatórios</h1>
                <p>Análise detalhada do desempenho das suas vendas</p>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <h2 class="filters-title">
                    <i class="fas fa-calendar"></i>
                    Período de Análise
                </h2>
                
                <form method="GET" action="reports.php" id="reportForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="period">Período</label>
                            <select name="period" id="period" onchange="toggleCustomDates()">
                                <option value="7" <?= $period_filter === '7' ? 'selected' : '' ?>>Últimos 7 dias</option>
                                <option value="30" <?= $period_filter === '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
                                <option value="90" <?= $period_filter === '90' ? 'selected' : '' ?>>Últimos 90 dias</option>
                                <option value="365" <?= $period_filter === '365' ? 'selected' : '' ?>>Último ano</option>
                                <option value="custom" <?= $period_filter === 'custom' ? 'selected' : '' ?>>Personalizado</option>
                            </select>
                        </div>

                        <div class="filter-group custom-dates <?= $period_filter === 'custom' ? 'show' : '' ?>" id="customDates">
                            <label for="date_from">Data Inicial</label>
                            <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($date_from) ?>">
                        </div>

                        <div class="filter-group custom-dates <?= $period_filter === 'custom' ? 'show' : '' ?>">
                            <label for="date_to">Data Final</label>
                            <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-chart-bar"></i> Gerar Relatório
                        </button>
                        <a href="reports.php" class="btn btn-secondary">
                            <i class="fas fa-refresh"></i> Resetar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-card-orders">
                    <div class="stat-card-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($period_stats['total_orders']) ?></div>
                    <div class="stat-card-label">Total de Pedidos</div>
                    <div class="stat-card-change <?= $orders_change >= 0 ? 'change-positive' : 'change-negative' ?>">
                        <i class="fas fa-<?= $orders_change >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                        <?= abs(number_format($orders_change, 1)) ?>% vs período anterior
                    </div>
                </div>

                <div class="stat-card stat-card-revenue">
                    <div class="stat-card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-card-value">R$ <?= number_format($period_stats['total_revenue'], 2, ',', '.') ?></div>
                    <div class="stat-card-label">Receita Total</div>
                    <div class="stat-card-change <?= $revenue_change >= 0 ? 'change-positive' : 'change-negative' ?>">
                        <i class="fas fa-<?= $revenue_change >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                        <?= abs(number_format($revenue_change, 1)) ?>% vs período anterior
                    </div>
                </div>

                <div class="stat-card stat-card-average">
                    <div class="stat-card-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stat-card-value">R$ <?= number_format($period_stats['avg_order_value'], 2, ',', '.') ?></div>
                    <div class="stat-card-label">Ticket Médio</div>
                </div>

                <div class="stat-card stat-card-products">
                    <div class="stat-card-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($period_stats['products_sold']) ?></div>
                    <div class="stat-card-label">Produtos Diferentes Vendidos</div>
                </div>
            </div>

            <!-- Sales Chart -->
            <div class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-chart-line"></i>
                    Vendas por Dia
                </h2>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="two-column">
                <!-- Top Products -->
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-trophy"></i>
                        Produtos Mais Vendidos
                    </h2>
                    
                    <?php if (!empty($top_products)): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Qtd Vendida</th>
                                    <th>Receita</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $max_sold = max(array_column($top_products, 'total_sold'));
                                foreach ($top_products as $product): 
                                    $percentage = $max_sold > 0 ? ($product['total_sold'] / $max_sold) * 100 : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($product['name']) ?></strong>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </td>
                                        <td><?= number_format($product['total_sold']) ?></td>
                                        <td class="price">R$ <?= number_format($product['product_revenue'], 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Nenhum produto vendido no período selecionado.</p>
                    <?php endif; ?>
                </div>

                <!-- Categories Performance -->
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-tags"></i>
                        Performance por Categoria
                    </h2>
                    
                    <?php if (!empty($categories_data)): ?>
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="categoriesChart"></canvas>
                        </div>
                        
                        <table class="table" style="margin-top: 1rem;">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Itens Vendidos</th>
                                    <th>Receita</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories_data as $category): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($category['category_name'] ?? 'Sem categoria') ?></td>
                                        <td><?= number_format($category['total_quantity']) ?></td>
                                        <td class="price">R$ <?= number_format($category['category_revenue'], 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Nenhuma venda por categoria no período selecionado.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Status Analysis -->
            <div class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-pie-chart"></i>
                    Análise de Status dos Pedidos
                </h2>
                
                <div class="two-column">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                    
                    <div>
                        <?php 
                        $status_labels = [
                            'pending' => 'Pendentes',
                            'processing' => 'Processando',
                            'shipped' => 'Enviados',
                            'delivered' => 'Entregues',
                            'cancelled' => 'Cancelados'
                        ];
                        
                        foreach ($status_data as $status): 
                        ?>
                            <div class="metric-item">
                                <span class="metric-label">
                                    <?= $status_labels[$status['status']] ?? ucfirst($status['status']) ?>
                                </span>
                                <div>
                                    <div class="metric-value"><?= number_format($status['status_count']) ?> pedidos</div>
                                    <div class="price" style="font-size: 0.9rem;">R$ <?= number_format($status['status_revenue'], 2, ',', '.') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-chart-area"></i>
                    Métricas Principais
                </h2>
                
                <div class="two-column">
                    <div>
                        <div class="metric-item">
                            <span class="metric-label">Total de Unidades Vendidas</span>
                            <span class="metric-value"><?= number_format($period_stats['total_quantity_sold']) ?></span>
                        </div>
                        
                        <div class="metric-item">
                            <span class="metric-label">Receita Média por Dia</span>
                            <span class="metric-value">R$ <?= number_format($period_stats['total_revenue'] / max(1, count($daily_sales)), 2, ',', '.') ?></span>
                        </div>
                        
                        <div class="metric-item">
                            <span class="metric-label">Pedidos por Dia (Média)</span>
                            <span class="metric-value"><?= number_format($period_stats['total_orders'] / max(1, (int)$period_filter ?: 30), 1) ?></span>
                        </div>
                    </div>
                    
                    <div>
                        <div class="metric-item">
                            <span class="metric-label">Taxa de Conversão</span>
                            <span class="metric-value">
                                <?php 
                                $conversion_rate = $period_stats['products_sold'] > 0 ? 
                                    ($period_stats['total_orders'] / $period_stats['products_sold']) * 100 : 0;
                                echo number_format($conversion_rate, 1) . '%';
                                ?>
                            </span>
                        </div>
                        
                        <div class="metric-item">
                            <span class="metric-label">Itens por Pedido (Média)</span>
                            <span class="metric-value">
                                <?= $period_stats['total_orders'] > 0 ? 
                                    number_format($period_stats['total_quantity_sold'] / $period_stats['total_orders'], 1) : '0' ?>
                            </span>
                        </div>
                        
                        <div class="metric-item">
                            <span class="metric-label">Melhor Dia de Vendas</span>
                            <span class="metric-value">
                                <?php 
                                if (!empty($daily_sales)) {
                                    $best_day = array_reduce($daily_sales, function($best, $day) {
                                        return (!$best || $day['daily_revenue'] > $best['daily_revenue']) ? $day : $best;
                                    });
                                    echo date('d/m/Y', strtotime($best_day['sale_date']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Options -->
            <div class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-download"></i>
                    Exportar Relatório
                </h2>
                
                <div class="stats-grid">
                    <a href="export_report.php?type=pdf&<?= http_build_query($_GET) ?>" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Exportar PDF
                    </a>
                    <a href="export_report.php?type=excel&<?= http_build_query($_GET) ?>" class="btn btn-primary">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </a>
                    <a href="export_report.php?type=csv&<?= http_build_query($_GET) ?>" class="btn btn-outline">
                        <i class="fas fa-file-csv"></i> Exportar CSV
                    </a>
                    <button onclick="window.print()" class="btn btn-outline">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle custom dates visibility
        function toggleCustomDates() {
            const period = document.getElementById('period').value;
            const customDates = document.getElementById('customDates');
            const customDatesElements = document.querySelectorAll('.custom-dates');
            
            if (period === 'custom') {
                customDatesElements.forEach(el => el.classList.add('show'));
            } else {
                customDatesElements.forEach(el => el.classList.remove('show'));
            }
        }

        // Auto-submit form when period changes (except custom)
        document.getElementById('period').addEventListener('change', function() {
            if (this.value !== 'custom') {
                document.getElementById('reportForm').submit();
            }
        });

        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const dailySalesData = <?= json_encode(array_reverse($daily_sales)) ?>;
        
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: dailySalesData.map(day => {
                    const date = new Date(day.sale_date);
                    return date.toLocaleDateString('pt-BR');
                }),
                datasets: [{
                    label: 'Receita Diária (R$)',
                    data: dailySalesData.map(day => parseFloat(day.daily_revenue)),
                    borderColor: 'rgb(39, 174, 96)',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.1,
                    fill: true
                }, {
                    label: 'Número de Pedidos',
                    data: dailySalesData.map(day => parseInt(day.orders_count)),
                    borderColor: 'rgb(52, 152, 219)',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
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

        // Categories Chart
        <?php if (!empty($categories_data)): ?>
        const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
        const categoriesData = <?= json_encode($categories_data) ?>;
        
        const categoriesChart = new Chart(categoriesCtx, {
            type: 'doughnut',
            data: {
                labels: categoriesData.map(cat => cat.category_name || 'Sem categoria'),
                datasets: [{
                    data: categoriesData.map(cat => parseFloat(cat.category_revenue)),
                    backgroundColor: [
                        'rgba(39, 174, 96, 0.8)',
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(243, 156, 18, 0.8)',
                        'rgba(231, 76, 60, 0.8)',
                        'rgba(155, 89, 182, 0.8)',
                        'rgba(26, 188, 156, 0.8)',
                        'rgba(241, 196, 15, 0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return context.label + ': R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?= json_encode($status_data) ?>;
        const statusLabels = {
            'pending': 'Pendentes',
            'processing': 'Processando',
            'shipped': 'Enviados',
            'delivered': 'Entregues',
            'cancelled': 'Cancelados'
        };
        
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: statusData.map(status => statusLabels[status.status] || status.status),
                datasets: [{
                    data: statusData.map(status => parseInt(status.status_count)),
                    backgroundColor: [
                        'rgba(243, 156, 18, 0.8)', // pending
                        'rgba(52, 152, 219, 0.8)', // processing
                        'rgba(26, 188, 156, 0.8)', // shipped
                        'rgba(39, 174, 96, 0.8)',  // delivered
                        'rgba(231, 76, 60, 0.8)'   // cancelled
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return context.label + ': ' + value + ' pedidos (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Print styles
        const printStyles = `
            @media print {
                .sidebar { display: none !important; }
                .seller-container { grid-template-columns: 1fr !important; }
                .filters-section { display: none !important; }
                .btn { display: none !important; }
                .chart-container { 
                    height: 300px !important; 
                    page-break-inside: avoid;
                }
                .content-section {
                    page-break-inside: avoid;
                    margin-bottom: 2rem;
                    box-shadow: none;
                    border: 1px solid #ddd;
                }
                .page-header {
                    text-align: center;
                    border-bottom: 2px solid #27ae60;
                    padding-bottom: 1rem;
                }
                .stats-grid {
                    display: grid !important;
                    grid-template-columns: repeat(2, 1fr) !important;
                    page-break-inside: avoid;
                }
                .two-column {
                    grid-template-columns: 1fr !important;
                }
                body {
                    background: white !important;
                }
            }
        `;

        const styleSheet = document.createElement("style");
        styleSheet.innerText = printStyles;
        document.head.appendChild(styleSheet);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + P to print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            
            // Ctrl + E to export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                window.location.href = 'export_report.php?type=pdf&' + new URLSearchParams(window.location.search);
            }
        });

        // Auto-refresh charts every 2 minutes
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 120000);

        // Smooth scrolling for internal links
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

        // Animation for progress bars
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const progressBar = entry.target;
                        const width = progressBar.style.width;
                        progressBar.style.width = '0%';
                        setTimeout(() => {
                            progressBar.style.width = width;
                        }, 100);
                    }
                });
            });

            progressBars.forEach(bar => observer.observe(bar));
        });

        // Tooltip for truncated text
        document.querySelectorAll('.table td').forEach(function(cell) {
            if (cell.scrollWidth > cell.clientWidth) {
                cell.title = cell.textContent;
            }
        });

        // Export functionality with loading state
        document.querySelectorAll('a[href*="export_report"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                const btn = this;
                const originalText = btn.innerHTML;
                
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exportando...';
                btn.style.pointerEvents = 'none';
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.pointerEvents = 'auto';
                }, 3000);
            });
        });

        // Custom date validation
        document.getElementById('date_from').addEventListener('change', function() {
            const dateTo = document.getElementById('date_to');
            if (this.value && dateTo.value && this.value > dateTo.value) {
                alert('A data inicial não pode ser maior que a data final.');
                this.value = '';
            }
        });

        document.getElementById('date_to').addEventListener('change', function() {
            const dateFrom = document.getElementById('date_from');
            if (this.value && dateFrom.value && this.value < dateFrom.value) {
                alert('A data final não pode ser menor que a data inicial.');
                this.value = '';
            }
        });
    </script>
</body>
</html>