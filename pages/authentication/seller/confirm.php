<?php
session_start();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Registration Confirmation</title>
    <link rel="stylesheet" href="style/confirm.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
   <div class="regform">
        <form action="conreq.php" method="post">
            <h2>Seller Registration Confirmation</h2>
            
            <div class="form-group">
                <label for="firstname">First Name</label>
                <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($_SESSION['firstname']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="middlename">Middle Name</label>
                <input type="text" id="middlename" name="middlename" value="<?php echo htmlspecialchars($_SESSION['middlename']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="lastname">Last Name</label>
                <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($_SESSION['lastname']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="bdate">Birthdate</label>
                <input type="date" id="bdate" name="bdate" value="<?php echo htmlspecialchars($_SESSION['bdate']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="contact">Contact Number</label>
                <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($_SESSION['contact']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="rsbsanum">RSBSA Number</label>
                <input type="text" id="rsbsanum" name="rsbsanum" value="<?php echo htmlspecialchars($_SESSION['rsbsanum']); ?>" readonly>
            </div>

            <!-- New address fields display -->
            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($_SESSION['address'] ?? ''); ?>" readonly>
            </div>

            <div class="form-group">
                <label for="barangay">Barangay</label>
                <input type="text" id="barangay" name="barangay" value="<?php echo htmlspecialchars($_SESSION['barangay'] ?? ''); ?>" readonly>
            </div>

            <div class="form-group">
                <label for="municipality">Municipality</label>
                <input type="text" id="municipality" name="municipality" value="<?php echo htmlspecialchars($_SESSION['municipality'] ?? ''); ?>" readonly>
            </div>

            <div class="form-group">
                <label for="province">Province</label>
                <input type="text" id="province" name="province" value="<?php echo htmlspecialchars($_SESSION['province'] ?? ''); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="valid_id">Valid ID</label>
                <?php
                    if (!empty($_SESSION['docs_path'])) {
                        echo "<div class='image-preview'>";
                        echo "<img src='" . $_SESSION['docs_path'] . "' alt='Valid ID' class='uploaded-image'>";
                        echo "<p class='file-info'>Uploaded file: " . basename($_SESSION['docs_path']) . "</p>";
                        echo "</div>";
                    } else {
                        echo "<div class='no-image'>No image uploaded.</div>";
                    }
                    
                    // Show upload error if any
                    if (!empty($_SESSION['upload_error'])) {
                        echo "<div class='error-message'>" . htmlspecialchars($_SESSION['upload_error']) . "</div>";
                        unset($_SESSION['upload_error']);
                    }
                ?>
            </div>
            
            <div class="form-group">
                <label for="idnum">Valid ID Number</label>
                <input type="text" id="idnum" name="idnum" value="<?php echo htmlspecialchars($_SESSION['idnum']); ?>" readonly>
            </div>
            
            <div id="acc">
                <h3>Login Credentials</h3>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="text" id="password" name="password" value="<?php echo htmlspecialchars($_SESSION['password']); ?>" readonly>
                </div>
            </div>
            
            <button type="submit" name="proceed" value="proceed">Submit Registration</button>
        </form>
    </div>
</body>
</html>

<?php
    if (isset($_POST["proceed"])){
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
        echo "<h3>Debug Information:</h3>";
        echo "Form submitted successfully!<br>";
        echo "Session data: <pre>" . print_r($_SESSION, true) . "</pre>";
        
        require_once dirname(__DIR__, 3) . '/config/RegistrationHandler.php';
        require_once dirname(__DIR__, 3) . '/config/UsernameChecker.php';
        
        $registrationHandler = new RegistrationHandler();
        $usernameChecker = new UsernameChecker();
        
        // Check if username already exists across all user tables
        if ($usernameChecker->usernameExists($_SESSION['username'])) {
            echo "<script>alert('Username already exists. Please choose a different username.'); window.history.back();</script>";
            exit;
        }
        
        // Check if email already exists across all user tables
        if ($usernameChecker->emailExists($_SESSION['email'])) {
            echo "<script>alert('Email already exists. Please use a different email.'); window.history.back();</script>";
            exit;
        }
        
        $data = [
            'user_fname' => $_SESSION['firstname'],
            'user_mname' => $_SESSION['middlename'],
            'user_lname' => $_SESSION['lastname'],
            'bdate' => $_SESSION['bdate'],
            'contact' => $_SESSION['contact'],
            'email' => $_SESSION['email'],
            'rsbsanum' => $_SESSION['rsbsanum'],
            'idnum' => $_SESSION['idnum'],
            'address' => $_SESSION['address'] ?? '',
            'barangay' => $_SESSION['barangay'] ?? '',
            'municipality' => $_SESSION['municipality'] ?? '',
            'province' => $_SESSION['province'] ?? '',
            'username' => $_SESSION['username'],
            'password' => $_SESSION['password'],
            'docs_path' => $_SESSION['docs_path'] ?? ''
        ];
        
        echo "Data to be inserted: <pre>" . print_r($data, true) . "</pre>";

        // Register seller in database
        $result = $registrationHandler->registerSeller($data);
        echo "Registration result: " . ($result ? "SUCCESS" : "FAILED") . "<br>";
        
        if ($result) {
            echo "<script>alert('Registration successful! You can now login.'); window.location.href='../login.php';</script>";
            // Clear session data
            session_destroy();
        } else {
            echo "<script>alert('Registration failed. Please try again.'); window.history.back();</script>";
        }
        echo "</div>";
    }
?>