# Pharmacy Prescription Management System (PPMS)

> A **PHP and MySQL web application** developed for **CIS 344: Database Design and Programming** at Lehman College.

## Project Overview

The **Pharmacy Prescription Management System (PPMS)** is a database-driven application designed to manage patient information, doctors, medications, prescriptions, and dispensing records.

The system integrates a **relational MySQL database**, a **PHP Object-Oriented Programming (OOP) backend**, and an **HTML/CSS web dashboard** to organize pharmacy operations.

## Technologies Used

| Component | Technology |
|-----------|------------|
| **Database** | MySQL, MySQL Workbench |
| **Backend** | PHP (OOP), PDO |
| **Frontend** | HTML, CSS |
| **Local Server** | XAMPP (Apache, MySQL) |

> **Note:** No JavaScript was used in the implementation.

## Key Features

- **Patient Management:** Register, search, update, and view patient records and prescription history.
- **Doctor Management:** Manage doctor information and associated prescriptions.
- **Medication Management:** Register medications, track inventory, and update stock.
- **Prescription Management:** Create prescriptions linking patients and doctors.
- **Prescription Item Management:** Add medications with quantities, dosage instructions, duration, and refill limits.
- **Dispensing Management:** Record medication fills, manage refills, update inventory, and track pickups and payments.

## Database Design

The relational database, **`pharmacy_management_refined`**, consists of six interconnected tables:

- `patients`
- `doctors`
- `medication`
- `prescriptions`
- `prescription_items`
- `dispensings`

Each table uses a **Primary Key (PK)** for unique identification, while **Foreign Keys (FK)** establish and maintain relationships between records.

**Key Relationships:**
- Prescriptions reference patients and doctors.
- Prescription items reference medications and prescriptions.
- Dispensing records reference prescription items.

The database structure and relationships are illustrated using an **Enhanced Entity-Relationship (EER) diagram** created in MySQL Workbench.

## Repository Structure

```text
Pharmacy-Prescription-Management-System/
├── database/                    # Database materials
├── pharm_app/                   # Earlier web interface
├── pharmacy-prescription-mgmt/
│   ├── app/                     # PHP OOP classes
│   ├── config/
│   │   └── config.example.php   # Database settings template
│   ├── public/
│   │   ├── index.php            # Main dashboard
│   │   ├── patients.php
│   │   ├── doctors.php
│   │   ├── medications.php
│   │   ├── prescriptions.php
│   │   ├── prescription_items.php
│   │   ├── dispensings.php
│   │   └── css/style.css        # Frontend styling
│   └── tests/                   # PHP testing scripts
└── README.md
```

## Installation and Setup

### 1. Install Required Software

Install the following tools:

- **XAMPP** — Local Apache and MySQL server
- **MySQL Workbench** — Database management and SQL execution
- **Git** — Repository cloning

### 2. Clone the Repository

Navigate to your XAMPP `htdocs` directory:

```bash
cd C:\xampp\htdocs
```

Clone the GitHub repository:

```bash
git clone https://github.com/emmaubah/Pharmacy-Prescription-Management-System.git
```

### 3. Set Up the Database

1. Open **MySQL Workbench**.
2. Locate and open the project's SQL script in the `database/` directory.
3. Execute the script to create the database and tables.
4. Verify that **`pharmacy_management_refined`** was created successfully.

### 4. Configure the Database Connection

Navigate to:

```text
pharmacy-prescription-mgmt/config/
```

Copy `config.example.php` to `config.local.php`.

Update the database configuration with your local MySQL credentials:

- **Host:** `localhost`
- **Port:** `3306`
- **Database:** `pharmacy_management_refined`
- **Username:** Your MySQL username
- **Password:** Your MySQL password

### 5. Start the Local Server

Open the **XAMPP Control Panel** and start:

- **Apache**
- **MySQL**

### 6. Launch the Application

Open your browser and navigate to:

```text
http://localhost/Pharmacy-Prescription-Management-System/pharmacy-prescription-mgmt/public/index.php
```

## How to Use

1. **Open the Dashboard:** Access the main application dashboard through `index.php`.
2. **Manage Records:** Register and update patients, doctors, and medications.
3. **Create Prescriptions:** Associate prescriptions with existing patients and doctors.
4. **Add Prescription Items:** Specify medications, quantities, dosages, and refill limits.
5. **Manage Dispensing:** Record medication fills, pickups, payments, and inventory updates.

> **Important:** PPMS is an educational prototype intended for fictional data. User authentication and access controls are not implemented.

## Team Members

**Project Contributors:**

- **Emmanuel Ubah**
- **Ruben Gudino**
- **Edith Oblitas**

## Course Information

| Detail | Information |
|--------|-------------|
| **Course** | CIS 344 — Database Design and Programming |
| **Institution** | Lehman College, City University of New York (CUNY) |
| **Semester** | Fall 2026 |

---

