<?php
require_once '../config/database.php';

requireLogin();
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'update_status':
            $order_id = (int)$_POST['order_id'];
            $status = sanitize($_POST['status']);
            
            $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            
            if (in_array($status, $valid_statuses)) {
                $query = "UPDATE orders SET status = :status WHERE id = :order_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':order_id', $order_id);
                
                if ($stmt->execute()) {
                    $message = 'Status do pedido atualizado com sucesso!';
                } else {
                    $error = 'Erro ao atualizar status do pedido.';
                }
            } else {
                $error = 'Status inválido.';
            }
            break;
            
        case 'delete_order':
            $order_id = (int)$_POST['order_id'];
            
            try {
                $db->beginTransaction();
                
                // Buscar itens do pedido para restaurar estoque
                $query = "SELECT oi.product_id, oi.quantity 
                          FROM order_items oi 
                          WHERE oi.order_id = :order_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->execute();
                $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Restaurar estoque
                foreach ($order_items as $item) {
                    $query = "UPDATE products 
                              SET stock_quantity = stock_quantity + :quantity 
                              WHERE id = :product_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':quantity', $item['quantity']);
                    $stmt->bindParam(':product_id', $item['product_id']);
                    $stmt->execute();
                }
                
                // Deletar itens do pedido
                $query = "DELETE FROM order_items WHERE order_id = :order_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->execute();
                
                // Deletar pedido
                $query = "DELETE FROM orders WHERE id = :order_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->execute();
                
                $db->commit();
                $message = 'Pedido removido com sucesso!';
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Erro ao remover pedido: ' . $e->getMessage();
            }
            break;
    }
}

// Filtros
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(o.id LIKE :search OR u.name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

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

$where_clause = implode(" AND ", $where_conditions);

// Buscar pedidos
$query = "SELECT o.*, u.name as user_name, u.email as user_email,
          COUNT(oi.id) as items_count,
          GROUP_CONCAT(DISTINCT SUBSTRING(p.name, 1, 30) ORDER BY p.name SEPARATOR ', ') as products_preview
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          LEFT JOIN order_items oi ON o.id = oi.order_id
          LEFT JOIN products p ON oi.product_id = p.id
          WHERE $where_clause 
          GROUP BY o.id, u.name, u.email
          ORDER BY o.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas rápidas
$stats = [];

$query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
    SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN status != 'cancelled' THEN total_amount ELSE 0 END) as total_revenue
    FROM orders";

$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pedidos - PC Store Admin</title>
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
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .stat-card-total .stat-card-icon { color: #3498db; }
        .stat-card-pending .stat-card-icon { color: #f39c12; }
        .stat-card-delivered .stat-card-icon { color: #27ae60; }
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
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        /* Orders Table */
        .orders-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .table-container {
            overflow-x: auto;
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
            white-space: nowrap;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .order-id {
            font-weight: bold;
            color: #3498db;
        }

        .order-customer {
            min-width: 150px;
        }

        .customer-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .customer-email {
            font-size: 0.8rem;
            color: #7f8c8d;
        }

        .badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
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
            background: #cce7ff;
            color: #004085;
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
            font-size: 1.1rem;
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
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.75rem;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            white-space: nowrap;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
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

        .status-select {
            padding: 0.3rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.8rem;
        }

        .products-preview {
            max-width: 200px;
            font-size: 0.8rem;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .date-info {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        @media (max-width: 1024px) {
            .admin-container {
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
            
            .page-title h1 {
                font-size: 2rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
            
            .actions {
                flex-direction: column;
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
                <li><a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> Pedidos</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Gerenciar Pedidos</h1>
                    <p>Administre todos os pedidos da loja</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-card-total">
                    <div class="stat-card-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['total']) ?></div>
                    <div class="stat-card-label">Total de Pedidos</div>
                </div>

                <div class="stat-card stat-card-pending">
                    <div class="stat-card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['pending']) ?></div>
                    <div class="stat-card-label">Pendentes</div>
                </div>

                <div class="stat-card stat-card-delivered">
                    <div class="stat-card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-card-value"><?= number_format($stats['delivered']) ?></div>
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
            <div class="filters">
                <form method="GET">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Buscar</label>
                            <input type="text" id="search" name="search" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Pedido, cliente ou email...">
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">Todos</option>
                                <option value="pending" <?= ($status_filter === 'pending') ? 'selected' : '' ?>>Pendente</option>
                                <option value="processing" <?= ($status_filter === 'processing') ? 'selected' : '' ?>>Processando</option>
                                <option value="shipped" <?= ($status_filter === 'shipped') ? 'selected' : '' ?>>Enviado</option>
                                <option value="delivered" <?= ($status_filter === 'delivered') ? 'selected' : '' ?>>Entregue</option>
                                <option value="cancelled" <?= ($status_filter === 'cancelled') ? 'selected' : '' ?>>Cancelado</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_from">Data Inicial</label>
                            <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_to">Data Final</label>
                            <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
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

            <!-- Orders Table -->
            <div class="orders-section">
                <div class="section-header">
                    <h2>Pedidos (<?= count($orders) ?>)</h2>
                </div>

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
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3rem; color: #7f8c8d;">
                                        <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 1rem;"></i><br>
                                        Nenhum pedido encontrado
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <div class="order-id">#<?= sprintf('%06d', $order['id']) ?></div>
                                            <div style="font-size: 0.8rem; color: #666;">
                                                <?= $order['items_count'] ?> item(s)
                                            </div>
                                        </td>
                                        <td>
                                            <div class="order-customer">
                                                <div class="customer-name"><?= htmlspecialchars($order['user_name']) ?></div>
                                                <div class="customer-email"><?= htmlspecialchars($order['user_email']) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="products-preview" title="<?= htmlspecialchars($order['products_preview']) ?>">
                                                <?= htmlspecialchars($order['products_preview'] ?: 'N/A') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="price">R$ <?= number_format($order['total_amount'], 2, ',', '.') ?></div>
                                            <div style="font-size: 0.8rem; color: #666;">
                                                <?= ucfirst($order['payment_method'] ?? 'N/A') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <select name="status" class="status-select" onchange="this.form.submit()">
                                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pendente</option>
                                                    <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processando</option>
                                                    <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Enviado</option>
                                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Entregue</option>
                                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= date('d/m/Y', strtotime($order['created_at'])) ?>
                                                <br>
                                                <small><?= date('H:i', strtotime($order['created_at'])) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="../order-details.php?id=<?= $order['id'] ?>" class="btn btn-primary btn-small" target="_blank">
                                                    <i class="fas fa-eye"></i> Ver
                                                </a>
                                                
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Tem certeza que deseja excluir este pedido?\n\nEsta ação não pode ser desfeita e o estoque será restaurado.')">
                                                    <input type="hidden" name="action" value="delete_order">
                                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-small">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-submit filtros
        document.querySelectorAll('#search, #status, #date_from, #date_to').forEach(element => {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Search com delay
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });

        // Confirmação para mudança de status críticos
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('status-select')) {
                const newStatus = e.target.value;
                const oldStatus = e.target.querySelector('option[selected]')?.value;
                
                if ((newStatus === 'cancelled' && oldStatus !== 'cancelled') ||
                    (newStatus === 'delivered' && oldStatus !== 'delivered')) {
                    if (!confirm(`Tem certeza que deseja alterar o status para "${newStatus.toUpperCase()}"?`)) {
                        e.target.value = oldStatus || 'pending';
                        return false;
                    }
                }
            }
        });

        // Atualização automática a cada 30 segundos
        setInterval(function() {
            // Recarregar apenas se não houver mudanças pendentes
            if (!document.querySelector('form:focus-within')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
<html>