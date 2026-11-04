<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Patient.php';
require_once __DIR__ . '/../app/patient_web_helpers.php';

// Start with empty form values.
$values = patientFormValues([]);
$errors = [];

// Process the form after the Save button is submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $values = patientFormValues($_POST);

    // Check CSRF protection.
    if (!patientCsrfValid($_POST['csrf_token'] ?? null)) {

        http_response_code(400);
        $errors[] = 'Invalid form token. Reload and try again.';

    } else {

        // Validate submitted patient information.
        $errors = patientFormErrors($values);

        if ($errors === []) {

            try {

                $connection = (new Database())->getConnection();

                // Create a Patient object.
                $patient = new Patient(
                    $connection,
                    $values['first_name'],
                    $values['last_name'],
                    $values['date_of_birth'] === ''
                        ? null
                        : $values['date_of_birth'],
                    $values['phone'] === ''
                        ? null
                        : $values['phone']
                );

                // Save patient using the existing OOP method.
                $newId = $patient->insert();

                $_SESSION['patient_flash'] =
                    'Patient registered successfully.';

                // Redirect after saving.
                header(
                    'Location: patient_details.php?id=' . $newId,
                    true,
                    303
                );

                exit;

            } catch (InvalidArgumentException $error) {

                $errors[] = $error->getMessage();

            } catch (Throwable $error) {

                error_log($error->getMessage());

                $errors[] = 'Unable to save the patient.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Register Patient | Pharmacy Management</title>

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
        <a href="patients.php">Patients</a> / Register
    </p>

    <h1>Register New Patient</h1>

    <p class="page-description">
        Enter the patient's information below.
        Use fictional records for testing.
    </p>

    <!-- Display validation errors -->
    <?php if ($errors !== []): ?>

        <div class="message message-error" role="alert">

            <strong>Please correct the following:</strong>

            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= patientHtml($error) ?></li>
                <?php endforeach; ?>
            </ul>

        </div>

    <?php endif; ?>

    <section class="panel">

        <h2>Patient Information</h2>

        <form method="post" action="add_patient.php">

            <!-- CSRF token -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= patientHtml(patientCsrfToken()) ?>"
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
                        value="<?= patientHtml($values['first_name']) ?>"
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
                        value="<?= patientHtml($values['last_name']) ?>"
                    >

                </div>

                <div class="form-field">

                    <label for="date_of_birth">
                        Date of Birth
                    </label>

                    <input
                        type="date"
                        id="date_of_birth"
                        name="date_of_birth"
                        max="<?= date('Y-m-d') ?>"
                        value="<?= patientHtml($values['date_of_birth']) ?>"
                    >

                </div>

                <div class="form-field">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        maxlength="20"
                        value="<?= patientHtml($values['phone']) ?>"
                    >

                </div>

            </div>

            <div class="form-actions">

                <button
                    class="button button-primary"
                    type="submit">
                    Save Patient
                </button>

                <a href="patients.php"
                   class="button button-light">
                    Cancel
                </a>

            </div>

        </form>

    </section>

</main>

<footer class="site-footer">
    <div class="container">
        CIS 344 &middot; Fictional records only
    </div>
</footer>

</body>
</html>

