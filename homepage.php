<?php
session_start();
include "db_conn.php"; // Koneksyon sa database

// --- BAGO: STORE STATUS CHECK (Ibo-block ang access kung closed) ---
$store_check = mysqli_query($conn, "SELECT is_open FROM store_settings WHERE id = 1");
if ($store_check && $row = mysqli_fetch_assoc($store_check)) {
    if ($row['is_open'] == 0) {
        // Kapag closed (0), ito ang lalabas sa buong screen at hindi maglo-load ang homepage
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Store Closed - Kainan ni Ate Kabayan</title>
            <link href='https://fonts.googleapis.com/css2?family=Fredoka+One&family=Poppins:wght@400;700;800&display=swap' rel='stylesheet'>
            <style>
                body { background-color: #FFF8E7; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: 'Poppins', sans-serif; text-align: center; }
                .closed-card { background: #fff; border: 3px solid #000; border-radius: 15px; padding: 40px; box-shadow: 5px 5px 0px #000; max-width: 500px; width: 90%; }
                h1 { font-family: 'Fredoka One', cursive; color: #e74c3c; margin-bottom: 15px; }
                p { font-weight: 600; color: #555; }
            </style>
            <link rel="stylesheet" href="modal.css">
</head>
        <body>
            <div class='closed-card'>
                <img src='https://res.cloudinary.com/dn38jxbeh/image/upload/v1772298452/logo_ate_kabayan_jtfqeg.jpg' style='width: 100px; border-radius: 50%; border: 2px solid #000; margin-bottom: 20px;'>
                <h1>STORE IS CLOSED</h1>
                <p>Pasensya na, Kabayan! Ang Kainan ni Ate Kabayan ay kasalukuyang sarado at hindi pa tumatanggap ng mga orders.</p>
                <p style='margin-top: 10px; font-size: 0.85rem; color: #888;'>Balik po kayo mamaya kapag kami ay open na ulit.</p>
            </div>
        <script src="modal.js"></script>
</body>
        </html>";
        exit(); // Pipigilan nito na basahin pa ang original html code mo sa ibaba
    }
}
// --- END STORE STATUS CHECK ---

// 1. Check kung sino ang user (Para sa personal na profile)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
$user_id = $_SESSION['user_id'];
$is_logged_in = true;

// 2. Kunin ang data ng user
$sql = "SELECT full_name, profile_pic FROM create_acc WHERE id = '$user_id'";
$result = mysqli_query($conn, $sql);
$user_data = mysqli_fetch_assoc($result);
$display_name = $user_data['full_name'] ?? "Guest";
$display_pic  = $user_data['profile_pic'] ?? "";

// --- RESERVATION SUBMIT LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_reservation'])) {
    $res_name = mysqli_real_escape_string($conn, $_POST['res_name']);
    $res_pax = mysqli_real_escape_string($conn, $_POST['res_pax']);
    $datetime = $_POST['res_datetime'];
    $res_date = date("Y-m-d", strtotime($datetime));
    $res_time = date("H:i:s", strtotime($datetime));

    $res_sql = "INSERT INTO table_reservations (user_id, name, pax, reservation_date, reservation_time, status) 
                VALUES ('$user_id', '$res_name', '$res_pax', '$res_date', '$res_time', 'Pending Confirmation')";

    if (mysqli_query($conn, $res_sql)) {
        echo "<script>document.addEventListener('DOMContentLoaded',()=>{ Swal.fire({ title:'Reservation Successful!', text:'Aantayin na lang ma-confirm ng admin.', icon:'success', confirmButtonColor:'#F4A42B', confirmButtonText:'Sige!' }).then(()=>{ window.location.href='homepage.php#reserve'; }); });<\/script>";
    } else {
        echo "<script>document.addEventListener('DOMContentLoaded',()=>{ Swal.fire({ title:'May Error!', text:'" . addslashes(mysqli_error($conn)) . "', icon:'error', confirmButtonColor:'#e74c3c' }); });<\/script>";
    }
}

// 3. --- DYNAMIC BEST SELLERS LOGIC ---
$best_sellers_query = mysqli_query($conn, "SELECT * FROM menu_items WHERE availability = 1 ORDER BY sold_count DESC LIMIT 6");

// 4. --- DYNAMIC GALLERY LOGIC ---
$gallery_query = mysqli_query($conn, "SELECT * FROM gallery_photos ORDER BY id DESC");

// 5. --- DYNAMIC SITE SETTINGS ---
$site = [];
$settings_query = mysqli_query($conn, "SELECT image_url FROM reserve_settings LIMIT 1");
if ($settings_query && mysqli_num_rows($settings_query) > 0) {
    $row = mysqli_fetch_assoc($settings_query);
    $site['reserve_image'] = $row['image_url'];
} else {
    $site['reserve_image'] = "https://res.cloudinary.com/dn38jxbeh/image/upload/v1772299904/Homapage_pic_4_job6tt.jpg";
}

// 6. Database Bridge para sa Cart
$db_items = [];
$cart_query = mysqli_query($conn, "SELECT item_name as name, price, image_url as img, qty FROM user_cart WHERE user_id = '$user_id'");
while ($row = mysqli_fetch_assoc($cart_query)) { $db_items[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kainan ni Ate Kabayan</title>
    <link rel="stylesheet" href="homepage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .gallery-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 15px; }
        .btn-add-cart { transition: 0.2s ease; box-shadow: 0 4px 0 #d48806; }
        .btn-add-cart:hover { background: #e69512 !important; transform: scale(1.02); }
        .btn-add-cart:active { transform: translateY(2px); box-shadow: 0 2px 0 #d48806; }
        .btn-heart { transition: 0.2s ease; }
        .btn-heart:hover { border-color: #ff4757 !important; color: #ff4757 !important; background: #fff0f1 !important; transform: scale(1.1); }
    </style>
    <link rel="stylesheet" href="modal.css">
</head>
<body>
  
<header>
    <div class="header-container">
        <a href="homepage.php" class="logo" style="text-decoration: none;">
            <div class="logo-circle"><img src="https://res.cloudinary.com/dn38jxbeh/image/upload/v1772298452/logo_ate_kabayan_jtfqeg.jpg" alt="Logo"></div>
            <h2>KAINAN NI ATE KABAYAN</h2>
        </a>
        <nav class="desktop-nav">
            <a href="homepage.php" class="active">Home</a>
            <a href="menu.php">Menu</a>
            <a href="orders.php">Orders</a>
            <a href="ratings.php">Reviews</a>
            <a href="About Us.php">About Us</a>
            <a href="Contactus.php">Contact</a>
        </nav>
        <div class="header-actions">
            <?php if($is_logged_in): ?>
            <a href="Profile.php" class="header-profile-desktop" style="text-decoration: none; display: flex; align-items: center; gap: 10px;">
                <div class="profile-img-small">
                    <?php if (!empty($display_pic)): ?>
                        <img src="<?php echo htmlspecialchars($display_pic); ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <span class="header-user-name" style="color: #fff; font-weight: bold;">HI, <?php echo strtoupper(explode(' ', trim($display_name))[0]); ?>!</span>
            </a>
            <?php endif; ?>
            <a href="cart.php" class="cart-icon-btn">
                <i class="fa-solid fa-shopping-cart"></i>
                <span class="badge" id="cart-badge">0</span>
            </a>
            <div class="hamburger-menu" id="hamburger-btn"><i class="fa-solid fa-bars"></i></div>
        </div>
    </div>
</header>

<nav class="side-nav" id="side-nav">
    <div class="nav-profile">
        <div class="close-btn" id="close-btn"><i class="fa-solid fa-xmark"></i></div>
        <div class="profile-info">
            <div class="profile-img">
                <?php if (!empty($display_pic)): ?>
                    <img src="<?php echo htmlspecialchars($display_pic); ?>" style="width:100%; border-radius:50%; aspect-ratio: 1/1; object-fit: cover;">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="profile-text">
                <h3><?php echo htmlspecialchars($display_name); ?></h3>
                <?php if($is_logged_in): ?><a href="Profile.php">(View Profile)</a><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="nav-links">
        <a href="homepage.php" class="active"><i class="fa-solid fa-house"></i> Home</a>
        <a href="menu.php"><i class="fa-solid fa-utensils"></i> Menu</a>
        <a href="orders.php"><i class="fa-solid fa-file-lines"></i> Orders</a>
        <a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> Cart</a>
        <a href="ratings.php"><i class="fa-solid fa-star"></i> Reviews</a>
        <a href="About Us.php"><i class="fa-solid fa-book-open"></i> About Us</a>
        <a href="Contactus.php"><i class="fa-solid fa-phone"></i> Contact Us</a>
        <a href="logout.php" class="logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log Out</a>
    </div>
</nav>

<div class="overlay" id="overlay"></div>

<section class="hero">
    <div class="hero-text">
         <div class="hero-buttons"><a href="menu.php" class="btn-order">ORDER NOW!</a></div>
    </div>
</section>

<section id="menu" class="menu-section">
    <div class="menu-orange-banner"><h2 class="banner-title">MGA PABORITO NI ATE KABAYAN</h2></div>
    <div class="container menu-container-content">
        <h3 class="section-subtitle-compact">BUSOG AT SULIT SA BAWAT ORDER! ITO ANG ILAN SA MGA <span class="best-sellers">BEST-SELLERS</span> NA SIGURADONG BABALIK-BALIKAN MO:</h3>
        
        <div class="menu-scroll-wrapper">
            <?php while($item = mysqli_fetch_assoc($best_sellers_query)): ?>
            <div class="menu-card-scroll">
                <div class="card-image-circle">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                </div>
                <div class="card-content">
                    <div class="title-price">
                        <h3><?php echo strtoupper($item['name']); ?></h3>
                        <span class="price-badge">₱<?php echo number_format($item['price'], 0); ?></span>
                    </div>
                    <p class="desc"><?php echo htmlspecialchars($item['description']); ?></p>
                    <div class="rating">⭐⭐⭐⭐⭐</div>
                    <div class="card-actions">
                        <button class="btn-add-cart">
                            Add to Cart! <i class="fa-solid fa-cart-shopping"></i>
                        </button>
                        <button class="btn-heart"><i class="fa-regular fa-heart"></i></button>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div> 
    </div>
</section>

<section class="features-section">
    <div class="features-container">
        <div class="feature-card">
            <div class="feature-icon-box">
                <img src="https://res.cloudinary.com/dn38jxbeh/image/upload/v1772299469/Motor_Driver_Homepage_ieqima.png" alt="Delivery">
            </div>
            <div class="feature-content">
                <h3>Fast Delivery</h3>
                <p>Si Ate ang bahala magpadeliver ng masarap, mabilis, at busog meal!</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon-box">
                <img src="https://res.cloudinary.com/dn38jxbeh/image/upload/v1772299510/Plate_logo_pic_Homepage_eftfyb.png" alt="Sulit">
            </div>
            <div class="feature-content">
                <h3>Sulit Servings</h3>
                <p>Busog ka na, sulit pa — good for sharing with pamilya at barkada.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon-box">
                <img src="https://res.cloudinary.com/dn38jxbeh/image/upload/v1772299510/Plate_logo_pic_Homepage_eftfyb.png" alt="Chef">
            </div>
            <div class="feature-content">
                <h3>Alagang Kabayan</h3>
                <p>Laging may ngiti, alaga, at malasakit mula sa team ni Ate Kabayan.</p>
            </div>
        </div>
    </div>
</section>

<section class="gallery-section">
    <h2 class="gallery-title">MGA BUSOG NA NGITI NG ATING MGA KABAYAN</h2>
    <div class="gallery-scroll-wrapper">
        <div class="gallery-photos">
            <?php while($photo = mysqli_fetch_assoc($gallery_query)): ?>
                <div class="gallery-item"><img src="<?php echo htmlspecialchars($photo['image_url']); ?>" alt="Customer Smile"></div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<section class="cta">
    <div class="cta-content">
        <h2>GUTOM KANA? KAIN NA KABAYAN!</h2>
        <div class="cta-buttons">
            <a href="menu.php" class="btn-primary">ORDER NOW!</a>
            <a href="https://share.google/I9ubNtooj7WKodVWl" class="btn-secondary">VISIT US!</a>
        </div>
    </div>
</section>

<section id="reserve" class="reserve-section">
    <div class="reserve-left">
        <h1 class="reserve-title"><span class="small">RESERVE A</span><br><span class="big">TABLE</span></h1>
        <form id="reserveForm" class="reserve-form" method="POST">
          <div class="input-group"><span>👤</span><input type="text" name="res_name" placeholder="Pangalan" required></div>
          <div class="input-group"><span>👥</span><input type="number" name="res_pax" placeholder="Ilang tao?" required></div>
          <div class="input-group"><span>📅</span><input type="datetime-local" name="res_datetime" required></div>
          <button type="submit" name="submit_reservation" class="reserve-btn">RESERVE</button>
        </form>
    </div>
    <div class="reserve-right"><img src="<?php echo htmlspecialchars($site['reserve_image']); ?>" alt="Group eating" /></div>
</section>

<footer id="contact">
    <div class="footer-content">
        <div class="footer-section contact-info">
            <img src="https://res.cloudinary.com/dn38jxbeh/image/upload/v1772298452/logo_ate_kabayan_jtfqeg.jpg" alt="Logo" class="footer-logo">
            <div class="details">
                <p><i class="fas fa-clock"></i> OPEN DAILY (10AM - 3AM)</p>
                <p><i class="fas fa-phone-alt"></i> (0921) 910 6057</p>
                <p><i class="fas fa-map-marker-alt"></i> 1785 Evangelista St., Bangkal, Makati City</p>
            </div>
        </div>
        <div class="footer-section sitemap">
            <h3>SITEMAP</h3>
            <ul>
                <li><a href="homepage.php">Home</a></li>
                <li><a href="menu.php">Menu</a></li>
                <li><a href="About Us.php">About Us</a></li>
                <li><a href="Contactus.php">Contact Us</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom"><p>© 2026 Kainan ni Ate Kabayan. All Right Reserved.</p></div>
</footer> 

<script>
    // --- GLOBAL VARIABLES PARA SA HOMEPAGE.JS ---
    const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    const currentUserId = "<?php echo $user_id; ?>";
    const cartKey = 'cart_' + currentUserId;
    const dbItems = <?php echo json_encode($db_items); ?>;

    // I-sync ang DB items sa Local Storage pag-load
    if (dbItems.length > 0) { 
        localStorage.setItem(cartKey, JSON.stringify(dbItems)); 
    }

    // --- BURGER MENU LOGIC ---
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sideNav = document.getElementById('side-nav');
    const overlay = document.getElementById('overlay');
    const closeBtn = document.getElementById('close-btn');

    hamburgerBtn?.addEventListener('click', () => { sideNav.classList.add('active'); overlay.classList.add('active'); });
    closeBtn?.addEventListener('click', () => { sideNav.classList.remove('active'); overlay.classList.remove('active'); });
    overlay?.addEventListener('click', () => { sideNav.classList.remove('active'); overlay.classList.remove('active'); });
</script>
<script src="homepage.js"></script>

<?php if (isset($_GET['new_user']) && $_GET['new_user'] == 1): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            title: 'Welcome Kabayan!',
            text: 'You are now a registered customer. Ready ka na bang umorder?',
            icon: 'success',
            confirmButtonColor: '#f4a261',
            confirmButtonText: 'Order Now!'
        }).then(() => { window.history.replaceState(null, null, window.location.pathname); });
    });
</script>
<?php endif; ?>

<script src="modal.js"></script>
</body>
</html>

