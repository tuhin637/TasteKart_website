CREATE DATABASE IF NOT EXISTS tastekart;
USE tastekart;

-- Table: users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('customer', 'restaurant', 'admin') NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE,
    password VARCHAR(255) NOT NULL,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: menu_items
CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    category ENUM('pizza', 'burgers', 'asian', 'desserts', 'beverages'),
    price DECIMAL(10, 2) NOT NULL,
    prep_time INT,
    availability BOOLEAN DEFAULT TRUE,
    image VARCHAR(255),
    FOREIGN KEY (restaurant_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table: orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    restaurant_id INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'received', 'preparing', 'delivered', 'cancelled') DEFAULT 'pending',
    delivery_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estimated_delivery TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_restaurant_id (restaurant_id)
);

-- Trigger to set estimated_delivery
DELIMITER //
CREATE TRIGGER set_estimated_delivery
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    SET NEW.estimated_delivery = DATE_ADD(NEW.created_at, INTERVAL 1 HOUR);
END //
DELIMITER ;

-- Table: order_items
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
);

-- Table: reviews
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    restaurant_id INT NOT NULL,
    rating INT,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table: coupons
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    discount DECIMAL(5, 2) NOT NULL,
    expiry_date DATE NOT NULL,
    min_order_value DECIMAL(10, 2)
);

-- Table: payment_transactions
CREATE TABLE payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method ENUM('bkash', 'nagad', 'rocket') NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);



