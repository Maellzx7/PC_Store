<?php
require_once '../config/database.php';

requireLogin();
requireSeller();

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Buscar categorias
$query = "SELECT * FROM categories ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $category_id = (int)$_POST['category_id'];
    $image_url = sanitize($_POST['image_url']);
    $seller_id = $_SESSION['user_id'];
    
    if (empty($name) || empty($description) || $price <= 0 || $stock_quantity < 0) {
        $error = 'Por favor, preencha todos os campos corretamente.';
    } else {
        try {
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
                $success = 'Produto adicionado com sucesso!';
                // Limpar campos após sucesso
                $_POST = array();
            } else {
                $error = 'Erro ao adicionar produto.';
            }
        } catch (Exception $e) {
            $error = 'Erro interno do servidor.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Produto - PC Store</title>
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

.form-container {
    background: white;
    border-radius: 20px;
    padding: 3rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    max-width: 800px;
    margin: 0 auto;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.form-group {
    margin-bottom: 2rem;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    margin-bottom: 0.8rem;
    color: #2c3e50;
    font-weight: 600;
    font-size: 1rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e1e5e9;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.9);
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #27ae60;
    box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.price-input {
    position: relative;
}

.price-input::before {
    content: 'R$';
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #27ae60;
    font-weight: bold;
    z-index: 2;
}

.price-input input {
    padding-left: 3rem;
}

.btn {
    padding: 1rem 2rem;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    transition: all 0.3s ease;
    font-family: inherit;
}

.btn-primary {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3);
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

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid #e1e5e9;
}

.alert {
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    text-align: center;
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

.form-help {
    font-size: 0.9rem;
    color: #6c757d;
    margin-top: 0.5rem;
}

.required {
    color: #e74c3c;
}

.preview-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 2rem;
    margin-top: 2rem;
    border: 2px dashed #dee2e6;
}

.preview-title {
    color: #495057;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

@media (max-width: 1024px) {
    .seller-container {
        grid-template-columns: 1fr;
    }
    
    .sidebar {
        display: none;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 1rem;
    }
    
    .form-container {
        padding: 2rem;
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .form-actions {
        flex-direction: column;
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
                <li><a href="add_product.php" class="active"><i class="fas fa-plus"></i> Adicionar Produto</a></li>
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
                <h1><i class="fas fa-plus"></i> Adicionar Produto</h1>
                <p>Cadastre um novo produto no seu catálogo</p>
            </div>

            <div class="form-container">
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

                <form method="POST" id="productForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Nome do Produto <span class="required">*</span></label>
                            <input type="text" id="name" name="name" required 
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                   placeholder="Ex: PC Gamer RTX 4060">
                            <div class="form-help">Nome comercial do produto</div>
                        </div>

                        <div class="form-group">
                            <label for="category_id">Categoria <span class="required">*</span></label>
                            <select id="category_id" name="category_id" required>
                                <option value="">Selecione uma categoria</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" 
                                            <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="price">Preço <span class="required">*</span></label>
                            <div class="price-input">
                                <input type="number" id="price" name="price" step="0.01" min="0.01" required
                                       value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                                       placeholder="0,00">
                            </div>
                            <div class="form-help">Preço de venda em reais</div>
                        </div>

                        <div class="form-group">
                            <label for="stock_quantity">Quantidade em Estoque <span class="required">*</span></label>
                            <input type="number" id="stock_quantity" name="stock_quantity" min="0" required
                                   value="<?= htmlspecialchars($_POST['stock_quantity'] ?? '') ?>"
                                   placeholder="0">
                            <div class="form-help">Quantidade disponível para venda</div>
                        </div>

                        <div class="form-group full-width">
                            <label for="image_url">URL da Imagem</label>
                            <input type="url" id="image_url" name="image_url"
                                   value="<?= htmlspecialchars($_POST['image_url'] ?? '') ?>"
                                   placeholder="https://exemplo.com/imagem.jpg">
                            <div class="form-help">Link para a imagem do produto (opcional)</div>
                        </div>

                        <div class="form-group full-width">
                            <label for="description">Descrição do Produto <span class="required">*</span></label>
                            <textarea id="description" name="description" required
                                      placeholder="Descreva as características, especificações e diferenciais do produto..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            <div class="form-help">Descrição detalhada que será exibida na página do produto</div>
                        </div>
                    </div>

                    <!-- Preview Section -->
                    <div class="preview-section" id="productPreview" style="display: none;">
                        <div class="preview-title">
                            <i class="fas fa-eye"></i>
                            Prévia do Produto
                        </div>
                        <div id="previewContent"></div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Adicionar Produto
                        </button>
                        <a href="products.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i>
                            Voltar
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Preview functionality
        function updatePreview() {
            const name = document.getElementById('name').value;
            const price = document.getElementById('price').value;
            const description = document.getElementById('description').value;
            const categorySelect = document.getElementById('category_id');
            const category = categorySelect.options[categorySelect.selectedIndex]?.text || '';
            
            if (name || price || description) {
                const previewContent = `
                    <div style="display: flex; gap: 2rem; align-items: start;">
                        <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #27ae60, #2ecc71); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                            <i class="fas fa-desktop"></i>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="color: #2c3e50; margin-bottom: 0.5rem;">${name || 'Nome do Produto'}</h3>
                            <p style="color: #7f8c8d; font-size: 0.9rem; margin-bottom: 0.5rem;">${category}</p>
                            <p style="color: #27ae60; font-weight: bold; font-size: 1.2rem; margin-bottom: 1rem;">
                                ${price ? 'R$ ' + parseFloat(price).toFixed(2).replace('.', ',') : 'R$ 0,00'}
                            </p>
                            <p style="color: #666; font-size: 0.9rem; line-height: 1.4;">
                                ${description || 'Descrição do produto aparecerá aqui...'}
                            </p>
                        </div>
                    </div>
                `;
                
                document.getElementById('previewContent').innerHTML = previewContent;
                document.getElementById('productPreview').style.display = 'block';
            } else {
                document.getElementById('productPreview').style.display = 'none';
            }
        }

        // Add event listeners for real-time preview
        document.getElementById('name').addEventListener('input', updatePreview);
        document.getElementById('price').addEventListener('input', updatePreview);
        document.getElementById('description').addEventListener('input', updatePreview);
        document.getElementById('category_id').addEventListener('change', updatePreview);

        // Form validation
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
                alert('A quantidade em estoque não pode ser negativa.');
                document.getElementById('stock_quantity').focus();
                return false;
            }
        });

        // Format price input
        document.getElementById('price').addEventListener('input', function() {
            let value = this.value;
            if (value && !isNaN(value)) {
                // Update preview when price changes
                updatePreview();
            }
        });

        // Auto-resize textarea
        document.getElementById('description').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Character counter for description
        const descriptionTextarea = document.getElementById('description');
        const maxChars = 1000;

        function updateCharCounter() {
            const currentLength = descriptionTextarea.value.length;
            const remaining = maxChars - currentLength;
            
            let counterElement = document.getElementById('charCounter');
            if (!counterElement) {
                counterElement = document.createElement('div');
                counterElement.id = 'charCounter';
                counterElement.className = 'form-help';
                counterElement.style.textAlign = 'right';
                counterElement.style.marginTop = '0.5rem';
                descriptionTextarea.parentNode.appendChild(counterElement);
            }
            
            counterElement.textContent = `${currentLength}/${maxChars} caracteres`;
            counterElement.style.color = remaining < 100 ? '#e74c3c' : '#6c757d';
        }

        descriptionTextarea.addEventListener('input', updateCharCounter);
        descriptionTextarea.setAttribute('maxlength', maxChars);
        updateCharCounter();

        // Save draft functionality
        function saveDraft() {
            const formData = {
                name: document.getElementById('name').value,
                category_id: document.getElementById('category_id').value,
                price: document.getElementById('price').value,
                stock_quantity: document.getElementById('stock_quantity').value,
                image_url: document.getElementById('image_url').value,
                description: document.getElementById('description').value
            };
            
            localStorage.setItem('product_draft', JSON.stringify(formData));
        }

        function loadDraft() {
            const draft = localStorage.getItem('product_draft');
            if (draft) {
                const formData = JSON.parse(draft);
                Object.keys(formData).forEach(key => {
                    const element = document.getElementById(key);
                    if (element && formData[key]) {
                        element.value = formData[key];
                    }
                });
                updatePreview();
            }
        }

        // Auto-save draft every 30 seconds
        setInterval(saveDraft, 30000);

        // Save draft when form fields change
        document.querySelectorAll('#productForm input, #productForm select, #productForm textarea').forEach(element => {
            element.addEventListener('change', saveDraft);
        });

        // Load draft on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Only load draft if form is empty
            const name = document.getElementById('name').value;
            if (!name) {
                loadDraft();
            }
            
            // Clear draft after successful submission
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === '1') {
                localStorage.removeItem('product_draft');
            }
        });

        // Clear draft when form is submitted
        document.getElementById('productForm').addEventListener('submit', function() {
            localStorage.removeItem('product_draft');
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('productForm').submit();
            }
            
            // Ctrl + P to preview
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                updatePreview();
                document.getElementById('productPreview').scrollIntoView({ behavior: 'smooth' });
            }
        });

        // Show helpful tooltips
        function showTooltip(element, message) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = message;
            tooltip.style.cssText = `
                position: absolute;
                background: #333;
                color: white;
                padding: 0.5rem;
                border-radius: 5px;
                font-size: 0.8rem;
                z-index: 1000;
                max-width: 200px;
                pointer-events: none;
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = element.getBoundingClientRect();
            tooltip.style.top = (rect.bottom + 5) + 'px';
            tooltip.style.left = rect.left + 'px';
            
            setTimeout(() => {
                document.body.removeChild(tooltip);
            }, 3000);
        }

        // Add help icons next to labels
        document.querySelectorAll('.form-group label').forEach(label => {
            const helpIcon = document.createElement('i');
            helpIcon.className = 'fas fa-question-circle';
            helpIcon.style.cssText = `
                margin-left: 0.5rem;
                color: #6c757d;
                cursor: help;
                font-size: 0.8rem;
            `;
            
            helpIcon.addEventListener('click', function() {
                const fieldName = label.getAttribute('for');
                let helpText = '';
                
                switch(fieldName) {
                    case 'name':
                        helpText = 'Digite um nome atrativo e descritivo para seu produto';
                        break;
                    case 'category_id':
                        helpText = 'Selecione a categoria que melhor descreve seu produto';
                        break;
                    case 'price':
                        helpText = 'Defina um preço competitivo baseado no mercado';
                        break;
                    case 'stock_quantity':
                        helpText = 'Informe quantas unidades você tem disponível';
                        break;
                    case 'image_url':
                        helpText = 'Cole o link de uma imagem de boa qualidade do produto';
                        break;
                    case 'description':
                        helpText = 'Detalhe as características e benefícios do produto';
                        break;
                }
                
                showTooltip(this, helpText);
            });
            
            if (!label.querySelector('.required')) {
                label.appendChild(helpIcon);
            }
        });

        // Initialize preview on page load
        updatePreview();
    </script>
</body>
</html>