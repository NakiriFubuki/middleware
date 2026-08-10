-- Parcel Delivery Management System
-- Database Schema with sample data

CREATE DATABASE IF NOT EXISTS parcel_delivery_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE parcel_delivery_db;

-- Users table
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'rider') NOT NULL DEFAULT 'rider',
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    remember_token VARCHAR(64) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB;

-- Riders table (extends users)
CREATE TABLE riders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    rider_code VARCHAR(20) NOT NULL UNIQUE,
    vehicle_type VARCHAR(50) DEFAULT 'Motorcycle',
    license_number VARCHAR(50) DEFAULT NULL COMMENT 'Vehicle license plate',
    is_online TINYINT(1) NOT NULL DEFAULT 0,
    last_online_at DATETIME DEFAULT NULL,
    last_latitude DECIMAL(10, 8) DEFAULT NULL,
    last_longitude DECIMAL(11, 8) DEFAULT NULL,
    last_location_at DATETIME DEFAULT NULL,
    total_deliveries INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_riders_online (is_online),
    INDEX idx_riders_code (rider_code)
) ENGINE=InnoDB;

-- Parcels table
CREATE TABLE parcels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_number VARCHAR(20) NOT NULL UNIQUE,
    sender_name VARCHAR(100) NOT NULL,
    sender_phone VARCHAR(20) NOT NULL,
    receiver_name VARCHAR(100) NOT NULL,
    receiver_phone VARCHAR(20) NOT NULL,
    pickup_address TEXT NOT NULL,
    delivery_address TEXT NOT NULL,
    parcel_description TEXT DEFAULT NULL,
    parcel_weight DECIMAL(8, 2) DEFAULT 0.00,
    delivery_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    assigned_rider_id INT UNSIGNED DEFAULT NULL,
    status ENUM('pending', 'out_for_delivery', 'delivered', 'failed') NOT NULL DEFAULT 'pending',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    delivered_at DATETIME DEFAULT NULL,
    FOREIGN KEY (assigned_rider_id) REFERENCES riders(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_parcels_status (status),
    INDEX idx_parcels_tracking (tracking_number),
    INDEX idx_parcels_rider (assigned_rider_id),
    INDEX idx_parcels_created (created_at)
) ENGINE=InnoDB;

-- Rider locations table
CREATE TABLE rider_locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    parcel_id INT UNSIGNED DEFAULT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy DECIMAL(8, 2) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE SET NULL,
    INDEX idx_locations_rider (rider_id),
    INDEX idx_locations_parcel (parcel_id),
    INDEX idx_locations_created (created_at),
    INDEX idx_locations_rider_created (rider_id, created_at),
    INDEX idx_locations_rider_parcel (rider_id, parcel_id, created_at)
) ENGINE=InnoDB;

-- Parcel status history
CREATE TABLE parcel_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcel_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'out_for_delivery', 'delivered', 'failed') NOT NULL,
    remarks TEXT DEFAULT NULL,
    rider_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL,
    INDEX idx_history_parcel (parcel_id),
    INDEX idx_history_status (status),
    INDEX idx_history_created (created_at)
) ENGINE=InnoDB;

-- Delivery photos
CREATE TABLE delivery_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcel_id INT UNSIGNED NOT NULL,
    rider_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT UNSIGNED DEFAULT 0,
    mime_type VARCHAR(50) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    INDEX idx_photos_parcel (parcel_id)
) ENGINE=InnoDB;

-- Activity logs
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_logs_user (user_id),
    INDEX idx_logs_action (action),
    INDEX idx_logs_created (created_at)
) ENGINE=InnoDB;

-- Sample data
-- Admin password: admin123 | user password: user123 | Other riders: password123
INSERT INTO users (username, email, password, role, full_name, phone, is_active) VALUES
('admin', 'admin@parceldelivery.com', '$2y$10$keymNnzU7FoQYGNWBHmdwe1XeMHBWA6plmWkjwaK/b35alNoaa5ke', 'admin', 'System Administrator', '09171234567', 1),
('rider1', 'juan.delacruz@parceldelivery.com', '$2y$10$ni/hi100TUcEkZDZHWT3BezI.YTZRUzFjrhO.aEHl.nn.Ap2KxQDG', 'rider', 'Juan Dela Cruz', '09181234567', 1),
('rider2', 'maria.santos@parceldelivery.com', '$2y$10$ni/hi100TUcEkZDZHWT3BezI.YTZRUzFjrhO.aEHl.nn.Ap2KxQDG', 'rider', 'Maria Santos', '09191234567', 1),
('rider3', 'pedro.reyes@parceldelivery.com', '$2y$10$ni/hi100TUcEkZDZHWT3BezI.YTZRUzFjrhO.aEHl.nn.Ap2KxQDG', 'rider', 'Pedro Reyes', '09201234567', 1),
('user', 'user@parceldelivery.com', '$2y$10$.lGW7F1WVOXoHhh8rPDJr.zFNXu4kwc/Z1pjVkf1tvSggGOtW25.6', 'rider', 'Delivery Rider', '09170000001', 1);

INSERT INTO riders (user_id, rider_code, vehicle_type, license_number, is_online, total_deliveries) VALUES
(2, 'RDR-001', 'Motorcycle', 'N01-123456', 0, 45),
(3, 'RDR-002', 'Motorcycle', 'N02-234567', 0, 38),
(4, 'RDR-003', 'Bicycle', 'N03-345678', 0, 22),
(5, 'RDR-004', 'Motorcycle', NULL, 0, 0);

INSERT INTO parcels (tracking_number, sender_name, sender_phone, receiver_name, receiver_phone, pickup_address, delivery_address, parcel_description, parcel_weight, delivery_fee, assigned_rider_id, status, created_by) VALUES
('PD2026000001', 'Ana Garcia', '09301111111', 'Roberto Lim', '09302222222', '123 Rizal St, Manila', '456 Bonifacio Ave, Quezon City', 'Documents envelope', 0.50, 150.00, 1, 'delivered', 1),
('PD2026000002', 'Carlos Tan', '09303333333', 'Elena Wong', '09304444444', '789 Mabini St, Makati', '321 Luna St, Pasig', 'Small electronics package', 2.30, 200.00, 1, 'out_for_delivery', 1),
('PD2026000003', 'Lisa Fernandez', '09305555555', 'Miguel Torres', '09306666666', '555 EDSA, Mandaluyong', '888 Shaw Blvd, Mandaluyong', 'Clothing items', 1.80, 120.00, 2, 'pending', 1),
('PD2026000004', 'David Kim', '09307777777', 'Sarah Park', '09308888888', '100 Ayala Ave, Makati', '200 BGC High Street, Taguig', 'Gift box', 3.50, 250.00, NULL, 'pending', 1),
('PD2026000005', 'Grace Lee', '09309999999', 'Tom Wilson', '09401111111', '50 Taft Ave, Manila', '75 Roxas Blvd, Manila', 'Books bundle', 4.20, 180.00, 3, 'failed', 1),
('PD2026000006', 'Henry Cruz', '09402222222', 'Irene Santos', '09403333333', '300 Commonwealth Ave, QC', '400 Katipunan Ave, QC', 'Food package (non-perishable)', 1.50, 130.00, 2, 'delivered', 1);

INSERT INTO parcel_status_history (parcel_id, status, remarks, rider_id) VALUES
(1, 'pending', 'Parcel created', NULL),
(1, 'out_for_delivery', 'Picked up from sender', 1),
(1, 'delivered', 'Delivered to receiver', 1),
(2, 'pending', 'Parcel created', NULL),
(2, 'out_for_delivery', 'On the way to delivery address', 1),
(3, 'pending', 'Parcel created and assigned', 2),
(4, 'pending', 'Parcel created, awaiting assignment', NULL),
(5, 'pending', 'Parcel created', NULL),
(5, 'out_for_delivery', 'Attempting delivery', 3),
(5, 'failed', 'Receiver not available', 3),
(6, 'pending', 'Parcel created', NULL),
(6, 'out_for_delivery', 'Picked up', 2),
(6, 'delivered', 'Successfully delivered', 2);

INSERT INTO rider_locations (rider_id, parcel_id, latitude, longitude, accuracy, created_at) VALUES
(1, 2, 14.59951200, 120.98422200, 15.50, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, 2, 14.60012300, 120.98533300, 12.30, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 3, 14.55472900, 121.02444500, 18.00, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(3, 5, 14.67620800, 121.04386100, 10.00, DATE_SUB(NOW(), INTERVAL 30 MINUTE));

INSERT INTO delivery_photos (parcel_id, rider_id, file_path, file_name, file_size, mime_type) VALUES
(1, 1, 'uploads/delivery_proofs/sample_proof_1.jpg', 'sample_proof_1.jpg', 125000, 'image/jpeg'),
(6, 2, 'uploads/delivery_proofs/sample_proof_2.jpg', 'sample_proof_2.jpg', 98000, 'image/jpeg');

INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
(1, 'login', 'Admin logged in', '127.0.0.1'),
(2, 'login', 'Rider logged in', '127.0.0.1'),
(1, 'parcel_create', 'Created parcel PD2026000001', '127.0.0.1'),
(1, 'parcel_assign', 'Assigned parcel PD2026000002 to rider RDR-001', '127.0.0.1');
