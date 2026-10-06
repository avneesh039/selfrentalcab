-- Database Schema for Avinash Travels
-- Premium Travel & Outstation Cab Booking Platform

CREATE DATABASE IF NOT EXISTS `avinashtravels` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `avinashtravels`;

-- 1. settings table
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `meta_key` VARCHAR(100) NOT NULL UNIQUE,
  `meta_value` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. admins table
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `role` VARCHAR(50) DEFAULT 'admin',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. users (customers) table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('active', 'suspended') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. vehicle_categories table
CREATE TABLE IF NOT EXISTS `vehicle_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `seating_capacity` INT NOT NULL,
  `luggage_capacity` INT NOT NULL,
  `ac` TINYINT(1) DEFAULT 1,
  `amenities` TEXT NULL, -- comma separated list
  `image` VARCHAR(255) NULL,
  `base_price` DECIMAL(10,2) NOT NULL,
  `per_km_price` DECIMAL(10,2) NOT NULL,
  `per_hour_price` DECIMAL(10,2) NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. vehicles table
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `plate_number` VARCHAR(30) NOT NULL UNIQUE,
  `color` VARCHAR(30) DEFAULT 'White',
  `status` ENUM('available', 'on_trip', 'maintenance') DEFAULT 'available',
  FOREIGN KEY (`category_id`) REFERENCES `vehicle_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. routes table
CREATE TABLE IF NOT EXISTS `routes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pickup_city` VARCHAR(100) NOT NULL,
  `drop_city` VARCHAR(100) NOT NULL,
  `distance_km` DECIMAL(10,2) NOT NULL,
  `est_hours` DECIMAL(5,2) NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  UNIQUE KEY `route_pickup_drop` (`pickup_city`, `drop_city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. pricing_rules table (one central config record, but editable)
CREATE TABLE IF NOT EXISTS `pricing_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `driver_allowance` DECIMAL(10,2) DEFAULT 300.00,
  `toll_parking_estimate` DECIMAL(10,2) DEFAULT 150.00,
  `night_charge` DECIMAL(10,2) DEFAULT 250.00,
  `weekend_surcharge_percent` DECIMAL(5,2) DEFAULT 5.00,
  `peak_hour_surcharge_percent` DECIMAL(5,2) DEFAULT 10.00,
  `gst_percent` DECIMAL(5,2) DEFAULT 5.00,
  `early_booking_48h_discount` DECIMAL(5,2) DEFAULT 10.00,
  `early_booking_7d_discount` DECIMAL(5,2) DEFAULT 15.00,
  `early_booking_15d_discount` DECIMAL(5,2) DEFAULT 20.00,
  `early_booking_30d_discount` DECIMAL(5,2) DEFAULT 25.00,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. bookings table
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NULL,
  `trip_type` ENUM('one_way', 'round_trip') NOT NULL,
  `pickup_city` VARCHAR(100) NOT NULL,
  `drop_city` VARCHAR(100) NOT NULL,
  `pickup_date` DATE NOT NULL,
  `pickup_time` TIME NOT NULL,
  `return_date` DATE NULL,
  `return_time` TIME NULL,
  `category_id` INT NOT NULL,
  `vehicle_id` INT NULL,
  `base_fare` DECIMAL(10,2) NOT NULL,
  `distance_fare` DECIMAL(10,2) NOT NULL,
  `driver_allowance` DECIMAL(10,2) NOT NULL,
  `toll_parking` DECIMAL(10,2) NOT NULL,
  `discount_amount` DECIMAL(10,2) NOT NULL,
  `gst_amount` DECIMAL(10,2) NOT NULL,
  `final_total` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'cash',
  `payment_status` ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
  `status` ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`category_id`) REFERENCES `vehicle_categories` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. booking_passengers table
CREATE TABLE IF NOT EXISTS `booking_passengers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `pickup_address` TEXT NOT NULL,
  `drop_address` TEXT NOT NULL,
  `passenger_count` INT NOT NULL DEFAULT 1,
  `special_instructions` TEXT NULL,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. payments table
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `status` VARCHAR(50) DEFAULT 'success',
  `raw_response` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. drivers table
CREATE TABLE IF NOT EXISTS `drivers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `vehicle_type` VARCHAR(50) NOT NULL,
  `vehicle_no` VARCHAR(30) NOT NULL,
  `rating` DECIMAL(2,1) DEFAULT 5.0,
  `status` ENUM('pending', 'approved', 'suspended') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. driver_documents table
CREATE TABLE IF NOT EXISTS `driver_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `driver_id` INT NOT NULL,
  `doc_type` VARCHAR(50) NOT NULL, -- e.g. License, RC, Insurance, Photo
  `doc_path` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. partners table
CREATE TABLE IF NOT EXISTS `partners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `city` VARCHAR(100) NOT NULL,
  `vehicle_type` VARCHAR(50) NOT NULL,
  `vehicle_no` VARCHAR(30) NOT NULL,
  `license_no` VARCHAR(50) NOT NULL,
  `rc_path` VARCHAR(255) NULL,
  `license_path` VARCHAR(255) NULL,
  `insurance_path` VARCHAR(255) NULL,
  `photo_path` VARCHAR(255) NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. trips table (available for bidding by drivers)
CREATE TABLE IF NOT EXISTS `trips` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `pickup_city` VARCHAR(100) NOT NULL,
  `drop_city` VARCHAR(100) NOT NULL,
  `travel_date` DATE NOT NULL,
  `travel_time` TIME NOT NULL,
  `est_distance` DECIMAL(10,2) NOT NULL,
  `expected_fare` DECIMAL(10,2) NOT NULL,
  `status` ENUM('open', 'bidding_closed', 'assigned', 'cancelled') DEFAULT 'open',
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. trip_bids table
CREATE TABLE IF NOT EXISTS `trip_bids` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `trip_id` INT NOT NULL,
  `driver_id` INT NOT NULL,
  `bid_amount` DECIMAL(10,2) NOT NULL,
  `comments` TEXT NULL,
  `status` ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `driver_trip` (`trip_id`, `driver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. offers table
CREATE TABLE IF NOT EXISTS `offers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(100) NOT NULL,
  `coupon_code` VARCHAR(20) NOT NULL UNIQUE,
  `pickup_city` VARCHAR(100) NOT NULL,
  `drop_city` VARCHAR(100) NOT NULL,
  `special_price` DECIMAL(10,2) NOT NULL,
  `original_price` DECIMAL(10,2) NOT NULL,
  `discount_percent` INT NOT NULL,
  `validity` DATE NOT NULL,
  `image` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. services table
CREATE TABLE IF NOT EXISTS `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NOT NULL,
  `image` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. testimonials table
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `rating` INT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `review` TEXT NOT NULL,
  `route` VARCHAR(150) NOT NULL,
  `photo` VARCHAR(255) DEFAULT 'default_user.png',
  `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. gallery table
CREATE TABLE IF NOT EXISTS `gallery` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `image` VARCHAR(255) NOT NULL,
  `title` VARCHAR(100) NULL,
  `is_featured` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. faqs table
CREATE TABLE IF NOT EXISTS `faqs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question` TEXT NOT NULL,
  `answer` TEXT NOT NULL,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. enquiries table
CREATE TABLE IF NOT EXISTS `enquiries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(50) NOT NULL, -- e.g. booking_confirmed, booking_cancelled, driver_assigned
  `recipient` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `gateway` ENUM('email', 'sms', 'whatsapp') NOT NULL,
  `status` ENUM('sent', 'failed', 'pending') DEFAULT 'sent',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
