<?php require 'includes/header.php'; ?>
<h2>Doctors</h2>

<form action="add_doctor.php" method="post">
<table>
    <tr>
        <th colspan= "2"> Add a New Doctor</th>
</tr>
<tr>
    <td>First Name *</td>
    <td><input type="text" name="first_name" size= "25" maxlength="50"></td>
</tr>
<tr>
    <td>Last Name *</td>
    <td><input type="text" name="last_name" size= "25" maxlength="50"></td>
</tr>
<tr>
    <td>Speciality</td>
    <td><input type="text" name="speciality" size= "25" maxlength="50"></td>
</tr>
<tr>
    <td>Phone</td>
    <td><input type="text" name="phone" size= "15" maxlength="20"></td>
</tr>
<tr>
    <td>Email</td>
    <td><input type="text" name="email" size= "30" maxlength="100"></td>
</tr>
<tr>
    <td colspan="2"><input type="submit" value="Add Doctor"></td>
</tr>
</table>
</form>

<p>* Required</p>
<h3>All Doctors</h3>

<?php require 'includes/footer.php'; ?>