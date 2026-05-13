<?php
session_start();
include "db_conn.php"; 

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Backend Password Validation (Prevent Bypass)
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $message = "Mahina ang password. Siguraduhing nasunod ang lahat ng requirements.";
        $message_type = "error";
    }
    // 2. I-check kung nagma-match ang password
    elseif ($password !== $confirm_password) {
        $message = "Hindi nagtutugma ang mga password. Subukan muli.";
        $message_type = "error";
    } else {
        // 3. I-check kung may gumagamit na ng email na ito
        $check_email = "SELECT id FROM create_acc WHERE email = '$email'";
        $result = mysqli_query($conn, $check_email);

        if (mysqli_num_rows($result) > 0) {
            $message = "Ang email na ito ay rehistrado na. Mag-login na lang.";
            $message_type = "error";
        } else {
            // 4. I-hash ang password bago i-save sa database para secure
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 5. I-insert ang bagong user sa create_acc table (Default role: Customer)
            $sql = "INSERT INTO create_acc (full_name, email, password, role) VALUES ('$full_name', '$email', '$hashed_password', 'Customer')";
            
            if (mysqli_query($conn, $sql)) {
                $new_id = mysqli_insert_id($conn);

                // --- AUTO LOGIN LOGIC ---
                $_SESSION['user_id'] = $new_id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['role'] = 'Customer';
                $_SESSION['logged_in'] = true;
                
                // Idirekta sa homepage na may notification flag
                header("Location: homepage.php?new_user=1");
                exit();
            } else {
                $message = "May mali sa system: " . mysqli_error($conn);
                $message_type = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Error</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4a261; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #fff; padding: 40px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 90%; }
        .card h2 { color: #e74c3c; margin-bottom: 10px; }
        .card p { color: #555; margin-bottom: 20px; }
        .btn-back { background: #333; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 30px; font-weight: 600; display: inline-block; }
        .btn-back:hover { background: #555; }
    </style>
</head>
<body>
    <?php if(!empty($message)): ?>
    <div class="card">
        <h2><i class="fas fa-exclamation-circle"></i> Oops!</h2>
        <p><?php echo htmlspecialchars($message); ?></p>
        <a href="createacc.html" class="btn-back">Bumalik at Subukan Muli</a>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <?php endif; ?>
</body>
</html>
