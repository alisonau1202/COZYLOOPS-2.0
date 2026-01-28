-- Create the database
CREATE DATABASE IF NOT EXISTS if0_39196567_COS30043_Project;
USE if0_39196567_COS30043_Project;

-- Drop and recreate users table
DROP TABLE IF EXISTS purchases;
DROP TABLE IF EXISTS purchase_items;


-- Create purchases table to store order information
CREATE TABLE IF NOT EXISTS purchases (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	order_number VARCHAR(50) UNIQUE NOT NULL,
	total_amount DECIMAL(10,2) NOT NULL,
	shipping_cost DECIMAL(10,2) DEFAULT 0.00,
	order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
	order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	INDEX idx_user_id (user_id),
	INDEX idx_order_number (order_number)
);

-- Create purchase_items table to store individual items in each order
CREATE TABLE IF NOT EXISTS purchase_items (
	id INT AUTO_INCREMENT PRIMARY KEY,
	purchase_id INT NOT NULL,
	product_id INT NOT NULL,
	name VARCHAR(255) NOT NULL,
	price DECIMAL(10,2) NOT NULL,
	quantity INT NOT NULL,
	image VARCHAR(255),
	FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
	INDEX idx_purchase_id (purchase_id)
);
