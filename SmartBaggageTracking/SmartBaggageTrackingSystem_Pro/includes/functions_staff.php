<?php
require_once 'config_staff.php';

function getBaggageStats($pdo, $period = 'today') {
    try {
        $dateCondition = getDateCondition($period);
        $stats = [];
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM baggage WHERE $dateCondition");
        $stats['total'] = $stmt->fetch()['total'];
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM baggage WHERE $dateCondition GROUP BY status");
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->query("SELECT COUNT(*) as delivered FROM baggage WHERE status = 'claimed' AND $dateCondition");
        $stats['delivered'] = $stmt->fetch()['delivered'];
        $stmt = $pdo->query("SELECT COUNT(*) as delayed FROM baggage WHERE status = 'delayed'");
        $stats['delayed'] = $stmt->fetch()['delayed'];
        $stmt = $pdo->query("SELECT COUNT(*) as lost FROM baggage WHERE status = 'lost'");
        $stats['lost'] = $stmt->fetch()['lost'];
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting baggage stats: " . $e->getMessage());
        return ['total'=>0,'by_status'=>[],'delivered'=>0,'delayed'=>0,'lost'=>0];
    }
}

function getDailyActivity($pdo) {
    try {
        $stmt = $pdo->query("SELECT HOUR(actual_scan_time) as hour, COUNT(*) as count FROM tracking_logs WHERE DATE(actual_scan_time) = CURDATE() GROUP BY HOUR(actual_scan_time) ORDER BY hour");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        for ($i=0;$i<24;$i++) {
            $found=false;
            foreach ($data as $item) { if ($item['hour']==$i) { $result[]=$item; $found=true; break; } }
            if (!$found) $result[]=['hour'=>$i,'count'=>0];
        }
        return $result;
    } catch (PDOException $e) { error_log("Error getting daily activity: ".$e->getMessage()); return []; }
}

function getStatusText($status) {
    $texts=['checked_in'=>'تم التسجيل','security_check'=>'فحص أمني','sorting_facility'=>'مركز الفرز','loaded_to_aircraft'=>'محمل على الطائرة','in_transit'=>'في transit','unloaded_from_aircraft'=>'تم تفريغه من الطائرة','at_carousel'=>'على السير','claimed'=>'تم الاستلام','delayed'=>'متأخرة','lost'=>'مفقودة'];
    return $texts[$status] ?? $status;
}

function getStatusBadgeClass($status) {
    $classes=['checked_in'=>'bg-checked-in','security_check'=>'bg-security','sorting_facility'=>'bg-sorting','loaded_to_aircraft'=>'bg-loaded','in_transit'=>'bg-transit','unloaded_from_aircraft'=>'bg-unloaded','at_carousel'=>'bg-carousel','claimed'=>'bg-claimed','delayed'=>'bg-delayed','lost'=>'bg-lost'];
    return $classes[$status] ?? 'bg-secondary';
}

function generateBaggageCode($pdo) {
    do {
        $code='BG'.date('Ymd').str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);
        $stmt=$pdo->prepare("SELECT id FROM baggage WHERE baggage_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

function calculateBaggageFee($pdo,$baggage_type_id,$weight) {
    try {
        $stmt=$pdo->prepare("SELECT fee_per_kg FROM baggage_types WHERE id = ?");
        $stmt->execute([$baggage_type_id]);
        $type=$stmt->fetch();
        return $type ? $type['fee_per_kg']*$weight : 0;
    } catch(PDOException $e){error_log("Error calculating baggage fee: ".$e->getMessage());return 0;}
}

function getOrCreateCheckinLocation($pdo) {
    try {
        $stmt=$pdo->prepare("SELECT id FROM locations WHERE location_type = 'checkin_counter' AND status = 'active' LIMIT 1");
        $stmt->execute(); $location=$stmt->fetch(); if($location)return $location['id'];
        $stmt=$pdo->prepare("SELECT id FROM terminals LIMIT 1"); $stmt->execute(); $terminal=$stmt->fetch(); $terminal_id=$terminal?$terminal['id']:null;
        $stmt=$pdo->prepare("SELECT id FROM locations WHERE location_code = 'CHK-001'"); $stmt->execute(); $existing=$stmt->fetch(); if($existing)return $existing['id'];
        $stmt=$pdo->prepare("INSERT INTO locations (location_code, location_name, location_type, terminal_id, status) VALUES (?, ?, 'checkin_counter', ?, 'active')");
        $stmt->execute(['CHK-001','Check-in Counter 1',$terminal_id]); return $pdo->lastInsertId();
    } catch(PDOException $e){
        error_log("Error getting/creating checkin location: ".$e->getMessage());
        $stmt=$pdo->prepare("SELECT id FROM locations WHERE status = 'active' LIMIT 1"); $stmt->execute(); $location=$stmt->fetch(); return $location?$location['id']:null;
    }
}

function addTrackingLog($pdo,$baggage_id,$location_id,$status,$remarks='',$scan_type='manual',$device_id=null){
    try{
        $stmt=$pdo->prepare("INSERT INTO tracking_logs (baggage_id, location_id, status, remarks, scanned_by, device_id, scan_type, actual_scan_time) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$baggage_id,$location_id,$status,$remarks,$_SESSION['user_id'],$device_id,$scan_type]); return $pdo->lastInsertId();
    }catch(PDOException $e){error_log("Error adding tracking log: ".$e->getMessage());return false;}
}

function updateBaggageStatus($pdo,$baggage_id,$status){
    try{$stmt=$pdo->prepare("UPDATE baggage SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");$stmt->execute([$status,$baggage_id]);return $stmt->rowCount()>0;}catch(PDOException $e){error_log("Error updating baggage status: ".$e->getMessage());return false;}
}

function getTrackingHistory($pdo,$baggage_id,$limit=50){
    try{
        $stmt=$pdo->prepare("SELECT tl.*, u.full_name as staff_name, l.location_name, l.location_type, d.device_name, d.device_type FROM tracking_logs tl LEFT JOIN users u ON tl.scanned_by = u.id LEFT JOIN locations l ON tl.location_id = l.id LEFT JOIN devices d ON tl.device_id = d.id WHERE tl.baggage_id = ? ORDER BY tl.actual_scan_time DESC LIMIT ?");
        $stmt->execute([$baggage_id,$limit]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }catch(PDOException $e){error_log("Error getting tracking history: ".$e->getMessage());return [];}
}

function getBaggageDetails($pdo,$baggage_id){
    try{
        $stmt=$pdo->prepare("SELECT b.*, u.full_name, u.passport_number, f.flight_number, f.departure_time, f.arrival_time, a1.airport_name as origin_airport, a2.airport_name as destination_airport, bt.type_name as baggage_type_name FROM baggage b JOIN users u ON b.passenger_id = u.id JOIN flights f ON b.flight_id = f.id JOIN airports a1 ON f.origin_airport_id = a1.id JOIN airports a2 ON f.destination_airport_id = a2.id JOIN baggage_types bt ON b.baggage_type_id = bt.id WHERE b.id = ?");
        $stmt->execute([$baggage_id]); return $stmt->fetch(PDO::FETCH_ASSOC);
    }catch(PDOException $e){error_log("Error getting baggage details: ".$e->getMessage());return false;}
}

function searchBaggage($pdo,$query,$type='all',$limit=20){
    try{
        $sql="SELECT b.*, u.full_name, u.passport_number, f.flight_number, f.departure_time, f.arrival_time, a1.airport_name as origin_airport, a2.airport_name as destination_airport, bt.type_name as baggage_type_name FROM baggage b JOIN users u ON b.passenger_id = u.id JOIN flights f ON b.flight_id = f.id JOIN airports a1 ON f.origin_airport_id = a1.id JOIN airports a2 ON f.destination_airport_id = a2.id JOIN baggage_types bt ON b.baggage_type_id = bt.id WHERE ";
        $searchParam='%'.$query.'%';
        switch($type){case 'baggage_code':$sql.="b.baggage_code LIKE ?";break;case 'passenger_name':$sql.="u.full_name LIKE ?";break;case 'passport':$sql.="u.passport_number LIKE ?";break;case 'flight_number':$sql.="f.flight_number LIKE ?";break;default:$sql.="(b.baggage_code LIKE ? OR u.full_name LIKE ? OR u.passport_number LIKE ? OR f.flight_number LIKE ?)";}
        $sql.=" ORDER BY b.created_at DESC LIMIT ?"; $stmt=$pdo->prepare($sql);
        $type==='all'?$stmt->execute([$searchParam,$searchParam,$searchParam,$searchParam,$limit]):$stmt->execute([$searchParam,$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }catch(PDOException $e){error_log("Error searching baggage: ".$e->getMessage());return [];}
}

function getLocations($pdo,$type='',$status='active'){
    try{$where="WHERE status = ?";$params=[$status];if(!empty($type)){$where.=" AND location_type = ?";$params[]=$type;}$stmt=$pdo->prepare("SELECT * FROM locations $where ORDER BY location_name");$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);}catch(PDOException $e){error_log("Error getting locations: ".$e->getMessage());return [];}
}
function getBaggageTypes($pdo,$status='active'){try{$stmt=$pdo->prepare("SELECT * FROM baggage_types WHERE status = ? ORDER BY type_name");$stmt->execute([$status]);return $stmt->fetchAll(PDO::FETCH_ASSOC);}catch(PDOException $e){error_log("Error getting baggage types: ".$e->getMessage());return [];}}
function getFlights($pdo,$future_only=true){try{$where=$future_only?"WHERE f.departure_time > NOW()":"";$stmt=$pdo->prepare("SELECT f.id, f.flight_number, f.departure_time, a1.airport_name as origin, a2.airport_name as destination FROM flights f JOIN airports a1 ON f.origin_airport_id = a1.id JOIN airports a2 ON f.destination_airport_id = a2.id $where ORDER BY f.departure_time");$stmt->execute();return $stmt->fetchAll(PDO::FETCH_ASSOC);}catch(PDOException $e){error_log("Error getting flights: ".$e->getMessage());return [];}}
function getPassengers($pdo,$status='active'){try{$stmt=$pdo->prepare("SELECT id, full_name, passport_number FROM users WHERE role = 'passenger' AND status = ? ORDER BY full_name");$stmt->execute([$status]);return $stmt->fetchAll(PDO::FETCH_ASSOC);}catch(PDOException $e){error_log("Error getting passengers: ".$e->getMessage());return [];}}
function sendNotification($pdo,$user_id,$type,$title,$message,$priority='medium',$channels=['in_app']){try{$stmt=$pdo->prepare("INSERT INTO notifications (user_id, type, title, message, priority, channels, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");$stmt->execute([$user_id,$type,$title,$message,$priority,json_encode($channels)]);return $pdo->lastInsertId();}catch(PDOException $e){error_log("Error sending notification: ".$e->getMessage());return false;}}
function getNotifications($pdo,$user_id,$limit=20,$status=''){try{$where="WHERE user_id = ?";$params=[$user_id];if(!empty($status)){$where.=" AND status = ?";$params[]=$status;}$params[]=$limit;$stmt=$pdo->prepare("SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT ?");$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);}catch(PDOException $e){error_log("Error getting notifications: ".$e->getMessage());return [];}}
function markNotificationAsRead($pdo,$notification_id,$user_id){try{$stmt=$pdo->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE id = ? AND user_id = ?");$stmt->execute([$notification_id,$user_id]);return $stmt->rowCount()>0;}catch(PDOException $e){error_log("Error marking notification as read: ".$e->getMessage());return false;}}
function getDateCondition($period){switch($period){case 'today':return "DATE(created_at) = CURDATE()";case 'week':return "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";case 'month':return "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";case 'year':return "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";default:return "DATE(created_at) = CURDATE()";}}
function formatDate($date,$format='Y-m-d H:i'){return date($format,strtotime($date));}
function timeAgo($datetime){$time=time()-strtotime($datetime);if($time<60)return 'الآن';if($time<3600)return floor($time/60).' دقيقة';if($time<86400)return floor($time/3600).' ساعة';if($time<2592000)return floor($time/86400).' يوم';if($time<31536000)return floor($time/2592000).' شهر';return floor($time/31536000).' سنة';}
function hasPermission($user_role,$required_roles){if(is_string($required_roles))$required_roles=[$required_roles];return in_array($user_role,$required_roles);}
function logActivity($pdo,$action,$description,$resource_type='',$resource_id=null){try{$stmt=$pdo->prepare("INSERT INTO security_logs (user_id, action_type, action_description, ip_address, user_agent, resource_type, resource_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");$stmt->execute([$_SESSION['user_id']??null,$action,$description,$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'',$resource_type,$resource_id]);return true;}catch(PDOException $e){error_log("Error logging activity: ".$e->getMessage());return false;}}
function sanitizeInput($data){return is_array($data)?array_map('sanitizeInput',$data):htmlspecialchars(trim($data),ENT_QUOTES,'UTF-8');}
function isValidEmail($email){return filter_var($email,FILTER_VALIDATE_EMAIL)!==false;}
function isValidPhone($phone){return preg_match('/^[\+]?[0-9\s\-\(\)]{10,}$/',$phone);}
function generateRandomCode($length=8){$characters='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';$code='';for($i=0;$i<$length;$i++)$code.=$characters[rand(0,strlen($characters)-1)];return $code;}
function compressData($data){return base64_encode(gzcompress(json_encode($data)));}
function decompressData($data){return json_decode(gzuncompress(base64_decode($data)),true);}
function getCachedData($key,$callback,$duration=null){if($duration===null)$duration=getStaffConfig('STAFF_CACHE_DURATION',300);$cacheFile=__DIR__.'/../cache/'.md5($key).'.cache';if(file_exists($cacheFile)&&(time()-filemtime($cacheFile))<$duration)return unserialize(file_get_contents($cacheFile));$data=$callback();if(!is_dir(dirname($cacheFile)))mkdir(dirname($cacheFile),0755,true);file_put_contents($cacheFile,serialize($data));return $data;}
function clearCache($pattern='*'){$cacheDir=__DIR__.'/../cache/';$files=glob($cacheDir.$pattern.'.cache');foreach($files as $file)unlink($file);}
?>