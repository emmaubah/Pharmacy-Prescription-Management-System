<?php
declare(strict_types=1);

session_start();

// Reuse the existing OOP classes.
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Medication.php';

// Escape information before displaying it in HTML.
function medicationHtml(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Read a submitted form value.
function medicationField(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

// Validate a medication ID.
function medicationId(mixed $value): ?int
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

// Create a CSRF token for submitted forms.
if (!isset($_SESSION['medication_csrf'])) {
    $_SESSION['medication_csrf'] =
        bin2hex(random_bytes(32));
}

// Initial dashboard variables.
$errors = [];
$selectedId = null;
$medication = null;
$prescriptionItems = [];
$stockCheck = null;

// Read a success message after a redirect.
$success = $_SESSION['medication_success'] ?? null;
unset($_SESSION['medication_success']);

// Default registration form values.
$name = '';
$strength = '';
$form = '';
$initialStock = '0';

/*
 * SECTION 1
 * Process submitted forms using POST.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = medicationField('action');

    $name = medicationField('medication_name');
    $strength = medicationField('strength');
    $form = medicationField('form');
    $initialStock = medicationField('stock_quantity');

    if ($action === 'restock') {
        $selectedId = medicationId(
            $_POST['medication_id'] ?? null
        );
    }

    try {

        // Verify CSRF protection.
        $token = $_POST['csrf_token'] ?? null;

        if (!is_string($token) ||
            !hash_equals(
                $_SESSION['medication_csrf'],
                $token
            )) {

            throw new InvalidArgumentException(
                'Invalid form token. Reload the page and try again.'
            );
        }

        /*
         * ACTION 1: Register a new medication.
         */
        if ($action === 'create') {

            if ($name === '' || strlen($name) > 100) {

                throw new InvalidArgumentException(
                    'Medication name is required (maximum 100 characters).'
                );
            }

            if (strlen($strength) > 50 ||
                strlen($form) > 50) {

                throw new InvalidArgumentException(
                    'Strength and form must be 50 characters or fewer.'
                );
            }

            // Stock must be a nonnegative whole number.
            $stock = filter_var(
                $initialStock,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 0,
                        'max_range' => 2147483647
                    ]
                ]
            );

            if ($stock === false) {

                throw new InvalidArgumentException(
                    'Starting stock must be a whole number of zero or more.'
                );
            }

            // Connect using the existing Database class.
            $connection = (new Database())->getConnection();

            // Create a Medication object.
            $newMedication = new Medication(
                $connection,
                $name,
                $strength === '' ? null : $strength,
                $form === '' ? null : $form,
                (int) $stock
            );

            // Save using the existing insert() method.
            $newId = $newMedication->insert();

            $_SESSION['medication_success'] =
                'Medication registered successfully.';

            // Redirect after POST.
            header(
                'Location: medications.php?id=' . $newId,
                true,
                303
            );

            exit;
        }

        /*
         * ACTION 2: Restock an existing medication.
         */
        if ($action === 'restock') {

            $quantity = filter_var(
                medicationField('quantity_to_add'),
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                        'max_range' => 2147483647
                    ]
                ]
            );

            if ($selectedId === null ||
                $quantity === false) {

                throw new InvalidArgumentException(
                    'Choose a valid medication and positive restock quantity.'
                );
            }

            $connection = (new Database())->getConnection();

            // Retrieve the existing Medication object.
            $existing = Medication::findById(
                $connection,
                $selectedId
            );

            if ($existing === null) {

                throw new InvalidArgumentException(
                    'Medication not found.'
                );
            }

            // Use the existing OOP restock method.
            $existing->restock((int) $quantity);

            $_SESSION['medication_success'] =
                'Stock increased successfully.';

            header(
                'Location: medications.php?id=' . $selectedId,
                true,
                303
            );

            exit;
        }

        throw new InvalidArgumentException(
            'Unknown form action.'
        );

    } catch (InvalidArgumentException | LogicException $error) {

        $errors[] = $error->getMessage();

    } catch (Throwable $error) {

        error_log($error->getMessage());

        $errors[] =
            'Unable to save medication information. Check the server log.';
    }
}

/*
 * SECTION 2
 * Read the medication ID from GET.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['id'])) {

    $selectedId = medicationId($_GET['id']);

    if ($selectedId === null) {

        $errors[] =
            'Enter a valid positive medication ID.';
    }
}

/*
 * SECTION 3
 * Retrieve the selected medication.
 */
if ($selectedId !== null) {

    try {

        $connection = (new Database())->getConnection();

        // Existing lookup method.
        $medication = Medication::findById(
            $connection,
            $selectedId
        );

        if ($medication === null) {

            $errors[] =
                'No medication exists with that ID.';

        } else {

            // Refresh stock from MySQL.
            $medication->refreshStock();

            // Retrieve related prescription items.
            $prescriptionItems =
                $medication->getPrescriptionItems();

            /*
             * Check availability using the existing method.
             */
            if (isset($_GET['check_quantity'])) {

                $required = is_string($_GET['check_quantity'])
                    ? filter_var(
                        $_GET['check_quantity'],
                        FILTER_VALIDATE_INT,
                        [
                            'options' => [
                                'min_range' => 1,
                                'max_range' => 2147483647
                            ]
                        ]
                    )
                    : false;

                if ($required === false) {

                    $errors[] =
                        'Required quantity must be a positive whole number.';

                } else {

                    $available =
                        $medication->hasAvailableStock(
                            (int) $required
                        );

                    $stockCheck = $available
                        ? 'Stock is sufficient for ' . $required . ' unit(s).'
                        : 'Stock is not sufficient for ' . $required . ' unit(s).';
                }
            }
        }

    } catch (Throwable $error) {

        error_log($error->getMessage());

        $errors[] =
            'Unable to load medication details.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Medication Dashboard | Pharmacy Prescription Management System</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<!-- Navigation -->

<header class="site-header">

    <div class="container header-content">

        <a class="brand" href="index.php">
            Pharmacy Prescription Management System
        </a>

        <nav aria-label="Main navigation">

            <a href="index.php">Home</a>

            <a href="patients.php">Patients</a>

            <a href="doctors.php">Doctors</a>

            <a class="active"
               href="medications.php"
               aria-current="page">
                Medications
            </a>

            <a href="prescriptions.php">Prescriptions</a>

            <a href="prescription_items.php">Prescription Items</a>
            <a href="dispensings.php">Dispensing</a>

        </nav>

    </div>

</header>

<main class="container page-content">

    <p class="breadcrumb">
        <a href="index.php">Home</a> / Medications
    </p>

    <h1>Medication Dashboard</h1>

    <p class="page-description">
        Register medications, find records by ID,
        check stock, restock, and view related prescription items.
    </p>

    <!-- Display success message -->

    <?php if ($success !== null): ?>

        <p class="message message-success" role="status">
            <?= medicationHtml($success) ?>
        </p>

    <?php endif; ?>

    <!-- Display error messages -->

    <?php if ($errors !== []): ?>

        <div class="message message-error" role="alert">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li><?= medicationHtml($error) ?></li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- SECTION 1: Register Medication -->

    <section class="panel">

        <h2>Register New Medication</h2>

        <p class="muted">
            Enter fictional medication information.
            Medication name is required.
        </p>

        <form method="post" action="medications.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= medicationHtml(
                    $_SESSION['medication_csrf']
                ) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="create"
            >

            <div class="form-grid">

                <div class="form-field">

                    <label for="medication_name">
                        Medication Name *
                    </label>

                    <input
                        type="text"
                        id="medication_name"
                        name="medication_name"
                        maxlength="100"
                        required
                        value="<?= medicationHtml($name) ?>"
                    >

                </div>

                <div class="form-field">

                    <label for="strength">
                        Strength
                    </label>

                    <input
                        type="text"
                        id="strength"
                        name="strength"
                        maxlength="50"
                        value="<?= medicationHtml($strength) ?>"
                    >

                </div>

                <div class="form-field">

                    <label for="form">
                        Form
                    </label>

                    <input
                        type="text"
                        id="form"
                        name="form"
                        maxlength="50"
                        value="<?= medicationHtml($form) ?>"
                    >

                </div>

                <div class="form-field">

                    <label for="stock_quantity">
                        Initial Stock Quantity *
                    </label>

                    <input
                        type="number"
                        id="stock_quantity"
                        name="stock_quantity"
                        min="0"
                        max="2147483647"
                        required
                        value="<?= medicationHtml($initialStock) ?>"
                    >

                </div>

            </div>

            <div class="form-actions">

                <button
                    class="button button-primary"
                    type="submit">
                    Save Medication
                </button>

                <a
                    class="button button-light"
                    href="medications.php">
                    Clear
                </a>

            </div>

        </form>

    </section>


    <!-- SECTION 2: Find Medication -->

    <section class="panel">

        <h2>Find Medication</h2>

        <p class="muted">
            Search by Medication ID.
        </p>

        <form
            method="get"
            action="medications.php"
            class="search-form"
        >

            <div class="form-field">

                <label for="medication_id">
                    Medication ID
                </label>

                <input
                    type="number"
                    id="medication_id"
                    name="id"
                    min="1"
                    required
                    value="<?= medicationHtml(
                        $selectedId ?? ''
                    ) ?>"
                >

            </div>

            <button
                class="button button-primary"
                type="submit">
                Find Medication
            </button>

            <a
                class="button button-light"
                href="medications.php">
                Clear
            </a>

        </form>

    </section>


    <!-- SECTION 3: Medication Details -->

    <?php if ($medication !== null): ?>

        <section class="panel">

            <h2>Medication Information</h2>

            <dl class="details-list">

                <div>
                    <dt>Medication ID</dt>

                    <dd>
                        <?= medicationHtml(
                            $medication->getMedicationId()
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Medication Name</dt>

                    <dd>
                        <?= medicationHtml(
                            $medication->getMedicationName()
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Strength</dt>

                    <dd>
                        <?= medicationHtml(
                            $medication->getStrength()
                            ?? 'Not provided'
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Form</dt>

                    <dd>
                        <?= medicationHtml(
                            $medication->getForm()
                            ?? 'Not provided'
                        ) ?>
                    </dd>
                </div>

                <div>
                    <dt>Current Stock</dt>

                    <dd>
                        <?= medicationHtml(
                            $medication->getStockQuantity()
                        ) ?>
                    </dd>
                </div>

            </dl>

        </section>


        <!-- SECTION 4: Restock Medication -->

        <section class="panel">

            <h2>Restock Medication</h2>

            <p class="muted">
                Add units to the current stock.
                This does not replace or subtract stock.
            </p>

            <form
                method="post"
                action="medications.php"
                class="search-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= medicationHtml(
                        $_SESSION['medication_csrf']
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="restock"
                >

                <input
                    type="hidden"
                    name="medication_id"
                    value="<?= medicationHtml(
                        $medication->getMedicationId()
                    ) ?>"
                >

                <div class="form-field">

                    <label for="quantity_to_add">
                        Quantity to Add
                    </label>

                    <input
                        type="number"
                        id="quantity_to_add"
                        name="quantity_to_add"
                        min="1"
                        max="2147483647"
                        required
                    >

                </div>

                <button
                    class="button button-primary"
                    type="submit">
                    Add Stock
                </button>

            </form>

        </section>


        <!-- SECTION 5: Stock Availability -->

        <section class="panel">

            <h2>Check Available Stock</h2>

            <form
                method="get"
                action="medications.php"
                class="search-form"
            >

                <input
                    type="hidden"
                    name="id"
                    value="<?= medicationHtml(
                        $medication->getMedicationId()
                    ) ?>"
                >

                <div class="form-field">

                    <label for="check_quantity">
                        Required Quantity
                    </label>

                    <input
                        type="number"
                        id="check_quantity"
                        name="check_quantity"
                        min="1"
                        max="2147483647"
                        required
                    >

                </div>

                <button
                    class="button button-primary"
                    type="submit">
                    Check Stock
                </button>

            </form>

            <?php if ($stockCheck !== null): ?>

                <p class="message" role="status">
                    <?= medicationHtml($stockCheck) ?>
                </p>

            <?php endif; ?>

        </section>


        <!-- SECTION 6: Related Prescription Items -->

        <section class="panel">

            <h2>Related Prescription Items</h2>

            <p class="muted">
                Prescription items that reference this medication.
                No dispensing changes are made here.
            </p>

            <div class="table-scroll">

                <table>

                    <thead>
                        <tr>
                            <th>Item ID</th>
                            <th>Prescription ID</th>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Quantity</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration (days)</th>
                            <th>Refills</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($prescriptionItems === []): ?>

                        <tr>
                            <td colspan="10">
                                No prescription items found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($prescriptionItems as $item): ?>

                            <tr>

                                <td>
                                    <?= medicationHtml(
                                        $item['item_id'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= medicationHtml(
                                        $item['prescription_id'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= medicationHtml(
                                        $item['prescription_date'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= medicationHtml(
                                        $item['patient_name'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= medicationHtml(
                                        $item['doctor_name'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= medicationHtml(
                                        $item['quantity'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= medicationHtml(
                                        $item['dosage'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= medicationHtml(
                                        $item['frequency'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= medicationHtml(
                                        $item['duration_days'] ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= medicationHtml(
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


