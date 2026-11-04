<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Patient.php';
require_once __DIR__ . '/../app/patient_web_helpers.php';

// Get patient ID from the URL.
$id = patientValidId($_GET['id'] ?? null);

$patient = null;
$profile = null;
$history = [];
$errorMessage = null;

if ($id === null) {

    http_response_code(400);
    $errorMessage = 'Please provide a valid patient ID.';

} else {

    try {

        $connection = (new Database())->getConnection();

        // Get the existing Patient object.
        $patient = Patient::findById($connection, $id);

        if ($patient === null) {

            http_response_code(404);
            $errorMessage = 'Patient not found.';

        } else {

            // Retrieve all basic patient fields.
            $profile = Patient::findProfile(
                $connection,
                $id
            );

            // Retrieve related prescription history.
            $history = $patient->getPrescriptions();
        }

    } catch (Throwable $error) {

        error_log($error->getMessage());

        http_response_code(500);
        $errorMessage = 'Unable to load patient details.';
    }
}

// Read the success message after a redirect.
$successMessage = $_SESSION['patient_flash'] ?? null;
unset($_SESSION['patient_flash']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Patient Details | Pharmacy Management</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header class="site-header">
    <div class="container header-content">

        <a class="brand" href="index.php">
            Pharmacy Management
        </a>

        <nav aria-label="Main navigation">
            <a href="index.php">Home</a>
            <a href="patients.php">Patients</a>
            <a href="medications.php">Medications</a>
            <a href="prescriptions.php">Prescriptions</a>
            <a href="prescription_items.php">Prescription Items</a>
            <a href="dispensings.php">Dispensing</a>
            
        </nav>

    </div>
</header>

<main class="container page-content">

    <p class="breadcrumb">
        <a href="patients.php">Patients</a> / Details
    </p>

    <h1>Patient Details</h1>

    <?php if ($errorMessage !== null): ?>

        <p class="message message-error" role="alert">
            <?= patientHtml($errorMessage) ?>
        </p>

        <a href="patients.php">Back to Patients</a>

    <?php else: ?>

        <?php if ($successMessage !== null): ?>

            <p class="message message-success" role="status">
                <?= patientHtml($successMessage) ?>
            </p>

        <?php endif; ?>

        <!-- Patient information -->
        <section class="panel">

            <div class="page-toolbar">

                <h2>
                    <?= patientHtml($patient->getFullName()) ?>
                </h2>

                <a
                    href="edit_patient.php?id=<?= patientHtml($id) ?>"
                    class="button button-primary">
                    Edit Patient
                </a>

            </div>

            <dl class="details-list">

                <div>
                    <dt>Patient ID</dt>
                    <dd>
                        <?= patientHtml(
                            $patient->getPatientId()
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Full Name</dt>
                    <dd>
                        <?= patientHtml(
                            $patient->getFullName()
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Date of Birth</dt>
                    <dd>
                        <?= patientHtml(
                            $profile['date_of_birth']
                            ?? 'Not provided'
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Phone</dt>
                    <dd>
                        <?= patientHtml(
                            $patient->getPhone()
                            ?? 'Not provided'
                        ) ?>
                    </dd>
                </div>

            </dl>

        </section>

        <!-- Prescription history -->
        <section class="panel">

            <h2>Prescription History</h2>

            <p class="muted">
                Prescriptions associated with this patient.
                One row represents one prescribed medication.
            </p>

            <div class="table-scroll">

                <table>

                    <thead>
                        <tr>
                            <th>Prescription ID</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Medication</th>
                            <th>Quantity</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
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
                                    <?= patientHtml(
                                        $item['prescription_id'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= patientHtml(
                                        $item['prescription_date'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= patientHtml(
                                        $item['doctor_name'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= patientHtml(
                                        $item['medication_name']
                                        ?? 'No item'
                                    ) ?>
                                </td>

                                <td>
                                    <?= patientHtml(
                                        $item['quantity'] ?? '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= patientHtml(
                                        $item['dosage'] ?? '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= patientHtml(
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
        CIS 344 &middot;
    </div>
</footer>

</body>
</html>

