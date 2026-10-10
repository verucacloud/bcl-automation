<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireControllerKey();
$data = inputJson();
$controller = trim((string)($data['controller_name'] ?? 'local-controller'));
$devices = $data['devices'] ?? [];
if (!is_array($devices)) jsonResponse(['success'=>false,'message'=>'devices must be an array'], 422);

$sql = "INSERT INTO managed_devices
(device_code, adb_serial, manufacturer, model, android_version, battery_level, connection_type, controller_name, status, last_seen_at, metadata_json)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
ON DUPLICATE KEY UPDATE
manufacturer=VALUES(manufacturer), model=VALUES(model), android_version=VALUES(android_version),
battery_level=VALUES(battery_level), connection_type=VALUES(connection_type), controller_name=VALUES(controller_name),
status=VALUES(status), last_seen_at=NOW(), metadata_json=VALUES(metadata_json)";
$stmt = $conn->prepare($sql);
if (!$stmt) jsonResponse(['success'=>false,'message'=>'Prepare failed'],500);
$count = 0;
foreach ($devices as $d) {
    if (!is_array($d)) continue;
    $serial = trim((string)($d['serial'] ?? ''));
    if ($serial === '') continue;
    $deviceCode = trim((string)($d['device_code'] ?? 'DEV-' . strtoupper(substr(hash('sha256',$serial),0,8))));
    $manufacturer = trim((string)($d['manufacturer'] ?? ''));
    $model = trim((string)($d['model'] ?? ''));
    $android = trim((string)($d['android_version'] ?? ''));
    $battery = max(0, min(100, (int)($d['battery_level'] ?? 0)));
    $connection = in_array(($d['connection_type'] ?? 'usb'), ['usb','wifi','agent'], true) ? $d['connection_type'] : 'usb';
    $status = in_array(($d['status'] ?? 'offline'), ['online','offline','unauthorized','maintenance'], true) ? $d['status'] : 'offline';
    $metadata = json_encode($d['metadata'] ?? new stdClass(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $stmt->bind_param('sssssisiss', $deviceCode,$serial,$manufacturer,$model,$android,$battery,$connection,$controller,$status,$metadata);
    if ($stmt->execute()) $count++;
}
$stmt->close();
jsonResponse(['success'=>true,'synced'=>$count]);
