<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Patient.php';
require_once __DIR__ . '/../app/patient_web_helpers.php';

// Patient ID from URL.
$id = patientValidId($_GET['id'] ?? null);

$values = patientFormValues([]);
$errors = [];
$profile = null;

if ($id === null) {

    http_response_code(400);
    $errors[] = 'Invalid patient ID.';

} else {

    try {

        $connection = (new Database())->getConnection();

        // Retrieve existing patient information.
        $profile = Patient::findProfile(
            $connection,
            $id
        );

        if ($profile === null) {

            http_response_code(404);
            $errors[] = 'Patient not found.';

        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Retrieve submitted form values.
            $values = patientFormValues($_POST);

            if (!patientCsrfValid($_POST['csrf_token'] ?? null)) {

                http_response_code(400);
                $errors[] = 'Invalid form token. Reload and try again.';

            } else {

                $errors = patientFormErrors($values);

                if ($errors === []) {

                    // Update through the Patient class.
                    Patient::updateProfile(
                        $connection,
                        $id,
                        $values['first_name'],
                        $values['last_name'],
                        $values['date_of_birth'] === ''
                            ? null
                            : $values['date_of_birth'],
                        $values['phone'] === ''
                            ? null
                            : $values['phone']
                    );

                    $_SESSION['patient_flash'] =
                        'Patient updated successfully.';

                    // Return to the patient details page.
                    header(
                        'Location: patient_details.php?id=' . $id,
                        true,
                        303
                    );

                    exit;
                }
            }

        } else {

            // Prefill the form on an ordinary GET request.
            $values = patientFormValues($profile);
        }

    } catch (InvalidArgumentException $error) {

        $errors[] = $error->getMessage();

    } catch (Throwable $error) {

        error_log($error->getMessage());

        $errors[] = 'Unable to update patient information.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Edit Patient | Pharmacy Management</title>

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
        <a href="patients.php">Patients</a> / Edit
    </p>

    <h1>Edit Patient</h1>

    <!-- Display errors -->
    <?php if ($errors !== []): ?>

        <div class="message message-error" role="alert">

            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= patientHtml($error) ?></li>
                <?php endforeach; ?>
            </ul>

        </div>

    <?php endif; ?>

    <?php if ($profile !== null): ?>

        <section class="panel">

            <h2>
                Patient #<?= patientHtml($id) ?>
            </h2>

            <p class="muted">
                Update the patient information below.
                The patient ID cannot be changed.
            </p>

            <form
                method="post"
                action="edit_patient.php?id=<?= patientHtml($id) ?>"
            >

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
                        type="submit"
                        class="button button-primary">
                        Save Changes
                    </button>

                    <a
                        href="patient_details.php?id=<?= patientHtml($id) ?>"
                        class="button button-light">
                        Cancel
                    </a>

                </div>

            </form>

        </section>

    <?php else: ?>

        <p>
            <a href="patients.php">Back to Patients</a>
        </p>

    <?php endif; ?>

</main>

<footer class="site-footer">
    <div class="container">
        CIS 344 &middot; Fictional records only
    </div>
</footer>

</body>
</html>

