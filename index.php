<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/album.php';
require_once __DIR__ . '/inc/actions.php';
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
        <div class="card shadow-sm mb-3">
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

        <!-- Img2Img Test Card (NEW) -->
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Test Img2Img (Diffusers)</h2>
            <p class="small text-muted">
              Upload a source image and enter a prompt.
            </p>

            <form id="img2img-form" enctype="multipart/form-data">
              <div class="mb-3">
                <label class="form-label">Source image</label>
                <input type="file" name="image" class="form-control" accept="image/*" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Prompt</label>
                <input type="text" name="prompt" class="form-control" placeholder="E.g., turn this into a neon poster" required>
              </div>

              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label class="form-label">Strength</label>
                  <input type="number" name="strength" class="form-control" min="0" max="1" step="0.05" value="0.6">
                </div>
                <div class="col-6">
                  <label class="form-label">Guidance scale</label>
                  <input type="number" name="guidance_scale" class="form-control" min="1" max="20" step="0.5" value="7.5">
                </div>
              </div>

              <button type="button" class="btn btn-primary" onclick="runImg2Img()">
                Generate
              </button>
            </form>

            <p id="img2img-status" class="mt-3 text-muted"></p>
            <img id="img2img-output" class="mt-2" style="max-width:100%; border-radius:4px; display:none;" alt="Generated result">
          </div>
        </div>
        <!-- End Img2Img Test Card -->

      </div>
    </div>

    <footer class="pt-4 text-center text-muted small">Style Sync • Folder-only • PHP + Bootstrap • XAMPP</footer>
  </div>

  <!-- JS for Img2Img test -->
  <script>
async function runImg2Img() {
  const form = document.getElementById("img2img-form");
  const status = document.getElementById("img2img-status");
  const output = document.getElementById("img2img-output");

  const fd = new FormData(form);

  status.textContent = "Generating image...";
  output.style.display = "none";

  try {
    const res = await fetch("api/hf_img2img.php", {
      method: "POST",
      body: fd
    });

    const text = await res.text();   // get raw text first
    console.log("Raw response from hf_img2img.php:", text);

    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      status.textContent = "Failed to parse response JSON. Raw response is in console.";
      return;
    }

    if (!data.ok) {
      status.textContent = "Error: " + (data.error || "Unknown error");
      console.log("Error detail:", data);
      return;
    }

    status.textContent = "Done!";
    if (data.image_url) {
      output.src = data.image_url + "?t=" + Date.now();
      output.style.display = "block";
    } else {
      status.textContent = "No image_url returned from server.";
      console.log("Data without image_url:", data);
    }

  } catch (err) {
    console.error(err);
    status.textContent = "Request failed: " + err;
  }
}
</script>

</body>
</html>
