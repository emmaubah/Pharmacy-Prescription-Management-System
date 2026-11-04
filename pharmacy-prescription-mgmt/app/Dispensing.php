
<?php

require_once __DIR__ . '/PrescriptionItem.php';

/**
 * Dispensing class for the existing dispensings table.
 * Class-project rule: one complete fill per refill number.
 */
class Dispensing
{
    private PDO $connection;

    // Properties from the dispensings table.
    private ?int $dispensingId;
    private int $itemId;
    private ?string $dispenseDate;
    private int $refillNumber;
    private int $quantityDispensed;
    private ?string $pickupStatus = null;
    private ?string $pickupDate = null;
    private ?string $totalAmount;
    private ?string $amountPaid = null;
    private ?string $paymentStatus = null;
    private ?string $paymentDate = null;
    private ?string $paymentMethod = null;

    // Constructor: initialize a Dispensing object.
    public function __construct(
        PDO $connection,
        int $itemId,
        int $quantityDispensed,
        int $refillNumber = 0,
        ?string $dispenseDate = null,
        ?string $totalAmount = null,
        ?int $dispensingId = null
    ) {
        if ($itemId <= 0 || $quantityDispensed <= 0 ||
            $refillNumber < 0) {
            throw new InvalidArgumentException(
                "Invalid item ID, quantity, or refill number."
            );
        }

        if ($dispensingId !== null && $dispensingId <= 0) {
            throw new InvalidArgumentException(
                "Dispensing ID must be positive."
            );
        }

        if ($dispenseDate !== null) {
            self::validateDate($dispenseDate);
        }

        if ($totalAmount !== null) {
            self::moneyToCents($totalAmount);
        }

        $this->connection = $connection;
        $this->dispensingId = $dispensingId;
        $this->itemId = $itemId;
        $this->quantityDispensed = $quantityDispensed;
        $this->refillNumber = $refillNumber;
        $this->dispenseDate = $dispenseDate;
        $this->totalAmount = $totalAmount;
    }

    // Getter methods.
    public function getDispensingId(): ?int
    {
        return $this->dispensingId;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getRefillNumber(): int
    {
        return $this->refillNumber;
    }

    public function getQuantityDispensed(): int
    {
        return $this->quantityDispensed;
    }

    public function getDispenseDate(): ?string
    {
        return $this->dispenseDate;
    }

    public function getPickupStatus(): ?string
    {
        return $this->pickupStatus;
    }

    public function getPickupDate(): ?string
    {
        return $this->pickupDate;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function getAmountPaid(): ?string
    {
        return $this->amountPaid;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function getPaymentDate(): ?string
    {
        return $this->paymentDate;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    // CREATE: Record a dispensing and deduct medication stock.
    public function record(): int
    {
        if ($this->dispensingId !== null) {
            throw new LogicException(
                "This dispensing already has an ID."
            );
        }

        $startedHere = $this->beginOperation();

        try {
            // Lock the prescription item.
            $sql = "SELECT medication_id, quantity,
                           refills_allowed
                    FROM prescription_items
                    WHERE item_id = :item_id
                    FOR UPDATE";

            $statement = $this->connection->prepare($sql);
            $statement->execute([
                'item_id' => $this->itemId
            ]);

            $item = $statement->fetch(PDO::FETCH_ASSOC);

            if ($item === false) {
                throw new DomainException(
                    "Prescription item not found."
                );
            }

            // Validate refill limit.
            if ($this->refillNumber >
                (int) $item['refills_allowed']) {
                throw new DomainException(
                    "Refill number exceeds allowed refills."
                );
            }

            // Require a complete fill for this project.
            if ($this->quantityDispensed !==
                (int) $item['quantity']) {
                throw new DomainException(
                    "Partial fills are not supported."
                );
            }

            // Retrieve existing refill numbers.
            $sql = "SELECT refill_number
                    FROM dispensings
                    WHERE item_id = :item_id
                    FOR UPDATE";

            $statement = $this->connection->prepare($sql);
            $statement->execute([
                'item_id' => $this->itemId
            ]);

            $existing = $statement->fetchAll(
                PDO::FETCH_COLUMN
            );

            $filledNumbers = [];

            foreach ($existing as $number) {
                $filledNumbers[(int) $number] = true;
            }

            // Prevent duplicate original fills or refills.
            if (isset($filledNumbers[$this->refillNumber])) {
                throw new DomainException(
                    "This refill number was already dispensed."
                );
            }

            // Require the previous fill to exist.
            if ($this->refillNumber > 0 &&
                !isset($filledNumbers[$this->refillNumber - 1])) {
                throw new DomainException(
                    "Previous fill must exist first."
                );
            }

            // Retrieve and lock medication stock.
            $medicationId = (int) $item['medication_id'];

            $sql = "SELECT stock_quantity
                    FROM medication
                    WHERE medication_id = :medication_id
                    FOR UPDATE";

            $statement = $this->connection->prepare($sql);
            $statement->execute([
                'medication_id' => $medicationId
            ]);

            $stock = $statement->fetchColumn();

            if ($stock === false ||
                (int) $stock < $this->quantityDispensed) {
                throw new DomainException(
                    "Not enough medication stock."
                );
            }

            // Deduct stock without allowing negative quantities.
            $sql = "UPDATE medication
                    SET stock_quantity =
                        stock_quantity - :subtract_quantity
                    WHERE medication_id = :medication_id
                      AND stock_quantity >= :minimum_quantity";

            $statement = $this->connection->prepare($sql);

            $statement->execute([
                'subtract_quantity' => $this->quantityDispensed,
                'medication_id' => $medicationId,
                'minimum_quantity' => $this->quantityDispensed
            ]);

            if ($statement->rowCount() !== 1) {
                throw new DomainException(
                    "Stock could not be reduced safely."
                );
            }

            // Insert the dispensing record.
            $date = $this->dispenseDate ?? date('Y-m-d');

            $sql = "INSERT INTO dispensings
                       (item_id, dispense_date, refill_number,
                        quantity_dispensed, pickup_status,
                        total_amount, amount_paid, payment_status)
                    VALUES
                       (:item_id, :dispense_date, :refill_number,
                        :quantity_dispensed, :pickup_status,
                        :total_amount, :amount_paid, :payment_status)";

            $statement = $this->connection->prepare($sql);

            $statement->execute([
                'item_id' => $this->itemId,
                'dispense_date' => $date,
                'refill_number' => $this->refillNumber,
                'quantity_dispensed' => $this->quantityDispensed,
                'pickup_status' => 'Pending',
                'total_amount' => $this->totalAmount,
                'amount_paid' => $this->totalAmount === null
                    ? null : '0.00',
                'payment_status' => 'Unpaid'
            ]);

            $newId = (int) $this->connection->lastInsertId();

            // Commit our operation or release its savepoint.
            $this->finishOperation($startedHere);

            // Update the PHP object.
            $this->dispensingId = $newId;
            $this->dispenseDate = $date;
            $this->pickupStatus = 'Pending';
            $this->amountPaid = $this->totalAmount === null
                ? null : '0.00';
            $this->paymentStatus = 'Unpaid';

            return $newId;

        } catch (Throwable $error) {
            $this->cancelOperation($startedHere);
            throw $error;
        }
    }

    // READ: Find a dispensing by ID.
    public static function findById(
        PDO $connection,
        int $dispensingId
    ): ?Dispensing {
        if ($dispensingId <= 0) {
            throw new InvalidArgumentException(
                "Dispensing ID must be positive."
            );
        }

        $sql = "SELECT *
                FROM dispensings
                WHERE dispensing_id = :dispensing_id";

        $statement = $connection->prepare($sql);

        $statement->execute([
            'dispensing_id' => $dispensingId
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        // Convert the database row into a Dispensing object.
        $dispensing = new Dispensing(
            $connection,
            (int) $row['item_id'],
            (int) $row['quantity_dispensed'],
            (int) $row['refill_number'],
            $row['dispense_date'],
            $row['total_amount'],
            (int) $row['dispensing_id']
        );

        $dispensing->pickupStatus = $row['pickup_status'];
        $dispensing->pickupDate = $row['pickup_date'];
        $dispensing->amountPaid = $row['amount_paid'];
        $dispensing->paymentStatus = $row['payment_status'];
        $dispensing->paymentDate = $row['payment_date'];
        $dispensing->paymentMethod = $row['payment_method'];

        return $dispensing;
    }

    // ASSOCIATION: Retrieve the related PrescriptionItem.
    public function getPrescriptionItem(): ?PrescriptionItem
    {
        return PrescriptionItem::findById(
            $this->connection,
            $this->itemId
        );
    }

    // READ: Retrieve dispensing details using SQL JOINs.
    public function getDetails(): array
    {
        $this->requireSaved();

        $sql = "
            SELECT
                di.*,
                m.medication_name,
                CONCAT(pt.first_name, ' ', pt.last_name)
                    AS patient_name,
                CONCAT(d.first_name, ' ', d.last_name)
                    AS doctor_name
            FROM dispensings AS di
            JOIN prescription_items AS pi
                ON di.item_id = pi.item_id
            JOIN medication AS m
                ON pi.medication_id = m.medication_id
            JOIN prescriptions AS p
                ON pi.prescription_id = p.prescription_id
            JOIN patients AS pt
                ON p.patient_id = pt.patient_id
            JOIN doctors AS d
                ON p.doctor_id = d.doctor_id
            WHERE di.dispensing_id = :dispensing_id
        ";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'dispensing_id' => $this->dispensingId
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException(
                "Dispensing record not found."
            );
        }

        return $row;
    }

    // UPDATE: Mark a saved dispensing as picked up.
    public function markPickedUp(
        ?string $pickupDate = null
    ): void {
        $this->requireSaved();

        $date = $pickupDate ?? date('Y-m-d');
        self::validateDate($date);

        $sql = "UPDATE dispensings
                SET pickup_status = 'Picked Up',
                    pickup_date = :pickup_date
                WHERE dispensing_id = :dispensing_id
                  AND (pickup_status IS NULL
                       OR pickup_status = 'Pending')";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'pickup_date' => $date,
            'dispensing_id' => $this->dispensingId
        ]);

        if ($statement->rowCount() !== 1) {
            throw new DomainException(
                "Record not found or already picked up."
            );
        }

        $this->pickupStatus = 'Picked Up';
        $this->pickupDate = $date;
    }

    // UPDATE: Record a payment.
    public function recordPayment(
        string $paymentAmount,
        string $method
    ): string {
        $this->requireSaved();

        $centsToAdd = self::moneyToCents($paymentAmount);

        if ($centsToAdd <= 0) {
            throw new InvalidArgumentException(
                "Payment amount must be positive."
            );
        }

        $method = trim($method);

        if ($method === '' || strlen($method) > 20) {
            throw new InvalidArgumentException(
                "Invalid payment method."
            );
        }

        $startedHere = $this->beginOperation();

        try {
            // Lock the payment record.
            $sql = "SELECT total_amount, amount_paid
                    FROM dispensings
                    WHERE dispensing_id = :dispensing_id
                    FOR UPDATE";

            $statement = $this->connection->prepare($sql);

            $statement->execute([
                'dispensing_id' => $this->dispensingId
            ]);

            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                throw new DomainException(
                    "Dispensing record not found."
                );
            }

            if ($row['total_amount'] === null) {
                throw new DomainException(
                    "Total amount must be set before payment."
                );
            }

            // Calculate payment using integer cents.
            $totalCents = self::moneyToCents(
                $row['total_amount']
            );

            $paidCents = $row['amount_paid'] === null
                ? 0
                : self::moneyToCents($row['amount_paid']);

            $newCents = $paidCents + $centsToAdd;

            if ($newCents > $totalCents) {
                throw new DomainException(
                    "Payment exceeds the outstanding amount."
                );
            }

            $newPaid = self::centsToMoney($newCents);

            $status = $newCents === $totalCents
                ? 'Paid'
                : 'Partially Paid';

            $paymentDate = date('Y-m-d H:i:s');

            $sql = "UPDATE dispensings
                    SET amount_paid = :amount_paid,
                        payment_status = :payment_status,
                        payment_date = :payment_date,
                        payment_method = :payment_method
                    WHERE dispensing_id = :dispensing_id";

            $statement = $this->connection->prepare($sql);

            $statement->execute([
                'amount_paid' => $newPaid,
                'payment_status' => $status,
                'payment_date' => $paymentDate,
                'payment_method' => $method,
                'dispensing_id' => $this->dispensingId
            ]);

            $this->finishOperation($startedHere);

            // Update the object's properties.
            $this->totalAmount = $row['total_amount'];
            $this->amountPaid = $newPaid;
            $this->paymentStatus = $status;
            $this->paymentDate = $paymentDate;
            $this->paymentMethod = $method;

            return $newPaid;

        } catch (Throwable $error) {
            $this->cancelOperation($startedHere);
            throw $error;
        }
    }

    // Ensure the record already exists in MySQL.
    private function requireSaved(): void
    {
        if ($this->dispensingId === null) {
            throw new LogicException(
                "Save the dispensing first."
            );
        }
    }

    // Validate dates using YYYY-MM-DD format.
    private static function validateDate(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

        if ($parsed === false ||
            $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException(
                "Date must be a valid YYYY-MM-DD date."
            );
        }
    }

    // Convert money strings into integer cents.
    private static function moneyToCents(string $amount): int
    {
        if (!preg_match(
            '/^[0-9]{1,8}(\.[0-9]{1,2})?$/D',
            $amount
        )) {
            throw new InvalidArgumentException(
                "Invalid money amount."
            );
        }

        $parts = explode('.', $amount);

        $whole = (int) $parts[0];

        $fraction = isset($parts[1])
            ? (int) str_pad($parts[1], 2, '0')
            : 0;

        return $whole * 100 + $fraction;
    }

    // Convert integer cents back to decimal money.
    private static function centsToMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' .
            str_pad(
                (string) ($cents % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }

    // Start a transaction or a nested savepoint.
    private function beginOperation(): bool
    {
        if ($this->connection->inTransaction()) {
            $this->connection->exec(
                'SAVEPOINT dispensing_action'
            );
            return false;
        }

        $this->connection->beginTransaction();
        return true;
    }

    // Commit a transaction or release its savepoint.
    private function finishOperation(
        bool $startedHere
    ): void {
        if ($startedHere) {
            $this->connection->commit();
        } else {
            $this->connection->exec(
                'RELEASE SAVEPOINT dispensing_action'
            );
        }
    }

    // Undo changes when an operation fails.
    private function cancelOperation(
        bool $startedHere
    ): void {
        if (!$this->connection->inTransaction()) {
            return;
        }

        if ($startedHere) {
            $this->connection->rollBack();
        } else {
            $this->connection->exec(
                'ROLLBACK TO SAVEPOINT dispensing_action'
            );

            $this->connection->exec(
                'RELEASE SAVEPOINT dispensing_action'
            );
        }
    }
}

