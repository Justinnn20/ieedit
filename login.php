<?php
session_start();
include "db_conn.php";

/**
 * --- FUNCTION PARA SA REDIRECTION ---
 * Dito sineset kung saang pinto papasok ang user base sa role niya.
 */
function getRedirectPage($role) {
    if ($role == 'Admin' || $role == 'Admin Assistant') {
        return "Dashboard.php";
    } 
    elseif ($role == 'Head Chef' || $role == 'Kitchen Staff') {
        return "kitchen_dashboard.php";
    } 
    else {
        return "homepage.php"; 
    }
}

/**
 * --- FUNCTION PARA SA CUSTOM MODAL (REPLACING ALERT) ---
 * Ito ang gagamitim natin para mawala ang "localhost says".
 */
function showModalError($message, $location) {
    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap' rel='stylesheet'>
        <style>
            .modal-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center; z-index: 9999; font-family: 'Poppins', sans-serif; }
            .modal-box { background: white; padding: 30px; border-radius: 20px; text-align: center; max-width: 350px; width: 90%; box-shadow: 0 10px 25px rgba(0,0,0,0.2); animation: pop 0.3s ease-out; }
            @keyframes pop { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
            .modal-box h3 { color: #FFBD59; margin-top: 0; font-size: 1.5rem; }
            .modal-box p { color: #555; font-size: 0.9rem; margin-bottom: 20px; }
            .modal-btn { background: #FFBD59; color: white; border: none; padding: 10px 30px; border-radius: 50px; font-weight: bold; cursor: pointer; transition: 0.3s; }
            .modal-btn:hover { background: #f4a42b; transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class='modal-bg'>
            <div class='modal-box'>
                <h3>Ate Kabayan Says</h3>
                <p>$message</p>
                <button class='modal-btn' onclick=\"window.location='$location'\">OK</button>
            </div>
        </div>
    </body>
    </html>";
    exit();
}

/**
 * --- 1. FACEBOOK LOGIN LOGIC ---
 */
if (isset($_POST['fb_login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['fb_email']);
    $name  = mysqli_real_escape_string($conn, $_POST['fb_name']);
    $fb_id = mysqli_real_escape_string($conn, $_POST['fb_id']);

    $check_query = "SELECT * FROM create_acc WHERE email='$email' LIMIT 1";
    $result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($result) > 0) {
        $update_sql = "UPDATE create_acc SET facebook_id='$fb_id' WHERE email='$email'";
        mysqli_query($conn, $update_sql);
        
        $result = mysqli_query($conn, $check_query);
        $user = mysqli_fetch_assoc($result);
    } else {
        $sql = "INSERT INTO create_acc (full_name, email, facebook_id, role, password) 
                VALUES ('$name', '$email', '$fb_id', 'Customer', 'FACEBOOK_AUTH_NO_PASS')";
        mysqli_query($conn, $sql);
        
        $new_id = mysqli_insert_id($conn);
        $user = ['id' => $new_id, 'full_name' => $name, 'role' => 'Customer', 'delete_at' => null];
    }

    if (strtolower($user['role']) !== 'customer') {
        showModalError('Ang portal na ito ay para lamang sa mga customers. Mangyaring gamitin ang Admin Portal para mag-login.', 'login.html');
    }

    // --- RESTORE ACCOUNT LOGIC (FB) ---
    if (!empty($user['delete_at'])) {
        mysqli_query($conn, "UPDATE create_acc SET delete_at = NULL WHERE id = '" . $user['id'] . "'");
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['logged_in'] = true;
    
    $login_id = $user['id'];
    mysqli_query($conn, "UPDATE create_acc SET last_login = NOW() WHERE id='$login_id'");

    header("Location: homepage.php");
    exit();
}

/**
 * --- 2. GOOGLE LOGIN LOGIC ---
 */
elseif (isset($_POST['google_login'])) {
    $payload_str = $_POST['credential'];
    
    $payload = json_decode($payload_str, true);
    
    if (!$payload || !isset($payload['email'])) {
        $parts = explode(".", $payload_str);
        if(count($parts) == 3) {
            $payload = json_decode(base64_decode($parts[1]), true);
        }
    }
        
    $email = mysqli_real_escape_string($conn, $payload['email']);
    $name  = mysqli_real_escape_string($conn, $payload['name']);
    $g_id  = mysqli_real_escape_string($conn, $payload['sub']);

    $check_query = "SELECT * FROM create_acc WHERE email='$email' LIMIT 1";
    $result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        mysqli_query($conn, "UPDATE create_acc SET google_id='$g_id' WHERE email='$email'");
    } else {
        $sql = "INSERT INTO create_acc (full_name, email, google_id, role, password) 
                VALUES ('$name', '$email', '$g_id', 'Customer', 'GOOGLE_AUTH_NO_PASS')";
        mysqli_query($conn, $sql);
        
        $new_id = mysqli_insert_id($conn);
        $user = ['id' => $new_id, 'full_name' => $name, 'role' => 'Customer', 'delete_at' => null];
    }

    if (strtolower($user['role']) !== 'customer') {
        showModalError('Ang portal na ito ay para lamang sa mga customers. Mangyaring gamitin ang Admin Portal para mag-login.', 'login.html');
    }

    // --- RESTORE ACCOUNT LOGIC (GOOGLE) ---
    if (!empty($user['delete_at'])) {
        mysqli_query($conn, "UPDATE create_acc SET delete_at = NULL WHERE id = '" . $user['id'] . "'");
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['logged_in'] = true;
    
    $login_id = $user['id'];
    mysqli_query($conn, "UPDATE create_acc SET last_login = NOW() WHERE id='$login_id'");

    header("Location: homepage.php");
    exit();
}

/**
 * --- 3. MANUAL LOGIN ---
 */
elseif (isset($_POST['login_btn'])) {
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $query  = "SELECT * FROM create_acc WHERE email='$email' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        if (strtolower($user['role']) !== 'customer') {
            showModalError('Ang portal na ito ay para lamang sa mga customers. Mangyaring gamitin ang <a href="admin_login.php" style="color: #FFBD59;">Admin Portal</a> para mag-login.', 'login.html');
        }

        if (password_verify($password, $user['password']) || $password === $user['password']) {
            
            // --- RESTORE ACCOUNT LOGIC (MANUAL) ---
            if (!empty($user['delete_at'])) {
                mysqli_query($conn, "UPDATE create_acc SET delete_at = NULL WHERE id = '" . $user['id'] . "'");
            }

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['logged_in'] = true;
            
            $login_id = $user['id'];
            mysqli_query($conn, "UPDATE create_acc SET last_login = NOW() WHERE id='$login_id'");

            header("Location: homepage.php");
            exit();
        } else {
            showModalError('Maling Password, Kabayan! Paki-check ulit.', 'login.html');
        }
    } else {
        showModalError('Hindi nahanap ang email na iyan. Sigurado ka bang tama?', 'login.html');
    }
}

else {
    header("Location: login.html");
    exit();
}
?>