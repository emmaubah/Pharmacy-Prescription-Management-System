<?php require 'includes/header.php'; ?>

<h2>Dispense</h2>
<p>Record when a medication is filled, picked up, and paid for.</p>
<form action="add_dispensing.php" method="post">
<table>
    <tr>
        <th colspan="2">Fill</th>
    </tr>
    <tr>
        <td>Item # (from the Prescriptions page) *</td>
        <td><input type="text" name="item_id" size="6" maxlength="6"></td>
    </tr>
    <tr>
        <td>Dispense Date (YYYY-MM-DD)</td>
        <td><input type="text" name="dispense_date" size="10" maxlength="10"></td>
    </tr>
    <tr>
        <td>Refill Number (0 = first fill)</td>
        <td><input type="text" name="refill_number" size="3" maxlength="3"></td>
    </tr>
    <tr>
        <td>Quantity Dispensed *</td>
        <td><input type="text" name="quantity_dispensed" size="6" maxlength="6"></td>
    </tr>
    <tr>
        <th colspan="2">Pickup</th>
    </tr>
    <tr>
        <td>Pickup Status (Ready for Pickup or Picked Up)</td>
        <td><input type="text" name="pickup_status" size="20" maxlength="20"></td>
    </tr>
    <tr>
        <td>Pickup Date (YYYY-MM-DD)</td>
        <td><input type="text" name="pickup_date" size="10" maxlength="10"></td>
    </tr>
    <tr>
        <th colspan="2">Payment</th>
    </tr>
    <tr>
        <td>Total Amount ($)</td>
        <td><input type="text" name="total_amount" size="10" maxlength="10"></td>
    </tr>
    <tr>
        <td>Amount Paid ($)</td>
        <td><input type="text" name="amount_paid" size="10" maxlength="10"></td>
    </tr>
    <tr>
        <td>Payment Status (Unpaid or Paid)</td>
        <td><input type="text" name="payment_status" size="20" maxlength="20"></td>
    </tr>
    <tr>
        <td>Payment Date (YYYY-MM-DD)</td>
        <td><input type="text" name="payment_date" size="10" maxlength="19"></td>
    </tr>
    <tr>
        <td>Payment Method (Cash, Card, or Insurance)</td>
        <td><input type="text" name="payment_method" size="20" maxlength="20"></td>
    </tr>
    <tr>
    <td colspan="2"><input type="submit" value="Save Dispensing"></td>
    </tr>
</table>
</form>

<p>* Required</p>

<?php require 'includes/footer.php'; ?>