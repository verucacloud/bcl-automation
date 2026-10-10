<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $deviceId=(int)($_POST['device_id']??0); $action=(string)($_POST['action']??'collect_status');
 $scheduled=str_replace('T',' ',(string)($_POST['scheduled_at']??''));
 $package=trim((string)($_POST['package_name']??''));
 $allowed=['collect_status','open_app','close_app','screenshot'];
 if($deviceId<1||!in_array($action,$allowed,true)||strtotime($scheduled)===false){$message='Data task tidak valid.';}
 elseif(in_array($action,['open_app','close_app'],true)&&!preg_match('/^[a-zA-Z0-9_.]+$/',$package)){$message='Package name tidak valid.';}
 else{$payload=json_encode($package!==''?['package_name'=>$package]:new stdClass());$stmt=$conn->prepare("INSERT INTO controller_tasks(device_id,action,payload_json,scheduled_at) VALUES(?,?,?,?)");$stmt->bind_param('isss',$deviceId,$action,$payload,$scheduled);$stmt->execute();$stmt->close();$message='Task berhasil dijadwalkan.';}
}
$devices=[];$r=$conn->query("SELECT id,device_code,manufacturer,model,status FROM managed_devices ORDER BY device_code");if($r)while($x=$r->fetch_assoc())$devices[]=$x;
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Buat Task Device</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 text-slate-100 p-6"><main class="max-w-2xl mx-auto"><a class="text-cyan-300" href="automation_center.php">← Automation Center</a><h1 class="text-3xl font-bold mt-5">Jadwalkan Task Perangkat</h1><p class="text-slate-400 mt-2">Untuk monitoring dan pengujian aplikasi pada perangkat milik sendiri.</p><?php if($message):?><div class="mt-5 rounded-xl bg-white/10 p-4"><?=htmlspecialchars($message)?></div><?php endif;?><form method="post" class="mt-6 space-y-4 rounded-2xl border border-white/10 bg-white/5 p-6"><label class="block">Perangkat<select name="device_id" required class="mt-2 w-full rounded-lg bg-slate-900 p-3"><?php foreach($devices as $d):?><option value="<?=$d['id']?>"><?=htmlspecialchars($d['device_code'].' - '.$d['manufacturer'].' '.$d['model'].' ('.$d['status'].')')?></option><?php endforeach;?></select></label><label class="block">Aksi<select name="action" id="action" class="mt-2 w-full rounded-lg bg-slate-900 p-3"><option value="collect_status">Collect status</option><option value="open_app">Open app</option><option value="close_app">Close app</option><option value="screenshot">Screenshot</option></select></label><label class="block">Package name (untuk open/close app)<input name="package_name" placeholder="com.perusahaan.aplikasi" class="mt-2 w-full rounded-lg bg-slate-900 p-3"></label><label class="block">Waktu<input type="datetime-local" name="scheduled_at" required value="<?=date('Y-m-d\TH:i')?>" class="mt-2 w-full rounded-lg bg-slate-900 p-3"></label><button class="rounded-xl bg-indigo-600 px-5 py-3 font-semibold">Simpan Task</button></form></main></body></html>
