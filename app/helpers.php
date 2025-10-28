<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function money($n){ return number_format((float)$n, 2, ',', '.'); }


function cloud_upload_or_local(array $file): ?string {
if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
$tmp = $file['tmp_name'];
if (CLOUD_ENABLED && CLOUD_NAME && CLOUD_API_KEY && CLOUD_API_SECRET) {
$timestamp = time();
$params_to_sign = [ 'folder' => CLOUD_UPLOAD_FOLDER, 'timestamp' => $timestamp ];
ksort($params_to_sign);
$to_sign = http_build_query($params_to_sign, '', '&', PHP_QUERY_RFC3986) . CLOUD_API_SECRET;
$signature = sha1($to_sign);
$ch = curl_init('https://api.cloudinary.com/v1_1/'.rawurlencode(CLOUD_NAME).'/image/upload');
$post = [
'api_key' => CLOUD_API_KEY,
'timestamp' => $timestamp,
'signature' => $signature,
'folder' => CLOUD_UPLOAD_FOLDER,
'file' => new CURLFile($tmp, $file['type'] ?? 'image/jpeg', $file['name'] ?? 'upload.jpg')
];
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post]);
$resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if ($code>=200 && $code<300){ $j=json_decode($resp,true); return $j['secure_url'] ?? $j['url'] ?? null; }
return null;
}
// Fallback local
$dir = __DIR__.'/../public/uploads/indumentaria'; if(!is_dir($dir)) @mkdir($dir,0775,true);
$ext = strtolower(pathinfo($file['name'] ?? ('foto_'.time().'.jpg'), PATHINFO_EXTENSION) ?: 'jpg');
$name = 'prod_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.'.$ext;
if (@move_uploaded_file($tmp, $dir.'/'.$name)) return '/uploads/indumentaria/'.$name;
return null;
}