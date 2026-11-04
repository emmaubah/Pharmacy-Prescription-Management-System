<?php

class Patient
{
    // Properties based on the patients table.
    private PDO $connection;
    private ?int $patientId;
    private string $firstName;
    private string $lastName;
    private ?string $dateOfBirth;
    private ?string $phone;

    // Initialize a Patient object.
    public function __construct(
        PDO $connection,
        string $firstName,
        string $lastName,
        ?string $dateOfBirth = null,
        ?string $phone = null,
        ?int $patientId = null
    ) {
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        // First and last names are required.
        if ($firstName === '' || $lastName === '') {
            throw new InvalidArgumentException(
                "First and last names are required."
            );
        }

        // Match the maximum lengths in MySQL.
        if (strlen($firstName) > 50 ||
            strlen($lastName) > 50) {
            throw new InvalidArgumentException(
                "Names cannot exceed 50 characters."
            );
        }

        // Validate the optional birth date.
        if ($dateOfBirth !== null) {
            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $dateOfBirth
            );

            if ($date === false ||
                $date->format('Y-m-d') !== $dateOfBirth) {
                throw new InvalidArgumentException(
                    "Birth date must be a valid YYYY-MM-DD date."
                );
            }
        }

        // Validate the optional phone number.
        if ($phone !== null) {
            $phone = trim($phone);

            if ($phone === '') {
                $phone = null;
            } elseif (strlen($phone) > 20) {
                throw new InvalidArgumentException(
                    "Phone number is too long."
                );
            }
        }

        $this->connection = $connection;
        $this->patientId = $patientId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->dateOfBirth = $dateOfBirth;
        $this->phone = $phone;
    }

    // Return the patient's full name.
    public function getFullName(): string
    {
        return $this->firstName . " " . $this->lastName;
    }

    // Return the patient ID, or null if not saved.
    public function getPatientId(): ?int
    {
        return $this->patientId;
    }

    // Return the phone number.
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    // INSERT: Save a new patient into MySQL.
    public function insert(): int
    {
        if ($this->patientId !== null) {
            throw new LogicException(
                "This patient already has a database ID."
            );
        }

        $sql = "INSERT INTO patients
                    (first_name, last_name, date_of_birth, phone)
                VALUES
                    (:first_name, :last_name, :date_of_birth, :phone)";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'date_of_birth' => $this->dateOfBirth,
            'phone' => $this->phone
        ]);

        // MySQL automatically generates patient_id.
        $this->patientId =
            (int) $this->connection->lastInsertId();

        return $this->patientId;
    }

    // SELECT: Retrieve a patient using their ID.
    public static function findById(
        PDO $connection,
        int $patientId
    ): ?Patient {
        if ($patientId <= 0) {
            throw new InvalidArgumentException(
                "Patient ID must be positive."
            );
        }

        $sql = "SELECT patient_id, first_name, last_name,
                       date_of_birth, phone
                FROM patients
                WHERE patient_id = :patient_id";

        $statement = $connection->prepare($sql);

        $statement->execute([
            'patient_id' => $patientId
        ]);

        $row = $statement->fetch();

        // No matching database record.
        if ($row === false) {
            return null;
        }

        // Turn the retrieved row into a Patient object.
        return new Patient(
            $connection,
            $row['first_name'],
            $row['last_name'],
            $row['date_of_birth'],
            $row['phone'],
            (int) $row['patient_id']
        );
    }

    // UPDATE: Change an existing patient's phone.
    public function updatePhone(?string $newPhone): void
    {
        if ($this->patientId === null) {
            throw new LogicException(
                "Save the patient before updating."
            );
        }

        if ($newPhone !== null) {
            $newPhone = trim($newPhone);

            if ($newPhone === '') {
                $newPhone = null;
            } elseif (strlen($newPhone) > 20) {
                throw new InvalidArgumentException(
                    "Phone number is too long."
                );
            }
        }

        $sql = "UPDATE patients
                SET phone = :phone
                WHERE patient_id = :patient_id";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'phone' => $newPhone,
            'patient_id' => $this->patientId
        ]);

        $this->phone = $newPhone;
    }

    // READ: Retrieve this patient's prescription history.
    public function getPrescriptions(): array
    {
        if ($this->patientId === null) {
            throw new LogicException(
                "Save the patient before viewing history."
            );
        }

        $sql = "
            SELECT
                p.prescription_id,
                p.prescription_date,
                d.doctor_id,
                CONCAT(d.first_name, ' ', d.last_name)
                    AS doctor_name,
                pi.item_id,
                m.medication_name,
                pi.quantity,
                pi.dosage,
                pi.frequency,
                pi.duration_days,
                pi.refills_allowed
            FROM prescriptions AS p
            JOIN doctors AS d
                ON p.doctor_id = d.doctor_id
            LEFT JOIN prescription_items AS pi
                ON p.prescription_id = pi.prescription_id
            LEFT JOIN medication AS m
                ON pi.medication_id = m.medication_id
            WHERE p.patient_id = :patient_id
            ORDER BY
                p.prescription_date DESC,
                p.prescription_id DESC,
                pi.item_id
        ";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'patient_id' => $this->patientId
        ]);

        return $statement->fetchAll();
    }

    public static function findAll(
        PDO $connection,
        string $search = ''
    ): array {

        $sql = 'SELECT patient_id,
                    first_name,
                    last_name,
                    date_of_birth,
                    phone
                FROM patients';

        $parameters = [];

        // Add a name filter only when searching.
        if ($search !== '') {

            $sql .= " WHERE first_name LIKE :first_name
                    OR last_name LIKE :last_name
                    OR CONCAT(first_name, ' ', last_name)
                        LIKE :full_name";

            $pattern = '%' . trim($search) . '%';

            $parameters = [
                'first_name' => $pattern,
                'last_name' => $pattern,
                'full_name' => $pattern
            ];
        }

        $sql .= ' ORDER BY patient_id DESC LIMIT 50';

        // Prepared statement protects query values.
        $statement = $connection->prepare($sql);
        $statement->execute($parameters);

        $patients = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {

            $patients[] = new self(
                $connection,
                $row['first_name'],
                $row['last_name'],
                $row['date_of_birth'],
                $row['phone'],
                (int) $row['patient_id']
            );
        }

        return $patients;
    }

    // Retrieve one patient's editable information.
    public static function findProfile(
        PDO $connection,
        int $patientId
    ): ?array {

        if ($patientId <= 0) {
            throw new InvalidArgumentException(
                'Patient ID must be positive.'
            );
        }

        $sql = "SELECT patient_id, first_name, last_name,
                       date_of_birth, phone
                FROM patients
                WHERE patient_id = :patient_id";

        $statement = $connection->prepare($sql);

        $statement->execute([
            'patient_id' => $patientId
        ]);

        $patient = $statement->fetch(PDO::FETCH_ASSOC);

        return $patient === false ? null : $patient;
    }


    // Update a patient's information.
    public static function updateProfile(
        PDO $connection,
        int $patientId,
        string $firstName,
        string $lastName,
        ?string $dateOfBirth,
        ?string $phone
    ): void {

        $firstName = trim($firstName);
        $lastName = trim($lastName);

        if ($patientId <= 0 ||
            $firstName === '' ||
            $lastName === '') {

            throw new InvalidArgumentException(
                'Valid patient ID and names are required.'
            );
        }

        if (strlen($firstName) > 50 ||
            strlen($lastName) > 50 ||
            ($phone !== null && strlen($phone) > 20)) {

            throw new InvalidArgumentException(
                'A patient field is too long.'
            );
        }

        if ($dateOfBirth !== null) {

            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $dateOfBirth
            );

            if ($date === false ||
                $date->format('Y-m-d') !== $dateOfBirth ||
                $dateOfBirth > date('Y-m-d')) {

                throw new InvalidArgumentException(
                    'Date of birth is invalid.'
                );
            }
        }

        $sql = "UPDATE patients
                SET first_name = :first_name,
                    last_name = :last_name,
                    date_of_birth = :date_of_birth,
                    phone = :phone
                WHERE patient_id = :patient_id";

        $statement = $connection->prepare($sql);

        $statement->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => $dateOfBirth,
            'phone' => $phone,
            'patient_id' => $patientId
        ]);
    }





}

