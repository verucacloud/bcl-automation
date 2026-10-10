'use strict';
require('dotenv').config();
const axios = require('axios');
const { execFile } = require('child_process');
const fs = require('fs');
const path = require('path');

const baseURL = (process.env.DASHBOARD_URL || '').replace(/\/$/, '');
const apiKey = process.env.CONTROLLER_API_KEY || '';
const controllerName = process.env.CONTROLLER_NAME || 'controller-lokal-01';
const adb = process.env.ADB_PATH || 'adb';
const pollMs = Number(process.env.POLL_INTERVAL_MS || 5000);
const syncMs = Number(process.env.SYNC_INTERVAL_MS || 30000);
const screenshotDir = path.resolve(process.env.SCREENSHOT_DIR || './screenshots');
fs.mkdirSync(screenshotDir, { recursive: true });

const api = axios.create({baseURL,timeout:20000,headers:{'X-Controller-Key':apiKey,'Content-Type':'application/json'}});
const run = (args, options={}) => new Promise((resolve,reject)=>execFile(adb,args,{timeout:30000,maxBuffer:4*1024*1024,...options},(e,stdout,stderr)=>e?reject(new Error((stderr||e.message).trim())):resolve(stdout)));
const shell = (serial,args)=>run(['-s',serial,'shell',...args]);

async function prop(serial,key){ try{return (await shell(serial,['getprop',key])).trim();}catch{return '';} }
async function battery(serial){ try{const out=await shell(serial,['dumpsys','battery']); const m=out.match(/level:\s*(\d+)/); return m?Number(m[1]):0;}catch{return 0;} }

async function discover(){
 const out=await run(['devices']);
 const rows=out.split(/\r?\n/).slice(1).map(x=>x.trim()).filter(Boolean);
 const devices=[];
 for(const row of rows){
   const [serial,state]=row.split(/\s+/);
   if(!serial) continue;
   if(state!=='device'){devices.push({serial,status:state==='unauthorized'?'unauthorized':'offline'});continue;}
   devices.push({serial,status:'online',manufacturer:await prop(serial,'ro.product.manufacturer'),model:await prop(serial,'ro.product.model'),android_version:await prop(serial,'ro.build.version.release'),battery_level:await battery(serial),connection_type:serial.includes(':')?'wifi':'usb'});
 }
 return devices;
}

async function syncDevices(){
 try{await api.post('/api/controller/device_sync.php',{controller_name:controllerName,devices:await discover()});}
 catch(e){console.error('[sync]',e.response?.data||e.message);}
}

function validatePackageName(value){return typeof value==='string' && /^[a-zA-Z0-9_.]+$/.test(value);}
async function executeTask(task){
 const serial=task.adb_serial; const payload=task.payload||{};
 switch(task.action){
  case 'collect_status': return {battery_level:await battery(serial),model:await prop(serial,'ro.product.model')};
  case 'open_app':
   if(!validatePackageName(payload.package_name)) throw new Error('package_name tidak valid');
   await shell(serial,['monkey','-p',payload.package_name,'-c','android.intent.category.LAUNCHER','1']); return {opened:true};
  case 'close_app':
   if(!validatePackageName(payload.package_name)) throw new Error('package_name tidak valid');
   await shell(serial,['am','force-stop',payload.package_name]); return {closed:true};
  case 'screenshot': {
   const file=path.join(screenshotDir,`${task.id}-${Date.now()}.png`);
   await run(['-s',serial,'exec-out','screencap','-p'],{encoding:'buffer'}).then(buf=>fs.writeFileSync(file,buf));
   return {local_file:file};
  }
  default: throw new Error('Action tidak diizinkan');
 }
}

let busy=false;
async function poll(){
 if(busy)return; busy=true;
 try{
  const {data}=await api.post('/api/controller/claim_task.php',{controller_name:controllerName});
  const task=data.task; if(!task)return;
  await api.post('/api/controller/task_result.php',{task_id:task.id,status:'running'});
  try{const result=await executeTask(task);await api.post('/api/controller/task_result.php',{task_id:task.id,status:'completed',result});}
  catch(e){await api.post('/api/controller/task_result.php',{task_id:task.id,status:'failed',error_message:e.message});}
 }catch(e){console.error('[poll]',e.response?.data||e.message);}finally{busy=false;}
}

if(!baseURL||!apiKey){console.error('Isi DASHBOARD_URL dan CONTROLLER_API_KEY pada .env');process.exit(1);}
syncDevices(); setInterval(syncDevices,syncMs); setInterval(poll,pollMs);
console.log(`Controller ${controllerName} aktif.`);
