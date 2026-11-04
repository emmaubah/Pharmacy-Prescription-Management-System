<?php
declare(strict_types=1);

session_start();

// Existing OOP classes.
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/PrescriptionItem.php';
require_once __DIR__ . '/../app/Dispensing.php';

// Escape output for HTML.
function dispensingHtml(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Read a submitted form field.
function dispensingField(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

// Validate whole numbers.
function dispensingInteger(
    mixed $value,
    int $minimum = 1
): ?int {

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

// Validate a date when one is supplied.
function dispensingDate(string $value): bool
{
    if ($value === '') {
        return true;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value
    );

    return $date !== false &&
        $date->format('Y-m-d') === $value;
}

// CSRF protection.
if (empty($_SESSION['dispensing_csrf'])) {
    $_SESSION['dispensing_csrf'] =
        bin2hex(random_bytes(32));
}

// Initial variables.
$errors = [];
$selectedId = null;
$dispensing = null;
$details = [];
$relatedItem = null;
$itemPreview = null;

// New dispensing form values.
$values = [
    'item_id' => '',
    'quantity_dispensed' => '',
    'refill_number' => '0',
    'dispense_date' => date('Y-m-d'),
    'total_amount' => ''
];

// Success message after redirect.
$success = $_SESSION['dispensing_success'] ?? null;
unset($_SESSION['dispensing_success']);

/*
 * SECTION 1
 * Optional prescription item ID from another page.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['item_id'])) {

    $itemId = dispensingInteger($_GET['item_id']);

    if ($itemId === null) {

        $errors[] = 'Invalid prescription item ID.';

    } else {

        try {

            $connection = (new Database())->getConnection();

            $itemPreview = PrescriptionItem::findById(
                $connection,
                $itemId
            );

            if ($itemPreview === null) {

                $errors[] = 'Prescription item not found.';

            } else {

                // Prefill the complete prescribed quantity.
                $values['item_id'] = (string) $itemId;

                $values['quantity_dispensed'] =
                    (string) $itemPreview->getQuantity();
            }

        } catch (Throwable $error) {

            error_log($error->getMessage());
            $errors[] = 'Unable to load prescription item.';
        }
    }
}

/*
 * SECTION 2
 * Process POST forms.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = dispensingField('action');

    // Retain submitted values if recording fails.
    if ($action === 'record') {
        foreach ($values as $field => $default) {
            $values[$field] = dispensingField($field);
        }
    }

    // Load the selected record again after an update error.
    if ($action === 'pickup' || $action === 'payment') {
        $selectedId = dispensingInteger(
            $_POST['dispensing_id'] ?? null
        );
    }

    try {

        // Verify CSRF token.
        $token = $_POST['csrf_token'] ?? null;

        if (!is_string($token) ||
            !hash_equals(
                $_SESSION['dispensing_csrf'],
                $token
            )) {

            throw new InvalidArgumentException(
                'Invalid form token. Reload the page.'
            );
        }

        $connection = (new Database())->getConnection();

        /*
         * ACTION 1: Record a complete dispensing.
         */
        if ($action === 'record') {

            $itemId = dispensingInteger(
                $values['item_id']
            );

            $quantity = dispensingInteger(
                $values['quantity_dispensed']
            );

            $refill = dispensingInteger(
                $values['refill_number'],
                0
            );

            if ($itemId === null ||
                $quantity === null ||
                $refill === null) {

                throw new InvalidArgumentException(
                    'Enter valid item, quantity and refill numbers.'
                );
            }

            $item = PrescriptionItem::findById(
                $connection,
                $itemId
            );

            if ($item === null) {

                throw new InvalidArgumentException(
                    'Prescription item does not exist.'
                );
            }

            // Existing project rule: complete fills only.
            if ($quantity !== $item->getQuantity()) {

                throw new InvalidArgumentException(
                    'Quantity must match the complete prescribed quantity.'
                );
            }

            // Use the existing refill-limit check.
            if (!$item->isRefillNumberWithinLimit($refill)) {

                throw new InvalidArgumentException(
                    'Refill number exceeds the allowed range.'
                );
            }

            $date = $values['dispense_date'];

            if (!dispensingDate($date)) {

                throw new InvalidArgumentException(
                    'Invalid dispensing date.'
                );
            }

            $date = $date === '' ? null : $date;

            $total = $values['total_amount'];

            if ($total !== '' &&
                !preg_match(
                    '/^[0-9]{1,8}(\.[0-9]{1,2})?$/D',
                    $total
                )) {

                throw new InvalidArgumentException(
                    'Total amount must look like 12.50.'
                );
            }

            // Create the existing Dispensing object.
            $newDispensing = new Dispensing(
                $connection,
                $itemId,
                $quantity,
                $refill,
                $date,
                $total === '' ? null : $total
            );

            // Records dispensing and decreases stock.
            $newId = $newDispensing->record();

            $_SESSION['dispensing_success'] =
                'Dispensing recorded successfully.';

            header(
                'Location: dispensings.php?id=' . $newId,
                true,
                303
            );

            exit;
        }

        /*
         * ACTION 2: Mark dispensing as picked up.
         */
        if ($action === 'pickup') {

            if ($selectedId === null) {

                throw new InvalidArgumentException(
                    'Invalid dispensing ID.'
                );
            }

            $existing = Dispensing::findById(
                $connection,
                $selectedId
            );

            if ($existing === null) {

                throw new InvalidArgumentException(
                    'Dispensing record not found.'
                );
            }

            $pickupDate = dispensingField('pickup_date');

            if (!dispensingDate($pickupDate)) {

                throw new InvalidArgumentException(
                    'Invalid pickup date.'
                );
            }

            // Existing OOP pickup method.
            $existing->markPickedUp(
                $pickupDate === '' ? null : $pickupDate
            );

            $_SESSION['dispensing_success'] =
                'Medication marked as picked up.';

            header(
                'Location: dispensings.php?id=' . $selectedId,
                true,
                303
            );

            exit;
        }

        /*
         * ACTION 3: Record a payment.
         */
        if ($action === 'payment') {

            if ($selectedId === null) {

                throw new InvalidArgumentException(
                    'Invalid dispensing ID.'
                );
            }

            $existing = Dispensing::findById(
                $connection,
                $selectedId
            );

            if ($existing === null) {

                throw new InvalidArgumentException(
                    'Dispensing record not found.'
                );
            }

            $amount = dispensingField('payment_amount');
            $method = dispensingField('payment_method');

            // Existing class validates payment amount
            // and prevents payments above the total.
            $existing->recordPayment($amount, $method);

            $_SESSION['dispensing_success'] =
                'Payment recorded successfully.';

            header(
                'Location: dispensings.php?id=' . $selectedId,
                true,
                303
            );

            exit;
        }

        throw new InvalidArgumentException(
            'Unknown form action.'
        );

    } catch (
        InvalidArgumentException |
        DomainException |
        LogicException $error
    ) {

        $errors[] = $error->getMessage();

    } catch (Throwable $error) {

        error_log($error->getMessage());

        $errors[] = 'Unable to process dispensing request.';
    }
}

/*
 * SECTION 3
 * Find an existing dispensing using GET.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['id'])) {

    $selectedId = dispensingInteger($_GET['id']);

    if ($selectedId === null) {
        $errors[] = 'Enter a valid dispensing ID.';
    }
}

/*
 * SECTION 4
 * Retrieve the selected dispensing record.
 */
if ($selectedId !== null) {

    try {

        $connection = (new Database())->getConnection();

        $dispensing = Dispensing::findById(
            $connection,
            $selectedId
        );

        if ($dispensing === null) {

            $errors[] = 'Dispensing record not found.';

        } else {

            $details = $dispensing->getDetails();

            $relatedItem =
                $dispensing->getPrescriptionItem();
        }

    } catch (Throwable $error) {

        error_log($error->getMessage());

        $errors[] = 'Unable to load dispensing details.';
        $dispensing = null;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Dispensing Dashboard | Pharmacy Prescription Management System</title>

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
            <a href="prescription_items.php">Prescription Items</a>

            <a class="active"
               href="dispensings.php"
               aria-current="page">
                Dispensing
            </a>
        </nav>
    </div>
</header>

<main class="container page-content">

    <p class="breadcrumb">
        <a href="index.php">Home</a> / Dispensing
    </p>

    <h1>Dispensing Dashboard</h1>

    <p class="page-description">
        Record complete medication fills, update pickup
        status, and manage payments using existing PHP classes.
    </p>

    <!-- Messages -->

    <?php if ($success !== null): ?>
        <p class="message message-success" role="status">
            <?= dispensingHtml($success) ?>
        </p>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="message message-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= dispensingHtml($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Section 1: Record dispensing -->

    <section class="panel">

        <h2>Record New Dispensing</h2>

        <p class="muted">
            Complete fills only. Refill number 0 is the
            original fill. Recording a fill decreases stock.
        </p>

        <?php if ($itemPreview !== null): ?>
            <p class="notice">
                Selected Item #<?= dispensingHtml(
                    $itemPreview->getItemId()
                ) ?>

                — Prescribed Quantity:
                <?= dispensingHtml(
                    $itemPreview->getQuantity()
                ) ?>

                — Refills Allowed:
                <?= dispensingHtml(
                    $itemPreview->getRefillsAllowed()
                ) ?>
            </p>
        <?php endif; ?>

        <form method="post" action="dispensings.php">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= dispensingHtml(
                       $_SESSION['dispensing_csrf']
                   ) ?>">

            <input type="hidden"
                   name="action"
                   value="record">

            <div class="form-grid">

                <div class="form-field">
                    <label for="item_id">
                        Prescription Item ID *
                    </label>

                    <input type="number"
                           id="item_id"
                           name="item_id"
                           min="1"
                           required
                           value="<?= dispensingHtml(
                               $values['item_id']
                           ) ?>">
                </div>

                <div class="form-field">
                    <label for="quantity_dispensed">
                        Quantity Dispensed *
                    </label>

                    <input type="number"
                           id="quantity_dispensed"
                           name="quantity_dispensed"
                           min="1"
                           required
                           value="<?= dispensingHtml(
                               $values['quantity_dispensed']
                           ) ?>">
                </div>

                <div class="form-field">
                    <label for="refill_number">
                        Refill Number *
                    </label>

                    <input type="number"
                           id="refill_number"
                           name="refill_number"
                           min="0"
                           required
                           value="<?= dispensingHtml(
                               $values['refill_number']
                           ) ?>">
                </div>

                <div class="form-field">
                    <label for="dispense_date">
                        Dispensing Date
                    </label>

                    <input type="date"
                           id="dispense_date"
                           name="dispense_date"
                           value="<?= dispensingHtml(
                               $values['dispense_date']
                           ) ?>">
                </div>

                <div class="form-field">
                    <label for="total_amount">
                        Total Amount (Optional)
                    </label>

                    <input type="text"
                           id="total_amount"
                           name="total_amount"
                           inputmode="decimal"
                           placeholder="12.50"
                           maxlength="11"
                           value="<?= dispensingHtml(
                               $values['total_amount']
                           ) ?>">
                </div>

            </div>

            <div class="form-actions">

                <button type="submit"
                        class="button button-primary">
                    Record Dispensing
                </button>

                <a href="dispensings.php"
                   class="button button-light">
                    Clear
                </a>

            </div>

        </form>

    </section>

    <!-- Section 2: Find dispensing -->

    <section class="panel">

        <h2>Find Dispensing</h2>

        <form method="get"
              action="dispensings.php"
              class="search-form">

            <div class="form-field">
                <label for="dispensing_id">
                    Dispensing ID
                </label>

                <input type="number"
                       id="dispensing_id"
                       name="id"
                       min="1"
                       required
                       value="<?= dispensingHtml(
                           $selectedId ?? ''
                       ) ?>">
            </div>

            <button type="submit"
                    class="button button-primary">
                Find Dispensing
            </button>

            <a href="dispensings.php"
               class="button button-light">
                Clear
            </a>
        </form>

    </section>

    <!-- Section 3: Selected dispensing -->

    <?php if ($dispensing !== null): ?>

        <section class="panel">

            <h2>Dispensing Details</h2>

            <dl class="details-list">

                <div>
                    <dt>Dispensing ID</dt>
                    <dd><?= dispensingHtml(
                        $dispensing->getDispensingId()
                    ) ?></dd>
                </div>

                <div>
                    <dt>Prescription Item</dt>

                    <dd>
                        <a href="prescription_items.php?id=<?= dispensingHtml(
                            $dispensing->getItemId()
                        ) ?>">
                            #<?= dispensingHtml(
                                $dispensing->getItemId()
                            ) ?>
                        </a>
                    </dd>
                </div>

                <div>
                    <dt>Patient</dt>
                    <dd><?= dispensingHtml(
                        $details['patient_name'] ?? ''
                    ) ?></dd>
                </div>

                <div>
                    <dt>Doctor</dt>
                    <dd><?= dispensingHtml(
                        $details['doctor_name'] ?? ''
                    ) ?></dd>
                </div>

                <div>
                    <dt>Medication</dt>
                    <dd><?= dispensingHtml(
                        $details['medication_name'] ?? ''
                    ) ?></dd>
                </div>

                <div>
                    <dt>Quantity Dispensed</dt>
                    <dd><?= dispensingHtml(
                        $dispensing->getQuantityDispensed()
                    ) ?></dd>
                </div>

                <div>
                    <dt>Refill Number</dt>
                    <dd><?= dispensingHtml(
                        $dispensing->getRefillNumber()
                    ) ?></dd>
                </div>

                <div>
                    <dt>Dispensing Date</dt>
                    <dd><?= dispensingHtml(
                        $dispensing->getDispenseDate() ?? '-'
                    ) ?></dd>
                </div>

                <div>
                    <dt>Pickup Status</dt>
                    <dd><?= dispensingHtml(
                        $dispensing->getPickupStatus() ?? '-'
                    ) ?></dd>
                </div>

                <div>
                    <dt>Pickup Date</dt>
                    <dd><?= dispensingHtml(
                        $dispensing->getPickupDate() ?? '-'
                    ) ?></dd>
                </div>

                <div>
                    <dt>Total Amount</dt>
                    <dd><?= dispensingHtml(
                        $dispensing->getTotalAmount() ?? '-'
                    ) ?></dd>
                </div>

                <div>
                    <dt>Amount Paid</dt>
                    <dd><?= dispensingHtml(
                        $dispensing->getAmountPaid() ?? '-'
                    ) ?></dd>
                </div>

                <div>
                    <dt>Payment Status</dt>
                    <dd><?= dispensingHtml(
                        $dispensing->getPaymentStatus() ?? '-'
                    ) ?></dd>
                </div>

                <div>
                    <dt>Payment Date</dt>
                    <dd><?= dispensingHtml(
                        $dispensing->getPaymentDate() ?? '-'
                    ) ?></dd>
                </div>

                <div>
                    <dt>Payment Method</dt>
                    <dd><?= dispensingHtml(
                        $dispensing->getPaymentMethod() ?? '-'
                    ) ?></dd>
                </div>

            </dl>

        </section>

        <!-- Section 4: Update pickup -->

        <?php if ($dispensing->getPickupStatus() !== 'Picked Up'): ?>

            <section class="panel">

                <h2>Mark Medication as Picked Up</h2>

                <form method="post" action="dispensings.php">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= dispensingHtml(
                               $_SESSION['dispensing_csrf']
                           ) ?>">

                    <input type="hidden"
                           name="action"
                           value="pickup">

                    <input type="hidden"
                           name="dispensing_id"
                           value="<?= dispensingHtml(
                               $dispensing->getDispensingId()
                           ) ?>">

                    <div class="form-grid">

                        <div class="form-field">
                            <label for="pickup_date">
                                Pickup Date
                            </label>

                            <input type="date"
                                   id="pickup_date"
                                   name="pickup_date"
                                   value="<?= date('Y-m-d') ?>">
                        </div>

                    </div>

                    <button type="submit"
                            class="button button-primary">
                        Mark Picked Up
                    </button>

                </form>

            </section>

        <?php endif; ?>

        <!-- Section 5: Record payment -->

        <?php if ($dispensing->getTotalAmount() !== null &&
                  $dispensing->getPaymentStatus() !== 'Paid'): ?>

            <section class="panel">

                <h2>Record Payment</h2>

                <p class="muted">
                    Payments must not exceed the total amount.
                </p>

                <form method="post" action="dispensings.php">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= dispensingHtml(
                               $_SESSION['dispensing_csrf']
                           ) ?>">

                    <input type="hidden"
                           name="action"
                           value="payment">

                    <input type="hidden"
                           name="dispensing_id"
                           value="<?= dispensingHtml(
                               $dispensing->getDispensingId()
                           ) ?>">

                    <div class="form-grid">

                        <div class="form-field">
                            <label for="payment_amount">
                                Payment Amount *
                            </label>

                            <input type="text"
                                   id="payment_amount"
                                   name="payment_amount"
                                   inputmode="decimal"
                                   maxlength="11"
                                   placeholder="5.00"
                                   required>
                        </div>

                        <div class="form-field">
                            <label for="payment_method">
                                Payment Method *
                            </label>

                            <input type="text"
                                   id="payment_method"
                                   name="payment_method"
                                   maxlength="20"
                                   placeholder="Cash"
                                   required>
                        </div>

                    </div>

                    <button type="submit"
                            class="button button-primary">
                        Record Payment
                    </button>

                </form>

            </section>

        <?php endif; ?>

    <?php endif; ?>

</main>

<footer class="site-footer">
    <div class="container">
        CIS 344 &middot; Fictional classroom data only
    </div>
</footer>

</body>
</html>

