<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Registration Form</title>
    <link rel="stylesheet" href="style/registrationform.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="regform">
        <form action="req.php" method="post" enctype="multipart/form-data">
            <h2>Seller Registration Details</h2>
            First Name<br>
            <input type="text" name="firstname" required>
            <br><br>
            Middle Name<br>
            <input type="text" name="middlename" required>
            <br><br>
            Last Name<br>
            <input type="text" name="lastname" required>
            <br><br>
            Birthdate<br>
            <input type="date" name="bdate" required>
            <br><br>
            Contact Number<br>
            <input type="text" name="contact" required>
            <br><br>
            Email Address<br>
            <input type="text" name="email" required>
            <br><br>
            RSBSA Number<br>
            <input type="text" name="rsbsanum" required>
            <br><br>

            <!-- New address fields -->
            Address<br>
            <input type="text" name="address" required>
            <br><br>
            Barangay<br>
            <input type="text" name="barangay" required>
            <br><br>
            Municipality<br>
            <input type="text" name="municipality" required>
            <br><br>
            Province<br>
            <input type="text" name="province" required>
            <br><br>

            Valid ID<br>
            <input type="file" name="valid_id" accept="image/*" required>
            <br><br>
            Valid ID Number<br>
            <input type="text" name="idnum" required>
            <br><br>
            <br><br>
            <div id="acc">Login Credentials<br>
                Username<br>
                <input type="text" name="username" required>
                <br><br>
                Password<br>
                <input type="password" name="password" required>
                <br>
            </div>
            <br><br>
            <button type="submit" name="next" value="next">Proceed</button>
            <br>
        </form>
    </div>
</body>
</html>

<?php
    if (isset($_POST["next"])){

            $_SESSION["firstname"] = $_POST["firstname"];
            $_SESSION["middlename"] = $_POST["middlename"];
            $_SESSION["lastname"] = $_POST["lastname"];
            $_SESSION["bdate"] = $_POST["bdate"];
            $_SESSION["contact"] = $_POST["contact"];
            $_SESSION["email"] = $_POST["email"];
            $_SESSION["rsbsanum"] = $_POST["rsbsanum"];
            $_SESSION["idnum"] = $_POST["idnum"];
            $_SESSION["username"] = $_POST["username"];
            $_SESSION["password"] = $_POST["password"];
            $_SESSION["address"] = $_POST["address"];
            $_SESSION["barangay"] = $_POST["barangay"];
            $_SESSION["municipality"] = $_POST["municipality"];
            $_SESSION["province"] = $_POST["province"];

            //img need to upload to supabase function

            header("Location: confirm.php");
    }
?>