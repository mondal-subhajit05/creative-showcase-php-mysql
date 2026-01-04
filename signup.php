<?php
require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $check_query = "SELECT id FROM users WHERE username = '$username' OR email = '$email'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = 'Username or email already exists';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_query = "INSERT INTO users (username, email, password, full_name) 
                             VALUES ('$username', '$email', '$hashed_password', '$full_name')";
            
            if (mysqli_query($conn, $insert_query)) {
                $success = 'Account created successfully! You can now login.';
                $_POST = array();
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up | Creative Showcase</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-container {
            width: 100%;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.95);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 700;
            color: #333;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            text-align: center;
        }

        .alert.error {
            background: #ffe1e1;
            color: #c0392b;
        }

        .alert.success {
            background: #e7f0ff;
            color: #2c3e9f;
        }

        .form-group {
            margin-bottom: 1.15rem;
        }

        label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
            color: #555;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #999;
            font-size: 0.9rem;
        }

        input {
            width: 100%;
            padding: 0.75rem 0.75rem 0.75rem 2.4rem;
            border-radius: 10px;
            border: 1px solid #ddd;
            outline: none;
            font-size: 0.95rem;
            transition: 0.3s;
        }

        input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }

        .btn {
            width: 100%;
            padding: 0.85rem;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .footer-text {
            text-align: center;
            margin-top: 1.2rem;
            font-size: 0.9rem;
            color: #555;
        }

        .footer-text a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Create Account</h2>

    <?php if($error): ?>
        <div class="alert error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Full Name</label>
            <div class="input-wrapper">
                <i class="fa fa-id-card"></i>
                <input type="text" name="full_name"
                    value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                    required>
            </div>
        </div>

        <div class="form-group">
            <label>Username</label>
            <div class="input-wrapper">
                <i class="fa fa-user"></i>
                <input type="text" name="username"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    required>
            </div>
        </div>

        <div class="form-group">
            <label>Email</label>
            <div class="input-wrapper">
                <i class="fa fa-envelope"></i>
                <input type="email" name="email"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    required>
            </div>
        </div>

        <div class="form-group">
            <label>Password</label>
            <div class="input-wrapper">
                <i class="fa fa-lock"></i>
                <input type="password" name="password" required>
            </div>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <div class="input-wrapper">
                <i class="fa fa-lock"></i>
                <input type="password" name="confirm_password" required>
            </div>
        </div>

        <button type="submit" class="btn">Sign Up</button>
    </form>

    <p class="footer-text">
        Already have an account?
        <a href="login.php">Login here</a>
    </p>
</div>

</body>
</html>
