CREATE DATABASE IF NOT EXISTS findit_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE findit_db;

-- USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    user_id INT(11) NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Student','Faculty','Staff','Admin') NOT NULL DEFAULT 'Student',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY email (email)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- ITEMS TABLE
CREATE TABLE IF NOT EXISTS items (
    item_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(100) NOT NULL,
    item_type ENUM('Lost','Found') NOT NULL,
    location VARCHAR(255) NOT NULL,
    date_lost_found DATE NOT NULL,
    photo_url VARCHAR(255) DEFAULT NULL,
    status ENUM('Pending','Approved','Rejected','Resolved') NOT NULL DEFAULT 'Pending',
    date_posted TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    KEY idx_items_user_id (user_id),
    KEY idx_items_status (status),
    KEY idx_items_type (item_type),
    CONSTRAINT fk_items_user
        FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- CLAIMS TABLE
CREATE TABLE IF NOT EXISTS claims (
    claim_id INT(11) NOT NULL AUTO_INCREMENT,
    item_id INT(11) NOT NULL,
    lost_item_id INT(11) DEFAULT NULL,
    user_id INT(11) NOT NULL,
    claim_message TEXT NOT NULL,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (claim_id),
    KEY idx_claims_item_id (item_id),
    KEY idx_claims_lost_item_id (lost_item_id),
    KEY idx_claims_user_id (user_id),
    KEY idx_claims_status (status),
    CONSTRAINT fk_claims_item
        FOREIGN KEY (item_id)
        REFERENCES items (item_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_claims_lost_item
        FOREIGN KEY (lost_item_id)
        REFERENCES items (item_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_claims_user
        FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;
