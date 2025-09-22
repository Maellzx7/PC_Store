<?php
require_once '../config/database.php';

requireLogin();
requireSeller();

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

// Filtros
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Paginação
$page = (int)($_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Construir query com filtros
$where_conditions = ["p.seller_id = :seller_id"];
$params = [':seller_id' => $seller_id];

if (!empty($status_filter)) {
    $where_conditions[] = "o.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(o.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(o.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE :search OR u.name LIKE :search OR o.id LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$where_clause = implode(' AND ', $where_conditions);

// Query para contar total de registros
$count_query = "SELECT COUNT(DISTINCT o.id) as total
                FROM orders o 
                INNER JOIN order_items oi ON o.id = oi.order_id 
                INNER JOIN products p ON oi.product_id = p.id 
                INNER JOIN users u ON o.user_id = u.id 
                WHERE {$where_clause}";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_orders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_orders / $per_page);

// Query principal para buscar pedidos
$query = "SELECT DISTINCT o.id, o.status, o.created_at,
                 u.name as customer_name, u.email as customer_email,
                 (SELECT GROUP_CONCAT(CONCAT(p2.name, ' (Qtd: ', oi2.quantity, ')') SEPARATOR ', ') 
                  FROM order_items oi2 
                  INNER JOIN products p2 ON oi2.product_id = p2.id 
                  WHERE oi2.order_id = o.id AND p2.seller_id = :seller_id2) as products,
                 (SELECT SUM(oi3.price * oi3.quantity) 
                  FROM order_items oi3 
                  INNER JOIN products p3 ON oi3.product_id = p3.id 
                  WHERE oi3.order_id = o.id AND p3.seller_id = :seller_id3) as seller_total
          FROM orders o 
          INNER JOIN order_items oi ON o.id = oi.order_id 
          INNER JOIN products p ON oi.product_id = p.id 
          INNER JOIN users u ON o.user_id = u.id 
          WHERE {$where_clause}
          ORDER BY o.created_at DESC 
          LIMIT {$per_page} OFFSET {$offset}";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
// Bind dos parâmetros adicionais das subqueries
$stmt->bindValue(':seller_id2', $seller_id);
$stmt->bindValue(':seller_id3', $seller_id);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas rápidas
$stats_query = "SELECT 
                  COUNT(DISTINCT o.id) as total_orders,
                  SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                  SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                  COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue
                FROM orders o 
                INNER JOIN order_items oi ON o.id = oi.order_id 
                INNER JOIN products p ON oi.product_id = p.id 
                WHERE p.seller_id = :seller_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':seller_id', $seller_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Processar ações (atualizar status do pedido)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status' && isset($_POST['order_id']) && isset($_POST['new_status'])) {
        $order_id = (int)$_POST['order_id'];
        $new_status = $_POST['new_status'];
        
        // Verificar se o pedido pertence ao vendedor
        $check_query = "SELECT COUNT(*) as count
                       FROM orders o 
                       INNER JOIN order_items oi ON o.id = oi.order_id 
                       INNER JOIN products p ON oi.product_id = p.id 
                       WHERE o.id = :order_id AND p.seller_id = :seller_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':order_id', $order_id);
        $check_stmt->bindParam(':seller_id', $seller_id);
        $check_stmt->execute();
        
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            $update_query = "UPDATE orders SET status = :status WHERE id = :order_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $new_status);
            $update_stmt->bindParam(':order_id', $order_id);
            
            if ($update_stmt->execute()) {
                header('Location: orders.php?status_updated=1');
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendas - PC Store</title>
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

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
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
    font-size: 2rem;
    margin-bottom: 0.8rem;
    display: inline-block;
}

.stat-card-total .stat-card-icon { color: #27ae60; }
.stat-card-pending .stat-card-icon { color: #f39c12; }
.stat-card-delivered .stat-card-icon { color: #3498db; }
.stat-card-revenue .stat-card-icon { color: #e74c3c; }

.stat-card-value {
    font-size: 1.8rem;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.stat-card-label {
    color: #7f8c8d;
    font-size: 0.9rem;
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

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

/* Orders Table */
.orders-section {
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

.table-container {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
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
    font-size: 0.9rem;
}

.table tbody tr:hover {
    background: #f8f9fa;
}

.table td {
    font-size: 0.9rem;
}

.order-id {
    font-weight: bold;
    color: #27ae60;
}

.customer-info {
    color: #2c3e50;
}

.customer-email {
    color: #7f8c8d;
    font-size: 0.8rem;
}

.products-list {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-block;
    min-width: 80px;
    text-align: center;
}

.badge-pending {
    background: #fff3cd;
    color: #856404;
}

.badge-processing {
    background: #d1ecf1;
    color: #0c5460;
}

.badge-shipped {
    background: #d4edda;
    color: #155724;
}

.badge-delivered {
    background: #d4edda;
    color: #155724;
}

.badge-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.price {
    color: #27ae60;
    font-weight: bold;
}

.date {
    color: #7f8c8d;
    font-size: 0.85rem;
}

/* Status Update */
.status-update {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.status-select {
    padding: 0.4rem;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    font-size: 0.8rem;
}

.update-btn {
    padding: 0.4rem 0.8rem;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.8rem;
}

.update-btn:hover {
    background: #0056b3;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.pagination a,
.pagination span {
    padding: 0.8rem 1rem;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    color: #495057;
    text-decoration: none;
    transition: all 0.3s ease;
}

.pagination a:hover {
    background: #27ae60;
    color: white;
    border-color: #27ae60;
}

.pagination .current {
    background: #27ae60;
    color: white;
    border-color: #27ae60;
}

/* Alerts */
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #7f8c8d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

@media (max-width: 1024px) {
    .seller-container {
        grid-template-columns: 1fr;
    }
    
    .sidebar {
        display: none;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 1rem;
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .filters-actions {
        justify-content: stretch;
        flex-direction: column;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .table th,
    .table td {
        padding: 0.5rem;
    }
    
    .products-list {
        max-width: 150px;
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
                <li><a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> Vendas</a></li>
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
                <h1><i class="fas fa-shopping-cart"></i> Vendas</h1>
                <p>Gerencie seus pedidos e acompanhe o status das vendas</p>
            </div>

            <?php if (isset($_GET['status_updated'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Status do pedido atualizado com sucesso!
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card stat-card-total">
                    <div class="stat-card-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['total_orders']) ?></div>
                    <div class="stat-card-label">Total de Vendas</div>
                </div>

                <div class="stat-card stat-card-pending">
                    <div class="stat-card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['pending_orders']) ?></div>
                    <div class="stat-card-label">Pendentes</div>
                </div>

                <div class="stat-card stat-card-delivered">
                    <div class="stat-card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['delivered_orders']) ?></div>
                    <div class="stat-card-label">Entregues</div>
                </div>

                <div class="stat-card stat-card-revenue">
                    <div class="stat-card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-card-value">R$ <?= number_format($stats['total_revenue'], 2, ',', '.') ?></div>
                    <div class="stat-card-label">Receita Total</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <h2 class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filtros
                </h2>
                
                <form method="GET" action="orders.php">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select name="status" id="status">
                                <option value="">Todos os status</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pendente</option>
                                <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processando</option>
                                <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : '' ?>>Enviado</option>
                                <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Entregue</option>
                                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="date_from">Data Inicial</label>
                            <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($date_from) ?>">
                        </div>

                        <div class="filter-group">
                            <label for="date_to">Data Final</label>
                            <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($date_to) ?>">
                        </div>

                        <div class="filter-group">
                            <label for="search">Buscar</label>
                            <input type="text" name="search" id="search" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Produto, cliente ou #pedido">
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="orders.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="orders-section">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    Pedidos (<?= number_format($total_orders) ?> encontrados)
                </h2>
                
                <?php if (!empty($orders)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Produtos</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <span class="order-id">#<?= $order['id'] ?></span>
                                        </td>
                                        <td>
                                            <div class="customer-info">
                                                <?= htmlspecialchars($order['customer_name']) ?>
                                            </div>
                                            <div class="customer-email">
                                                <?= htmlspecialchars($order['customer_email']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="products-list" title="<?= htmlspecialchars($order['products']) ?>">
                                                <?= htmlspecialchars($order['products']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="price">R$ <?= number_format($order['seller_total'], 2, ',', '.') ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = 'badge-pending';
                                            $status_text = 'Pendente';
                                            switch($order['status']) {
                                                case 'processing':
                                                    $status_class = 'badge-processing';
                                                    $status_text = 'Processando';
                                                    break;
                                                case 'shipped':
                                                    $status_class = 'badge-shipped';
                                                    $status_text = 'Enviado';
                                                    break;
                                                case 'delivered':
                                                    $status_class = 'badge-delivered';
                                                    $status_text = 'Entregue';
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'badge-cancelled';
                                                    $status_text = 'Cancelado';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                        </td>
                                        <td>
                                            <div class="date">
                                                <?= date('d/m/Y', strtotime($order['created_at'])) ?>
                                                <br>
                                                <small><?= date('H:i', strtotime($order['created_at'])) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <form method="POST" class="status-update">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <select name="new_status" class="status-select">
                                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pendente</option>
                                                    <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processando</option>
                                                    <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Enviado</option>
                                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Entregue</option>
                                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                                                </select>
                                                <button type="submit" class="update-btn" onclick="return confirm('Deseja atualizar o status deste pedido?')">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    Próximo <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Nenhum pedido encontrado</h3>
                        <p>
                            <?php if (!empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($search)): ?>
                                Não há pedidos que correspondam aos filtros aplicados.
                                <br><a href="orders.php">Limpar filtros</a>
                            <?php else: ?>
                                Você ainda não possui vendas realizadas.
                                <br><a href="add_product.php">Cadastre seus produtos</a> para começar a vender.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Export Options -->
            <div class="orders-section">
                <h2 class="section-title">
                    <i class="fas fa-download"></i>
                    Exportar Dados
                </h2>
                
                <div class="stats-grid">
                    <a href="export_orders.php?format=csv&<?= http_build_query($_GET) ?>" class="btn btn-primary">
                        <i class="fas fa-file-csv"></i> Exportar CSV
                    </a>
                    <a href="export_orders.php?format=pdf&<?= http_build_query($_GET) ?>" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Exportar PDF
                    </a>
                    <a href="reports.php" class="btn btn-primary">
                        <i class="fas fa-chart-bar"></i> Ver Relatórios
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh da página a cada 5 minutos para atualizar status dos pedidos
        setInterval(function() {
            if (!document.querySelector('form').checkValidity || 
                !document.querySelector('input:focus, select:focus, textarea:focus')) {
                location.reload();
            }
        }, 300000); // 5 minutos

        // Confirmar mudança de status
        document.querySelectorAll('.status-select').forEach(function(select) {
            select.addEventListener('change', function() {
                const form = this.closest('form');
                const orderId = form.querySelector('input[name="order_id"]').value;
                const newStatus = this.value;
                const statusText = this.options[this.selectedIndex].text;
                
                if (confirm(`Deseja alterar o status do pedido #${orderId} para "${statusText}"?`)) {
                    form.submit();
                }
            });
        });

        // Busca em tempo real
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 3 || this.value.length === 0) {
                    this.form.submit();
                }
            }, 500);
        });

        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + F para focar no campo de busca
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search').focus();
            }
            
            // Esc para limpar filtros
            if (e.key === 'Escape') {
                window.location.href = 'orders.php';
            }
        });

        // Tooltips para produtos truncados
        document.querySelectorAll('.products-list').forEach(function(element) {
            if (element.scrollWidth > element.clientWidth) {
                element.style.cursor = 'help';
                element.addEventListener('click', function() {
                    alert(this.getAttribute('title'));
                });
            }
        });

        // Highlight de pedidos recentes (últimas 24h)
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const oneDayAgo = new Date(now.getTime() - 24 * 60 * 60 * 1000);
            
            document.querySelectorAll('.table tbody tr').forEach(function(row) {
                const dateCell = row.querySelector('.date');
                if (dateCell) {
                    const dateText = dateCell.textContent.trim();
                    const dateParts = dateText.split('\n')[0].split('/');
                    const orderDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
                    
                    if (orderDate > oneDayAgo) {
                        row.style.backgroundColor = '#f8fff9';
                        row.style.borderLeft = '4px solid #27ae60';
                    }
                }
            });
        });

        // Estatísticas em tempo real (atualizar a cada 30 segundos)
        function updateStats() {
            fetch('../api/seller_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Atualizar apenas se os valores mudaram
                        const elements = {
                            orders: document.querySelector('.stat-card-total .stat-card-value'),
                            revenue: document.querySelector('.stat-card-revenue .stat-card-value')
                        };
                        
                        if (elements.orders) {
                            const currentOrders = parseInt(elements.orders.textContent.replace(/\D/g, ''));
                            if (currentOrders !== data.stats.orders) {
                                elements.orders.textContent = new Intl.NumberFormat('pt-BR').format(data.stats.orders);
                                elements.orders.style.color = '#27ae60';
                                setTimeout(() => {
                                    elements.orders.style.color = '#2c3e50';
                                }, 2000);
                            }
                        }
                        
                        if (elements.revenue) {
                            const currentRevenue = parseFloat(elements.revenue.textContent.replace(/[^\d,]/g, '').replace(',', '.'));
                            if (Math.abs(currentRevenue - data.stats.revenue) > 0.01) {
                                elements.revenue.textContent = 'R$ ' + new Intl.NumberFormat('pt-BR', {
                                    minimumFractionDigits: 2
                                }).format(data.stats.revenue);
                                elements.revenue.style.color = '#27ae60';
                                setTimeout(() => {
                                    elements.revenue.style.color = '#2c3e50';
                                }, 2000);
                            }
                        }
                    }
                })
                .catch(error => console.error('Erro ao atualizar estatísticas:', error));
        }

        // Atualizar estatísticas a cada 30 segundos
        setInterval(updateStats, 30000);

        // Filtro rápido por status nos cards
        document.querySelectorAll('.stat-card').forEach(function(card, index) {
            if (index > 0 && index < 3) { // Apenas cards de Pendentes e Entregues
                card.style.cursor = 'pointer';
                card.addEventListener('click', function() {
                    const statusMap = ['', 'pending', 'delivered'];
                    const status = statusMap[index];
                    if (status) {
                        const url = new URL(window.location);
                        url.searchParams.set('status', status);
                        window.location.href = url.toString();
                    }
                });
            }
        });

        // Salvar estado dos filtros no localStorage
        function saveFilterState() {
            const filters = {
                status: document.getElementById('status').value,
                date_from: document.getElementById('date_from').value,
                date_to: document.getElementById('date_to').value,
                search: document.getElementById('search').value
            };
            localStorage.setItem('orders_filters', JSON.stringify(filters));
        }

        // Salvar ao alterar qualquer filtro
        document.querySelectorAll('#status, #date_from, #date_to, #search').forEach(function(element) {
            element.addEventListener('change', saveFilterState);
        });
    </script>
</body>
</html>