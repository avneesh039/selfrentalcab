<?php
// API - Search Cabs and Calculate Real-time Fares
if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pricing_helper.php';

global $pdo;

try {
    $pickup_city = trim($_POST['pickup_city'] ?? '');
    $drop_city = trim($_POST['drop_city'] ?? '');
    $pickup_date = trim($_POST['pickup_date'] ?? '');
    $pickup_time = trim($_POST['pickup_time'] ?? '');
    $trip_type = trim($_POST['trip_type'] ?? 'one_way');
    $coupon_code = trim($_POST['coupon_code'] ?? '');

    if ($trip_type === 'local') {
        if (empty($drop_city)) {
            $drop_city = trim($_POST['local_package'] ?? '8 Hours / 80 KMs');
        }
    }

    if (empty($pickup_city) || empty($drop_city) || empty($pickup_date) || empty($pickup_time)) {
        throw new Exception("All search fields are required.");
    }

    // Date validation: Allow search anytime (ensure pickup date is not in the past)
    $today_date = date('Y-m-d');
    if ($pickup_date < $today_date) {
        throw new Exception("Pickup date cannot be in the past.");
    }

    // Check if route exists in DB
    $route = null;
    if ($trip_type === 'local') {
        $pkg_km = (strpos($drop_city, '4') !== false) ? 40 : ((strpos($drop_city, '12') !== false) ? 120 : 80);
        $pkg_hrs = (strpos($drop_city, '4') !== false) ? 4 : ((strpos($drop_city, '12') !== false) ? 12 : 8);
        $route = [
            'pickup_city' => $pickup_city,
            'drop_city' => $drop_city,
            'distance_km' => $pkg_km,
            'est_hours' => $pkg_hrs,
            'status' => 'active'
        ];
    } elseif ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `routes` WHERE (pickup_city = ? AND drop_city = ?) AND status = 'active'");
            $stmt->execute([$pickup_city, $drop_city]);
            $route = $stmt->fetch();
        } catch (Exception $e) {}
    }

    // Fallback to fixed one-way routes matrix or comprehensive Chhattisgarh highway distance
    if (!$route) {
        $fixed_route = find_fixed_oneway_route($pickup_city, $drop_city);
        if ($fixed_route) {
            $route = [
                'pickup_city' => $pickup_city,
                'drop_city' => $drop_city,
                'distance_km' => $fixed_route['distance_km'],
                'est_hours' => $fixed_route['est_hours'],
                'status' => 'active'
            ];
        } else {
            $cg_est = get_chhattisgarh_route_estimate($pickup_city, $drop_city);
            if ($cg_est) {
                $route = [
                    'pickup_city' => $pickup_city,
                    'drop_city' => $drop_city,
                    'distance_km' => $cg_est['distance_km'],
                    'est_hours' => $cg_est['est_hours'],
                    'status' => 'active'
                ];
            } elseif (stripos($pickup_city, 'airport') !== false || stripos($drop_city, 'airport') !== false) {
                $route = [
                    'pickup_city' => $pickup_city,
                    'drop_city' => $drop_city,
                    'distance_km' => 45,
                    'est_hours' => 1.0,
                    'status' => 'active'
                ];
            } else {
                throw new Exception("Currently, we do not operate cabs directly between $pickup_city and $drop_city. Please contact our 24/7 support team at 8120868818.");
            }
        }
    }

    // Fetch active vehicle categories
    $categories = [];
    if ($pdo) {
        try {
            $categories = $pdo->query("SELECT * FROM `vehicle_categories` WHERE status = 'active' ORDER BY base_price ASC")->fetchAll();
        } catch (Exception $e) {}
    }
    
    if (empty($categories)) {
        // Fallback default vehicles
        $categories = [
            [
                'id' => 1,
                'name' => 'Sedan',
                'seating_capacity' => 4,
                'luggage_capacity' => 2,
                'ac' => 1,
                'amenities' => 'AC, Music System, Boot Space, Chauffeur Included, Bottled Water',
                'image' => 'sedan.png',
                'base_price' => 500.00,
                'per_km_price' => 11.00
            ],
            [
                'id' => 3,
                'name' => 'MPV',
                'seating_capacity' => 7,
                'luggage_capacity' => 3,
                'ac' => 1,
                'amenities' => 'AC, Extra Legroom, 7-Seater, Carrier, Bottled Water',
                'image' => 'mpv.png',
                'base_price' => 1000.00,
                'per_km_price' => 16.50
            ],
            [
                'id' => 2,
                'name' => 'SUV',
                'seating_capacity' => 6,
                'luggage_capacity' => 4,
                'ac' => 1,
                'amenities' => 'AC, Premium Ride, Rooftop Carrier, Bluetooth Audio',
                'image' => 'suv.png',
                'base_price' => 800.00,
                'per_km_price' => 15.00
            ],
            [
                'id' => 4,
                'name' => 'Luxury',
                'seating_capacity' => 4,
                'luggage_capacity' => 2,
                'ac' => 1,
                'amenities' => 'Dual-Zone Climate, Leather Recliners, Wi-Fi, Chauffeur',
                'image' => 'luxury.png',
                'base_price' => 2000.00,
                'per_km_price' => 30.00
            ]
        ];
    }

    $cards_html = "";
    
    foreach ($categories as $cat) {
        $fare = calculate_trip_fare([
            'pickup_city' => $pickup_city,
            'drop_city' => $drop_city,
            'pickup_date' => $pickup_date,
            'pickup_time' => $pickup_time,
            'trip_type' => $trip_type,
            'category_id' => $cat['id'],
            'coupon_code' => $coupon_code
        ]);

        if (isset($fare['error'])) {
            continue;
        }

        // Format vehicle title & subtitle
        $vehicle_display_name = htmlspecialchars($cat['name']);
        $cat_subtitle = "Door-to-door pickup • Professional Chauffeur included";
        
        if ($cat['name'] === 'Sedan') {
            $vehicle_display_name = "Swift Dzire (Sedan)";
            $cat_subtitle = "Maruti Suzuki Dzire • AC Comfort • Best for 1-4 passengers";
        } elseif ($cat['name'] === 'MPV') {
            $vehicle_display_name = "Maruti Suzuki Ertiga (MPV)";
            $cat_subtitle = "Maruti Ertiga 7-Seater • Spacious Family Ride with Luggage";
        } elseif ($cat['name'] === 'SUV') {
            $vehicle_display_name = "Innova Crysta / SUV";
            $cat_subtitle = "Premium SUV • High Ground Clearance • Group Comfort";
        }

        // Format amenities list
        $amenities = explode(',', $cat['amenities']);
        $amenity_badges = "";
        foreach ($amenities as $am) {
            $badge = trim($am);
            if ($badge) {
                $amenity_badges .= "<span class='badge bg-[#F8FAFC] text-slate-600 text-xs px-2.5 py-1 rounded-full border border-slate-200 mr-1.5 mb-1.5 inline-block font-medium'>$badge</span>";
            }
        }

        // Image mapping
        $img_src = get_vehicle_image_path($cat['name'], $cat['image'] ?? '');

        // Pricing HTML
        $price_html = "";
        if ($fare['discount_amount'] > 0) {
            $orig = $fare['final_total'] + $fare['discount_amount'];
            $price_html .= "<span class='text-sm text-slate-400 line-through mr-2 font-medium'>₹" . number_format($orig, 2) . "</span>";
        }
        $price_html .= "<span class='price-val text-2xl font-extrabold text-[#0099D4]'>₹" . number_format($fare['final_total'], 2) . "</span>";

        // Special Badge for Fixed Package / Inclusions
        $inclusion_badge_html = "";
        if (!empty($fare['inclusion_badge'])) {
            $inclusion_badge_html = "<span class='bg-emerald-50 text-emerald-700 text-xs px-2.5 py-0.5 rounded-full border border-emerald-200 font-bold inline-flex items-center gap-1'><i class='fa-solid fa-circle-check text-emerald-600 text-[10px]'></i> " . htmlspecialchars($fare['inclusion_badge']) . "</span>";
        }

        $trip_type_label = 'One Way';
        if ($trip_type === 'round_trip') $trip_type_label = 'Round Trip';
        elseif ($trip_type === 'local') $trip_type_label = 'Local Rental';
        elseif ($trip_type === 'airport') $trip_type_label = 'Airport Transfer';

        $cards_html .= "
        <div class='search-cab-card bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col md:flex-row mb-6'>
            <!-- Vehicle Image -->
            <div class='md:w-1/3 bg-[#F4FBFF] p-6 flex flex-col justify-center items-center border-b md:border-b-0 md:border-r border-[#C8E6F5] relative'>
                <div class='absolute top-3 left-3 bg-[#E8F7FD] text-[#007AA8] text-xs font-bold px-2.5 py-0.5 rounded-full border border-[#C8E6F5] uppercase tracking-wider'>
                    " . strtoupper($trip_type_label) . "
                </div>
                <img src='" . $img_src . "' alt='" . htmlspecialchars($vehicle_display_name) . "' class='w-full max-h-36 object-contain filter drop-shadow-sm mb-3 transition-transform hover:scale-105 duration-200' onerror=\"this.onerror=null; this.src='assets/images/vehicles/sedan.png';\">
                <span class='text-xs font-semibold text-slate-600 bg-white px-3 py-1 rounded-full border border-slate-200 shadow-sm'>" . htmlspecialchars($cat['seating_capacity']) . " Seats • " . htmlspecialchars($cat['luggage_capacity']) . " Bags</span>
            </div>
            
            <!-- Vehicle Specifications -->
            <div class='md:w-2/3 p-6 flex flex-col justify-between'>
                <div>
                    <div class='flex flex-wrap justify-between items-start gap-2 mb-2'>
                        <div>
                            <h3 class='text-xl font-extrabold text-slate-900 font-poppins'>" . $vehicle_display_name . "</h3>
                            <p class='text-xs text-slate-500 font-medium'>" . $cat_subtitle . "</p>
                        </div>
                        <div class='flex flex-wrap items-center gap-1.5'>
                            " . $inclusion_badge_html . "
                            " . ($cat['ac'] ? "<span class='bg-[#E8F7FD] text-[#007AA8] text-xs px-2.5 py-0.5 rounded-full border border-[#C8E6F5] font-bold'>AC Cab</span>" : "<span class='bg-slate-100 text-slate-600 text-xs px-2.5 py-0.5 rounded-full border border-slate-200 font-medium'>Non-AC</span>") . "
                        </div>
                    </div>
                    
                    <!-- Amenities -->
                    <div class='my-4'>
                        " . $amenity_badges . "
                    </div>
                    
                    <!-- Distance Details -->
                    <div class='text-xs text-slate-600 font-medium flex items-center gap-2 bg-[#F8FAFC] px-3 py-2 rounded-lg border border-slate-100 w-fit'>
                        <span><i class='fa-solid fa-route text-[#20A8D9] mr-1'></i> Est. Distance: <strong class='text-slate-900'>" . $fare['distance_km'] . " KM</strong></span>
                        <span>•</span>
                        <span><i class='fa-regular fa-clock text-[#20A8D9] mr-1'></i> Duration: <strong class='text-slate-900'>" . $fare['est_hours'] . " Hours</strong></span>
                    </div>
                </div>

                <!-- Fare & Actions -->
                <div class='mt-6 pt-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4'>
                    <div class='text-center sm:text-left'>
                        <span class='block text-[11px] font-bold text-slate-400 uppercase tracking-wider'>" . ($fare['is_fixed_package'] ? 'Guaranteed Package Fare (All-Inclusive)' : 'Guaranteed Final Fare (All Inclusive)') . "</span>
                        <div class='flex items-center justify-center sm:justify-start'>
                            " . $price_html . "
                        </div>
                    </div>
                    
                    <div class='flex gap-2 w-full sm:w-auto'>
                        <button onclick='showFareBreakdown(" . json_encode($fare) . ")' class='w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold hover:border-[#20A8D9] hover:text-[#20A8D9] bg-white transition-colors text-xs uppercase tracking-wider'>
                            Fare Details
                        </button>
                        <button onclick='initiateBookingFlow(" . $cat['id'] . ", " . json_encode($fare) . ")' class='w-full sm:w-auto px-6 py-2.5 rounded-xl bg-[#EF6614] hover:bg-[#D9530F] text-white font-extrabold transition-all text-xs uppercase tracking-wider shadow-sm'>
                            Book Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
        ";
    }

    echo json_encode([
        'success' => true,
        'html' => $cards_html,
        'distance_km' => $route['distance_km'],
        'est_hours' => $route['est_hours']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
