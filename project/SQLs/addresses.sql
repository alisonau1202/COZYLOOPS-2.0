-- Create the database
CREATE DATABASE IF NOT EXISTS project;
USE project;

-- Drop and recreate addresses table with new fields
DROP TABLE IF EXISTS addresses;

CREATE TABLE addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    unit_number VARCHAR(50), -- Optional unit number
    street VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL, -- New required field
    postcode VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Malaysia', -- New required field with default
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Add foreign key constraint if users table exists
    -- FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Add indexes for better performance
    INDEX idx_user_id (user_id),
    INDEX idx_country (country),
    INDEX idx_state (state)
);