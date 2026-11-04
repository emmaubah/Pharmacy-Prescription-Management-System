
<?php

// Run this test from the command line only.
if (PHP_SAPI !== 'cli') {
    exit("Run this test from the command line.");
}

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Patient.php';

try {
    // TEST 1: Connect to MySQL.
    $database = new Database();
    $connection = $database->getConnection();

    echo "Database connected successfully!\n\n";

    // TEST 2: Create a Patient object.
    $patient = new Patient(
        $connection,
        "Demo",
        "Patient",
        "2000-01-01",
        "555-0101"
    );

    echo "Patient object created.\n";
    echo "Name: " . $patient->getFullName() . "\n";

    // TEST 3: Insert the patient into MySQL.
    $newId = $patient->insert();

    echo "Patient inserted with ID: " . $newId . "\n\n";

    // TEST 4: Retrieve the saved patient.
    $savedPatient = Patient::findById(
        $connection,
        $newId
    );

    if ($savedPatient === null) {
        throw new RuntimeException("Patient was not found.");
    }

    echo "Retrieved: ";
    echo $savedPatient->getFullName() . "\n";

    // TEST 5: Update the phone number.
    $savedPatient->updatePhone("555-0199");

    // Retrieve it again to verify the saved change.
    $updatedPatient = Patient::findById(
        $connection,
        $newId
    );

    echo "Updated phone: ";
    echo $updatedPatient->getPhone() . "\n\n";

    // TEST 6: Retrieve the patient's prescriptions.
    $history = $updatedPatient->getPrescriptions();

    echo "New patient's prescription history:\n";
    print_r($history);

    // TEST 7: Check existing sample patient ID 1.
    $samplePatient = Patient::findById(
        $connection,
        1
    );

    if ($samplePatient !== null) {
        echo "\nPatient 1 prescription history:\n";
        print_r($samplePatient->getPrescriptions());
    }

} catch (PDOException $error) {
    error_log($error->getMessage());
    echo "Database error. Check your MySQL connection or SQL schema.\n";

} catch (Throwable $error) {
    echo "Test failed: " . $error->getMessage() . "\n";
}

