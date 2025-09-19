<?php
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Verificar se há itens no carrinho
$query = "SELECT c.id as cart_id, c.quantity, p.id, p.name, p.price, p.stock_quantity, cat.name as category_name
          FROM cart c 
          INNER JOIN products p ON c.product_id = p.id 
          LEFT JOIN categories cat ON p.category_id = cat.id 
          WHERE c.user_id = :user_id AND p.status = 'active'
          ORDER BY c.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart_items)) {
    header('Location: cart.php');
    exit();
}

// Calcular totais
$subtotal = 0;
$items_valid = true;
$error_message = '';

foreach ($cart_items as $item) {
    if ($item['stock_quantity'] < $item['quantity']) {
        $items_valid = false;
        $error_message = "Produto '{$item['name']}' não possui estoque suficiente.";
        break;
    }
    $subtotal += $item['price'] * $item['quantity'];
}

$shipping = $subtotal > 200 ? 0 : 15.00;
$total = $subtotal + $shipping;

$success = false;
$order_id = null;

// Processar pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $items_valid) {
    $payment_method = sanitize($_POST['payment_method']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $zipcode = sanitize($_POST['zipcode']);
    $phone = sanitize($_POST['phone']);
    
    if (empty($payment_method) || empty($address) || empty($city) || empty($zipcode)) {
        $error_message = 'Por favor, preencha todos os campos obrigatórios.';
    } else {
        try {
            $db->beginTransaction();
            
            // Criar pedido
            $query = "INSERT INTO orders (user_id, total_amount, status) VALUES (:user_id, :total_amount, 'pending')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':total_amount', $total);
            $stmt->execute();
            
            $order_id = $db->lastInsertId();
            
            // Adicionar itens do pedido e atualizar estoque
            foreach ($cart_items as $item) {
                // Verificar estoque novamente
                $query = "SELECT stock_quantity FROM products WHERE id = :product_id FOR UPDATE";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':product_id', $item['id']);
                $stmt->execute();
                $current_stock = $stmt->fetch(PDO::FETCH_ASSOC)['stock_quantity'];
                
                if ($current_stock < $item['quantity']) {
                    throw new Exception("Estoque insuficiente para {$item['name']}");
                }
                
                // Adicionar item do pedido
                $query = "INSERT INTO order_items (order_id, product_id, quantity, price) 
                          VALUES (:order_id, :product_id, :quantity, :price)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->bindParam(':product_id', $item['id']);
                $stmt->bindParam(':quantity', $item['quantity']);
                $stmt->bindParam(':price', $item['price']);
                $stmt->execute();
                
                // Atualizar estoque
                $query = "UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :product_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':quantity', $item['quantity']);
                $stmt->bindParam(':product_id', $item['id']);
                $stmt->execute();
            }
            
            // Limpar carrinho
            $query = "DELETE FROM cart WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $db->commit();
            $success = true;
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = 'Erro ao processar pedido: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - PC Store</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #667eea;
            text-decoration: none;
        }

        .checkout-steps {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #7f8c8d;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .step.active {
            color: #27ae60;
            font-weight: bold;
        }

        .step-separator {
            width: 30px;
            height: 2px;
            background: #ecf0f1;
        }

        .step-separator.active {
            background: #27ae60;
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-size: 3rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #7f8c8d;
            font-size: 1.2rem;
        }

        /* Success Message */
        .success-container {
            background: white;
            border-radius: 20px;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .success-icon {
            font-size: 4rem;
            color: #27ae60;
            margin-bottom: 2rem;
        }

        .success-title {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .success-text {
            color: #7f8c8d;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .order-number {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin: 2rem 0;
            font-size: 1.2rem;
            color: #2c3e50;
        }

        /* Checkout Layout */
        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 3rem;
        }

        .checkout-form,
        .order-summary {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .order-summary {
            height: fit-content;
            position: sticky;
            top: 120px;
        }

        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.8rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .payment-methods {
            display: grid;
            gap: 1rem;
        }

        .payment-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .payment-option input[type="radio"] {
            margin-right: 1rem;
            width: auto;
        }

        .payment-option.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .payment-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
            width: 30px;
            text-align: center;
        }

        /* Order Summary */
        .order-items {
            margin-bottom: 2rem;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .item-details {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .item-total {
            font-weight: bold;
            color: #27ae60;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }

        .summary-row.total {
            border-top: 2px solid #ecf0f1;
            padding-top: 1rem;
            margin-top: 1rem;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .summary-row .label {
            color: #7f8c8d;
        }

        .summary-row .value {
            color: #2c3e50;
            font-weight: 600;
        }

        .summary-row.total .value {
            color: #27ae60;
            font-size: 1.5rem;
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
            width: 100%;
            justify-content: center;
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
            border: 2px solid #667eea;
            color: #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border: 1px solid #fcc;
            text-align: center;
        }

        .security-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #6c757d;
            text-align: center;
        }

        @media (max-width: 1024px) {
            .checkout-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .order-summary {
                position: static;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }
            
            .checkout-steps {
                display: none;
            }
            
            .main-content {
                padding: 1rem 0;
            }
            
            .checkout-form,
            .order-summary {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-desktop"></i> PC Store
            </a>
            
            <div class="checkout-steps">
                <div class="step">
                    <i class="fas fa-shopping-cart"></i> Carrinho
                </div>
                <div class="step-separator active"></div>
                <div class="step active">
                    <i class="fas fa-credit-card"></i> Checkout
                </div>
                <div class="step-separator"></div>
                <div class="step">
                    <i class="fas fa-check"></i> Confirmação
                </div>
            </div>
            
            <div>
                <span>Olá, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
            </div>
        </nav>
    </header>

    <main class="main-content container">
        <?php if ($success): ?>
            <div class="success-container">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="success-title">Pedido Realizado com Sucesso!</h1>
                <p class="success-text">Obrigado por sua compra. Seu pedido foi processado e será enviado em breve.</p>
                
                <div class="order-number">
                    <strong>Número do Pedido: #<?= sprintf('%06d', $order_id) ?></strong>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-home"></i> Voltar à Loja
                    </a>
                    <a href="orders.php" class="btn btn-primary">
                        <i class="fas fa-list"></i> Ver Meus Pedidos
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="page-header">
                <h1><i class="fas fa-credit-card"></i> Finalizar Compra</h1>
                <p>Complete seus dados para finalizar o pedido</p>
            </div>

            <?php if ($error_message): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!$items_valid): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    Há problemas com itens do seu carrinho. 
                    <a href="cart.php" style="color: #c33; text-decoration: underline;">Clique aqui para revisar</a>
                </div>
            <?php else: ?>
                <div class="checkout-layout">
                    <div class="checkout-form">
                        <form method="POST" id="checkoutForm">
                            <!-- Dados de Entrega -->
                            <section>
                                <h2 class="section-title">
                                    <i class="fas fa-truck"></i>
                                    Dados de Entrega
                                </h2>
                                
                                <div class="form-group">
                                    <label for="address">Endereço Completo *</label>
                                    <input type="text" id="address" name="address" required 
                                           placeholder="Rua, número, complemento">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="city">Cidade *</label>
                                        <input type="text" id="city" name="city" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="zipcode">CEP *</label>
                                        <input type="text" id="zipcode" name="zipcode" required 
                                               placeholder="00000-000" pattern="[0-9]{5}-?[0-9]{3}">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Telefone para Contato</label>
                                    <input type="tel" id="phone" name="phone" 
                                           placeholder="(11) 99999-9999">
                                </div>
                            </section>

                            <!-- Forma de Pagamento -->
                            <section>
                                <h2 class="section-title">
                                    <i class="fas fa-credit-card"></i>
                                    Forma de Pagamento
                                </h2>
                                
                                <div class="payment-methods">
                                    <label class="payment-option" onclick="selectPayment(this)">
                                        <input type="radio" name="payment_method" value="credit_card" required>
                                        <i class="fas fa-credit-card payment-icon" style="color: #3498db;"></i>
                                        <div>
                                            <strong>Cartão de Crédito</strong>
                                            <br><small>Visa, Mastercard, Elo</small>
                                        </div>
                                    </label>
                                    
                                    <label class="payment-option" onclick="selectPayment(this)">
                                        <input type="radio" name="payment_method" value="debit_card" required>
                                        <i class="fas fa-credit-card payment-icon" style="color: #27ae60;"></i>
                                        <div>
                                            <strong>Cartão de Débito</strong>
                                            <br><small>Pagamento à vista</small>
                                        </div>
                                    </label>
                                    
                                    <label class="payment-option" onclick="selectPayment(this)">
                                        <input type="radio" name="payment_method" value="pix" required>
                                        <i class="fab fa-pix payment-icon" style="color: #32BCAD;"></i>
                                        <div>
                                            <strong>PIX</strong>
                                            <br><small>Pagamento instantâneo</small>
                                        </div>
                                    </label>
                                    
                                    <label class="payment-option" onclick="selectPayment(this)">
                                        <input type="radio" name="payment_method" value="boleto" required>
                                        <i class="fas fa-barcode payment-icon" style="color: #e74c3c;"></i>
                                        <div>
                                            <strong>Boleto Bancário</strong>
                                            <br><small>Vencimento em 3 dias</small>
                                        </div>
                                    </label>
                                </div>
                            </section>

                            <button type="submit" class="btn btn-primary" style="margin-top: 2rem;">
                                <i class="fas fa-check"></i>
                                Finalizar Pedido
                            </button>
                            
                            <div class="security-info">
                                <i class="fas fa-shield-alt" style="color: #27ae60;"></i>
                                Seus dados estão protegidos com criptografia SSL
                            </div>
                        </form>
                    </div>

                    <div class="order-summary">
                        <h2 class="section-title">
                            <i class="fas fa-receipt"></i>
                            Resumo do Pedido
                        </h2>
                        
                        <div class="order-items">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="order-item">
                                    <div class="item-image">
                                        <i class="fas fa-desktop"></i>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="item-details">
                                            Qtd: <?= $item['quantity'] ?> × R$ <?= number_format($item['price'], 2, ',', '.') ?>
                                        </div>
                                    </div>
                                    <div class="item-total">
                                        R$ <?= number_format($item['price'] * $item['quantity'], 2, ',', '.') ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="summary-row">
                            <span class="label">Subtotal</span>
                            <span class="value">R$ <?= number_format($subtotal, 2, ',', '.') ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="label">Frete</span>
                            <span class="value">
                                <?= $shipping == 0 ? 'GRÁTIS' : 'R$ ' . number_format($shipping, 2, ',', '.') ?>
                            </span>
                        </div>
                        
                        <div class="summary-row total">
                            <span class="label">Total</span>
                            <span class="value">R$ <?= number_format($total, 2, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script>
        function selectPayment(element) {
            // Remove active class from all options
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add active class to selected option
            element.classList.add('selected');
        }

        // Format CEP
        document.getElementById('zipcode').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 5) {
                value = value.substring(0, 5) + '-' + value.substring(5, 8);
            }
            e.target.value = value;
        });

        // Format phone
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
            }
            if (value.length >= 10) {
                value = value.substring(0, 10) + '-' + value.substring(10, 14);
            }
            e.target.value = value;
        });

        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                e.preventDefault();
                alert('Por favor, selecione uma forma de pagamento.');
                return false;
            }
            
            // Add loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            submitBtn.disabled = true;
        });

        // Auto-select first payment method
        document.addEventListener('DOMContentLoaded', function() {
            const firstPayment = document.querySelector('.payment-option');
            if (firstPayment) {
                firstPayment.click();
            }
        });
    </script>
</body>
</html>