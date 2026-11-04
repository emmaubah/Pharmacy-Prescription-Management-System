<?php
declare(strict_types=1);

// Start a PHP session for secure form tokens.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Safely display information inside HTML.
function patientHtml(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Create or retrieve a CSRF token.
function patientCsrfToken(): string
{
    if (!isset($_SESSION['patient_csrf_token'])) {

        $_SESSION['patient_csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['patient_csrf_token'];
}

// Check a submitted CSRF token.
function patientCsrfValid(mixed $submitted): bool
{
    return is_string($submitted)
        && isset($_SESSION['patient_csrf_token'])
        && hash_equals(
            $_SESSION['patient_csrf_token'],
            $submitted
        );
}

// Validate patient IDs from a URL or form.
function patientValidId(mixed $rawId): ?int
{
    if (!is_string($rawId) && !is_int($rawId)) {
        return null;
    }

    $id = filter_var(
        $rawId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    return $id === false ? null : (int) $id;
}

// Retrieve and clean submitted patient fields.
function patientFormValues(array $source): array
{
    $fields = [
        'first_name',
        'last_name',
        'date_of_birth',
        'phone'
    ];

    $values = [];

    foreach ($fields as $field) {

        $raw = $source[$field] ?? '';

        $values[$field] = is_string($raw)
            ? trim($raw)
            : '';
    }

    return $values;
}

// Check submitted patient information.
function patientFormErrors(array $values): array
{
    $errors = [];

    if ($values['first_name'] === '') {
        $errors[] = 'First name is required.';
    }

    if ($values['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }

    if (strlen($values['first_name']) > 50) {
        $errors[] = 'First name is too long.';
    }

    if (strlen($values['last_name']) > 50) {
        $errors[] = 'Last name is too long.';
    }

    foreach (['first_name', 'last_name'] as $field) {
        if (preg_match('/[\x00-\x1F\x7F]/', $values[$field])) {
            $errors[] = 'Name contains invalid characters.';
            break;
        }
    }

    if (strlen($values['phone']) > 20) {
        $errors[] = 'Phone must be 20 characters or fewer.';
    }

    if ($values['date_of_birth'] !== '') {

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $values['date_of_birth']
        );

        if ($date === false ||
            $date->format('Y-m-d') !== $values['date_of_birth']) {

            $errors[] = 'Please enter a valid date of birth.';

        } elseif ($values['date_of_birth'] > date('Y-m-d')) {

            $errors[] = 'Date of birth cannot be in the future.';
        }
    }

    return $errors;
}

