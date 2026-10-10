<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireControllerKey();
$data = inputJson();
$controller = trim((string)($data['controller_name'] ?? 'local-controller'));
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT t.id,t.action,t.payload_json,d.adb_serial,d.device_code
        FROM controller_tasks t JOIN managed_devices d ON d.id=t.device_id
        WHERE t.status='scheduled' AND t.scheduled_at<=NOW() AND d.controller_name=?
        ORDER BY t.scheduled_at,t.id LIMIT 1 FOR UPDATE");
    $stmt->bind_param('s',$controller); $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$task) { $conn->commit(); jsonResponse(['success'=>true,'task'=>null]); }
    $id=(int)$task['id'];
    $up=$conn->prepare("UPDATE controller_tasks SET status='claimed',claimed_by=?,claimed_at=NOW() WHERE id=? AND status='scheduled'");
    $up->bind_param('si',$controller,$id); $up->execute(); $up->close();
    $conn->commit();
    $task['payload'] = json_decode((string)($task['payload_json'] ?? '{}'), true) ?: [];
    unset($task['payload_json']);
    jsonResponse(['success'=>true,'task'=>$task]);
} catch (Throwable $e) {
    $conn->rollback(); error_log($e->getMessage()); jsonResponse(['success'=>false,'message'=>'Claim failed'],500);
}
