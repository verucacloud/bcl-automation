<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireControllerKey();
$data=inputJson();
$id=(int)($data['task_id']??0);
$status=(string)($data['status']??'failed');
if ($id<1 || !in_array($status,['running','completed','failed'],true)) jsonResponse(['success'=>false,'message'=>'Invalid task result'],422);
$result=json_encode($data['result']??new stdClass(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$error=trim((string)($data['error_message']??''));
if ($status==='running') {
 $stmt=$conn->prepare("UPDATE controller_tasks SET status='running',started_at=NOW() WHERE id=?"); $stmt->bind_param('i',$id);
} elseif ($status==='completed') {
 $stmt=$conn->prepare("UPDATE controller_tasks SET status='completed',completed_at=NOW(),result_json=?,error_message=NULL WHERE id=?"); $stmt->bind_param('si',$result,$id);
} else {
 $stmt=$conn->prepare("UPDATE controller_tasks SET status='failed',completed_at=NOW(),result_json=?,error_message=? WHERE id=?"); $stmt->bind_param('ssi',$result,$error,$id);
}
$stmt->execute(); $stmt->close();
jsonResponse(['success'=>true]);
