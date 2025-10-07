<?php
/* Style Sync — Folder-only version (single file)
 * - Uploads: folder-only (webkitdirectory)
 * - Select an uploaded folder ("album") to generate from
 * - Jobs reference a folder; completed section shows first image as preview
 * Folders required: /uploads (writable), /data (writable)
 */

$ROOT = __DIR__;
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

/* List top-level albums (folders) under /uploads */
function list_albums($uploadsDir){
  $dirs = array_values(array_filter(scandir($uploadsDir), function($f) use ($uploadsDir){
    return $f !== '.' && $f !== '..' && is_dir($uploadsDir . '/' . $f);
  }));
  sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
  return $dirs;
}

/* Collect image files (recursively) within an album */
function album_images($uploadsDir, $album){
  $root = $uploadsDir . '/' . $album;
  if (!is_dir($root)) return [];
  $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
  $out = [];
  foreach ($rii as $file) {
    if ($file->isFile()) {
      $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
      if (preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) {
        // path relative to /uploads
        $rel = ltrim(str_replace('\\','/', substr($file->getPathname(), strlen($uploadsDir))), '/');
        $out[] = $rel;
      }
    }
  }
  sort($out, SORT_NATURAL | SORT_FLAG_CASE);
  return $out;
}

/* First image path (relative to /uploads) or null */
function album_first_image($uploadsDir, $album){
  $imgs = album_images($uploadsDir, $album);
  return $imgs ? $imgs[0] : null;
}

/* Determine folder "type" for backend (jpeg/png/webp/mixed/unknown) */
function album_file_type($uploadsDir, $album){
  $imgs = album_images($uploadsDir, $album);
  if (!$imgs) return 'unknown';
  $types = [];
  foreach ($imgs as $rel){
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    $t = ($ext==='png'?'png':($ext==='webp'?'webp':'jpeg'));
    $types[$t] = true;
    if (count($types) > 1) return 'mixed';
  }
  return array_key_first($types);
}

/* ---------- Actions ---------- */
$notice = '';
$currentAlbum = isset($_GET['album']) ? $_GET['album'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  /* Folder-only upload (with declared type) */
  if ($action === 'upload_folder') {
    $declared = strtolower(trim($_POST['declared_type'] ?? 'auto')); // auto/jpeg/png/webp
    $declaredMime = $declared==='jpeg'?'image/jpeg':($declared==='png'?'image/png':($declared==='webp'?'image/webp':null));
    $allowedMimes = ['image/jpeg','image/png','image/webp'];
    $handled = 0; $skipped = 0;

    if (empty($_FILES['folder']['name'][0])) {
      $notice = 'Please choose a folder with images.';
    } else {
      $uploads_meta = read_json_arr($UPLOADS_META_JSON);

      // When using webkitdirectory, each file name contains a relative path like "MyAlbum/sub/x.jpg"
      foreach ($_FILES['folder']['name'] as $i => $relPath) {
        if ($_FILES['folder']['error'][$i] !== UPLOAD_ERR_OK) { $skipped++; continue; }

        $tmp  = $_FILES['folder']['tmp_name'][$i];
        $mime = @mime_content_type($tmp);
        if (!in_array($mime, $allowedMimes, true)) { $skipped++; continue; }
        if ($declaredMime && $mime !== $declaredMime) { $skipped++; continue; }

        // Extract top-level folder (album)
        $rel = str_replace('\\','/', $relPath);
        $parts = explode('/', $rel, 2);
        $albumRaw = $parts[0];
        // sanitize album name
        $album = trim(preg_replace('/[^A-Za-z0-9 _.-]/', '_', $albumRaw));
        if ($album === '') $album = 'album_' . date('Ymd_His');

        // compute destination path under /uploads/<album>/<subpath...>
        $sub = count($parts) > 1 ? $parts[1] : basename($rel);
        $sub = trim($sub, '/');
        $ext = strtolower(pathinfo($sub, PATHINFO_EXTENSION)) ?: ext_from_mime($mime);
        if (!preg_match('/^(jpe?g|png|webp)$/i', $ext)) $ext = ext_from_mime($mime);

        $destDir = $UPLOADS . '/' . $album . '/' . trim(dirname($sub), '.');
        if ($destDir === $UPLOADS . '/' . $album . '/' ) $destDir = $UPLOADS . '/' . $album;
        ensure_dir($destDir);

        $base = basename($sub);
        if ($base === '' || $base === '.' || $base === '..') $base = uniqid('img_', true) . '.' . $ext;

        $dest = $destDir . '/' . $base;
        if (@move_uploaded_file($tmp, $dest)) {
          $handled++;
          $uploads_meta[] = [
            'album'         => $album,
            'stored'        => ltrim(str_replace('\\','/', substr($dest, strlen($UPLOADS))), '/'), // relative to /uploads
            'original_path' => $relPath,
            'mime'          => $mime,
            'declared_type' => $declared,
            'declared_mime' => $declaredMime,
            'created_at'    => date('c')
          ];
          // remember the last album for redirect/select
          $currentAlbum = $album;
        } else {
          $skipped++;
        }
      }

      write_json_arr($UPLOADS_META_JSON, $uploads_meta);
      if ($handled && $skipped) $notice = "Uploaded $handled image(s). Skipped $skipped (type mismatch or error).";
      elseif ($handled)         $notice = "Uploaded $handled image(s) into album “" . h($currentAlbum) . "”.";
      else                      $notice = "No valid images found or all mismatched for the chosen file type.";
    }
  }

  /* Create job for a selected folder */
  if ($action === 'create_jobs') {
    $selectedAlbum = trim($_POST['selected_album'] ?? '');
    $outreach = trim($_POST['outreach'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $s1 = (int)($_POST['style_intensity'] ?? 50);
    $s2 = (int)($_POST['color_variation'] ?? 50);
    $s3 = (int)($_POST['layout_variation'] ?? 50);

    if ($selectedAlbum === '') {
      $notice = 'Please select an uploaded folder (album) on the left.';
    } elseif ($outreach === '') {
      $notice = 'Please choose a type of outreach.';
    } else {
      $jobs = read_json_arr($JOBS_JSON);

      // infer folder file type (or mixed)
      $file_type = album_file_type($UPLOADS, $selectedAlbum);

      $nextId = empty($jobs) ? 1 : (max(array_column($jobs, 'id')) + 1);
      $jobs[] = [
        'id' => $nextId,
        'album' => $selectedAlbum,
        'file_type' => $file_type, // jpeg/png/webp/mixed/unknown
        'outreach' => $outreach,
        'style_intensity' => max(0,min(100,$s1)),
        'color_variation' => max(0,min(100,$s2)),
        'layout_variation' => max(0,min(100,$s3)),
        'notes' => $notes,
        'status' => 'queued',
        'created_at' => date('c'),
        'output_path' => null
      ];
      write_json_arr($JOBS_JSON, $jobs);
      $notice = 'Job created for album “' . h($selectedAlbum) . '”.';
    }
  }

  /* Clear all jobs */
  if ($action === 'clear_jobs') {
    write_json_arr($JOBS_JSON, []);
    $notice = 'Jobs cleared.';
  }

  /* Regenerate (duplicate a job) */
  if ($action === 'regenerate') {
    $id = (int)($_POST['job_id'] ?? 0);
    $jobs = read_json_arr($JOBS_JSON);
    foreach ($jobs as $j) {
      if ($j['id'] === $id) {
        $nextId = empty($jobs) ? 1 : (max(array_column($jobs, 'id')) + 1);
        $j['id'] = $nextId;
        $j['created_at'] = date('c');
        $j['status'] = 'queued';
        $j['output_path'] = null;
        $jobs[] = $j;
        write_json_arr($JOBS_JSON, $jobs);
        $notice = "Job #$id re-queued.";
        break;
      }
    }
  }

  /* Regenerate with notes (from Completed) */
  if ($action === 'regenerate_with_notes') {
    $id = (int)($_POST['job_id'] ?? 0);
    $newNotes = trim($_POST['new_notes'] ?? '');
    $jobs = read_json_arr($JOBS_JSON);
    foreach ($jobs as $j) {
      if ($j['id'] === $id) {
        $nextId = empty($jobs) ? 1 : (max(array_column($jobs, 'id')) + 1);
        $j['id'] = $nextId;
        $j['created_at'] = date('c');
        $j['status'] = 'queued';
        if ($newNotes !== '') $j['notes'] = $newNotes;
        $j['output_path'] = null;
        $jobs[] = $j;
        write_json_arr($JOBS_JSON, $jobs);
        $notice = "Job #$id re-queued with notes.";
        break;
      }
    }
  }

  /* MOCK: mark a job complete (for demo) — uses first image as preview */
  if ($action === 'mock_complete') {
    $id = (int)($_POST['job_id'] ?? 0);
    $jobs = read_json_arr($JOBS_JSON);
    foreach ($jobs as &$j) {
      if ($j['id'] === $id) {
        $j['status'] = 'done';
        $first = album_first_image($UPLOADS, $j['album']);
        $j['output_path'] = $first ? 'uploads/' . $first : null;
        $notice = "Job #$id marked complete (mock).";
        break;
      }
    }
    unset($j);
    write_json_arr($JOBS_JSON, $jobs);
  }
}

/* ---------- Data for UI ---------- */
$albums = list_albums($UPLOADS);
if (!$currentAlbum && $albums) $currentAlbum = $albums[0];

$albumImages = $currentAlbum ? album_images($UPLOADS, $currentAlbum) : [];
$firstPreview = $currentAlbum ? album_first_image($UPLOADS, $currentAlbum) : null;

$jobs = read_json_arr($JOBS_JSON);
usort($jobs, fn($a,$b)=>$b['id'] <=> $a['id']);
$queuedJobs = array_values(array_filter($jobs, fn($j)=>$j['status'] !== 'done'));
$doneJobs   = array_values(array_filter($jobs, fn($j)=>$j['status'] === 'done'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Style Sync — Folder Uploads</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .thumb { border-radius:8px; border:1px solid #e6e6e6; }
    .preview-box { aspect-ratio: 1/1; background:#f8f9fa; border:1px dashed #ced4da; border-radius:8px;
                   display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .preview-box img { max-width:100%; max-height:100%; }
    .album-link.active { font-weight:600; }
  </style>
</head>
<body class="bg-light">
  <div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h1 class="h4 mb-0">Style Sync</h1>
        <small class="text-muted">Upload a <strong>folder</strong> of images → select folder → set options → create job</small>
      </div>
      <button class="btn btn-outline-secondary btn-sm" disabled>Login</button>
    </div>

    <?php if ($notice): ?>
      <div class="alert alert-info py-2"><?= h($notice) ?></div>
    <?php endif; ?>

    <div class="row g-3">
      <!-- LEFT COLUMN -->
      <div class="col-12 col-lg-6">
        <!-- Upload (folder-only) -->
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Upload folder</h2>
            <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
              <input type="hidden" name="action" value="upload_folder">
              <div class="col-12 col-md-6">
                <label class="form-label">Choose a folder</label>
                <input class="form-control" type="file" name="folder[]" webkitdirectory directory multiple required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">File type for processing</label>
                <select class="form-select" name="declared_type" required>
                  <option value="auto" selected>Auto detect</option>
                  <option value="jpeg">JPEG only</option>
                  <option value="png">PNG only</option>
                  <option value="webp">WEBP only</option>
                </select>
              </div>
              <div class="col-12">
                <button class="btn btn-primary" type="submit">Upload folder</button>
                <div class="form-text">Non-matching types are skipped when a specific type is chosen.</div>
              </div>
            </form>
          </div>
        </div>

        <!-- Album selector + preview + thumbnails -->
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h2 class="h6 text-uppercase text-muted mb-0">Select uploaded folder</h2>
              <?php if ($currentAlbum): ?>
                <span class="badge bg-secondary"><?= count($albumImages) ?> images</span>
              <?php endif; ?>
            </div>

            <?php if (!$albums): ?>
              <div class="text-secondary">No albums yet. Upload a folder above.</div>
            <?php else: ?>
              <!-- Album list -->
              <div class="mb-3">
                <div class="list-group list-group-sm">
                  <?php foreach ($albums as $a): 
                    $url = '?album=' . urlencode($a);
                    $active = $a === $currentAlbum ? ' active' : ''; ?>
                    <a href="<?= h($url) ?>" class="list-group-item list-group-item-action album-link<?= $active ?>">
                      <?= h($a) ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Preview -->
              <div class="preview-box mb-3">
                <?php if ($firstPreview): ?>
                  <img src="<?= 'uploads/' . h($firstPreview) ?>" alt="">
                <?php else: ?>
                  <span class="text-secondary small">No images found in this folder.</span>
                <?php endif; ?>
              </div>

              <!-- Thumbnails (subset) -->
              <?php if ($albumImages): ?>
                <div class="row row-cols-3 row-cols-md-4 g-2">
                  <?php
                    $limit = 24; $i = 0;
                    foreach ($albumImages as $rel):
                      if ($i++ >= $limit) break;
                      $src = 'uploads/' . $rel;
                  ?>
                    <div class="col">
                      <img src="<?= h($src) ?>" class="img-fluid thumb" alt="">
                    </div>
                  <?php endforeach; ?>
                </div>
                <?php if (count($albumImages) > $limit): ?>
                  <div class="small text-muted mt-1"><?= count($albumImages)-$limit ?> more…</div>
                <?php endif; ?>
              <?php endif; ?>

            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div class="col-12 col-lg-6">
        <!-- Generation -->
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Generation</h2>

            <form method="post">
              <input type="hidden" name="action" value="create_jobs">
              <input type="hidden" name="selected_album" value="<?= h($currentAlbum) ?>">

              <div class="mb-3">
                <label class="form-label">Type of outreach</label>
                <select class="form-select" name="outreach" required>
                  <option value="">Choose…</option>
                  <?php foreach (['billboard','pamphlet','magazine','social','poster','flyer','email','web'] as $m): ?>
                    <option value="<?= h($m) ?>"><?= ucfirst($m) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="row g-3">
                <div class="col-12 col-md-4">
                  <label class="form-label">Style intensity</label>
                  <input type="range" class="form-range" name="style_intensity" min="0" max="100" value="50" oninput="this.nextElementSibling.value=this.value">
                  <output>50</output>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label">Color variation</label>
                  <input type="range" class="form-range" name="color_variation" min="0" max="100" value="50" oninput="this.nextElementSibling.value=this.value">
                  <output>50</output>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label">Layout variation</label>
                  <input type="range" class="form-range" name="layout_variation" min="0" max="100" value="50" oninput="this.nextElementSibling.value=this.value">
                  <output>50</output>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Notes for generation</label>
                <textarea class="form-control" name="notes" rows="2" placeholder="Palette, copy hook, audience…"></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">Create</button>
                <button class="btn btn-outline-secondary" type="submit" name="action" value="clear_jobs">Clear</button>
              </div>
              <div class="form-text mt-2">Selected folder: <strong><?= $currentAlbum ? h($currentAlbum) : 'none' ?></strong></div>
            </form>
          </div>
        </div>

        <!-- Current jobs -->
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Current jobs list</h2>
            <?php if (!$queuedJobs): ?>
              <div class="text-secondary">No queued jobs.</div>
            <?php else: ?>
              <?php foreach ($queuedJobs as $j):
                $cover = album_first_image($UPLOADS, $j['album']);
                $src = $cover ? ('uploads/' . $cover) : ''; ?>
                <div class="border rounded p-2 mb-2">
                  <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                      <?php if ($src): ?>
                        <img src="<?= h($src) ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #eee">
                      <?php else: ?>
                        <div style="width:44px;height:44px;border-radius:6px;border:1px solid #eee;background:#f8f9fa"></div>
                      <?php endif; ?>
                      <div>
                        <div>
                          <strong>#<?= (int)$j['id'] ?></strong> • <?= h(ucfirst($j['outreach'])) ?>
                          <span class="text-muted small ms-2"><?= strtoupper(h($j['file_type'])) ?></span>
                        </div>
                        <div class="text-muted small">Album: <?= h($j['album']) ?> • <?= h(date('M j, Y H:i', strtotime($j['created_at']))) ?></div>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge bg-secondary"><?= h($j['status']) ?></span>
                      <form method="post" class="m-0">
                        <input type="hidden" name="action" value="mock_complete">
                        <input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                        <button class="btn btn-sm btn-outline-success" type="submit" title="Mark complete (demo only)">Mark Complete (mock)</button>
                      </form>
                    </div>
                  </div>
                  <div class="small mt-1">
                    <span class="me-3">Style <?= (int)$j['style_intensity'] ?></span>
                    <span class="me-3">Color <?= (int)$j['color_variation'] ?></span>
                    <span class="me-3">Layout <?= (int)$j['layout_variation'] ?></span>
                  </div>
                  <?php if (!empty($j['notes'])): ?>
                    <div class="small text-muted mt-1"><?= nl2br(h($j['notes'])) ?></div>
                  <?php endif; ?>
                  <div class="mt-2 d-flex gap-2">
                    <?php if ($src): ?>
                      <a class="btn btn-sm btn-outline-secondary" href="<?= h($src) ?>" target="_blank" rel="noopener">Open cover</a>
                    <?php endif; ?>
                    <form method="post">
                      <input type="hidden" name="action" value="regenerate">
                      <input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                      <button class="btn btn-sm btn-outline-primary" type="submit">Regenerate</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Completed results -->
        <div class="card shadow-sm">
          <div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Completed results (preview & regenerate)</h2>
            <?php if (!$doneJobs): ?>
              <div class="text-secondary">When a job finishes, it will appear here with a preview and a “Regenerate with notes” option.</div>
            <?php else: ?>
              <?php foreach ($doneJobs as $j):
                $preview = $j['output_path'] ?: (album_first_image($UPLOADS, $j['album']) ? 'uploads/' . album_first_image($UPLOADS, $j['album']) : null); ?>
                <div class="border rounded p-2 mb-3">
                  <div class="d-flex gap-3">
                    <?php if ($preview): ?>
                      <a href="<?= h($preview) ?>" target="_blank" rel="noopener">
                        <img src="<?= h($preview) ?>" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:8px;border:1px solid #eee">
                      </a>
                    <?php else: ?>
                      <div style="width:96px;height:96px;border-radius:8px;border:1px solid #eee;background:#f8f9fa"></div>
                    <?php endif; ?>
                    <div class="flex-grow-1">
                      <div class="d-flex justify-content-between align-items-center">
                        <div>
                          <strong>#<?= (int)$j['id'] ?></strong> • <?= h(ucfirst($j['outreach'])) ?> • <span class="text-success">done</span>
                          <span class="text-muted small ms-2"><?= strtoupper(h($j['file_type'])) ?></span>
                          <span class="text-muted small ms-2">Album: <?= h($j['album']) ?></span>
                        </div>
                        <?php if ($preview): ?>
                          <a class="btn btn-sm btn-outline-secondary" href="<?= h($preview) ?>" target="_blank" rel="noopener">Open</a>
                        <?php endif; ?>
                      </div>
                      <div class="small mt-1">
                        <span class="me-3">Style <?= (int)$j['style_intensity'] ?></span>
                        <span class="me-3">Color <?= (int)$j['color_variation'] ?></span>
                        <span class="me-3">Layout <?= (int)$j['layout_variation'] ?></span>
                      </div>
                      <?php if (!empty($j['notes'])): ?>
                        <div class="small text-muted mt-1"><?= nl2br(h($j['notes'])) ?></div>
                      <?php endif; ?>
                      <form method="post" class="mt-2">
                        <input type="hidden" name="action" value="regenerate_with_notes">
                        <input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                        <div class="input-group">
                          <input class="form-control" name="new_notes" placeholder="Add notes for regeneration (optional)">
                          <button class="btn btn-outline-primary" type="submit">Regenerate with notes</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <footer class="pt-4 text-center text-muted small">Style Sync • Folder-only • PHP + Bootstrap • XAMPP</footer>
  </div>
</body>
</html>
