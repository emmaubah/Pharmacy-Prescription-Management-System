<?php
declare(strict_types=1);

session_start();

// Reuse the existing OOP classes.
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Patient.php';
require_once __DIR__ . '/../app/Doctor.php';
require_once __DIR__ . '/../app/Prescription.php';

// Escape values printed inside HTML.
function prescriptionHtml(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Read a text field submitted by POST.
function prescriptionField(string $name): string
{
    $value = $_POST[$name] ?? '';

    return is_string($value) ? trim($value) : '';
}

// Accept positive integer IDs only.
function prescriptionPositiveId(mixed $value): ?int
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

// Create a CSRF token for HTML forms.
if (empty($_SESSION['prescription_csrf'])) {
    $_SESSION['prescription_csrf'] =
        bin2hex(random_bytes(32));
}

// Dashboard variables.
$errors = [];
$selectedPrescription = null;
$details = [];
$items = [];
$relatedPatient = null;
$relatedDoctor = null;
$selectedId = null;

// Registration form values.
$patientIdInput = '';
$doctorIdInput = '';
$dateInput = date('Y-m-d');

// Read success message after redirect.
$success = $_SESSION['prescription_success'] ?? null;
unset($_SESSION['prescription_success']);


/*
 * SECTION 1
 * Register a new prescription using POST.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $patientIdInput = prescriptionField('patient_id');
    $doctorIdInput = prescriptionField('doctor_id');
    $dateInput = prescriptionField('prescription_date');

    try {

        // Check the CSRF token.
        $submittedToken = $_POST['csrf_token'] ?? null;

        if (!is_string($submittedToken) ||
            !hash_equals(
                $_SESSION['prescription_csrf'],
                $submittedToken
            )) {

            throw new InvalidArgumentException(
                'Invalid form token. Refresh and try again.'
            );
        }

        // Check form action.
        if (prescriptionField('action') !== 'create') {

            throw new InvalidArgumentException(
                'Unknown form action.'
            );
        }

        // Validate patient and doctor IDs.
        $patientId = prescriptionPositiveId($patientIdInput);
        $doctorId = prescriptionPositiveId($doctorIdInput);

        if ($patientId === null || $doctorId === null) {

            throw new InvalidArgumentException(
                'Patient ID and Doctor ID must be positive whole numbers.'
            );
        }

        // Validate prescription date.
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $dateInput
        );

        if ($date === false ||
            $date->format('Y-m-d') !== $dateInput) {

            throw new InvalidArgumentException(
                'Enter a valid prescription date.'
            );
        }

        // Use the existing Database class.
        $connection = (new Database())->getConnection();

        // Verify that the patient exists.
        if (Patient::findById(
            $connection,
            $patientId
        ) === null) {

            throw new InvalidArgumentException(
                'The selected patient does not exist.'
            );
        }

        // Verify that the doctor exists.
        if (Doctor::findById(
            $connection,
            $doctorId
        ) === null) {

            throw new InvalidArgumentException(
                'The selected doctor does not exist.'
            );
        }

        // Create a Prescription object.
        $prescription = new Prescription(
            $connection,
            $patientId,
            $doctorId,
            $dateInput
        );

        // Save using the existing insert() method.
        $newId = $prescription->insert();

        $_SESSION['prescription_success'] =
            'Prescription saved successfully.';

        // Redirect after successful POST.
        header(
            'Location: prescriptions.php?id=' . $newId,
            true,
            303
        );

        exit;

    } catch (InvalidArgumentException | LogicException $error) {

        $errors[] = $error->getMessage();

    } catch (Throwable $error) {

        error_log($error->getMessage());

        $errors[] =
            'Could not save the prescription. Check the server log.';
    }
}


/*
 * SECTION 2
 * Search for an existing prescription by ID.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['id'])) {

    $selectedId = prescriptionPositiveId($_GET['id']);

    if ($selectedId === null) {

        $errors[] =
            'Enter a valid positive prescription ID.';
    }
}


/*
 * SECTION 3
 * Retrieve prescription details and items.
 */
if ($selectedId !== null) {

    try {

        $connection = (new Database())->getConnection();

        // Existing Prescription lookup method.
        $selectedPrescription = Prescription::findById(
            $connection,
            $selectedId
        );

        if ($selectedPrescription === null) {

            $errors[] =
                'No prescription was found with that ID.';

        } else {

            // Existing OOP methods.
            $details = $selectedPrescription->getDetails();

            $items = $selectedPrescription->getItems();

            $relatedPatient =
                $selectedPrescription->getPatient();

            $relatedDoctor =
                $selectedPrescription->getDoctor();
        }

    } catch (Throwable $error) {

        error_log($error->getMessage());

        $errors[] =
            'Unable to load prescription information.';

        $selectedPrescription = null;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Prescription Dashboard | Pharmacy Prescription Management System</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<!-- Application navigation -->

<header class="site-header">

    <div class="container header-content">

        <a class="brand" href="index.php">
            Pharmacy Prescription Management System
        </a>

        <nav aria-label="Main navigation">

            <a href="index.php">Home</a>

            <a href="patients.php">Patients</a>

            <a href="doctors.php">Doctors</a>

            <a href="medications.php">Medications</a>

            <a class="active"
               href="prescriptions.php"
               aria-current="page">
                Prescriptions
            </a>

            <a href="prescription_items.php">Prescription Items</a>
            <a href="dispensings.php">Dispensing</a>

        </nav>

    </div>

</header>

<main class="container page-content">

    <p class="breadcrumb">
        <a href="index.php">Home</a> / Prescriptions
    </p>

    <h1>Prescription Dashboard</h1>

    <p class="page-description">
        Create a prescription for an existing patient
        and doctor, or find a saved prescription by ID.
    </p>

    <!-- Success message -->

    <?php if ($success !== null): ?>

        <p class="message message-success" role="status">
            <?= prescriptionHtml($success) ?>
        </p>

    <?php endif; ?>

    <!-- Error messages -->

    <?php if ($errors !== []): ?>

        <div class="message message-error" role="alert">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= prescriptionHtml($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- SECTION 4: Register New Prescription -->

    <section class="panel">

        <h2>Register New Prescription</h2>

        <p class="muted">
            Use existing Patient and Doctor IDs.
            Medication items will be handled
            separately in Stage 4D.
        </p>

        <form method="post" action="prescriptions.php">

            <!-- CSRF protection -->

            <input
                type="hidden"
                name="csrf_token"
                value="<?= prescriptionHtml(
                    $_SESSION['prescription_csrf']
                ) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="create"
            >

            <div class="form-grid">

                <div class="form-field">

                    <label for="patient_id">
                        Patient ID *
                    </label>

                    <input
                        type="number"
                        id="patient_id"
                        name="patient_id"
                        min="1"
                        required
                        value="<?= prescriptionHtml(
                            $patientIdInput
                        ) ?>"
                    >

                </div>

                <div class="form-field">

                    <label for="doctor_id">
                        Doctor ID *
                    </label>

                    <input
                        type="number"
                        id="doctor_id"
                        name="doctor_id"
                        min="1"
                        required
                        value="<?= prescriptionHtml(
                            $doctorIdInput
                        ) ?>"
                    >

                </div>

                <div class="form-field">

                    <label for="prescription_date">
                        Prescription Date *
                    </label>

                    <input
                        type="date"
                        id="prescription_date"
                        name="prescription_date"
                        required
                        value="<?= prescriptionHtml(
                            $dateInput
                        ) ?>"
                    >

                </div>

            </div>

            <div class="form-actions">

                <button
                    class="button button-primary"
                    type="submit">
                    Save Prescription
                </button>

                <a
                    class="button button-light"
                    href="prescriptions.php">
                    Clear
                </a>

            </div>

        </form>

    </section>


    <!-- SECTION 5: Search by Prescription ID -->

    <section class="panel">

        <h2>Find Prescription</h2>

        <p class="muted">
            Enter a previously saved Prescription ID.
        </p>

        <form
            method="get"
            action="prescriptions.php"
            class="search-form"
        >

            <div class="form-field">

                <label for="id">
                    Prescription ID
                </label>

                <input
                    type="number"
                    id="id"
                    name="id"
                    min="1"
                    required
                    value="<?= prescriptionHtml(
                        $selectedId ?? ''
                    ) ?>"
                >

            </div>

            <button
                class="button button-primary"
                type="submit">
                Find Prescription
            </button>

            <a
                class="button button-light"
                href="prescriptions.php">
                Clear
            </a>

        </form>

    </section>


    <!-- SECTION 6: Selected Prescription Details -->

    <?php if ($selectedPrescription !== null): ?>
        <p>
            <a
                class="button button-primary"
                href="prescription_items.php?prescription_id=<?= prescriptionHtml(
                    $selectedPrescription->getPrescriptionId()
                ) ?>">
                Add Medication to This Prescription
            </a>
        </p>


       

        <section class="panel">

            <h2>Prescription Details</h2>

            <dl class="details-list">

                <div>
                    <dt>Prescription ID</dt>

                    <dd>
                        <?= prescriptionHtml(
                            $selectedPrescription->getPrescriptionId()
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Prescription Date</dt>

                    <dd>
                        <?= prescriptionHtml(
                            $selectedPrescription->getPrescriptionDate()
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Patient</dt>

                    <dd>

                        <a href="patient_details.php?id=<?= prescriptionHtml(
                            $selectedPrescription->getPatientId()
                        ) ?>">

                            <?= prescriptionHtml(
                                $relatedPatient?->getFullName()
                                ?? ($details['patient_name'] ?? 'Unknown')
                            ) ?>

                        </a>

                        (ID <?= prescriptionHtml(
                            $selectedPrescription->getPatientId()
                        ) ?>)

                    </dd>
                </div>

                <div>
                    <dt>Doctor</dt>

                    <dd>

                        <a href="doctors.php?id=<?= prescriptionHtml(
                            $selectedPrescription->getDoctorId()
                        ) ?>">

                            <?= prescriptionHtml(
                                $relatedDoctor?->getFullName()
                                ?? ($details['doctor_name'] ?? 'Unknown')
                            ) ?>

                        </a>

                        (ID <?= prescriptionHtml(
                            $selectedPrescription->getDoctorId()
                        ) ?>)

                    </dd>
                </div>

            </dl>

        </section>


        <!-- SECTION 7: Prescription Items -->

        <section class="panel">

            <h2>Prescription Medication Items</h2>

            <p class="muted">
                These records come from the existing
                Prescription::getItems() method.
                New prescriptions may not have medication
                items until Stage 4D is implemented.
            </p>

            <div class="table-scroll">

                <table>

                    <thead>
                        <tr>
                            <th scope="col">Item ID</th>
                            <th scope="col">Medication</th>
                            <th scope="col">Strength</th>
                            <th scope="col">Form</th>
                            <th scope="col">Quantity</th>
                            <th scope="col">Dosage</th>
                            <th scope="col">Frequency</th>
                            <th scope="col">Duration (Days)</th>
                            <th scope="col">Refills Allowed</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($items === []): ?>

                        <tr>

                            <td colspan="9">
                                No medication items are linked
                                to this prescription yet.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($items as $item): ?>

                            <tr>

                                <td>
                                    <?= prescriptionHtml(
                                        $item['item_id'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= prescriptionHtml(
                                        $item['medication_name'] ?? ''
                                    ) ?>

                                    (ID <?= prescriptionHtml(
                                        $item['medication_id'] ?? ''
                                    ) ?>)
                                </td>

                                <td>
                                    <?= prescriptionHtml(
                                        $item['strength'] ?? '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= prescriptionHtml(
                                        $item['form'] ?? '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= prescriptionHtml(
                                        $item['quantity'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= prescriptionHtml(
                                        $item['dosage'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= prescriptionHtml(
                                        $item['frequency'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= prescriptionHtml(
                                        $item['duration_days'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= prescriptionHtml(
                                        $item['refills_allowed'] ?? ''
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
        CIS 344 &middot; Fictional classroom data only
    </div>

</footer>

</body>
</html>


