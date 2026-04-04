-- ============================================================
-- Collections Manager - Install Script
-- Run this to (re)create the database from scratch
-- Encoding: UTF-8
-- ============================================================
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Drop tables in reverse order (respect foreign keys)
DROP TABLE IF EXISTS item_tags;
DROP TABLE IF EXISTS item_values;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS collection_fields;
DROP TABLE IF EXISTS collections;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS users;

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Collections
CREATE TABLE collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    description TEXT,
    has_images TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Fields definition per collection
CREATE TABLE collection_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collection_id INT NOT NULL,
    field_name VARCHAR(64) NOT NULL,
    field_type ENUM('text','number','boolean') NOT NULL DEFAULT 'text',
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Items
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collection_id INT NOT NULL,
    created_by INT NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Dynamic field values
CREATE TABLE item_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    field_id INT NOT NULL,
    value TEXT,
    UNIQUE KEY uq_item_field (item_id, field_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES collection_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tags
CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL UNIQUE,
    image_path VARCHAR(255) DEFAULT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Item <-> Tag pivot
CREATE TABLE item_tags (
    item_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (item_id, tag_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Initial admin user  (login: admin / admin)
-- ============================================================
INSERT INTO users (username, password_hash, is_admin)
VALUES ('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);
-- password_hash above = bcrypt of "admin"  (cost 12)
