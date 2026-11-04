<?php

require_once __DIR__ . '/Patient.php';
require_once __DIR__ . '/Doctor.php';

class Prescription
{
    // Properties matching the prescriptions table.
    private PDO $connection;
    private ?int $prescriptionId;
    private int $patientId;
    private int $doctorId;
    private string $prescriptionDate;

    // Initialize a Prescription object.
    public function __construct(
        PDO $connection,
        int $patientId,
        int $doctorId,
        string $prescriptionDate,
        ?int $prescriptionId = null
    ) {
        // Validate patient and doctor IDs.
        if ($patientId <= 0 || $doctorId <= 0) {
            throw new InvalidArgumentException(
                'Patient and doctor IDs must be positive.'
            );
        }

        if ($prescriptionId !== null && $prescriptionId <= 0) {
            throw new InvalidArgumentException(
                'Prescription ID must be positive.'
            );
        }

        // Check that the prescription date is valid.
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $prescriptionDate
        );

        if ($date === false ||
            $date->format('Y-m-d') !== $prescriptionDate) {
            throw new InvalidArgumentException(
                'Prescription date must be a valid YYYY-MM-DD date.'
            );
        }

        // Store values in the object.
        $this->connection = $connection;
        $this->patientId = $patientId;
        $this->doctorId = $doctorId;
        $this->prescriptionDate = $prescriptionDate;
        $this->prescriptionId = $prescriptionId;
    }

    // Return the prescription ID.
    public function getPrescriptionId(): ?int
    {
        return $this->prescriptionId;
    }

    // Return the patient ID.
    public function getPatientId(): int
    {
        return $this->patientId;
    }

    // Return the doctor ID.
    public function getDoctorId(): int
    {
        return $this->doctorId;
    }

    // Return the prescription date.
    public function getPrescriptionDate(): string
    {
        return $this->prescriptionDate;
    }

    // CREATE: Insert a new prescription into MySQL.
    public function insert(): int
    {
        if ($this->prescriptionId !== null) {
            throw new LogicException(
                'This prescription already has an ID.'
            );
        }

        $sql = "INSERT INTO prescriptions
                    (patient_id, doctor_id, prescription_date)
                VALUES
                    (:patient_id, :doctor_id, :prescription_date)";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'patient_id' => $this->patientId,
            'doctor_id' => $this->doctorId,
            'prescription_date' => $this->prescriptionDate
        ]);

        // Retrieve the new prescription ID.
        $this->prescriptionId =
            (int) $this->connection->lastInsertId();

        return $this->prescriptionId;
    }

    // READ: Retrieve a prescription by ID.
    public static function findById(
        PDO $connection,
        int $prescriptionId
    ): ?Prescription {
        if ($prescriptionId <= 0) {
            throw new InvalidArgumentException(
                'Prescription ID must be positive.'
            );
        }

        $sql = "SELECT prescription_id,
                       patient_id,
                       doctor_id,
                       prescription_date
                FROM prescriptions
                WHERE prescription_id = :prescription_id";

        $statement = $connection->prepare($sql);

        $statement->execute([
            'prescription_id' => $prescriptionId
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        // Convert the database row into a PHP object.
        return new Prescription(
            $connection,
            (int) $row['patient_id'],
            (int) $row['doctor_id'],
            $row['prescription_date'],
            (int) $row['prescription_id']
        );
    }

    // Retrieve the Patient object associated with this prescription.
    public function getPatient(): ?Patient
    {
        return Patient::findById(
            $this->connection,
            $this->patientId
        );
    }

    // Retrieve the Doctor object associated with this prescription.
    public function getDoctor(): ?Doctor
    {
        return Doctor::findById(
            $this->connection,
            $this->doctorId
        );
    }

    // READ: Retrieve patient and doctor details using JOINs.
    public function getDetails(): array
    {
        if ($this->prescriptionId === null) {
            throw new LogicException(
                'Save the prescription before retrieving details.'
            );
        }

        $sql = "
            SELECT
                p.prescription_id,
                p.prescription_date,
                p.patient_id,
                CONCAT(pt.first_name, ' ', pt.last_name)
                    AS patient_name,
                p.doctor_id,
                CONCAT(d.first_name, ' ', d.last_name)
                    AS doctor_name
            FROM prescriptions AS p
            JOIN patients AS pt
                ON p.patient_id = pt.patient_id
            JOIN doctors AS d
                ON p.doctor_id = d.doctor_id
            WHERE p.prescription_id = :prescription_id
        ";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'prescription_id' => $this->prescriptionId
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException(
                'Prescription was not found in MySQL.'
            );
        }

        return $row;
    }

    // READ: Retrieve medication items for this prescription.
    public function getItems(): array
    {
        if ($this->prescriptionId === null) {
            throw new LogicException(
                'Save the prescription before retrieving items.'
            );
        }

        $sql = "
            SELECT
                pi.item_id,
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
            JOIN medication AS m
                ON pi.medication_id = m.medication_id
            WHERE pi.prescription_id = :prescription_id
            ORDER BY pi.item_id
        ";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'prescription_id' => $this->prescriptionId
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}

