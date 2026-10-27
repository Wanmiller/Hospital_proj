-- ==============================================================================
-- ЗЕРТХАНАЛЫҚ ЖҰМЫС №7: ДЕРЕКТЕР БАЗАСЫН 1NF, 2NF, 3NF БОЙЫНША ҚАЛЫПҚА КЕЛТІРУ
-- «Hospital Management System» кезеңдік нормализация кестелері
-- ==============================================================================
-- Бұл файлды phpMyAdmin-де орындасаңыз, базаңызда қалыпқа келтірудің барлық
-- кезеңдерін (0NF, 1NF, 2NF, 3NF) көрсететін нақты кестелер құрылады.
-- ==============================================================================

-- Базаны таңдау (Қате шықпас үшін):
CREATE DATABASE IF NOT EXISTS hospital_db;
USE hospital_db;

-- ------------------------------------------------------------------------------
-- 1-КЕЗЕҢ: 0NF (БАСТАПҚЫ ҚАЛЫПҚА КЕЛТІРІЛМЕГЕН АЛЫП КЕСТЕ)
-- Мәселелер: Medicine1, Medicine2 қайталанатын бағандары, редунданттылық
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS stage0_unnormalized;
CREATE TABLE stage0_unnormalized (
    app_id INT,
    patient_name VARCHAR(100),
    patient_phone VARCHAR(20),
    doctor_name VARCHAR(100),
    department_name VARCHAR(100),
    appointment_date DATE,
    medicine_1 VARCHAR(100),
    dosage_1 VARCHAR(50),
    medicine_2 VARCHAR(100),
    dosage_2 VARCHAR(50),
    diagnosis VARCHAR(255)
);

INSERT INTO stage0_unnormalized VALUES
(1, 'Alice Williams', '555-0100', 'Dr. John Smith', 'Cardiology', '2026-10-01', 'Vitamin C', '500mg', 'Aspirin', '100mg', 'Healthy heart'),
(2, 'Bob Brown', '555-0200', 'Dr. Sarah Jones', 'Neurology', '2026-10-02', 'Ibuprofen', '400mg', 'Magnesium B6', '1 tab', 'Migraine'),
(3, 'Alice Williams', '555-0100', 'Dr. John Smith', 'Cardiology', '2026-10-15', 'Omega 3', '1000mg', NULL, NULL, 'Routine check');

-- ------------------------------------------------------------------------------
-- 2-КЕЗЕҢ: 1NF (БІРІНШІ ҚАЛЫПТЫ ФОРМА)
-- Талап: Барлық мәндер атомарлық, Medicine1/Medicine2 жеке жолдарға бөлінді.
-- Бастапқы құрама кілт: (app_id, medicine_name)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS stage1_1nf;
CREATE TABLE stage1_1nf (
    app_id INT NOT NULL,
    medicine_name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    patient_name VARCHAR(100) NOT NULL,
    patient_phone VARCHAR(20) NOT NULL,
    doctor_name VARCHAR(100) NOT NULL,
    department_name VARCHAR(100) NOT NULL,
    appointment_date DATE NOT NULL,
    diagnosis VARCHAR(255) NOT NULL,
    PRIMARY KEY (app_id, medicine_name)
);

INSERT INTO stage1_1nf VALUES
(1, 'Vitamin C', '500mg', 'Alice Williams', '555-0100', 'Dr. John Smith', 'Cardiology', '2026-10-01', 'Healthy heart'),
(1, 'Aspirin', '100mg', 'Alice Williams', '555-0100', 'Dr. John Smith', 'Cardiology', '2026-10-01', 'Healthy heart'),
(2, 'Ibuprofen', '400mg', 'Bob Brown', '555-0200', 'Dr. Sarah Jones', 'Neurology', '2026-10-02', 'Migraine'),
(2, 'Magnesium B6', '1 tab', 'Bob Brown', '555-0200', 'Dr. Sarah Jones', 'Neurology', '2026-10-02', 'Migraine'),
(3, 'Omega 3', '1000mg', 'Alice Williams', '555-0100', 'Dr. John Smith', 'Cardiology', '2026-10-15', 'Routine check');

-- ------------------------------------------------------------------------------
-- 3-КЕЗЕҢ: 2NF (ЕКІНШІ ҚАЛЫПТЫ ФОРМА)
-- Талап: Жартылай функционалдық тәуелділіктер жойылды (4 дербес кестеге бөлінді)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS stage2_prescriptions;
DROP TABLE IF EXISTS stage2_appointments;
DROP TABLE IF EXISTS stage2_doctors;
DROP TABLE IF EXISTS stage2_patients;

-- 2.1 Пациенттер кестесі (тек пациентке тәуелді өрістер)
CREATE TABLE stage2_patients (
    patient_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL
);

-- 2.2 Дәрігерлер кестесі (тек дәрігерге тәуелді өрістер)
CREATE TABLE stage2_doctors (
    doctor_id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_name VARCHAR(100) NOT NULL,
    department_name VARCHAR(100) NOT NULL
);

-- 2.3 Қабылдаулар кестесі (орталық байланыстырушы)
CREATE TABLE stage2_appointments (
    app_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    diagnosis VARCHAR(255) NOT NULL,
    FOREIGN KEY (patient_id) REFERENCES stage2_patients(patient_id),
    FOREIGN KEY (doctor_id) REFERENCES stage2_doctors(doctor_id)
);

-- 2.4 Рецептер кестесі (дәрі-дәрмекке тәуелді өрістер)
CREATE TABLE stage2_prescriptions (
    presc_id INT PRIMARY KEY AUTO_INCREMENT,
    app_id INT NOT NULL,
    medicine_name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    FOREIGN KEY (app_id) REFERENCES stage2_appointments(app_id)
);

-- 2NF Деректерін толтыру
INSERT INTO stage2_patients (patient_name, phone) VALUES
('Alice Williams', '555-0100'),
('Bob Brown', '555-0200');

INSERT INTO stage2_doctors (doctor_name, department_name) VALUES
('Dr. John Smith', 'Cardiology'),
('Dr. Sarah Jones', 'Neurology');

INSERT INTO stage2_appointments (patient_id, doctor_id, appointment_date, diagnosis) VALUES
(1, 1, '2026-10-01', 'Healthy heart'),
(2, 2, '2026-10-02', 'Migraine'),
(1, 1, '2026-10-15', 'Routine check');

INSERT INTO stage2_prescriptions (app_id, medicine_name, dosage) VALUES
(1, 'Vitamin C', '500mg'),
(1, 'Aspirin', '100mg'),
(2, 'Ibuprofen', '400mg'),
(2, 'Magnesium B6', '1 tab'),
(3, 'Omega 3', '1000mg');

-- ------------------------------------------------------------------------------
-- 4-КЕЗЕҢ: 3NF (ҮШІНШІ ҚАЛЫПТЫ ФОРМА)
-- Талап: Транзитивті тәуелділіктер жойылды (Бөлімдер мен Медициналық карталар бөлінді)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS stage3_prescriptions;
DROP TABLE IF EXISTS stage3_medical_records;
DROP TABLE IF EXISTS stage3_appointments;
DROP TABLE IF EXISTS stage3_doctors;
DROP TABLE IF EXISTS stage3_departments;
DROP TABLE IF EXISTS stage3_patients;

-- 4.1 Бөлімдер (транзитивті тәуелділік жойылды)
CREATE TABLE stage3_departments (
    dept_id INT PRIMARY KEY AUTO_INCREMENT,
    dept_name VARCHAR(100) NOT NULL,
    description VARCHAR(255)
);

-- 4.2 Дәрігерлер (бөлімнің id-іне сілтеме жасайды)
CREATE TABLE stage3_doctors (
    doctor_id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_name VARCHAR(100) NOT NULL,
    dept_id INT NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    FOREIGN KEY (dept_id) REFERENCES stage3_departments(dept_id)
);

-- 4.3 Пациенттер
CREATE TABLE stage3_patients (
    patient_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address VARCHAR(255)
);

-- 4.4 Қабылдаулар (тек қабылдаудың уақыты мен статусы)
CREATE TABLE stage3_appointments (
    app_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'Completed',
    FOREIGN KEY (patient_id) REFERENCES stage3_patients(patient_id),
    FOREIGN KEY (doctor_id) REFERENCES stage3_doctors(doctor_id)
);

-- 4.5 Медициналық карталар (диагноз қабылдаудан бөлініп 1:1 байланысты)
CREATE TABLE stage3_medical_records (
    record_id INT PRIMARY KEY AUTO_INCREMENT,
    app_id INT UNIQUE NOT NULL,
    diagnosis VARCHAR(255) NOT NULL,
    treatment VARCHAR(255),
    FOREIGN KEY (app_id) REFERENCES stage3_appointments(app_id)
);

-- 4.6 Рецептер (тікелей медициналық картаға байланады)
CREATE TABLE stage3_prescriptions (
    presc_id INT PRIMARY KEY AUTO_INCREMENT,
    record_id INT NOT NULL,
    medicine_name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    FOREIGN KEY (record_id) REFERENCES stage3_medical_records(record_id)
);

-- 3NF Деректерін толтыру
INSERT INTO stage3_departments (dept_name, description) VALUES
('Cardiology', 'Heart and cardiovascular diseases'),
('Neurology', 'Brain and nervous system'),
('Pediatrics', 'Children and infants');

INSERT INTO stage3_doctors (doctor_name, dept_id, specialization) VALUES
('Dr. John Smith', 1, 'Cardiologist'),
('Dr. Sarah Jones', 2, 'Neurologist');

INSERT INTO stage3_patients (patient_name, phone, address) VALUES
('Alice Williams', '555-0100', '123 Main St'),
('Bob Brown', '555-0200', '456 Elm St');

INSERT INTO stage3_appointments (patient_id, doctor_id, appointment_date, status) VALUES
(1, 1, '2026-10-01', 'Completed'),
(2, 2, '2026-10-02', 'Completed'),
(1, 1, '2026-10-15', 'Completed');

INSERT INTO stage3_medical_records (app_id, diagnosis, treatment) VALUES
(1, 'Healthy heart', 'Regular physical exercises'),
(2, 'Migraine', 'Painkillers and rest'),
(3, 'Routine check', 'None');

INSERT INTO stage3_prescriptions (record_id, medicine_name, dosage) VALUES
(1, 'Vitamin C', '500mg'),
(1, 'Aspirin', '100mg'),
(2, 'Ibuprofen', '400mg'),
(2, 'Magnesium B6', '1 tab'),
(3, 'Omega 3', '1000mg');
