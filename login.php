<?php
session_start();
require_once("config.php");

// Auto-escalate complaints past SLA
autoEscalateComplaints();

$error = '';

if(isset($_POST['login'])){
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $role = sanitize($_POST['role']);
    $remember = isset($_POST['remember']) ? true : false;

    // Validate inputs
    if(empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT user_id, name, email, password_hash, role, is_active FROM users WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows == 1){
            $user = $result->fetch_assoc();
            
            // Check if account is active
            if($user['is_active'] != 1) {
                $error = "Your account has been deactivated. Please contact the administrator.";
            } elseif(password_verify($password, $user['password_hash'])){
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                
                // Set remember me cookie if requested
                if($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                }
                
                $stmt->close();
                
                // Redirect based on role
                if($user['role'] === 'supervisor') {
                    header("Location: admin.php");
                } elseif($user['role'] === 'staff') {
                    header("Location: staff.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Complaint Management System</title>
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
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="login-title">Complaint Portal</h1>
                <p class="login-subtitle">Secure access to complaint management</p>
                
                <?php if(!empty($error)): ?>
                <div class="alert alert-error" style="background: rgba(220, 38, 38, 0.2); border: 1px solid rgba(220, 38, 38, 0.5); color: #fca5a5;">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Account Type</label>
                        <select name="role" class="form-control" required>
                            <option value="">Select your role</option>
                            <option value="complainant">👤 Citizen / Complainant</option>
                            <option value="staff">🔧 Field Staff</option>
                            <option value="supervisor">🛡️ Admin / Supervisor</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="remember" id="remember" value="1" style="width: auto;">
                        <label for="remember" style="margin: 0; font-weight: normal; color: var(--primary-300);">Remember me for 30 days</label>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-sign-in-alt"></i>
                        Sign In
                    </button>
                </form>
                
                <div class="login-footer">
                    <p>New user? <a href="register.php">Create account</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>