CREATE DATABASE pharmacy_management;

USE pharmacy_management;

CREATE TABLE patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    date_of_birth DATE,
    phone VARCHAR(20)
);

CREATE TABLE doctors (
	doctor_id INT AUTO_INCREMENT PRIMARY KEY,
	first_name VARCHAR(50),
	last_name VARCHAR(50),
	speciality VARCHAR(50),
	phone VARCHAR(20),
	email VARCHAR(100)
);

CREATE TABLE medication (
	medication_id INT AUTO_INCREMENT PRIMARY KEY,
    medication_name VARCHAR(100),
    strength VARCHAR(50),
    form VARCHAR(50),
	stock_quantity INT
);

CREATE TABLE prescriptions (
	prescription_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    doctor_id INT,
    prescription_date DATE,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id)
);

CREATE TABLE prescription_items (
	item_id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT,
    medication_id INT,
    quantity INT,
    dosage VARCHAR(50),
    frequency VARCHAR(50),
    duration_days INT,
    refills_allowed INT,
    FOREIGN KEY (prescription_id)
		REFERENCES prescriptions(prescription_id),
	FOREIGN KEY (medication_id)
		REFERENCES medication(medication_id)
);

CREATE TABLE dispensings (
    dispensing_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    dispense_date DATE,
    refill_number INT,
    quantity_dispensed INT,
    pickup_status VARCHAR(20),
    pickup_date DATE,
    total_amount DECIMAL(10,2),
    amount_paid DECIMAL(10,2),
    payment_status VARCHAR(20),
    payment_date DATETIME,
    payment_method VARCHAR(20),
    FOREIGN KEY (item_id)
        REFERENCES prescription_items(item_id)
);

INSERT INTO patients (first_name, last_name, date_of_birth, phone)
VALUES ('Adrian', 'Ruiz', '1995-06-15', '7185551234');

SELECT * FROM patients;

INSERT INTO patients (first_name, last_name, date_of_birth, phone)
VALUES ('Sofia', 'Garcia', '2000-03-20', '9175554321');

SELECT * FROM patients;

INSERT INTO doctors (first_name, last_name, speciality, phone, email)
VALUES ('Adela', 'Smith', 'Cardiology', '2125551234', 'emily.smith@example.com');
SELECT * FROM doctors;

INSERT INTO medication
    (medication_name, strength, form, stock_quantity)
VALUES
    ('Amoxicillin', '500 mg', 'Capsule', 100);
SELECT * FROM medication;

INSERT INTO prescriptions
	(patient_id, doctor_id, prescription_date)
VALUES (1, 1, '2026-11-01');
SELECT * FROM prescriptions;

INSERT INTO prescription_items
    (prescription_id, medication_id, quantity,
     dosage, frequency, duration_days, refills_allowed)
VALUES
    (1, 1, 21, '1 capsule', '3 times daily', 7, 0);
SELECT * FROM prescription_items;

SHOW CREATE TABLE dispensings;
    
SELECT
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'pharmacy_management'
AND TABLE_NAME = 'dispensings'
AND REFERENCED_TABLE_NAME IS NOT NULL;


SELECT
    patients.first_name AS patient,
    doctors.first_name AS doctor,
    medication.medication_name AS medicine
FROM prescriptions
JOIN patients
    ON prescriptions.patient_id = patients.patient_id
JOIN doctors
    ON prescriptions.doctor_id = doctors.doctor_id
JOIN prescription_items
    ON prescriptions.prescription_id = prescription_items.prescription_id
JOIN medication
    ON prescription_items.medication_id = medication.medication_id;



INSERT INTO dispensings (
    item_id,
    dispense_date,
    refill_number,
    quantity_dispensed,
    pickup_status,
    pickup_date,
    total_amount,
    amount_paid,
    payment_status,
    payment_date,
    payment_method
)
VALUES (
    1,
    '2026-11-01',
    0,
    21,
    'Picked Up',
    '2026-11-01',
    15.00,
    15.00,
    'Paid',
    '2026-11-01 18:00:00',
    'Cash'
);

SELECT * FROM dispensings;

SELECT
    patients.first_name AS Patient,
    doctors.first_name AS Doctor,
    medication.medication_name AS Medication,
    dispensings.pickup_status AS Pickup_Status,
    dispensings.payment_method AS Payment_Method,
    dispensings.payment_status AS Payment_Status
FROM dispensings
JOIN prescription_items
    ON dispensings.item_id = prescription_items.item_id
JOIN prescriptions
    ON prescription_items.prescription_id = prescriptions.prescription_id
JOIN patients
    ON prescriptions.patient_id = patients.patient_id
JOIN doctors
    ON prescriptions.doctor_id = doctors.doctor_id
JOIN medication
    ON prescription_items.medication_id = medication.medication_id;


SHOW CREATE TABLE dispensings;
















