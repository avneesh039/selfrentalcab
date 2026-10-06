<?php
// Single-page Homepage for Avinash Travels - Savaari Themed Overhaul
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/pricing_helper.php';

// Fetch global settings
$company_name = get_setting('company_name', 'Avinash Travels');
$company_tagline = get_setting('company_tagline', '90,000+ Rides Completed. One Journey at a Time.');
$phone = get_setting('company_phone', '8120868818');
if (empty($phone) || strpos($phone, '98765') !== false) {
    $phone = '8120868818';
    if ($pdo) {
        try {
            $pdo->prepare("UPDATE `settings` SET meta_value = '8120868818' WHERE meta_key = 'company_phone'")->execute();
        } catch (Exception $e) {}
    }
}

$whatsapp = get_setting('company_whatsapp', '918120868818');
if (empty($whatsapp) || strpos($whatsapp, '98765') !== false) {
    $whatsapp = '918120868818';
    if ($pdo) {
        try {
            $pdo->prepare("UPDATE `settings` SET meta_value = '918120868818' WHERE meta_key = 'company_whatsapp'")->execute();
        } catch (Exception $e) {}
    }
}
$email = get_setting('company_email', 'info@avinashtravels.com');
$address = get_setting('company_address', 'Cab Service, Alakh DHaM Face1, Maharishi chowk, Vikas Nagar, Mangla, Bilaspur, Chhattisgarh 495001');
if (empty($address) || strpos($address, 'VIP Road') !== false) {
    $address = 'Cab Service, Alakh DHaM Face1, Maharishi chowk, Vikas Nagar, Mangla, Bilaspur, Chhattisgarh 495001';
    if ($pdo) {
        try {
            $pdo->prepare("UPDATE `settings` SET meta_value = ? WHERE meta_key = 'company_address'")->execute([$address]);
        } catch (Exception $e) {}
    }
}
$maps_link = 'https://www.google.com/search?sca_esv=b06705979e1ea1a7&sxsrf=APpeQnvOZFZ0R0EVS8ccAzFYGacFsaVCXA:1789119204752&q=avinash+tour+and+travels+bilaspur+address&ludocid=1257295245785868028&sa=X&ved=2ahUKEwj2vqO1nOaWAxWukOEIHRztJMsQ6BN6BAgpEAI';
$founder_name = get_setting('company_founder', 'Ravi Gupta');
$established_year = get_setting('company_established_year', '2013');
$maps_iframe = get_setting('google_maps_iframe', 'https://maps.google.com/maps?q=Avinash+Tour+and+Travels,+Alakh+Dham+Face1,+Maharishi+chowk,+Vikas+Nagar,+Mangla,+Bilaspur,+Chhattisgarh+495001&t=&z=15&ie=UTF8&iwloc=&output=embed');
$social_fb = get_setting('social_facebook', '#');
$social_ig = get_setting('social_instagram', '#');
$social_tw = get_setting('social_twitter', '#');

// Fetch Payment Gateway Settings (configured in Admin Panel Settings & Payments)
$front_upi_id = get_setting('upi_id', get_setting('payment_upi_id', '8120868818@ybl'));
$front_qr_mode = get_setting('payment_qr_mode', 'dynamic');
$front_qr_img = get_setting('payment_qr_image', '');
$front_bank_name = get_setting('bank_name', 'State Bank of India (SBI)');
$front_acc_name = get_setting('bank_account_name', $company_name);
$front_acc_num = get_setting('bank_account_number', '38920194829');
$front_ifsc = get_setting('bank_ifsc', 'SBIN0000461');
$front_branch = get_setting('bank_branch', 'Raipur Main Branch');

// Fetch CMS stats
$stat_happy = get_setting('stat_happy_customers', '85K+');
$stat_trips = get_setting('stat_trips_completed', '90,000+');
$stat_fleet = get_setting('stat_active_vehicles', '100+');
$stat_support = get_setting('stat_verified_drivers', '24/7 Support');
$stat_founded = get_setting('stat_founded_year', '2013');

// Fetch about paragraphs
$about_story = get_setting('about_story', 'Founded in 2013 by Ravi Gupta, our taxi service began with a simple vision — to provide safe, reliable, comfortable, and hassle-free transportation to every customer. Over the years, we have grown through the trust and support of our customers. Today, we are proud to have completed 90,000+ taxi rides, making every journey a valuable part of our story.');
$about_commitment = get_setting('about_commitment', 'For us, every ride is more than just transportation. It is about punctuality, safety, comfort, and customer satisfaction. We strive to make every journey smooth and convenient, whether it is a local trip, airport transfer, outstation journey, business travel, or a family ride.');
$about_mission = get_setting('about_mission', 'To make every journey safe, comfortable, punctual, and memorable through professional service and customer-first values.');
$about_vision = get_setting('about_vision', 'To become a trusted and preferred mobility partner, delivering dependable transportation services while building long-lasting relationships with our customers.');

// Fetch dynamic data from database
try {
    $categories = ($pdo) ? $pdo->query("SELECT * FROM `vehicle_categories` WHERE status = 'active' ORDER BY base_price ASC LIMIT 6")->fetchAll() : [];
    $routes = ($pdo) ? $pdo->query("SELECT * FROM `routes` WHERE status = 'active' LIMIT 6")->fetchAll() : [];
    $offers = ($pdo) ? $pdo->query("SELECT * FROM `offers` WHERE status = 'active' AND validity >= CURDATE() LIMIT 3")->fetchAll() : [];
    $services = ($pdo) ? $pdo->query("SELECT * FROM `services` WHERE status = 'active' LIMIT 6")->fetchAll() : [];
    $testimonials = ($pdo) ? $pdo->query("SELECT * FROM `testimonials` WHERE status = 'active' LIMIT 6")->fetchAll() : [];
    $faqs = ($pdo) ? $pdo->query("SELECT * FROM `faqs` ORDER BY sort_order ASC")->fetchAll() : [];
    $gallery = ($pdo) ? $pdo->query("SELECT * FROM `gallery` LIMIT 8")->fetchAll() : [];
    
    // Fetch unique cities for search dropdowns/datalists
    $cities = [];
    if ($pdo) {
        $cities_stmt = $pdo->query("SELECT DISTINCT pickup_city AS city FROM `routes` WHERE status = 'active' UNION SELECT DISTINCT drop_city AS city FROM `routes` WHERE status = 'active'");
        $cities = $cities_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    $categories = [];
    $routes = [];
    $offers = [];
    $services = [];
    $testimonials = [];
    $faqs = [];
    $gallery = [];
    $cities = [];
}

// Ensure fleet categories always contain the complete catalog
if (empty($categories)) {
    $categories = [
        ['id' => 1, 'name' => 'Sedan', 'seating_capacity' => 4, 'luggage_capacity' => 2, 'ac' => 1, 'amenities' => 'AC, Music System, Boot Space, Bottled Water', 'image' => 'sedan.png', 'base_price' => 500.00, 'per_km_price' => 11.00, 'per_hour_price' => 150.00],
        ['id' => 2, 'name' => 'SUV', 'seating_capacity' => 6, 'luggage_capacity' => 4, 'ac' => 1, 'amenities' => 'High Ground Clearance, Luggage Carrier, AC, Extra Legroom', 'image' => 'suv.png', 'base_price' => 800.00, 'per_km_price' => 15.00, 'per_hour_price' => 200.00],
        ['id' => 3, 'name' => 'MPV', 'seating_capacity' => 7, 'luggage_capacity' => 3, 'ac' => 1, 'amenities' => '7-Seater, AC, Extra Legroom, Family Favorite', 'image' => 'mpv.png', 'base_price' => 1000.00, 'per_km_price' => 16.50, 'per_hour_price' => 220.00],
        ['id' => 4, 'name' => 'Luxury', 'seating_capacity' => 4, 'luggage_capacity' => 2, 'ac' => 1, 'amenities' => 'Leather Interior, Dual AC, VIP Chauffeur, Wi-Fi', 'image' => 'luxury.png', 'base_price' => 2000.00, 'per_km_price' => 30.00, 'per_hour_price' => 450.00],
        ['id' => 5, 'name' => 'Tempo Traveller', 'seating_capacity' => 12, 'luggage_capacity' => 10, 'ac' => 1, 'amenities' => 'Pushback Seats, Carrier, AC, Group Tours', 'image' => 'tempo.png', 'base_price' => 1800.00, 'per_km_price' => 22.00, 'per_hour_price' => 350.00],
        ['id' => 6, 'name' => 'Bus', 'seating_capacity' => 26, 'luggage_capacity' => 25, 'ac' => 1, 'amenities' => 'AC Coach, Luxury Seats, Tour Sound, Luggage Bay', 'image' => 'bus.png', 'base_price' => 4000.00, 'per_km_price' => 45.00, 'per_hour_price' => 800.00]
    ];
}

if (empty($testimonials)) {
    $testimonials = [
        ['name' => 'Rahul Sharma', 'route' => 'Raipur to Bilaspur', 'rating' => 5, 'review' => 'Excellent on-time service! The Ertiga was sanitized and spotless. Driver reached 10 minutes early and drove very safely on the expressway.'],
        ['name' => 'Pooja Verma', 'route' => 'Bilaspur to Raipur Airport', 'rating' => 5, 'review' => 'Booked an airport drop at 4:30 AM. Chauffeur was courteous, helped with our heavy luggage, and the fare was fixed with no hidden fees.'],
        ['name' => 'Amitabh Agrawal', 'route' => 'Raipur to Jagdalpur Outstation', 'rating' => 5, 'review' => 'Best intercity taxi service in Chhattisgarh. Completed our 3-day Bastar tour smoothly. Highly recommended for families!']
    ];
}

if (empty($faqs)) {
    $faqs = [
        ['question' => 'How much advance is required to book a cab?', 'answer' => 'You can book a cab anytime 24/7! We support instant on-demand pickups as well as scheduled advance trips.'],
        ['question' => 'Are toll taxes and driver allowances included in the fare?', 'answer' => 'Yes! All our outstation fixed-route quotes and search results are transparent with tolls, state taxes, and driver allowances clearly specified.'],
        ['question' => 'What happens if my flight or train is delayed?', 'answer' => 'For airport and railway station pickups, our team monitors your flight or train arrival in real-time. Complimentary waiting time is included.'],
        ['question' => 'How can I pay for my cab booking?', 'answer' => 'We accept UPI (GPay, PhonePe, Paytm, BHIM), dynamic QR code payments, bank transfers, as well as Cash directly to the chauffeur.'],
        ['question' => 'Can I cancel or reschedule my cab booking?', 'answer' => 'Yes, free cancellations and hassle-free rescheduling are available up to 4 hours before scheduled pickup time.']
    ];
}

// Fixed one-way routes catalog & guaranteed city coverage across all 33 Chhattisgarh districts
$oneway_corridors = get_fixed_oneway_routes();
$default_cities = [
    'Raipur', 'Bilaspur', 'Durg', 'Bhilai', 'Korba', 'Raigarh', 'Rajnandgaon', 'Jagdalpur', 'Ambikapur',
    'Dhamtari', 'Mahasamund', 'Kanker', 'Kawardha', 'Janjgir', 'Champa', 'Bhatapara', 'Baloda Bazar',
    'Mungeli', 'Gariaband', 'Balod', 'Bemetara', 'Kondagaon', 'Narayanpur', 'Dantewada', 'Bijapur',
    'Sukma', 'Jashpur', 'Balrampur', 'Surajpur', 'Baikunthpur', 'Manendragarh', 'Chirmiri', 'Mohla',
    'Sarangarh', 'Khairagarh', 'Gaurela-Pendra-Marwahi', 'Sakti', 'Ratanpur', 'Takhatpur', 'Tilda Newra',
    'Simga', 'Saraipali', 'Basna', 'Dongargarh', 'Arang', 'Abhanpur', 'Patan', 'Kurud', 'Rajim',
    'Nagpur', 'Jharsuguda'
];
$cities = array_values(array_unique(array_merge(!empty($cities) ? $cities : [], $default_cities)));
sort($cities);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title><?php echo htmlspecialchars($company_name); ?> - Reliable & Safe Cabs and Taxi Services at Affordable Prices</title>
    <meta name="description" content="Book affordable outstation cabs, one-way drops, round trips, and airport transfers with <?php echo htmlspecialchars($company_name); ?>. Chauffeur included, guaranteed fixed fares, sanitized cars, 24/7 helpline.">
    <meta name="keywords" content="Savaari cab, outstation cab booking, one way taxi, round trip cab, airport taxi Raipur, Bilaspur cab, Raigarh taxi, Korba cab">
    <link rel="canonical" href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($company_name); ?> - Outstation Cabs & Taxi Services">
    <meta property="og:description" content="Safe, verified and affordable outstation cab rentals across Central India with expert chauffeurs.">
    <meta property="og:image" content="https://avinashtaxi.com/uploads/logo/logo.png">
    <meta property="og:type" content="website">

    <!-- Fonts & Icons (Inter + Poppins + FontAwesome 6) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS CDN with Savaari Palette Tokens -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        savaari: {
                            cyan: '#20A8D9',
                            cyanDark: '#0099D4',
                            cyanDeep: '#007AA8',
                            cyanLight: '#E8F7FD',
                            cyanHover: '#1A95C2',
                            orange: '#EF6614',
                            orangeHover: '#D9530F',
                            iceBlue: '#F4FBFF',
                            iceBorder: '#C8E6F5',
                            charcoal: '#1A1A1A',
                            muted: '#5E6D77',
                            lightBg: '#F8FAFC',
                            cardBorder: '#E5E9EF',
                            green: '#00A859'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        poppins: ['Poppins', 'sans-serif']
                    },
                    boxShadow: {
                        'savaari': '0 4px 20px -2px rgba(32, 168, 217, 0.15)',
                        'card': '0 2px 12px 0 rgba(0, 0, 0, 0.06)',
                        'floating': '0 12px 36px 0 rgba(0, 0, 0, 0.12)',
                        'orange': '0 4px 14px 0 rgba(239, 102, 20, 0.35)'
                    }
                }
            }
        }
    </script>
    
    <!-- Custom Savaari Micro-styles -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F8FAFC;
            color: #1A1A1A;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #F1F5F9; }
        ::-webkit-scrollbar-thumb { background: #C8E6F5; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #20A8D9; }

        /* Savaari input container styling */
        .savaari-input-box {
            background-color: #F4FBFF;
            border: 1px solid #C8E6F5;
            transition: all 0.2s ease-in-out;
        }
        .savaari-input-box:focus-within {
            border-color: #20A8D9;
            box-shadow: 0 0 0 3px rgba(32, 168, 217, 0.18);
            background-color: #FFFFFF;
        }
        /* Active Tab indicator */
        .tab-active {
            background-color: #20A8D9 !important;
            color: #FFFFFF !important;
            border-color: #20A8D9 !important;
        }
        /* Laurel trust badge styling */
        .laurel-pill {
            background: linear-gradient(135deg, #FFFFFF 0%, #F4FBFF 100%);
            border: 1px solid #C8E6F5;
            box-shadow: 0 4px 16px rgba(32, 168, 217, 0.08);
        }

        /* ===== MOBILE RESPONSIVE OVERHAUL ===== */
        /* Trip type tabs — single horizontal scrollable row */
        .trip-tabs-row {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            gap: 8px;
            padding-bottom: 12px;
            margin-bottom: 14px;
            border-bottom: 1px solid #E2E8F0;
        }
        .trip-tabs-row::-webkit-scrollbar { display: none; }
        .trip-tabs-row button {
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* Compact form inputs on mobile */
        @media (max-width: 640px) {
            #booking-widget {
                padding: 14px !important;
                border-radius: 16px !important;
            }
            .savaari-input-box {
                padding: 9px 10px !important;
            }
            .savaari-input-box label {
                font-size: 9px !important;
                margin-bottom: 2px !important;
            }
            .savaari-input-box input {
                font-size: 13px !important;
            }
            #home .text-3xl {
                font-size: 1.5rem !important;
                line-height: 1.25 !important;
            }
            #home p.text-sm {
                font-size: 11px !important;
                margin-bottom: 16px !important;
            }
            /* Quick routes — wrap nicely */
            #booking-widget .mt-4 span,
            #booking-widget .mt-4 button {
                font-size: 10px !important;
            }
            /* Make search button slightly smaller */
            #search-cab-form button[type=submit] {
                padding-top: 12px !important;
                padding-bottom: 12px !important;
                font-size: 13px !important;
            }
            /* Advance booking notice text */
            #search-cab-form .text-\[11px\] {
                font-size: 10px !important;
            }
            /* Hero section top padding reduction */
            #home {
                padding-top: 6px !important;
                padding-bottom: 60px !important;
            }
        }
    </style>
    
    <!-- Structured Data Schema for Local Business -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "TaxiService",
      "name": "<?php echo htmlspecialchars($company_name); ?>",
      "image": "https://avinashtaxi.com/uploads/logo/logo.png",
      "telephone": "<?php echo htmlspecialchars($phone); ?>",
      "email": "<?php echo htmlspecialchars($email); ?>",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "<?php echo htmlspecialchars($address); ?>",
        "addressLocality": "Bilaspur",
        "addressRegion": "Chhattisgarh",
        "postalCode": "495001",
        "addressCountry": "IN"
      },
      "url": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>",
      "priceRange": "₹₹",
      "areaServed": "India",
      "providerMobility": "dynamic"
    }
    </script>
</head>
<body class="bg-[#F8FAFC] text-[#1A1A1A] antialiased">

    <!-- Top Announcement Bar (Savaari Subtle Top Bar) -->
    <div class="bg-[#E8F7FD] border-b border-[#C8E6F5] py-1.5 px-4 text-xs text-[#007AA8] hidden sm:block">
        <div class="container mx-auto flex justify-between items-center font-medium">
            <div class="flex items-center gap-2">
                <span class="bg-[#20A8D9] text-white text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">Top Rated</span>
                <span>Chhattisgarh's Premier Outstation Cab & Fixed Route Transfer Network</span>
            </div>
            <div class="flex items-center gap-4 text-[11px]">
                <a href="#one-way-fares" class="hover:underline flex items-center gap-1 font-semibold text-[#0099D4]">
                    <i class="fa-solid fa-tags"></i> One-Way Fares from ₹2,200/-
                </a>
                <span>•</span>
                <a href="#partner" class="hover:underline text-slate-600">Attach Cab / Drive With Us</a>
                <span>•</span>
                <a href="/customer/login.php" class="hover:underline text-slate-600">Customer Login</a>
            </div>
        </div>
    </div>

    <!-- Header Section (Savaari Clean White Header with Split 24x7 Phone Pill) -->
    <header id="main-header" class="sticky top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-100 shadow-sm transition-all duration-200">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <!-- Brand Logo -->
            <a href="#home" class="flex items-center space-x-3 group">
                <div class="px-2 py-1 flex items-center transition-transform duration-200 group-hover:scale-105">
                    <img src="uploads/logo/logo.png" onerror="this.onerror=null; this.src='https://avinashtaxi.com/uploads/logo/logo.png';" alt="<?php echo htmlspecialchars($company_name); ?>" class="h-10 sm:h-12 w-auto object-contain">
                </div>
            </a>
            
            <!-- Desktop Navigation Links -->
            <nav class="hidden lg:flex items-center space-x-5 text-sm font-semibold text-slate-700">
                <a href="#home" class="hover:text-[#20A8D9] transition-colors">Home</a>
                <a href="#one-way-fares" class="text-[#0099D4] font-bold hover:text-[#20A8D9] transition-colors flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>One-Way Fares
                </a>
                <a href="#services" class="hover:text-[#20A8D9] transition-colors">Services</a>
                <a href="#packages" class="hover:text-[#20A8D9] text-[#EF6614] font-bold transition-colors flex items-center gap-1">
                    <i class="fa-solid fa-gift text-xs"></i> Packages
                </a>
                <a href="#vehicles" class="hover:text-[#20A8D9] transition-colors">Fleet</a>
                <a href="#offers" class="hover:text-[#20A8D9] transition-colors">Offers</a>
                <a href="#testimonials" class="hover:text-[#20A8D9] transition-colors">Reviews</a>
                <a href="#faq" class="hover:text-[#20A8D9] transition-colors">FAQs</a>
                <a href="#about" class="hover:text-[#20A8D9] transition-colors">About</a>
                <a href="#contact" class="hover:text-[#20A8D9] transition-colors">Contact</a>
            </nav>

            <!-- Call-to-Actions (Savaari Signature Split Phone Pill + WhatsApp) -->
            <div class="hidden lg:flex items-center space-x-3">
                <!-- WhatsApp button -->
                <a href="https://wa.me/<?php echo $whatsapp; ?>?text=Hello%20Avinash%20Travels,%20I%20would%20like%20to%20inquire%20about%20a%20cab%20booking." target="_blank" class="flex items-center justify-center w-9 h-9 rounded-full bg-emerald-50 hover:bg-emerald-100 text-emerald-600 border border-emerald-200 transition-all" title="Chat on WhatsApp">
                    <i class="fa-brands fa-whatsapp text-lg"></i>
                </a>

                <!-- Signature Savaari Split 24x7 Telephone Pill -->
                <div class="inline-flex items-center rounded-full border border-[#20A8D9]/40 bg-white overflow-hidden shadow-sm hover:shadow transition-all">
                    <span class="px-3 py-1.5 text-xs font-bold text-slate-600 bg-slate-50 border-r border-[#20A8D9]/20 flex items-center gap-1">
                        <i class="fa-solid fa-headset text-[#20A8D9]"></i> 24x7
                    </span>
                    <a href="tel:<?php echo $phone; ?>" class="px-3.5 py-1.5 text-xs font-bold text-white bg-[#20A8D9] hover:bg-[#0099D4] transition-colors flex items-center gap-1.5">
                        <i class="fa-solid fa-phone text-[10px]"></i> Mobile <?php echo htmlspecialchars($phone); ?>
                    </a>
                </div>

                <!-- Customer Portal Button -->
                <a href="/customer/login.php" class="px-3.5 py-1.5 rounded-full border border-[#20A8D9] text-[#20A8D9] hover:bg-[#20A8D9] hover:text-white text-xs font-bold transition-all">
                    Sign In
                </a>

                <!-- Book Now CTA -->
                <a href="#booking-widget" class="px-4 py-1.5 rounded-full bg-[#EF6614] hover:bg-[#D9530F] text-white text-xs font-bold transition-all shadow-savaari">
                    Book Cab
                </a>
            </div>

            <!-- Mobile Hamburger Menu Button -->
            <div class="lg:hidden flex items-center space-x-2">
                <a href="tel:<?php echo $phone; ?>" class="flex items-center justify-center w-8 h-8 rounded-full bg-[#E8F7FD] text-[#0099D4] border border-[#C8E6F5]" title="Call Mobile 8120868818">
                    <i class="fa-solid fa-phone text-xs"></i>
                </a>
                <button id="menu-btn" class="text-slate-800 hover:text-[#20A8D9] p-2 focus:outline-none">
                    <i class="fa-solid fa-bars text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobile-menu" class="hidden lg:hidden bg-white border-t border-slate-100 px-4 py-4 space-y-3 shadow-lg">
            <a href="#home" class="block text-slate-700 font-medium hover:text-[#20A8D9] py-1" onclick="toggleMenu()">Home</a>
            <a href="#one-way-fares" class="block text-[#0099D4] font-bold hover:text-[#20A8D9] py-1 flex items-center gap-2" onclick="toggleMenu()">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>One-Way Fares
            </a>
            <a href="#services" class="block text-slate-700 font-medium hover:text-[#20A8D9] py-1" onclick="toggleMenu()">Services</a>
            <a href="#packages" class="block text-[#EF6614] font-bold hover:text-[#20A8D9] py-1 flex items-center gap-2" onclick="toggleMenu()">
                <i class="fa-solid fa-gift text-xs"></i> Packages & Tours
            </a>
            <a href="#vehicles" class="block text-slate-700 font-medium hover:text-[#20A8D9] py-1" onclick="toggleMenu()">Fleet</a>
            <a href="#offers" class="block text-slate-700 font-medium hover:text-[#20A8D9] py-1" onclick="toggleMenu()">Offers</a>
            <a href="#testimonials" class="block text-slate-700 font-medium hover:text-[#20A8D9] py-1" onclick="toggleMenu()">Customer Reviews</a>
            <a href="#faq" class="block text-slate-700 font-medium hover:text-[#20A8D9] py-1" onclick="toggleMenu()">FAQs</a>
            <a href="#about" class="block text-slate-700 font-medium hover:text-[#20A8D9] py-1" onclick="toggleMenu()">About Us</a>
            <a href="#contact" class="block text-slate-700 font-medium hover:text-[#20A8D9] py-1" onclick="toggleMenu()">Contact</a>
            <div class="grid grid-cols-2 gap-2 pt-3 border-t border-slate-100">
                <a href="tel:<?php echo $phone; ?>" class="flex items-center justify-center py-2 bg-[#E8F7FD] text-[#0099D4] border border-[#C8E6F5] rounded-lg font-bold text-xs">
                    <i class="fa-solid fa-phone mr-1.5 text-xs"></i> 8120868818
                </a>
                <a href="https://wa.me/<?php echo $whatsapp; ?>" class="flex items-center justify-center py-2 bg-emerald-500 text-white rounded-lg font-bold text-xs">
                    <i class="fa-brands fa-whatsapp mr-1.5 text-sm"></i> WhatsApp
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section with Scenic Highway Banner & Savaari Floating Card -->
    <section id="home" class="relative pt-6 sm:pt-12 pb-16 sm:pb-32 bg-cover bg-center" style="background-image: linear-gradient(rgba(10, 25, 47, 0.70), rgba(10, 25, 47, 0.80)), url('https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=1920&q=80');">
        <div class="container mx-auto px-4 text-center text-white relative z-10">
            <!-- Top Tagline Badge — hidden on very small screens to save space -->
            <div class="hidden sm:inline-flex items-center gap-2 bg-white/10 backdrop-blur-md border border-white/20 rounded-full px-4 py-1.5 mb-3 text-xs font-semibold text-cyan-200">
                <span class="w-2 h-2 rounded-full bg-[#20A8D9]"></span>
                SERVICES ACROSS CHHATTISGARH & CENTRAL INDIA
            </div>

            <!-- Bold Uppercase Hero Heading -->
            <h1 class="text-2xl sm:text-5xl lg:text-6xl font-extrabold font-poppins tracking-tight uppercase max-w-4xl mx-auto leading-tight mb-2 sm:mb-3">
                Reliable & Safe Cabs at <span class="text-[#20A8D9]">Affordable Prices</span>
            </h1>
            <p class="text-slate-200 text-xs sm:text-base max-w-2xl mx-auto mb-5 sm:mb-10 font-medium">
                Verified Chauffeurs • Fixed One-Way Rates • Clean AC Sedans & Ertiga Fleets • 24x7 Customer Support
            </p>

            <!-- Savaari Signature Floating White Booking Search Card -->
            <div id="booking-widget" class="bg-white text-slate-800 rounded-2xl shadow-floating border border-slate-100 p-4 sm:p-8 max-w-4xl mx-auto text-left relative z-20">
                
                <!-- Segmented Tabs — Single horizontal scrollable row on all screen sizes -->
                <div class="trip-tabs-row">
                    <button type="button" id="btn-tab-oneway" class="tab-active px-4 py-2 rounded-full font-bold text-xs tracking-wide uppercase transition-all shadow-sm flex items-center gap-1.5" onclick="switchSearchTab('oneway')">
                        <i class="fa-solid fa-arrow-right text-[10px]"></i> ONE WAY
                    </button>
                    <button type="button" id="btn-tab-roundtrip" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-full font-bold text-xs tracking-wide uppercase transition-all flex items-center gap-1.5" onclick="switchSearchTab('roundtrip')">
                        <i class="fa-solid fa-arrow-right-arrow-left text-[10px]"></i> ROUND TRIP
                    </button>
                    <button type="button" id="btn-tab-local" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-full font-bold text-xs tracking-wide uppercase transition-all flex items-center gap-1.5" onclick="switchSearchTab('local')">
                        <i class="fa-solid fa-city text-[10px]"></i> LOCAL RENTAL
                    </button>
                    <button type="button" id="btn-tab-airport" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-full font-bold text-xs tracking-wide uppercase transition-all flex items-center gap-1.5" onclick="switchSearchTab('airport')">
                        <i class="fa-solid fa-plane-departure text-[10px]"></i> AIRPORT DROP
                    </button>
                </div>

                <!-- Booking Search Form -->
                <form id="search-cab-form" onsubmit="handleSearchSubmit(event)">
                    <input type="hidden" name="trip_type" id="search-trip-type" value="one_way">

                    <!-- FROM + Swap + TO row — stacked on mobile, side-by-side on md+ -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 mb-3 items-stretch">
                        <!-- FROM Location -->
                        <div class="md:col-span-5 savaari-input-box rounded-xl p-2.5 sm:p-3" id="box-from-city">
                            <label class="block text-[10px] sm:text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-0.5 flex items-center gap-1.5" id="label-from-title">
                                <i class="fa-solid fa-location-crosshairs text-[#20A8D9]"></i> <span id="label-from-txt">FROM (Pickup City)</span>
                            </label>
                            <input type="text" name="pickup_city" id="search-from" list="cities-list" placeholder="Enter pickup city (e.g. Raipur)" class="w-full bg-transparent text-sm font-semibold text-slate-900 focus:outline-none placeholder:text-slate-400 placeholder:font-normal" required>
                        </div>

                        <!-- Circular Swap Button -->
                        <div class="md:col-span-2 flex justify-center items-center" id="box-swap-btn">
                            <button type="button" onclick="swapCities()" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-[#E8F7FD] hover:bg-[#20A8D9] text-[#20A8D9] hover:text-white border border-[#C8E6F5] flex items-center justify-center transition-all shadow-sm group" title="Swap From and To">
                                <i class="fa-solid fa-right-left text-xs group-hover:rotate-180 transition-transform duration-300"></i>
                            </button>
                        </div>

                        <!-- TO Location (for One Way & Round Trip) -->
                        <div class="md:col-span-5 savaari-input-box rounded-xl p-2.5 sm:p-3" id="box-to-city">
                            <label class="block text-[10px] sm:text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-0.5 flex items-center gap-1.5">
                                <i class="fa-solid fa-location-dot text-[#EF6614]"></i> TO (Drop City)
                            </label>
                            <input type="text" name="drop_city" id="search-to" list="cities-list" placeholder="Enter drop city (e.g. Bilaspur)" class="w-full bg-transparent text-sm font-semibold text-slate-900 focus:outline-none placeholder:text-slate-400 placeholder:font-normal" required>
                        </div>

                        <!-- Local Rental Package Dropdown (for Local Rental tab) -->
                        <div class="md:col-span-5 savaari-input-box rounded-xl p-2.5 sm:p-3 hidden" id="box-local-package">
                            <label class="block text-[10px] sm:text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-0.5 flex items-center gap-1.5">
                                <i class="fa-solid fa-clock text-[#EF6614]"></i> SELECT RENTAL PACKAGE
                            </label>
                            <select name="local_package" id="search-local-package" class="w-full bg-transparent text-sm font-semibold text-slate-900 focus:outline-none cursor-pointer">
                                <option value="8 Hours / 80 KMs" selected>8 Hours / 80 KMs (Full Day City Tour)</option>
                                <option value="4 Hours / 40 KMs">4 Hours / 40 KMs (Half Day City Errand)</option>
                                <option value="12 Hours / 120 KMs">12 Hours / 120 KMs (Extended Day Rental)</option>
                            </select>
                        </div>

                        <!-- Airport Service Dropdown (for Airport tab) -->
                        <div class="md:col-span-5 savaari-input-box rounded-xl p-2.5 sm:p-3 hidden" id="box-airport-route">
                            <label class="block text-[10px] sm:text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-0.5 flex items-center gap-1.5">
                                <i class="fa-solid fa-plane-departure text-[#EF6614]"></i> AIRPORT SERVICE
                            </label>
                            <select name="airport_service" id="search-airport-service" class="w-full bg-transparent text-sm font-semibold text-slate-900 focus:outline-none cursor-pointer">
                                <option value="Swami Vivekananda Airport Raipur (RPR)" selected>Drop to Swami Vivekananda Airport Raipur (RPR)</option>
                                <option value="Pickup: Swami Vivekananda Airport Raipur (RPR)">Pickup from Swami Vivekananda Airport Raipur (RPR)</option>
                                <option value="Veer Surendra Sai Airport Jharsuguda (JRG)">Drop to Jharsuguda Airport (JRG)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Datalist for Cities -->
                    <datalist id="cities-list">
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>">
                        <?php endforeach; ?>
                    </datalist>

                    <!-- Date / Time / Coupon — 2 cols on mobile, 3 cols on sm+ -->
                    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 gap-2 mb-2">
                        <!-- Pick Up Date -->
                        <div class="savaari-input-box rounded-xl p-2.5 sm:p-3">
                            <label class="block text-[10px] sm:text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-0.5 flex items-center justify-between">
                                <span class="flex items-center gap-1"><i class="fa-regular fa-calendar text-[#20A8D9]"></i> PICK UP DATE</span>
                                <span class="text-[9px] text-emerald-600 font-bold hidden sm:inline">Book Anytime 24/7</span>
                            </label>
                            <input type="date" name="pickup_date" id="search-date" class="w-full bg-transparent text-xs sm:text-sm font-semibold text-slate-900 focus:outline-none" min="<?php echo date('Y-m-d'); ?>" required onchange="validateBookingDateTime(true)">
                        </div>

                        <!-- Pick Up Time -->
                        <div class="savaari-input-box rounded-xl p-2.5 sm:p-3">
                            <label class="block text-[10px] sm:text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-0.5 flex items-center justify-between">
                                <span class="flex items-center gap-1"><i class="fa-regular fa-clock text-[#20A8D9]"></i> PICK UP TIME</span>
                                <span class="text-[9px] text-sky-600 font-bold hidden sm:inline">Instant / Scheduled</span>
                            </label>
                            <input type="time" name="pickup_time" id="search-time" class="w-full bg-transparent text-xs sm:text-sm font-semibold text-slate-900 focus:outline-none" required onchange="validateBookingDateTime(true)">
                        </div>

                        <!-- Coupon Code — full width on mobile (spans 2 cols) -->
                        <div class="savaari-input-box rounded-xl p-2.5 sm:p-3 col-span-2 md:col-span-1">
                            <label class="block text-[10px] sm:text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-0.5 flex items-center gap-1">
                                <i class="fa-solid fa-tag text-[#20A8D9]"></i> COUPON CODE
                            </label>
                            <input type="text" name="coupon_code" id="search-coupon" placeholder="Optional (e.g. FLAT10)" class="w-full bg-transparent text-xs sm:text-sm font-semibold text-slate-900 focus:outline-none placeholder:text-slate-400 placeholder:font-normal uppercase">
                        </div>
                    </div>

                    <!-- 24x7 Any-Time Booking Helper Notice -->
                    <div class="flex items-center gap-1.5 text-[11px] text-slate-500 font-semibold mb-4 px-1">
                        <i class="fa-solid fa-bolt text-amber-500"></i>
                        <span>Book anytime 24/7: Instant pickups and scheduled rides available across all routes.</span>
                    </div>

                    <!-- Return fields (Visible only for Round Trip) -->
                    <div id="roundtrip-fields" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5 pt-2 border-t border-slate-100">
                        <div class="savaari-input-box rounded-xl p-3">
                            <label class="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                                <i class="fa-regular fa-calendar text-[#20A8D9]"></i> RETURN DATE
                            </label>
                            <input type="date" name="return_date" id="search-return-date" class="w-full bg-transparent text-sm font-semibold text-slate-900 focus:outline-none" min="<?php echo date('Y-m-d'); ?>" onchange="validateBookingDateTime(true)">
                        </div>
                        <div class="savaari-input-box rounded-xl p-3">
                            <label class="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                                <i class="fa-regular fa-clock text-[#20A8D9]"></i> RETURN TIME
                            </label>
                            <input type="time" name="return_time" id="search-return-time" class="w-full bg-transparent text-sm font-semibold text-slate-900 focus:outline-none" onchange="validateBookingDateTime(true)">
                        </div>
                    </div>

                    <!-- Big Vivid Orange EXPLORE CABS Primary CTA Button (Savaari Signature) -->
                    <button type="submit" class="w-full py-4 bg-[#EF6614] hover:bg-[#D9530F] text-white font-extrabold rounded-xl text-base tracking-wider uppercase transition-all shadow-orange flex items-center justify-center gap-2 group">
                        <span id="search-btn-txt" class="flex items-center gap-2">
                            EXPLORE CABS <i class="fa-solid fa-arrow-right text-sm group-hover:translate-x-1 transition-transform"></i>
                        </span>
                        <span id="search-btn-spinner" class="hidden flex items-center gap-2">
                            <i class="fa-solid fa-spinner animate-spin"></i> FINDING BEST CABS...
                        </span>
                    </button>
                </form>

                <!-- Quick route suggestions under the card -->
                <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between text-xs text-slate-500 gap-2">
                    <span class="font-semibold text-slate-600"><i class="fa-solid fa-bolt text-amber-500 mr-1"></i> Popular 1-Click Routes:</span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="quickBookOneWay('Bilaspur', 'Raipur')" class="hover:text-[#20A8D9] hover:underline font-medium">Bilaspur ↔ Raipur (₹2,500)</button>
                        <span>•</span>
                        <button type="button" onclick="quickBookOneWay('Bilaspur', 'Raigarh')" class="hover:text-[#20A8D9] hover:underline font-medium">Bilaspur ↔ Raigarh (₹3,000)</button>
                        <span>•</span>
                        <button type="button" onclick="quickBookOneWay('Korba', 'Bilaspur')" class="hover:text-[#20A8D9] hover:underline font-medium">Korba ↔ Bilaspur (₹2,200)</button>
                    </div>
                </div>
            </div>

            <!-- Floating Laurel Trust Reviews Pill (Savaari Hero Signature) -->
            <div class="mt-8 inline-flex flex-wrap items-center justify-center gap-4 sm:gap-8 laurel-pill px-6 py-3 rounded-full text-slate-700 shadow-md">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-award text-amber-500 text-lg"></i>
                    <span class="text-xs sm:text-sm font-bold">India's Top Rated Cab Service</span>
                </div>
                <div class="hidden sm:block w-px h-5 bg-slate-200"></div>
                <div class="flex items-center gap-1.5 text-xs font-semibold">
                    <i class="fa-brands fa-apple text-slate-800"></i>
                    <span>App Store</span>
                    <span class="text-amber-500 font-bold">4.8 ★</span>
                </div>
                <div class="hidden sm:block w-px h-5 bg-slate-200"></div>
                <div class="flex items-center gap-1.5 text-xs font-semibold">
                    <i class="fa-brands fa-google-play text-emerald-600"></i>
                    <span>Play Store</span>
                    <span class="text-amber-500 font-bold">4.9 ★</span>
                </div>
                <div class="hidden sm:block w-px h-5 bg-slate-200"></div>
                <div class="flex items-center gap-1.5 text-xs font-semibold">
                    <i class="fa-solid fa-shield-check text-[#20A8D9]"></i>
                    <span>50,000+ Verified Trips</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Search Results Display Anchor -->
    <section id="results-section" class="hidden py-12 bg-white border-t border-b border-slate-200 scroll-mt-20">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 pb-4 border-b border-slate-200 gap-4">
                <div>
                    <span class="text-xs font-bold text-[#20A8D9] uppercase tracking-wider">REAL-TIME INVENTORY</span>
                    <h2 class="text-2xl font-extrabold text-slate-900 font-poppins">Available Vehicle Categories</h2>
                    <p class="text-sm text-slate-500">Showing vehicles for <span id="txt-search-route" class="text-[#0099D4] font-bold"></span></p>
                </div>
                
                <!-- Filters & Sorting controls -->
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <select id="filter-type" onchange="applyFilters()" class="flex-1 sm:flex-initial bg-[#F4FBFF] border border-[#C8E6F5] text-slate-700 text-xs font-semibold rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-[#20A8D9]">
                        <option value="">All Categories</option>
                        <option value="Sedan">Sedan Only (Dzire)</option>
                        <option value="MPV">MPV Only (Ertiga)</option>
                        <option value="SUV">SUV Only (Innova)</option>
                        <option value="Luxury">Luxury Only</option>
                    </select>
                    
                    <select id="sort-price" onchange="applyFilters()" class="flex-1 sm:flex-initial bg-[#F4FBFF] border border-[#C8E6F5] text-slate-700 text-xs font-semibold rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-[#20A8D9]">
                        <option value="recommended">Sort: Recommended</option>
                        <option value="low_high">Price: Low to High</option>
                        <option value="high_low">Price: High to Low</option>
                    </select>
                </div>
            </div>

            <!-- Results container -->
            <div id="results-loader" class="hidden text-center py-16">
                <i class="fa-solid fa-circle-notch animate-spin text-4xl text-[#20A8D9]"></i>
                <p class="text-slate-500 text-sm mt-3 font-semibold">Calculating best route rates & fleet availability...</p>
            </div>
            
            <div id="results-container">
                <!-- AJAX results will be injected here -->
            </div>
        </div>
    </section>

    <!-- 3 Savaari Promo Banners Section -->
    <section class="py-12 bg-[#F8FAFC]">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Banner 1: Book Now at zero cost -->
                <div class="bg-white border border-[#E5E9EF] rounded-2xl p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-[#E8F7FD] rounded-bl-full -z-0 group-hover:scale-110 transition-transform"></div>
                    <div class="relative z-10">
                        <span class="inline-block bg-[#E8F7FD] text-[#007AA8] text-[10px] font-extrabold px-2.5 py-1 rounded-full uppercase tracking-wider mb-3">
                            <i class="fa-solid fa-wallet mr-1"></i> Pay Later
                        </span>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Book Now at Zero Cost</h3>
                        <p class="text-xs text-slate-500 leading-relaxed mb-4">Reserve your confirmed cab now without paying any advance. Pay smoothly upon trip completion directly to your chauffeur.</p>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between relative z-10">
                        <span class="text-xs font-bold text-[#0099D4]">Zero Cancellation Fee*</span>
                        <a href="#booking-widget" class="text-xs font-bold text-[#EF6614] hover:underline flex items-center gap-1">
                            Book Ride <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>

                <!-- Banner 2: Exclusive Tour Packages -->
                <div class="bg-white border border-[#E5E9EF] rounded-2xl p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-amber-50 rounded-bl-full -z-0 group-hover:scale-110 transition-transform"></div>
                    <div class="relative z-10">
                        <span class="inline-block bg-amber-50 text-amber-700 text-[10px] font-extrabold px-2.5 py-1 rounded-full uppercase tracking-wider mb-3">
                            <i class="fa-solid fa-mountain-sun mr-1"></i> Special Tours
                        </span>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Exclusive Tour Packages</h3>
                        <p class="text-xs text-slate-500 leading-relaxed mb-4">Discover Chitrakote Falls, Bastar cultural trails, Sirpur monuments, and Barnawapara sanctuary with our curated multi-day tour itineraries.</p>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between relative z-10">
                        <span class="text-xs font-bold text-amber-600">Custom Group Fares</span>
                        <a href="#contact" class="text-xs font-bold text-[#EF6614] hover:underline flex items-center gap-1">
                            Explore Tours <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>

                <!-- Banner 3: Free Cancellations -->
                <div class="bg-white border border-[#E5E9EF] rounded-2xl p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-50 rounded-bl-full -z-0 group-hover:scale-110 transition-transform"></div>
                    <div class="relative z-10">
                        <span class="inline-block bg-emerald-50 text-emerald-700 text-[10px] font-extrabold px-2.5 py-1 rounded-full uppercase tracking-wider mb-3">
                            <i class="fa-solid fa-shield-halved mr-1"></i> 100% Flexibility
                        </span>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Free Cancellations</h3>
                        <p class="text-xs text-slate-500 leading-relaxed mb-4">Plans changed? Cancel for free up to 1 hour before scheduled pickup time with zero cancellation penalties or deductions.</p>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between relative z-10">
                        <span class="text-xs font-bold text-emerald-600">Instant Full Refund</span>
                        <a href="#booking-widget" class="text-xs font-bold text-[#EF6614] hover:underline flex items-center gap-1">
                            Plan Ahead <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- "WHAT SETS AVINASH TRAVELS APART?" (Savaari Signature Solid Cyan Banner) -->
    <section class="py-6 bg-[#F8FAFC]">
        <div class="container mx-auto px-4">
            <div class="bg-[#20A8D9] rounded-2xl text-white py-10 px-6 sm:px-12 shadow-lg">
                <div class="text-center mb-8">
                    <h2 class="text-2xl sm:text-3xl font-extrabold font-poppins uppercase tracking-wider text-white">
                        WHAT SETS <?php echo strtoupper(htmlspecialchars($company_name)); ?> APART?
                    </h2>
                    <div class="w-20 h-1 bg-white mx-auto mt-2 rounded-full"></div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 sm:gap-8 text-center">
                    <!-- Feature 1: Clean & Hygienic -->
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-white text-[#20A8D9] flex items-center justify-center text-2xl sm:text-3xl shadow-md mb-3 transition-transform hover:scale-110 duration-200">
                            <i class="fa-solid fa-car-side"></i>
                        </div>
                        <h4 class="font-bold text-sm sm:text-base leading-snug">Clean and<br>Hygienic Car</h4>
                        <p class="text-[11px] text-cyan-100 mt-1 hidden sm:block">Sanitized after every ride</p>
                    </div>

                    <!-- Feature 2: Transparent Billing -->
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-white text-[#20A8D9] flex items-center justify-center text-2xl sm:text-3xl shadow-md mb-3 transition-transform hover:scale-110 duration-200">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                        <h4 class="font-bold text-sm sm:text-base leading-snug">Transparent<br>Billing</h4>
                        <p class="text-[11px] text-cyan-100 mt-1 hidden sm:block">No surge or hidden charges</p>
                    </div>

                    <!-- Feature 3: Expert Chauffeurs -->
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-white text-[#20A8D9] flex items-center justify-center text-2xl sm:text-3xl shadow-md mb-3 transition-transform hover:scale-110 duration-200">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                        <h4 class="font-bold text-sm sm:text-base leading-snug">Expert<br>Chauffeurs</h4>
                        <p class="text-[11px] text-cyan-100 mt-1 hidden sm:block">Polite, route-tested drivers</p>
                    </div>

                    <!-- Feature 4: Wide Intercity Network -->
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-white text-[#20A8D9] flex items-center justify-center text-2xl sm:text-3xl shadow-md mb-3 transition-transform hover:scale-110 duration-200">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </div>
                        <h4 class="font-bold text-sm sm:text-base leading-snug">Wide Intercity<br>Network</h4>
                        <p class="text-[11px] text-cyan-100 mt-1 hidden sm:block">Across 50+ Cities in Central India</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- "OUR SERVICES" Section with Floating Circular Cyan Badges & Micro-Badges -->
    <section id="services" class="py-16 bg-white border-t border-slate-100">
        <div class="container mx-auto px-4">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="text-xs font-bold text-[#20A8D9] uppercase tracking-wider">COMPREHENSIVE MOBILITY</span>
                <h2 class="text-3xl font-extrabold font-poppins text-slate-900 tracking-tight mt-1 mb-2">
                    OUR SERVICES
                </h2>
                <p class="text-slate-500 text-sm">Flexible rental models crafted for outstation journeys, city errands, and airport arrivals.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Service 1: Roundtrip Cabs -->
                <div class="bg-white border border-[#E5E9EF] rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col justify-between group">
                    <div class="relative h-44 bg-slate-100 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=500&q=80" alt="Roundtrip Cabs" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <!-- Floating Circular Cyan Badge Icon -->
                        <div class="absolute bottom-3 left-4 w-11 h-11 rounded-full bg-[#20A8D9] text-white flex items-center justify-center text-lg shadow-md border-2 border-white">
                            <i class="fa-solid fa-repeat"></i>
                        </div>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-base text-slate-900 mb-1.5">ROUNDTRIP CABS</h3>
                            <p class="text-xs text-slate-500 leading-relaxed mb-4">Multi-day or same-day return cabs with an expert chauffeur for family holidays and business meetings.</p>
                        </div>
                        
                        <!-- 3 Circular Micro-Badges (Savaari Element) -->
                        <div>
                            <div class="flex items-center gap-2 mb-4 text-[10px] text-slate-600 font-semibold">
                                <span class="bg-[#F4FBFF] border border-[#C8E6F5] px-2 py-0.5 rounded-full flex items-center gap-1"><i class="fa-solid fa-check text-[#20A8D9]"></i> Multi-Stops</span>
                                <span class="bg-[#F4FBFF] border border-[#C8E6F5] px-2 py-0.5 rounded-full flex items-center gap-1"><i class="fa-solid fa-check text-[#20A8D9]"></i> Sightseeing</span>
                            </div>
                            <button onclick="switchSearchTab('roundtrip'); document.getElementById('booking-widget').scrollIntoView({behavior: 'smooth'})" class="w-full py-2 rounded-xl bg-[#F4FBFF] hover:bg-[#20A8D9] text-[#0099D4] hover:text-white border border-[#C8E6F5] text-xs font-bold transition-all">
                                Book Roundtrip
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Service 2: Oneway Drops -->
                <div class="bg-white border border-[#E5E9EF] rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col justify-between group">
                    <div class="relative h-44 bg-slate-100 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?auto=format&fit=crop&w=500&q=80" alt="One Way Drops" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute bottom-3 left-4 w-11 h-11 rounded-full bg-[#20A8D9] text-white flex items-center justify-center text-lg shadow-md border-2 border-white">
                            <i class="fa-solid fa-arrow-right"></i>
                        </div>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-base text-slate-900 mb-1.5">ONEWAY DROPS</h3>
                            <p class="text-xs text-slate-500 leading-relaxed mb-4">Pay strictly for one-way point-to-point drop. Zero return fare charges across all major Chhattisgarh corridors.</p>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-4 text-[10px] text-slate-600 font-semibold">
                                <span class="bg-[#F4FBFF] border border-[#C8E6F5] px-2 py-0.5 rounded-full flex items-center gap-1"><i class="fa-solid fa-check text-[#20A8D9]"></i> Zero Return</span>
                                <span class="bg-[#F4FBFF] border border-[#C8E6F5] px-2 py-0.5 rounded-full flex items-center gap-1"><i class="fa-solid fa-check text-[#20A8D9]"></i> Doorstep</span>
                            </div>
                            <button onclick="switchSearchTab('oneway'); document.getElementById('booking-widget').scrollIntoView({behavior: 'smooth'})" class="w-full py-2 rounded-xl bg-[#F4FBFF] hover:bg-[#20A8D9] text-[#0099D4] hover:text-white border border-[#C8E6F5] text-xs font-bold transition-all">
                                Book One Way
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Service 3: Local Rentals -->
                <div class="bg-white border border-[#E5E9EF] rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col justify-between group">
                    <div class="relative h-44 bg-slate-100 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1502877338535-766e1452684a?auto=format&fit=crop&w=500&q=80" alt="Local Hourly Rentals" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute bottom-3 left-4 w-11 h-11 rounded-full bg-[#20A8D9] text-white flex items-center justify-center text-lg shadow-md border-2 border-white">
                            <i class="fa-solid fa-city"></i>
                        </div>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-base text-slate-900 mb-1.5">LOCAL RENTALS</h3>
                            <p class="text-xs text-slate-500 leading-relaxed mb-4">Book packages from 4 hrs/40 km up to 8 hrs/80 km for city shopping, business meetings, and local events.</p>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-4 text-[10px] text-slate-600 font-semibold">
                                <span class="bg-[#F4FBFF] border border-[#C8E6F5] px-2 py-0.5 rounded-full flex items-center gap-1"><i class="fa-solid fa-check text-[#20A8D9]"></i> 4hr / 8hr</span>
                                <span class="bg-[#F4FBFF] border border-[#C8E6F5] px-2 py-0.5 rounded-full flex items-center gap-1"><i class="fa-solid fa-check text-[#20A8D9]"></i> City Errand</span>
                            </div>
                            <button onclick="switchSearchTab('local'); document.getElementById('booking-widget').scrollIntoView({behavior: 'smooth'})" class="w-full py-2 rounded-xl bg-[#F4FBFF] hover:bg-[#20A8D9] text-[#0099D4] hover:text-white border border-[#C8E6F5] text-xs font-bold transition-all">
                                Book Hourly
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Service 4: Airport Transfers -->
                <div class="bg-white border border-[#E5E9EF] rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col justify-between group">
                    <div class="relative h-44 bg-slate-100 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=500&q=80" alt="Airport Transfers" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute bottom-3 left-4 w-11 h-11 rounded-full bg-[#20A8D9] text-white flex items-center justify-center text-lg shadow-md border-2 border-white">
                            <i class="fa-solid fa-plane-departure"></i>
                        </div>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-base text-slate-900 mb-1.5">AIRPORT TRANSFERS</h3>
                            <p class="text-xs text-slate-500 leading-relaxed mb-4">Never miss a flight. Guaranteed punctual pickups to and from Swami Vivekananda Airport Raipur (RPR) & Jharsuguda Airport.</p>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-4 text-[10px] text-slate-600 font-semibold">
                                <span class="bg-[#F4FBFF] border border-[#C8E6F5] px-2 py-0.5 rounded-full flex items-center gap-1"><i class="fa-solid fa-check text-[#20A8D9]"></i> Flight Track</span>
                                <span class="bg-[#F4FBFF] border border-[#C8E6F5] px-2 py-0.5 rounded-full flex items-center gap-1"><i class="fa-solid fa-check text-[#20A8D9]"></i> Zero Delay</span>
                            </div>
                            <button onclick="switchSearchTab('airport'); document.getElementById('booking-widget').scrollIntoView({behavior: 'smooth'})" class="w-full py-2 rounded-xl bg-[#F4FBFF] hover:bg-[#20A8D9] text-[#0099D4] hover:text-white border border-[#C8E6F5] text-xs font-bold transition-all">
                                Book Airport Cab
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ONE-WAY CAB FARE DETAILS Section (Adapted to Savaari Light Theme) -->
    <section id="one-way-fares" class="py-16 bg-[#F8FAFC] relative border-t border-b border-slate-200 scroll-mt-20">
        <div class="container mx-auto px-4 relative z-10">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-10">
                <span class="inline-flex items-center gap-2 bg-[#E8F7FD] text-[#007AA8] border border-[#C8E6F5] text-xs px-3.5 py-1 rounded-full font-extrabold uppercase tracking-wider mb-3">
                    <i class="fa-solid fa-tags text-[#20A8D9]"></i> Guaranteed Fixed Route Fares
                </span>
                <h2 class="text-3xl md:text-4xl font-extrabold font-poppins text-slate-900 tracking-tight mb-2">
                    ONE-WAY CAB FARE DETAILS
                </h2>
                <p class="text-slate-500 text-sm md:text-base leading-relaxed">
                    Guaranteed fixed one-way taxi fares across major Chhattisgarh travel corridors. Clean AC cabs, verified professional drivers, no return fare charges.
                </p>
            </div>

            <!-- Route City Filter Chips -->
            <div class="flex flex-wrap justify-center items-center gap-2 mb-8">
                <button onclick="filterCorridors('all')" class="corridor-filter-btn px-4 py-2 rounded-full text-xs md:text-sm font-bold transition-all bg-[#20A8D9] text-white shadow-savaari" data-filter="all">
                    All Routes (6)
                </button>
                <button onclick="filterCorridors('bilaspur')" class="corridor-filter-btn px-4 py-2 rounded-full text-xs md:text-sm font-bold transition-all bg-white border border-slate-200 text-slate-700 hover:text-[#20A8D9] hover:border-[#20A8D9]" data-filter="bilaspur">
                    Bilaspur
                </button>
                <button onclick="filterCorridors('raipur')" class="corridor-filter-btn px-4 py-2 rounded-full text-xs md:text-sm font-bold transition-all bg-white border border-slate-200 text-slate-700 hover:text-[#20A8D9] hover:border-[#20A8D9]" data-filter="raipur">
                    Raipur
                </button>
                <button onclick="filterCorridors('raigarh')" class="corridor-filter-btn px-4 py-2 rounded-full text-xs md:text-sm font-bold transition-all bg-white border border-slate-200 text-slate-700 hover:text-[#20A8D9] hover:border-[#20A8D9]" data-filter="raigarh">
                    Raigarh
                </button>
                <button onclick="filterCorridors('korba')" class="corridor-filter-btn px-4 py-2 rounded-full text-xs md:text-sm font-bold transition-all bg-white border border-slate-200 text-slate-700 hover:text-[#20A8D9] hover:border-[#20A8D9]" data-filter="korba">
                    Korba
                </button>
                <button onclick="filterCorridors('jharsuguda')" class="corridor-filter-btn px-4 py-2 rounded-full text-xs md:text-sm font-bold transition-all bg-white border border-slate-200 text-slate-700 hover:text-[#20A8D9] hover:border-[#20A8D9]" data-filter="jharsuguda">
                    Jharsuguda
                </button>
            </div>

            <!-- Route Cards Grid (6 Corridors in Savaari Light Card Aesthetic) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10" id="corridor-cards-container">
                <?php foreach ($oneway_corridors as $corr): ?>
                    <div class="corridor-card bg-white border-t-4 border-t-[#20A8D9] border border-slate-200 rounded-2xl p-6 flex flex-col justify-between shadow-sm hover:shadow-lg transition-all duration-200" data-cities="<?php echo strtolower($corr['city_a'] . ' ' . $corr['city_b']); ?>">
                        <div>
                            <!-- Header Info -->
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-xs font-bold text-slate-600 bg-[#F4FBFF] border border-[#C8E6F5] px-2.5 py-1 rounded-md">
                                    <i class="fa-solid fa-route text-[#20A8D9] mr-1"></i> <?php echo $corr['distance_km']; ?> KM • ~<?php echo $corr['est_hours']; ?> Hrs
                                </span>
                                <?php if ($corr['toll_included'] && $corr['parking_included']): ?>
                                    <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full inline-flex items-center gap-1">
                                        <i class="fa-solid fa-circle-check text-[10px]"></i> Toll & Parking Inc.
                                    </span>
                                <?php elseif ($corr['toll_included']): ?>
                                    <span class="bg-blue-50 text-blue-700 border border-blue-200 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full inline-flex items-center gap-1">
                                        <i class="fa-solid fa-road text-[10px]"></i> Toll Included
                                    </span>
                                <?php else: ?>
                                    <span class="bg-slate-100 text-slate-600 border border-slate-200 text-[11px] font-medium px-2 py-0.5 rounded-full">
                                        Standard Rate
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Route Title -->
                            <h3 class="text-xl font-extrabold font-poppins text-slate-900 mb-4 flex items-center gap-2">
                                <span><?php echo htmlspecialchars($corr['city_a']); ?></span>
                                <i class="fa-solid fa-right-left text-[#20A8D9] text-sm"></i>
                                <span><?php echo htmlspecialchars($corr['city_b']); ?></span>
                            </h3>

                            <!-- Pricing Breakdown Box (Savaari Soft Ice-Blue Style) -->
                            <div class="space-y-3 mb-5">
                                <!-- Swift Dzire (Sedan) Tier -->
                                <div class="bg-[#F4FBFF] border border-[#C8E6F5] rounded-xl p-3.5 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-white border border-[#C8E6F5] flex items-center justify-center text-[#20A8D9] shadow-sm">
                                            <i class="fa-solid fa-car text-base"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 text-sm">Swift Dzire</div>
                                            <div class="text-[11px] text-slate-500">Sedan • 4 Seats • AC</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-extrabold text-[#0099D4]">₹<?php echo number_format($corr['dzire_fare']); ?>/-</div>
                                        <div class="text-[10px] text-slate-500 uppercase font-semibold">One Way</div>
                                    </div>
                                </div>

                                <!-- Ertiga (MPV) Tier -->
                                <div class="bg-[#F4FBFF] border border-[#C8E6F5] rounded-xl p-3.5 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-white border border-[#C8E6F5] flex items-center justify-center text-[#20A8D9] shadow-sm">
                                            <i class="fa-solid fa-van-shuttle text-base"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 text-sm">Maruti Ertiga</div>
                                            <div class="text-[11px] text-slate-500">MPV • 6+1 Seats • AC</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-extrabold text-[#0099D4]">₹<?php echo number_format($corr['ertiga_fare']); ?>/-</div>
                                        <div class="text-[10px] text-slate-500 uppercase font-semibold">One Way</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 1-Click Directional Booking Actions (Savaari Orange CTA) -->
                        <div class="pt-3 border-t border-slate-100">
                            <span class="block text-[11px] font-extrabold text-slate-500 uppercase mb-2">Book One-Way Direction:</span>
                            <div class="grid grid-cols-2 gap-2">
                                <button onclick="quickBookOneWay('<?php echo htmlspecialchars($corr['city_a']); ?>', '<?php echo htmlspecialchars($corr['city_b']); ?>')" class="px-2.5 py-2.5 rounded-xl bg-[#EF6614] hover:bg-[#D9530F] text-white font-bold text-xs transition-all flex items-center justify-center gap-1 shadow-sm">
                                    <span><?php echo htmlspecialchars($corr['city_a']); ?></span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    <span><?php echo htmlspecialchars($corr['city_b']); ?></span>
                                </button>
                                <button onclick="quickBookOneWay('<?php echo htmlspecialchars($corr['city_b']); ?>', '<?php echo htmlspecialchars($corr['city_a']); ?>')" class="px-2.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-800 font-bold text-xs border border-slate-300 transition-all flex items-center justify-center gap-1">
                                    <span><?php echo htmlspecialchars($corr['city_b']); ?></span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    <span><?php echo htmlspecialchars($corr['city_a']); ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Full Comparison Table Toggle Button -->
            <div class="text-center mb-10">
                <button onclick="toggleTableBreakdown()" id="toggle-table-btn" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-white border border-slate-300 hover:border-[#20A8D9] text-slate-700 hover:text-[#20A8D9] font-bold text-sm transition-all shadow-sm">
                    <i class="fa-solid fa-table text-[#20A8D9]"></i>
                    <span id="table-toggle-txt">View Complete Route Fare Matrix Table</span>
                    <i class="fa-solid fa-chevron-down text-xs transition-transform duration-300" id="table-toggle-icon"></i>
                </button>
            </div>

            <!-- Complete Route Fare Matrix Table (Collapsible Light Table) -->
            <div id="corridor-table-wrapper" class="hidden overflow-x-auto bg-white border border-slate-200 rounded-2xl shadow-md mb-12">
                <table class="w-full text-left text-sm text-slate-700">
                    <thead class="bg-[#F4FBFF] text-xs uppercase text-slate-600 border-b border-slate-200 font-bold">
                        <tr>
                            <th class="px-5 py-4">Corridor</th>
                            <th class="px-5 py-4">One-Way Route</th>
                            <th class="px-5 py-4 text-center">Swift Dzire</th>
                            <th class="px-5 py-4 text-center">Maruti Ertiga</th>
                            <th class="px-5 py-4 text-center">Toll & Parking</th>
                            <th class="px-5 py-4 text-right">Instant Booking</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php 
                        $directions = [
                            ['Bilaspur', 'Raipur', 'Bilaspur ↔ Raipur', 2500, 3000, 'Toll & Parking Included', 'emerald'],
                            ['Raipur', 'Bilaspur', 'Bilaspur ↔ Raipur', 2500, 3000, 'Toll & Parking Included', 'emerald'],
                            ['Bilaspur', 'Raigarh', 'Bilaspur ↔ Raigarh', 3000, 4500, 'Toll Included', 'blue'],
                            ['Raigarh', 'Bilaspur', 'Bilaspur ↔ Raigarh', 3000, 4500, 'Toll Included', 'blue'],
                            ['Raigarh', 'Raipur', 'Raigarh ↔ Raipur', 4500, 5500, 'Standard Rate', 'slate'],
                            ['Raipur', 'Raigarh', 'Raigarh ↔ Raipur', 4500, 5500, 'Standard Rate', 'slate'],
                            ['Raigarh', 'Jharsuguda', 'Raigarh ↔ Jharsuguda', 2500, 3500, 'Toll Included', 'blue'],
                            ['Jharsuguda', 'Raigarh', 'Raigarh ↔ Jharsuguda', 2500, 3500, 'Toll Included', 'blue'],
                            ['Korba', 'Bilaspur', 'Korba ↔ Bilaspur', 2200, 3500, 'Standard Rate', 'slate'],
                            ['Bilaspur', 'Korba', 'Korba ↔ Bilaspur', 2200, 3500, 'Standard Rate', 'slate'],
                            ['Korba', 'Raipur', 'Korba ↔ Raipur', 4500, 5500, 'Toll & Parking Included', 'emerald'],
                            ['Raipur', 'Korba', 'Korba ↔ Raipur', 4500, 5500, 'Toll & Parking Included', 'emerald']
                        ];
                        foreach ($directions as $row):
                            list($from, $to, $corr_name, $dzire_p, $ertiga_p, $badge_txt, $color) = $row;
                        ?>
                        <tr class="hover:bg-[#F8FAFC] transition-colors">
                            <td class="px-5 py-3.5 font-bold text-slate-900"><?php echo $corr_name; ?></td>
                            <td class="px-5 py-3.5">
                                <span class="text-slate-900 font-semibold"><?php echo $from; ?></span>
                                <i class="fa-solid fa-arrow-right text-[#20A8D9] mx-1.5 text-xs"></i>
                                <span class="text-slate-900 font-semibold"><?php echo $to; ?></span>
                            </td>
                            <td class="px-5 py-3.5 text-center font-bold text-[#0099D4]">₹<?php echo number_format($dzire_p); ?>/-</td>
                            <td class="px-5 py-3.5 text-center font-bold text-[#0099D4]">₹<?php echo number_format($ertiga_p); ?>/-</td>
                            <td class="px-5 py-3.5 text-center">
                                <?php if ($color === 'emerald'): ?>
                                    <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs px-2.5 py-0.5 rounded-full font-semibold">Toll & Parking Included</span>
                                <?php elseif ($color === 'blue'): ?>
                                    <span class="bg-blue-50 text-blue-700 border border-blue-200 text-xs px-2.5 py-0.5 rounded-full font-semibold">Toll Included</span>
                                <?php else: ?>
                                    <span class="bg-slate-100 text-slate-600 border border-slate-200 text-xs px-2.5 py-0.5 rounded-full">Standard</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button onclick="quickBookOneWay('<?php echo $from; ?>', '<?php echo $to; ?>')" class="px-3.5 py-1.5 rounded-lg bg-[#EF6614] hover:bg-[#D9530F] text-white font-bold text-xs transition-all shadow-sm">
                                    Book Now
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Value Guarantees Banner (Savaari Light Aesthetics) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto pt-6 border-t border-slate-200 text-center">
                <div class="p-3">
                    <i class="fa-solid fa-hand-holding-dollar text-2xl text-[#20A8D9] mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-900">No Return Fare</h4>
                    <p class="text-xs text-slate-500">Pay strictly for one-way travel</p>
                </div>
                <div class="p-3">
                    <i class="fa-solid fa-shield-halved text-2xl text-[#20A8D9] mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-900">Guaranteed Fixed Rates</h4>
                    <p class="text-xs text-slate-500">Zero surge or hidden fees</p>
                </div>
                <div class="p-3">
                    <i class="fa-solid fa-car-side text-2xl text-[#20A8D9] mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-900">Clean AC Fleet</h4>
                    <p class="text-xs text-slate-500">Dzire & Ertiga sanitized cabs</p>
                </div>
                <div class="p-3">
                    <i class="fa-solid fa-clock-rotate-left text-2xl text-[#20A8D9] mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-900">On-Time Pickup</h4>
                    <p class="text-xs text-slate-500">Prompt doorstep service</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PACKAGES & SIGHTSEEING TOURS SECTION (Savaari Light Theme) -->
    <section id="packages" class="py-16 bg-[#F8FAFC] border-t border-b border-slate-200 scroll-mt-20">
        <div class="container mx-auto px-4">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-10">
                <span class="inline-flex items-center gap-2 bg-[#E8F7FD] text-[#007AA8] border border-[#C8E6F5] text-xs px-3.5 py-1 rounded-full font-extrabold uppercase tracking-wider mb-3">
                    <i class="fa-solid fa-gift text-[#20A8D9]"></i> Transparent Rental Packages
                </span>
                <h2 class="text-3xl md:text-4xl font-extrabold font-poppins text-slate-900 tracking-tight mb-2">
                    PACKAGES & SIGHTSEEING TOURS
                </h2>
                <p class="text-slate-500 text-sm md:text-base leading-relaxed">
                    Choose from all-inclusive local hourly city rentals or curated sightseeing and pilgrimage tours across Chhattisgarh with zero hidden charges.
                </p>

                <!-- Dual Segmented Selector for Packages -->
                <div class="inline-flex p-1.5 bg-white border border-slate-200 rounded-full shadow-sm mt-6">
                    <button type="button" id="pkg-tab-local" onclick="switchPackageTab('local')" class="px-5 py-2 rounded-full font-bold text-xs uppercase tracking-wide transition-all bg-[#20A8D9] text-white shadow-sm flex items-center gap-1.5">
                        <i class="fa-solid fa-clock"></i> Local Hourly Packages
                    </button>
                    <button type="button" id="pkg-tab-tours" onclick="switchPackageTab('tours')" class="px-5 py-2 rounded-full font-bold text-xs uppercase tracking-wide transition-all text-slate-600 hover:text-slate-900 flex items-center gap-1.5">
                        <i class="fa-solid fa-mountain-sun text-[#EF6614]"></i> Sightseeing & Pilgrimages
                    </button>
                </div>
            </div>

            <!-- 1. LOCAL HOURLY PACKAGES VIEW -->
            <div id="pkg-view-local" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Card 1: 4 Hours / 40 KMs -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="bg-slate-100 text-slate-700 text-[11px] font-bold px-2.5 py-0.5 rounded-full">
                                <i class="fa-regular fa-clock mr-1 text-[#20A8D9]"></i> Half Day Rental
                            </span>
                            <span class="text-xs text-slate-400 font-semibold">City Limits</span>
                        </div>
                        <h3 class="text-2xl font-extrabold font-poppins text-slate-900 mb-1">4 Hours / 40 KMs</h3>
                        <p class="text-xs text-slate-500 mb-5 leading-relaxed">Ideal for shopping, doctor visits, hospital errands, and quick business meetings.</p>

                        <!-- Price Breakdown by Vehicle Tier -->
                        <div class="space-y-2.5 mb-5">
                            <div class="p-3 rounded-xl bg-[#F4FBFF] border border-[#C8E6F5] flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-car text-[#20A8D9]"></i>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">Swift Dzire (Sedan)</div>
                                        <div class="text-[10px] text-slate-500">4 Seats • Full AC</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-base font-extrabold text-[#0099D4]">₹1,200</span>
                                    <span class="text-[10px] text-slate-400 block font-medium">All-inc</span>
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-[#F4FBFF] border border-[#C8E6F5] flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-van-shuttle text-[#20A8D9]"></i>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">Maruti Ertiga (MPV)</div>
                                        <div class="text-[10px] text-slate-500">6+1 Seats • Family Size</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-base font-extrabold text-[#0099D4]">₹1,800</span>
                                    <span class="text-[10px] text-slate-400 block font-medium">All-inc</span>
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-[#F4FBFF] border border-[#C8E6F5] flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-truck-monster text-[#20A8D9]"></i>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">Innova Crysta (SUV)</div>
                                        <div class="text-[10px] text-slate-500">7 Seats • Premium</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-base font-extrabold text-[#0099D4]">₹2,200</span>
                                    <span class="text-[10px] text-slate-400 block font-medium">All-inc</span>
                                </div>
                            </div>
                        </div>

                        <!-- Highlights List -->
                        <div class="space-y-1.5 text-[11px] text-slate-600 mb-5">
                            <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-500"></i> Fuel & Chauffeur allowance included</div>
                            <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-500"></i> Extra Km: ₹12/km • Extra Hr: ₹120/hr</div>
                            <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-500"></i> Multiple city stops allowed</div>
                        </div>
                    </div>

                    <button onclick="bookLocalPackage('4 Hours / 40 KMs')" class="w-full py-3 rounded-xl bg-[#F4FBFF] hover:bg-[#20A8D9] text-[#0099D4] hover:text-white border border-[#C8E6F5] hover:border-[#20A8D9] font-bold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 shadow-sm">
                        <span>Book 4Hr Package</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </button>
                </div>

                <!-- Card 2: 8 Hours / 80 KMs (HIGHLIGHTED - MOST POPULAR) -->
                <div class="bg-white border-2 border-[#20A8D9] rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 flex flex-col justify-between relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-[#20A8D9] text-white text-[10px] font-extrabold px-3 py-1 rounded-bl-xl uppercase tracking-wider shadow-xs">
                        <i class="fa-solid fa-star mr-1 text-amber-300"></i> Most Booked
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold px-2.5 py-0.5 rounded-full">
                                <i class="fa-solid fa-bolt mr-1"></i> Full Day City Tour
                            </span>
                        </div>
                        <h3 class="text-2xl font-extrabold font-poppins text-slate-900 mb-1">8 Hours / 80 KMs</h3>
                        <p class="text-xs text-slate-500 mb-5 leading-relaxed">Perfect for full-day corporate duties, family shopping sprees, and comprehensive city tours.</p>

                        <!-- Price Breakdown by Vehicle Tier -->
                        <div class="space-y-2.5 mb-5">
                            <div class="p-3 rounded-xl bg-[#E8F7FD] border border-[#20A8D9]/40 flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-car text-[#20A8D9]"></i>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">Swift Dzire (Sedan)</div>
                                        <div class="text-[10px] text-slate-500">4 Seats • Full AC</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-base font-extrabold text-[#0099D4]">₹2,200</span>
                                    <span class="text-[10px] text-slate-400 block font-medium">All-inc</span>
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-[#E8F7FD] border border-[#20A8D9]/40 flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-van-shuttle text-[#20A8D9]"></i>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">Maruti Ertiga (MPV)</div>
                                        <div class="text-[10px] text-slate-500">6+1 Seats • Family Size</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-base font-extrabold text-[#0099D4]">₹3,000</span>
                                    <span class="text-[10px] text-slate-400 block font-medium">All-inc</span>
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-[#E8F7FD] border border-[#20A8D9]/40 flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-truck-monster text-[#20A8D9]"></i>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">Innova Crysta (SUV)</div>
                                        <div class="text-[10px] text-slate-500">7 Seats • Premium</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-base font-extrabold text-[#0099D4]">₹3,600</span>
                                    <span class="text-[10px] text-slate-400 block font-medium">All-inc</span>
                                </div>
                            </div>
                        </div>

                        <!-- Highlights List -->
                        <div class="space-y-1.5 text-[11px] text-slate-600 mb-5">
                            <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-500"></i> Fuel, tolls & driver allowance included</div>
                            <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-500"></i> Extra Km: ₹12/km • Extra Hr: ₹120/hr</div>
                            <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-500"></i> Unlimited pickup/drop points in city</div>
                        </div>
                    </div>

                    <button onclick="bookLocalPackage('8 Hours / 80 KMs')" class="w-full py-3 rounded-xl bg-[#EF6614] hover:bg-[#D9530F] text-white font-bold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 shadow-savaari">
                        <span>Book 8Hr Package</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </button>
                </div>

                <!-- Card 3: 12 Hours / 120 KMs -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="bg-slate-100 text-slate-700 text-[11px] font-bold px-2.5 py-0.5 rounded-full">
                                <i class="fa-solid fa-arrows-split-up-and-left mr-1 text-[#20A8D9]"></i> Extended Day
                            </span>
                            <span class="text-xs text-slate-400 font-semibold">City + Suburbs</span>
                        </div>
                        <h3 class="text-2xl font-extrabold font-poppins text-slate-900 mb-1">12 Hours / 120 KMs</h3>
                        <p class="text-xs text-slate-500 mb-5 leading-relaxed">Designed for weddings, day-long conferences, factory visits, and suburban travels.</p>

                        <!-- Price Breakdown by Vehicle Tier -->
                        <div class="space-y-2.5 mb-5">
                            <div class="p-3 rounded-xl bg-[#F4FBFF] border border-[#C8E6F5] flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-car text-[#20A8D9]"></i>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">Swift Dzire (Sedan)</div>
                                        <div class="text-[10px] text-slate-500">4 Seats • Full AC</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-base font-extrabold text-[#0099D4]">₹3,200</span>
                                    <span class="text-[10px] text-slate-400 block font-medium">All-inc</span>
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-[#F4FBFF] border border-[#C8E6F5] flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-van-shuttle text-[#20A8D9]"></i>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">Maruti Ertiga (MPV)</div>
                                        <div class="text-[10px] text-slate-500">6+1 Seats • Family Size</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-base font-extrabold text-[#0099D4]">₹4,200</span>
                                    <span class="text-[10px] text-slate-400 block font-medium">All-inc</span>
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-[#F4FBFF] border border-[#C8E6F5] flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <i class="fa-solid fa-truck-monster text-[#20A8D9]"></i>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">Innova Crysta (SUV)</div>
                                        <div class="text-[10px] text-slate-500">7 Seats • Premium</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-base font-extrabold text-[#0099D4]">₹4,800</span>
                                    <span class="text-[10px] text-slate-400 block font-medium">All-inc</span>
                                </div>
                            </div>
                        </div>

                        <!-- Highlights List -->
                        <div class="space-y-1.5 text-[11px] text-slate-600 mb-5">
                            <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-500"></i> 12 full hours of chauffeur standby</div>
                            <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-500"></i> Extra Km: ₹12/km • Extra Hr: ₹120/hr</div>
                            <div class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-500"></i> Zero night charges till 10 PM</div>
                        </div>
                    </div>

                    <button onclick="bookLocalPackage('12 Hours / 120 KMs')" class="w-full py-3 rounded-xl bg-[#F4FBFF] hover:bg-[#20A8D9] text-[#0099D4] hover:text-white border border-[#C8E6F5] hover:border-[#20A8D9] font-bold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 shadow-sm">
                        <span>Book 12Hr Package</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </button>
                </div>
            </div>

            <!-- 2. SIGHTSEEING & PILGRIMAGES VIEW (Initially Hidden) -->
            <div id="pkg-view-tours" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Tour 1: Chitrakote Falls & Bastar Eco-Tour -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group">
                    <div class="relative h-44 bg-slate-100 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=600&q=80" alt="Chitrakote Falls" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <span class="absolute top-3 left-3 bg-white/90 backdrop-blur-sm text-slate-900 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border border-slate-200 shadow-xs">
                            <i class="fa-solid fa-moon text-indigo-600 mr-1"></i> 2 Days / 1 Night
                        </span>
                        <span class="absolute bottom-3 right-3 bg-[#EF6614] text-white text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-sm">
                            From ₹7,500/-
                        </span>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-[#20A8D9] uppercase tracking-wider block mb-1">Scenic Waterfall & Tribal Trail</span>
                            <h3 class="font-bold text-base text-slate-900 mb-2">Chitrakote Falls & Bastar Tour</h3>
                            <p class="text-xs text-slate-500 mb-3 line-clamp-3">Witness the "Niagara of India", explore Teerathgarh cascade, tribal art markets, and Kanger Valley wildlife.</p>
                            <div class="bg-[#F8FAFC] p-2.5 rounded-xl border border-slate-100 mb-4 text-[11px] text-slate-600 space-y-1">
                                <div class="flex items-center gap-1.5"><i class="fa-solid fa-car text-[#20A8D9]"></i> Sedan: ₹7,500 | Ertiga: ₹10,500</div>
                                <div class="flex items-center gap-1.5"><i class="fa-solid fa-route text-slate-400"></i> Pickup from Raipur / Bilaspur</div>
                            </div>
                        </div>
                        <button onclick="bookTourPackage('Raipur', 'Chitrakote Falls, Bastar', 'round_trip')" class="w-full py-2.5 rounded-xl bg-[#20A8D9] hover:bg-[#0099D4] text-white font-bold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 shadow-sm">
                            <span>Book Bastar Tour</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                    </div>
                </div>

                <!-- Tour 2: Dongargarh Maa Bamleshwari Pilgrimage -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group">
                    <div class="relative h-44 bg-slate-100 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1582510003544-4d00b7f74220?auto=format&fit=crop&w=600&q=80" alt="Dongargarh Maa Bamleshwari" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <span class="absolute top-3 left-3 bg-white/90 backdrop-blur-sm text-slate-900 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border border-slate-200 shadow-xs">
                            <i class="fa-solid fa-sun text-amber-500 mr-1"></i> Same Day Return
                        </span>
                        <span class="absolute bottom-3 right-3 bg-[#EF6614] text-white text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-sm">
                            From ₹3,200/-
                        </span>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-[#20A8D9] uppercase tracking-wider block mb-1">Spiritual Pilgrimage</span>
                            <h3 class="font-bold text-base text-slate-900 mb-2">Dongargarh Temple Yatra</h3>
                            <p class="text-xs text-slate-500 mb-3 line-clamp-3">Sacred darshan at hilltop Maa Bamleshwari Devi with ropeway access, plus Pragyagiri Buddhist peace monument.</p>
                            <div class="bg-[#F8FAFC] p-2.5 rounded-xl border border-slate-100 mb-4 text-[11px] text-slate-600 space-y-1">
                                <div class="flex items-center gap-1.5"><i class="fa-solid fa-car text-[#20A8D9]"></i> Sedan: ₹3,200 | Ertiga: ₹4,200</div>
                                <div class="flex items-center gap-1.5"><i class="fa-solid fa-road text-slate-400"></i> Tolls & Parking Included</div>
                            </div>
                        </div>
                        <button onclick="bookTourPackage('Raipur', 'Dongargarh', 'round_trip')" class="w-full py-2.5 rounded-xl bg-[#20A8D9] hover:bg-[#0099D4] text-white font-bold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 shadow-sm">
                            <span>Book Pilgrimage Cab</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                    </div>
                </div>

                <!-- Tour 3: Sirpur Heritage & Barnawapara Safari -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group">
                    <div class="relative h-44 bg-slate-100 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1564507592333-c60657eea523?auto=format&fit=crop&w=600&q=80" alt="Sirpur & Barnawapara" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <span class="absolute top-3 left-3 bg-white/90 backdrop-blur-sm text-slate-900 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border border-slate-200 shadow-xs">
                            <i class="fa-solid fa-compass text-emerald-600 mr-1"></i> Heritage & Forest
                        </span>
                        <span class="absolute bottom-3 right-3 bg-[#EF6614] text-white text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-sm">
                            From ₹2,800/-
                        </span>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-[#20A8D9] uppercase tracking-wider block mb-1">History & Wildlife</span>
                            <h3 class="font-bold text-base text-slate-900 mb-2">Sirpur & Barnawapara Safari</h3>
                            <p class="text-xs text-slate-500 mb-3 line-clamp-3">7th-century terracotta Laxman Temple and Buddhist viharas paired with Barnawapara forest sanctuary safari.</p>
                            <div class="bg-[#F8FAFC] p-2.5 rounded-xl border border-slate-100 mb-4 text-[11px] text-slate-600 space-y-1">
                                <div class="flex items-center gap-1.5"><i class="fa-solid fa-car text-[#20A8D9]"></i> Sedan: ₹2,800 | Ertiga: ₹3,800</div>
                                <div class="flex items-center gap-1.5"><i class="fa-solid fa-location-dot text-slate-400"></i> ~85 KM from Raipur</div>
                            </div>
                        </div>
                        <button onclick="bookTourPackage('Raipur', 'Barnawapara Wildlife Sanctuary', 'round_trip')" class="w-full py-2.5 rounded-xl bg-[#20A8D9] hover:bg-[#0099D4] text-white font-bold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 shadow-sm">
                            <span>Book Safari Cab</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                    </div>
                </div>

                <!-- Tour 4: Ratanpur Mahamaya Devi & Khuntaghat -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group">
                    <div class="relative h-44 bg-slate-100 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=600&q=80" alt="Ratanpur Mahamaya Devi" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <span class="absolute top-3 left-3 bg-white/90 backdrop-blur-sm text-slate-900 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border border-slate-200 shadow-xs">
                            <i class="fa-solid fa-place-of-worship text-rose-500 mr-1"></i> Shaktipeeth Darshan
                        </span>
                        <span class="absolute bottom-3 right-3 bg-[#EF6614] text-white text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-sm">
                            From ₹1,800/-
                        </span>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-[#20A8D9] uppercase tracking-wider block mb-1">Pilgrimage & Eco-Dam</span>
                            <h3 class="font-bold text-base text-slate-900 mb-2">Ratanpur Mahamaya & Khuntaghat</h3>
                            <p class="text-xs text-slate-500 mb-3 line-clamp-3">Historical 51 Shaktipeeth darshan at Mahamaya Temple followed by scenic boating at Khuntaghat dam reservoir.</p>
                            <div class="bg-[#F8FAFC] p-2.5 rounded-xl border border-slate-100 mb-4 text-[11px] text-slate-600 space-y-1">
                                <div class="flex items-center gap-1.5"><i class="fa-solid fa-car text-[#20A8D9]"></i> Sedan: ₹1,800 | Ertiga: ₹2,600</div>
                                <div class="flex items-center gap-1.5"><i class="fa-solid fa-location-dot text-slate-400"></i> ~25 KM from Bilaspur</div>
                            </div>
                        </div>
                        <button onclick="bookTourPackage('Bilaspur', 'Ratanpur Mahamaya', 'round_trip')" class="w-full py-2.5 rounded-xl bg-[#20A8D9] hover:bg-[#0099D4] text-white font-bold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 shadow-sm">
                            <span>Book Ratanpur Cab</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Guarantee Features Strip -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto pt-6 border-t border-slate-200 text-center">
                <div class="p-2">
                    <i class="fa-solid fa-circle-check text-xl text-[#20A8D9] mb-1"></i>
                    <h5 class="text-xs font-bold text-slate-900">Zero Surge Pricing</h5>
                    <p class="text-[10px] text-slate-500">Fixed rate throughout the day</p>
                </div>
                <div class="p-2">
                    <i class="fa-solid fa-user-shield text-xl text-[#20A8D9] mb-1"></i>
                    <h5 class="text-xs font-bold text-slate-900">Verified Chauffeurs</h5>
                    <p class="text-[10px] text-slate-500">Courteous & background-checked</p>
                </div>
                <div class="p-2">
                    <i class="fa-solid fa-snowflake text-xl text-[#20A8D9] mb-1"></i>
                    <h5 class="text-xs font-bold text-slate-900">100% Chilled AC</h5>
                    <p class="text-[10px] text-slate-500">Sanitized clean interiors</p>
                </div>
                <div class="p-2">
                    <i class="fa-solid fa-headset text-xl text-[#20A8D9] mb-1"></i>
                    <h5 class="text-xs font-bold text-slate-900">24x7 Trip Support</h5>
                    <p class="text-[10px] text-slate-500">Emergency support & tracking</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Interior Comfort & Sanitization Showcase (Savaari Car Armrest / Water Bottle Feature) -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                <div>
                    <span class="text-xs font-bold text-[#20A8D9] uppercase tracking-wider block mb-2">PRISTINE CAB HYGIENE</span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold font-poppins text-slate-900 mb-4 leading-tight">
                        Your Comfort & Cleanliness is Our Utmost Priority
                    </h2>
                    <p class="text-slate-600 text-sm leading-relaxed mb-6">
                        Every vehicle in the <?php echo htmlspecialchars($company_name); ?> network undergoes rigorous multi-point mechanical safety checks and deep interior sanitization prior to dispatch.
                    </p>
                    
                    <div class="space-y-4 text-sm text-slate-700">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-[#E8F7FD] text-[#20A8D9] flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="fa-solid fa-bottle-water"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900">Complimentary Packaged Water</h4>
                                <p class="text-xs text-slate-500">Fresh mineral water bottles and tissue packs in every outstation cab.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-[#E8F7FD] text-[#20A8D9] flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="fa-solid fa-spray-can-sparkles"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900">Sanitized Air Conditioning & Upholstery</h4>
                                <p class="text-xs text-slate-500">Pleasant cabin aroma, vacuumed seats, and fresh high-efficiency AC filters.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-[#E8F7FD] text-[#20A8D9] flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="fa-solid fa-shield-virus"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900">Zero-Contact Doorstep Pickup</h4>
                                <p class="text-xs text-slate-500">Direct chauffeur assistance for heavy luggage and door-to-door coordination.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <a href="#booking-widget" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-[#EF6614] hover:bg-[#D9530F] text-white text-xs font-bold transition-all shadow-savaari uppercase">
                            Experience The Difference <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Interior Graphic Image (Clean sanitized luxury cabin showcase) -->
                <div class="relative rounded-2xl overflow-hidden shadow-lg border border-slate-200">
                    <img src="https://images.unsplash.com/photo-1563720223185-11003d516935?auto=format&fit=crop&w=800&q=80" alt="Sanitized Cab Interior" class="w-full h-80 sm:h-96 object-cover">
                    <div class="absolute bottom-4 left-4 right-4 bg-white/95 backdrop-blur-sm p-4 rounded-xl shadow-md border border-slate-100 flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-extrabold text-[#20A8D9] uppercase tracking-wider block">Avinash Verified Quality</span>
                            <span class="text-sm font-bold text-slate-900">Sanitized Clean AC Cab Guaranteed</span>
                        </div>
                        <span class="bg-emerald-500 text-white text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1">
                            <i class="fa-solid fa-check"></i> 100% Inspected
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Vehicles Showcase (Fleet) -->
    <section id="vehicles" class="py-16 bg-[#F8FAFC] border-t border-slate-200">
        <div class="container mx-auto px-4 text-center">
            <span class="text-xs font-bold text-[#20A8D9] uppercase tracking-wider">MODERN VEHICLES</span>
            <h2 class="text-3xl font-extrabold font-poppins text-slate-900 mb-2 mt-1">Explore Our Premium Fleet</h2>
            <p class="text-slate-500 text-sm mb-12 max-w-lg mx-auto">From pocket-friendly Sedans to spacious 7-seater Ertiga MPVs, choose the vehicle tailored for your trip.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 text-left">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): 
                        $vehicle_img = get_vehicle_image_path($cat['name'], $cat['image'] ?? '');
                        $cname = strtolower($cat['name']);
                        $model_subtitle = 'Comfort AC Fleet';
                        $local_start_rate = 1200;
                        if (strpos($cname, 'sedan') !== false) {
                            $model_subtitle = 'Maruti Dzire / Hyundai Aura / Etios';
                            $local_start_rate = 1200;
                        } elseif (strpos($cname, 'suv') !== false) {
                            $model_subtitle = 'Toyota Innova Crysta / XUV700';
                            $local_start_rate = 2200;
                        } elseif (strpos($cname, 'mpv') !== false) {
                            $model_subtitle = 'Maruti Suzuki Ertiga 7-Seater';
                            $local_start_rate = 1800;
                        } elseif (strpos($cname, 'lux') !== false) {
                            $model_subtitle = 'Mercedes-Benz / BMW / Audi';
                            $local_start_rate = 3500;
                        } elseif (strpos($cname, 'tempo') !== false) {
                            $model_subtitle = 'Force Traveller 12-17 Seater';
                            $local_start_rate = 4500;
                        } elseif (strpos($cname, 'bus') !== false) {
                            $model_subtitle = 'Luxury Tourist Coach 26-Seater';
                            $local_start_rate = 8500;
                        }
                    ?>
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 hover:shadow-xl hover:border-[#20A8D9]/40 transition-all duration-300 flex flex-col justify-between group">
                            <div>
                                <!-- Vehicle Product Image with Zoom on Hover -->
                                <div class="h-44 w-full flex items-center justify-center mb-4 bg-gradient-to-b from-[#F4FBFF] to-white rounded-xl p-3 border border-[#C8E6F5] overflow-hidden relative">
                                    <span class="absolute top-2.5 right-2.5 bg-white/90 backdrop-blur-sm text-[#007AA8] text-[10px] font-extrabold px-2 py-0.5 rounded-full border border-[#C8E6F5] shadow-xs">
                                        <i class="fa-solid fa-snowflake text-cyan-500 mr-1"></i> Full AC
                                    </span>
                                    <img src="<?php echo htmlspecialchars($vehicle_img); ?>" 
                                         alt="<?php echo htmlspecialchars($cat['name']); ?>" 
                                         class="w-full h-full object-contain filter drop-shadow-sm group-hover:scale-105 transition-transform duration-300"
                                         loading="lazy"
                                         onerror="this.onerror=null; this.src='assets/images/vehicles/sedan.png';">
                                </div>

                                <div class="flex justify-between items-start mb-1">
                                    <h3 class="text-xl font-extrabold text-slate-900 group-hover:text-[#0099D4] transition-colors"><?php echo htmlspecialchars($cat['name']); ?></h3>
                                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100 flex items-center gap-1">
                                        <i class="fa-solid fa-circle-check text-[10px]"></i> Inspected
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 font-semibold mb-3"><?php echo htmlspecialchars($model_subtitle); ?></p>
                                
                                <!-- Specifications Grid -->
                                <div class="grid grid-cols-2 gap-2 text-xs text-slate-600 mb-3 bg-[#F8FAFC] p-2.5 rounded-xl border border-slate-100">
                                    <span class="flex items-center gap-1.5 font-semibold"><i class="fa-solid fa-users text-[#20A8D9]"></i> <?php echo htmlspecialchars($cat['seating_capacity']); ?> Passenger Seats</span>
                                    <span class="flex items-center gap-1.5 font-semibold"><i class="fa-solid fa-suitcase-rolling text-[#20A8D9]"></i> <?php echo htmlspecialchars($cat['luggage_capacity']); ?> Boot Bags</span>
                                    <span class="flex items-center gap-1.5 font-semibold"><i class="fa-solid fa-snowflake text-cyan-600"></i> High-Cool AC</span>
                                    <span class="flex items-center gap-1.5 font-semibold"><i class="fa-solid fa-shield-halved text-emerald-600"></i> Verified Chauffeur</span>
                                </div>

                                <?php if (!empty($cat['amenities'])): ?>
                                    <p class="text-[11px] text-slate-500 mb-4 flex items-center gap-1.5">
                                        <i class="fa-solid fa-circle-check text-emerald-500 text-[10px]"></i>
                                        <span><?php echo htmlspecialchars($cat['amenities']); ?></span>
                                    </p>
                                <?php endif; ?>

                                <!-- Transparent Pricing Matrix Inside Card -->
                                <div class="p-3 bg-[#F4FBFF] border border-[#C8E6F5] rounded-xl mb-4 flex items-center justify-between">
                                    <div>
                                        <span class="text-[10px] text-slate-500 block uppercase font-extrabold tracking-wider">Outstation Rate</span>
                                        <div class="flex items-baseline gap-1">
                                            <span class="text-xl font-extrabold text-[#0099D4]">₹<?php echo number_format($cat['per_km_price'], 2); ?></span>
                                            <span class="text-xs text-slate-500 font-semibold">/KM</span>
                                        </div>
                                        <span class="text-[10px] text-slate-400">Base pickup: ₹<?php echo number_format($cat['base_price'], 0); ?></span>
                                    </div>
                                    <div class="text-right border-l border-[#C8E6F5] pl-3">
                                        <span class="text-[10px] text-slate-500 block uppercase font-extrabold tracking-wider">Local Hourly</span>
                                        <div class="flex items-baseline justify-end gap-1">
                                            <span class="text-base font-extrabold text-[#EF6614]">₹<?php echo number_format($local_start_rate); ?></span>
                                        </div>
                                        <span class="text-[10px] text-slate-400">From 4hr / 40km</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Dual Booking Action Buttons -->
                            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100">
                                <button type="button" 
                                        onclick="selectFleetVehicle('<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>', '<?php echo $cat['id']; ?>', '<?php echo number_format($cat['per_km_price'], 2); ?>', 'one_way')" 
                                        class="w-full py-2.5 bg-[#EF6614] hover:bg-[#D9530F] text-white text-xs font-extrabold rounded-xl shadow-sm hover:shadow transition-all flex items-center justify-center gap-1 cursor-pointer">
                                    <span>Outstation</span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </button>
                                <button type="button" 
                                        onclick="selectFleetVehicle('<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>', '<?php echo $cat['id']; ?>', '<?php echo number_format($cat['per_km_price'], 2); ?>', 'local')" 
                                        class="w-full py-2.5 bg-white hover:bg-[#E8F7FD] text-[#0099D4] border border-[#C8E6F5] hover:border-[#20A8D9] text-xs font-extrabold rounded-xl transition-all flex items-center justify-center gap-1 cursor-pointer">
                                    <span>Local Package</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Offers Section -->
    <section id="offers" class="py-16 bg-white border-t border-slate-100">
        <div class="container mx-auto px-4 text-center">
            <span class="text-xs font-bold text-[#20A8D9] uppercase tracking-wider">LIMITED TIME SAVINGS</span>
            <h2 class="text-3xl font-extrabold font-poppins text-slate-900 mb-2 mt-1">Today's Special Offers</h2>
            <p class="text-slate-500 text-sm mb-12 max-w-lg mx-auto">Claim promotional deals on designated travel routes across Chhattisgarh.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php if (!empty($offers)): ?>
                    <?php foreach ($offers as $offer): ?>
                        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col text-left">
                            <div class="relative h-40 bg-slate-100 flex items-center justify-center bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=400&q=80');">
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent"></div>
                                <div class="absolute bottom-3 left-4 right-4 flex justify-between items-end">
                                    <span class="bg-[#EF6614] text-white text-[10px] font-extrabold px-2.5 py-0.5 rounded-full tracking-wide uppercase">
                                        Save <?php echo $offer['discount_percent']; ?>%
                                    </span>
                                    <span class="text-xs text-white font-bold bg-black/40 px-2 py-0.5 rounded-full"><i class="fa-solid fa-stopwatch mr-1"></i>Expires Soon</span>
                                </div>
                            </div>
                            <div class="p-5 flex-grow flex flex-col justify-between">
                                <div>
                                    <h3 class="text-slate-900 font-bold text-base mb-1"><?php echo htmlspecialchars($offer['title']); ?></h3>
                                    <p class="text-xs text-slate-500 mb-3">Route: <?php echo htmlspecialchars($offer['pickup_city']); ?> to <?php echo htmlspecialchars($offer['drop_city']); ?></p>
                                    <div class="flex justify-between items-center text-xs text-slate-600 bg-[#F4FBFF] p-2.5 rounded-lg border border-[#C8E6F5] mb-4">
                                        <span>Coupon: <strong class="text-[#0099D4] font-mono font-bold"><?php echo htmlspecialchars($offer['coupon_code']); ?></strong></span>
                                        <span>Valid until: <?php echo date('d M Y', strtotime($offer['validity'])); ?></span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center pt-2 border-t border-slate-100">
                                    <div>
                                        <span class="block text-[10px] text-slate-400 uppercase">Promo Fare</span>
                                        <span class="text-lg font-bold text-[#0099D4]">₹<?php echo number_format($offer['special_price'], 2); ?></span>
                                        <span class="text-xs text-slate-400 line-through ml-1.5">₹<?php echo number_format($offer['original_price'], 2); ?></span>
                                    </div>
                                    <button onclick="prefillSearch('<?php echo htmlspecialchars($offer['pickup_city']); ?>', '<?php echo htmlspecialchars($offer['drop_city']); ?>', '<?php echo htmlspecialchars($offer['coupon_code']); ?>')" class="px-4 py-2 bg-[#EF6614] hover:bg-[#D9530F] text-white text-xs font-bold rounded-full transition-colors shadow-sm">
                                        Claim Deal
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-3 bg-[#F4FBFF] border border-[#C8E6F5] rounded-2xl p-8 text-center text-slate-600 text-sm">
                        <i class="fa-solid fa-tag text-[#20A8D9] text-2xl mb-2"></i>
                        <p class="font-semibold">Special promotional rates are active across all 6 one-way corridors above!</p>
                        <a href="#one-way-fares" class="text-xs font-bold text-[#0099D4] hover:underline mt-2 inline-block">Review Fixed One-Way Fares →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- About Section & CMS Metrics -->
    <section id="about" class="py-16 sm:py-20 bg-[#F8FAFC] border-t border-slate-200">
        <div class="container mx-auto px-4">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
                <span class="inline-flex items-center gap-2 bg-[#E8F7FD] text-[#0099D4] border border-[#C8E6F5] px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider mb-3">
                    <span class="w-2 h-2 rounded-full bg-[#20A8D9]"></span>
                    SINCE 2013 • TRUSTED MOBILITY PARTNER
                </span>
                <h2 class="text-3xl sm:text-4xl font-extrabold font-poppins text-slate-900 mb-3">About Us</h2>
                <p class="text-slate-600 text-sm sm:text-base font-medium">
                    <span class="text-[#0099D4] font-bold">90,000+ rides completed.</span> One journey at a time.
                </p>
            </div>

            <!-- Main 2-Column Grid: Journey & Commitment vs Why Choose Us -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start mb-12">
                <!-- Left: Our Journey & Our Commitment (7 cols) -->
                <div class="lg:col-span-7 space-y-6">
                    <!-- Our Journey Card -->
                    <div class="bg-white border border-[#C8E6F5] rounded-2xl p-6 sm:p-8 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-11 h-11 rounded-xl bg-[#E8F7FD] text-[#0099D4] border border-[#C8E6F5] flex items-center justify-center text-lg shadow-sm">
                                <i class="fa-solid fa-route"></i>
                            </div>
                            <div>
                                <span class="text-[11px] font-bold text-[#20A8D9] uppercase tracking-wider block">OUR STORY</span>
                                <h3 class="text-xl font-bold font-poppins text-slate-900">Our Journey</h3>
                            </div>
                        </div>
                        <p class="text-slate-600 text-sm leading-relaxed mb-4">
                            Founded in <strong class="text-slate-900 font-bold">2013 by Ravi Gupta</strong>, our taxi service began with a simple vision — to provide <strong class="text-slate-900 font-bold">safe, reliable, comfortable, and hassle-free transportation</strong> to every customer.
                        </p>
                        <p class="text-slate-600 text-sm leading-relaxed">
                            Over the years, we have grown through the trust and support of our customers. Today, we are proud to have completed <strong class="text-[#0099D4] font-bold">90,000+ taxi rides</strong>, making every journey a valuable part of our story.
                        </p>
                    </div>

                    <!-- Our Commitment Card -->
                    <div class="bg-white border border-[#C8E6F5] rounded-2xl p-6 sm:p-8 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-11 h-11 rounded-xl bg-orange-50 text-[#EF6614] border border-orange-200 flex items-center justify-center text-lg shadow-sm">
                                <i class="fa-solid fa-shield-heart"></i>
                            </div>
                            <div>
                                <span class="text-[11px] font-bold text-[#EF6614] uppercase tracking-wider block">PASSENGER FIRST</span>
                                <h3 class="text-xl font-bold font-poppins text-slate-900">Our Commitment</h3>
                            </div>
                        </div>
                        <p class="text-slate-600 text-sm leading-relaxed mb-3">
                            For us, every ride is more than just transportation. It is about <strong class="text-slate-900 font-bold">punctuality, safety, comfort, and customer satisfaction</strong>.
                        </p>
                        <p class="text-slate-600 text-sm leading-relaxed">
                            We strive to make every journey smooth and convenient, whether it is a local trip, airport transfer, outstation journey, business travel, or a family ride.
                        </p>
                    </div>
                </div>

                <!-- Right: Why Choose Us? (5 cols) -->
                <div class="lg:col-span-5 bg-white border border-[#C8E6F5] rounded-2xl p-6 sm:p-8 shadow-sm">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                        <div class="w-11 h-11 rounded-xl bg-[#E8F7FD] text-[#0099D4] border border-[#C8E6F5] flex items-center justify-center text-lg shadow-sm">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold text-[#20A8D9] uppercase tracking-wider block">KEY ADVANTAGES</span>
                            <h3 class="text-xl font-bold font-poppins text-slate-900">Why Choose Us?</h3>
                        </div>
                    </div>

                    <div class="space-y-3.5">
                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-[#F8FAFC] border border-slate-100 hover:border-[#C8E6F5] transition-colors">
                            <span class="text-lg leading-none mt-0.5">🚕</span>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Established in 2013</h4>
                                <p class="text-[11px] text-slate-500">Over a decade of trusted travel experience across Central India</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-[#F8FAFC] border border-slate-100 hover:border-[#C8E6F5] transition-colors">
                            <span class="text-lg leading-none mt-0.5">👨‍💼</span>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Founded by Ravi Gupta</h4>
                                <p class="text-[11px] text-slate-500">Built on personal integrity, accountable service, and care</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-[#F8FAFC] border border-slate-100 hover:border-[#C8E6F5] transition-colors">
                            <span class="text-lg leading-none mt-0.5">⭐</span>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">90,000+ Taxi Rides Completed</h4>
                                <p class="text-[11px] text-slate-500">A proud milestone of happy travelers and safe arrivals</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-[#F8FAFC] border border-slate-100 hover:border-[#C8E6F5] transition-colors">
                            <span class="text-lg leading-none mt-0.5">🛡️</span>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Focus on Safe and Reliable Travel</h4>
                                <p class="text-[11px] text-slate-500">Strict vehicle safety maintenance & verified professional chauffeurs</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-[#F8FAFC] border border-slate-100 hover:border-[#C8E6F5] transition-colors">
                            <span class="text-lg leading-none mt-0.5">⏱️</span>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Commitment to Timely Service</h4>
                                <p class="text-[11px] text-slate-500">Guaranteed on-time doorstep pickup with 24/7 flight tracking</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-[#F8FAFC] border border-slate-100 hover:border-[#C8E6F5] transition-colors">
                            <span class="text-lg leading-none mt-0.5">😊</span>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Customer-Focused Approach</h4>
                                <p class="text-[11px] text-slate-500">Transparent flat rates, zero hidden charges, and polite chauffeurs</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-[#F8FAFC] border border-slate-100 hover:border-[#C8E6F5] transition-colors">
                            <span class="text-lg leading-none mt-0.5">🚗</span>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">Comfortable and Convenient Transportation</h4>
                                <p class="text-[11px] text-slate-500">Clean, air-conditioned sedans, SUVs, and luxury vehicles</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vision & Mission Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
                <!-- Our Vision -->
                <div class="bg-gradient-to-br from-white to-[#F4FBFF] border border-[#C8E6F5] rounded-2xl p-6 sm:p-8 shadow-sm relative overflow-hidden">
                    <div class="w-12 h-12 rounded-xl bg-[#20A8D9]/10 text-[#0099D4] border border-[#20A8D9]/20 flex items-center justify-center text-xl mb-4 shadow-sm">
                        <i class="fa-solid fa-compass"></i>
                    </div>
                    <span class="text-[11px] font-bold text-[#20A8D9] uppercase tracking-wider block mb-1">LONG-TERM ASPIRATION</span>
                    <h3 class="text-2xl font-bold font-poppins text-slate-900 mb-3">Our Vision</h3>
                    <p class="text-slate-600 text-sm sm:text-base leading-relaxed">
                        To become a <strong class="text-slate-900 font-bold">trusted and preferred mobility partner</strong>, delivering dependable transportation services while building long-lasting relationships with our customers.
                    </p>
                </div>

                <!-- Our Mission -->
                <div class="bg-gradient-to-br from-white to-orange-50/40 border border-orange-200/70 rounded-2xl p-6 sm:p-8 shadow-sm relative overflow-hidden">
                    <div class="w-12 h-12 rounded-xl bg-[#EF6614]/10 text-[#EF6614] border border-[#EF6614]/20 flex items-center justify-center text-xl mb-4 shadow-sm">
                        <i class="fa-solid fa-bullseye"></i>
                    </div>
                    <span class="text-[11px] font-bold text-[#EF6614] uppercase tracking-wider block mb-1">OUR PURPOSE</span>
                    <h3 class="text-2xl font-bold font-poppins text-slate-900 mb-3">Our Mission</h3>
                    <p class="text-slate-600 text-sm sm:text-base leading-relaxed">
                        To make every journey <strong class="text-slate-900 font-bold">safe, comfortable, punctual, and memorable</strong> through professional service and customer-first values.
                    </p>
                </div>
            </div>

            <!-- Milestone Numbers Bar -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
                <div class="bg-white border border-[#C8E6F5] p-5 sm:p-6 rounded-2xl text-center shadow-sm hover:border-[#20A8D9] transition-all">
                    <div class="text-2xl sm:text-4xl font-extrabold font-poppins text-[#20A8D9] mb-1">2013</div>
                    <div class="text-xs font-bold text-slate-700 uppercase tracking-wide">Established Year</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Over 13+ Years of Service</div>
                </div>
                <div class="bg-white border border-[#C8E6F5] p-5 sm:p-6 rounded-2xl text-center shadow-sm hover:border-[#20A8D9] transition-all">
                    <div class="text-2xl sm:text-4xl font-extrabold font-poppins text-[#20A8D9] mb-1">90,000+</div>
                    <div class="text-xs font-bold text-slate-700 uppercase tracking-wide">Taxi Rides Completed</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Across Central India</div>
                </div>
                <div class="bg-white border border-[#C8E6F5] p-5 sm:p-6 rounded-2xl text-center shadow-sm hover:border-[#20A8D9] transition-all">
                    <div class="text-2xl sm:text-4xl font-extrabold font-poppins text-[#20A8D9] mb-1">100%</div>
                    <div class="text-xs font-bold text-slate-700 uppercase tracking-wide">Customer-First Values</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Transparent Fixed Fares</div>
                </div>
                <div class="bg-white border border-[#C8E6F5] p-5 sm:p-6 rounded-2xl text-center shadow-sm hover:border-[#20A8D9] transition-all">
                    <div class="text-2xl sm:text-4xl font-extrabold font-poppins text-[#20A8D9] mb-1">24x7</div>
                    <div class="text-xs font-bold text-slate-700 uppercase tracking-wide">Helpline Support</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Direct Call & WhatsApp</div>
                </div>
            </div>

            <!-- Founder & Contact Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white rounded-2xl p-6 sm:p-8 shadow-xl border border-slate-700 relative overflow-hidden">
                <!-- Background decorative elements -->
                <div class="absolute -right-12 -top-12 w-48 h-48 bg-[#20A8D9]/10 rounded-full blur-2xl pointer-events-none"></div>
                <div class="absolute -left-12 -bottom-12 w-48 h-48 bg-[#EF6614]/10 rounded-full blur-2xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-4 text-center md:text-left">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#20A8D9] to-[#007AA8] text-white flex items-center justify-center text-2xl shadow-md border border-white/20 flex-shrink-0">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                        <div>
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-white/10 text-[#20A8D9] text-[11px] font-bold uppercase tracking-wider mb-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Founder & Contact
                            </div>
                            <h4 class="text-xl sm:text-2xl font-bold font-poppins text-white">Ravi Gupta</h4>
                            <p class="text-xs text-slate-300 mt-0.5">
                                Thank you for being a part of our journey since 2013.
                            </p>
                        </div>
                    </div>

                    <!-- Direct Contact CTAs -->
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <a href="tel:8120868818" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-[#20A8D9] hover:bg-[#0099D4] text-white font-bold text-xs uppercase tracking-wider transition-all shadow-savaari">
                            <i class="fa-solid fa-phone"></i>
                            <span>Mobile 8120868818</span>
                        </a>
                        <a href="https://wa.me/918120868818?text=Hello%20Ravi%20ji,%20I%20would%20like%20to%20inquire%20about%20Avinash%20Travels%20taxi%20services." target="_blank" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs uppercase tracking-wider transition-all shadow-md">
                            <i class="fa-brands fa-whatsapp text-sm"></i>
                            <span>WhatsApp Chat</span>
                        </a>
                    </div>
                </div>

                <div class="mt-6 pt-5 border-t border-slate-700/80 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-400 gap-2">
                    <span class="flex items-center gap-1.5 font-medium text-slate-300">
                        <i class="fa-solid fa-award text-[#20A8D9]"></i> 90,000+ rides completed. One journey at a time.
                    </span>
                    <span class="text-slate-400">
                        Serving local, airport transfer, outstation, business & family travel.
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- Booking Tracking Tool -->
    <section id="track" class="py-14 bg-white border-t border-b border-slate-100">
        <div class="container mx-auto px-4 max-w-xl text-center">
            <span class="text-xs font-bold text-[#20A8D9] uppercase tracking-wider">LIVE STATUS</span>
            <h2 class="text-2xl font-extrabold text-slate-900 font-poppins mb-1 mt-1">Track Your Booking</h2>
            <p class="text-xs text-slate-500 mb-6">Review driver coordinates, vehicle assignment, and live schedule updates.</p>
            
            <form onsubmit="handleTrackingSubmit(event)" class="bg-[#F8FAFC] p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Booking ID</label>
                        <input type="text" id="track-id" placeholder="E.g. AVT-20260909-1234" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9] placeholder:text-slate-400 font-mono" required>
                    </div>
                    <div>
                        <label class="block text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Mobile Number</label>
                        <input type="tel" id="track-phone" placeholder="Registered 10-digit phone" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9] placeholder:text-slate-400" required>
                    </div>
                </div>
                <button type="submit" class="w-full py-3 bg-[#20A8D9] hover:bg-[#0099D4] text-white font-bold rounded-xl text-xs uppercase tracking-wider transition-colors shadow-sm">
                    Track Ride Status
                </button>
            </form>

            <div id="tracking-result-box" class="mt-6">
                <!-- Search Result Card injected here -->
            </div>
        </div>
    </section>

    <!-- Partner Registration / Driver login -->
    <section id="partner" class="py-16 bg-[#F8FAFC]">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                <div>
                    <span class="text-[#20A8D9] font-bold text-xs uppercase tracking-wider block mb-2">JOIN OUR FLEET NETWORK</span>
                    <h2 class="text-3xl font-extrabold text-slate-900 font-poppins mb-4">Drive & Earn With <?php echo htmlspecialchars($company_name); ?></h2>
                    <p class="text-slate-600 text-sm leading-relaxed mb-6">Own a commercial vehicle and looking to maximize your bookings? Register with Avinash Travels, accept premium outstation trips, set your coordinates, and join a fleet of verified partners.</p>
                    
                    <div class="flex flex-wrap gap-3">
                        <button onclick="openPartnerModal()" class="px-5 py-3 bg-[#EF6614] hover:bg-[#D9530F] text-white font-bold rounded-full text-xs uppercase tracking-wider transition-all shadow-savaari">
                            Become a Partner
                        </button>
                        <a href="/driver/login.php" class="px-5 py-3 bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 font-bold rounded-full text-xs uppercase tracking-wider transition-all">
                            Driver Login Portal
                        </a>
                    </div>
                </div>
                <div class="relative h-64 lg:h-80 rounded-2xl overflow-hidden shadow-md bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?auto=format&fit=crop&w=600&q=80');">
                    <div class="absolute inset-0 bg-gradient-to-r from-black/50 to-transparent"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-16 bg-white border-t border-slate-100">
        <div class="container mx-auto px-4 text-center">
            <span class="text-xs font-bold text-[#20A8D9] uppercase tracking-wider">REAL TRAVEL STORIES</span>
            <h2 class="text-3xl font-extrabold text-slate-900 font-poppins mb-2 mt-1">Customer Reviews & Ratings</h2>
            <p class="text-slate-500 text-sm mb-12">What clients say about our punctuality, sanitized cabins, and polite chauffeurs.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php if (!empty($testimonials)): ?>
                    <?php foreach ($testimonials as $t): ?>
                        <div class="bg-[#F8FAFC] border border-slate-200 p-6 rounded-2xl text-left shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
                            <div>
                                <div class="flex text-amber-400 text-xs mb-3">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <i class="fa-solid fa-star <?php echo ($i < $t['rating']) ? '' : 'text-slate-300'; ?> mr-0.5"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-slate-600 text-sm leading-relaxed mb-4 italic">"<?php echo htmlspecialchars($t['review']); ?>"</p>
                            </div>
                            <div class="flex items-center space-x-3 pt-4 border-t border-slate-200">
                                <div class="w-10 h-10 bg-[#20A8D9] text-white rounded-full flex items-center justify-center font-bold text-sm">
                                    <?php echo substr($t['name'], 0, 1); ?>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-900 text-sm"><?php echo htmlspecialchars($t['name']); ?></h4>
                                    <span class="text-slate-500 text-[10px] block">Route: <?php echo htmlspecialchars($t['route']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- FAQs Accordion -->
    <section id="faq" class="py-16 bg-[#F8FAFC] border-t border-slate-200">
        <div class="container mx-auto px-4 max-w-3xl">
            <span class="text-xs font-bold text-[#20A8D9] uppercase tracking-wider block text-center mb-1">COMMON QUERIES</span>
            <h2 class="text-3xl font-extrabold text-slate-900 font-poppins text-center mb-2">Frequently Asked Questions</h2>
            <p class="text-slate-500 text-sm text-center mb-10">Essential details regarding billing, toll allowances, vehicle sanitization, and cancellations.</p>
            
            <div class="space-y-3">
                <?php if (!empty($faqs)): ?>
                    <?php foreach ($faqs as $index => $faq): ?>
                        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                            <button onclick="toggleFaq(<?php echo $index; ?>)" class="w-full flex justify-between items-center p-4 text-left font-bold text-slate-900 hover:text-[#20A8D9] transition-colors focus:outline-none">
                                <span class="text-sm"><?php echo htmlspecialchars($faq['question']); ?></span>
                                <i id="faq-icon-<?php echo $index; ?>" class="fa-solid fa-chevron-down text-[#20A8D9] text-xs transition-transform duration-200"></i>
                            </button>
                            <div id="faq-ans-<?php echo $index; ?>" class="hidden px-4 pb-4 text-slate-600 text-xs sm:text-sm leading-relaxed border-t border-slate-100 pt-3">
                                <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-16 bg-white border-t border-slate-100">
        <div class="container mx-auto px-4 grid grid-cols-1 lg:grid-cols-12 gap-10">
            <!-- Contact Info -->
            <div class="lg:col-span-5 space-y-6">
                <div>
                    <span class="text-xs font-bold text-[#20A8D9] uppercase tracking-wider block mb-1">GET IN TOUCH</span>
                    <h2 class="text-3xl font-extrabold font-poppins text-slate-900 mb-2">Connect With Support</h2>
                    <p class="text-slate-500 text-sm">Need help with custom corporate tours or wedding car fleets? Contact our 24/7 helpdesk.</p>
                </div>
                
                <div class="space-y-3 text-sm text-slate-700">
                    <div class="flex items-start space-x-3 bg-[#F4FBFF] p-4 rounded-xl border border-[#C8E6F5]">
                        <i class="fa-solid fa-location-dot text-[#20A8D9] text-lg mt-0.5"></i>
                        <div>
                            <strong class="block text-slate-900 font-bold text-xs uppercase tracking-wider">Head Office Address</strong>
                            <span class="text-xs text-slate-600 block mb-1.5"><?php echo htmlspecialchars($address); ?></span>
                            <a href="<?php echo htmlspecialchars($maps_link); ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#0099D4] hover:underline">
                                <i class="fa-solid fa-map-location-dot"></i> View on Google Maps
                            </a>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3 bg-[#F4FBFF] p-4 rounded-xl border border-[#C8E6F5]">
                        <i class="fa-solid fa-phone text-[#20A8D9] text-sm mt-1"></i>
                        <div>
                            <strong class="block text-slate-900 font-bold text-xs uppercase tracking-wider">Mobile & Helpline Support</strong>
                            <a href="tel:<?php echo $phone; ?>" class="text-xs font-bold text-[#0099D4] hover:underline">Mobile: <?php echo htmlspecialchars($phone); ?></a>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3 bg-[#F4FBFF] p-4 rounded-xl border border-[#C8E6F5]">
                        <i class="fa-solid fa-envelope text-[#20A8D9] text-sm mt-1"></i>
                        <div>
                            <strong class="block text-slate-900 font-bold text-xs uppercase tracking-wider">Official Email Inbox</strong>
                            <a href="mailto:<?php echo $email; ?>" class="text-xs font-bold text-[#0099D4] hover:underline"><?php echo htmlspecialchars($email); ?></a>
                        </div>
                    </div>
                </div>

                <!-- Google map iframe -->
                <?php if ($maps_iframe): ?>
                    <div class="h-52 rounded-xl overflow-hidden border border-slate-200 shadow-sm">
                        <iframe src="<?php echo htmlspecialchars($maps_iframe); ?>" class="w-full h-full border-0" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contact Form -->
            <div class="lg:col-span-7 bg-[#F8FAFC] border border-slate-200 rounded-2xl p-6 sm:p-8 shadow-sm">
                <h3 class="text-xl font-bold text-slate-900 font-poppins mb-5">Drop Us a Message</h3>
                <form id="contact-form" onsubmit="handleContactSubmit(event)" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Full Name</label>
                            <input type="text" name="name" placeholder="E.g. Ashish" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9] placeholder:text-slate-400" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Mobile Number</label>
                            <input type="tel" name="phone" placeholder="Registered 10-digit number" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9] placeholder:text-slate-400" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address</label>
                        <input type="email" name="email" placeholder="E.g. ashish@gmail.com" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9] placeholder:text-slate-400" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Subject</label>
                        <input type="text" name="subject" placeholder="E.g. Outstation Tour Booking" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9] placeholder:text-slate-400" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Your Message</label>
                        <textarea name="message" rows="3" placeholder="Detail your travel coordinates, timings, and passenger requirements..." class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9] placeholder:text-slate-400" required></textarea>
                    </div>
                    <button type="submit" class="w-full py-3 bg-[#20A8D9] hover:bg-[#0099D4] text-white font-bold rounded-xl text-xs uppercase tracking-wider transition-colors shadow-sm">
                        Send Enquiry Message
                    </button>
                </form>
                <div id="contact-success-msg" class="hidden mt-4 bg-emerald-50 text-emerald-700 border border-emerald-200 p-3 rounded-xl text-sm"></div>
                <div id="contact-error-msg" class="hidden mt-4 bg-rose-50 text-rose-700 border border-rose-200 p-3 rounded-xl text-sm"></div>
            </div>
        </div>
    </section>

    <!-- SIGNATURE SAVAARI FULL-WIDTH CYAN FOOTER (#0099D4 / #20A8D9) -->
    <footer class="bg-[#0099D4] text-white pt-12 pb-8 border-t border-cyan-400/30">
        <div class="container mx-auto px-4 grid grid-cols-1 md:grid-cols-4 gap-8 mb-8 pb-8 border-b border-cyan-300/30 text-cyan-50">
            <!-- Col 1 -->
            <div>
                <a href="#home" class="inline-block mb-4">
                    <div class="bg-white px-3 py-1.5 rounded-xl shadow-md inline-flex items-center">
                        <img src="uploads/logo/logo.png" onerror="this.onerror=null; this.src='https://avinashtaxi.com/uploads/logo/logo.png';" alt="<?php echo htmlspecialchars($company_name); ?>" class="h-10 w-auto object-contain">
                    </div>
                </a>
                <p class="text-xs text-white/80 leading-relaxed mb-4">
                    Chhattisgarh's premier outstation taxi and cab service. Enjoy reliable door-to-door rides, sanitized modern cabs, verified chauffeurs, and zero return charges.
                </p>
                <div class="flex space-x-2">
                    <a href="<?php echo htmlspecialchars($social_fb); ?>" class="w-8 h-8 rounded-full bg-white/15 hover:bg-white text-white hover:text-[#0099D4] flex items-center justify-center text-xs transition-colors"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="<?php echo htmlspecialchars($social_ig); ?>" class="w-8 h-8 rounded-full bg-white/15 hover:bg-white text-white hover:text-[#0099D4] flex items-center justify-center text-xs transition-colors"><i class="fa-brands fa-instagram"></i></a>
                    <a href="<?php echo htmlspecialchars($social_tw); ?>" class="w-8 h-8 rounded-full bg-white/15 hover:bg-white text-white hover:text-[#0099D4] flex items-center justify-center text-xs transition-colors"><i class="fa-brands fa-twitter"></i></a>
                </div>
            </div>

            <!-- Col 2 -->
            <div>
                <h4 class="text-white font-extrabold text-sm mb-4 uppercase tracking-wider">Services</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="#one-way-fares" class="hover:text-white transition-colors underline-offset-2 hover:underline">One Way Outstation Cab</a></li>
                    <li><a href="#booking-widget" class="hover:text-white transition-colors underline-offset-2 hover:underline">Round Trip Cabs</a></li>
                    <li><a href="#booking-widget" class="hover:text-white transition-colors underline-offset-2 hover:underline">Airport Taxi Pickup & Drop</a></li>
                    <li><a href="#booking-widget" class="hover:text-white transition-colors underline-offset-2 hover:underline">Local Hourly Car Rentals</a></li>
                    <li><a href="#contact" class="hover:text-white transition-colors underline-offset-2 hover:underline">Corporate & Wedding Transport</a></li>
                </ul>
            </div>

            <!-- Col 3 -->
            <div>
                <h4 class="text-white font-extrabold text-sm mb-4 uppercase tracking-wider">Quick Links</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="#one-way-fares" class="hover:text-white transition-colors underline-offset-2 hover:underline">One-Way Fare Catalog</a></li>
                    <li><a href="#offers" class="hover:text-white transition-colors underline-offset-2 hover:underline">Special Offers & Deals</a></li>
                    <li><a href="#track" class="hover:text-white transition-colors underline-offset-2 hover:underline">Track Booking Status</a></li>
                    <li><a href="#partner" class="hover:text-white transition-colors underline-offset-2 hover:underline">Attach Vehicle / Partner</a></li>
                    <li><a href="/customer/login.php" class="hover:text-white transition-colors underline-offset-2 hover:underline">Customer Portal Login</a></li>
                    <li><a href="/driver/login.php" class="hover:text-white transition-colors underline-offset-2 hover:underline">Driver Portal Login</a></li>
                    <li><a href="admin/login.php" class="hover:text-white transition-colors underline-offset-2 hover:underline opacity-90"><i class="fa-solid fa-lock text-[10px] mr-1"></i> Admin Portal</a></li>
                </ul>
            </div>

            <!-- Col 4 -->
            <div>
                <h4 class="text-white font-extrabold text-sm mb-4 uppercase tracking-wider">24x7 Helpdesk</h4>
                <div class="space-y-3 text-xs">
                    <p class="flex items-center gap-2">
                        <i class="fa-solid fa-phone"></i>
                        <a href="tel:<?php echo $phone; ?>" class="font-bold hover:underline">Mobile: <?php echo htmlspecialchars($phone); ?></a>
                    </p>
                    <p class="flex items-center gap-2">
                        <i class="fa-brands fa-whatsapp"></i>
                        <a href="https://wa.me/<?php echo $whatsapp; ?>" class="hover:underline">WhatsApp: +<?php echo htmlspecialchars($whatsapp); ?></a>
                    </p>
                    <p class="flex items-center gap-2">
                        <i class="fa-solid fa-envelope"></i>
                        <a href="mailto:<?php echo $email; ?>" class="hover:underline"><?php echo htmlspecialchars($email); ?></a>
                    </p>
                    <p class="flex items-start gap-2">
                        <i class="fa-solid fa-location-dot mt-0.5"></i>
                        <a href="<?php echo htmlspecialchars($maps_link); ?>" target="_blank" class="hover:underline"><?php echo htmlspecialchars($address); ?></a>
                    </p>
                </div>
            </div>
        </div>

        <div class="container mx-auto px-4 flex flex-col md:flex-row justify-between items-center text-xs text-cyan-100 gap-2 font-medium">
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company_name); ?>. All Rights Reserved.</span>
            <span>Safe & Sanitized Outstation Cabs Across Chhattisgarh & Central India.</span>
        </div>
    </footer>

    <!-- WhatsApp Floating Action Button -->
    <a href="https://wa.me/<?php echo $whatsapp; ?>" target="_blank" class="fixed bottom-20 sm:bottom-6 right-6 z-40 bg-emerald-500 hover:bg-emerald-600 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110" title="WhatsApp 24/7 Support">
        <i class="fa-brands fa-whatsapp text-3xl"></i>
    </a>
    
    <!-- Mobile Sticky Bottom "Book Cab" CTA Bar -->
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-slate-200 p-2.5 grid grid-cols-2 gap-2 shadow-2xl">
        <a href="tel:<?php echo $phone; ?>" class="flex items-center justify-center py-2.5 bg-slate-100 border border-slate-300 text-slate-800 font-bold rounded-xl text-xs">
            <i class="fa-solid fa-phone mr-1.5 text-[#20A8D9]"></i> Mobile <?php echo htmlspecialchars($phone); ?>
        </a>
        <a href="#booking-widget" class="flex items-center justify-center py-2.5 bg-[#EF6614] text-white font-bold rounded-xl text-xs shadow-md">
            <i class="fa-solid fa-car mr-1.5"></i> Book Cab Now
        </a>
    </div>

    <!-- Booking Checkout Multi-Step Wizard Modal (Compact Single-View Design) -->
    <div id="booking-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/60 backdrop-blur-sm p-2 sm:p-4 overflow-y-auto">
        <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-xl md:max-w-2xl max-h-[94vh] sm:max-h-[90vh] flex flex-col shadow-2xl relative my-auto overflow-hidden">
            <!-- Close Modal -->
            <button onclick="closeBookingModal()" class="absolute top-3 right-3 text-slate-400 hover:text-slate-700 transition-colors focus:outline-none z-10 p-1" title="Close">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <!-- Modal Header (Compact) -->
            <div class="bg-[#F4FBFF] px-4 py-2.5 sm:px-5 sm:py-3 border-b border-[#C8E6F5] shrink-0">
                <h3 class="text-sm sm:text-base font-bold text-slate-900 font-poppins flex items-center gap-2">
                    <i class="fa-solid fa-car text-[#20A8D9]"></i> Confirm Your Cab Reservation
                </h3>
                <p class="text-[10px] sm:text-[11px] text-slate-500">Provide details for verified dispatch confirmation</p>
            </div>

            <!-- Booking Flow Steps Indicator (Slim) -->
            <div class="px-4 py-1.5 border-b border-slate-100 bg-white grid grid-cols-3 gap-2 text-center text-[10px] text-slate-500 font-bold uppercase tracking-wider shrink-0">
                <span id="step-indicator-passenger" class="text-[#0099D4] border-b-2 border-[#0099D4] pb-1 block">1. Passenger</span>
                <span id="step-indicator-summary" class="pb-1 block border-b-2 border-transparent">2. Fare Summary</span>
                <span id="step-indicator-payment" class="pb-1 block border-b-2 border-transparent">3. Confirmation</span>
            </div>

            <form id="checkout-booking-form" onsubmit="handleCheckoutSubmit(event)" enctype="multipart/form-data" class="flex-1 flex flex-col overflow-y-auto">
                <!-- Shared booking details -->
                <input type="hidden" name="pickup_city" id="chk-pickup-city">
                <input type="hidden" name="drop_city" id="chk-drop-city">
                <input type="hidden" name="pickup_date" id="chk-pickup-date">
                <input type="hidden" name="pickup_time" id="chk-pickup-time">
                <input type="hidden" name="return_date" id="chk-return-date">
                <input type="hidden" name="return_time" id="chk-return-time">
                <input type="hidden" name="trip_type" id="chk-trip-type">
                <input type="hidden" name="category_id" id="chk-category-id">
                <input type="hidden" name="coupon_code" id="chk-coupon-code">

                <!-- MODAL STEP 1: PASSENGER INFO (Compact) -->
                <div id="booking-step-passenger" class="p-3.5 sm:p-5 space-y-2.5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mb-1">Passenger Name *</label>
                            <input type="text" name="name" placeholder="Full name" class="w-full px-3 py-1.5 bg-[#F8FAFC] border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mb-1">Mobile Number *</label>
                            <input type="tel" name="phone" placeholder="10-digit mobile number" class="w-full px-3 py-1.5 bg-[#F8FAFC] border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mb-1">Email Address *</label>
                            <input type="email" name="email" placeholder="customer@gmail.com" class="w-full px-3 py-1.5 bg-[#F8FAFC] border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mb-1">Number of Passengers</label>
                            <input type="number" name="passenger_count" min="1" max="15" value="1" class="w-full px-3 py-1.5 bg-[#F8FAFC] border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mb-1">Pickup Address (Full location / Landmark) *</label>
                        <textarea name="pickup_address" rows="1" placeholder="E.g. House No, Landmark, Area, City" class="w-full px-3 py-1.5 bg-[#F8FAFC] border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-[#20A8D9] placeholder:text-slate-400" required></textarea>
                    </div>
                    <div>
                        <label class="block text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mb-1">Drop Address (Destination location) *</label>
                        <textarea name="drop_address" rows="1" placeholder="E.g. Destination address or hotel/station name" class="w-full px-3 py-1.5 bg-[#F8FAFC] border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-[#20A8D9] placeholder:text-slate-400" required></textarea>
                    </div>
                    <div>
                        <label class="block text-[10px] font-extrabold text-slate-500 uppercase tracking-wider mb-1">Special Instructions (Optional)</label>
                        <input type="text" name="special_instructions" placeholder="E.g. Extra luggage space, senior citizen travel" class="w-full px-3 py-1.5 bg-[#F8FAFC] border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-[#20A8D9]">
                    </div>
                    
                    <div class="pt-2 border-t border-slate-100 text-right">
                        <button type="button" onclick="goToStep('summary')" class="px-5 py-2 bg-[#EF6614] hover:bg-[#D9530F] text-white font-bold rounded-lg text-xs uppercase tracking-wider transition-colors shadow-sm">
                            Next: Fare Summary <i class="fa-solid fa-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>

                <!-- MODAL STEP 2: FARE SUMMARY (Compact) -->
                <div id="booking-step-summary" class="hidden p-3.5 sm:p-5 space-y-2.5">
                    <div class="bg-[#F4FBFF] p-3 rounded-xl border border-[#C8E6F5] space-y-1.5 text-xs text-slate-700">
                        <div class="flex justify-between border-b border-[#C8E6F5] pb-1.5 mb-1">
                            <span class="text-slate-500">Vehicle Category</span>
                            <span class="text-slate-900 font-bold" id="summary-cat-name">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Base Fare</span>
                            <span class="text-slate-800" id="summary-base-fare">₹0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Distance Fare</span>
                            <span class="text-slate-800" id="summary-distance-fare">₹0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Driver Allowance</span>
                            <span class="text-slate-800" id="summary-driver-allowance">₹0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Tolls & Parking Estimates</span>
                            <span class="text-slate-800" id="summary-toll-parking">₹0.00</span>
                        </div>
                        <div class="flex justify-between text-rose-600" id="summary-discount-row">
                            <span class="font-semibold">Promotional Discount</span>
                            <span class="font-semibold" id="summary-discount-amount">-₹0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">GST (<span id="summary-gst-rate">5</span>%)</span>
                            <span class="text-slate-800" id="summary-gst-amount">₹0.00</span>
                        </div>
                        <div class="flex justify-between border-t border-[#C8E6F5] pt-2 text-base font-black mt-1">
                            <span class="text-slate-900">Total Payable</span>
                            <span class="text-[#0099D4]" id="summary-final-total">₹0.00</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex justify-between">
                        <button type="button" onclick="goToStep('passenger')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-xs transition-colors">
                            <i class="fa-solid fa-chevron-left mr-1"></i> Back
                        </button>
                        <button type="button" onclick="goToStep('payment')" class="px-5 py-2 bg-[#EF6614] hover:bg-[#D9530F] text-white font-bold rounded-lg text-xs uppercase tracking-wider transition-colors shadow-sm">
                            Next: Payment & Verification <i class="fa-solid fa-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>

                <!-- MODAL STEP 3: PAYMENT METHOD & SCREENSHOT (Compact Single View) -->
                <div id="booking-step-payment" class="hidden p-3 sm:p-4 space-y-2.5">
                    <!-- Segmented Mode Selector -->
                    <div class="grid grid-cols-2 gap-1.5 p-1 bg-slate-100 rounded-xl text-xs font-bold">
                        <label id="tab-label-upi" class="text-center py-1.5 px-2 rounded-lg cursor-pointer transition-all flex items-center justify-center gap-1.5 bg-white text-[#20A8D9] shadow-xs">
                            <input type="radio" name="payment_method" value="upi" class="hidden" checked onchange="togglePaymentGatewayView(this.value)">
                            <i class="fa-solid fa-qrcode text-xs"></i> <span>UPI QR & Screenshot</span>
                        </label>
                        <label id="tab-label-cash" class="text-center py-1.5 px-2 rounded-lg cursor-pointer transition-all flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900">
                            <input type="radio" name="payment_method" value="cash" class="hidden" onchange="togglePaymentGatewayView(this.value)">
                            <i class="fa-solid fa-hand-holding-dollar text-xs"></i> <span>Pay Driver (Cash)</span>
                        </label>
                    </div>

                    <!-- UPI Gateway Split Container (Desktop 2-Col, Mobile Single View) -->
                    <div id="upi-gateway-container" class="space-y-2">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-stretch">
                            <!-- Left: QR Code & Account Box -->
                            <div class="md:col-span-5 bg-gradient-to-br from-slate-900 via-slate-850 to-slate-900 text-white p-3 rounded-xl border border-slate-800 flex flex-col items-center justify-between text-center shadow-xs">
                                <div class="w-full flex items-center justify-between text-[10px] text-slate-400 border-b border-slate-800 pb-1 mb-1">
                                    <span class="font-bold text-sky-400 truncate max-w-[120px]"><?php echo htmlspecialchars($front_acc_name ?: $company_name); ?></span>
                                    <span class="text-[9px] bg-emerald-500/20 text-emerald-400 font-bold px-1.5 py-0.2 rounded border border-emerald-500/30">Verified</span>
                                </div>

                                <!-- QR Code Box -->
                                <div class="bg-white p-1 rounded-lg shadow-inner inline-block my-0.5">
                                    <?php
                                    $has_custom_qr = ($front_qr_mode === 'uploaded' && !empty($front_qr_img) && file_exists(__DIR__ . '/' . $front_qr_img));
                                    $initial_qr_src = $has_custom_qr 
                                        ? htmlspecialchars($front_qr_img)
                                        : 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode('upi://pay?pa=' . $front_upi_id . '&pn=' . urlencode($company_name) . '&cu=INR');
                                    ?>
                                    <img id="checkout-upi-qr" src="<?php echo $initial_qr_src; ?>" alt="UPI QR" class="w-28 h-28 sm:w-32 sm:h-32 object-contain rounded">
                                </div>

                                <div class="text-[11px] font-bold text-white mt-1">
                                    Payable: <span id="qr-payable-text" class="text-amber-400 font-black">₹0.00</span>
                                </div>

                                <!-- UPI ID Copy Row -->
                                <div class="w-full bg-slate-950/80 p-1.5 rounded-lg border border-slate-800 flex items-center justify-between text-[11px] mt-1.5">
                                    <span class="font-mono text-amber-300 font-bold truncate max-w-[125px] select-all" id="checkout-upi-id-text"><?php echo htmlspecialchars($front_upi_id); ?></span>
                                    <button type="button" onclick="copyUpiId()" id="btn-copy-upi" class="px-2 py-0.5 bg-sky-500 hover:bg-sky-400 text-white text-[10px] font-bold rounded transition-colors inline-flex items-center gap-1 shrink-0">
                                        <i class="fa-regular fa-copy"></i> <span>Copy</span>
                                    </button>
                                </div>

                                <!-- Mobile 1-Click Direct UPI Payment Redirection -->
                                <div class="w-full mt-2 space-y-1.5 text-left">
                                    <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider text-center">
                                        <i class="fa-solid fa-mobile-screen-button text-emerald-400 mr-1"></i> Pay Directly via Mobile UPI
                                    </span>
                                    <a id="btn-direct-upi-all" href="upi://pay?pa=<?php echo urlencode($front_upi_id); ?>&pn=<?php echo urlencode($company_name); ?>&cu=INR" class="w-full py-2 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-500 hover:from-emerald-500 hover:to-teal-400 text-white font-extrabold text-[11px] rounded-lg shadow-md inline-flex items-center justify-center gap-1.5 transition-all text-center">
                                        <i class="fa-solid fa-bolt text-amber-300"></i> Open Any UPI App (₹<span class="dyn-upi-rate">0.00</span>)
                                    </a>
                                    <!-- App Deep Link Shortcuts -->
                                    <div class="grid grid-cols-3 gap-1 pt-0.5">
                                        <a id="btn-upi-gpay" href="#" class="py-1 px-1 bg-slate-950 hover:bg-slate-800 border border-slate-700 rounded text-[10px] font-bold text-slate-200 inline-flex items-center justify-center gap-1 transition-colors" title="Pay with Google Pay">
                                            <i class="fa-brands fa-google text-rose-400 text-[11px]"></i> GPay
                                        </a>
                                        <a id="btn-upi-phonepe" href="#" class="py-1 px-1 bg-slate-950 hover:bg-slate-800 border border-slate-700 rounded text-[10px] font-bold text-slate-200 inline-flex items-center justify-center gap-1 transition-colors" title="Pay with PhonePe">
                                            <i class="fa-solid fa-wallet text-purple-400 text-[11px]"></i> PhonePe
                                        </a>
                                        <a id="btn-upi-paytm" href="#" class="py-1 px-1 bg-slate-950 hover:bg-slate-800 border border-slate-700 rounded text-[10px] font-bold text-slate-200 inline-flex items-center justify-center gap-1 transition-colors" title="Pay with Paytm">
                                            <i class="fa-solid fa-money-bill text-sky-400 text-[11px]"></i> Paytm
                                        </a>
                                    </div>
                                </div>

                                <!-- Bank details footnote -->
                                <div class="w-full text-[9px] text-slate-400 pt-1.5 mt-2 border-t border-slate-800 flex items-center justify-between">
                                    <span class="truncate"><?php echo htmlspecialchars($front_bank_name); ?></span>
                                    <span class="font-mono">A/C: <?php echo htmlspecialchars($front_acc_num); ?></span>
                                </div>
                            </div>

                            <!-- Right: Screenshot Upload, UTR & Actions -->
                            <div class="md:col-span-7 flex flex-col justify-between space-y-2">
                                <!-- Compact Dropzone -->
                                <div class="bg-[#F8FAFC] border-2 border-dashed border-[#C8E6F5] hover:border-[#20A8D9] rounded-xl p-2 text-center cursor-pointer transition-all" onclick="document.getElementById('payment_screenshot').click()">
                                    <input type="file" name="payment_screenshot" id="payment_screenshot" accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden" onchange="handlePaymentScreenshotUpload(event)">
                                    
                                    <div id="screenshot-placeholder" class="py-1">
                                        <div class="flex items-center justify-center gap-1.5 text-slate-700">
                                            <i class="fa-solid fa-cloud-arrow-up text-[#20A8D9] text-base"></i>
                                            <span class="text-xs font-bold">Attach Payment Screenshot *</span>
                                        </div>
                                        <p class="text-[10px] text-slate-400 mt-0.5">Click or drag screenshot (JPG, PNG, PDF max 10MB)</p>
                                    </div>

                                    <div id="screenshot-preview-container" class="hidden flex items-center justify-between text-left py-0.5">
                                        <div class="flex items-center gap-2">
                                            <img id="screenshot-preview-img" src="" alt="Proof" class="w-8 h-8 object-cover rounded border border-slate-200">
                                            <div class="leading-tight">
                                                <span class="block text-[11px] font-bold text-slate-800 truncate max-w-[140px]" id="screenshot-filename">-</span>
                                                <span class="text-[9px] text-emerald-600 font-bold" id="screenshot-filesize"><i class="fa-solid fa-circle-check"></i> Attached</span>
                                            </div>
                                        </div>
                                        <button type="button" onclick="event.stopPropagation(); removePaymentScreenshot();" class="text-rose-500 text-[10px] font-bold hover:underline px-1 py-0.5">Remove</button>
                                    </div>
                                </div>

                                <!-- 12-Digit UTR Input -->
                                <div>
                                    <label class="block text-[10px] font-extrabold text-slate-600 uppercase tracking-wider mb-0.5">
                                        12-Digit UPI / UTR Transaction ID *
                                    </label>
                                    <input type="text" name="utr_number" id="utr_number" placeholder="Enter 12-digit UPI / UTR Ref ID" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono text-slate-900 focus:outline-none focus:border-[#20A8D9]">
                                </div>

                                <!-- Fast Dispatch & Refund Notice -->
                                <div class="bg-amber-50/90 border border-amber-200/80 rounded-lg p-2 text-[10px] text-amber-900 flex items-start gap-1.5 leading-tight">
                                    <i class="fa-solid fa-shield-halved text-amber-600 text-xs shrink-0 mt-0.5"></i>
                                    <div><strong>Admin Verification Notice:</strong> Booking approved immediately upon screenshot match. Free cancellation up to 1 hr before departure.</div>
                                </div>

                                <!-- Buttons -->
                                <div class="pt-1 flex items-center justify-between gap-2 border-t border-slate-100">
                                    <button type="button" onclick="goToStep('summary')" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition-colors shrink-0">
                                        <i class="fa-solid fa-chevron-left mr-1"></i> Back
                                    </button>
                                    <button type="submit" class="flex-1 py-2 bg-[#EF6614] hover:bg-[#D9530F] text-white font-extrabold rounded-lg text-xs uppercase tracking-wider transition-all shadow-sm flex items-center justify-center">
                                        <span id="chk-btn-txt"><i class="fa-solid fa-cloud-arrow-up mr-1.5"></i> Upload Proof & Place Booking</span>
                                        <span id="chk-btn-spinner" class="hidden"><i class="fa-solid fa-spinner animate-spin mr-1"></i> Placing Booking...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cash on Delivery Notice (Shown when Cash is toggled) -->
                    <div id="cash-gateway-notice" class="hidden bg-[#F4FBFF] border border-[#C8E6F5] rounded-xl p-3 text-xs text-slate-700 space-y-2">
                        <div class="flex items-center gap-2 text-slate-900 font-bold">
                            <i class="fa-solid fa-phone text-[#20A8D9]"></i>
                            <span>Cash on Boarding Verification Policy</span>
                        </div>
                        <p class="text-[11px] text-slate-600 leading-relaxed">Our dispatch team will call your mobile number to confirm pickup coordinates before car dispatch. To guarantee instant car hold without phone call delay, UPI transfer is recommended.</p>
                        <div class="flex items-center justify-between gap-2 pt-1 border-t border-[#C8E6F5]">
                            <button type="button" onclick="goToStep('summary')" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition-colors">
                                <i class="fa-solid fa-chevron-left mr-1"></i> Back
                            </button>
                            <button type="submit" class="flex-1 py-1.5 bg-[#EF6614] hover:bg-[#D9530F] text-white font-extrabold rounded-lg text-xs uppercase tracking-wider transition-all shadow-sm">
                                <i class="fa-solid fa-phone mr-1"></i> Confirm Phone Booking (Pay Later)
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- MODAL SUCCESS PAGE -->
            <div id="booking-step-success" class="hidden p-6 sm:p-8 text-center space-y-5">
                <div class="w-16 h-16 bg-amber-50 text-amber-500 border border-amber-200 rounded-full flex items-center justify-center text-3xl mx-auto shadow-sm" id="success-icon-box">
                    <i class="fa-solid fa-hourglass-half animate-pulse" id="success-icon"></i>
                </div>
                <div>
                    <span class="inline-block px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300 mb-2" id="success-status-badge">
                        Payment Proof Awaiting Admin Approval
                    </span>
                    <h3 class="text-2xl font-extrabold font-poppins text-slate-900" id="success-heading">Reservation Placed Successfully!</h3>
                    <p class="text-xs text-slate-500 mt-1" id="success-subheading">Your payment screenshot has been uploaded. Our dispatch admin is verifying the transaction to execute ride confirmation.</p>
                </div>

                <!-- Approval Lifecycle Timeline -->
                <div class="bg-white border border-slate-200 rounded-2xl p-4 max-w-md mx-auto shadow-sm text-left">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3">Execution Progress</div>
                    <div class="space-y-3 text-xs">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] font-bold"><i class="fa-solid fa-check"></i></span>
                            <span class="text-slate-700 font-semibold">1. Booking & Screenshot Uploaded</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-5 rounded-full bg-amber-500 text-white flex items-center justify-center text-[10px] font-bold animate-pulse"><i class="fa-solid fa-clock"></i></span>
                            <div class="flex-1">
                                <span class="text-amber-700 font-bold block">2. Admin Approval & Execution</span>
                                <span class="text-[10px] text-slate-400">Dispatch manager checks screenshot against bank records (5-10 mins)</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 opacity-60">
                            <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-[10px] font-bold">3</span>
                            <span class="text-slate-500">3. Driver Assigned & Chauffeur SMS Sent</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-[#F4FBFF] p-4 rounded-xl border border-[#C8E6F5] max-w-md mx-auto space-y-2 text-xs text-left">
                    <div class="flex justify-between border-b border-[#C8E6F5] pb-2 mb-2 font-mono">
                        <span class="text-slate-500">Booking Reference</span>
                        <span class="text-[#0099D4] font-bold text-base" id="success-booking-ref">-</span>
                    </div>
                    <p class="text-slate-600">Need instant execution? Contact our 24x7 Raipur dispatch desk on WhatsApp with your booking reference.</p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 justify-center pt-2">
                    <a id="success-whatsapp-btn" href="https://wa.me/918120868818?text=Hi%2C+I+have+booked+cab+and+submitted+payment+screenshot." target="_blank" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs uppercase tracking-wider transition-colors shadow-sm inline-flex items-center justify-center gap-2">
                        <i class="fa-brands fa-whatsapp text-sm"></i> WhatsApp Support (Fast Approval)
                    </a>
                    <button onclick="closeBookingModal(); jumpToTracking();" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition-colors">
                        <i class="fa-solid fa-magnifying-glass mr-1"></i> Track Ride Status
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Partner Registration Modal -->
    <div id="partner-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl relative my-8">
            <button onclick="closePartnerModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 transition-colors focus:outline-none">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
            <div class="bg-[#F4FBFF] px-6 py-4 border-b border-[#C8E6F5]">
                <h3 class="text-lg font-bold text-slate-900 font-poppins">Partner Fleet Registration</h3>
                <p class="text-xs text-slate-500">Attach your commercial vehicle to Avinash Travels network</p>
            </div>
            
            <form id="partner-registration-form" onsubmit="handlePartnerSubmit(event)" enctype="multipart/form-data" class="p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Full Name</label>
                        <input type="text" name="name" placeholder="Owner / Driver name" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Mobile Number</label>
                        <input type="tel" name="phone" placeholder="Active mobile number" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address</label>
                        <input type="email" name="email" placeholder="partner@gmail.com" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Operating City</label>
                        <input type="text" name="city" placeholder="E.g. Raipur / Bilaspur" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Vehicle Category Type</label>
                        <select name="vehicle_type" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
                            <option value="Sedan">Sedan (Dzire, Etios, Aura)</option>
                            <option value="MPV">MPV (Ertiga, XL6)</option>
                            <option value="SUV">SUV (Innova, Crysta)</option>
                            <option value="Luxury">Luxury Class</option>
                            <option value="Tempo">Tempo Traveller</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Vehicle Number</label>
                        <input type="text" name="vehicle_no" placeholder="E.g. CG04-ME-1234" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Driving License Number</label>
                    <input type="text" name="license_no" placeholder="Enter license number" class="w-full px-3.5 py-2.5 bg-[#F8FAFC] border border-slate-200 rounded-xl text-sm text-slate-900 focus:outline-none focus:border-[#20A8D9]" required>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Registration Certificate (RC)</label>
                        <input type="file" name="rc" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#E8F7FD] file:text-[#007AA8] hover:file:bg-[#20A8D9] hover:file:text-white cursor-pointer" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Driving License (DL)</label>
                        <input type="file" name="license" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#E8F7FD] file:text-[#007AA8] hover:file:bg-[#20A8D9] hover:file:text-white cursor-pointer" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Vehicle Insurance</label>
                        <input type="file" name="insurance" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#E8F7FD] file:text-[#007AA8] hover:file:bg-[#20A8D9] hover:file:text-white cursor-pointer" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Driver Photo</label>
                        <input type="file" name="photo" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#E8F7FD] file:text-[#007AA8] hover:file:bg-[#20A8D9] hover:file:text-white cursor-pointer" required>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-between items-center">
                    <span class="text-[11px] text-slate-400 font-semibold"><i class="fa-solid fa-circle-info text-[#20A8D9] mr-1"></i>Max upload size: 5MB per document.</span>
                    <button type="submit" class="px-6 py-2.5 bg-[#EF6614] hover:bg-[#D9530F] text-white font-bold rounded-xl text-xs uppercase tracking-wider transition-colors shadow-sm">
                        Submit Application
                    </button>
                </div>
            </form>
            <div id="partner-status-box" class="hidden p-8 text-center space-y-4">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-full flex items-center justify-center text-2xl mx-auto"><i class="fa-solid fa-circle-check"></i></div>
                <p id="partner-success-msg" class="text-sm text-slate-800 font-semibold"></p>
                <button onclick="closePartnerModal()" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl">Close</button>
            </div>
        </div>
    </div>

    <!-- Core Javascript Functionality -->
    <script>
        // Payment & Merchant Gateway Settings (Dynamic from Admin Settings)
        window.MERCHANT_UPI_ID = <?php echo json_encode($front_upi_id); ?>;
        window.MERCHANT_NAME = <?php echo json_encode($company_name); ?>;
        window.QR_CODE_MODE = <?php echo json_encode($front_qr_mode); ?>;
        window.CUSTOM_QR_IMAGE = <?php echo json_encode((!empty($front_qr_img) && file_exists(__DIR__ . '/' . $front_qr_img)) ? $front_qr_img : ''); ?>;

        // Any-Time Booking Helper (Instant & Advance 24/7)
        function getMinBookingDateTime() {
            const now = new Date();
            const toDateStr = (d) => {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            const todayStr = toDateStr(now);

            return {
                todayStr: todayStr,
                nowDateObj: now
            };
        }

        function validateBookingDateTime(showAlert = false) {
            const dateInput = document.getElementById('search-date');
            const timeInput = document.getElementById('search-time');
            if (!dateInput || !timeInput) return true;

            const { todayStr, nowDateObj } = getMinBookingDateTime();
            
            // Set min date to today
            dateInput.min = todayStr;

            const selectedDate = dateInput.value;
            const selectedTime = timeInput.value;

            if (!selectedDate) return true;

            // 1. Check if chosen date is in the past
            if (selectedDate < todayStr) {
                dateInput.value = todayStr;
                if (showAlert) {
                    alert(`Pickup date cannot be in the past. Please select today (${todayStr}) or a future date.`);
                }
                return false;
            }

            // 2. If booking for today, warn if time has passed significantly
            if (selectedDate === todayStr && selectedTime) {
                const [h, m] = selectedTime.split(':').map(Number);
                const chosenDateTime = new Date();
                chosenDateTime.setHours(h || 0, m || 0, 0, 0);

                if (chosenDateTime.getTime() < nowDateObj.getTime() - (15 * 60 * 1000)) {
                    if (showAlert) {
                        alert(`Selected pickup time has already passed today. Please select a current or upcoming time.`);
                    }
                    return false;
                }
            }

            // 3. Update return date min constraint for round trips
            const rDateInput = document.getElementById('search-return-date');
            if (rDateInput) {
                rDateInput.min = selectedDate;
                if (rDateInput.value && rDateInput.value < selectedDate) {
                    rDateInput.value = selectedDate;
                }
            }

            return true;
        }

        // Set today and current time on page load
        window.addEventListener('load', () => {
            const { todayStr, nowDateObj } = getMinBookingDateTime();
            const dateInput = document.getElementById('search-date');
            const timeInput = document.getElementById('search-time');
            
            if (dateInput) {
                dateInput.min = todayStr;
                if (!dateInput.value) {
                    dateInput.value = todayStr;
                }
            }
            if (timeInput && !timeInput.value) {
                let eh = nowDateObj.getHours();
                let em = Math.ceil(nowDateObj.getMinutes() / 15) * 15;
                if (em >= 60) { eh += 1; em = 0; }
                if (eh >= 24) eh = 0;
                timeInput.value = `${String(eh).padStart(2, '0')}:${String(em).padStart(2, '0')}`;
            }

            const rDate = document.getElementById('search-return-date');
            if (rDate) {
                rDate.min = todayStr;
                if (!rDate.value) rDate.value = todayStr;
                const rTime = document.getElementById('search-return-time');
                if (rTime && !rTime.value) rTime.value = "20:00";
            }
        });

        // Swap Pickup & Drop Cities
        function swapCities() {
            const from = document.getElementById('search-from');
            const to = document.getElementById('search-to');
            const temp = from.value;
            from.value = to.value;
            to.value = temp;
        }

        // Tab Switching Logic (Savaari Segments)
        function switchSearchTab(tab) {
            const tabs = ['oneway', 'roundtrip', 'local', 'airport'];
            const roundFields = document.getElementById('roundtrip-fields');
            const typeInput = document.getElementById('search-trip-type');
            const boxTo = document.getElementById('box-to-city');
            const boxLocal = document.getElementById('box-local-package');
            const boxAirport = document.getElementById('box-airport-route');
            const boxSwap = document.getElementById('box-swap-btn');
            const searchTo = document.getElementById('search-to');
            const fromLabelTxt = document.getElementById('label-from-txt');
            const fromInput = document.getElementById('search-from');

            tabs.forEach(t => {
                const btn = document.getElementById(`btn-tab-${t}`);
                if (btn) {
                    if (t === tab) {
                        btn.className = "tab-active px-4 py-2 rounded-full font-bold text-xs tracking-wide uppercase transition-all shadow-sm flex items-center gap-1.5";
                    } else {
                        btn.className = "bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-full font-bold text-xs tracking-wide uppercase transition-all flex items-center gap-1.5";
                    }
                }
            });

            if (tab === 'oneway') {
                typeInput.value = 'one_way';
                if (roundFields) roundFields.classList.add('hidden');
                if (boxTo) boxTo.classList.remove('hidden');
                if (boxSwap) boxSwap.classList.remove('hidden');
                if (boxLocal) boxLocal.classList.add('hidden');
                if (boxAirport) boxAirport.classList.add('hidden');
                if (fromLabelTxt) fromLabelTxt.innerText = 'FROM (Pickup City)';
                if (fromInput && fromInput.placeholder.includes('Rental')) fromInput.placeholder = 'Enter pickup city (e.g. Raipur)';
                if (searchTo) {
                    searchTo.setAttribute('required', 'required');
                    if (searchTo.value.includes('Hours') || searchTo.value.includes('Airport')) {
                        searchTo.value = '';
                    }
                }
                const retDate = document.getElementById('search-return-date');
                const retTime = document.getElementById('search-return-time');
                if (retDate) retDate.removeAttribute('required');
                if (retTime) retTime.removeAttribute('required');
            } else if (tab === 'roundtrip') {
                typeInput.value = 'round_trip';
                if (roundFields) roundFields.classList.remove('hidden');
                if (boxTo) boxTo.classList.remove('hidden');
                if (boxSwap) boxSwap.classList.remove('hidden');
                if (boxLocal) boxLocal.classList.add('hidden');
                if (boxAirport) boxAirport.classList.add('hidden');
                if (fromLabelTxt) fromLabelTxt.innerText = 'FROM (Pickup City)';
                if (fromInput && fromInput.placeholder.includes('Rental')) fromInput.placeholder = 'Enter pickup city (e.g. Raipur)';
                if (searchTo) {
                    searchTo.setAttribute('required', 'required');
                    if (searchTo.value.includes('Hours') || searchTo.value.includes('Airport')) {
                        searchTo.value = '';
                    }
                }
                const retDate = document.getElementById('search-return-date');
                const retTime = document.getElementById('search-return-time');
                if (retDate) retDate.setAttribute('required', 'required');
                if (retTime) retTime.setAttribute('required', 'required');
            } else if (tab === 'local') {
                typeInput.value = 'local';
                if (roundFields) roundFields.classList.add('hidden');
                if (boxTo) boxTo.classList.add('hidden');
                if (boxSwap) boxSwap.classList.add('hidden');
                if (boxLocal) boxLocal.classList.remove('hidden');
                if (boxAirport) boxAirport.classList.add('hidden');
                if (fromLabelTxt) fromLabelTxt.innerText = 'CITY OF RENTAL (e.g. Raipur)';
                if (fromInput) fromInput.placeholder = 'Enter city for local rental (e.g. Raipur)';
                const pkgSelect = document.getElementById('search-local-package');
                if (searchTo && pkgSelect) {
                    searchTo.removeAttribute('required');
                    searchTo.value = pkgSelect.value;
                }
                const retDate = document.getElementById('search-return-date');
                const retTime = document.getElementById('search-return-time');
                if (retDate) retDate.removeAttribute('required');
                if (retTime) retTime.removeAttribute('required');
            } else if (tab === 'airport') {
                typeInput.value = 'airport';
                if (roundFields) roundFields.classList.add('hidden');
                if (boxTo) boxTo.classList.add('hidden');
                if (boxSwap) boxSwap.classList.add('hidden');
                if (boxLocal) boxLocal.classList.add('hidden');
                if (boxAirport) boxAirport.classList.remove('hidden');
                if (fromLabelTxt) fromLabelTxt.innerText = 'YOUR LOCATION / CITY';
                if (fromInput) fromInput.placeholder = 'Enter your pickup address/city';
                const airSelect = document.getElementById('search-airport-service');
                if (searchTo && airSelect) {
                    searchTo.removeAttribute('required');
                    searchTo.value = airSelect.value;
                }
                const retDate = document.getElementById('search-return-date');
                const retTime = document.getElementById('search-return-time');
                if (retDate) retDate.removeAttribute('required');
                if (retTime) retTime.removeAttribute('required');
            }
        }

        // Toggle Local Hourly vs Sightseeing Packages View
        function switchPackageTab(view) {
            const btnLocal = document.getElementById('pkg-tab-local');
            const btnTours = document.getElementById('pkg-tab-tours');
            const viewLocal = document.getElementById('pkg-view-local');
            const viewTours = document.getElementById('pkg-view-tours');

            if (view === 'local') {
                if (btnLocal) btnLocal.className = "px-5 py-2 rounded-full font-bold text-xs uppercase tracking-wide transition-all bg-[#20A8D9] text-white shadow-sm flex items-center gap-1.5";
                if (btnTours) btnTours.className = "px-5 py-2 rounded-full font-bold text-xs uppercase tracking-wide transition-all text-slate-600 hover:text-slate-900 flex items-center gap-1.5";
                if (viewLocal) viewLocal.classList.remove('hidden');
                if (viewTours) viewTours.classList.add('hidden');
            } else {
                if (btnLocal) btnLocal.className = "px-5 py-2 rounded-full font-bold text-xs uppercase tracking-wide transition-all text-slate-600 hover:text-slate-900 flex items-center gap-1.5";
                if (btnTours) btnTours.className = "px-5 py-2 rounded-full font-bold text-xs uppercase tracking-wide transition-all bg-[#EF6614] text-white shadow-sm flex items-center gap-1.5";
                if (viewLocal) viewLocal.classList.add('hidden');
                if (viewTours) viewTours.classList.remove('hidden');
            }
        }

        // 1-Click Quick Booking for Local Hourly Rental Package
        function bookLocalPackage(packageTitle, vehicleCat = '') {
            switchSearchTab('local');
            const pkgSelect = document.getElementById('search-local-package');
            if (pkgSelect) {
                pkgSelect.value = packageTitle;
            }
            const searchTo = document.getElementById('search-to');
            if (searchTo) {
                searchTo.value = packageTitle;
            }
            const fromInput = document.getElementById('search-from');
            if (fromInput && !fromInput.value.trim()) {
                fromInput.value = 'Raipur';
            }

            const { todayStr } = getMinBookingDateTime();
            const dateInput = document.getElementById('search-date');
            if (dateInput && (!dateInput.value || dateInput.value < todayStr)) {
                dateInput.value = todayStr;
            }

            if (vehicleCat) {
                window.preSelectedFleetCat = vehicleCat;
            }

            const widget = document.getElementById('booking-widget');
            if (widget) {
                widget.scrollIntoView({ behavior: 'smooth' });
            }

            setTimeout(() => {
                const form = document.getElementById('search-cab-form');
                if (form) {
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            }, 300);
        }

        // 1-Click Quick Booking for Tour / Sightseeing Package
        function bookTourPackage(fromCity, tourDestination, tripType = 'round_trip', vehicleCat = '') {
            switchSearchTab(tripType === 'round_trip' ? 'roundtrip' : 'oneway');
            const fromInput = document.getElementById('search-from');
            const toInput = document.getElementById('search-to');
            if (fromInput) fromInput.value = fromCity;
            if (toInput) toInput.value = tourDestination;

            const { todayStr } = getMinBookingDateTime();
            const dateInput = document.getElementById('search-date');
            if (dateInput && (!dateInput.value || dateInput.value < todayStr)) {
                dateInput.value = todayStr;
            }

            if (vehicleCat) {
                window.preSelectedFleetCat = vehicleCat;
            }

            const widget = document.getElementById('booking-widget');
            if (widget) {
                widget.scrollIntoView({ behavior: 'smooth' });
            }

            setTimeout(() => {
                const form = document.getElementById('search-cab-form');
                if (form) {
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            }, 300);
        }

        // Auto pre-fill search from offers/routes click
        function prefillSearch(from, to, coupon = '') {
            switchSearchTab('oneway');
            document.getElementById('search-from').value = from;
            document.getElementById('search-to').value = to;
            if (coupon) {
                document.getElementById('search-coupon').value = coupon;
            }
            document.getElementById('booking-widget').scrollIntoView({ behavior: 'smooth' });
        }

        // 1-Click Quick Booking for One-Way Corridors
        function quickBookOneWay(from, to) {
            switchSearchTab('oneway');
            const fromInput = document.getElementById('search-from');
            const toInput = document.getElementById('search-to');
            fromInput.value = from;
            toInput.value = to;

            const { todayStr, nowDateObj } = getMinBookingDateTime();
            const dateInput = document.getElementById('search-date');
            if (dateInput && (!dateInput.value || dateInput.value < todayStr)) {
                dateInput.min = todayStr;
                dateInput.value = todayStr;
            }

            const timeInput = document.getElementById('search-time');
            if (timeInput && !timeInput.value) {
                let eh = nowDateObj.getHours();
                let em = Math.ceil(nowDateObj.getMinutes() / 15) * 15;
                if (em >= 60) { eh += 1; em = 0; }
                if (eh >= 24) eh = 0;
                timeInput.value = `${String(eh).padStart(2, '0')}:${String(em).padStart(2, '0')}`;
            }

            const widget = document.getElementById('booking-widget');
            if (widget) {
                widget.scrollIntoView({ behavior: 'smooth' });
            }

            // Trigger search automatically
            setTimeout(() => {
                const form = document.getElementById('search-cab-form');
                if (form) {
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            }, 400);
        }

        // Corridor City Filter
        function filterCorridors(city) {
            const buttons = document.querySelectorAll('.corridor-filter-btn');
            buttons.forEach(btn => {
                if (btn.dataset.filter === city) {
                    btn.className = 'corridor-filter-btn px-4 py-2 rounded-full text-xs md:text-sm font-bold transition-all bg-[#20A8D9] text-white shadow-savaari';
                } else {
                    btn.className = 'corridor-filter-btn px-4 py-2 rounded-full text-xs md:text-sm font-bold transition-all bg-white border border-slate-200 text-slate-700 hover:text-[#20A8D9] hover:border-[#20A8D9]';
                }
            });

            const cards = document.querySelectorAll('.corridor-card');
            cards.forEach(card => {
                if (city === 'all') {
                    card.classList.remove('hidden');
                } else {
                    const cities = (card.dataset.cities || '').toLowerCase();
                    if (cities.includes(city.toLowerCase())) {
                        card.classList.remove('hidden');
                    } else {
                        card.classList.add('hidden');
                    }
                }
            });
        }

        // Toggle Complete Route Matrix Table
        function toggleTableBreakdown() {
            const tableWrap = document.getElementById('corridor-table-wrapper');
            const txt = document.getElementById('table-toggle-txt');
            const icon = document.getElementById('table-toggle-icon');

            if (tableWrap.classList.contains('hidden')) {
                tableWrap.classList.remove('hidden');
                txt.innerText = 'Hide Route Fare Matrix Table';
                icon.classList.add('rotate-180');
            } else {
                tableWrap.classList.add('hidden');
                txt.innerText = 'View Complete Route Fare Matrix Table';
                icon.classList.remove('rotate-180');
            }
        }

        // Fleet Vehicle Quick Selection
        window.preSelectedFleetCat = null;

        function selectFleetVehicle(catName, catId, perKm, tripType = 'one_way') {
            window.preSelectedFleetCat = catName;
            
            // If local rental requested from vehicle card, switch tab
            if (tripType === 'local') {
                switchSearchTab('local');
            } else {
                switchSearchTab('oneway');
            }

            // 1. Scroll to booking search widget smoothly
            const widget = document.getElementById('booking-widget');
            if (widget) {
                widget.scrollIntoView({ behavior: 'smooth' });
            }

            // 2. Display visual selected vehicle banner inside the booking widget
            let banner = document.getElementById('fleet-selection-banner');
            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'fleet-selection-banner';
                banner.className = 'mb-5 p-3.5 bg-[#E8F7FD] border border-[#20A8D9] rounded-xl flex items-center justify-between shadow-sm animate-pulse';
                const form = document.getElementById('search-cab-form');
                if (form) {
                    form.parentNode.insertBefore(banner, form);
                }
            }
            if (banner) {
                banner.innerHTML = `
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-full bg-[#20A8D9] text-white flex items-center justify-center text-sm font-bold shadow-sm">
                            <i class="fa-solid fa-car"></i>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Vehicle Chosen:</span>
                                <span class="text-sm font-black text-[#0099D4]">${catName}</span>
                                <span class="text-[10px] font-bold bg-[#EF6614] text-white px-2 py-0.5 rounded-full uppercase">${tripType === 'local' ? 'Local Rental' : 'Outstation'}</span>
                            </div>
                            <span class="text-xs text-slate-500 font-medium">${tripType === 'local' ? 'Fixed Hourly City Rental Package' : `Starting from ₹${perKm}/KM • Fixed Fare Guarantee`}</span>
                        </div>
                    </div>
                    <button type="button" onclick="clearSelectedFleetVehicle()" class="text-slate-400 hover:text-rose-500 text-xs font-bold px-2 py-1 rounded transition-colors" title="Change Vehicle">
                        <i class="fa-solid fa-xmark mr-1"></i> Clear
                    </button>
                `;
                banner.classList.remove('hidden');
            }

            // 3. Update filter select if it exists
            const filterSelect = document.getElementById('filter-type');
            if (filterSelect) {
                for (let i = 0; i < filterSelect.options.length; i++) {
                    if (filterSelect.options[i].value.toLowerCase() === catName.toLowerCase() ||
                        catName.toLowerCase().includes(filterSelect.options[i].value.toLowerCase())) {
                        filterSelect.selectedIndex = i;
                        break;
                    }
                }
            }

            // 4. Trigger search if cities are populated, or populate default route so user gets immediate results
            const fromInput = document.getElementById('search-from');
            const toInput = document.getElementById('search-to');
            if (fromInput) {
                if (!fromInput.value.trim()) {
                    fromInput.value = 'Raipur';
                }
            }
            if (tripType === 'local') {
                const pkg = document.getElementById('search-local-package');
                if (pkg && toInput) toInput.value = pkg.value;
            } else if (toInput && !toInput.value.trim()) {
                toInput.value = 'Bilaspur';
            }

            const form = document.getElementById('search-cab-form');
            if (form) {
                setTimeout(() => {
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }, 300);
            }
        }

        function clearSelectedFleetVehicle() {
            window.preSelectedFleetCat = null;
            const banner = document.getElementById('fleet-selection-banner');
            if (banner) {
                banner.classList.add('hidden');
            }
            const filterSelect = document.getElementById('filter-type');
            if (filterSelect) {
                filterSelect.value = '';
                applyFilters();
            }
        }

        // AJAX Search Submission
        function handleSearchSubmit(e) {
            e.preventDefault();

            // Validate booking date
            if (!validateBookingDateTime(true)) {
                return;
            }

            // Sync trip specific inputs to drop_city before building FormData
            const tripType = document.getElementById('search-trip-type').value;
            const searchTo = document.getElementById('search-to');
            if (tripType === 'local') {
                const pkg = document.getElementById('search-local-package');
                if (pkg && searchTo) {
                    searchTo.value = pkg.value;
                }
            } else if (tripType === 'airport') {
                const air = document.getElementById('search-airport-service');
                if (air && searchTo) {
                    searchTo.value = air.value;
                }
            }
            
            const btnText = document.getElementById('search-btn-txt');
            const btnSpinner = document.getElementById('search-btn-spinner');
            const resultsSection = document.getElementById('results-section');
            const resultsContainer = document.getElementById('results-container');
            const resultsLoader = document.getElementById('results-loader');
            
            btnText.classList.add('hidden');
            btnSpinner.classList.remove('hidden');
            resultsSection.classList.remove('hidden');
            resultsLoader.classList.remove('hidden');
            resultsContainer.innerHTML = '';
            
            const formData = new FormData(e.target);
            
            fetch('api/search_cabs.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnText.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
                resultsLoader.classList.add('hidden');
                
                if (data.success) {
                    document.getElementById('txt-search-route').innerText = `${formData.get('pickup_city')} to ${formData.get('drop_city')}`;
                    resultsContainer.innerHTML = data.html;
                    
                    // If a fleet car was selected, automatically apply filter
                    if (window.preSelectedFleetCat) {
                        const filterSelect = document.getElementById('filter-type');
                        if (filterSelect) {
                            for (let i = 0; i < filterSelect.options.length; i++) {
                                if (filterSelect.options[i].value.toLowerCase() === window.preSelectedFleetCat.toLowerCase() ||
                                    window.preSelectedFleetCat.toLowerCase().includes(filterSelect.options[i].value.toLowerCase())) {
                                    filterSelect.selectedIndex = i;
                                    break;
                                }
                            }
                            applyFilters();
                        }
                    }
                    
                    resultsSection.scrollIntoView({ behavior: 'smooth' });
                } else {
                    resultsContainer.innerHTML = `
                        <div class="bg-[#F4FBFF] border border-[#C8E6F5] rounded-2xl p-8 text-center text-slate-600 text-sm">
                            <i class="fa-solid fa-circle-exclamation text-[#EF6614] text-3xl mb-3"></i>
                            <p class="font-semibold text-slate-800">${data.error || 'No vehicles found for this route.'}</p>
                            <p class="text-xs text-slate-500 mt-1">Please call our 24x7 desk at <?php echo htmlspecialchars($phone); ?> for custom route arrangements.</p>
                        </div>
                    `;
                }
            })
            .catch(err => {
                btnText.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
                resultsLoader.classList.add('hidden');
                alert('Connection error. Please try again.');
            });
        }

        // Filters and Sorting Logic
        function applyFilters() {
            const filterType = document.getElementById('filter-type').value.toLowerCase();
            const sortPrice = document.getElementById('sort-price').value;
            const resultsContainer = document.getElementById('results-container');
            const cards = Array.from(resultsContainer.getElementsByClassName('search-cab-card'));

            if (cards.length === 0) return;

            // Show/Hide filter type
            cards.forEach(card => {
                const heading = (card.querySelector('h3') ? card.querySelector('h3').innerText : '').toLowerCase();
                if (filterType === '' || heading.includes(filterType)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });

            // Sorting
            if (sortPrice !== 'recommended') {
                const visibleCards = cards.filter(c => c.style.display !== 'none');
                visibleCards.sort((a, b) => {
                    const priceElemA = a.querySelector('.price-val');
                    const priceElemB = b.querySelector('.price-val');
                    const priceA = priceElemA ? parseFloat(priceElemA.innerText.replace(/[^\d.]/g, '')) : 0;
                    const priceB = priceElemB ? parseFloat(priceElemB.innerText.replace(/[^\d.]/g, '')) : 0;
                    
                    return (sortPrice === 'low_high') ? (priceA - priceB) : (priceB - priceA);
                });

                visibleCards.forEach(c => resultsContainer.appendChild(c));
            }
        }

        // Fare Breakdown Alert Modal
        function showFareBreakdown(fare) {
            let detailStr = `
FARE BREAKDOWN DETAILS:
========================
Vehicle: ${fare.category_name}
Base Fare: ₹${fare.base_fare}
Distance Fare: ₹${fare.distance_fare}
Driver Allowance: ₹${fare.driver_allowance}
Tolls & Parking: ₹${fare.toll_parking}
Discount: -₹${fare.discount_amount} (${fare.discount_percent}%)
GST (${fare.gst_percent}%): ₹${fare.gst_amount}
------------------------
All-Inclusive Total: ₹${fare.final_total}
            `;
            alert(detailStr);
        }

        // Multi-Step Checkout Modal Operations
        let currentStep = 'passenger';
        
        function initiateBookingFlow(catId, fare) {
            document.getElementById('chk-category-id').value = catId;
            document.getElementById('chk-pickup-city').value = fare.pickup_city;
            document.getElementById('chk-drop-city').value = fare.drop_city;
            document.getElementById('chk-pickup-date').value = document.getElementById('search-date').value;
            document.getElementById('chk-pickup-time').value = document.getElementById('search-time').value;
            document.getElementById('chk-return-date').value = document.getElementById('search-return-date').value;
            document.getElementById('chk-return-time').value = document.getElementById('search-return-time').value;
            document.getElementById('chk-trip-type').value = document.getElementById('search-trip-type').value;
            document.getElementById('chk-coupon-code').value = document.getElementById('search-coupon').value;

            // Step 2 Summary Details
            document.getElementById('summary-cat-name').innerText = fare.category_name;
            document.getElementById('summary-base-fare').innerText = `₹${fare.base_fare}`;
            document.getElementById('summary-distance-fare').innerText = `₹${fare.distance_fare}`;
            document.getElementById('summary-driver-allowance').innerText = `₹${fare.driver_allowance}`;
            document.getElementById('summary-toll-parking').innerText = `₹${fare.toll_parking}`;
            
            if (fare.discount_amount > 0) {
                document.getElementById('summary-discount-row').classList.remove('hidden');
                document.getElementById('summary-discount-amount').innerText = `-₹${fare.discount_amount} (${fare.discount_percent}%)`;
            } else {
                document.getElementById('summary-discount-row').classList.add('hidden');
            }
            
            document.getElementById('summary-gst-rate').innerText = fare.gst_percent;
            document.getElementById('summary-gst-amount').innerText = `₹${fare.gst_amount}`;
            document.getElementById('summary-final-total').innerText = `₹${fare.final_total}`;

            goToStep('passenger');
            document.getElementById('booking-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeBookingModal() {
            document.getElementById('booking-modal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Payment Gateway Helpers
        function togglePaymentGatewayView(method) {
            const upiBox = document.getElementById('upi-gateway-container');
            const cashBox = document.getElementById('cash-gateway-notice');
            const tabUpi = document.getElementById('tab-label-upi');
            const tabCash = document.getElementById('tab-label-cash');
            const btnTxt = document.getElementById('chk-btn-txt');
            
            if (method === 'cash') {
                if (upiBox) upiBox.classList.add('hidden');
                if (cashBox) cashBox.classList.remove('hidden');
                if (btnTxt) btnTxt.innerHTML = '<i class="fa-solid fa-phone mr-1.5"></i> Confirm Phone Booking (Pay Later)';
                if (tabUpi) tabUpi.className = "text-center py-1.5 px-2 rounded-lg cursor-pointer transition-all flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900";
                if (tabCash) tabCash.className = "text-center py-1.5 px-2 rounded-lg cursor-pointer transition-all flex items-center justify-center gap-1.5 bg-white text-[#20A8D9] shadow-xs";
            } else {
                if (upiBox) upiBox.classList.remove('hidden');
                if (cashBox) cashBox.classList.add('hidden');
                if (btnTxt) btnTxt.innerHTML = '<i class="fa-solid fa-cloud-arrow-up mr-1.5"></i> Upload Proof & Place Booking';
                if (tabUpi) tabUpi.className = "text-center py-1.5 px-2 rounded-lg cursor-pointer transition-all flex items-center justify-center gap-1.5 bg-white text-[#20A8D9] shadow-xs";
                if (tabCash) tabCash.className = "text-center py-1.5 px-2 rounded-lg cursor-pointer transition-all flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900";
            }
        }

        function copyUpiId() {
            const upiId = window.MERCHANT_UPI_ID || (document.getElementById('checkout-upi-id-text') ? document.getElementById('checkout-upi-id-text').innerText.trim() : '8120868818@ybl');
            if (!upiId) return;
            navigator.clipboard.writeText(upiId).then(() => {
                const btn = document.getElementById('btn-copy-upi');
                if (btn) {
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Copied!</span>';
                    btn.classList.replace('bg-sky-500', 'bg-emerald-600');
                    setTimeout(() => {
                        btn.innerHTML = '<i class="fa-regular fa-copy"></i> <span>Copy UPI</span>';
                        btn.classList.replace('bg-emerald-600', 'bg-sky-500');
                    }, 2500);
                }
            }).catch(err => {
                const temp = document.createElement('input');
                temp.value = upiId;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                alert('UPI ID copied to clipboard: ' + upiId);
            });
        }

        function handlePaymentScreenshotUpload(event) {
            const input = event.target;
            const file = input.files && input.files[0];
            if (!file) return;

            if (file.size > 10 * 1024 * 1024) {
                alert('Screenshot file size must be less than 10MB.');
                input.value = '';
                return;
            }

            const placeholder = document.getElementById('screenshot-placeholder');
            const previewContainer = document.getElementById('screenshot-preview-container');
            const previewImg = document.getElementById('screenshot-preview-img');
            const filenameSpan = document.getElementById('screenshot-filename');
            const filesizeSpan = document.getElementById('screenshot-filesize');

            filenameSpan.innerText = file.name;
            const kbSize = (file.size / 1024).toFixed(1);
            filesizeSpan.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> Attached (${kbSize} KB) - Ready for Admin Verification`;

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                // PDF fallback
                previewImg.src = 'https://cdn-icons-png.flaticon.com/512/337/337946.png';
            }

            placeholder.classList.add('hidden');
            previewContainer.classList.remove('hidden');
        }

        function removePaymentScreenshot() {
            const input = document.getElementById('payment_screenshot');
            if (input) input.value = '';
            
            const placeholder = document.getElementById('screenshot-placeholder');
            const previewContainer = document.getElementById('screenshot-preview-container');
            if (placeholder) placeholder.classList.remove('hidden');
            if (previewContainer) previewContainer.classList.add('hidden');
        }

        function jumpToTracking() {
            const trackingSec = document.getElementById('tracking-result-box');
            if (trackingSec) {
                trackingSec.scrollIntoView({ behavior: 'smooth' });
                const trackIdInput = document.getElementById('track-id');
                const lastBookingId = document.getElementById('success-booking-ref').innerText;
                if (trackIdInput && lastBookingId && lastBookingId !== '-') {
                    trackIdInput.value = lastBookingId;
                }
                const trackPhoneInput = document.getElementById('track-phone');
                if (trackPhoneInput) trackPhoneInput.focus();
            }
        }

        function goToStep(step) {
            currentStep = step;
            const passengerSec = document.getElementById('booking-step-passenger');
            const summarySec = document.getElementById('booking-step-summary');
            const paymentSec = document.getElementById('booking-step-payment');
            const successSec = document.getElementById('booking-step-success');

            const indPass = document.getElementById('step-indicator-passenger');
            const indSum = document.getElementById('step-indicator-summary');
            const indPay = document.getElementById('step-indicator-payment');

            passengerSec.classList.add('hidden');
            summarySec.classList.add('hidden');
            paymentSec.classList.add('hidden');
            successSec.classList.add('hidden');

            indPass.className = "text-slate-400";
            indSum.className = "text-slate-400";
            indPay.className = "text-slate-400";

            if (step === 'passenger') {
                passengerSec.classList.remove('hidden');
                indPass.className = "text-[#0099D4] border-b-2 border-[#0099D4] pb-1";
            } else if (step === 'summary') {
                const form = document.getElementById('checkout-booking-form');
                if (!form.reportValidity()) {
                    passengerSec.classList.remove('hidden');
                    indPass.className = "text-[#0099D4] border-b-2 border-[#0099D4] pb-1";
                    return;
                }
                summarySec.classList.remove('hidden');
                indSum.className = "text-[#0099D4] border-b-2 border-[#0099D4] pb-1";
            } else if (step === 'payment') {
                paymentSec.classList.remove('hidden');
                indPay.className = "text-[#0099D4] border-b-2 border-[#0099D4] pb-1";

                // Update QR Code and Mobile UPI Deep Links with dynamic amount
                const finalTotalStr = document.getElementById('summary-final-total').innerText;
                const cleanAmount = finalTotalStr.replace(/[^\d.]/g, '') || '0';
                const formattedRate = parseFloat(cleanAmount).toFixed(2);
                
                const qrTextSpan = document.getElementById('qr-payable-text');
                if (qrTextSpan) qrTextSpan.innerText = '₹' + formattedRate;

                // Update rate displays
                document.querySelectorAll('.dyn-upi-rate').forEach(el => el.innerText = formattedRate);

                const targetUpi = window.MERCHANT_UPI_ID || '8120868818@ybl';
                const targetName = encodeURIComponent(window.MERCHANT_NAME || 'Avinash Tour and Travels');
                const note = encodeURIComponent('Cab Reservation - Avinash Travels');

                // Standard NPCI UPI URI schemes
                const upiString = `upi://pay?pa=${encodeURIComponent(targetUpi)}&pn=${targetName}&am=${cleanAmount}&cu=INR&tn=${note}`;
                const gpayUri   = `tez://upi/pay?pa=${encodeURIComponent(targetUpi)}&pn=${targetName}&am=${cleanAmount}&cu=INR&tn=${note}`;
                const phonepeUri= `phonepe://pay?pa=${encodeURIComponent(targetUpi)}&pn=${targetName}&am=${cleanAmount}&cu=INR&tn=${note}`;
                const paytmUri  = `paytmmp://pay?pa=${encodeURIComponent(targetUpi)}&pn=${targetName}&am=${cleanAmount}&cu=INR&tn=${note}`;

                // Update mobile 1-click payment buttons
                const btnAll = document.getElementById('btn-direct-upi-all');
                if (btnAll) btnAll.href = upiString;

                const btnGpay = document.getElementById('btn-upi-gpay');
                if (btnGpay) btnGpay.href = gpayUri;

                const btnPhonePe = document.getElementById('btn-upi-phonepe');
                if (btnPhonePe) btnPhonePe.href = phonepeUri;

                const btnPaytm = document.getElementById('btn-upi-paytm');
                if (btnPaytm) btnPaytm.href = paytmUri;

                const qrImg = document.getElementById('checkout-upi-qr');
                if (qrImg) {
                    if (window.QR_CODE_MODE === 'uploaded' && window.CUSTOM_QR_IMAGE) {
                        qrImg.src = window.CUSTOM_QR_IMAGE;
                    } else {
                        qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(upiString)}`;
                    }
                }
            } else if (step === 'success') {
                successSec.classList.remove('hidden');
            }
        }

        // Checkout Final Submission with Screenshot Validation
        function handleCheckoutSubmit(e) {
            e.preventDefault();
            
            const form = e.target;
            const payMethod = (form.querySelector('input[name="payment_method"]:checked') || {}).value || 'upi';

            // Validate screenshot upload and UTR if UPI is chosen
            if (payMethod === 'upi') {
                const ssInput = document.getElementById('payment_screenshot');
                const utrInput = document.getElementById('utr_number');
                
                if (!ssInput || !ssInput.files || ssInput.files.length === 0) {
                    alert('Please select and upload your payment screenshot proof before placing booking.');
                    ssInput.click();
                    return;
                }

                if (!utrInput || !utrInput.value.trim()) {
                    alert('Please enter your 12-digit UPI / UTR Reference ID from your payment app.');
                    utrInput.focus();
                    return;
                }
            }
            
            const btnText = document.getElementById('chk-btn-txt');
            const btnSpinner = document.getElementById('chk-btn-spinner');
            
            btnText.classList.add('hidden');
            btnSpinner.classList.remove('hidden');

            const formData = new FormData(form);

            fetch('api/book.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnText.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
                
                if (data.success) {
                    document.getElementById('success-booking-ref').innerText = data.booking_id;
                    
                    // Prepopulate WhatsApp direct link with booking details
                    const waBtn = document.getElementById('success-whatsapp-btn');
                    if (waBtn) {
                        const utrVal = data.utr_number || (document.getElementById('utr_number') ? document.getElementById('utr_number').value.trim() : '');
                        const waMsg = `Hello Avinash Travels, I have submitted a booking #${data.booking_id} with payment screenshot proof (UTR: ${utrVal}). Please verify and execute ride confirmation.`;
                        waBtn.href = `https://wa.me/918120868818?text=${encodeURIComponent(waMsg)}`;
                    }

                    goToStep('success');
                } else {
                    alert('Booking failed: ' + (data.error || 'Server error occurred.'));
                }
            })
            .catch(err => {
                btnText.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
                alert('Connection error. Please try booking again.');
            });
        }

        // Booking Status Tracking
        function handleTrackingSubmit(e) {
            e.preventDefault();
            
            const trackId = document.getElementById('track-id').value;
            const trackPhone = document.getElementById('track-phone').value;
            const resultBox = document.getElementById('tracking-result-box');
            
            resultBox.innerHTML = '<p class="text-slate-500 text-xs text-center py-4"><i class="fa-solid fa-spinner animate-spin mr-1.5 text-[#20A8D9]"></i> Retrieving status details...</p>';

            const formData = new FormData();
            formData.append('booking_id', trackId);
            formData.append('phone', trackPhone);

            fetch('/api/track_booking.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    resultBox.innerHTML = data.html;
                } else {
                    resultBox.innerHTML = `
                        <div class="bg-rose-50 text-rose-700 border border-rose-200 p-4 rounded-xl text-xs text-left">
                            <strong>Lookup Failed:</strong> ${data.error || 'Invalid booking reference details.'}
                        </div>
                    `;
                }
            })
            .catch(err => {
                resultBox.innerHTML = '<p class="text-rose-600 text-xs text-center">Connection error. Please try again.</p>';
            });
        }

        // Partner Modal Operations
        function openPartnerModal() {
            document.getElementById('partner-status-box').classList.add('hidden');
            document.getElementById('partner-registration-form').classList.remove('hidden');
            document.getElementById('partner-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closePartnerModal() {
            document.getElementById('partner-modal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        function handlePartnerSubmit(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            const statusBox = document.getElementById('partner-status-box');
            const successMsg = document.getElementById('partner-success-msg');
            
            fetch('/api/partner_register.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    form.classList.add('hidden');
                    statusBox.classList.remove('hidden');
                    successMsg.innerText = data.message;
                    form.reset();
                } else {
                    alert('Submission failed: ' + data.error);
                }
            })
            .catch(err => {
                alert('Connection error. Please try again.');
            });
        }

        // Accordion FAQ toggle
        function toggleFaq(index) {
            const ans = document.getElementById(`faq-ans-${index}`);
            const icon = document.getElementById(`faq-icon-${index}`);
            
            if (ans.classList.contains('hidden')) {
                ans.classList.remove('hidden');
                icon.style.transform = 'rotate(180deg)';
            } else {
                ans.classList.add('hidden');
                icon.style.transform = 'rotate(0deg)';
            }
        }

        // Contact enquiry form handler
        function handleContactSubmit(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            const successMsg = document.getElementById('contact-success-msg');
            const errorMsg = document.getElementById('contact-error-msg');
            
            successMsg.classList.add('hidden');
            errorMsg.classList.add('hidden');
            
            fetch('/api/contact.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    successMsg.innerText = data.message;
                    successMsg.classList.remove('hidden');
                    form.reset();
                } else {
                    errorMsg.innerText = data.error;
                    errorMsg.classList.remove('hidden');
                }
            })
            .catch(err => {
                errorMsg.innerText = 'Connection error. Please try again.';
                errorMsg.classList.remove('hidden');
            });
        }

        function toggleMenu() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        }
        document.getElementById('menu-btn').addEventListener('click', toggleMenu);
    </script>
</body>
</html>
