<?php
// Print-friendly Invoicing System
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$booking_id = intval($_GET['id'] ?? 0);

if ($booking_id <= 0) {
    die("Invalid invoice query ID.");
}

// Fetch booking data
$stmt = $pdo->prepare("
    SELECT b.*, bp.name AS passenger_name, bp.phone AS passenger_phone, bp.email AS passenger_email, bp.pickup_address, bp.drop_address, bp.passenger_count,
           vc.name AS category_name, v.name AS vehicle_name, v.plate_number
    FROM `bookings` b
    INNER JOIN `booking_passengers` bp ON b.id = bp.booking_id
    INNER JOIN `vehicle_categories` vc ON b.category_id = vc.id
    LEFT JOIN `vehicles` v ON b.vehicle_id = v.id
    WHERE b.id = ?
");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Booking record not found.");
}

// Access Control check: Customer who owns it, or logged in admin
if (!is_admin_logged_in()) {
    require_customer();
    if (intval($booking['user_id']) !== intval($_SESSION['user_id'])) {
        die("Unauthorized access. You do not have permissions to view this invoice.");
    }
}

// Fetch payment transactions if any
$stmt_pay = $pdo->prepare("SELECT * FROM `payments` WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt_pay->execute([$booking_id]);
$payment = $stmt_pay->fetch();

$company_name = get_setting('company_name', 'Avinash Travels');
$company_email = get_setting('company_email', 'info@avinashtravels.com');
$company_phone = get_setting('company_phone', '8120868818');
$company_address = get_setting('company_address', 'Cab Service, Alakh DHaM Face1, Maharishi chowk, Vikas Nagar, Mangla, Bilaspur, Chhattisgarh 495001');
$company_gst = get_setting('company_gst', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo $booking['booking_id']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #1E293B;
            background-color: #F8FAFC;
            margin: 0;
            padding: 40px 20px;
        }
        .invoice-card {
            background-color: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #F1F5F9;
            padding-bottom: 25px;
            margin-bottom: 25px;
        }
        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .logo img {
            max-height: 50px;
            width: auto;
            object-fit: contain;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h1 {
            margin: 0 0 5px 0;
            font-size: 24px;
            color: #0F172A;
        }
        .grid-2 {
            display: grid;
            grid-cols: 1fr 1fr;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 30px;
        }
        .grid-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748B;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 4px;
        }
        .meta-p {
            font-size: 13px;
            line-height: 1.5;
            margin: 0 0 5px 0;
        }
        .table-invoice {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table-invoice th {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748B;
            background-color: #F8FAFC;
            padding: 10px 15px;
            border-bottom: 1px solid #E2E8F0;
        }
        .table-invoice td {
            font-size: 13px;
            padding: 15px;
            border-bottom: 1px solid #F1F5F9;
        }
        .text-right {
            text-align: right !important;
        }
        .totals-box {
            width: 300px;
            margin-left: auto;
            margin-bottom: 30px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 8px 0;
        }
        .totals-row.grand-total {
            font-size: 16px;
            font-weight: 700;
            color: #0066CC;
            border-top: 2px solid #E2E8F0;
            padding-top: 12px;
        }
        .badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .badge-paid {
            background-color: #DCFCE7;
            color: #15803D;
        }
        .badge-pending {
            background-color: #E0F2FE;
            color: #0369A1;
        }
        .actions-bar {
            max-width: 800px;
            margin: 20px auto 0 auto;
            display: flex;
            justify-content: space-between;
        }
        .btn-print {
            background-color: #0066CC;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            border: 0;
            cursor: pointer;
        }
        .btn-back {
            color: #64748B;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 0;
        }
        @media print {
            body {
                background-color: #FFFFFF;
                padding: 0;
            }
            .invoice-card {
                border: 0;
                box-shadow: none;
                padding: 0;
            }
            .actions-bar {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="invoice-card">
        <!-- Logo and Invoice ID -->
        <div class="header">
            <div>
                <div class="logo">
                    <img src="https://avinashtaxi.com/uploads/logo/logo.png" alt="<?php echo htmlspecialchars($company_name); ?>">
                </div>
                <p class="meta-p" style="color: #64748B; font-size: 11px; margin-top: 5px;">
                    <?php echo htmlspecialchars($company_address); ?><br>
                    Mobile: <?php echo htmlspecialchars($company_phone); ?> | Email: <?php echo htmlspecialchars($company_email); ?><br>
                    <?php if ($company_gst): ?>GSTIN: <?php echo htmlspecialchars($company_gst); ?><?php endif; ?>
                </p>
            </div>
            <div class="invoice-title">
                <h1>INVOICE</h1>
                <p class="meta-p" style="font-mono">ID: <strong><?php echo $booking['booking_id']; ?></strong></p>
                <p class="meta-p">Date: <?php echo date('d-m-Y', strtotime($booking['created_at'])); ?></p>
            </div>
        </div>

        <!-- Meta Billing Info -->
        <div class="grid-2">
            <div>
                <div class="grid-title">Billed To (Passenger)</div>
                <p class="meta-p"><strong><?php echo htmlspecialchars($booking['passenger_name']); ?></strong></p>
                <p class="meta-p">Phone: <?php echo htmlspecialchars($booking['passenger_phone']); ?></p>
                <p class="meta-p">Email: <?php echo htmlspecialchars($booking['passenger_email']); ?></p>
                <p class="meta-p" style="margin-top: 10px; font-size: 12px; color: #64748B;">
                    <strong>Pickup Address:</strong><br>
                    <?php echo htmlspecialchars($booking['pickup_address']); ?>
                </p>
            </div>
            <div>
                <div class="grid-title">Travel Details</div>
                <p class="meta-p"><strong>Route:</strong> <?php echo htmlspecialchars($booking['pickup_city']); ?> → <?php echo htmlspecialchars($booking['drop_city']); ?></p>
                <p class="meta-p"><strong>Trip Type:</strong> <?php echo ($booking['trip_type'] === 'one_way') ? 'One Way outstation' : 'Round Trip outstation'; ?></p>
                <p class="meta-p"><strong>Departure:</strong> <?php echo date('d M Y', strtotime($booking['pickup_date'])); ?> at <?php echo date('h:i A', strtotime($booking['pickup_time'])); ?></p>
                <?php if ($booking['return_date']): ?>
                    <p class="meta-p"><strong>Return:</strong> <?php echo date('d M Y', strtotime($booking['return_date'])); ?> at <?php echo date('h:i A', strtotime($booking['return_time'])); ?></p>
                <?php endif; ?>
                <p class="meta-p" style="margin-top: 10px; font-size: 12px; color: #64748B;">
                    <strong>Dropoff Address:</strong><br>
                    <?php echo htmlspecialchars($booking['drop_address']); ?>
                </p>
            </div>
        </div>

        <!-- Itemized Table -->
        <table class="table-invoice">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>Vehicle Class</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Intercity Outstation Taxi Services</strong><br>
                        <span style="font-size: 11px; color: #64748B;">Includes vehicle, clean cabin amenities, and door-to-door passenger dropoff.</span>
                    </td>
                    <td><?php echo htmlspecialchars($booking['category_name']); ?></td>
                    <td class="text-right">₹<?php echo number_format($booking['base_fare'] + $booking['distance_fare'], 2); ?></td>
                </tr>
                <tr>
                    <td>
                        <strong>Chauffeur / Driver Trip Allowance</strong><br>
                        <span style="font-size: 11px; color: #64748B;">Covers driver boarding allowance and nighttime support charges.</span>
                    </td>
                    <td>-</td>
                    <td class="text-right">₹<?php echo number_format($booking['driver_allowance'], 2); ?></td>
                </tr>
                <tr>
                    <td>
                        <strong>Tolls, Permits & Parking Estimate</strong><br>
                        <span style="font-size: 11px; color: #64748B;">Allocated fund for highway tolls and parking permissions.</span>
                    </td>
                    <td>-</td>
                    <td class="text-right">₹<?php echo number_format($booking['toll_parking'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Totals & Transaction logs -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <div class="grid-title" style="width: 250px;">Receipt & Status Log</div>
                <p class="meta-p" style="font-size: 12px;">Payment Method: <strong><?php echo strtoupper($booking['payment_method']); ?></strong></p>
                <p class="meta-p" style="font-size: 12px;">
                    Payment Status: 
                    <span class="badge <?php echo ($booking['payment_status'] === 'paid') ? 'badge-paid' : 'badge-pending'; ?>">
                        <?php echo $booking['payment_status']; ?>
                    </span>
                </p>
                <?php if ($payment): ?>
                    <p class="meta-p" style="font-size: 11px; color: #64748B; font-family: monospace; margin-top: 5px;">Txn ID: <?php echo $payment['transaction_id']; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="totals-box">
                <?php if ($booking['discount_amount'] > 0): ?>
                    <div class="totals-row" style="color: #DC2626;">
                        <span>Advance Discount:</span>
                        <span>-₹<?php echo number_format($booking['discount_amount'], 2); ?></span>
                    </div>
                <?php endif; ?>
                <div class="totals-row">
                    <span>GST (Taxes):</span>
                    <span>₹<?php echo number_format($booking['gst_amount'], 2); ?></span>
                </div>
                <div class="totals-row grand-total">
                    <span>Total Amount:</span>
                    <span>₹<?php echo number_format($booking['final_total'], 2); ?></span>
                </div>
            </div>
        </div>

        <div style="margin-top: 40px; border-top: 1px solid #E2E8F0; padding-top: 20px; font-size: 11px; text-align: center; color: #64748B;">
            Thank you for traveling with <?php echo htmlspecialchars($company_name); ?>. This is a computer-generated digital receipt.
        </div>
    </div>

    <!-- Actions -->
    <div class="actions-bar">
        <a href="dashboard.php" class="btn-back"><i class="fa-solid fa-chevron-left"></i> Return to Dashboard</a>
        <a href="../api/generate_booking_card.php?id=<?php echo urlencode($booking['booking_id']); ?>&action=view" target="_blank" style="padding: 10px 18px; background: #0284C7; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-id-card"></i> View Official AI Pass
        </a>
        <button onclick="window.print()" class="btn-print">Print / Save PDF</button>
    </div>

</body>
</html>
