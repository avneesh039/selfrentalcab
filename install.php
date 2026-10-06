<?php
// Database Installer and Dummy Data Populator for Avinash Travels
header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$username = 'lakshyas_avinashtour';
$password = 'avinash123@';
$dbname = 'lakshyas_avinashtour';

$status_log = [];
$success = true;

try {
    // 1. Establish connection to MySQL server and select DB
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $status_log[] = "Connected to MySQL database '$dbname' successfully.";

    // 2. Try to verify database creation (will fail gracefully if user lacks global CREATE privilege)
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $status_log[] = "Database '$dbname' verified/created.";
    } catch (PDOException $e) {
        $status_log[] = "Database creation query skipped (using pre-existing user database).";
    }

    // 3. Switch to database
    $pdo->exec("USE `$dbname`;");
    $status_log[] = "Switched to database context '$dbname'.";

    // 4. Read and execute schema.sql
    $schema_file = __DIR__ . '/schema.sql';
    if (!file_exists($schema_file)) {
        throw new Exception("schema.sql file not found in root directory!");
    }
    
    $sql = file_get_contents($schema_file);
    
    // Remove comments
    $sql = preg_replace('/--.*\n/', '', $sql);
    
    // Split by semicolons (simple splitter, enough for our schema)
    $statements = explode(';', $sql);
    
    $table_count = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement) {
            // Programmatically skip database creation and context switching to prevent server permission errors
            if (preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $statement)) {
                continue;
            }
            $pdo->exec($statement);
            $table_count++;
        }
    }
    $status_log[] = "Schema executed successfully. ($table_count SQL statements processed)";

    // 5. Populate Pricing Rules if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM `pricing_rules`");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO `pricing_rules` (driver_allowance, toll_parking_estimate, night_charge, weekend_surcharge_percent, peak_hour_surcharge_percent, gst_percent, early_booking_48h_discount, early_booking_7d_discount, early_booking_15d_discount, early_booking_30d_discount) 
                    VALUES (300.00, 150.00, 250.00, 5.00, 10.00, 5.00, 10.00, 15.00, 20.00, 25.00)");
        $status_log[] = "Default pricing rules inserted.";
    }

    // 6. Populate Settings
    $stmt = $pdo->query("SELECT COUNT(*) FROM `settings`");
    if ($stmt->fetchColumn() == 0) {
        $default_settings = [
            'company_name' => 'Avinash Travels',
            'company_logo' => 'https://avinashtaxi.com/uploads/logo/logo.png',
            'company_tagline' => '90,000+ Rides Completed. One Journey at a Time.',
            'company_phone' => '8120868818',
            'company_whatsapp' => '918120868818',
            'company_email' => 'info@avinashtravels.com',
            'company_address' => 'Cab Service, Alakh DHaM Face1, Maharishi chowk, Vikas Nagar, Mangla, Bilaspur, Chhattisgarh 495001',
            'company_founder' => 'Ravi Gupta',
            'company_established_year' => '2013',
            'company_gst' => '22AAAAA0000A1Z5',
            'google_maps_iframe' => 'https://maps.google.com/maps?q=Avinash+Tour+and+Travels,+Alakh+Dham+Face1,+Maharishi+chowk,+Vikas+Nagar,+Mangla,+Bilaspur,+Chhattisgarh+495001&t=&z=15&ie=UTF8&iwloc=&output=embed',
            'social_facebook' => 'https://facebook.com/avinashtravels',
            'social_instagram' => 'https://instagram.com/avinashtravels',
            'social_twitter' => 'https://twitter.com/avinashtravels',
            'razorpay_key_id' => 'rzp_test_demo12345',
            'razorpay_key_secret' => 'demo_secret_key_12345',
            'sms_api_url' => 'https://api.mock-sms-gateway.com/send?key=demo&to={to}&msg={msg}',
            'whatsapp_api_url' => 'https://api.mock-wa-gateway.com/send?key=demo&to={to}&msg={msg}',
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => '2525',
            'smtp_user' => 'smtp_demo_user',
            'smtp_pass' => 'smtp_demo_pass',
            'booking_cancellation_policy' => 'Free cancellation up to 24 hours prior to departure. 50% charge applies within 24 hours. No-show is non-refundable.',
            'booking_terms_conditions' => 'Base fare includes driver allowance and vehicle usage. Toll, state taxes, and parking fees extra unless stated. Peak-hour surcharges might apply in dense traffic conditions.',
            'about_story' => 'Founded in 2013 by Ravi Gupta, our taxi service began with a simple vision — to provide safe, reliable, comfortable, and hassle-free transportation to every customer. Over the years, we have grown through the trust and support of our customers. Today, we are proud to have completed 90,000+ taxi rides, making every journey a valuable part of our story.',
            'about_commitment' => 'For us, every ride is more than just transportation. It is about punctuality, safety, comfort, and customer satisfaction. We strive to make every journey smooth and convenient, whether it is a local trip, airport transfer, outstation journey, business travel, or a family ride.',
            'about_mission' => 'To make every journey safe, comfortable, punctual, and memorable through professional service and customer-first values.',
            'about_vision' => 'To become a trusted and preferred mobility partner, delivering dependable transportation services while building long-lasting relationships with our customers.',
            'stat_happy_customers' => '85K+',
            'stat_trips_completed' => '90,000+',
            'stat_active_vehicles' => '100+',
            'stat_verified_drivers' => '24/7 Support',
            'stat_founded_year' => '2013'
        ];
        
        $sql_set = $pdo->prepare("INSERT INTO `settings` (meta_key, meta_value) VALUES (?, ?)");
        foreach ($default_settings as $k => $v) {
            $sql_set->execute([$k, $v]);
        }
        $status_log[] = "Global settings variables initialized.";
    }

    // 7. Populate Admins
    $stmt = $pdo->query("SELECT COUNT(*) FROM `admins`");
    if ($stmt->fetchColumn() == 0) {
        $admin_email = 'admin@avinashtravels.com';
        $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO `admins` (email, password_hash, name, role) VALUES ('$admin_email', '$admin_pass', 'Avinash Admin', 'superadmin')");
        $status_log[] = "Admin account created: email: <b>admin@avinashtravels.com</b> / password: <b>admin123</b>";
    }

    // 8. Populate Users (Customers)
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users`");
    if ($stmt->fetchColumn() == 0) {
        $cust_email = 'customer@avinashtravels.com';
        $cust_pass = password_hash('customer123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO `users` (name, email, phone, password_hash) VALUES ('Rahul Sharma', '$cust_email', '9876543210', '$cust_pass')");
        $status_log[] = "Customer account created: email: <b>customer@avinashtravels.com</b> / password: <b>customer123</b>";
    }

    // 9. Populate Drivers
    $stmt = $pdo->query("SELECT COUNT(*) FROM `drivers`");
    if ($stmt->fetchColumn() == 0) {
        $driver_email = 'driver@avinashtravels.com';
        $driver_pass = password_hash('driver123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO `drivers` (name, phone, email, password_hash, city, vehicle_type, vehicle_no, status) 
                    VALUES ('Amit Kumar', '8888888888', '$driver_email', '$driver_pass', 'Raipur', 'SUV', 'CG04-ME-1234', 'approved')");
        $status_log[] = "Driver account created: email: <b>driver@avinashtravels.com</b> / password: <b>driver123</b>";
    }

    // 10. Populate Vehicle Categories
    $stmt = $pdo->query("SELECT COUNT(*) FROM `vehicle_categories`");
    if ($stmt->fetchColumn() == 0) {
        $categories = [
            ['Sedan', 4, 2, 1, 'AC, Charging Port, Music System, Water Bottle', 'sedan.png', 500.00, 11.00, 150.00],
            ['SUV', 6, 4, 1, 'AC, Charging Port, Luggage Carrier, Bottled Water, Bluetooth Stereo', 'suv.png', 800.00, 15.00, 200.00],
            ['MPV', 7, 3, 1, 'AC, Charging Port, Extra Legroom, Bottled Water, Stereo System', 'mpv.png', 1000.00, 16.50, 220.00],
            ['Luxury', 4, 2, 1, 'Dual-Zone AC, Rear Charging, Leather Seats, Premium Sound, Wi-Fi, Newspaper', 'luxury.png', 2000.00, 30.00, 450.00],
            ['Tempo Traveller', 12, 10, 1, 'AC, LCD Screen, Overhead Carrier, Mic System, Adjustable Seats', 'tempo.png', 1800.00, 22.00, 350.00],
            ['Bus', 26, 25, 1, 'AC, DVD Player, Pushback Seats, Ambient Lighting, Driver Assistant', 'bus.png', 4000.00, 45.00, 800.00]
        ];
        
        $sql_cat = $pdo->prepare("INSERT INTO `vehicle_categories` (name, seating_capacity, luggage_capacity, ac, amenities, image, base_price, per_km_price, per_hour_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($categories as $c) {
            $sql_cat->execute($c);
        }
        $status_log[] = "Vehicle categories (Sedan, SUV, Luxury, etc.) configured.";
    }

    // 11. Populate Vehicles
    $stmt = $pdo->query("SELECT COUNT(*) FROM `vehicles`");
    if ($stmt->fetchColumn() == 0) {
        // Fetch categories to link correctly
        $cat_ids = [];
        foreach ($pdo->query("SELECT id, name FROM `vehicle_categories`")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cat_ids[$row['name']] = $row['id'];
        }

        $vehicles = [
            [$cat_ids['Sedan'], 'Maruti Suzuki Dzire', 'CG04-MR-9898', 'Silver'],
            [$cat_ids['Sedan'], 'Hyundai Aura', 'CG04-NB-1122', 'White'],
            [$cat_ids['SUV'], 'Toyota Innova Crysta', 'CG04-MZ-4455', 'Pearl White'],
            [$cat_ids['SUV'], 'Mahindra XUV700', 'CG04-NX-6677', 'Midnight Black'],
            [$cat_ids['MPV'], 'Maruti Suzuki Ertiga', 'CG04-OP-2233', 'White'],
            [$cat_ids['Luxury'], 'Mercedes-Benz C-Class', 'CG04-LX-0001', 'Black'],
            [$cat_ids['Tempo Traveller'], 'Force Traveller 12-Seater', 'CG04-TT-5050', 'White'],
            [$cat_ids['Bus'], 'Tata Luxury Coach 26-Seater', 'CG04-BS-9090', 'White & Gold']
        ];

        $sql_veh = $pdo->prepare("INSERT INTO `vehicles` (category_id, name, plate_number, color, status) VALUES (?, ?, ?, ?, 'available')");
        foreach ($vehicles as $v) {
            $sql_veh->execute($v);
        }
        $status_log[] = "Vehicles assigned to inventory.";
    }

    // 12. Populate Routes
    $stmt = $pdo->query("SELECT COUNT(*) FROM `routes`");
    if ($stmt->fetchColumn() == 0) {
        $routes = [
            ['Bilaspur', 'Raipur', 120.00, 2.50],
            ['Raipur', 'Bilaspur', 120.00, 2.50],
            ['Bilaspur', 'Raigarh', 160.00, 3.50],
            ['Raigarh', 'Bilaspur', 160.00, 3.50],
            ['Raigarh', 'Raipur', 240.00, 5.00],
            ['Raipur', 'Raigarh', 240.00, 5.00],
            ['Raigarh', 'Jharsuguda', 80.00, 1.75],
            ['Jharsuguda', 'Raigarh', 80.00, 1.75],
            ['Korba', 'Bilaspur', 90.00, 2.00],
            ['Bilaspur', 'Korba', 90.00, 2.00],
            ['Korba', 'Raipur', 210.00, 4.50],
            ['Raipur', 'Korba', 210.00, 4.50],
            ['Raipur', 'Nagpur', 285.00, 5.50],
            ['Raipur', 'Bhilai', 35.00, 0.75],
            ['Raipur', 'Jagdalpur', 300.00, 6.00],
            ['Raipur', 'Ambikapur', 330.00, 7.00],
            ['Raipur', 'Durg', 40.00, 0.90],
            ['Bilaspur', 'Nagpur', 380.00, 7.50],
            ['Bhilai', 'Nagpur', 250.00, 5.00]
        ];

        $sql_rt = $pdo->prepare("INSERT INTO `routes` (pickup_city, drop_city, distance_km, est_hours) VALUES (?, ?, ?, ?)");
        foreach ($routes as $r) {
            $sql_rt->execute($r);
        }
        $status_log[] = "Standard intercity travel routes set.";
    }

    // 13. Populate Offers
    $stmt = $pdo->query("SELECT COUNT(*) FROM `offers`");
    if ($stmt->fetchColumn() == 0) {
        $offers = [
            ['Weekend Raipur to Nagpur Trip Discount', 'NGP20', 'Raipur', 'Nagpur', 3800.00, 4800.00, 20, date('Y-m-d', strtotime('+30 days')), 'offer1.jpg'],
            ['Special Bilaspur Ride Promo', 'BSP15', 'Raipur', 'Bilaspur', 1800.00, 2200.00, 15, date('Y-m-d', strtotime('+15 days')), 'offer2.jpg'],
            ['Jagdalpur Forest Tour Deal', 'JAG25', 'Raipur', 'Jagdalpur', 4500.00, 6000.00, 25, date('Y-m-d', strtotime('+45 days')), 'offer3.jpg']
        ];

        $sql_off = $pdo->prepare("INSERT INTO `offers` (title, coupon_code, pickup_city, drop_city, special_price, original_price, discount_percent, validity, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($offers as $o) {
            $sql_off->execute($o);
        }
        $status_log[] = "Promo coupon codes and discounts cataloged.";
    }

    // 14. Populate Services
    $stmt = $pdo->query("SELECT COUNT(*) FROM `services`");
    if ($stmt->fetchColumn() == 0) {
        $services = [
            ['One Way Outstation Cab', 'Economical one-way transfers with absolute door-to-door luxury. Avoid paying double. We charge only for distance traveled.', 'oneway.png'],
            ['Round Trip Outstation Cab', 'Plan weekend getaways with experienced chauffeurs, minimal waits, and customizable sightseeing packages.', 'roundtrip.png'],
            ['Airport & Railway Transfers', 'Never miss a flight or train. Prompt, on-time airport pickups and drop-offs with absolute flight tracking services.', 'airport.png'],
            ['Wedding & Event Rentals', 'Make your special day premium with decorated luxury fleets and flawless transport coordination for families.', 'wedding.png'],
            ['Corporate & Business Travel', 'Monthly premium packages, clean sedans, and structured invoices with dedicated billing profiles for corporate executives.', 'corporate.png'],
            ['Tempo Traveller & Coach Services', 'Travel together in large groups with absolute comfort, high capacity luggage carriers, and entertainment setups.', 'tempo_service.png']
        ];

        $sql_ser = $pdo->prepare("INSERT INTO `services` (title, description, image) VALUES (?, ?, ?)");
        foreach ($services as $s) {
            $sql_ser->execute($s);
        }
        $status_log[] = "Travel services catalog active.";
    }

    // 15. Populate FAQs
    $stmt = $pdo->query("SELECT COUNT(*) FROM `faqs`");
    if ($stmt->fetchColumn() == 0) {
        $faqs = [
            ['How do I book a premium cab on Avinash Travels?', 'You can book using our simple booking widget on the homepage. Just select One Way or Round Trip, search available cars, choose your preferred vehicle, fill passenger details, pay a token amount or choose Cash, and download the receipt.', 1],
            ['Are toll, state tax, and parking fees included in the fare?', 'Standard pricing calculations include the base fare and driver charges. Toll fees, parking, and state permit taxes are calculated extra based on actual usage, unless a fixed promotional route offer specifies otherwise.', 2],
            ['Can I modify or cancel my booking?', 'Yes, cancellations are allowed. Log in to your Customer Dashboard with your phone/email to cancel or modify. Full refunds are processed if cancelled at least 24 hours prior to departure.', 3],
            ['Do you provide door-to-door pickups?', 'Absolutely. Our driver will arrive at your specified pickup address and drop you off directly at your final destination.', 4],
            ['How can I register my vehicle or become a driver partner?', 'Scroll down to the "Drive With Us" section and click "Become a Partner". Fill in your details and upload your vehicle documents. Once approved by the administrator, you can bid on available trips.', 5]
        ];

        $sql_faq = $pdo->prepare("INSERT INTO `faqs` (question, answer, sort_order) VALUES (?, ?, ?)");
        foreach ($faqs as $f) {
            $sql_faq->execute($f);
        }
        $status_log[] = "Accordion FAQs populated.";
    }

    // 16. Populate Testimonials
    $stmt = $pdo->query("SELECT COUNT(*) FROM `testimonials`");
    if ($stmt->fetchColumn() == 0) {
        $testimonials = [
            ['Ashish Verma', 5, 'Extremely professional drivers and neat vehicles. The Raipur to Nagpur trip was smooth and booking took under 2 minutes.', 'Raipur to Nagpur', 't1.png'],
            ['Priya Sen', 5, 'Highly recommended for outstation trips. Safe for solo female travelers, the driver was verified and GPS trackable.', 'Raipur to Bilaspur', 't2.png'],
            ['Vikram Aditya', 4, 'Very reasonable rates. The Tempo Traveller was luxurious, fully AC and had custom adjustments. Superb experience!', 'Raipur to Jagdalpur', 't3.png']
        ];

        $sql_test = $pdo->prepare("INSERT INTO `testimonials` (name, rating, review, route, photo) VALUES (?, ?, ?, ?, ?)");
        foreach ($testimonials as $t) {
            $sql_test->execute($t);
        }
        $status_log[] = "Customer testimonials registered.";
    }

    // 17. Create uploads folder
    $upload_dirs = [
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/vehicles',
        __DIR__ . '/uploads/offers',
        __DIR__ . '/uploads/services',
        __DIR__ . '/uploads/testimonials',
        __DIR__ . '/uploads/gallery',
        __DIR__ . '/uploads/documents'
    ];
    foreach ($upload_dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    $status_log[] = "Upload directory structures verified/created.";

} catch (PDOException $e) {
    $success = false;
    $status_log[] = "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $success = false;
    $status_log[] = "System Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Avinash Travels</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0B1220;
            color: #F8FAFC;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .container {
            background-color: #111A2E;
            border: 1px solid #1E293B;
            border-radius: 12px;
            padding: 30px;
            width: 100%;
            max-width: 650px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }
        h1 {
            color: #0070E0;
            font-size: 22px;
            margin-top: 15px;
            margin-bottom: 20px;
            border-bottom: 1px solid #1E293B;
            padding-bottom: 10px;
        }
        .status-box {
            background-color: #070D19;
            border: 1px solid #1E293B;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 25px;
            max-height: 300px;
            overflow-y: auto;
        }
        .status-item {
            margin: 10px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        .status-item::before {
            content: "✓";
            color: #10B981;
            font-weight: bold;
            margin-right: 10px;
            display: inline-block;
        }
        .status-item.error::before {
            content: "✗";
            color: #EF4444;
        }
        .success-banner {
            background-color: rgba(16, 185, 129, 0.15);
            border: 1px solid #10B981;
            color: #10B981;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .error-banner {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid #EF4444;
            color: #EF4444;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #0066CC 0%, #00B4D8 100%);
            color: #FFFFFF;
            text-decoration: none;
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.2s ease;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }
        .btn:hover {
            background: linear-gradient(135deg, #0052A3 0%, #0096C7 100%);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="text-align: center; margin-bottom: 10px;">
            <div style="display: inline-block; background-color: rgba(255,255,255,0.95); padding: 8px 18px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
                <img src="https://avinashtaxi.com/uploads/logo/logo.png" alt="Avinash Travels" style="height: 42px; vertical-align: middle;">
            </div>
        </div>
        <h1 style="text-align: center;">Database Configuration Setup</h1>
        
        <?php if ($success): ?>
            <div class="success-banner">
                Database initialized and configured successfully!
            </div>
        <?php else: ?>
            <div class="error-banner">
                Database configuration failed. Check logs below.
            </div>
        <?php endif; ?>

        <div class="status-box">
            <?php foreach ($status_log as $log): ?>
                <div class="status-item <?php echo (strpos($log, 'Error') !== false) ? 'error' : ''; ?>">
                    <?php echo $log; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($success): ?>
            <a href="index.php" class="btn">Launch Homepage & Book Cabs</a>
        <?php else: ?>
            <a href="install.php" class="btn" style="background-color: #EF4444; color: white;">Retry Setup</a>
        <?php endif; ?>
    </div>
</body>
</html>
