-- Criação do banco de dados
CREATE DATABASE IF NOT EXISTS pc_store;
USE pc_store;

-- Tabela de usuários
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'seller', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de categorias
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de produtos
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    category_id INT,
    image_url VARCHAR(500),
    seller_id INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabela de carrinho
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Tabela de pedidos
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de itens do pedido
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Inserção de dados iniciais
-- Admin padrão
INSERT INTO users (name, email, password, user_type) VALUES 
('Administrador', 'admin@pcstore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Categorias iniciais
INSERT INTO categories (name, description) VALUES 
('PCs Prontos', 'Computadores montados e prontos para uso'),
('Processadores', 'CPUs Intel e AMD'),
('Placas de Vídeo', 'GPUs para gaming e trabalho'),
('Memória RAM', 'Memórias DDR4 e DDR5'),
('Armazenamento', 'HDs, SSDs e NVMe'),
('Placas-Mãe', 'Motherboards para diferentes sockets'),
('Periféricos', 'Mouse, teclado, monitores e headsets'),
('Gabinetes', 'Cases para montagem de PCs');

-- Produtos de exemplo
INSERT INTO products (name, description, price, stock_quantity, category_id, seller_id) VALUES 
('PC Gamer Completo RTX 4060', 'PC completo com RTX 4060, Ryzen 5 5600, 16GB RAM, SSD 500GB', 3499.99, 10, 1, 1),
('Intel Core i5-13400F', 'Processador Intel 13ª geração, 10 cores, 20 threads', 899.99, 25, 2, 1),
('RTX 4070 Super', 'Placa de vídeo NVIDIA RTX 4070 Super 12GB', 2899.99, 8, 3, 1),
('Corsair Vengeance 32GB DDR4', 'Kit 2x16GB DDR4 3200MHz', 699.99, 15, 4, 1),
('SSD Samsung 980 1TB', 'SSD NVMe M.2 1TB, leitura até 3500MB/s', 499.99, 20, 5, 1);