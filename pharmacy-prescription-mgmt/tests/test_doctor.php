
<?php

// Only run this test from the terminal.
if (PHP_SAPI !== 'cli') {
    exit("Run this test from the command line.");
}

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Doctor.php';

try {
    // TEST 1: Connect to MySQL.
    $database = new Database();
    $connection = $database->getConnection();

    echo "Database connected successfully!\n\n";

    // TEST 2: Create a Doctor object.
    $doctor = new Doctor(
        $connection,
        "Demo",
        "Doctor",
        "Dermatology",
        "555-0100",
        "demo.doctor@example.com"
    );

    echo "Doctor object created.\n";
    echo "Name: " . $doctor->getFullName() . "\n";
    echo "Speciality: " . $doctor->getSpeciality() . "\n";

    // TEST 3: Insert the doctor.
    $newId = $doctor->insert();

    echo "Doctor inserted with ID: " . $newId . "\n\n";

    // TEST 4: Retrieve the saved doctor.
    $savedDoctor = Doctor::findById(
        $connection,
        $newId
    );

    if ($savedDoctor === null) {
        throw new RuntimeException("Doctor not found.");
    }

    echo "Retrieved: ";
    echo $savedDoctor->getFullName() . "\n";

    // TEST 5: Update contact information.
    $savedDoctor->updateContact(
        "555-0199",
        "updated.doctor@example.com"
    );

    // Retrieve again to verify the database update.
    $updatedDoctor = Doctor::findById(
        $connection,
        $newId
    );

    if ($updatedDoctor === null) {
        throw new RuntimeException("Updated doctor not found.");
    }

    echo "Updated phone: ";
    echo $updatedDoctor->getPhone() . "\n";

    echo "Updated email: ";
    echo $updatedDoctor->getEmail() . "\n\n";

    // TEST 6: Retrieve the new doctor's prescriptions.
    $history = $updatedDoctor->getPrescriptions();

    echo "New doctor's prescription history:\n";
    print_r($history);

    // TEST 7: Check existing sample doctor ID 1.
    $sampleDoctor = Doctor::findById(
        $connection,
        1
    );

    if ($sampleDoctor !== null) {
        echo "\nDoctor 1 prescription history:\n";
        print_r($sampleDoctor->getPrescriptions());
    }

} catch (PDOException $error) {
    error_log($error->getMessage());
    echo "Database error. Check your connection or SQL.\n";

} catch (Throwable $error) {
    echo "Test failed: " . $error->getMessage() . "\n";
}

