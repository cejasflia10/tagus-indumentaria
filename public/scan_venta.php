<?php
// public/scan_venta.php — Escanear QR y abrir venta_qr.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/partials/menu.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/public$#', '', $scriptDir); if ($BASE === '') $BASE = '/';
$ventaBase = rtrim($BASE,'/').'/app/pages/venta_qr.php';
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
.wrap{max-width:860px;margin:16px auto;padding:0 14px}
.panel{border:1px solid #e5e7eb;border-radius:14px;background:#fff;box-shadow:0 6px 20px rgba(17,24,39,.06)}
.px{padding:12px 14px}
.header{font-weight:800;border-bottom:1px solid #e5e7eb}
.row{display:grid;gap:12px}
.label{font-weight:700;margin:.2rem 0}
.input,textarea{width:100%;padding:12px 14px;border:1px solid #e5e7eb;border-radius:10px;min-height:44px;font-size:16px}
.btn{display:inline-flex;gap:8px;align-items:center;justify-content:center;padding:12px 16px;border-radius:12px;border:1px solid transparent;cursor:pointer;font-weight:700;min-height:44px}
.btn-primary{background:#0d6efd;color:#fff}
.btn-muted{background:#f3f4f6}
video{width:100%;border-radius:12px;background:#000}
canvas{display:none}
</style>

<div class="wrap">
  <div class="panel">
    <div class="px header">Escanear QR (venta)</div>
    <div class="px">
      <div class="row">
        <div>
          <video id="video" playsinline></video>
          <div style="display:flex;gap:8px;margin-top:8px">
            <button class="btn btn-primary" id="btnStart">Iniciar cámara</button>
            <button class="btn btn-muted" id="btnStop">Detener</button>
          </div>
          <div class="label" style="margin-top:10px">Código leído</div>
          <textarea id="qrOut" rows="3" class="input" placeholder="Acá aparecerá el contenido del QR"></textarea>
          <div style="display:flex;gap:8px;margin-top:8px">
            <button class="btn btn-primary" id="btnGo">Ir a confirmar venta</button>
            <button class="btn btn-muted" id="btnClear">Limpiar</button>
          </div>
        </div>
      </div>
      <small class="muted">Si el QR es una URL a <code>venta_qr.php?pid=..&vid=..</code>, te llevo directo a confirmar.</small>
    </div>
  </div>
</div>

<canvas id="canvas"></canvas>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const qrOut  = document.getElementById('qrOut');
const btnStart = document.getElementById('btnStart');
const btnStop  = document.getElementById('btnStop');
const btnGo    = document.getElementById('btnGo');
const btnClear = document.getElementById('btnClear');

let stream = null, rafId = null;

btnStart.onclick = async () => {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    video.srcObject = stream;
    await video.play();
    tick();
  } catch (e) {
    alert('No se pudo iniciar la cámara: ' + e);
  }
};

btnStop.onclick = () => {
  if (rafId) cancelAnimationFrame(rafId);
  if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
};

btnGo.onclick = () => {
  const v = (qrOut.value || '').trim();
  if (!v) return;
  let url = v;
  try {
    const u = new URL(v, window.location.origin);
    // si apunta a venta_qr y tiene pid/vid, vamos directo
    if (u.pathname.includes('/app/pages/venta_qr.php') && (u.searchParams.get('pid') && u.searchParams.get('vid'))) {
      window.location.href = u.toString();
      return;
    }
  } catch (e) {
    // no es URL absoluta; intento armar si viene como "pid=..&vid=.."
    if (/pid=\d+/.test(v) && /vid=\d+/.test(v)) {
      url = '<?= h($ventaBase) ?>' + '?' + v + '&sell=1';
      window.location.href = url;
      return;
    }
  }
  // Fallback: si el QR contiene otra cosa, intento redirigir igual (puede ser URL absoluta)
  window.location.href = url;
};

btnClear.onclick = () => { qrOut.value = ''; };

function tick(){
  if (!video.videoWidth) { rafId = requestAnimationFrame(tick); return; }
  const w = video.videoWidth, h = video.videoHeight;
  canvas.width = w; canvas.height = h;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, w, h);
  const img = ctx.getImageData(0, 0, w, h);
  const code = jsQR(img.data, w, h, { inversionAttempts: 'dontInvert' });
  if (code && code.data) {
    qrOut.value = code.data;
    // Auto-go si es un venta_qr directo
    try {
      const u = new URL(code.data, window.location.origin);
      if (u.pathname.includes('/app/pages/venta_qr.php') && (u.searchParams.get('pid') && u.searchParams.get('vid'))) {
        window.location.href = u.toString();
        return;
      }
    } catch(e){}
  }
  rafId = requestAnimationFrame(tick);
}
</script>
