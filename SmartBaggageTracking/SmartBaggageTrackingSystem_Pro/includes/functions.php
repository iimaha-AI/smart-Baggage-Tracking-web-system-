<?php
function generateBaggageCode($flightNumber) {
    $prefix = "BG-" . $flightNumber . "-";
    $random = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    return $prefix . $random;
}

function getBaggageStatusText($status) {
    $statuses = [
        'checked_in' => 'Checked In',
        'security_check' => 'Security Check',
        'sorting_facility' => 'In Sorting Facility',
        'loaded_to_aircraft' => 'Loaded to Aircraft',
        'in_transit' => 'In Transit',
        'unloaded_from_aircraft' => 'Unloaded from Aircraft',
        'at_carousel' => 'At Carousel',
        'claimed' => 'Claimed',
        'delayed' => 'Delayed',
        'lost' => 'Lost'
    ];
    
    return $statuses[$status] ?? $status;
}

function getFlightStatusText($status) {
    $statuses = [
        'scheduled' => 'Scheduled',
        'boarding' => 'Boarding',
        'departed' => 'Departed',
        'in_air' => 'In Air',
        'arrived' => 'Arrived',
        'delayed' => 'Delayed',
        'cancelled' => 'Cancelled'
    ];
    return $statuses[$status] ?? $status;
}

function getStatusBadge($status) {
    $badges = [
        'checked_in' => 'bg-primary',
        'security_check' => 'bg-warning',
        'sorting_facility' => 'bg-info',
        'loaded_to_aircraft' => 'bg-success',
        'in_transit' => 'bg-secondary',
        'unloaded_from_aircraft' => 'bg-primary',
        'at_carousel' => 'bg-success',
        'claimed' => 'bg-dark',
        'delayed' => 'bg-danger',
        'lost' => 'bg-danger'
    ];
    
    return $badges[$status] ?? 'bg-secondary';
}

function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

function sendNotification($userId, $title, $message, $type = 'baggage_status') {
    global $db;
    
    $db->query(
        "INSERT INTO notifications (user_id, type, title, message, channels) 
         VALUES (?, ?, ?, ?, '[\"in_app\"]')",
        [$userId, $type, $title, $message]
    );
    
    return true;
}

function getTrackingHistory($baggageId) {
    global $db;
    
    return $db->fetchAll(
        "SELECT tl.*, l.location_name, l.location_type, u.full_name as scanned_by_name
         FROM tracking_logs tl
         LEFT JOIN locations l ON tl.location_id = l.id
         LEFT JOIN users u ON tl.scanned_by = u.id
         WHERE tl.baggage_id = ?
         ORDER BY tl.actual_scan_time ASC",
        [$baggageId]
    );
}

function calculateExcessFee($weight, $baggageType = 'CHECKED') {
    $allowedWeights = [
        'CABIN' => 7,
        'CHECKED' => 23,
        'OVERWEIGHT' => 32
    ];
    
    $allowedWeight = $allowedWeights[$baggageType] ?? 23;
    
    if ($weight <= $allowedWeight) {
        return 0;
    }
    
    $excess = $weight - $allowedWeight;
    $feePerKg = 50; // USD per kg
    
    return $excess * $feePerKg;
}

function getUserRoleText($role) {
    $roles = [
        'passenger' => 'Passengers',
        'counter_staff' => 'Counter Staff',
        'handling_staff' => 'Handling Staff',
        'supervisor' => 'Supervisors',
        'airline_manager' => 'Airline Managers',
        'system_admin' => 'System Administrators'
    ];
    return $roles[$role] ?? $role;
}

function getStatusText($status) {
    return getBaggageStatusText($status);
}

function getCheckInLocationId($pdo) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM locations
             WHERE location_type = 'checkin_counter' AND status = 'active'
             ORDER BY id
             LIMIT 1"
        );
        $stmt->execute();
        $location = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($location) {
            return $location['id'];
        }

        $stmt = $pdo->prepare("SELECT id FROM locations WHERE location_code = ? LIMIT 1");
        $stmt->execute(['CHK-001']);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return $existing['id'];
        }

        $stmt = $pdo->prepare("SELECT id FROM terminals ORDER BY id LIMIT 1");
        $stmt->execute();
        $terminal = $stmt->fetch(PDO::FETCH_ASSOC);
        $terminalId = $terminal ? $terminal['id'] : null;

        $stmt = $pdo->prepare(
            "INSERT INTO locations
             (location_code, location_name, location_type, terminal_id, status)
             VALUES (?, ?, 'checkin_counter', ?, 'active')"
        );
        $stmt->execute(['CHK-001', 'Check-in Counter 1', $terminalId]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Error resolving check-in location: ' . $e->getMessage());
        return null;
    }
}
?>