
<?php

// Run this test from Command Prompt only.
if (PHP_SAPI !== 'cli') {
    exit("Run this test from the command line.");
}

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/PrescriptionItem.php';

$connection = null;

try {
    // TEST 1: Connect to MySQL.
    $database = new Database();
    $connection = $database->getConnection();

    echo "Database connected successfully!\n\n";

    // TEST 2: Retrieve existing prescription item.
    $existing = PrescriptionItem::findById(
        $connection,
        1
    );

    if ($existing === null) {
        throw new RuntimeException(
            "Prescription item ID 1 was not found."
        );
    }

    echo "Existing item ID: ";
    echo $existing->getItemId() . "\n";

    echo "Instructions: ";
    echo $existing->getInstructions() . "\n";

    echo "Quantity: ";
    echo $existing->getQuantity() . "\n";

    echo "Refills allowed: ";
    echo $existing->getRefillsAllowed() . "\n\n";

    // TEST 3: Retrieve related objects.
    $prescription = $existing->getPrescription();
    $medication = $existing->getMedication();

    if ($prescription === null || $medication === null) {
        throw new RuntimeException(
            "Prescription or Medication not found."
        );
    }

    echo "Prescription ID: ";
    echo $prescription->getPrescriptionId() . "\n";

    echo "Medication: ";
    echo $medication->getMedicationName() . "\n\n";

    // TEST 4: Check refill number limits.
    echo "Initial fill number 0 within limit? ";

    echo $existing->isRefillNumberWithinLimit(0)
        ? "Yes\n" : "No\n";

    echo "Refill number 1 within limit? ";

    echo $existing->isRefillNumberWithinLimit(1)
        ? "Yes\n" : "No\n";

    // TEST 5: Retrieve details using SQL JOINs.
    echo "\nPrescription item details:\n";

    print_r($existing->getDetails());

    // TEST 6: Retrieve associated dispensing records.
    echo "\nAssociated dispensing records:\n";

    print_r($existing->getDispensings());

    // TEST 7: Begin a temporary transaction.
    $connection->beginTransaction();

    // Create a temporary prescription item.
    // Prescription ID 1 and Medication ID 1 already exist.
    $newItem = new PrescriptionItem(
        $connection,
        1,                  // Prescription ID
        1,                  // Medication ID
        10,                 // Quantity
        "1 capsule",        // Dosage
        "Once daily",       // Frequency
        10,                 // Duration in days
        1                   // Refills allowed
    );

    // Insert the new item.
    $newId = $newItem->insert();

    echo "\nTemporary item inserted with ID: ";
    echo $newId . "\n";

    // TEST 8: Retrieve the temporary item.
    $savedItem = PrescriptionItem::findById(
        $connection,
        $newId
    );

    if ($savedItem === null) {
        throw new RuntimeException(
            "Temporary prescription item not found."
        );
    }

    echo "Retrieved temporary item ID: ";
    echo $savedItem->getItemId() . "\n";

    echo "Temporary item instructions: ";
    echo $savedItem->getInstructions() . "\n";

    // TEST 9: Undo the temporary insertion.
    $connection->rollBack();

    echo "Test insert rolled back; ";
    echo "no extra item was kept.\n";

} catch (Throwable $error) {

    // Undo unfinished database changes.
    if (
        $connection instanceof PDO &&
        $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    echo "Test failed: ";
    echo $error->getMessage() . "\n";
}

