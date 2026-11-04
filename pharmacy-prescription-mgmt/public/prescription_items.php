<?php
declare(strict_types=1);

session_start();

// Existing OOP classes.
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Prescription.php';
require_once __DIR__ . '/../app/Medication.php';
require_once __DIR__ . '/../app/PrescriptionItem.php';

// Escape values for HTML.
function itemHtml(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Get a submitted form field.
function itemField(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

// Validate an integer.
function itemInteger(mixed $value, int $minimum = 1): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $number = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => [
            'min_range' => $minimum,
            'max_range' => 2147483647
        ]]
    );

    return $number === false ? null : (int) $number;
}

// Session token for POST forms.
if (empty($_SESSION['item_csrf'])) {
    $_SESSION['item_csrf'] = bin2hex(random_bytes(32));
}

// Initial variables.
$errors = [];
$item = null;
$details = [];
$dispensings = [];
$selectedId = null;

// Registration form values.
$values = [
    'prescription_id' => '',
    'medication_id' => '',
    'quantity' => '',
    'dosage' => '',
    'frequency' => '',
    'duration_days' => '',
    'refills_allowed' => '0'
];

// Success message after redirect.
$success = $_SESSION['item_success'] ?? null;
unset($_SESSION['item_success']);

// Optionally prefill a prescription ID from a link.
if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['prescription_id'])) {

    $prefill = itemInteger($_GET['prescription_id']);

    if ($prefill !== null) {
        $values['prescription_id'] = (string) $prefill;
    }
}

/*
 * SECTION 1: Register a prescription item.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($values as $field => $default) {
        $values[$field] = itemField($field);
    }

    try {

        // Verify CSRF token.
        $token = $_POST['csrf_token'] ?? null;

        if (!is_string($token) ||
            !hash_equals($_SESSION['item_csrf'], $token)) {

            throw new InvalidArgumentException(
                'Invalid form token. Reload the page.'
            );
        }

        if (itemField('action') !== 'create') {
            throw new InvalidArgumentException(
                'Unknown form action.'
            );
        }

        // Validate IDs and quantities.
        $prescriptionId = itemInteger(
            $values['prescription_id']
        );

        $medicationId = itemInteger(
            $values['medication_id']
        );

        $quantity = itemInteger($values['quantity']);

        $duration = itemInteger(
            $values['duration_days']
        );

        $refills = itemInteger(
            $values['refills_allowed'],
            0
        );

        if ($prescriptionId === null ||
            $medicationId === null ||
            $quantity === null ||
            $duration === null ||
            $refills === null) {

            throw new InvalidArgumentException(
                'Enter valid IDs and whole-number quantities.'
            );
        }

        // Validate text fields.
        $dosage = $values['dosage'];
        $frequency = $values['frequency'];

        if ($dosage === '' || $frequency === '' ||
            strlen($dosage) > 50 ||
            strlen($frequency) > 50) {

            throw new InvalidArgumentException(
                'Dosage and frequency are required (maximum 50 characters).'
            );
        }

        // Connect using existing Database class.
        $connection = (new Database())->getConnection();

        // Confirm the prescription exists.
        if (Prescription::findById(
            $connection,
            $prescriptionId
        ) === null) {

            throw new InvalidArgumentException(
                'Prescription ID does not exist.'
            );
        }

        // Confirm the medication exists.
        if (Medication::findById(
            $connection,
            $medicationId
        ) === null) {

            throw new InvalidArgumentException(
                'Medication ID does not exist.'
            );
        }

        // Create the PrescriptionItem object.
        $newItem = new PrescriptionItem(
            $connection,
            $prescriptionId,
            $medicationId,
            $quantity,
            $dosage,
            $frequency,
            $duration,
            $refills
        );

        // Existing OOP insert method.
        $newId = $newItem->insert();

        $_SESSION['item_success'] =
            'Prescription item saved successfully.';

        // Redirect after POST.
        header(
            'Location: prescription_items.php?id=' . $newId,
            true,
            303
        );
        exit;

    } catch (InvalidArgumentException | LogicException $error) {

        $errors[] = $error->getMessage();

    } catch (Throwable $error) {

        error_log($error->getMessage());
        $errors[] = 'Unable to save prescription item.';
    }
}

/*
 * SECTION 2: Find a saved prescription item.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['id'])) {

    $selectedId = itemInteger($_GET['id']);

    if ($selectedId === null) {
        $errors[] = 'Enter a valid prescription item ID.';
    }
}

/*
 * SECTION 3: Retrieve item details and dispensing history.
 */
if ($selectedId !== null) {

    try {

        $connection = (new Database())->getConnection();

        $item = PrescriptionItem::findById(
            $connection,
            $selectedId
        );

        if ($item === null) {

            $errors[] = 'Prescription item not found.';

        } else {

            $details = $item->getDetails();
            $dispensings = $item->getDispensings();
        }

    } catch (Throwable $error) {

        error_log($error->getMessage());
        $errors[] = 'Unable to load prescription item.';
        $item = null;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Prescription Items | Pharmacy Prescription Management System</title>
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
            <a href="doctors.php">Doctors</a>
            <a href="medications.php">Medications</a>
            <a href="prescriptions.php">Prescriptions</a>

            <a class="active"
               href="prescription_items.php"
               aria-current="page">
                Prescription Items
            </a>

            <a href="dispensings.php">Dispensing</a>
        </nav>
    </div>
</header>

<main class="container page-content">

    <p class="breadcrumb">
        <a href="index.php">Home</a> / Prescription Items
    </p>

    <h1>Prescription Item Dashboard</h1>

    <p class="page-description">
        Add a medication to an existing prescription,
        find an item, and view its dispensing records.
    </p>

    <!-- Messages -->

    <?php if ($success !== null): ?>
        <p class="message message-success" role="status">
            <?= itemHtml($success) ?>
        </p>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="message message-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= itemHtml($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Register prescription item -->

    <section class="panel">

        <h2>Add Prescription Item</h2>

        <p class="muted">
            Enter an existing Prescription ID and Medication ID.
        </p>

        <form method="post" action="prescription_items.php">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= itemHtml($_SESSION['item_csrf']) ?>">

            <input type="hidden"
                   name="action"
                   value="create">

            <div class="form-grid">

                <div class="form-field">
                    <label for="prescription_id">
                        Prescription ID *
                    </label>

                    <input type="number"
                           id="prescription_id"
                           name="prescription_id"
                           min="1"
                           required
                           value="<?= itemHtml($values['prescription_id']) ?>">
                </div>

                <div class="form-field">
                    <label for="medication_id">
                        Medication ID *
                    </label>

                    <input type="number"
                           id="medication_id"
                           name="medication_id"
                           min="1"
                           required
                           value="<?= itemHtml($values['medication_id']) ?>">
                </div>

                <div class="form-field">
                    <label for="quantity">
                        Prescribed Quantity *
                    </label>

                    <input type="number"
                           id="quantity"
                           name="quantity"
                           min="1"
                           required
                           value="<?= itemHtml($values['quantity']) ?>">
                </div>

                <div class="form-field">
                    <label for="dosage">
                        Dosage *
                    </label>

                    <input type="text"
                           id="dosage"
                           name="dosage"
                           maxlength="50"
                           required
                           placeholder="1 capsule"
                           value="<?= itemHtml($values['dosage']) ?>">
                </div>

                <div class="form-field">
                    <label for="frequency">
                        Frequency *
                    </label>

                    <input type="text"
                           id="frequency"
                           name="frequency"
                           maxlength="50"
                           required
                           placeholder="3 times daily"
                           value="<?= itemHtml($values['frequency']) ?>">
                </div>

                <div class="form-field">
                    <label for="duration_days">
                        Duration (Days) *
                    </label>

                    <input type="number"
                           id="duration_days"
                           name="duration_days"
                           min="1"
                           required
                           value="<?= itemHtml($values['duration_days']) ?>">
                </div>

                <div class="form-field">
                    <label for="refills_allowed">
                        Refills Allowed *
                    </label>

                    <input type="number"
                           id="refills_allowed"
                           name="refills_allowed"
                           min="0"
                           required
                           value="<?= itemHtml($values['refills_allowed']) ?>">
                </div>

            </div>

            <div class="form-actions">
                <button type="submit"
                        class="button button-primary">
                    Save Prescription Item
                </button>

                <a href="prescription_items.php"
                   class="button button-light">
                    Clear
                </a>
            </div>

        </form>
    </section>

    <!-- Find item -->

    <section class="panel">

        <h2>Find Prescription Item</h2>

        <form method="get"
              action="prescription_items.php"
              class="search-form">

            <div class="form-field">
                <label for="item_id">
                    Item ID
                </label>

                <input type="number"
                       id="item_id"
                       name="id"
                       min="1"
                       required
                       value="<?= itemHtml($selectedId ?? '') ?>">
            </div>

            <button type="submit"
                    class="button button-primary">
                Find Item
            </button>

            <a href="prescription_items.php"
               class="button button-light">
                Clear
            </a>
        </form>
    </section>

    <!-- Selected item details -->

    <?php if ($item !== null): ?>

        <section class="panel">

            <h2>Prescription Item Details</h2>

            <dl class="details-list">

                <div>
                    <dt>Item ID</dt>
                    <dd><?= itemHtml($item->getItemId()) ?></dd>
                </div>

                <div>
                    <dt>Prescription</dt>
                    <dd>
                        <a href="prescriptions.php?id=<?= itemHtml(
                            $item->getPrescriptionId()
                        ) ?>">
                            #<?= itemHtml($item->getPrescriptionId()) ?>
                        </a>
                    </dd>
                </div>

                <div>
                    <dt>Medication</dt>
                    <dd>
                        <a href="medications.php?id=<?= itemHtml(
                            $item->getMedicationId()
                        ) ?>">
                            <?= itemHtml($details['medication_name']) ?>
                        </a>
                    </dd>
                </div>

                <div>
                    <dt>Patient</dt>
                    <dd><?= itemHtml($details['patient_name']) ?></dd>
                </div>

                <div>
                    <dt>Doctor</dt>
                    <dd><?= itemHtml($details['doctor_name']) ?></dd>
                </div>

                <div>
                    <dt>Quantity</dt>
                    <dd><?= itemHtml($item->getQuantity()) ?></dd>
                </div>

                <div>
                    <dt>Dosage</dt>
                    <dd><?= itemHtml($item->getDosage()) ?></dd>
                </div>

                <div>
                    <dt>Frequency</dt>
                    <dd><?= itemHtml($item->getFrequency()) ?></dd>
                </div>

                <div>
                    <dt>Duration</dt>
                    <dd>
                        <?= itemHtml($item->getDurationDays()) ?> days
                    </dd>
                </div>

                <div>
                    <dt>Refills Allowed</dt>
                    <dd><?= itemHtml($item->getRefillsAllowed()) ?></dd>
                </div>

                <div>
                    <dt>Instructions</dt>
                    <dd><?= itemHtml($item->getInstructions()) ?></dd>
                </div>

            </dl>

            <p>
                <a class="button button-primary"
                   href="dispensings.php?item_id=<?= itemHtml(
                       $item->getItemId()
                   ) ?>">
                    Open Dispensing Dashboard
                </a>
            </p>

        </section>

        <!-- Related dispensing history -->

        <section class="panel">

            <h2>Dispensing History</h2>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Dispensing ID</th>
                            <th>Date</th>
                            <th>Refill</th>
                            <th>Quantity</th>
                            <th>Pickup</th>
                            <th>Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($dispensings === []): ?>

                        <tr>
                            <td colspan="7">
                                No dispensing records yet.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($dispensings as $row): ?>
                            <tr>
                                <td>
                                    <?= itemHtml($row['dispensing_id']) ?>
                                </td>

                                <td>
                                    <?= itemHtml($row['dispense_date'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= itemHtml($row['refill_number']) ?>
                                </td>

                                <td>
                                    <?= itemHtml($row['quantity_dispensed']) ?>
                                </td>

                                <td>
                                    <?= itemHtml($row['pickup_status'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= itemHtml($row['payment_status'] ?? '-') ?>
                                </td>

                                <td>
                                    <a href="dispensings.php?id=<?= itemHtml(
                                        $row['dispensing_id']
                                    ) ?>">
                                        View
                                    </a>
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



