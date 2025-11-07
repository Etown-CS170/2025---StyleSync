<?php
// album.php — album-related helpers

function list_albums($uploadsDir){
  $dirs = array_values(array_filter(scandir($uploadsDir), function($f) use ($uploadsDir){
    return $f !== '.' && $f !== '..' && is_dir($uploadsDir . '/' . $f);
  }));
  sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
  return $dirs;
}

function album_images($uploadsDir, $album){
  $root = $uploadsDir . '/' . $album;
  if (!is_dir($root)) return [];
  $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
  $out = [];
  foreach ($rii as $file) {
    if ($file->isFile()) {
      $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
      if (preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) {
        $rel = ltrim(str_replace('\\','/', substr($file->getPathname(), strlen($uploadsDir))), '/');
        $out[] = $rel;
      }
    }
  }
  sort($out, SORT_NATURAL | SORT_FLAG_CASE);
  return $out;
}

function album_first_image($uploadsDir, $album){
  $imgs = album_images($uploadsDir, $album);
  return $imgs ? $imgs[0] : null;
}

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

?>
