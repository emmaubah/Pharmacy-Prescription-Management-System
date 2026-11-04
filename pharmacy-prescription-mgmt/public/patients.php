<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Patient.php';
require_once __DIR__ . '/../app/patient_web_helpers.php';

// Keep older Stage 2 patient links working.
if (isset($_GET['id'])) {
    $oldId = patientValidId($_GET['id']);

    if ($oldId !== null) {
        header(
            'Location: patient_details.php?id=' . $oldId,
            true,
            302
        );
        exit;
    }
}

// Read the patient search.
$search = isset($_GET['q']) && is_string($_GET['q'])
    ? trim($_GET['q'])
    : '';

$patients = [];
$errorMessage = null;

if (strlen($search) > 100) {

    $errorMessage = 'Search text must be 100 characters or fewer.';

} else {

    try {
        $connection = (new Database())->getConnection();

        // Use existing Patient class.
        $patients = Patient::findAll($connection, $search);

    } catch (Throwable $error) {

        error_log($error->getMessage());

        $errorMessage = 'Unable to retrieve patient records.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Patient Dashboard | Pharmacy Prescription Management System</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header class="site-header">
    <div class="container header-content">

        <a class="brand" href="index.php">
            Pharmacy Prescription Management System
        </a>

        <nav aria-label="Main navigation">
            <a href="index.php">Home</a>
            <a class="active"
               href="patients.php"
               aria-current="page">Patients</a>
            
            <a href="medications.php">Medications</a>
            <a href="prescriptions.php">Prescriptions</a>
            <a href="prescription_items.php">Prescription Items</a>
            <a href="dispensings.php">Dispensing</a>

        </nav>
    </div>
</header>

<main class="container page-content">

    <p class="breadcrumb">
        <a href="index.php">Home</a> / Patients
    </p>

    <div class="page-toolbar">

        <div>
            <h1>Patient Dashboard</h1>

            <p class="page-description">
                Search, register, and manage patient records.
            </p>
        </div>

        <a href="add_patient.php"
           class="button button-primary">
            + Register Patient
        </a>

    </div>

    <?php if ($errorMessage !== null): ?>

        <p class="message message-error" role="alert">
            <?= patientHtml($errorMessage) ?>
        </p>

    <?php endif; ?>

    <!-- Patient search -->
    <section class="panel">

        <h2>Search Patients</h2>

        <form method="get"
              action="patients.php"
              class="search-form">

            <div class="form-field">

                <label for="q">Patient name</label>

                <input
                    type="search"
                    id="q"
                    name="q"
                    maxlength="100"
                    value="<?= patientHtml($search) ?>"
                    placeholder="Search by name"
                >

            </div>

            <button type="submit"
                    class="button button-primary">
                Search
            </button>

            <a href="patients.php"
               class="button button-light">
                Clear
            </a>

        </form>

    </section>

    <!-- Patient table -->
    <section class="panel">

        <h2>Patient Records</h2>

        <p class="muted">
            Showing up to 50 patient records.
        </p>

        <div class="table-scroll">

            <table>

                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($patients === []): ?>

                    <tr>
                        <td colspan="4">
                            No patient records found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($patients as $patient): ?>

                        <tr>

                            <td>
                                <?= patientHtml(
                                    $patient->getPatientId()
                                ) ?>
                            </td>

                            <td>
                                <?= patientHtml(
                                    $patient->getFullName()
                                ) ?>
                            </td>

                            <td>
                                <?= patientHtml(
                                    $patient->getPhone()
                                    ?? 'Not provided'
                                ) ?>
                            </td>

                            <td>
                                <a href="patient_details.php?id=<?= patientHtml(
                                    $patient->getPatientId()
                                ) ?>">
                                    View Details
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

<footer class="site-footer">
    <div class="container">
        CIS 344 &middot; Pharmacy Prescription Management System
    </div>
</footer>

</body>
</html>

