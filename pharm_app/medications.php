<?php require 'includes/header.php'; ?>

<h2>Medications</h2>

<form action="add_medication.php" method="post">
<table>
    <tr>
        <th colspan="2">Add a New Medication</th>
</tr>
    <tr>
        <td>Medication Name *</td>
        <td><input type="text" name="medication_name" size="30" maxlength="100"></td>
    </tr>
    <tr>
        <td>Strength (example: 500 mg)</td>
        <td><input type="text" name="strength" size="15" maxlength="50"></td>
    </tr>
    <tr>
        <td>Form (example: Capsule)</td>
        <td><input type="text" name="form" size="15" maxlength="50"></td>
    </tr>
    <tr>
        <td>Stock Quantity</td>
        <td><input type="text" name="stock_quantity" size="6" maxlength="6"></td>
    </tr>
    <tr>
        <td colspan="2"><input type="submit" value="Add Medication"></td>
</tr>
</table>
</form>

<p>* Required</p>
<h3>All Medications</h3>

<?php require 'includes/footer.php'; ?>