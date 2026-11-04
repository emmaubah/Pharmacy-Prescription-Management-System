<?php
declare(strict_types=1);

session_start();

// Reuse existing OOP classes.
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Doctor.php';

// Safely display database values in HTML.
function doctorHtml(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Read an HTML form field safely.
function doctorInput(string $field): string
{
    $value = $_POST[$field] ?? '';

    return is_string($value) ? trim($value) : '';
}

// Validate an ID from GET or POST.
function validDoctorId(mixed $value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $id = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    return $id === false ? null : (int) $id;
}

// Create the CSRF token once per session.
if (!isset($_SESSION['doctor_csrf_token'])) {
    $_SESSION['doctor_csrf_token'] =
        bin2hex(random_bytes(32));
}

// Messages and dashboard data.
$errors = [];
$doctor = null;
$history = [];

$success = $_SESSION['doctor_success'] ?? null;
unset($_SESSION['doctor_success']);

// Values for the registration form.
$firstName = '';
$lastName = '';
$speciality = '';
$phone = '';
$email = '';
$action = '';

// Check whether the URL contains a doctor ID.
$selectedId = null;

if (isset($_GET['id'])) {

    $selectedId = validDoctorId($_GET['id']);

    if ($selectedId === null) {
        $errors[] = 'Please enter a valid doctor ID.';
    }
}

/*
 * Handle form submissions.
 *
 * Action 1: create
 * Action 2: update_contact
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = doctorInput('action');

    $firstName = doctorInput('first_name');
    $lastName = doctorInput('last_name');
    $speciality = doctorInput('speciality');
    $phone = doctorInput('phone');
    $email = doctorInput('email');

    if ($action === 'update_contact') {
        $selectedId = validDoctorId(
            $_POST['doctor_id'] ?? null
        );
    }

    try {

        // Verify the CSRF token.
        $submittedToken = $_POST['csrf_token'] ?? null;

        if (!is_string($submittedToken) ||
            !hash_equals(
                $_SESSION['doctor_csrf_token'],
                $submittedToken
            )) {

            http_response_code(400);

            throw new InvalidArgumentException(
                'Invalid form token. Reload the page.'
            );
        }

        // Validate common contact fields.
        if (strlen($phone) > 20) {
            throw new InvalidArgumentException(
                'Phone number cannot exceed 20 characters.'
            );
        }

        if (strlen($email) > 100) {
            throw new InvalidArgumentException(
                'Email cannot exceed 100 characters.'
            );
        }

        if ($email !== '' &&
            filter_var($email, FILTER_VALIDATE_EMAIL) === false) {

            throw new InvalidArgumentException(
                'Please provide a valid email address.'
            );
        }

        // Reuse the existing database connection.
        $connection = (new Database())->getConnection();

        // Register a new doctor.
        if ($action === 'create') {

            if ($firstName === '' || $lastName === '') {

                throw new InvalidArgumentException(
                    'First name and last name are required.'
                );
            }

            if (strlen($firstName) > 50 ||
                strlen($lastName) > 50 ||
                strlen($speciality) > 50) {

                throw new InvalidArgumentException(
                    'Name or speciality exceeds 50 characters.'
                );
            }

            // Existing Doctor constructor.
            $newDoctor = new Doctor(
                $connection,
                $firstName,
                $lastName,
                $speciality === '' ? null : $speciality,
                $phone === '' ? null : $phone,
                $email === '' ? null : $email
            );

            // Existing OOP insert method.
            $newId = $newDoctor->insert();

            $_SESSION['doctor_success'] =
                'Doctor registered successfully.';

            // Redirect after POST to avoid duplicates.
            header(
                'Location: doctors.php?id=' . $newId,
                true,
                303
            );
            exit;
        }

        // Update an existing doctor's contact information.
        elseif ($action === 'update_contact') {

            if ($selectedId === null) {

                throw new InvalidArgumentException(
                    'Invalid doctor ID.'
                );
            }

            // Existing OOP lookup method.
            $existingDoctor = Doctor::findById(
                $connection,
                $selectedId
            );

            if ($existingDoctor === null) {

                throw new InvalidArgumentException(
                    'Doctor not found.'
                );
            }

            // Existing OOP update method.
            $existingDoctor->updateContact(
                $phone === '' ? null : $phone,
                $email === '' ? null : $email
            );

            $_SESSION['doctor_success'] =
                'Doctor contact information updated.';

            header(
                'Location: doctors.php?id=' . $selectedId,
                true,
                303
            );
            exit;
        }

        else {
            throw new InvalidArgumentException(
                'Unknown form action.'
            );
        }

    } catch (InvalidArgumentException | LogicException $error) {

        $errors[] = $error->getMessage();

    } catch (Throwable $error) {

        // Keep technical errors in the server log.
        error_log($error->getMessage());

        $errors[] = 'Unable to save doctor information.';
    }
}

/*
 * Retrieve a doctor when an ID is selected.
 */
if ($selectedId !== null) {

    try {

        $connection = (new Database())->getConnection();

        // Find the selected doctor.
        $doctor = Doctor::findById(
            $connection,
            $selectedId
        );

        if ($doctor === null) {

            $errors[] = 'No doctor found with that ID.';

        } else {

            // Retrieve the doctor's existing prescriptions.
            $history = $doctor->getPrescriptions();
        }

    } catch (Throwable $error) {

        error_log($error->getMessage());

        $errors[] = 'Unable to load doctor records.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Doctor Dashboard | Pharmacy Management</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<!-- Application navigation -->

<header class="site-header">

    <div class="container header-content">

        <a class="brand" href="index.php">
            Pharmacy Management
        </a>

        <nav aria-label="Main navigation">

            <a href="index.php">Home</a>

            <a href="patients.php">Patients</a>

            <a class="active"
               href="doctors.php"
               aria-current="page">
                Doctors
            </a>

            <a href="medications.php">Medications</a>

            <a href="prescriptions.php">Prescriptions</a>

            <a href="prescription_items.php">Prescription Items</a>
            <a href="dispensings.php">Dispensing</a>

        </nav>

    </div>

</header>

<main class="container page-content">

    <p class="breadcrumb">
        <a href="index.php">Home</a> / Doctors
    </p>

    <h1>Doctor Dashboard</h1>

    <p class="page-description">
        Register doctors, find doctor records,
        update contact information, and view prescriptions.
    </p>

    <!-- Success message -->

    <?php if ($success !== null): ?>

        <p class="message message-success" role="status">
            <?= doctorHtml($success) ?>
        </p>

    <?php endif; ?>

    <!-- Error messages -->

    <?php if ($errors !== []): ?>

        <div class="message message-error" role="alert">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li><?= doctorHtml($error) ?></li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <!-- Section 1: Register doctor -->

    <section class="panel">

        <h2>Register New Doctor</h2>

        <p class="muted">
            Enter fictional doctor information.
            First and last name are required.
        </p>

        <form method="post" action="doctors.php">

            <!-- CSRF protection -->

            <input
                type="hidden"
                name="csrf_token"
                value="<?= doctorHtml(
                    $_SESSION['doctor_csrf_token']
                ) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="create"
            >

            <div class="form-grid">

                <div class="form-field">

                    <label for="first_name">
                        First Name *
                    </label>

                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        maxlength="50"
                        required
                        value="<?= doctorHtml($firstName) ?>"
                    >

                </div>

                <div class="form-field">

                    <label for="last_name">
                        Last Name *
                    </label>

                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        maxlength="50"
                        required
                        value="<?= doctorHtml($lastName) ?>"
                    >

                </div>

                <div class="form-field">

                    <label for="speciality">
                        Speciality
                    </label>

                    <input
                        type="text"
                        id="speciality"
                        name="speciality"
                        maxlength="50"
                        value="<?= doctorHtml($speciality) ?>"
                    >

                </div>

                <div class="form-field">

                    <label for="new_phone">
                        Phone Number
                    </label>

                    <input
                        type="tel"
                        id="new_phone"
                        name="phone"
                        maxlength="20"
                        value="<?= doctorHtml($action === 'create' ? $phone : ''
                        ) ?>"

                    >



                </div>

                <div class="form-field">

                    <label for="new_email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="new_email"
                        name="email"
                        maxlength="100"
                        value="<?= doctorHtml(
                            $action === 'create'
                                ? $email
                                : ''
                        ) ?>"
                    >

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="button button-primary">
                    Save Doctor
                </button>

                <a
                    href="doctors.php"
                    class="button button-light">
                    Clear
                </a>

            </div>

        </form>

    </section>

    <!-- Section 2: Search by doctor ID -->

    <section class="panel">

        <h2>Find Doctor</h2>

        <p class="muted">
            Search using the doctor's ID number.
        </p>

        <form
            method="get"
            action="doctors.php"
            class="search-form"
        >

            <div class="form-field">

                <label for="doctor_id">
                    Doctor ID
                </label>

                <input
                    type="number"
                    id="doctor_id"
                    name="id"
                    min="1"
                    required
                    value="<?= doctorHtml(
                        $selectedId ?? ''
                    ) ?>"
                >

            </div>

            <button
                type="submit"
                class="button button-primary">
                Find Doctor
            </button>

            <a
                href="doctors.php"
                class="button button-light">
                Clear
            </a>

        </form>

    </section>

    <!-- Section 3: Selected doctor details -->

    <?php if ($doctor !== null): ?>

        <section class="panel">

            <h2>Doctor Information</h2>

            <dl class="details-list">

                <div>
                    <dt>Doctor ID</dt>

                    <dd>
                        <?= doctorHtml(
                            $doctor->getDoctorId()
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Full Name</dt>

                    <dd>
                        <?= doctorHtml(
                            $doctor->getFullName()
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Speciality</dt>

                    <dd>
                        <?= doctorHtml(
                            $doctor->getSpeciality()
                            ?? 'Not provided'
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Phone</dt>

                    <dd>
                        <?= doctorHtml(
                            $doctor->getPhone()
                            ?? 'Not provided'
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Email</dt>

                    <dd>
                        <?= doctorHtml(
                            $doctor->getEmail()
                            ?? 'Not provided'
                        ) ?>
                    </dd>
                </div>

            </dl>

        </section>

        <!-- Section 4: Edit existing contact -->

        <section class="panel">

            <h2>Update Doctor Contact</h2>

            <p class="muted">
                Update the doctor's phone and email.
                Other fields remain unchanged.
            </p>

            <form
                method="post"
                action="doctors.php"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= doctorHtml(
                        $_SESSION['doctor_csrf_token']
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="update_contact"
                >

                <input
                    type="hidden"
                    name="doctor_id"
                    value="<?= doctorHtml(
                        $doctor->getDoctorId()
                    ) ?>"
                >

                <div class="form-grid">

                    <div class="form-field">

                        <label for="edit_phone">
                            Phone Number
                        </label>

                        <input
                            type="tel"
                            id="edit_phone"
                            name="phone"
                            maxlength="20"
                            value="<?= doctorHtml(
                                $doctor->getPhone() ?? ''
                            ) ?>"
                        >

                    </div>

                    <div class="form-field">

                        <label for="edit_email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="edit_email"
                            name="email"
                            maxlength="100"
                            value="<?= doctorHtml(
                                $doctor->getEmail() ?? ''
                            ) ?>"
                        >

                    </div>

                </div>

                <button
                    type="submit"
                    class="button button-primary">
                    Update Contact
                </button>

            </form>

        </section>

        <!-- Section 5: Doctor prescription history -->

        <section class="panel">

            <h2>Doctor Prescription History</h2>

            <p class="muted">
                Prescriptions associated with
                <?= doctorHtml($doctor->getFullName()) ?>.
            </p>

            <div class="table-scroll">

                <table>

                    <thead>

                        <tr>
                            <th scope="col">Prescription ID</th>
                            <th scope="col">Date</th>
                            <th scope="col">Patient</th>
                            <th scope="col">Medication</th>
                            <th scope="col">Quantity</th>
                            <th scope="col">Dosage</th>
                            <th scope="col">Frequency</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($history === []): ?>

                        <tr>
                            <td colspan="7">
                                No prescription history found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($history as $item): ?>

                            <tr>

                                <td>
                                    <?= doctorHtml(
                                        $item['prescription_id'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= doctorHtml(
                                        $item['prescription_date'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= doctorHtml(
                                        $item['patient_name'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= doctorHtml(
                                        $item['medication_name'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= doctorHtml(
                                        $item['quantity'] ?? '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= doctorHtml(
                                        $item['dosage'] ?? '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= doctorHtml(
                                        $item['frequency'] ?? '-'
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>

</main>

<footer class="site-footer">

    <div class="container">
        CIS 344 &middot; PHP, HTML, CSS and MySQL
    </div>

</footer>

</body>
</html>


