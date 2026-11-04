-- CIS 344 - Pharmacy Prescription Management System
-- Collabration: Edith Oblitas & Emmanuel Ubah


-- ================================================================
-- PART 1 - CREATE DATABASE AND TABLES
-- ================================================================

CREATE DATABASE pharmacy_management_refined;
USE pharmacy_management_refined;

-- 1. Patient information
CREATE TABLE patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    phone VARCHAR(20)
) ENGINE = InnoDB;

-- 2. Doctor information
-- "speciality" keeps the column name used in our first SQL file.
CREATE TABLE doctors (
    doctor_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    speciality VARCHAR(50),
    phone VARCHAR(20),
    email VARCHAR(100)
) ENGINE = InnoDB;

-- 3. Medication information and current stock
CREATE TABLE medication (
    medication_id INT AUTO_INCREMENT PRIMARY KEY,
    medication_name VARCHAR(100) NOT NULL,
    strength VARCHAR(50),
    form VARCHAR(50),
    stock_quantity INT NOT NULL DEFAULT 0,
    CHECK (stock_quantity >= 0)
) ENGINE = InnoDB;

-- 4. One prescription belongs to one patient and one doctor.
CREATE TABLE prescriptions (
    prescription_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    prescription_date DATE NOT NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id)
) ENGINE = InnoDB;

-- 5. Each prescription can contain one or more medications.
CREATE TABLE prescription_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medication_id INT NOT NULL,
    quantity INT NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    frequency VARCHAR(50) NOT NULL,
    duration_days INT NOT NULL,
    refills_allowed INT NOT NULL DEFAULT 0,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(prescription_id),
    FOREIGN KEY (medication_id) REFERENCES medication(medication_id),
    CHECK (quantity > 0),
    CHECK (duration_days > 0),
    CHECK (refills_allowed >= 0)
) ENGINE = InnoDB;

-- 6. Dispensing and payment information
-- Pickup and payment fields are optional until those steps happen.
CREATE TABLE dispensings (
    dispensing_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    dispense_date DATE,
    refill_number INT NOT NULL DEFAULT 0,
    quantity_dispensed INT NOT NULL,
    pickup_status VARCHAR(20),
    pickup_date DATE,
    total_amount DECIMAL(10,2),
    amount_paid DECIMAL(10,2),
    payment_status VARCHAR(20),
    payment_date DATETIME,
    payment_method VARCHAR(20),
    FOREIGN KEY (item_id) REFERENCES prescription_items(item_id),
    CHECK (refill_number >= 0),
    CHECK (quantity_dispensed > 0),
    CHECK (total_amount >= 0),
    CHECK (amount_paid >= 0),
    CHECK (amount_paid <= total_amount)
) ENGINE = InnoDB;

-- Check that all six tables were created.
SHOW TABLES;

-- ================================================================
-- PART 2 - INSERT SAMPLE DATA
-- ================================================================

USE pharmacy_management_refined;

INSERT INTO patients (first_name, last_name, date_of_birth, phone)
VALUES
    ('Adrian', 'Ruiz', '1995-06-15', '7185551234'),
    ('Sofia', 'Garcia', '2000-03-20', '9175554321');

INSERT INTO doctors (first_name, last_name, speciality, phone, email)
VALUES
    ('Adela', 'Smith', 'Cardiology', '2125551234', 'adela.smith@example.com');

-- Stock 79 represents the remaining amount after the example dispensing
-- of 21 units from an initial 100. This script does NOT automate inventory.
INSERT INTO medication (medication_name, strength, form, stock_quantity)
VALUES
    ('Amoxicillin', '500 mg', 'Capsule', 79);

INSERT INTO prescriptions (patient_id, doctor_id, prescription_date)
VALUES (1, 1, '2026-11-01');

INSERT INTO prescription_items
    (prescription_id, medication_id, quantity, dosage,
     frequency, duration_days, refills_allowed)
VALUES
    (1, 1, 21, '1 capsule', '3 times daily', 7, 0);

-- Refill number 0 represents the first/original fill in this example.
INSERT INTO dispensings
    (item_id, dispense_date, refill_number, quantity_dispensed,
     pickup_status, pickup_date, total_amount, amount_paid,
     payment_status, payment_date, payment_method)
VALUES
    (1, '2026-11-01', 0, 21,
     'Picked Up', '2026-11-01', 15.00, 15.00,
     'Paid', '2026-11-01 18:00:00', 'Cash');

SELECT * FROM patients;
SELECT * FROM doctors;
SELECT * FROM medication;
SELECT * FROM prescriptions;
SELECT * FROM prescription_items;
SELECT * FROM dispensings;

-- ================================================================
-- PART 3 - TEST THE DATABASE WITH SELECT QUERIES
-- ================================================================

-- 4. Join the related tables to show a prescription and dispensing.
-- This returns one row when the included sample records were loaded.
SELECT
    p.prescription_id,
    CONCAT(pt.first_name, ' ', pt.last_name) AS patient_name,
    CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
    m.medication_name,
    pi.quantity AS prescribed_quantity,
    di.quantity_dispensed,
    di.pickup_status,
    di.payment_status
FROM prescriptions p
JOIN patients pt ON p.patient_id = pt.patient_id
JOIN doctors d ON p.doctor_id = d.doctor_id
JOIN prescription_items pi ON p.prescription_id = pi.prescription_id
JOIN medication m ON pi.medication_id = m.medication_id
LEFT JOIN dispensings di ON pi.item_id = di.item_id;

-- 5. These checks should return zero rows in a valid database.
SELECT * FROM medication WHERE stock_quantity < 0;
SELECT * FROM prescription_items
WHERE quantity <= 0 OR duration_days <= 0 OR refills_allowed < 0;
SELECT * FROM dispensings
WHERE quantity_dispensed <= 0 OR refill_number < 0
   OR total_amount < 0 OR amount_paid < 0
   OR (amount_paid IS NOT NULL AND total_amount IS NOT NULL
        AND amount_paid > total_amount);

-- 6. Find dispensing records past the stated refill limit.
-- This is only a report; the database does not automatically block these.
SELECT di.dispensing_id, di.refill_number, pi.refills_allowed
FROM dispensings di
JOIN prescription_items pi ON di.item_id = pi.item_id
WHERE di.refill_number > pi.refills_allowed;
