DROP DATABASE IF EXISTS hospital_db;
CREATE DATABASE hospital_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hospital_db;
SET NAMES utf8mb4;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Doctor', 'Receptionist', 'Patient') NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    UNIQUE KEY unique_user_role (id, role),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE specializations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    user_role ENUM('Admin', 'Doctor', 'Receptionist', 'Patient') GENERATED ALWAYS AS ('Doctor') STORED,
    department_id INT,
    specialization_id INT,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_doctor_user_role FOREIGN KEY (user_id, user_role) REFERENCES users(id, role) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_doctor_specialization FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    dob DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other'),
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE patient_accounts (
    user_id INT PRIMARY KEY,
    patient_id INT NOT NULL UNIQUE,
    user_role ENUM('Admin','Doctor','Receptionist','Patient') GENERATED ALWAYS AS ('Patient') STORED,
    CONSTRAINT fk_patient_account_user FOREIGN KEY (user_id,user_role) REFERENCES users(id,role) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_patient_account_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT ON UPDATE RESTRICT
);

CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    reason TEXT,
    status ENUM('Scheduled', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    occupied_slot TINYINT GENERATED ALWAYS AS (CASE WHEN status = 'Cancelled' THEN NULL ELSE 1 END) STORED,
    UNIQUE KEY unique_doctor_slot (doctor_id, appointment_date, appointment_time, occupied_slot),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE RESTRICT
);

CREATE TABLE medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNIQUE NOT NULL,
    diagnosis TEXT NOT NULL,
    treatment TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medical_record_id INT NOT NULL,
    medicine_name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    frequency VARCHAR(50) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE CASCADE
);

-- Insert Sample Data (All passwords are 'password123')
INSERT INTO users (username, password_hash, role, first_name, last_name, is_active) VALUES
('admin', '$2y$10$ipSdwD1xiD7SnZ1wuac1L.iJyasKfl4jlsxCw54h9uz01dvgO3DxO', 'Admin', 'Super', 'Admin', TRUE),
('reception', '$2y$10$ipSdwD1xiD7SnZ1wuac1L.iJyasKfl4jlsxCw54h9uz01dvgO3DxO', 'Receptionist', 'Front', 'Desk', TRUE),
('doctor_smith', '$2y$10$ipSdwD1xiD7SnZ1wuac1L.iJyasKfl4jlsxCw54h9uz01dvgO3DxO', 'Doctor', 'John', 'Smith', TRUE),
('doctor_jones', '$2y$10$ipSdwD1xiD7SnZ1wuac1L.iJyasKfl4jlsxCw54h9uz01dvgO3DxO', 'Doctor', 'Sarah', 'Jones', TRUE);

INSERT INTO departments (name, description) VALUES
('Cardiology', 'Heart and cardiovascular diseases'),
('Neurology', 'Brain and nervous system'),
('Pediatrics', 'Children and infants');

INSERT INTO specializations (name) VALUES ('Cardiologist'), ('Neurologist');

INSERT INTO doctors (user_id, department_id, specialization_id, phone) VALUES
(3, 1, 1, '123-456-7890'),
(4, 2, 2, '098-765-4321');

INSERT INTO patients (first_name, last_name, dob, gender, phone, email, address) VALUES
('Alice', 'Williams', '1985-05-15', 'Female', '555-0100', 'alice@example.com', '123 Main St'),
('Bob', 'Brown', '1990-08-22', 'Male', '555-0200', 'bob@example.com', '456 Elm St');

INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status) VALUES
(1, 1, '2023-11-01', '09:00:00', 'Routine checkup', 'Completed'),
(2, 2, '2023-11-02', '10:00:00', 'Headaches', 'Scheduled');

INSERT INTO medical_records (appointment_id, diagnosis, treatment, notes) VALUES
(1, 'Healthy heart', 'None', 'Patient is in good condition. Advised to exercise regularly.');

INSERT INTO prescriptions (medical_record_id, medicine_name, dosage, frequency, duration) VALUES
(1, 'Vitamin C', '500mg', 'Once a day', '30 days');
