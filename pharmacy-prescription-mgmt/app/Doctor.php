
<?php

class Doctor
{
    // Properties based on the doctors table.
    private PDO $connection;
    private ?int $doctorId;
    private string $firstName;
    private string $lastName;
    private ?string $speciality;
    private ?string $phone;
    private ?string $email;

    // Initialize a Doctor object.
    public function __construct(
        PDO $connection,
        string $firstName,
        string $lastName,
        ?string $speciality = null,
        ?string $phone = null,
        ?string $email = null,
        ?int $doctorId = null
    ) {
        // Remove extra spaces.
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        // Validate required names.
        if ($firstName === '' || $lastName === '') {
            throw new InvalidArgumentException(
                "First and last names are required."
            );
        }

        if (strlen($firstName) > 50 ||
            strlen($lastName) > 50) {
            throw new InvalidArgumentException(
                "Names cannot exceed 50 characters."
            );
        }

        // Validate optional speciality.
        if ($speciality !== null) {
            $speciality = trim($speciality);

            if ($speciality === '') {
                $speciality = null;
            } elseif (strlen($speciality) > 50) {
                throw new InvalidArgumentException(
                    "Speciality cannot exceed 50 characters."
                );
            }
        }

        // Validate optional phone.
        if ($phone !== null) {
            $phone = trim($phone);

            if ($phone === '') {
                $phone = null;
            } elseif (strlen($phone) > 20) {
                throw new InvalidArgumentException(
                    "Phone cannot exceed 20 characters."
                );
            }
        }

        // Validate optional email.
        if ($email !== null) {
            $email = trim($email);

            if ($email === '') {
                $email = null;
            } elseif (
                strlen($email) > 100 ||
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                throw new InvalidArgumentException(
                    "Invalid doctor email."
                );
            }
        }

        // Assign values to the object's properties.
        $this->connection = $connection;
        $this->doctorId = $doctorId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->speciality = $speciality;
        $this->phone = $phone;
        $this->email = $email;
    }

    // Return the doctor's database ID.
    public function getDoctorId(): ?int
    {
        return $this->doctorId;
    }

    // Return the doctor's full name.
    public function getFullName(): string
    {
        return $this->firstName . " " . $this->lastName;
    }

    // Return the doctor's speciality.
    public function getSpeciality(): ?string
    {
        return $this->speciality;
    }

    // Return the doctor's phone.
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    // Return the doctor's email.
    public function getEmail(): ?string
    {
        return $this->email;
    }

    // CREATE: Insert a new doctor into MySQL.
    public function insert(): int
    {
        if ($this->doctorId !== null) {
            throw new LogicException(
                "This doctor already has a database ID."
            );
        }

        $sql = "INSERT INTO doctors
                    (first_name, last_name, speciality,
                     phone, email)
                VALUES
                    (:first_name, :last_name, :speciality,
                     :phone, :email)";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'speciality' => $this->speciality,
            'phone' => $this->phone,
            'email' => $this->email
        ]);

        // Retrieve the newly generated doctor_id.
        $this->doctorId =
            (int) $this->connection->lastInsertId();

        return $this->doctorId;
    }

    // READ: Find an existing doctor by ID.
    public static function findById(
        PDO $connection,
        int $doctorId
    ): ?Doctor {
        if ($doctorId <= 0) {
            throw new InvalidArgumentException(
                "Doctor ID must be positive."
            );
        }

        $sql = "SELECT doctor_id, first_name, last_name,
                       speciality, phone, email
                FROM doctors
                WHERE doctor_id = :doctor_id";

        $statement = $connection->prepare($sql);

        $statement->execute([
            'doctor_id' => $doctorId
        ]);

        $row = $statement->fetch();

        // Return null if the doctor does not exist.
        if ($row === false) {
            return null;
        }

        // Convert the database row into a Doctor object.
        return new Doctor(
            $connection,
            $row['first_name'],
            $row['last_name'],
            $row['speciality'],
            $row['phone'],
            $row['email'],
            (int) $row['doctor_id']
        );
    }

    // UPDATE: Change an existing doctor's contact details.
    public function updateContact(
        ?string $newPhone,
        ?string $newEmail
    ): void {
        if ($this->doctorId === null) {
            throw new LogicException(
                "Save the doctor before updating."
            );
        }

        // Validate the new phone.
        if ($newPhone !== null) {
            $newPhone = trim($newPhone);

            if ($newPhone === '') {
                $newPhone = null;
            } elseif (strlen($newPhone) > 20) {
                throw new InvalidArgumentException(
                    "Phone cannot exceed 20 characters."
                );
            }
        }

        // Validate the new email.
        if ($newEmail !== null) {
            $newEmail = trim($newEmail);

            if ($newEmail === '') {
                $newEmail = null;
            } elseif (
                strlen($newEmail) > 100 ||
                !filter_var($newEmail, FILTER_VALIDATE_EMAIL)
            ) {
                throw new InvalidArgumentException(
                    "Invalid doctor email."
                );
            }
        }

        $sql = "UPDATE doctors
                SET phone = :phone,
                    email = :email
                WHERE doctor_id = :doctor_id";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'phone' => $newPhone,
            'email' => $newEmail,
            'doctor_id' => $this->doctorId
        ]);

        // Update the values stored in this PHP object.
        $this->phone = $newPhone;
        $this->email = $newEmail;
    }

    // READ: Retrieve the doctor's prescriptions.
    public function getPrescriptions(): array
    {
        if ($this->doctorId === null) {
            throw new LogicException(
                "Save the doctor before viewing prescriptions."
            );
        }

        $sql = "
            SELECT
                p.prescription_id,
                p.prescription_date,
                pt.patient_id,
                CONCAT(pt.first_name, ' ', pt.last_name)
                    AS patient_name,
                pi.item_id,
                m.medication_name,
                pi.quantity,
                pi.dosage,
                pi.frequency,
                pi.duration_days,
                pi.refills_allowed
            FROM prescriptions AS p
            JOIN patients AS pt
                ON p.patient_id = pt.patient_id
            LEFT JOIN prescription_items AS pi
                ON p.prescription_id = pi.prescription_id
            LEFT JOIN medication AS m
                ON pi.medication_id = m.medication_id
            WHERE p.doctor_id = :doctor_id
            ORDER BY
                p.prescription_date DESC,
                p.prescription_id DESC,
                pi.item_id
        ";

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'doctor_id' => $this->doctorId
        ]);

        return $statement->fetchAll();
    }
}


