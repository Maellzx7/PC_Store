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
        case 'promote_to_seller':
            $user_id = (int)$_POST['user_id'];
            
            $query = "UPDATE users SET user_type = 'seller' WHERE id = :user_id AND user_type = 'user'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                $message = 'Usuário promovido a vendedor com sucesso!';
            } else {
                $error = 'Erro ao promover usuário.';
            }
            break;
            
        case 'demote_to_user':
            $user_id = (int)$_POST['user_id'];
            
            // Não permitir rebaixar o admin principal
            $query = "SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $admin_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $query = "SELECT user_type FROM users WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $user_type = $stmt->fetch(PDO::FETCH_ASSOC)['user_type'];
            
            if ($user_type === 'admin' && $admin_count <= 1) {
                $error = 'Não é possível rebaixar o único administrador do sistema.';
            } else {
                $new_type = $user_type === 'admin' ? 'seller' : 'user';
                
                $query = "UPDATE users SET user_type = :new_type WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':new_type', $new_type);
                $stmt->bindParam(':user_id', $user_id);
                
                if ($stmt->execute()) {
                    $message = 'Usuário rebaixado com sucesso!';
                } else {
                    $error = 'Erro ao rebaixar usuário.';
                }
            }
            break;
            
        case 'delete_user':
            $user_id = (int)$_POST['user_id'];
            
            // Não permitir deletar o admin principal ou a si mesmo
            if ($user_id == $_SESSION['user_id']) {
                $error = 'Você não pode deletar sua própria conta.';
            } else {
                $query = "SELECT user_type FROM users WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $user_type = $stmt->fetch(PDO::FETCH_ASSOC)['user_type'];
                
                if ($user_type === 'admin') {
                    $query = "SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $admin_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($admin_count <= 1) {
                        $error = 'Não é possível deletar o único administrador do sistema.';
                        break;
                    }
                }
                
                $query = "DELETE FROM users WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                
                if ($stmt->execute()) {
                    $message = 'Usuário removido com sucesso!';
                } else {
                    $error = 'Erro ao remover usuário.';
                }
            }
            break;
    }
}

// Buscar usuários
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : '';

$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE :search OR email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($type_filter)) {
    $where_conditions[] = "user_type = :type";
    $params[':type'] = $type_filter;
}

$where_clause = implode(" AND ", $where_conditions);

$query = "SELECT * FROM users WHERE $where_clause ORDER BY created_at DESC";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stats = [];
$query = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type";
$stmt = $db->prepare($query);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats[$row['user_type']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - PC Store Admin</title>
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
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-card-admin .stat-card-icon { color: #e74c3c; }
        .stat-card-seller .stat-card-icon { color: #f39c12; }
        .stat-card-user .stat-card-icon { color: #3498db; }

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

        /* Users Table */
        .users-section {
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

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .user-info {
            min-width: 200px;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .user-email {
            font-size: 0.9rem;
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

        .badge-admin {
            background: #fee;
            color: #e74c3c;
        }

        .badge-seller {
            background: #fff3cd;
            color: #f39c12;
        }

        .badge-user {
            background: #e3f2fd;
            color: #3498db;
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

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
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

        .date-info {
            font-size: 0.9rem;
            color: #7f8c8d;
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
                <li><a href="users.php" class="active"><i class="fas fa-users"></i> Usuários</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Pedidos</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>Gerenciar Usuários</h1>
                    <p>Administre usuários e suas permissões no sistema</p>
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
                <div class="stat-card stat-card-admin">
                    <div class="stat-card-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="stat-card-value"><?= $stats['admin'] ?? 0 ?></div>
                    <div class="stat-card-label">Administradores</div>
                </div>

                <div class="stat-card stat-card-seller">
                    <div class="stat-card-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-card-value"><?= $stats['seller'] ?? 0 ?></div>
                    <div class="stat-card-label">Vendedores</div>
                </div>

                <div class="stat-card stat-card-user">
                    <div class="stat-card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-card-value"><?= $stats['user'] ?? 0 ?></div>
                    <div class="stat-card-label">Usuários</div>
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
                                   placeholder="Nome ou email...">
                        </div>
                        
                        <div class="filter-group">
                            <label for="type">Tipo de Usuário</label>
                            <select id="type" name="type">
                                <option value="">Todos</option>
                                <option value="admin" <?= ($type_filter === 'admin') ? 'selected' : '' ?>>Administrador</option>
                                <option value="seller" <?= ($type_filter === 'seller') ? 'selected' : '' ?>>Vendedor</option>
                                <option value="user" <?= ($type_filter === 'user') ? 'selected' : '' ?>>Usuário</option>
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

            <!-- Users Table -->
            <div class="users-section">
                <div class="section-header">
                    <h2>Usuários (<?= count($users) ?>)</h2>
                </div>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Usuário</th>
                                <th>Tipo</th>
                                <th>Cadastro</th>
                                <th>Última Atualização</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 3rem; color: #7f8c8d;">
                                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem;"></i><br>
                                        Nenhum usuário encontrado
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($user['name'], 0, 2)) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                                <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $user['user_type'] ?>">
                                                <?= ucfirst($user['user_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                                <br>
                                                <small><?= date('H:i', strtotime($user['created_at'])) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?= date('d/m/Y', strtotime($user['updated_at'])) ?>
                                                <br>
                                                <small><?= date('H:i', strtotime($user['updated_at'])) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <?php if ($user['user_type'] === 'user'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="promote_to_seller">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="btn btn-success btn-small" 
                                                                onclick="return confirm('Promover usuário a vendedor?')">
                                                            <i class="fas fa-arrow-up"></i> Promover
                                                        </button>
                                                    </form>
                                                <?php elseif ($user['user_type'] === 'seller' || $user['user_type'] === 'admin'): ?>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="demote_to_user">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <button type="submit" class="btn btn-warning btn-small" 
                                                                    onclick="return confirm('Rebaixar usuário?')">
                                                                <i class="fas fa-arrow-down"></i> Rebaixar
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-small" 
                                                                onclick="return confirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.')">
                                                            <i class="fas fa-trash"></i> Excluir
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="btn btn-small" style="background: #bdc3c7; color: white; cursor: not-allowed;">
                                                        <i class="fas fa-user"></i> Você
                                                    </span>
                                                <?php endif; ?>
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
        // Auto-submit filters
        document.querySelectorAll('#search, #type').forEach(element => {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Search with delay
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>
</body>
</html>