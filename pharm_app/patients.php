<?php require 'includes/header.php'; ?>
<h2>Patients</h2>
<!-- will add a new patients. it will send the data to add_patients.php--> 
 <form action="add_patient.php" method="post">
    <table>
        <tr>
            <th colspan="2">Add a New Patient</th>
</tr>
<tr>
    <td>First Name *</td>
    <td><input type ="text" name="first_name" size"25" maxlength="50"></td>
</tr>
<tr>
    <td>Last Name *</td>
    <td><input type ="text" name="last_name" size"25" maxlength="50"></td>
</tr>
<tr>
    <td>Date of Birth (YYYY-MM-DD)</td>
    <td><input type="text" name="date_of_birth" size="10" maxlength="10"></td>
    </tr>
<tr>
    <td>Phone</td>
    <td><input type="text" name="phone" size="15" maxlength="20"></td>
     </tr>
<tr>
    <td colspan ="2"><input type="submit" value="Add Patient"></td>
</tr>
</table>
</form>

<p>* Required</p>
<h3>All Patients</h3>

<?php require 'includes/footer.php';?>