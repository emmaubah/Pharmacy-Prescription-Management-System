
<?php

// Run this file from Command Prompt only.
if (PHP_SAPI !== 'cli') {
    exit("Run this test from the command line.\n");
}

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/PrescriptionItem.php';
require_once __DIR__ . '/../app/Dispensing.php';

$connection = null;

try {
    // TEST 1: Connect to MySQL.
    $database = new Database();
    $connection = $database->getConnection();

    echo "Database connected successfully!\n\n";

    // Retrieve the original sample dispensing.
    $sample = Dispensing::findById($connection, 1);

    if ($sample === null) {
        throw new RuntimeException(
            "Sample dispensing ID 1 was not found."
        );
    }

    echo "Existing dispensing ID: ";
    echo $sample->getDispensingId() . "\n";

    echo "Existing pickup status: ";
    echo $sample->getPickupStatus() . "\n";

    echo "Existing payment status: ";
    echo $sample->getPaymentStatus() . "\n";

    echo "Existing medication: ";
    echo $sample->getDetails()['medication_name'];
    echo "\n\n";

    // Start transaction for temporary testing.
    $connection->beginTransaction();

    // TEST 2: Retrieve existing stock.
    $stockStatement = $connection->query(
        "SELECT stock_quantity
         FROM medication
         WHERE medication_id = 1"
    );

    $originalStock = (int) $stockStatement->fetchColumn();

    if ($originalStock < 6) {
        throw new RuntimeException(
            "At least 6 stock units are needed."
        );
    }

    // Create a temporary prescription item.
    $item = new PrescriptionItem(
        $connection,
        1,             // Existing prescription ID
        1,             // Existing medication ID
        3,             // Quantity
        "1 capsule",
        "Once daily",
        3,             // Duration
        1              // One refill allowed
    );

    $newItemId = $item->insert();

    echo "Temporary prescription item created: ";
    echo $newItemId . "\n";

    echo "Original medication stock: ";
    echo $originalStock . "\n\n";

    // TEST 3: Record the original fill.
    $first = new Dispensing(
        $connection,
        $newItemId,
        3,
        0,
        date('Y-m-d'),
        '12.50'
    );

    $firstId = $first->record();

    echo "Original fill recorded with ID: ";
    echo $firstId . "\n";

    // Retrieve the new dispensing.
    $saved = Dispensing::findById(
        $connection,
        $firstId
    );

    if ($saved === null) {
        throw new RuntimeException(
            "Original fill could not be retrieved."
        );
    }

    echo "Retrieved fill ID: ";
    echo $saved->getDispensingId() . "\n";

    echo "Patient: ";
    echo $saved->getDetails()['patient_name'] . "\n";

    echo "Doctor: ";
    echo $saved->getDetails()['doctor_name'] . "\n";

    echo "Medication: ";
    echo $saved->getDetails()['medication_name'] . "\n";

    echo "Status: ";
    echo $saved->getPickupStatus();
    echo " / ";
    echo $saved->getPaymentStatus() . "\n";

    // TEST 4: Mark the medication as picked up.
    $saved->markPickedUp();

    echo "Pickup status: ";
    echo $saved->getPickupStatus() . "\n";

    // TEST 5: Record payments.
    echo "Amount paid after first payment: ";

    echo $saved->recordPayment(
        '5.00',
        'Cash'
    ) . "\n";

    echo "Payment status: ";
    echo $saved->getPaymentStatus() . "\n";

    echo "Amount paid after second payment: ";

    echo $saved->recordPayment(
        '7.50',
        'Cash'
    ) . "\n";

    echo "Payment status: ";
    echo $saved->getPaymentStatus() . "\n\n";

    // TEST 6: Reject a duplicate original fill.
    try {
        $duplicate = new Dispensing(
            $connection,
            $newItemId,
            3,
            0
        );

        $duplicate->record();

        throw new RuntimeException(
            "ERROR: Duplicate fill was accepted."
        );

    } catch (DomainException $error) {
        echo "Duplicate fill rejected: ";
        echo $error->getMessage() . "\n";
    }

    // TEST 7: Record permitted refill number 1.
    $refill = new Dispensing(
        $connection,
        $newItemId,
        3,
        1
    );

    $refillId = $refill->record();

    echo "Refill number 1 recorded with ID: ";
    echo $refillId . "\n";

    // TEST 8: Reject refill number 2.
    try {
        $notAllowed = new Dispensing(
            $connection,
            $newItemId,
            3,
            2
        );

        $notAllowed->record();

        throw new RuntimeException(
            "ERROR: Excess refill was accepted."
        );

    } catch (DomainException $error) {
        echo "Excess refill rejected: ";
        echo $error->getMessage() . "\n";
    }

    // Verify stock after two temporary fills.
    $stockStatement = $connection->query(
        "SELECT stock_quantity
         FROM medication
         WHERE medication_id = 1"
    );

    $stockDuringTest =
        (int) $stockStatement->fetchColumn();

    echo "Stock after two temporary fills: ";
    echo $stockDuringTest . "\n";

    if ($stockDuringTest !== $originalStock - 6) {
        throw new RuntimeException(
            "Stock did not decrease by 6 units."
        );
    }

    // TEST 9: Roll back all temporary changes.
    $connection->rollBack();

    echo "Rolled back all test changes.\n";

    // Verify stock was restored.
    $stockStatement = $connection->query(
        "SELECT stock_quantity
         FROM medication
         WHERE medication_id = 1"
    );

    $stockAfterRollback =
        (int) $stockStatement->fetchColumn();

    echo "Stock after rollback: ";
    echo $stockAfterRollback . "\n";

    if ($stockAfterRollback !== $originalStock) {
        throw new RuntimeException(
            "Stock was not restored."
        );
    }

    // Verify temporary item was removed.
    $statement = $connection->prepare(
        "SELECT COUNT(*)
         FROM prescription_items
         WHERE item_id = :item_id"
    );

    $statement->execute([
        'item_id' => $newItemId
    ]);

    if ((int) $statement->fetchColumn() !== 0) {
        throw new RuntimeException(
            "Temporary item still exists."
        );
    }

    echo "No temporary prescription item or ";
    echo "dispensing records were kept.\n";

    echo "All dispensing tests passed!\n";

} catch (Throwable $error) {

    if (
        $connection instanceof PDO &&
        $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    echo "Test failed: ";
    echo $error->getMessage() . "\n";

    exit(1);
}

