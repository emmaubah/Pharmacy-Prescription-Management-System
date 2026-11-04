
<?php

class Medication
{
    // Properties matching the medication table.
    private PDO $connection;
    private ?int $medicationId;
    private string $medicationName;
    private ?string $strength;
    private ?string $form;
    private int $stockQuantity;

    // Initialize a Medication object.
    public function __construct(
        PDO $connection,
        string $medicationName,
        ?string $strength = null,
        ?string $form = null,
        int $stockQuantity = 0,
        ?int $medicationId = null
    ) {
        $medicationName = trim($medicationName);

        // Validate the medication name.
        if ($medicationName === '' ||
            strlen($medicationName) > 100) {
            throw new InvalidArgumentException(
                "Medication name is required and must be at most 100 characters."
            );
        }

        // Validate optional strength.
        if ($strength !== null) {
            $strength = trim($strength);

            if ($strength === '') {
                $strength = null;
            } elseif (strlen($strength) > 50) {
                throw new InvalidArgumentException(
                    "Strength is too long."
                );
            }
        }

        // Validate optional medication form.
        if ($form !== null) {
            $form = trim($form);

            if ($form === '') {
                $form = null;
            } elseif (strlen($form) > 50) {
                throw new InvalidArgumentException(
                    "Form is too long."
                );
            }
        }

        // Stock cannot be negative.
        if ($stockQuantity < 0) {
            throw new InvalidArgumentException(
                "Stock cannot be negative."
            );
        }

        if ($medicationId !== null && $medicationId <= 0) {
            throw new InvalidArgumentException(
                "Medication ID must be positive."
            );
        }

        // Store the values in this PHP object.
        $this->connection = $connection;
        $this->medicationId = $medicationId;
        $this->medicationName = $medicationName;
        $this->strength = $strength;
        $this->form = $form;
        $this->stockQuantity = $stockQuantity;
    }

    // Return the medication ID.
    public function getMedicationId(): ?int
    {
        return $this->medicationId;
    }

    // Return the medication name.
    public function getMedicationName(): string
    {
        return $this->medicationName;
    }

    // Return the medication strength.
    public function getStrength(): ?string
    {
        return $this->strength;
    }

    // Return the medication form.
    public function getForm(): ?string
    {
        return $this->form;
    }

    // Return the stock currently stored in this object.
    public function getStockQuantity(): int
    {
        return $this->stockQuantity;
    }

    // CREATE: Insert a new medication into MySQL.
    public function insert(): int
    {
        if ($this->medicationId !== null) {
            throw new LogicException(
                "This medication already has an ID."
            );
        }

        $sql = "INSERT INTO medication
                    (medication_name, strength, form, stock_quantity)
                VALUES
                    (:name, :strength, :form, :stock)";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'name' => $this->medicationName,
            'strength' => $this->strength,
            'form' => $this->form,
            'stock' => $this->stockQuantity
        ]);

        // Save the newly generated medication ID.
        $this->medicationId =
            (int) $this->connection->lastInsertId();

        return $this->medicationId;
    }

    // READ: Find an existing medication using its ID.
    public static function findById(
        PDO $connection,
        int $medicationId
    ): ?Medication {
        if ($medicationId <= 0) {
            throw new InvalidArgumentException(
                "Medication ID must be positive."
            );
        }

        $sql = "SELECT medication_id, medication_name,
                       strength, form, stock_quantity
                FROM medication
                WHERE medication_id = :medication_id";

        $statement = $connection->prepare($sql);

        $statement->execute([
            'medication_id' => $medicationId
        ]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        // Convert the database row into a Medication object.
        return new Medication(
            $connection,
            $row['medication_name'],
            $row['strength'],
            $row['form'],
            (int) $row['stock_quantity'],
            (int) $row['medication_id']
        );
    }

    // READ: Refresh the stock from MySQL.
    public function refreshStock(): int
    {
        if ($this->medicationId === null) {
            throw new LogicException(
                "Save the medication first."
            );
        }

        $sql = "SELECT stock_quantity
                FROM medication
                WHERE medication_id = :medication_id";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'medication_id' => $this->medicationId
        ]);

        $value = $statement->fetchColumn();

        if ($value === false) {
            throw new RuntimeException(
                "Medication was not found in MySQL."
            );
        }

        $this->stockQuantity = (int) $value;

        return $this->stockQuantity;
    }

    // Check if the requested quantity is available.
    public function hasAvailableStock(
        int $requiredQuantity
    ): bool {
        if ($requiredQuantity <= 0) {
            throw new InvalidArgumentException(
                "Required quantity must be positive."
            );
        }

        // Read current stock for a saved medication.
        if ($this->medicationId !== null) {
            $this->refreshStock();
        }

        return $this->stockQuantity >= $requiredQuantity;
    }

    // UPDATE: Add new stock to an existing medication.
    public function restock(int $quantityToAdd): int
    {
        if ($this->medicationId === null) {
            throw new LogicException(
                "Save the medication before restocking."
            );
        }

        if ($quantityToAdd <= 0) {
            throw new InvalidArgumentException(
                "Restock quantity must be positive."
            );
        }

        // Increase the quantity stored in MySQL.
        $sql = "UPDATE medication
                SET stock_quantity = stock_quantity + :quantity
                WHERE medication_id = :medication_id";

        $statement = $this->connection->prepare($sql);

        $statement->bindValue(
            ':quantity',
            $quantityToAdd,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':medication_id',
            $this->medicationId,
            PDO::PARAM_INT
        );

        $statement->execute();

        if ($statement->rowCount() === 0) {
            throw new RuntimeException(
                "Medication was not found in MySQL."
            );
        }

        // Retrieve the updated stock quantity.
        return $this->refreshStock();
    }

    // READ: Retrieve prescription items for this medication.
    public function getPrescriptionItems(): array
    {
        if ($this->medicationId === null) {
            throw new LogicException(
                "Save the medication first."
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
            WHERE pi.medication_id = :medication_id
            ORDER BY
                p.prescription_date DESC,
                pi.item_id DESC
        ";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'medication_id' => $this->medicationId
        ]);

        return $statement->fetchAll();
    }
}

