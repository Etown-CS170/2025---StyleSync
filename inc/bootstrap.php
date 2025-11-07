<?php
// bootstrap.php — paths, setup, and small helpers
$ROOT = dirname(__DIR__);
$UPLOADS = $ROOT . '/uploads';
$DATA = $ROOT . '/data';
$JOBS_JSON = $DATA . '/jobs.json';
$UPLOADS_META_JSON = $DATA . '/uploads_meta.json';

if (!is_dir($UPLOADS)) mkdir($UPLOADS, 0777, true);
if (!is_dir($DATA)) mkdir($DATA, 0777, true);
if (!file_exists($JOBS_JSON)) file_put_contents($JOBS_JSON, json_encode([]));
if (!file_exists($UPLOADS_META_JSON)) file_put_contents($UPLOADS_META_JSON, json_encode([]));

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function read_json_arr($f){ $j = json_decode(@file_get_contents($f) ?: '[]', true); return is_array($j)?$j:[]; }
function write_json_arr($f,$a){ file_put_contents($f, json_encode($a, JSON_PRETTY_PRINT)); }
function ext_from_mime($mime){ return $mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg'); }
function ensure_dir($path){ if (!is_dir($path)) @mkdir($path, 0777, true); }

?>
