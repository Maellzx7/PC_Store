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
        case 'add':
            $name = sanitize($_POST['name']);
            $description = sanitize($_POST['description']);
            $price = (float)$_POST['price'];
            $stock_quantity = (int)$_POST['stock_quantity'];
            $category_id = (int)$_POST['category_id'];
            $image_url = sanitize($_POST['image_url']);
            $seller_id = $_SESSION['user_id']; // Admin como vendedor
            
            if (!empty($name) && !empty($description) && $price > 0 && $stock_quantity >= 0) {
                $query = "INSERT INTO products (name, description, price, stock_quantity, category_id, image_url, seller_id) 
                          VALUES (:name, :description, :price, :stock_quantity, :category_id, :image_url, :seller_id)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':price', $price);
                $stmt->bindParam(':stock_quantity', $stock_quantity);
                $stmt->bindParam(':category_id', $category_id);
                $stmt->bindParam(':image_url', $image_url);
                $stmt->bindParam(':seller_id', $seller_id);
                
                if ($stmt->execute()) {
                    $message = 'Produto adicionado com sucesso!';
                } else {
                    $error = 'Erro ao adicionar produto.';
                }
            } else {
                $error = 'Preencha todos os campos obrigatórios.';
            }
            break;
            
        case 'edit':
            $id = (int)$_POST['id'];
            $name = sanitize($_POST['name']);
            $description = sanitize($_POST['description']);
            $price = (float)$_POST['price'];
            $stock_quantity = (int)$_POST['stock_quantity'];
            $category_id = (int)$_POST['category_id'];
            $image_url = sanitize($_POST['image_url']);
            $status = sanitize($_POST['status']);
            
            if (!empty($name) && !empty($description) && $price > 0 && $stock_quantity >= 0) {
                $query = "UPDATE products SET name = :name, description = :description, price = :price, 
                          stock_quantity = :stock_quantity, category_id = :category_id, image_url = :image_url, 
                          status = :status WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':price', $price);
                $stmt->bindParam(':stock_quantity', $stock_quantity);
                $stmt->bindParam(':category_id', $category_id);
                $stmt->bindParam(':image_url', $image_url);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $id);
                
                if ($stmt->execute()) {
                    $message = 'Produto atualizado com sucesso!';
                } else {
                    $error = 'Erro ao atualizar produto.';
                }
            } else {
                $error = 'Preencha todos os campos obrigatórios.';
            }
            break;
            
        case 'delete':
            $id = (int)$_POST['id'];
            
            $query = "DELETE FROM products WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $message = 'Produto removido com sucesso!';
            } else {
                $error = 'Erro ao remover produto.';
            }
            break;
    }
}

// Buscar produtos
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE :search OR p.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = implode(" AND ", $where_conditions);

$query = "SELECT p.*, c.name as category_name, u.name as seller_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN users u ON p.seller_id = u.id 
          WHERE $where_clause 
          ORDER BY p.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar categorias
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
    <title>Gerenciar Produtos - PC Store Admin</title>
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

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
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
            font-size: 0.8rem;
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

        /* Products Table */
        .products-section {
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

        .product-image {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .product-info {
            min-width: 200px;
        }

        .product-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .product-category {
            font-size: 0.8rem;
            color: #7f8c8d;
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

        .price {
            color: #27ae60;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            white-space: nowrap;
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
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            overflow-y: auto;
            padding: 20px 0;
        }

        .modal-content {
            background-color: white;
            margin: 0 auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: modalSlide 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        @keyframes modalSlide {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 2rem 2rem 1rem;
            border-bottom: 1px solid #ecf0f1;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .modal-title {
            color: #2c3e50;
            font-size: 1.5rem;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-body {
            padding: 2rem;
            max-height: calc(90vh - 200px);
            overflow-y: auto;
        }

        .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
            max-height: 120px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding-top: 2rem;
            border-top: 1px solid #ecf0f1;
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

        .close {
            float: right;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
            margin-top: -10px;
        }

        .close:hover {
            color: #000;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                max-height: 95vh;
                margin: 2.5vh auto;
            }
            
            .modal-body {
                max-height: calc(95vh - 160px);
            }
            
            .form-actions {
                flex-direction: column-reverse;
            }
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 98%;
                max-height: 98vh;
                margin: 1vh auto;
                border-radius: 10px;
            }
            
            .modal-header,
            .modal-body,
            .form-actions {
                padding: 1.5rem;
            }
            
            .modal-body {
                max-height: calc(98vh - 140px);
            }
            
            .modal-title {
                font-size: 1.3rem;
            }
            
            .form-group {
                margin-bottom: 1.5rem;
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
                <li><a href="products.php" class="active"><i class="fas fa-box"></i> Produtos</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categorias</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Usuários</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Pedidos</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Gerenciar Produtos</h1>
                    <p>Adicione, edite e gerencie produtos da loja</p>
                </div>
                <button class="btn btn-primary" onclick="openModal('add')">
                    <i class="fas fa-plus"></i> Adicionar Produto
                </button>
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

            <!-- Filters -->
            <div class="filters">
                <form method="GET">
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
                                <option value="">Todas</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" 
                                            <?= ($category_filter == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">Todos</option>
                                <option value="active" <?= ($status_filter === 'active') ? 'selected' : '' ?>>Ativo</option>
                                <option value="inactive" <?= ($status_filter === 'inactive') ? 'selected' : '' ?>>Inativo</option>
                            </select>
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

            <!-- Products Table -->
            <div class="products-section">
                <div class="section-header">
                    <h2>Produtos (<?= count($products) ?>)</h2>
                </div>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Produto</th>
                                <th>Categoria</th>
                                <th>Preço</th>
                                <th>Estoque</th>
                                <th>Status</th>
                                <th>Vendedor</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 3rem; color: #7f8c8d;">
                                        <i class="fas fa-box" style="font-size: 3rem; margin-bottom: 1rem;"></i><br>
                                        Nenhum produto encontrado
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <div class="product-image">
                                                <i class="fas fa-desktop"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="product-info">
                                                <div class="product-title"><?= htmlspecialchars($product['name']) ?></div>
                                                <div class="product-category"><?= htmlspecialchars($product['category_name'] ?? 'Sem categoria') ?></div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>
                                        <td class="price">R$ <?= number_format($product['price'], 2, ',', '.') ?></td>
                                        <td>
                                            <?php if ($product['stock_quantity'] <= 5): ?>
                                                <span class="badge badge-danger"><?= $product['stock_quantity'] ?></span>
                                            <?php elseif ($product['stock_quantity'] <= 20): ?>
                                                <span class="badge badge-warning"><?= $product['stock_quantity'] ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-success"><?= $product['stock_quantity'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $product['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                                <?= $product['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($product['seller_name']) ?></td>
                                        <td>
                                            <div class="actions">
                                                <button class="btn btn-primary btn-small" 
                                                        onclick="editProduct(<?= htmlspecialchars(json_encode($product)) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Tem certeza que deseja excluir este produto?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $product['id'] ?>">
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

    <!-- Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">
                    Adicionar Produto
                    <button type="button" class="close" onclick="closeModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </h2>
            </div>
            <div class="modal-body">
                <form method="POST" id="productForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="productId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Nome do Produto *</label>
                            <input type="text" id="name" name="name" required>
                        </div>

                        <div class="form-group">
                            <label for="category_id">Categoria *</label>
                            <select id="category_id" name="category_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="price">Preço *</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" required>
                        </div>

                        <div class="form-group">
                            <label for="stock_quantity">Estoque *</label>
                            <input type="number" id="stock_quantity" name="stock_quantity" min="0" required>
                        </div>

                        <div class="form-group" id="statusGroup" style="display: none;">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="image_url">URL da Imagem</label>
                            <input type="url" id="image_url" name="image_url" placeholder="https://exemplo.com/imagem.jpg">
                        </div>

                        <div class="form-group full-width">
                            <label for="description">Descrição *</label>
                            <textarea id="description" name="description" required placeholder="Descreva o produto..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" form="productForm" class="btn btn-success">
                    <i class="fas fa-save"></i> Salvar
                </button>
            </div>
        </div>
    </div>

    <script>
        function openModal(action) {
            const modal = document.getElementById('productModal');
            const form = document.getElementById('productForm');
            const title = document.getElementById('modalTitle');
            
            form.reset();
            document.getElementById('formAction').value = action;
            document.getElementById('statusGroup').style.display = action === 'edit' ? 'block' : 'none';
            
            if (action === 'add') {
                title.innerHTML = 'Adicionar Produto <button type="button" class="close" onclick="closeModal()"><i class="fas fa-times"></i></button>';
                document.getElementById('productId').value = '';
            }
            
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Impede scroll do body
            
            // Foco no primeiro campo
            setTimeout(() => {
                document.getElementById('name').focus();
            }, 100);
        }

        function editProduct(product) {
            openModal('edit');
            
            document.getElementById('modalTitle').innerHTML = 'Editar Produto <button type="button" class="close" onclick="closeModal()"><i class="fas fa-times"></i></button>';
            document.getElementById('productId').value = product.id;
            document.getElementById('name').value = product.name;
            document.getElementById('description').value = product.description;
            document.getElementById('price').value = product.price;
            document.getElementById('stock_quantity').value = product.stock_quantity;
            document.getElementById('category_id').value = product.category_id || '';
            document.getElementById('image_url').value = product.image_url || '';
            document.getElementById('status').value = product.status;
        }

        function closeModal() {
            const modal = document.getElementById('productModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restaura scroll do body
        }

        // Fechar modal clicando fora
        window.onclick = function(event) {
            const modal = document.getElementById('productModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Fechar modal com ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Auto-submit filtros
        document.querySelectorAll('#search, #category, #status').forEach(element => {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Validação do formulário
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const price = parseFloat(document.getElementById('price').value);
            const stock = parseInt(document.getElementById('stock_quantity').value);
            
            if (price <= 0) {
                e.preventDefault();
                alert('O preço deve ser maior que zero.');
                document.getElementById('price').focus();
                return false;
            }
            
            if (stock < 0) {
                e.preventDefault();
                alert('O estoque não pode ser negativo.');
                document.getElementById('stock_quantity').focus();
                return false;
            }
            
            // Adicionar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            submitBtn.disabled = true;
            
            // Se houver erro, restaurar botão
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });

        // Auto-resize textarea
        document.getElementById('description').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Prévia da imagem
        document.getElementById('image_url').addEventListener('input', function() {
            const url = this.value.trim();
            let previewContainer = document.getElementById('imagePreview');
            
            if (!previewContainer) {
                previewContainer = document.createElement('div');
                previewContainer.id = 'imagePreview';
                previewContainer.style.cssText = `
                    margin-top: 1rem;
                    padding: 1rem;
                    border: 2px dashed #e1e5e9;
                    border-radius: 10px;
                    text-align: center;
                    background: #f8f9fa;
                    display: none;
                `;
                this.parentNode.appendChild(previewContainer);
            }
            
            if (url && isValidImageUrl(url)) {
                showImagePreview(previewContainer, url);
            } else {
                hideImagePreview(previewContainer);
            }
        });

        function isValidImageUrl(url) {
            return /\.(jpg|jpeg|png|webp|gif)$/i.test(url) || url.includes('unsplash.com') || url.includes('images.');
        }

        function showImagePreview(container, url) {
            container.innerHTML = `
                <div style="margin-bottom: 1rem; color: #666;">
                    <i class="fas fa-image"></i> Prévia da Imagem
                </div>
                <img src="${url}" alt="Prévia" 
                     style="max-width: 200px; max-height: 150px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                     onerror="this.parentElement.innerHTML='<div style=color:#e74c3c><i class=fas fa-exclamation-triangle></i> Erro ao carregar imagem</div>'">
                <div style="margin-top: 0.5rem; font-size: 0.8rem; color: #666;">
                    A imagem será exibida assim nos produtos
                </div>
            `;
            container.style.display = 'block';
        }

        function hideImagePreview(container) {
            container.style.display = 'none';
            container.innerHTML = '';
        }
    </script>
</body>
</html>