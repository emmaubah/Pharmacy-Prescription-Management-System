<?php require 'includes/header.php'; ?>

<h2>New Prescription</h2>

<form action="add_prescription.php" method="post">
<table>
    <tr>
        <th colspan="2">Prescription</th>
    </tr>
    <tr>
        <td>Patient ID *</td>
        <td><input type="text" name="patient_id" size="6" maxlength="6"></td>
    </tr>
    <tr>
        <td>Doctor ID *</td>
        <td><input type="text" name="doctor_id" size="6" maxlength="6"></td>
</tr>
<tr>
    <td>Date (YYYY-MM-DD) *</td>
        <td><input type="text" name="prescription_date" size="10" maxlength="10"></td>
    </tr>
    <tr>
        <th colspan="2">Medication</th>
    </tr>
    <tr>
    <td>Medication ID *</td>
    <td><input type="text" name="medication_id" size="6" maxlength="6"></td>
    </tr>
    <tr>
        <td>Quantity *</td>
        <td><input type="text" name="quantity" size="6" maxlength="6"></td>
</tr>
<tr>
    <td>Dosage (example: 1 capsule) *</td>
    <td><input type="text" name="dosage" size="20" maxlength="50"></td>
    </tr>
    <tr>
        <td>Frequency (example: 3 times daily) *</td>
        <td><input type="text" name="frequency" size="20" maxlength="50"></td>
    </tr>
    <tr>
        <td>Duration in Days *</td>
        <td><input type="text" name="duration_days" size="4" maxlength="4"></td>
    </tr>
    <tr>
        <td>Refills Allowed</td>
        <td><input type="text" name="refills_allowed" size="3" maxlength="3"></td>
    </tr>
    <tr>
    <td colspan="2"><input type="submit" value="Save Prescription"></td>
</tr>
</table>
</form>

<p>* Required</p>

<?php require 'includes/footer.php'; ?>