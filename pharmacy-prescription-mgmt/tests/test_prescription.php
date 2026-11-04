
<?php

if (PHP_SAPI !== 'cli') {
    exit('Run this test from the command line.');
}

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Prescription.php';

$connection = null;

try {
    // TEST 1: Connect to MySQL.
    $database = new Database();
    $connection = $database->getConnection();

    echo "Database connected successfully!\n\n";

    // TEST 2: Retrieve existing sample prescription.
    $existing = Prescription::findById(
        $connection,
        1
    );

    if ($existing === null) {
        throw new RuntimeException(
            'Sample prescription ID 1 was not found.'
        );
    }

    echo "Existing prescription ID: ";
    echo $existing->getPrescriptionId() . "\n";

    echo "Date: ";
    echo $existing->getPrescriptionDate() . "\n";

    // TEST 3: Retrieve associated Patient and Doctor.
    $patient = $existing->getPatient();
    $doctor = $existing->getDoctor();

    if ($patient === null || $doctor === null) {
        throw new RuntimeException(
            'Patient or doctor not found.'
        );
    }

    echo "Patient: ";
    echo $patient->getFullName() . "\n";

    echo "Doctor: ";
    echo $doctor->getFullName() . "\n";

    // TEST 4: Retrieve joined details.
    echo "\nPrescription details:\n";
    print_r($existing->getDetails());

    // TEST 5: Retrieve medication items.
    echo "\nPrescription items:\n";
    print_r($existing->getItems());

    // TEST 6: Begin a temporary database transaction.
    $connection->beginTransaction();

    // Create a new prescription with existing IDs.
    $newPrescription = new Prescription(
        $connection,
        1,
        1,
        '2026-11-03'
    );

    $newId = $newPrescription->insert();

    echo "\nTest prescription inserted with ID: ";
    echo $newId . "\n";

    // TEST 7: Retrieve the temporary prescription.
    $saved = Prescription::findById(
        $connection,
        $newId
    );

    if ($saved === null) {
        throw new RuntimeException(
            'Inserted prescription could not be retrieved.'
        );
    }

    echo "Retrieved test prescription ID: ";
    echo $saved->getPrescriptionId() . "\n";

    echo "New prescription items: ";
    echo count($saved->getItems()) . "\n";

    // TEST 8: Undo the test insertion.
    $connection->rollBack();

    echo "Test insert rolled back; ";
    echo "no new prescription was kept.\n";

} catch (Throwable $error) {

    // Undo unfinished test changes if an error occurs.
    if (
        $connection instanceof PDO &&
        $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    echo "Test failed: ";
    echo $error->getMessage() . "\n";
}

