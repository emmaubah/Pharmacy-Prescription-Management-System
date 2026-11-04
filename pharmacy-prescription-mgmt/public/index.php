<?php
declare(strict_types=1);

// Main Pharmacy Management dashboard.
$sections = [
    [
        'name' => 'Patients',
        'description' => 'Register, find and edit patients.',
        'url' => 'patients.php'
    ],
    [
        'name' => 'Doctors',
        'description' => 'Manage doctors and contact information.',
        'url' => 'doctors.php'
    ],
    [
        'name' => 'Medications',
        'description' => 'Register medications and manage stock.',
        'url' => 'medications.php'
    ],
    [
        'name' => 'Prescriptions',
        'description' => 'Register prescriptions and view details.',
        'url' => 'prescriptions.php'
    ],
    [
        'name' => 'Prescription Items',
        'description' => 'Add medications to prescriptions and view items.',
        'url' => 'prescription_items.php'
    ],
    [
        'name' => 'Dispensing',
        'description' => 'Record fills, pickups and payments.',
        'url' => 'dispensings.php'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Pharmacy Prescription Management System</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header class="site-header">

    <div class="container header-content">

        <a class="brand" href="index.php">
            Pharmacy Prescription Management System
        </a>

        <nav aria-label="Main navigation">

            <a class="active"
               href="index.php"
               aria-current="page">Home</a>

            <a href="patients.php">Patients</a>

            <a href="doctors.php">Doctors</a>

            <a href="medications.php">Medications</a>

            <a href="prescriptions.php">Prescriptions</a>

            <a href="prescription_items.php">
                Prescription Items
            </a>

            <a href="dispensings.php">Dispensing</a>

        </nav>

    </div>

</header>

<main class="container">

    <section class="hero">

        <p class="eyebrow">CIS 344 Group Project</p>

        <h1>
            Pharmacy Prescription Management System
        </h1>

        <p>
            Manage patients, doctors, medications,
            prescriptions, prescription items, and dispensing
            records using PHP Object-Oriented Programming.
        </p>

        <a class="button button-primary"
           href="prescriptions.php">
            Open Prescription Dashboard
        </a>

    </section>

    <section aria-labelledby="sections-title">

        <h2 id="sections-title">
            Application Sections
        </h2>

        <div class="card-grid">

            <?php foreach ($sections as $section): ?>

                <article class="card">

                    <h3>
                        <?= htmlspecialchars(
                            $section['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h3>

                    <p>
                        <?= htmlspecialchars(
                            $section['description'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                    <a class="text-link"
                       href="<?= htmlspecialchars(
                           $section['url'],
                           ENT_QUOTES,
                           'UTF-8'
                       ) ?>">
                        Open Section
                    </a>

                </article>

            <?php endforeach; ?>

        </div>

    </section>

    <p class="notice">
        CIS 344 classroom prototype.
        Use fictional data only.
        Authentication and access controls are not implemented.
    </p>

</main>

<footer class="site-footer">

    <div class="container">
        CIS 344 &middot; PHP, HTML, CSS and MySQL
    </div>

</footer>

</body>
</html>

