-- ==============================================================================
-- ПРАКТИКАЛЫҚ ЖҰМЫС №7: SQL СҰРАНЫСТАР ТІЛІ ЖӘНЕ ОНЫ ҮЛКЕН ДЕРЕКТЕРГЕ ҚОЛДАНУ
-- «Hospital Management System» емхана дерекқорына бейімделген нұсқа
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS hospital_db;
USE hospital_db;

DROP TABLE IF EXISTS hospital_appointments;
DROP TABLE IF EXISTS hospital_patients;

-- 1. Пациенттер кестесі (hospital_patients)
CREATE TABLE hospital_patients (
    patient_id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    registration_date DATE NOT NULL
);

-- 2. Медициналық қабылдаулар мен қызметтер транзакциясы (hospital_appointments)
CREATE TABLE hospital_appointments (
    appointment_id INT PRIMARY KEY,
    patient_id INT NOT NULL,
    timestamp DATETIME NOT NULL,
    category VARCHAR(50) NOT NULL, -- Медициналық бөлім/қызмет: Cardiology, Neurology, Surgery, т.б.
    amount DECIMAL(12, 2) NOT NULL, -- Қызмет/емдеу құны (теңге)
    payment_method VARCHAR(50) NOT NULL, -- Төлем түрі: Card, Kaspi QR, Cash, МӘМС/Insurance
    status VARCHAR(50) NOT NULL, -- Мәртебесі: Completed, Scheduled, Cancelled
    FOREIGN KEY (patient_id) REFERENCES hospital_patients(patient_id)
);

-- 3. Пациенттердің нақты деректері (Almaty, Astana, Shymkent, Karaganda)
INSERT INTO hospital_patients (patient_id, name, city, age, registration_date) VALUES
(101, 'Айдос Қасымов', 'Almaty', 28, '2025-01-15'),
(102, 'Динара Серікова', 'Astana', 34, '2025-02-10'),
(103, 'Бауыржан Ерланұлы', 'Almaty', 22, '2025-03-01'),
(104, 'Мәдина Омарова', 'Shymkent', 45, '2025-04-12'),
(105, 'Асқар Төлегенов', 'Almaty', 31, '2025-05-20'),
(106, 'Аружан Болатбек', 'Astana', 26, '2025-06-18'),
(107, 'Ержан Сағындықов', 'Karaganda', 39, '2025-07-05'),
(108, 'Гүлнәр Смағұлова', 'Shymkent', 29, '2025-08-22'),
(110, 'Қайрат Мұсаев', 'Almaty', 40, '2025-09-14'),
(121, 'Әлихан Нұрлан', 'Almaty', 35, '2025-10-01');

-- 4. Емханалық қабылдаулар мен қызметтердің транзакциялық деректері
INSERT INTO hospital_appointments (appointment_id, patient_id, timestamp, category, amount, payment_method, status) VALUES
(1, 101, '2026-01-10 10:15:00', 'Cardiology', 350000.00, 'Card', 'Completed'),
(2, 102, '2026-01-10 10:20:00', 'Pediatrics', 12500.00, 'Kaspi QR', 'Completed'),
(3, 101, '2026-01-11 14:05:00', 'Diagnostics', 18000.00, 'Card', 'Completed'),
(4, 103, '2026-01-12 09:30:00', 'Neurology', 45000.00, 'Cash', 'Completed'),
(5, 104, '2026-01-12 11:15:00', 'Cardiology', 280000.00, 'Card', 'Cancelled'),
(6, 105, '2026-01-13 16:40:00', 'Surgery', 550000.00, 'Card', 'Completed'),
(7, 105, '2026-01-14 12:10:00', 'Cardiology', 400000.00, 'Card', 'Completed'),
(8, 121, '2026-01-15 15:25:00', 'Surgery', 480000.00, 'Card', 'Completed'),
(9, 121, '2026-01-16 18:00:00', 'Cardiology', 390000.00, 'Card', 'Completed'),
(10, 110, '2026-01-17 11:50:00', 'Cardiology', 420000.00, 'Card', 'Completed'),
(11, 110, '2026-01-18 14:20:00', 'Neurology', 370000.00, 'Kaspi QR', 'Completed'),
(12, 106, '2026-01-19 10:05:00', 'Surgery', 620000.00, 'Card', 'Completed'),
(13, 102, '2026-01-20 17:30:00', 'Cardiology', 310000.00, 'Card', 'Completed'),
(14, 104, '2026-01-21 13:45:00', 'Pediatrics', 25000.00, 'Kaspi QR', 'Completed'),
(15, 107, '2026-01-22 09:15:00', 'Diagnostics', 35000.00, 'Card', 'Completed'),
(16, 108, '2026-01-23 16:00:00', 'Neurology', 85000.00, 'Cash', 'Completed');
