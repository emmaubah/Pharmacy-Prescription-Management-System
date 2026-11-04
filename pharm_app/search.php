<?php require 'includes/header.php'; ?>

<h2>Search</h2>
<p> Find a patient's prescriptions by last name.</p>
<form action="search_results.php" method="post">
    <table>
        <tr>
            <th colspan="2">Search Prescriptions</th>
</tr>
<tr>
    <td>Patient Last Name *</td>
    <td><input type="text" name="last_name" size="25" maxlength="50"></td>
</tr>
<tr>
    <td colspan="2"><input type ="submit" value = "Search"></td>
</tr>
</table>
</form>

<p>* Required</p>
<?php require 'includes/footer.php'; ?>



