<?php
// Database Configuration
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "techhat_db";

try {
    // Connect to MySQL server (without DB)
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '$dbname' created or already exists.<br>";

    // Connect to the specific database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL to create tables
    $sql = "
    -- Users Table
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        role ENUM('admin', 'user') DEFAULT 'user',
        image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Categories Table
    CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Products Table
    CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        video_url VARCHAR(255),
        is_flash_sale BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    );

    -- Product Variants Table
    CREATE TABLE IF NOT EXISTS product_variants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        name VARCHAR(100) NOT NULL, -- e.g., 'Red - XL'
        price DECIMAL(10, 2) NOT NULL,
        offer_price DECIMAL(10, 2) DEFAULT 0.00,
        stock_quantity INT DEFAULT 0,
        sku VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );

    -- Product Images Table
    CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        is_primary BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );

    -- Orders Table
    CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        total_amount DECIMAL(10, 2) NOT NULL,
        status ENUM('Pending', 'Processing', 'Delivered', 'Cancelled') DEFAULT 'Pending',
        payment_method VARCHAR(50),
        transaction_id VARCHAR(100),
        shipping_address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    );

    -- Order Items Table
    CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT,
        variant_id INT,
        quantity INT NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
        FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
    );

    -- POS Sales Table
    CREATE TABLE IF NOT EXISTS pos_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        total_amount DECIMAL(10, 2) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'Cash',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- POS Sale Items Table (To link products to POS sales)
    CREATE TABLE IF NOT EXISTS pos_sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pos_sale_id INT NOT NULL,
        product_id INT,
        variant_id INT,
        quantity INT NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        FOREIGN KEY (pos_sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
        FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
    );

    -- Stock Movements Table
    CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT,
        variant_id INT,
        quantity INT NOT NULL, -- Positive for add, negative for remove
        type ENUM('sale', 'purchase', 'adjustment', 'return') NOT NULL,
        reference_id INT, -- order_id or pos_sale_id
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
        FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
    );

    -- Accounts Income Table
    CREATE TABLE IF NOT EXISTS accounts_income (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source VARCHAR(100),
        amount DECIMAL(10, 2) NOT NULL,
        description TEXT,
        date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Accounts Expense Table
    CREATE TABLE IF NOT EXISTS accounts_expense (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(100),
        amount DECIMAL(10, 2) NOT NULL,
        description TEXT,
        date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Flash Sales Table
    CREATE TABLE IF NOT EXISTS flash_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255),
        start_time DATETIME,
        end_time DATETIME,
        status BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Flash Sale Products (Many-to-Many relationship if needed, or just use is_flash_sale in products. 
    -- Requirement says 'Product flag (is_flash_sale)', but also 'Flash Sale System... Discount percentage'. 
    -- If discount is per product, we might need a linking table or columns in product/variant. 
    -- Let's add a table to manage specific flash sale items if multiple flash sales exist, or just stick to the flag as per requirement A.
    -- Requirement F says 'Flash Sale System... Product flag (is_flash_sale)... Discount percentage'.
    -- Let's assume global flash sale or per product. I'll add a table for mapping products to flash sales with discount.)
    CREATE TABLE IF NOT EXISTS flash_sale_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        flash_sale_id INT NOT NULL,
        product_id INT NOT NULL,
        discount_percentage DECIMAL(5, 2),
        FOREIGN KEY (flash_sale_id) REFERENCES flash_sales(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );

    -- Banners Table
    CREATE TABLE IF NOT EXISTS banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        link VARCHAR(255),
        title VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";

    $pdo->exec($sql);
    echo "All tables created successfully.<br>";

    // Create Default Admin User
    $adminName = "Admin";
    $adminEmail = "admin@techhat.com";
    $adminPass = password_hash("123456", PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    
    if(!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$adminName, $adminEmail, $adminPass]);
        echo "Default Admin created: admin@techhat.com / 123456<br>";
    } else {
        echo "Admin already exists.<br>";
    }

} catch(PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>