
<?php

require_once __DIR__ . '/Prescription.php';
require_once __DIR__ . '/Medication.php';

class PrescriptionItem
{
    // Properties matching the prescription_items table.
    private PDO $connection;
    private ?int $itemId;
    private int $prescriptionId;
    private int $medicationId;
    private int $quantity;
    private string $dosage;
    private string $frequency;
    private int $durationDays;
    private int $refillsAllowed;

    // Initialize a PrescriptionItem object.
    public function __construct(
        PDO $connection,
        int $prescriptionId,
        int $medicationId,
        int $quantity,
        string $dosage,
        string $frequency,
        int $durationDays,
        int $refillsAllowed = 0,
        ?int $itemId = null
    ) {
        // Validate the IDs.
        if ($prescriptionId <= 0 || $medicationId <= 0) {
            throw new InvalidArgumentException(
                "Prescription and medication IDs must be positive."
            );
        }

        if ($itemId !== null && $itemId <= 0) {
            throw new InvalidArgumentException(
                "Item ID must be positive."
            );
        }

        // Validate quantities and duration.
        if ($quantity <= 0 || $durationDays <= 0) {
            throw new InvalidArgumentException(
                "Quantity and duration must be greater than zero."
            );
        }

        if ($refillsAllowed < 0) {
            throw new InvalidArgumentException(
                "Refills allowed cannot be negative."
            );
        }

        // Validate dosage and frequency.
        $dosage = trim($dosage);
        $frequency = trim($frequency);

        if ($dosage === '' || $frequency === '') {
            throw new InvalidArgumentException(
                "Dosage and frequency are required."
            );
        }

        if (strlen($dosage) > 50 ||
            strlen($frequency) > 50) {
            throw new InvalidArgumentException(
                "Dosage and frequency must be at most 50 characters."
            );
        }

        // Store values inside the object.
        $this->connection = $connection;
        $this->itemId = $itemId;
        $this->prescriptionId = $prescriptionId;
        $this->medicationId = $medicationId;
        $this->quantity = $quantity;
        $this->dosage = $dosage;
        $this->frequency = $frequency;
        $this->durationDays = $durationDays;
        $this->refillsAllowed = $refillsAllowed;
    }

    // Getter methods.
    public function getItemId(): ?int
    {
        return $this->itemId;
    }

    public function getPrescriptionId(): int
    {
        return $this->prescriptionId;
    }

    public function getMedicationId(): int
    {
        return $this->medicationId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getDosage(): string
    {
        return $this->dosage;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function getDurationDays(): int
    {
        return $this->durationDays;
    }

    public function getRefillsAllowed(): int
    {
        return $this->refillsAllowed;
    }

    // Return readable medication instructions.
    public function getInstructions(): string
    {
        return $this->dosage . ', ' .
               $this->frequency . ', for ' .
               $this->durationDays . ' days';
    }

    // Check whether a refill number is within the limit.
    // 0 = original fill; 1 and above = refills.
    public function isRefillNumberWithinLimit(
        int $refillNumber
    ): bool {
        return $refillNumber >= 0 &&
               $refillNumber <= $this->refillsAllowed;
    }

    // CREATE: Insert a prescription item into MySQL.
    public function insert(): int
    {
        if ($this->itemId !== null) {
            throw new LogicException(
                "This item already has a database ID."
            );
        }

        $sql = "INSERT INTO prescription_items
                    (prescription_id, medication_id,
                     quantity, dosage, frequency,
                     duration_days, refills_allowed)
                VALUES
                    (:prescription_id, :medication_id,
                     :quantity, :dosage, :frequency,
                     :duration_days, :refills_allowed)";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'prescription_id' => $this->prescriptionId,
            'medication_id' => $this->medicationId,
            'quantity' => $this->quantity,
            'dosage' => $this->dosage,
            'frequency' => $this->frequency,
            'duration_days' => $this->durationDays,
            'refills_allowed' => $this->refillsAllowed
        ]);

        $this->itemId =
            (int) $this->connection->lastInsertId();

        return $this->itemId;
    }

    // READ: Retrieve an existing prescription item.
    public static function findById(
        PDO $connection,
        int $itemId
    ): ?PrescriptionItem {
        if ($itemId <= 0) {
            throw new InvalidArgumentException(
                "Item ID must be positive."
            );
        }

        $sql = "SELECT item_id, prescription_id,
                       medication_id, quantity, dosage,
                       frequency, duration_days,
                       refills_allowed
                FROM prescription_items
                WHERE item_id = :item_id";

        $statement = $connection->prepare($sql);

        $statement->execute([
            'item_id' => $itemId
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        // Convert the database row into a PHP object.
        return new PrescriptionItem(
            $connection,
            (int) $row['prescription_id'],
            (int) $row['medication_id'],
            (int) $row['quantity'],
            $row['dosage'],
            $row['frequency'],
            (int) $row['duration_days'],
            (int) $row['refills_allowed'],
            (int) $row['item_id']
        );
    }

    // ASSOCIATION: Retrieve the related Prescription object.
    public function getPrescription(): ?Prescription
    {
        return Prescription::findById(
            $this->connection,
            $this->prescriptionId
        );
    }

    // ASSOCIATION: Retrieve the related Medication object.
    public function getMedication(): ?Medication
    {
        return Medication::findById(
            $this->connection,
            $this->medicationId
        );
    }

    // READ: Retrieve complete item details using JOINs.
    public function getDetails(): array
    {
        if ($this->itemId === null) {
            throw new LogicException(
                "Save the item before retrieving details."
            );
        }

        $sql = "
            SELECT
                pi.item_id,
                pi.prescription_id,
                p.prescription_date,
                CONCAT(pt.first_name, ' ', pt.last_name)
                    AS patient_name,
                CONCAT(d.first_name, ' ', d.last_name)
                    AS doctor_name,
                pi.medication_id,
                m.medication_name,
                m.strength,
                m.form,
                pi.quantity,
                pi.dosage,
                pi.frequency,
                pi.duration_days,
                pi.refills_allowed
            FROM prescription_items AS pi
            JOIN prescriptions AS p
                ON pi.prescription_id = p.prescription_id
            JOIN patients AS pt
                ON p.patient_id = pt.patient_id
            JOIN doctors AS d
                ON p.doctor_id = d.doctor_id
            JOIN medication AS m
                ON pi.medication_id = m.medication_id
            WHERE pi.item_id = :item_id
        ";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'item_id' => $this->itemId
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException(
                "Prescription item was not found."
            );
        }

        return $row;
    }

    // READ: Retrieve dispensing records for this item.
    public function getDispensings(): array
    {
        if ($this->itemId === null) {
            throw new LogicException(
                "Save the item before retrieving dispensings."
            );
        }

        $sql = "
            SELECT
                dispensing_id,
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
            FROM dispensings
            WHERE item_id = :item_id
            ORDER BY refill_number, dispensing_id
        ";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'item_id' => $this->itemId
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}

