<?php
session_start();
require_once("config.php");

$success = '';
$error = '';

if(isset($_POST['register'])){
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = sanitize($_POST['phone']);
    $ward_id = !empty($_POST['ward_id']) ? intval($_POST['ward_id']) : null;
    
    // Validation
    if(empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif(strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } elseif($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif(!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Please enter a valid 10-digit phone number";
    } else {
        // Check if email already exists using prepared statement
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        
        if($check->num_rows > 0){
            $error = "Email already registered! Please use a different email or login.";
        } else {
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $role = 'complainant'; // Default role for registration
            
            // Insert user with prepared statement
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, phone, ward_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $name, $email, $passwordHash, $role, $phone, $ward_id);
            
            if($stmt->execute()){
                $success = "Registration successful! Redirecting to login...";
                header("refresh:2;url=login.php");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Fetch wards for dropdown
$wards = $conn->query("SELECT area_id, area_name FROM area_master WHERE area_type = 'ward' AND is_active = 1 ORDER BY area_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | Citizen Complaint Portal</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    
    <div class="login-container">
        <div class="login-wrapper">
            <div class="glass-card login-card fade-in">
                <div class="login-logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1 class="login-title">Create Account</h1>
                <p class="login-subtitle">Join the Citizen Complaint Portal</p>
                
                <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registerForm">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="name" id="name" class="form-control" placeholder="Enter your full name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
                        </div>
                        <small style="color: var(--primary-400);">This will be your login username</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-phone input-icon"></i>
                            <input type="tel" name="phone" id="phone" class="form-control" placeholder="10-digit mobile number" maxlength="10" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Your Ward (Optional)</label>
                        <select name="ward_id" class="form-control">
                            <option value="">Select your ward</option>
                            <?php while($ward = $wards->fetch_assoc()): ?>
                            <option value="<?php echo $ward['area_id']; ?>"><?php echo htmlspecialchars($ward['area_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small style="color: var(--primary-400);">Used to save your default location filter</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Create a password (min 6 chars)" minlength="6" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm your password" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="register" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </form>
                
                <script>
                // Client-side validation
                document.getElementById('registerForm').addEventListener('submit', function(e) {
                    const phone = document.getElementById('phone').value;
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    const email = document.getElementById('email').value;
                    
                    // Phone validation
                    if(!/^[0-9]{10}$/.test(phone)) {
                        alert('Please enter a valid 10-digit phone number');
                        e.preventDefault();
                        return false;
                    }
                    
                    // Email validation
                    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        alert('Please enter a valid email address');
                        e.preventDefault();
                        return false;
                    }
                    
                    // Password match validation
                    if(password !== confirmPassword) {
                        alert('Passwords do not match!');
                        e.preventDefault();
                        return false;
                    }
                    
                    // Password length validation
                    if(password.length < 6) {
                        alert('Password must be at least 6 characters long');
                        e.preventDefault();
                        return false;
                    }
                    
                    return true;
                });
                </script>
                
                <div class="login-footer">
                    <p>Already have an account? <a href="login.php">Sign in</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>