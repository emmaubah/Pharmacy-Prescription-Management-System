
<?php

// Only run this test from the command line.
if (PHP_SAPI !== 'cli') {
    exit("Run this test from the command line.");
}

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Medication.php';

try {
    // TEST 1: Connect to MySQL.
    $database = new Database();
    $connection = $database->getConnection();

    echo "Database connected successfully!\n\n";

    // TEST 2: Create a Medication object.
    $medication = new Medication(
        $connection,
        "Demo Medication",
        "10 mg",
        "Tablet",
        20
    );

    echo "Medication object created: ";
    echo $medication->getMedicationName() . "\n";

    // TEST 3: Insert the medication into MySQL.
    $newId = $medication->insert();

    echo "Medication inserted with ID: " . $newId . "\n";

    // TEST 4: Retrieve the medication.
    $savedMedication = Medication::findById(
        $connection,
        $newId
    );

    if ($savedMedication === null) {
        throw new RuntimeException(
            "New medication not found."
        );
    }

    echo "Retrieved medication: ";
    echo $savedMedication->getMedicationName() . "\n";

    echo "Current stock: ";
    echo $savedMedication->getStockQuantity() . "\n";

    // TEST 5: Check stock availability.
    echo "Enough for 15? ";
    echo $savedMedication->hasAvailableStock(15)
        ? "Yes\n" : "No\n";

    echo "Enough for 30? ";
    echo $savedMedication->hasAvailableStock(30)
        ? "Yes\n" : "No\n";

    // TEST 6: Add 10 units to the current stock.
    $newStock = $savedMedication->restock(10);

    echo "Stock after adding 10: ";
    echo $newStock . "\n";

    // TEST 7: Verify the update by retrieving it again.
    $verifiedMedication = Medication::findById(
        $connection,
        $newId
    );

    if ($verifiedMedication === null) {
        throw new RuntimeException(
            "Restocked medication not found."
        );
    }

    echo "Verified stock from MySQL: ";
    echo $verifiedMedication->getStockQuantity() . "\n";

    // TEST 8: View existing medication's prescriptions.
    $sampleMedication = Medication::findById(
        $connection,
        1
    );

    if ($sampleMedication !== null) {
        echo "\nMedication ID 1: ";
        echo $sampleMedication->getMedicationName() . "\n";

        echo "Associated prescription items:\n";

        print_r(
            $sampleMedication->getPrescriptionItems()
        );
    }

} catch (PDOException $error) {
    error_log($error->getMessage());
    echo "Database error. Check your connection or SQL schema.\n";

} catch (Throwable $error) {
    echo "Test failed: " . $error->getMessage() . "\n";
}

