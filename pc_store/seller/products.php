<?php
require_once '../config/database.php';

requireLogin();
requireSeller();

$database = new Database();
$db = $database->getConnection();
$seller_id = $_SESSION['user_id'];

$success = '';
$error = '';

// Filtros
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$search = $_GET['search'] ?? '';

// Paginação
$page = (int)($_GET['page'] ?? 1);
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $product_id = (int)$_POST['product_id'];
        
        // Verificar se o produto pertence ao vendedor
        $check_query = "SELECT id FROM products WHERE id = :product_id AND seller_id = :seller_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':product_id', $product_id);
        $check_stmt->bindParam(':seller_id', $seller_id);
        $check_stmt->execute();
        
        if ($check_stmt->fetch()) {
            switch ($_POST['action']) {
                case 'toggle_status':
                    $new_status = $_POST['current_status'] === 'active' ? 'inactive' : 'active';
                    $update_query = "UPDATE products SET status = :status WHERE id = :product_id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':status', $new_status);
                    $update_stmt->bindParam(':product_id', $product_id);
                    
                    if ($update_stmt->execute()) {
                        $success = 'Status do produto atualizado com sucesso!';
                    } else {
                        $error = 'Erro ao atualizar status do produto.';
                    }
                    break;
                    
                case 'delete':
                    $delete_query = "UPDATE products SET status = 'deleted' WHERE id = :product_id";
                    $delete_stmt = $db->prepare($delete_query);
                    $delete_stmt->bindParam(':product_id', $product_id);
                    
                    if ($delete_stmt->execute()) {
                        $success = 'Produto removido com sucesso!';
                    } else {
                        $error = 'Erro ao remover produto.';
                    }
                    break;
                    
                case 'update_stock':
                    $new_stock = (int)$_POST['new_stock'];
                    if ($new_stock >= 0) {
                        $update_query = "UPDATE products SET stock_quantity = :stock WHERE id = :product_id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':stock', $new_stock);
                        $update_stmt->bindParam(':product_id', $product_id);
                        
                        if ($update_stmt->execute()) {
                            $success = 'Estoque atualizado com sucesso!';
                        } else {
                            $error = 'Erro ao atualizar estoque.';
                        }
                    } else {
                        $error = 'Quantidade de estoque deve ser maior ou igual a zero.';
                    }
                    break;
            }
        } else {
            $error = 'Produto não encontrado ou sem permissão.';
        }
    }
}

// Construir query com filtros
$where_conditions = ["seller_id = :seller_id", "status != 'deleted'"];
$params = [':seller_id' => $seller_id];

if (!empty($category_filter)) {
    $where_conditions[] = "category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
} else {
    // Por padrão, mostrar apenas produtos ativos se não especificado
    $where_conditions[] = "status = 'active'";
}

if (!empty($stock_filter)) {
    switch ($stock_filter) {
        case 'low':
            $where_conditions[] = "stock_quantity <= 5 AND stock_quantity > 0";
            break;
        case 'out':
            $where_conditions[] = "stock_quantity = 0";
            break;
        case 'high':
            $where_conditions[] = "stock_quantity > 20";
            break;
    }
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$where_clause = implode(' AND ', $where_conditions);

// Query para contar total de produtos
$count_query = "SELECT COUNT(*) as total FROM products WHERE {$where_clause}";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $per_page);

// Query principal para buscar produtos
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE {$where_clause}
          ORDER BY p.created_at DESC 
          LIMIT {$per_page} OFFSET {$offset}";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar categorias para o filtro
$cat_query = "SELECT * FROM categories ORDER BY name";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas rápidas
$stats_query = "SELECT 
                  COUNT(*) as total_products,
                  SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
                  SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_products,
                  SUM(CASE WHEN stock_quantity <= 5 AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
                  SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_stock
                FROM products 
                WHERE seller_id = :seller_id AND status != 'deleted'";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':seller_id', $seller_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Produtos - PC Store</title>
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
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header-text h1 {
    color: #2c3e50;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.page-header-text p {
    color: #7f8c8d;
    font-size: 1.1rem;
}

.page-header-actions {
    display: flex;
    gap: 1rem;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
    cursor: pointer;
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
.stat-card-active .stat-card-icon { color: #3498db; }
.stat-card-inactive .stat-card-icon { color: #95a5a6; }
.stat-card-low .stat-card-icon { color: #f39c12; }
.stat-card-out .stat-card-icon { color: #e74c3c; }

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

/* Buttons */
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

.btn-success {
    background: #27ae60;
    color: white;
}

.btn-warning {
    background: #f39c12;
    color: white;
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

/* Products Grid */
.products-section {
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
    justify-content: space-between;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
}

.product-card {
    background: white;
    border: 2px solid #f8f9fa;
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    border-color: #27ae60;
}

.product-image {
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.product-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.product-image i {
    font-size: 4rem;
    color: #dee2e6;
}

.product-status {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.product-info {
    padding: 1.5rem;
}

.product-name {
    font-size: 1.2rem;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.product-category {
    color: #7f8c8d;
    font-size: 0.9rem;
    margin-bottom: 0.8rem;
}

.product-description {
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
    margin-bottom: 1rem;
    max-height: 60px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-price {
    font-size: 1.5rem;
    font-weight: bold;
    color: #27ae60;
    margin-bottom: 1rem;
}

.product-stock {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stock-info {
    flex: 1;
}

.stock-label {
    font-size: 0.8rem;
    color: #7f8c8d;
    margin-bottom: 0.3rem;
}

.stock-value {
    font-weight: bold;
}

.stock-high {
    color: #27ae60;
}

.stock-medium {
    color: #f39c12;
}

.stock-low {
    color: #e74c3c;
}

.stock-out {
    color: #e74c3c;
}

.stock-update {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stock-input {
    width: 60px;
    padding: 0.3rem;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    text-align: center;
}

.product-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
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

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.empty-state {
    text-align: center;
    padding: 4rem;
    color: #7f8c8d;
}

.empty-state i {
    font-size: 5rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

.empty-state h3 {
    margin-bottom: 1rem;
    color: #2c3e50;
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

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 2rem;
    border-radius: 15px;
    width: 90%;
    max-width: 500px;
    position: relative;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.modal-title {
    font-size: 1.5rem;
    color: #2c3e50;
}

.close {
    font-size: 2rem;
    cursor: pointer;
    color: #aaa;
}

.close:hover {
    color: #e74c3c;
}

@media (max-width: 1024px) {
    .seller-container {
        grid-template-columns: 1fr;
    }
    
    .sidebar {
        display: none;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 1rem;
    }
    
    .page-header-text h1 {
        font-size: 2rem;
    }
    
    .filters-actions {
        justify-content: stretch;
        flex-direction: column;
    }
    
    .product-actions {
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
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
                <li><a href="products.php" class="active"><i class="fas fa-box"></i> Meus Produtos</a></li>
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
                <div class="page-header-text">
                    <h1><i class="fas fa-box"></i> Meus Produtos</h1>
                    <p>Gerencie seu catálogo de produtos</p>
                </div>
                <div class="page-header-actions">
                    <a href="add_product.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Novo Produto
                    </a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card stat-card-total" onclick="filterByStatus('')">
                    <div class="stat-card-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['total_products']) ?></div>
                    <div class="stat-card-label">Total de Produtos</div>
                </div>

                <div class="stat-card stat-card-active" onclick="filterByStatus('active')">
                    <div class="stat-card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['active_products']) ?></div>
                    <div class="stat-card-label">Ativos</div>
                </div>

                <div class="stat-card stat-card-inactive" onclick="filterByStatus('inactive')">
                    <div class="stat-card-icon">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['inactive_products']) ?></div>
                    <div class="stat-card-label">Inativos</div>
                </div>

                <div class="stat-card stat-card-low" onclick="filterByStock('low')">
                    <div class="stat-card-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['low_stock']) ?></div>
                    <div class="stat-card-label">Estoque Baixo</div>
                </div>

                <div class="stat-card stat-card-out" onclick="filterByStock('out')">
                    <div class="stat-card-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['out_stock']) ?></div>
                    <div class="stat-card-label">Sem Estoque</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <h2 class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filtros
                </h2>
                
                <form method="GET" action="products.php" id="filtersForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="category">Categoria</label>
                            <select name="category" id="category">
                                <option value="">Todas as categorias</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" 
                                            <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select name="status" id="status">
                                <option value="">Todos os status</option>
                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Ativo</option>
                                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="stock">Estoque</label>
                            <select name="stock" id="stock">
                                <option value="">Todos os estoques</option>
                                <option value="high" <?= $stock_filter === 'high' ? 'selected' : '' ?>>Alto (>20)</option>
                                <option value="low" <?= $stock_filter === 'low' ? 'selected' : '' ?>>Baixo (≤5)</option>
                                <option value="out" <?= $stock_filter === 'out' ? 'selected' : '' ?>>Sem estoque</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="search">Buscar</label>
                            <input type="text" name="search" id="search" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Nome ou descrição do produto">
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="products.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Products Grid -->
            <div class="products-section">
                <h2 class="section-title">
                    <span>
                        <i class="fas fa-list"></i>
                        Produtos (<?= number_format($total_products) ?> encontrados)
                    </span>
                    <div>
                        <a href="add_product.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Adicionar
                        </a>
                    </div>
                </h2>
                
                <?php if (!empty($products)): ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <i class="fas fa-desktop" style="display: none;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-desktop"></i>
                                    <?php endif; ?>
                                    
                                    <div class="product-status <?= $product['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                        <?= $product['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                                    </div>
                                </div>

                                <div class="product-info">
                                    <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                                    
                                    <div class="product-category">
                                        <i class="fas fa-tag"></i>
                                        <?= htmlspecialchars($product['category_name'] ?? 'Sem categoria') ?>
                                    </div>

                                    <div class="product-description">
                                        <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>
                                        <?= strlen($product['description']) > 100 ? '...' : '' ?>
                                    </div>

                                    <div class="product-price">
                                        R$ <?= number_format($product['price'], 2, ',', '.') ?>
                                    </div>

                                    <div class="product-stock">
                                        <div class="stock-info">
                                            <div class="stock-label">Estoque</div>
                                            <div class="stock-value <?php
                                                if ($product['stock_quantity'] == 0) echo 'stock-out';
                                                elseif ($product['stock_quantity'] <= 5) echo 'stock-low';
                                                elseif ($product['stock_quantity'] <= 20) echo 'stock-medium';
                                                else echo 'stock-high';
                                            ?>">
                                                <?= $product['stock_quantity'] ?> unidade(s)
                                            </div>
                                        </div>

                                        <form method="POST" class="stock-update" onsubmit="return confirm('Deseja atualizar o estoque?')">
                                            <input type="hidden" name="action" value="update_stock">
                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                            <input type="number" name="new_stock" class="stock-input" 
                                                   value="<?= $product['stock_quantity'] ?>" min="0" max="9999">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <div class="product-actions">
                                        <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>

                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Deseja <?= $product['status'] === 'active' ? 'inativar' : 'ativar' ?> este produto?')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $product['status'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $product['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>">
                                                <i class="fas fa-<?= $product['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                                <?= $product['status'] === 'active' ? 'Inativar' : 'Ativar' ?>
                                            </button>
                                        </form>

                                        <button onclick="openDeleteModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>')" 
                                                class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Excluir
                                        </button>

                                        <a href="../product.php?id=<?= $product['id'] ?>" target="_blank" 
                                           class="btn btn-sm btn-secondary">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                        <i class="fas fa-box-open"></i>
                        <h3>Nenhum produto encontrado</h3>
                        <p>
                            <?php if (!empty($category_filter) || !empty($status_filter) || !empty($stock_filter) || !empty($search)): ?>
                                Não há produtos que correspondam aos filtros aplicados.
                                <br><a href="products.php" class="btn btn-secondary" style="margin-top: 1rem;">
                                    <i class="fas fa-times"></i> Limpar filtros
                                </a>
                            <?php else: ?>
                                Você ainda não tem produtos cadastrados.
                                <br><a href="add_product.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="fas fa-plus"></i> Cadastrar primeiro produto
                                </a>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Bulk Actions -->
            <?php if (!empty($products)): ?>
                <div class="products-section">
                    <h2 class="section-title">
                        <i class="fas fa-cogs"></i>
                        Ações em Massa
                    </h2>
                    
                    <div class="stats-grid">
                        <button onclick="bulkAction('activate')" class="btn btn-success">
                            <i class="fas fa-play"></i> Ativar Selecionados
                        </button>
                        <button onclick="bulkAction('deactivate')" class="btn btn-warning">
                            <i class="fas fa-pause"></i> Inativar Selecionados
                        </button>
                        <button onclick="bulkAction('delete')" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Excluir Selecionados
                        </button>
                        <a href="export_products.php?<?= http_build_query($_GET) ?>" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Exportar Lista
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirmar Exclusão</h2>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o produto <strong id="productToDelete"></strong>?</p>
                <p><small>Esta ação não pode ser desfeita.</small></p>
            </div>
            <div class="modal-footer" style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button onclick="closeDeleteModal()" class="btn btn-secondary">Cancelar</button>
                <form method="POST" style="display: inline;" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" id="deleteProductId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Confirmar Exclusão
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Filtro rápido pelos cards de estatísticas
        function filterByStatus(status) {
            const url = new URL(window.location);
            url.searchParams.delete('stock');
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            window.location.href = url.toString();
        }

        function filterByStock(stock) {
            const url = new URL(window.location);
            url.searchParams.delete('status');
            if (stock) {
                url.searchParams.set('stock', stock);
            } else {
                url.searchParams.delete('stock');
            }
            window.location.href = url.toString();
        }

        // Modal de exclusão
        function openDeleteModal(productId, productName) {
            document.getElementById('productToDelete').textContent = productName;
            document.getElementById('deleteProductId').value = productId;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Fechar modal clicando fora
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }

        // Busca em tempo real
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 2 || this.value.length === 0) {
                    document.getElementById('filtersForm').submit();
                }
            }, 500);
        });

        // Seleção múltipla para ações em massa
        function toggleProductSelection() {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            if (selectAll.checked) {
                checkboxes.forEach(cb => cb.checked = true);
            } else {
                checkboxes.forEach(cb => cb.checked = false);
            }
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const selectedCount = document.querySelectorAll('.product-checkbox:checked').length;
            const bulkActions = document.querySelectorAll('[onclick^="bulkAction"]');
            
            bulkActions.forEach(btn => {
                btn.disabled = selectedCount === 0;
                btn.style.opacity = selectedCount === 0 ? '0.5' : '1';
            });
        }

        // Placeholder para ações em massa (implementar conforme necessário)
        function bulkAction(action) {
            const selected = Array.from(document.querySelectorAll('.product-checkbox:checked'))
                                 .map(cb => cb.value);
            
            if (selected.length === 0) {
                alert('Selecione pelo menos um produto.');
                return;
            }
            
            let message = '';
            switch (action) {
                case 'activate':
                    message = `Ativar ${selected.length} produto(s) selecionado(s)?`;
                    break;
                case 'deactivate':
                    message = `Inativar ${selected.length} produto(s) selecionado(s)?`;
                    break;
                case 'delete':
                    message = `Excluir ${selected.length} produto(s) selecionado(s)? Esta ação não pode ser desfeita.`;
                    break;
            }
            
            if (confirm(message)) {
                // Implementar ação em massa aqui
                console.log(`Ação: ${action}, Produtos: ${selected.join(', ')}`);
            }
        }

        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + N para novo produto
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'add_product.php';
            }
            
            // Ctrl + F para focar no campo de busca
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search').focus();
            }
            
            // Esc para limpar filtros
            if (e.key === 'Escape') {
                window.location.href = 'products.php';
            }
        });

        // Auto-save dos filtros
        function saveFilterState() {
            const filters = {
                category: document.getElementById('category').value,
                status: document.getElementById('status').value,
                stock: document.getElementById('stock').value,
                search: document.getElementById('search').value
            };
            localStorage.setItem('products_filters', JSON.stringify(filters));
        }

        // Salvar ao alterar qualquer filtro
        document.querySelectorAll('#category, #status, #stock, #search').forEach(function(element) {
            element.addEventListener('change', saveFilterState);
        });

        // Confirmação inteligente para ações
        document.querySelectorAll('form[onsubmit*="confirm"]').forEach(function(form) {
            const originalOnSubmit = form.getAttribute('onsubmit');
            form.onsubmit = function(e) {
                const action = form.querySelector('input[name="action"]').value;
                let message = '';
                
                switch (action) {
                    case 'toggle_status':
                        const currentStatus = form.querySelector('input[name="current_status"]').value;
                        message = `Deseja ${currentStatus === 'active' ? 'inativar' : 'ativar'} este produto?`;
                        break;
                    case 'update_stock':
                        message = 'Deseja atualizar o estoque deste produto?';
                        break;
                    default:
                        return eval(originalOnSubmit);
                }
                
                return confirm(message);
            };
        });

        // Lazy loading para imagens
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // Tooltip para produtos com descrição longa
        document.querySelectorAll('.product-description').forEach(function(desc) {
            if (desc.scrollHeight > desc.clientHeight) {
                desc.title = desc.textContent;
                desc.style.cursor = 'help';
            }
        });

        // Highlight de produtos com estoque baixo
        document.querySelectorAll('.stock-low, .stock-out').forEach(function(element) {
            const card = element.closest('.product-card');
            if (card) {
                card.style.borderLeftColor = '#e74c3c';
                card.style.borderLeftWidth = '4px';
            }
        });

        // Atualização automática das estatísticas
        function updateStats() {
            fetch('../api/seller_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Atualizar apenas se os valores mudaram
                        const totalElement = document.querySelector('.stat-card-total .stat-card-value');
                        if (totalElement) {
                            const currentTotal = parseInt(totalElement.textContent.replace(/\D/g, ''));
                            if (currentTotal !== data.stats.products) {
                                totalElement.textContent = new Intl.NumberFormat('pt-BR').format(data.stats.products);
                                totalElement.style.color = '#27ae60';
                                setTimeout(() => {
                                    totalElement.style.color = '#2c3e50';
                                }, 2000);
                            }
                        }
                    }
                })
                .catch(error => console.error('Erro ao atualizar estatísticas:', error));
        }

        // Atualizar estatísticas a cada 60 segundos
        setInterval(updateStats, 60000);
    </script>
</body>
</html>