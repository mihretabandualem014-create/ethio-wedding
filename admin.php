<?php
session_start();
require_once 'db.php';

// ── Auth ────────────────────────────────────────────────────────────────────
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
    } else {
        $loginError = 'Incorrect password. Please try again.';
    }
}

$loggedIn = !empty($_SESSION['admin']);

// ── Image resize (mirrors upload.php) ───────────────────────────────────────
function resizeAndSave(string $source, string $dest, int $maxWidth, int $quality): bool
{
    $info = @getimagesize($source);
    if (!$info) return false;

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($source); break;
        case 'image/png':  $img = @imagecreatefrompng($source);  break;
        case 'image/gif':  $img = @imagecreatefromgif($source);  break;
        case 'image/webp': $img = @imagecreatefromwebp($source); break;
        default: return false;
    }
    if (!$img) return false;

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($source);
        if (!empty($exif['Orientation'])) {
            switch ((int) $exif['Orientation']) {
                case 3: $img = imagerotate($img, 180, 0); break;
                case 6: $img = imagerotate($img, -90, 0); break;
                case 8: $img = imagerotate($img,  90, 0); break;
            }
        }
    }

    $w = imagesx($img);
    $h = imagesy($img);

    if ($w <= $maxWidth) {
        $result = imagejpeg($img, $dest, $quality);
        imagedestroy($img);
        return $result;
    }

    $ratio  = $maxWidth / $w;
    $newH   = (int) round($h * $ratio);
    $canvas = imagecreatetruecolor($maxWidth, $newH);
    $white  = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopyresampled($canvas, $img, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
    $result = imagejpeg($canvas, $dest, $quality);
    imagedestroy($img);
    imagedestroy($canvas);
    return $result;
}

// ── Handle upload ────────────────────────────────────────────────────────────
$uploadSuccess = false;
$uploadError   = '';

$uploadedCount = 0;
$uploadErrors  = [];

if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $files = $_FILES['photos'];
    $count = count($files['name']);

    if (!is_dir(UPLOAD_DIR) || !is_writable(UPLOAD_DIR)) {
        $uploadError = 'uploads/ directory is missing or not writable.';
    } else {
        for ($i = 0; $i < $count; $i++) {
            $errorCode = $files['error'][$i];
            $tmpName   = $files['tmp_name'][$i];
            $size      = $files['size'][$i];
            $origName  = $files['name'][$i];

            if ($errorCode === UPLOAD_ERR_NO_FILE) continue;
            if ($errorCode !== UPLOAD_ERR_OK) { $uploadErrors[] = htmlspecialchars($origName) . ': upload error.'; continue; }
            if ($size > MAX_FILE_SIZE) { $uploadErrors[] = htmlspecialchars($origName) . ': too large.'; continue; }

            $info = @getimagesize($tmpName);
            if (!$info || !in_array($info['mime'], $allowedMimes, true)) {
                $uploadErrors[] = htmlspecialchars($origName) . ': not a valid image.';
                continue;
            }
            $filename = uniqid('admin_', true) . '.jpg';
            $destPath = UPLOAD_DIR . $filename;
            if (!resizeAndSave($tmpName, $destPath, MAX_IMAGE_WIDTH, JPEG_QUALITY)) {
                $uploadErrors[] = htmlspecialchars($origName) . ': could not process.';
                continue;
            }
            try {
                getDB()->prepare('INSERT INTO photos (filename) VALUES (?)')->execute([$filename]);
                $uploadedCount++;
            } catch (PDOException $e) {
                @unlink($destPath);
                $uploadErrors[] = htmlspecialchars($origName) . ': failed to save.';
            }
        }
        $uploadSuccess = $uploadedCount > 0;
        if (!empty($uploadErrors)) $uploadError = implode('<br>', $uploadErrors);
    }
}

// ── Handle delete ────────────────────────────────────────────────────────────
$deleteMsg = '';
if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int) $_POST['delete_id'];
    try {
        $stmt = getDB()->prepare('SELECT filename FROM photos WHERE id = ?');
        $stmt->execute([$deleteId]);
        $row = $stmt->fetch();
        if ($row) {
            getDB()->prepare('DELETE FROM photos WHERE id = ?')->execute([$deleteId]);
            @unlink(UPLOAD_DIR . $row['filename']);
            $deleteMsg = 'Photo deleted.';
        }
    } catch (PDOException $e) {
        $deleteMsg = 'Could not delete photo.';
    }
}

// ── Handle video upload ───────────────────────────────────────────────────────
$videoSuccess      = false;
$videoError        = '';
$uploadedVideoCount = 0;

if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video_file'])) {
    $file  = $_FILES['video_file'];

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $videoError = 'Video too large for server limit. Upload via cPanel File Manager then use "Add existing" instead.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $videoError = 'Upload error (code ' . $file['error'] . ').';
    } elseif ($file['size'] > VIDEO_MAX_SIZE) {
        $videoError = 'Video too large (max 500 MB).';
    } else {
        $allowedExts = ['mp4', 'webm', 'mov', 'avi', 'ogv'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            $videoError = 'Not a valid video format. Use MP4, WebM, or MOV.';
        } elseif (!is_dir(VIDEO_DIR) && !mkdir(VIDEO_DIR, 0755, true)) {
            $videoError = 'videos/ directory could not be created.';
        } elseif (!is_writable(VIDEO_DIR)) {
            $videoError = 'videos/ directory is not writable.';
        } else {
            $filename = uniqid('video_', true) . '.' . $ext;
            $destPath = VIDEO_DIR . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $videoError = 'Could not save video file.';
            } else {
                try {
                    getDB()->prepare('INSERT INTO videos (filename) VALUES (?)')->execute([$filename]);
                    $videoSuccess       = true;
                    $uploadedVideoCount = 1;
                } catch (PDOException $e) {
                    @unlink($destPath);
                    $videoError = 'Failed to save to database.';
                }
            }
        }
    }
}

// ── Handle register existing video ───────────────────────────────────────────
$registerVideoMsg = '';
if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_existing_video'])) {
    $existingFile  = basename(trim($_POST['existing_video_filename'] ?? ''));
    $allowedExts   = ['mp4', 'webm', 'mov', 'avi', 'ogv'];
    $ext           = strtolower(pathinfo($existingFile, PATHINFO_EXTENSION));
    if (!$existingFile || !in_array($ext, $allowedExts, true)) {
        $registerVideoMsg = 'error:Invalid filename or extension.';
    } elseif (!file_exists(VIDEO_DIR . $existingFile)) {
        $registerVideoMsg = 'error:File not found in videos/ directory.';
    } else {
        try {
            getDB()->prepare('INSERT INTO videos (filename) VALUES (?)')->execute([$existingFile]);
            $registerVideoMsg = 'ok:Video registered successfully.';
        } catch (PDOException $e) {
            $registerVideoMsg = 'error:Database error.';
        }
    }
}

// ── Handle video delete ───────────────────────────────────────────────────────
$videoDeleteMsg = '';
if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_video_id'])) {
    $deleteVideoId = (int) $_POST['delete_video_id'];
    try {
        $stmt = getDB()->prepare('SELECT filename FROM videos WHERE id = ?');
        $stmt->execute([$deleteVideoId]);
        $row = $stmt->fetch();
        if ($row) {
            getDB()->prepare('DELETE FROM videos WHERE id = ?')->execute([$deleteVideoId]);
            @unlink(VIDEO_DIR . $row['filename']);
            $videoDeleteMsg = 'Video deleted.';
        }
    } catch (PDOException $e) {
        $videoDeleteMsg = 'Could not delete video.';
    }
}

// ── Fetch all photos ─────────────────────────────────────────────────────────
$photos  = [];
$dbError = false;
if ($loggedIn) {
    try {
        $stmt   = getDB()->query('SELECT id, filename FROM photos ORDER BY id DESC');
        $photos = $stmt->fetchAll();
    } catch (PDOException $e) {
        $dbError = true;
    }
}

// ── Fetch all videos ─────────────────────────────────────────────────────────
$videos = [];
if ($loggedIn) {
    try {
        $videos = getDB()->query('SELECT id, filename FROM videos ORDER BY id DESC')->fetchAll();
    } catch (PDOException $e) {
        // table may not exist yet — run setup.sql in phpMyAdmin
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin &mdash; <?= htmlspecialchars(SITE_TITLE) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=Great+Vibes&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=4">
  <style>
    .admin-wrap   { max-width: 960px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
    .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2.5rem; flex-wrap: wrap; gap: 1rem; }
    .admin-title  { font-family: var(--font-heading); font-size: 2rem; color: var(--rose); }
    .admin-grid   { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; margin-top: 2rem; }
    .admin-photo  { position: relative; border-radius: var(--radius); overflow: hidden; aspect-ratio: 1; background: var(--blush); }
    .admin-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .admin-photo form { position: absolute; top: 0.4rem; right: 0.4rem; }
    .btn-delete   { background: rgba(0,0,0,0.55); color: #fff; border: none; border-radius: 50%; width: 28px; height: 28px; font-size: 1rem; line-height: 28px; text-align: center; cursor: pointer; padding: 0; }
    .btn-delete:hover { background: #c0392b; }
    .login-box    { max-width: 360px; margin: 6rem auto; background: #fff; border-radius: var(--radius); box-shadow: var(--shadow-lg); padding: 2.5rem 2rem; text-align: center; }
    .login-box h1 { font-family: var(--font-script); font-size: 2.5rem; color: var(--rose); margin-bottom: 0.25rem; }
    .login-box p  { color: var(--gray); margin-bottom: 1.5rem; }
    .upload-zone  { border: 2px dashed var(--gold); border-radius: var(--radius); padding: 2.5rem 1.5rem; text-align: center; cursor: pointer; position: relative; background: var(--cream); transition: background 0.2s; }
    .upload-zone.drag-over { background: var(--blush); }
    .empty-state  { text-align: center; padding: 3rem; color: var(--gray); }
  </style>
</head>
<body style="background:var(--cream);">

<?php if (!$loggedIn): ?>
<!-- ── LOGIN ──────────────────────────────────────────────────────────────── -->
<div class="login-box">
  <h1><?= htmlspecialchars(BRIDE_NAME) ?> &amp; <?= htmlspecialchars(GROOM_NAME) ?></h1>
  <p>Admin area — enter your password to continue</p>

  <?php if (!empty($loginError)): ?>
    <div class="alert alert-error" style="margin-bottom:1rem;"><?= htmlspecialchars($loginError) ?></div>
  <?php endif; ?>

  <form method="POST" action="admin.php">
    <div class="form-group" style="margin-bottom:1rem;">
      <input type="password" name="password" placeholder="Admin password" required autofocus
             style="width:100%;padding:.75rem 1rem;border:1px solid #ddd;border-radius:var(--radius);font-size:1rem;">
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;">Enter Admin</button>
  </form>
</div>

<?php else: ?>
<!-- ── ADMIN PANEL ────────────────────────────────────────────────────────── -->

<nav class="site-nav">
  <div class="nav-inner">
    <a href="index.php" class="nav-logo"><?= htmlspecialchars(BRIDE_NAME) ?> &amp; <?= htmlspecialchars(GROOM_NAME) ?></a>
    <form method="POST" action="admin.php" style="margin:0;">
      <button type="submit" name="logout" value="1" class="btn btn-outline" style="padding:.4rem 1rem;font-size:.8rem;">Log out</button>
    </form>
  </div>
</nav>

<div class="admin-wrap">

  <div class="admin-header">
    <div class="admin-title">Photo Management</div>
    <div style="color:var(--gray);font-size:.9rem;"><?= count($photos) ?> photo<?= count($photos) !== 1 ? 's' : '' ?> in gallery</div>
  </div>

  <!-- Flash messages -->
  <?php if ($uploadSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem;">✓ <?= $uploadedCount ?> photo<?= $uploadedCount !== 1 ? 's' : '' ?> uploaded successfully.</div>
  <?php endif; ?>
  <?php if ($uploadError): ?>
    <div class="alert alert-error" style="margin-bottom:1.5rem;"><?= htmlspecialchars($uploadError) ?></div>
  <?php endif; ?>
  <?php if ($deleteMsg): ?>
    <div class="alert <?= strpos($deleteMsg, 'Photo') === 0 ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:1.5rem;"><?= htmlspecialchars($deleteMsg) ?></div>
  <?php endif; ?>

  <!-- Upload form -->
  <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow-sm);padding:1.75rem 1.5rem;margin-bottom:2.5rem;">
    <h2 style="font-family:var(--font-heading);font-size:1.4rem;color:var(--dark);margin-bottom:1.25rem;">Upload a Photo</h2>
    <form method="POST" action="admin.php" enctype="multipart/form-data" id="adminUploadForm">
      <div class="upload-zone" id="adminUploadZone">
        <div id="adminUzIcon" style="font-size:2.5rem;margin-bottom:.5rem;">📷</div>
        <strong>Click to select photos — multiple allowed</strong>
        <p style="color:var(--gray);margin:.25rem 0 0;font-size:.85rem;">JPEG · PNG · GIF · WebP — drag &amp; drop OK</p>
        <input type="file" id="adminPhotoInput" name="photos[]" accept="image/*" multiple required
               style="position:absolute;inset:0;opacity:0;cursor:pointer;" onchange="adminHandleSelect(this)">
      </div>
      <div id="adminFilePreview" style="margin-top:.5rem;"></div>
      <button type="submit" class="btn btn-primary" id="adminSubmitBtn" style="margin-top:1.25rem;width:100%;">Upload Photos</button>
    </form>
  </div>

  <!-- Photo grid -->
  <h2 style="font-family:var(--font-heading);font-size:1.4rem;color:var(--dark);margin-bottom:1rem;">Gallery Photos</h2>

  <?php if ($dbError): ?>
    <div class="alert alert-error">Unable to load photos. Check database configuration in db.php.</div>
  <?php elseif (empty($photos)): ?>
    <div class="empty-state">
      <div style="font-size:3rem;">📷</div>
      <p style="margin-top:.5rem;">No photos yet. Upload your first one above.</p>
    </div>
  <?php else: ?>
    <div class="admin-grid">
      <?php foreach ($photos as $photo): ?>
        <div class="admin-photo">
          <img src="uploads/<?= htmlspecialchars($photo['filename']) ?>" alt="Photo <?= $photo['id'] ?>" loading="lazy">
          <form method="POST" action="admin.php"
                onsubmit="return confirm('Delete this photo? This cannot be undone.');">
            <input type="hidden" name="delete_id" value="<?= $photo['id'] ?>">
            <button type="submit" class="btn-delete" title="Delete photo">&times;</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ── VIDEO MANAGEMENT ──────────────────────────────────────── -->
  <div style="border-top:1px solid #e9ddd6;margin-top:3rem;padding-top:3rem;">

    <div class="admin-header" style="margin-bottom:1.5rem;">
      <div class="admin-title">Video Management</div>
      <div style="color:var(--gray);font-size:.9rem;"><?= count($videos) ?> video<?= count($videos) !== 1 ? 's' : '' ?></div>
    </div>

    <?php if ($videoSuccess): ?>
      <div class="alert alert-success" style="margin-bottom:1.5rem;">&#10003; Video uploaded successfully.</div>
    <?php endif; ?>
    <?php if ($videoError): ?>
      <div class="alert alert-error" style="margin-bottom:1.5rem;"><?= htmlspecialchars($videoError) ?></div>
    <?php endif; ?>
    <?php if ($videoDeleteMsg): ?>
      <div class="alert <?= strpos($videoDeleteMsg, 'Video deleted') === 0 ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:1.5rem;"><?= htmlspecialchars($videoDeleteMsg) ?></div>
    <?php endif; ?>
    <?php if ($registerVideoMsg): ?>
      <?php [$rmType, $rmText] = explode(':', $registerVideoMsg, 2); ?>
      <div class="alert <?= $rmType === 'ok' ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:1.5rem;"><?= htmlspecialchars($rmText) ?></div>
    <?php endif; ?>

    <!-- Video upload form -->
    <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow-sm);padding:1.75rem 1.5rem;margin-bottom:2.5rem;">
      <h2 style="font-family:var(--font-heading);font-size:1.4rem;color:var(--dark);margin-bottom:1.25rem;">Upload a Video</h2>
      <p style="color:var(--gray);font-size:.85rem;margin-bottom:1.25rem;">
        Formats: MP4, WebM, MOV &mdash; max 500&thinsp;MB.
        For larger files, upload directly via cPanel File&nbsp;Manager to the <code>videos/</code> folder,
        then use the &ldquo;Add existing file&rdquo; form below.
      </p>
      <form method="POST" action="admin.php" enctype="multipart/form-data" id="videoUploadForm">
        <input type="file" name="video_file" id="videoFileInput" accept="video/mp4,video/webm,video/quicktime,video/*" required
               style="width:100%;padding:.6rem .9rem;border:1px solid #ddd;border-radius:var(--radius);font-size:.9rem;box-sizing:border-box;background:#fafafa;cursor:pointer;"
               onchange="videoHandleSelect(this)">
        <div id="videoFileInfo" style="margin-top:.75rem;font-size:.85rem;color:var(--gray);"></div>
        <button type="submit" class="btn btn-primary" id="videoSubmitBtn" style="margin-top:1.25rem;">Upload Video</button>
      </form>
    </div>

    <!-- Add existing file from server -->
    <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow-sm);padding:1.25rem 1.5rem;margin-bottom:2.5rem;">
      <h3 style="font-family:var(--font-heading);font-size:1.1rem;color:var(--dark);margin-bottom:.75rem;">Add existing file from server</h3>
      <p style="color:var(--gray);font-size:.83rem;margin-bottom:.9rem;">
        If you already uploaded a video to the <code>videos/</code> folder via cPanel File Manager, register it here.
      </p>
      <form method="POST" action="admin.php" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
        <div>
          <label style="display:block;font-size:.83rem;color:var(--dark);margin-bottom:.3rem;">Filename (e.g. ceremony.mp4)</label>
          <input type="text" name="existing_video_filename" placeholder="ceremony.mp4" maxlength="255" required
                 style="padding:.6rem .9rem;border:1px solid #ddd;border-radius:var(--radius);font-size:.9rem;width:260px;">
        </div>
        <button type="submit" name="register_existing_video" value="1" class="btn btn-outline" style="padding:.65rem 1.25rem;">Register</button>
      </form>
    </div>

    <!-- Video grid -->
    <h2 style="font-family:var(--font-heading);font-size:1.4rem;color:var(--dark);margin-bottom:1rem;">Videos</h2>

    <?php if (empty($videos)): ?>
      <div class="empty-state">
        <div style="font-size:3rem;">🎬</div>
        <p style="margin-top:.5rem;">No videos yet. Upload your first one above.</p>
      </div>
    <?php else: ?>
      <?php
        $vMimeMap = ['mp4'=>'video/mp4','webm'=>'video/webm','mov'=>'video/quicktime','avi'=>'video/x-msvideo','ogv'=>'video/ogg'];
      ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem;">
        <?php foreach ($videos as $v): ?>
          <?php $vExt = strtolower(pathinfo($v['filename'], PATHINFO_EXTENSION)); $vType = $vMimeMap[$vExt] ?? 'video/mp4'; ?>
          <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow-sm);overflow:hidden;">
            <video controls preload="metadata" style="width:100%;display:block;max-height:190px;background:#111;">
              <source src="videos/<?= htmlspecialchars($v['filename']) ?>" type="<?= $vType ?>">
            </video>
            <div style="padding:.5rem .75rem;display:flex;justify-content:flex-end;">
              <form method="POST" action="admin.php" onsubmit="return confirm('Delete this video? This cannot be undone.');">
                <input type="hidden" name="delete_video_id" value="<?= $v['id'] ?>">
                <button type="submit" class="btn-delete" title="Delete video">&times;</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

  <div style="margin-top:3rem;text-align:center;">
    <a href="index.php" class="btn btn-outline">← Back to site</a>
  </div>
</div>

<?php endif; ?>

<script>
  function adminHandleSelect(input) {
    var files   = input.files;
    if (!files.length) return;
    var preview = document.getElementById('adminFilePreview');
    var icon    = document.getElementById('adminUzIcon');
    icon.textContent = '📷';
    preview.innerHTML = '';
    var grid = document.createElement('div');
    grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:0.5rem;margin-top:0.75rem;';
    for (var i = 0; i < files.length; i++) {
      (function(file) {
        if (file.type.indexOf('image/') !== 0) return;
        var img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.style.cssText = 'width:100%;aspect-ratio:1;object-fit:cover;border-radius:var(--radius);';
        grid.appendChild(img);
      })(files[i]);
    }
    preview.appendChild(grid);
    document.getElementById('adminSubmitBtn').textContent = 'Upload ' + files.length + ' Photo' + (files.length !== 1 ? 's' : '');
  }

  var zone = document.getElementById('adminUploadZone');
  if (zone) {
    zone.addEventListener('dragover',  function(e){ e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', function(){ zone.classList.remove('drag-over'); });
    zone.addEventListener('drop', function(e){
      e.preventDefault(); zone.classList.remove('drag-over');
      var input = document.getElementById('adminPhotoInput');
      var dt    = e.dataTransfer;
      if (dt.files.length) {
        try { input.files = dt.files; } catch(err) {}
        adminHandleSelect({ files: dt.files });
      }
    });
  }

  async function adminResizeImage(file, maxPx) {
    return new Promise(function(resolve) {
      var img = new Image();
      var url = URL.createObjectURL(file);
      img.onload = function() {
        URL.revokeObjectURL(url);
        var w = img.naturalWidth, h = img.naturalHeight;
        if (w > maxPx || h > maxPx) {
          if (w >= h) { h = Math.round(h * maxPx / w); w = maxPx; }
          else        { w = Math.round(w * maxPx / h); h = maxPx; }
        }
        var canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
        canvas.toBlob(function(blob) {
          resolve(new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), { type: 'image/jpeg' }));
        }, 'image/jpeg', 0.88);
      };
      img.onerror = function() { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  var form = document.getElementById('adminUploadForm');
  if (form) {
    form.addEventListener('submit', async function(e) {
      var input = document.getElementById('adminPhotoInput');
      var btn   = document.getElementById('adminSubmitBtn');
      if (!input.files.length) return;
      e.preventDefault();
      btn.disabled = true;
      var origFiles = Array.from(input.files);
      var resized   = [];
      for (var i = 0; i < origFiles.length; i++) {
        btn.textContent = 'Resizing ' + (i + 1) + ' of ' + origFiles.length + '…';
        resized.push(await adminResizeImage(origFiles[i], 1600));
      }
      try {
        var dt = new DataTransfer();
        resized.forEach(function(f) { dt.items.add(f); });
        input.files = dt.files;
      } catch(err) {}
      btn.textContent = 'Uploading…';
      form.submit();
    });
  }

  function videoHandleSelect(input) {
    var file = input.files[0];
    if (!file) return;
    var info = document.getElementById('videoFileInfo');
    var mb   = (file.size / 1024 / 1024).toFixed(1);
    info.textContent = file.name + ' — ' + mb + ' MB';
    document.getElementById('videoSubmitBtn').textContent = 'Upload Video';
  }

  var vForm = document.getElementById('videoUploadForm');
  if (vForm) {
    vForm.addEventListener('submit', function() {
      var btn = document.getElementById('videoSubmitBtn');
      btn.disabled = true;
      btn.textContent = 'Uploading… (large files may take a while)';
    });
  }
</script>
</body>
</html>
