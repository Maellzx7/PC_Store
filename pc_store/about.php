<?php
require_once 'config/database.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre Nós - PC Store</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

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
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: #667eea;
        }

        .nav-links a.active {
            color: #667eea;
            font-weight: 600;
        }

        .user-menu {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9)),
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            color: white;
            text-align: center;
            padding: 4rem 0;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        /* About Section */
        .about-section {
            background: white;
            padding: 4rem 0;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 3rem;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 2px;
        }

        .about-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
            font-size: 1.1rem;
            color: #555;
            line-height: 1.8;
            margin-bottom: 4rem;
        }

        /* Team Section */
        .team-section {
            background: linear-gradient(135deg, #f8f9ff 0%, #e3e7ff 100%);
            padding: 4rem 0;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .team-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .team-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .team-card:hover::before {
            opacity: 1;
        }

        .team-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 3rem;
            position: relative;
            z-index: 1;
        }

        .team-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .team-role {
            color: #667eea;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .team-description {
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9ff;
            color: #667eea;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        /* Contact Section */
        .contact-section {
            background: white;
            padding: 4rem 0;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .contact-card {
            background: linear-gradient(135deg, #f8f9ff, #e3e7ff);
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .contact-card:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }

        .contact-card i {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .contact-card h3 {
            color: #333;
            margin-bottom: 1rem;
        }

        .contact-card p {
            color: #666;
        }

        .contact-card a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .contact-card a:hover {
            text-decoration: underline;
        }

        /* Footer */
        footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 2rem 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .team-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .contact-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        .cart-icon {
            position: relative;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">
                <i class="fas fa-desktop"></i> PC Store
            </div>
            
            <ul class="nav-links">
                <li><a href="index.php">Início</a></li>
                <li><a href="products.php">Produtos</a></li>
                <li><a href="index.php#categorias">Categorias</a></li>
                <li><a href="about.php" class="active">Sobre</a></li>
            </ul>
            
            <div class="user-menu">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="admin/dashboard.php" class="btn btn-outline btn-small">
                            <i class="fas fa-cog"></i> Admin
                        </a>
                    <?php elseif (isSeller()): ?>
                        <a href="seller/dashboard.php" class="btn btn-outline btn-small">
                            <i class="fas fa-store"></i> Vendedor
                        </a>
                    <?php endif; ?>
                    
                    <a href="cart.php" class="btn btn-outline btn-small cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count">0</span>
                    </a>
                    
                    <span>Olá, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?></span>
                    <a href="logout.php" class="btn btn-primary btn-small">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">
                        <i class="fas fa-sign-in-alt"></i> Entrar
                    </a>
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Cadastrar
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Sobre a PC Store</h1>
                    <p>Conheça nossa história, nossa equipe e nossos valores</p>
                </div>
            </div>
        </section>

        <section class="about-section">
            <div class="container">
                <h2 class="section-title">Nossa História</h2>
                <div class="about-content">
                    <p>
                        Tudo começou em 2025, durante o curso de Desenvolvimento de Sistemas. Dois amigos se conheceram em uma aula de Programação Web e foram sorteados para fazer dupla em um projeto. O que parecia ser apenas mais um trabalho do curso se transformou em algo muito maior.
O desafio era criar um e-commerce completo do zero. Inicialmente, achamos que seria tranquilo - "é só uma lojinha básica", pensávamos. Escolhemos fazer uma loja de componentes de computador porque um de nós estava montando um PC gamer na época e conhecia um pouco do mercado.
Mas conforme fomos desenvolvendo, percebemos que o projeto era bem mais complexo do que imaginávamos. Tínhamos que criar sistema de usuários, carrinho de compras, área administrativa, controle de estoque, sistema de pagamento... Era front-end e back-end trabalhando juntos pela primeira vez.
Foram muitas noites viradas debuggando código PHP, estruturando banco de dados MySQL, e tentando fazer o CSS ficar bonito. Entre erros 500, consultas SQL que não funcionavam e JavaScript que simplesmente não queria cooperar, fomos aprendendo na prática.
O legal é que cada obstáculo nos ensinou algo novo. Quando o sistema de login não funcionava, aprendemos sobre sessões e segurança. Quando o carrinho perdia os itens, descobrimos como gerenciar estados. Quando o site ficou lento, entendemos sobre otimização de consultas no banco.
                    </p>
                    <br>
                    <p>
                        No final das contas, conseguimos muito mais do que esperávamos. O projeto ficou funcional, bonito e completo. Esse projeto acadêmico se tornou a base da PC Store. Toda a experiência que ganhamos desenvolvendo em PHP e MySQL, criando nossa primeira aplicação completa com front e back-end, foi fundamental e podemos dizer que foi o melhor projeto em que trabalhamos.
                </div>
            </div>
        </section>

        <section class="team-section">
            <div class="container">
                <h2 class="section-title">Nossa Equipe</h2>
                <div class="team-grid">
                    <div class="team-card">
                        <div class="team-avatar">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 class="team-name">José Paulo</h3>
                        <p class="team-role">CEO & Fundador</p>
                        <p class="team-description">
                            Estudante do curso de Desenvolvimento de sistemas da Etec de Peruíbe.
                        </p>
                        <div class="social-links">
                            <a href="sobrinho.josepaulo@gmail.com" class="social-link" title="Email">
                                <i class="fas fa-envelope"></i>
                            </a>
                            <a href="https://github.com/Maellzx7" class="social-link" title="GitHub">
                                <i class="fab fa-github"></i>
                            </a>
                            <a href="https://instagram.com/maell.zx7" class="social-link" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="https://linkedin.com/in/josepaulo" class="social-link" title="LinkedIn">
                                <i class="fab fa-linkedin"></i>
                            </a>
                        </div>
                    </div>

                    <div class="team-card">
                        <div class="team-avatar">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <h3 class="team-name">Anthony</h3>
                        <p class="team-role">Co-Fundador</p>
                        <p class="team-description">
                            Estudante do curso de Desenvolvimento de sistemas da Etec de Peruíbe.
                        </p>
                        <div class="social-links">
                            <a href="anthonyrodrigues@gmail.com" class="social-link" title="Email">
                                <i class="fas fa-envelope"></i>
                            </a>
                            <a href="https://github.com/#" class="social-link" title="GitHub">
                                <i class="fab fa-github"></i>
                            </a>
                            <a href="https://instagram.com/mirainikkiksksksk" class="social-link" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="https://linkedin.com/anthonyrodrigues" class="social-link" title="LinkedIn">
                                <i class="fab fa-linkedin"></i>
                            </a>
                        </div>
                    </div>

                    
                </div>
            </div>
        </section>

        <section class="contact-section">
            <div class="container">
                <h2 class="section-title">Entre em Contato</h2>
                <div class="contact-grid">
                    <div class="contact-card">
                        <i class="fas fa-envelope"></i>
                        <h3>Email Geral</h3>
                        <p><a href="lepolepochalenge@gmail.com">lepolepochalenge@gmail.com</a></p>
                    </div>

                    <div class="contact-card">
                        <i class="fas fa-headset"></i>
                        <h3>Suporte</h3>
                        <p><a href="lepolepochalenge@gmail.com">lepolepochalenge@gmail.com</a></p>
                    </div>

                    <div class="contact-card">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Vendas</h3>
                        <p><a href="mailto:vendas@pcstore.com.br">vendas@pcstore.com.br</a></p>
                    </div>

                    <div class="contact-card">
                        <i class="fab fa-instagram"></i>
                        <h3>Instagram</h3>
                        <p><a href="https://instagram.com/pcstore_oficial" target="_blank">@pcstore_oficial</a></p>
                    </div>

                    <div class="contact-card">
                        <i class="fab fa-github"></i>
                        <h3>GitHub</h3>
                        <p><a href="https://github.com/Maellzx7/PC_Store.git" target="_blank">github.com/pcstore</a></p>
                    </div>

                    <div class="contact-card">
                        <i class="fas fa-phone"></i>
                        <h3>Telefone</h3>
                        <p>
                            <a href="tel:+551133334444">(11) 7777-7777</a><br>
                            <small>Segunda a Sexta, 9h às 18h</small>
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 PC Store. Todos os direitos reservados.</p>
            <p>
                <a href="mailto:contato@pcstore.com.br" style="color: #ccc; text-decoration: none;">
                    contato@pcstore.com.br
                </a>
                | 
                <a href="https://instagram.com/pcstore_oficial" style="color: #ccc; text-decoration: none;">
                    @pcstore_oficial
                </a>
            </p>
        </div>
    </footer>

    <script>
        function updateCartCount() {
            <?php if (isLoggedIn()): ?>
                fetch('api/cart_count.php')
                    .then(response => response.json())
                    .then(data => {
                        document.querySelector('.cart-count').textContent = data.count;
                    })
                    .catch(error => console.error('Erro:', error));
            <?php endif; ?>
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            
            document.querySelectorAll('.team-card').forEach((card, index) => {
                card.style.animationDelay = `${index * 0.2}s`;
            });

            document.querySelectorAll('.social-link').forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px) scale(1.1)';
                });
                
                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        });
    </script>
</body>
</html>