CREATE DATABASE IF NOT EXISTS salon;
USE salon;

-- Existing tables
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL,
    token VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    service VARCHAR(100) NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    module VARCHAR(50) NOT NULL,
    record_id INT,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- New tables for the two final modules
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    duration INT NOT NULL COMMENT 'minutes',
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20),
    service_id INT NOT NULL,
    preferred_date DATE NOT NULL,
    preferred_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- Users (hashed password for "password")
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('staff1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff');

-- Clients (50 dummy records)
INSERT INTO clients (name, email, phone) VALUES
('John Doe', 'john@example.com', '555-0101'),
('Jane Smith', 'jane@example.com', '555-0102'),
('Alice Brown', 'alice@example.com', '555-0103'),
('Bob White', 'bob@example.com', '555-0104'),
('Charlie Black', 'charlie@example.com', '555-0105'),
('Diana Green', 'diana@example.com', '555-0106'),
('Eve Blue', 'eve@example.com', '555-0107'),
('Frank Red', 'frank@example.com', '555-0108'),
('Grace Yellow', 'grace@example.com', '555-0109'),
('Henry Purple', 'henry@example.com', '555-0110'),
('Ivy Orange', 'ivy@example.com', '555-0111'),
('Jack Cyan', 'jack@example.com', '555-0112'),
('Karen Magenta', 'karen@example.com', '555-0113'),
('Leo Gray', 'leo@example.com', '555-0114'),
('Mia Silver', 'mia@example.com', '555-0115'),
('Nick Gold', 'nick@example.com', '555-0116'),
('Olive Bronze', 'olive@example.com', '555-0117'),
('Paul Platinum', 'paul@example.com', '555-0118'),
('Quinn Copper', 'quinn@example.com', '555-0119'),
('Rose Zinc', 'rose@example.com', '555-0120'),
('Sam Nickel', 'sam@example.com', '555-0121'),
('Tina Lead', 'tina@example.com', '555-0122'),
('Umar Tin', 'umar@example.com', '555-0123'),
('Vera Iron', 'vera@example.com', '555-0124'),
('Will Cobalt', 'will@example.com', '555-0125'),
('Xena Chromium', 'xena@example.com', '555-0126'),
('Yuri Manganese', 'yuri@example.com', '555-0127'),
('Zara Aluminum', 'zara@example.com', '555-0128'),
('Adam Titanium', 'adam@example.com', '555-0129'),
('Beth Vanadium', 'beth@example.com', '555-0130'),
('Carl Tungsten', 'carl@example.com', '555-0131'),
('Dana Zirconium', 'dana@example.com', '555-0132'),
('Eli Molybdenum', 'eli@example.com', '555-0133'),
('Fay Rhodium', 'fay@example.com', '555-0134'),
('Gus Palladium', 'gus@example.com', '555-0135'),
('Hope Iridium', 'hope@example.com', '555-0136'),
('Ivan Osmium', 'ivan@example.com', '555-0137'),
('Jade Rhenium', 'jade@example.com', '555-0138'),
('Kurt Ruthenium', 'kurt@example.com', '555-0139'),
('Lara Technetium', 'lara@example.com', '555-0140'),
('Mick Niobium', 'mick@example.com', '555-0141'),
('Nina Hafnium', 'nina@example.com', '555-0142'),
('Oscar Tantalum', 'oscar@example.com', '555-0143'),
('Paula Thorium', 'paula@example.com', '555-0144'),
('Quentin Uranium', 'quentin@example.com', '555-0145'),
('Rita Plutonium', 'rita@example.com', '555-0146'),
('Steve Americium', 'steve@example.com', '555-0147'),
('Tracy Curium', 'tracy@example.com', '555-0148'),
('Ulysses Berkelium', 'ulysses@example.com', '555-0149'),
('Valerie Californium', 'valerie@example.com', '555-0150');

-- Appointments (60 dummy records)
INSERT INTO appointments (client_id, service, appointment_date, appointment_time, status) VALUES
(1, 'Haircut', '2025-03-10', '09:00:00', 'scheduled'),
(2, 'Manicure', '2025-03-10', '10:30:00', 'scheduled'),
(3, 'Pedicure', '2025-03-10', '11:00:00', 'scheduled'),
(4, 'Haircut', '2025-03-11', '14:00:00', 'scheduled'),
(5, 'Coloring', '2025-03-11', '15:30:00', 'scheduled'),
(6, 'Facial', '2025-03-12', '09:30:00', 'scheduled'),
(7, 'Massage', '2025-03-12', '11:00:00', 'scheduled'),
(8, 'Haircut', '2025-03-12', '13:00:00', 'scheduled'),
(9, 'Manicure', '2025-03-13', '10:00:00', 'scheduled'),
(10, 'Pedicure', '2025-03-13', '12:00:00', 'scheduled'),
(11, 'Haircut', '2025-03-14', '09:00:00', 'scheduled'),
(12, 'Coloring', '2025-03-14', '10:30:00', 'scheduled'),
(13, 'Facial', '2025-03-15', '14:00:00', 'scheduled'),
(14, 'Massage', '2025-03-15', '15:30:00', 'scheduled'),
(15, 'Haircut', '2025-03-16', '09:00:00', 'scheduled'),
(16, 'Manicure', '2025-03-16', '10:30:00', 'scheduled'),
(17, 'Pedicure', '2025-03-17', '11:00:00', 'scheduled'),
(18, 'Haircut', '2025-03-17', '14:00:00', 'scheduled'),
(19, 'Coloring', '2025-03-18', '09:30:00', 'scheduled'),
(20, 'Facial', '2025-03-18', '11:00:00', 'scheduled'),
(21, 'Massage', '2025-03-19', '13:00:00', 'scheduled'),
(22, 'Haircut', '2025-03-19', '15:00:00', 'scheduled'),
(23, 'Manicure', '2025-03-20', '09:00:00', 'scheduled'),
(24, 'Pedicure', '2025-03-20', '10:30:00', 'scheduled'),
(25, 'Haircut', '2025-03-21', '11:00:00', 'scheduled'),
(26, 'Coloring', '2025-03-21', '14:00:00', 'scheduled'),
(27, 'Facial', '2025-03-22', '09:30:00', 'scheduled'),
(28, 'Massage', '2025-03-22', '11:00:00', 'scheduled'),
(29, 'Haircut', '2025-03-23', '13:00:00', 'scheduled'),
(30, 'Manicure', '2025-03-23', '15:30:00', 'scheduled'),
(31, 'Pedicure', '2025-03-24', '09:00:00', 'scheduled'),
(32, 'Haircut', '2025-03-24', '10:30:00', 'scheduled'),
(33, 'Coloring', '2025-03-25', '11:00:00', 'scheduled'),
(34, 'Facial', '2025-03-25', '14:00:00', 'scheduled'),
(35, 'Massage', '2025-03-26', '09:00:00', 'scheduled'),
(36, 'Haircut', '2025-03-26', '10:30:00', 'scheduled'),
(37, 'Manicure', '2025-03-27', '11:00:00', 'scheduled'),
(38, 'Pedicure', '2025-03-27', '14:00:00', 'scheduled'),
(39, 'Haircut', '2025-03-28', '09:30:00', 'scheduled'),
(40, 'Coloring', '2025-03-28', '11:00:00', 'scheduled'),
(41, 'Facial', '2025-03-29', '13:00:00', 'scheduled'),
(42, 'Massage', '2025-03-29', '15:00:00', 'scheduled'),
(43, 'Haircut', '2025-03-30', '09:00:00', 'scheduled'),
(44, 'Manicure', '2025-03-30', '10:30:00', 'scheduled'),
(45, 'Pedicure', '2025-04-01', '11:00:00', 'scheduled'),
(46, 'Haircut', '2025-04-01', '14:00:00', 'scheduled'),
(47, 'Coloring', '2025-04-02', '09:30:00', 'scheduled'),
(48, 'Facial', '2025-04-02', '11:00:00', 'scheduled'),
(49, 'Massage', '2025-04-03', '13:00:00', 'scheduled'),
(50, 'Haircut', '2025-04-03', '15:30:00', 'scheduled'),
(1, 'Haircut', '2025-04-04', '09:00:00', 'completed'),
(2, 'Manicure', '2025-04-04', '10:30:00', 'completed'),
(3, 'Pedicure', '2025-04-05', '11:00:00', 'cancelled'),
(4, 'Haircut', '2025-04-05', '14:00:00', 'scheduled'),
(5, 'Coloring', '2025-04-06', '09:00:00', 'scheduled'),
(6, 'Facial', '2025-04-06', '10:30:00', 'scheduled'),
(7, 'Massage', '2025-04-07', '11:00:00', 'scheduled'),
(8, 'Haircut', '2025-04-07', '14:00:00', 'scheduled'),
(9, 'Manicure', '2025-04-08', '09:00:00', 'scheduled'),
(10, 'Pedicure', '2025-04-08', '10:30:00', 'scheduled');

-- Services (6 dummy records, can be extended)
INSERT INTO services (name, duration, price) VALUES
('Haircut', 30, 25.00),
('Manicure', 45, 35.00),
('Pedicure', 60, 45.00),
('Facial', 50, 55.00),
('Massage', 60, 70.00),
('Hair Coloring', 90, 80.00);

-- Public Bookings (20 dummy records, creating a mix of pending, confirmed, cancelled)
INSERT INTO public_bookings (customer_name, customer_email, customer_phone, service_id, preferred_date, preferred_time, status) VALUES
('Anna Garcia', 'anna@example.com', '555-1001', 1, CURDATE() + INTERVAL 1 DAY, '09:00:00', 'pending'),
('Brian Lee', 'brian@example.com', '555-1002', 2, CURDATE() + INTERVAL 2 DAY, '10:30:00', 'confirmed'),
('Carla Smith', 'carla@example.com', '555-1003', 3, CURDATE() + INTERVAL 3 DAY, '11:00:00', 'pending'),
('David Kim', 'david@example.com', '555-1004', 4, CURDATE() + INTERVAL 1 DAY, '14:00:00', 'cancelled'),
('Emma Jones', 'emma@example.com', '555-1005', 5, CURDATE() + INTERVAL 2 DAY, '15:30:00', 'pending'),
('Frank White', 'frank@example.com', '555-1006', 6, CURDATE() + INTERVAL 4 DAY, '09:30:00', 'confirmed'),
('Grace Lee', 'grace@example.com', '555-1007', 1, CURDATE() + INTERVAL 5 DAY, '11:00:00', 'pending'),
('Henry Brown', 'henry@example.com', '555-1008', 2, CURDATE() + INTERVAL 1 DAY, '13:00:00', 'pending'),
('Isabel Green', 'isabel@example.com', '555-1009', 3, CURDATE() + INTERVAL 3 DAY, '10:00:00', 'confirmed'),
('Jack Black', 'jack@example.com', '555-1010', 4, CURDATE() + INTERVAL 2 DAY, '12:00:00', 'cancelled'),
('Karen Wilson', 'karen@example.com', '555-1011', 5, CURDATE() + INTERVAL 6 DAY, '09:00:00', 'pending'),
('Liam Miller', 'liam@example.com', '555-1012', 6, CURDATE() + INTERVAL 1 DAY, '14:30:00', 'pending'),
('Mia Davis', 'mia@example.com', '555-1013', 1, CURDATE() + INTERVAL 4 DAY, '10:00:00', 'confirmed'),
('Noah Martinez', 'noah@example.com', '555-1014', 2, CURDATE() + INTERVAL 5 DAY, '11:30:00', 'pending'),
('Olivia Rodriguez', 'olivia@example.com', '555-1015', 3, CURDATE() + INTERVAL 2 DAY, '13:30:00', 'confirmed'),
('Paul Hernandez', 'paul@example.com', '555-1016', 4, CURDATE() + INTERVAL 3 DAY, '15:00:00', 'pending'),
('Quinn Lopez', 'quinn@example.com', '555-1017', 5, CURDATE() + INTERVAL 1 DAY, '09:30:00', 'cancelled'),
('Rose Gonzalez', 'rose@example.com', '555-1018', 6, CURDATE() + INTERVAL 7 DAY, '10:30:00', 'pending'),
('Sam Perez', 'sam@example.com', '555-1019', 1, CURDATE() + INTERVAL 2 DAY, '11:00:00', 'confirmed'),
('Tina Thompson', 'tina@example.com', '555-1020', 2, CURDATE() + INTERVAL 3 DAY, '12:15:00', 'pending');

-- Audit logs (sample)
INSERT INTO audit_logs (user_id, action, module, record_id, details) VALUES
(1, 'CREATE', 'client', 1, 'Created client John Doe'),
(1, 'CREATE', 'appointment', 1, 'Created appointment for client 1'),
(1, 'CREATE', 'service', 1, 'Created service Haircut'),
(1, 'CREATE', 'public_booking', 1, 'Created booking for Anna Garcia');