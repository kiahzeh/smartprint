CREATE DATABASE IF NOT EXISTS smartprint_workflow;
USE smartprint_workflow;

DROP TABLE IF EXISTS job_updates;
DROP TABLE IF EXISTS job_messages;
DROP TABLE IF EXISTS job_payments;
DROP TABLE IF EXISTS print_jobs;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS service_types;
DROP TABLE IF EXISTS job_statuses;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    label VARCHAR(60) NOT NULL
);

CREATE TABLE job_statuses (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    label VARCHAR(60) NOT NULL
);

CREATE TABLE service_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_users_role_id (role_id)
);

CREATE TABLE print_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(20) NOT NULL UNIQUE,
    client_id INT NOT NULL,
    assigned_admin_id INT NULL,
    assigned_artist_id INT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    service_type_id INT UNSIGNED NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity INT NOT NULL,
    due_date DATE NOT NULL,
    status_id TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_jobs_client FOREIGN KEY (client_id) REFERENCES users(id),
    CONSTRAINT fk_jobs_admin FOREIGN KEY (assigned_admin_id) REFERENCES users(id),
    CONSTRAINT fk_jobs_artist FOREIGN KEY (assigned_artist_id) REFERENCES users(id),
    CONSTRAINT fk_jobs_service_type FOREIGN KEY (service_type_id) REFERENCES service_types(id),
    CONSTRAINT fk_jobs_status FOREIGN KEY (status_id) REFERENCES job_statuses(id),
    INDEX idx_jobs_client_id (client_id),
    INDEX idx_jobs_admin_id (assigned_admin_id),
    INDEX idx_jobs_artist_id (assigned_artist_id),
    INDEX idx_jobs_service_type_id (service_type_id),
    INDEX idx_jobs_status_id (status_id)
);

CREATE TABLE job_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    paid_by INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_job FOREIGN KEY (job_id) REFERENCES print_jobs(id),
    CONSTRAINT fk_payments_user FOREIGN KEY (paid_by) REFERENCES users(id),
    INDEX idx_payments_job_id (job_id),
    INDEX idx_payments_user_id (paid_by)
);

CREATE TABLE job_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    sender_id INT NOT NULL,
    message_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_job FOREIGN KEY (job_id) REFERENCES print_jobs(id),
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id),
    INDEX idx_messages_job_id (job_id),
    INDEX idx_messages_sender_id (sender_id),
    INDEX idx_messages_created_at (created_at)
);

CREATE TABLE job_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    updated_by INT NOT NULL,
    status_id TINYINT UNSIGNED NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_updates_job FOREIGN KEY (job_id) REFERENCES print_jobs(id),
    CONSTRAINT fk_updates_user FOREIGN KEY (updated_by) REFERENCES users(id),
    CONSTRAINT fk_updates_status FOREIGN KEY (status_id) REFERENCES job_statuses(id),
    INDEX idx_updates_job_id (job_id),
    INDEX idx_updates_user_id (updated_by),
    INDEX idx_updates_status_id (status_id)
);

INSERT INTO roles (id, code, label) VALUES
(1, 'super_admin', 'Super Admin'),
(2, 'admin', 'Admin'),
(3, 'artist', 'Graphic Artist'),
(4, 'client', 'Client');

INSERT INTO job_statuses (id, code, label) VALUES
(1, 'pending', 'Pending'),
(2, 'for_layout', 'For Layout'),
(3, 'for_approval', 'For Approval'),
(4, 'for_print', 'For Print'),
(5, 'completed', 'Completed'),
(6, 'cancelled', 'Cancelled');

INSERT INTO service_types (id, name) VALUES
(1, 'Tarpaulin'),
(2, 'Business Card'),
(3, 'Sticker'),
(4, 'ID'),
(5, 'Shirt');

INSERT INTO users (full_name, email, password_hash, role_id) VALUES
('Super Admin', 'super@smartprint.com', '$2y$12$MkaSW3a2WXPYhZb5qQqb1uNrR3XXz2u5G/0PWW3eLBBpgX5O0VwUC', 1),
('Main Admin', 'admin@smartprint.com', '$2y$12$EiqIp2TdPDn.KBEhtrQ1.O5P4ZGKcDthbYqrv1BWe4CM1QQ1j6SV6', 2),
('Lead Artist', 'artist@smartprint.com', '$2y$12$Ag7P.V4H0rUSJLm/MUzUUu46zOU7iEkscxByd4oCivbnAKeKyqW02', 3),
('Sample Client', 'client@smartprint.com', '$2y$12$GNnrenPexx9iq4Iq1dDHHOXPOGEd7E1H4SqTFGa.KNf2isLq85eUi', 4);

INSERT INTO print_jobs (reference_number, client_id, assigned_admin_id, assigned_artist_id, title, description, service_type_id, total_amount, quantity, due_date, status_id) VALUES
('REF-2026-0001', 4, 2, 3, 'Grand Opening Tarpaulin', '6x4 feet tarpaulin for shop opening event.', 1, 3000.00, 2, '2026-05-20', 2),
('REF-2026-0002', 4, 2, 3, 'Business Card Batch', 'Matte finish business cards.', 2, 2500.00, 500, '2026-05-15', 3);

INSERT INTO job_payments (job_id, paid_by, amount, note) VALUES
(1, 4, 1500.00, 'Initial 50% down payment'),
(2, 4, 500.00, 'Partial down payment');

INSERT INTO job_messages (job_id, sender_id, message_text) VALUES
(1, 4, 'Hi artist, please follow the color palette from our brand guide.'),
(1, 3, 'Received. I will prepare the first layout draft today.');
