
# Style Sync

Style Sync is a lightweight PHP web app (folder-only uploads) that helps you prepare image-based "jobs" from local design folders (albums). It currently runs locally (XAMPP recommended) and lets you:

- Upload a folder of images (via the browser's folder picker/webkitdirectory).
- Select an uploaded folder (an "album") and create generation jobs referencing that folder.
- Preview albums and use a simple mocked workflow to mark jobs done and regenerate them with notes.

This repository is an entrypoint for adding an image-to-image model (for example, a Hugging Face image-to-image pipeline) that will process images in an album using prompts/notes and the selected options.

## Files Incorperated

- Language: PHP + bootstrap (single-page UI)
- Run with: XAMPP (Apache + PHP) on Windows
- Files of interest:
	- `index.php` — main template; includes `inc/bootstrap.php`, `inc/album.php`, `inc/actions.php`
	- `inc/bootstrap.php` — initialization and helper functions
	- `inc/album.php` — album scanning and helpers
	- `inc/actions.php` — POST handlers and job creation logic
	- `data/jobs.json` — persisted job queue/state
	- `data/uploads_meta.json` — metadata for uploaded files
	- `uploads/` — uploaded albums (created automatically)

## Requirements

- XAMPP (Apache + PHP). Tested with PHP 7.4+; newer versions should work.
- Modern browser (Chrome, Edge) for folder uploads (`webkitdirectory` support).

## Install & Run (Windows / XAMPP)

1. Put the project folder inside your XAMPP `htdocs` directory, e.g.:

```powershell
# from PowerShell (example; adapt the paths)
# Copy-Item -Path 'C:\path\to\2025---StyleSync' -Destination 'C:\xampp\htdocs\' -Recurse
```

2. Start Apache from the XAMPP control panel.

# Style Sync

Style Sync is a small PHP web app that helps you turn a folder of images into a "job" you can later process with an image model. It's meant to run locally (XAMPP is easiest).

Quick start
1. Put this project folder inside your XAMPP `htdocs` (for example `C:\xampp\htdocs\2025---StyleSync`).
2. Start Apache from the XAMPP control panel.
3. Open: http://localhost/2025---StyleSync/ in your browser.

How to use
- Upload a folder using the "Upload folder" card. The top-level folder name becomes the album.
- Click the album on the left to preview images.
- Use the Generation form to create a job that references that album.
- For testing, you can mark jobs as complete with the "Mark Complete (mock)" button.

Important files
- `index.php` — UI and template (loads `inc/` files).
- `inc/bootstrap.php`, `inc/album.php`, `inc/actions.php` — app logic split into small files.
- `uploads/` — where your albums are stored.
- `data/jobs.json` and `data/uploads_meta.json` — simple JSON data stores.

Hugging Face / model plans (short)
- The app doesn't run a model yet. The plan is to add a background worker that reads queued jobs and runs an image-to-image model (Hugging Face API or a local model).
- Worker idea: pick queued jobs, process each image in the album, save outputs to `uploads/<album>/_outputs/`, then mark the job `done`.

Security note
- This is for local use. Don't expose it to the public without adding auth, CSRF protection, and file checks.

Next steps
- I can scaffold a simple `worker.php` that shows how to process one job and call the Hugging Face Inference API.


