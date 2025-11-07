<?php
// actions.php — POST handlers and data preparation for the template

$notice = '';
$currentAlbum = isset($_GET['album']) ? $_GET['album'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  /* Folder-only upload (with declared type) */
  if ($action === 'upload_folder') {
    $declared = strtolower(trim($_POST['declared_type'] ?? 'auto'));
    $declaredMime = $declared==='jpeg'?'image/jpeg':($declared==='png'?'image/png':($declared==='webp'?'image/webp':null));
    $allowedMimes = ['image/jpeg','image/png','image/webp'];
    $handled = 0; $skipped = 0;

    if (empty($_FILES['folder']['name'][0])) {
      $notice = 'Please choose a folder with images.';
    } else {
      $uploads_meta = read_json_arr($UPLOADS_META_JSON);

      foreach ($_FILES['folder']['name'] as $i => $relPath) {
        if ($_FILES['folder']['error'][$i] !== UPLOAD_ERR_OK) { $skipped++; continue; }

        $tmp  = $_FILES['folder']['tmp_name'][$i];
        $mime = @mime_content_type($tmp);
        if (!in_array($mime, $allowedMimes, true)) { $skipped++; continue; }
        if ($declaredMime && $mime !== $declaredMime) { $skipped++; continue; }

        $rel = str_replace('\\','/', $relPath);
        $parts = explode('/', $rel, 2);
        $albumRaw = $parts[0];
        $album = trim(preg_replace('/[^A-Za-z0-9 _.-]/', '_', $albumRaw));
        if ($album === '') $album = 'album_' . date('Ymd_His');

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
            'stored'        => ltrim(str_replace('\\','/', substr($dest, strlen($UPLOADS))), '/'),
            'original_path' => $relPath,
            'mime'          => $mime,
            'declared_type' => $declared,
            'declared_mime' => $declaredMime,
            'created_at'    => date('c')
          ];
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
      $file_type = album_file_type($UPLOADS, $selectedAlbum);
      $nextId = empty($jobs) ? 1 : (max(array_column($jobs, 'id')) + 1);
      $jobs[] = [
        'id' => $nextId,
        'album' => $selectedAlbum,
        'file_type' => $file_type,
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
