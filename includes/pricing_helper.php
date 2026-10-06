<?php
// Pricing Helper Module
// Calculates exact vehicle fares server-side for Avinash Travels

require_once __DIR__ . '/db.php';

/**
 * Master catalog of guaranteed fixed one-way routes & flat package fares
 */
function get_fixed_oneway_routes() {
    return [
        'bilaspur|raipur' => [
            'route_key' => 'bilaspur|raipur',
            'title' => 'Bilaspur ↔ Raipur',
            'city_a' => 'Bilaspur',
            'city_b' => 'Raipur',
            'distance_km' => 120.00,
            'est_hours' => 2.50,
            'dzire_fare' => 2500.00,
            'ertiga_fare' => 3000.00,
            'toll_included' => true,
            'parking_included' => true,
            'inclusions_text' => 'Toll & Parking: Included',
            'badge' => 'Toll & Parking Included',
            'popular' => true,
            'tag' => 'Capital Express'
        ],
        'bilaspur|raigarh' => [
            'route_key' => 'bilaspur|raigarh',
            'title' => 'Bilaspur ↔ Raigarh',
            'city_a' => 'Bilaspur',
            'city_b' => 'Raigarh',
            'distance_km' => 160.00,
            'est_hours' => 3.50,
            'dzire_fare' => 3000.00,
            'ertiga_fare' => 4500.00,
            'toll_included' => true,
            'parking_included' => false,
            'inclusions_text' => 'Toll: Included',
            'badge' => 'Toll Included',
            'popular' => true,
            'tag' => 'Industrial Corridor'
        ],
        'raigarh|raipur' => [
            'route_key' => 'raigarh|raipur',
            'title' => 'Raigarh ↔ Raipur',
            'city_a' => 'Raigarh',
            'city_b' => 'Raipur',
            'distance_km' => 240.00,
            'est_hours' => 5.00,
            'dzire_fare' => 4500.00,
            'ertiga_fare' => 5500.00,
            'toll_included' => false,
            'parking_included' => false,
            'inclusions_text' => 'Standard Rate',
            'badge' => 'Flat Rate',
            'popular' => true,
            'tag' => 'Long Distance'
        ],
        'raigarh|jharsuguda' => [
            'route_key' => 'raigarh|jharsuguda',
            'title' => 'Raigarh ↔ Jharsuguda',
            'city_a' => 'Raigarh',
            'city_b' => 'Jharsuguda',
            'distance_km' => 80.00,
            'est_hours' => 1.75,
            'dzire_fare' => 2500.00,
            'ertiga_fare' => 3500.00,
            'toll_included' => true,
            'parking_included' => false,
            'inclusions_text' => 'Toll: Included',
            'badge' => 'Toll Included',
            'popular' => true,
            'tag' => 'Interstate Corridor'
        ],
        'korba|bilaspur' => [
            'route_key' => 'korba|bilaspur',
            'title' => 'Korba ↔ Bilaspur',
            'city_a' => 'Korba',
            'city_b' => 'Bilaspur',
            'distance_km' => 90.00,
            'est_hours' => 2.00,
            'dzire_fare' => 2200.00,
            'ertiga_fare' => 3500.00,
            'toll_included' => false,
            'parking_included' => false,
            'inclusions_text' => 'Standard Rate',
            'badge' => 'Flat Rate',
            'popular' => true,
            'tag' => 'Express Corridor'
        ],
        'korba|raipur' => [
            'route_key' => 'korba|raipur',
            'title' => 'Korba ↔ Raipur',
            'city_a' => 'Korba',
            'city_b' => 'Raipur',
            'distance_km' => 210.00,
            'est_hours' => 4.50,
            'dzire_fare' => 4500.00,
            'ertiga_fare' => 5500.00,
            'toll_included' => true,
            'parking_included' => true,
            'inclusions_text' => 'Toll & Parking: Included',
            'badge' => 'Toll & Parking Included',
            'popular' => true,
            'tag' => 'Direct Express'
        ]
    ];
}

/**
 * Match a pickup and drop pair against fixed one-way route matrix (bidirectional)
 */
function find_fixed_oneway_route($pickup, $drop) {
    $pickup = strtolower(trim($pickup));
    $drop = strtolower(trim($drop));
    $routes = get_fixed_oneway_routes();
    
    foreach ($routes as $key => $r) {
        $c1 = strtolower($r['city_a']);
        $c2 = strtolower($r['city_b']);
        if (($pickup === $c1 && $drop === $c2) || ($pickup === $c2 && $drop === $c1)) {
            $matched = $r;
            $matched['direction_pickup'] = ($pickup === $c1) ? $r['city_a'] : $r['city_b'];
            $matched['direction_drop'] = ($drop === $c2) ? $r['city_b'] : $r['city_a'];
            return $matched;
        }
    }
    return null;
}

function calculate_trip_fare($params) {
    global $pdo;

    $pickup_city = trim($params['pickup_city'] ?? '');
    $drop_city = trim($params['drop_city'] ?? '');
    $pickup_date = trim($params['pickup_date'] ?? date('Y-m-d'));
    $pickup_time = trim($params['pickup_time'] ?? date('H:i'));
    $trip_type = trim($params['trip_type'] ?? 'one_way');
    $category_id = intval($params['category_id'] ?? 1);
    $coupon_code = trim($params['coupon_code'] ?? '');

    // 1. Fetch category rates
    $category = null;
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `vehicle_categories` WHERE id = ? AND status = 'active'");
            $stmt->execute([$category_id]);
            $category = $stmt->fetch();
        } catch (Exception $e) {}
    }

    if (!$category) {
        $default_cats = [
            1 => ['id' => 1, 'name' => 'Sedan', 'base_price' => 500.00, 'per_km_price' => 11.00, 'seating_capacity' => 4, 'luggage_capacity' => 2, 'ac' => 1, 'amenities' => 'AC, Charging Port, Music System, Bottled Water', 'image' => 'sedan.png'],
            2 => ['id' => 2, 'name' => 'SUV', 'base_price' => 800.00, 'per_km_price' => 15.00, 'seating_capacity' => 6, 'luggage_capacity' => 4, 'ac' => 1, 'amenities' => 'AC, Charging Port, Luggage Carrier, Bottled Water, Bluetooth Stereo', 'image' => 'suv.png'],
            3 => ['id' => 3, 'name' => 'MPV', 'base_price' => 1000.00, 'per_km_price' => 16.50, 'seating_capacity' => 7, 'luggage_capacity' => 3, 'ac' => 1, 'amenities' => 'AC, Charging Port, Extra Legroom, Bottled Water, Stereo System', 'image' => 'mpv.png'],
            4 => ['id' => 4, 'name' => 'Luxury', 'base_price' => 2000.00, 'per_km_price' => 30.00, 'seating_capacity' => 4, 'luggage_capacity' => 2, 'ac' => 1, 'amenities' => 'Dual-Zone AC, Rear Charging, Leather Seats, Premium Sound, Wi-Fi', 'image' => 'luxury.png'],
        ];
        $category = $default_cats[$category_id] ?? $default_cats[1];
    }

    // 2. Fetch route distance
    $route = null;
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `routes` WHERE (pickup_city = ? AND drop_city = ?) AND status = 'active'");
            $stmt->execute([$pickup_city, $drop_city]);
            $route = $stmt->fetch();
        } catch (Exception $e) {}
    }

    $fixed_route = find_fixed_oneway_route($pickup_city, $drop_city);

    $distance = 0;
    $est_hours = 0;
    $cat_name_upper = strtoupper($category['name'] ?? '');

    if ($trip_type === 'local') {
        $drop_clean = strtolower(trim((string)$drop_city));
        if (strpos($drop_clean, '4') !== false) {
            $distance = 40;
            $est_hours = 4;
        } elseif (strpos($drop_clean, '12') !== false) {
            $distance = 120;
            $est_hours = 12;
        } else {
            $distance = 80;
            $est_hours = 8;
        }
    } elseif ($route) {
        $distance = floatval($route['distance_km']);
        $est_hours = floatval($route['est_hours']);
    } elseif ($fixed_route) {
        $distance = floatval($fixed_route['distance_km']);
        $est_hours = floatval($fixed_route['est_hours']);
    } else {
        // Fallback: Check comprehensive Chhattisgarh Highway Distance network
        $cg_est = get_chhattisgarh_route_estimate($pickup_city, $drop_city);
        if ($cg_est) {
            $distance = floatval($cg_est['distance_km']);
            $est_hours = floatval($cg_est['est_hours']);
        } else {
            // Handle Airport keywords
            if (stripos($pickup_city, 'airport') !== false || stripos($drop_city, 'airport') !== false) {
                $other_city = (stripos($pickup_city, 'airport') !== false) ? $drop_city : $pickup_city;
                $cg_airport = get_chhattisgarh_route_estimate('raipur', $other_city);
                if ($cg_airport) {
                    $distance = floatval($cg_airport['distance_km']) + 15;
                    $est_hours = floatval($cg_airport['est_hours']) + 0.3;
                } else {
                    $distance = 30;
                    $est_hours = 0.75;
                }
            } else {
                return ['error' => "Currently, we do not operate cabs directly between $pickup_city and $drop_city. Please contact support at 8120868818."];
            }
        }
    }

    // Check if fixed one-way or local rental flat package rate applies
    $is_fixed_pkg = false;
    $fixed_target = 0;

    if ($trip_type === 'local') {
        $is_fixed_pkg = true;
        $is_sedan = (strpos($cat_name_upper, 'SEDAN') !== false || $category_id === 1);
        $is_mpv   = (strpos($cat_name_upper, 'MPV') !== false || $category_id === 3);
        $is_suv   = (strpos($cat_name_upper, 'SUV') !== false || $category_id === 2);
        $is_lux   = (strpos($cat_name_upper, 'LUX') !== false || $category_id === 4);

        if ($distance <= 45) { // 4 Hours / 40 KMs
            if ($is_sedan) $fixed_target = 1200;
            elseif ($is_mpv) $fixed_target = 1800;
            elseif ($is_suv) $fixed_target = 2200;
            elseif ($is_lux) $fixed_target = 3500;
            else $fixed_target = 1600;
        } elseif ($distance >= 100) { // 12 Hours / 120 KMs
            if ($is_sedan) $fixed_target = 3200;
            elseif ($is_mpv) $fixed_target = 4200;
            elseif ($is_suv) $fixed_target = 4800;
            elseif ($is_lux) $fixed_target = 7500;
            else $fixed_target = 3800;
        } else { // 8 Hours / 80 KMs
            if ($is_sedan) $fixed_target = 2200;
            elseif ($is_mpv) $fixed_target = 3000;
            elseif ($is_suv) $fixed_target = 3600;
            elseif ($is_lux) $fixed_target = 5500;
            else $fixed_target = 2600;
        }
    } elseif ($trip_type === 'one_way' && $fixed_route) {
        if (strpos($cat_name_upper, 'SEDAN') !== false || $category_id === 1) {
            $is_fixed_pkg = true;
            $fixed_target = floatval($fixed_route['dzire_fare']);
        } elseif (strpos($cat_name_upper, 'MPV') !== false || $category_id === 3) {
            $is_fixed_pkg = true;
            $fixed_target = floatval($fixed_route['ertiga_fare']);
        }
    }

    // 3. Timing and Calendar variables
    $day_of_week = date('N', strtotime($pickup_date)); // 6 = Sat, 7 = Sun
    $is_weekend = ($day_of_week == 6 || $day_of_week == 7);
    $hour = intval(date('H', strtotime($pickup_time)));
    $is_peak = (($hour >= 8 && $hour <= 10) || ($hour >= 17 && $hour <= 19));
    $is_night = ($hour >= 22 || $hour < 6);

    if ($is_fixed_pkg) {
        // Guaranteed Fixed Package Rate logic
        $base_fare = min(500.00, $fixed_target);
        $distance_fare = $fixed_target - $base_fare;
        $surcharges = 0.00;
        $driver_allowance = 0.00; // Included in package
        $toll_parking = 0.00;     // Included in package
        $gst_percent = 0.00;      // All-inclusive package
        $gst_amount = 0.00;
        $early_discount_percent = 0;

        // Apply Coupon Code discount if any
        $coupon_discount_percent = 0;
        $discount_amount = 0.00;
        if ($coupon_code && $pdo) {
            try {
                $stmt_cop = $pdo->prepare("SELECT * FROM `offers` WHERE coupon_code = ? AND status = 'active' AND validity >= CURDATE()");
                $stmt_cop->execute([$coupon_code]);
                $offer = $stmt_cop->fetch();
                if ($offer) {
                    $coupon_discount_percent = intval($offer['discount_percent']);
                    $discount_amount = $fixed_target * ($coupon_discount_percent / 100);
                }
            } catch (Exception $e) {}
        }

        $subtotal = $fixed_target - $discount_amount;
        $final_total = max(0.00, $subtotal);

    } else {
        // Standard dynamic calculation
        $calc_distance = ($trip_type === 'round_trip') ? ($distance * 2) : $distance;

        $rules = null;
        if ($pdo) {
            try {
                $rules = $pdo->query("SELECT * FROM `pricing_rules` LIMIT 1")->fetch();
            } catch (Exception $e) {}
        }
        if (!$rules) {
            $rules = [
                'driver_allowance' => 300.00,
                'toll_parking_estimate' => 150.00,
                'night_charge' => 250.00,
                'weekend_surcharge_percent' => 5.00,
                'peak_hour_surcharge_percent' => 10.00,
                'gst_percent' => 5.00,
                'early_booking_48h_discount' => 10.00,
                'early_booking_7d_discount' => 15.00,
                'early_booking_15d_discount' => 20.00,
                'early_booking_30d_discount' => 25.00
            ];
        }

        $base_fare = floatval($category['base_price']);
        $distance_fare = $calc_distance * floatval($category['per_km_price']);
        $surcharges = 0;

        if ($is_weekend) {
            $surcharges += $distance_fare * (floatval($rules['weekend_surcharge_percent']) / 100);
        }
        if ($is_peak) {
            $surcharges += $distance_fare * (floatval($rules['peak_hour_surcharge_percent']) / 100);
        }

        $driver_allowance = floatval($rules['driver_allowance']);
        if ($is_night) {
            $driver_allowance += floatval($rules['night_charge']);
        }
        if ($trip_type === 'round_trip') {
            $driver_allowance *= 2;
        }

        // Early booking discounts
        $now = new DateTime();
        $pickup_dt = new DateTime($pickup_date . ' ' . $pickup_time);
        $diff = $now->diff($pickup_dt);
        $hours_diff = ($diff->days * 24) + $diff->h;
        if ($pickup_dt < $now) {
            $hours_diff = 0;
        }

        $early_discount_percent = 0;
        if ($hours_diff >= 720) {
            $early_discount_percent = floatval($rules['early_booking_30d_discount']);
        } elseif ($hours_diff >= 360) {
            $early_discount_percent = floatval($rules['early_booking_15d_discount']);
        } elseif ($hours_diff >= 168) {
            $early_discount_percent = floatval($rules['early_booking_7d_discount']);
        } elseif ($hours_diff >= 48) {
            $early_discount_percent = floatval($rules['early_booking_48h_discount']);
        }

        $discount_amount = ($base_fare + $distance_fare + $surcharges) * ($early_discount_percent / 100);

        // Coupon Code discount
        $coupon_discount_percent = 0;
        if ($coupon_code && $pdo) {
            try {
                $stmt_cop = $pdo->prepare("SELECT * FROM `offers` WHERE coupon_code = ? AND status = 'active' AND validity >= CURDATE()");
                $stmt_cop->execute([$coupon_code]);
                $offer = $stmt_cop->fetch();
                if ($offer) {
                    $coupon_discount_percent = intval($offer['discount_percent']);
                    $coupon_discount_amount = ($base_fare + $distance_fare + $surcharges - $discount_amount) * ($coupon_discount_percent / 100);
                    $discount_amount += $coupon_discount_amount;
                }
            } catch (Exception $e) {}
        }

        // Toll and Parking
        $toll_parking = floatval($rules['toll_parking_estimate']);
        if ($trip_type === 'round_trip') {
            $toll_parking *= 1.5;
        }

        $subtotal = ($base_fare + $distance_fare + $surcharges + $driver_allowance + $toll_parking) - $discount_amount;
        if ($subtotal < 0) {
            $subtotal = 0;
        }

        $gst_percent = floatval($rules['gst_percent']);
        $gst_amount = $subtotal * ($gst_percent / 100);
        $final_total = $subtotal + $gst_amount;
    }

    $vehicle_title = $category['name'];
    if (strpos($cat_name_upper, 'SEDAN') !== false) {
        $vehicle_title = 'Swift Dzire (Sedan)';
    } elseif (strpos($cat_name_upper, 'MPV') !== false) {
        $vehicle_title = 'Maruti Suzuki Ertiga (MPV)';
    }

    return [
        'pickup_city' => $pickup_city,
        'drop_city' => $drop_city,
        'distance_km' => $distance,
        'est_hours' => $est_hours,
        'trip_type' => $trip_type,
        'category_name' => $category['name'],
        'vehicle_title' => $vehicle_title,
        'base_fare' => round($base_fare, 2),
        'distance_fare' => round($distance_fare + $surcharges, 2),
        'driver_allowance' => round($driver_allowance, 2),
        'toll_parking' => round($toll_parking, 2),
        'discount_percent' => $early_discount_percent + $coupon_discount_percent,
        'discount_amount' => round($discount_amount, 2),
        'gst_percent' => round($gst_percent, 2),
        'gst_amount' => round($gst_amount, 2),
        'final_total' => round($final_total, 2),
        'is_fixed_package' => $is_fixed_pkg,
        'inclusion_badge' => $fixed_route ? $fixed_route['inclusions_text'] : '',
        'toll_included' => $fixed_route ? $fixed_route['toll_included'] : false,
        'parking_included' => $fixed_route ? $fixed_route['parking_included'] : false,
        'is_weekend' => $is_weekend,
        'is_peak' => $is_peak,
        'is_night' => $is_night
    ];
}

/**
 * Clean helper function to retrieve the relative path of a vehicle product image
 */
function get_vehicle_image_path($cat_name, $image_field = '') {
    $cname = strtolower(trim((string)$cat_name));
    
    // Check if uploaded custom image file exists in uploads or assets
    if (!empty($image_field)) {
        $clean_img = basename($image_field);
        if (file_exists(__DIR__ . '/../uploads/vehicles/' . $clean_img)) {
            return 'uploads/vehicles/' . $clean_img;
        }
        if (file_exists(__DIR__ . '/../assets/images/vehicles/' . $clean_img)) {
            return 'assets/images/vehicles/' . $clean_img;
        }
    }
    
    // Categorical matching for standard taxi fleet types
    if (strpos($cname, 'suv') !== false || strpos($cname, 'innova') !== false || strpos($cname, 'crysta') !== false) {
        return 'assets/images/vehicles/suv.png';
    } elseif (strpos($cname, 'mpv') !== false || strpos($cname, 'ertiga') !== false) {
        return 'assets/images/vehicles/mpv.png';
    } elseif (strpos($cname, 'lux') !== false || strpos($cname, 'mercedes') !== false || strpos($cname, 'bmw') !== false || strpos($cname, 'audi') !== false) {
        return 'assets/images/vehicles/luxury.png';
    } elseif (strpos($cname, 'tempo') !== false || strpos($cname, 'traveller') !== false || strpos($cname, 'van') !== false) {
        return 'assets/images/vehicles/tempo.png';
    } elseif (strpos($cname, 'bus') !== false || strpos($cname, 'coach') !== false) {
        return 'assets/images/vehicles/bus.png';
    }
    
    // Default to clean sedan Dzire
    return 'assets/images/vehicles/sedan.png';
}

/**
 * Fallback Highway Distance Matrix for all Chhattisgarh Districts & Hubs
 */
function get_chhattisgarh_route_estimate($pickup, $drop) {
    $p = strtolower(trim((string)$pickup));
    $d = strtolower(trim((string)$drop));
    
    if (!$p || !$d || $p === $d) return null;

    // Master coordinates & distances from Raipur (hub 1) and Bilaspur (hub 2) in KM
    $cg_hub_distances = [
        'raipur' => ['raipur' => 0, 'bilaspur' => 120, 'durg' => 40, 'bhilai' => 35, 'korba' => 210, 'raigarh' => 240, 'rajnandgaon' => 75, 'jagdalpur' => 300, 'ambikapur' => 330, 'dhamtari' => 75, 'mahasamund' => 55, 'kanker' => 130, 'kawardha' => 120, 'janjgir' => 155, 'champa' => 165, 'bhatapara' => 85, 'baloda bazar' => 85, 'mungeli' => 110, 'gariaband' => 90, 'balod' => 95, 'bemetara' => 70, 'kondagaon' => 220, 'narayanpur' => 240, 'dantewada' => 380, 'sukma' => 400, 'bijapur' => 430, 'jashpur' => 380, 'balrampur' => 420, 'surajpur' => 320, 'baikunthpur' => 300, 'manendragarh' => 340, 'chirmiri' => 320, 'mohla' => 145, 'sarangarh' => 190, 'khairagarh' => 90, 'gaurela-pendra-marwahi' => 230, 'sakti' => 180, 'ratanpur' => 145, 'takhatpur' => 150, 'tilda newra' => 45, 'simga' => 55, 'saraipali' => 155, 'basna' => 135, 'dongargarh' => 105, 'arang' => 35, 'abhanpur' => 30, 'patan' => 35, 'kurud' => 50, 'rajim' => 45, 'nagpur' => 285, 'jharsuguda' => 320],
        'bilaspur' => ['bilaspur' => 0, 'raipur' => 120, 'korba' => 90, 'raigarh' => 160, 'janjgir' => 60, 'champa' => 70, 'mungeli' => 50, 'bhatapara' => 65, 'baloda bazar' => 85, 'kawardha' => 115, 'gaurela-pendra-marwahi' => 110, 'ambikapur' => 220, 'surajpur' => 230, 'baikunthpur' => 210, 'manendragarh' => 250, 'jashpur' => 270, 'balrampur' => 310, 'sakti' => 95, 'sarangarh' => 120, 'ratanpur' => 25, 'takhatpur' => 30, 'durg' => 155, 'bhilai' => 150, 'rajnandgaon' => 190, 'jagdalpur' => 415, 'dhamtari' => 190, 'mahasamund' => 160, 'kanker' => 245, 'kondagaon' => 335, 'dantewada' => 495, 'nagpur' => 380, 'jharsuguda' => 230]
    ];

    // Check direct Raipur connection
    if (isset($cg_hub_distances['raipur'][$d]) && $p === 'raipur') {
        $km = $cg_hub_distances['raipur'][$d];
        return ['distance_km' => $km, 'est_hours' => round($km / 48, 2)];
    }
    if (isset($cg_hub_distances['raipur'][$p]) && $d === 'raipur') {
        $km = $cg_hub_distances['raipur'][$p];
        return ['distance_km' => $km, 'est_hours' => round($km / 48, 2)];
    }

    // Check direct Bilaspur connection
    if (isset($cg_hub_distances['bilaspur'][$d]) && $p === 'bilaspur') {
        $km = $cg_hub_distances['bilaspur'][$d];
        return ['distance_km' => $km, 'est_hours' => round($km / 48, 2)];
    }
    if (isset($cg_hub_distances['bilaspur'][$p]) && $d === 'bilaspur') {
        $km = $cg_hub_distances['bilaspur'][$p];
        return ['distance_km' => $km, 'est_hours' => round($km / 48, 2)];
    }

    // Triangular estimate via Raipur Hub
    if (isset($cg_hub_distances['raipur'][$p]) && isset($cg_hub_distances['raipur'][$d])) {
        $d1 = $cg_hub_distances['raipur'][$p];
        $d2 = $cg_hub_distances['raipur'][$d];
        $est_km = max(30, round(abs($d1 - $d2) > 50 ? ($d1 + $d2) * 0.75 : ($d1 + $d2)));
        return ['distance_km' => $est_km, 'est_hours' => round($est_km / 45, 2)];
    }

    return null;
}

