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

if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file      = $_FILES['photo'];
    $errorCode = $file['error'];

    if ($errorCode !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        ];
        $uploadError = $msgs[$errorCode] ?? 'Upload failed.';
    } elseif ($file['size'] > MAX_FILE_SIZE) {
        $uploadError = 'File is too large. Maximum 10 MB.';
    } else {
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info['mime'], $allowedMimes, true)) {
            $uploadError = 'Invalid file. Please upload a JPEG, PNG, GIF, or WebP image.';
        } elseif (!is_dir(UPLOAD_DIR) || !is_writable(UPLOAD_DIR)) {
            $uploadError = 'uploads/ directory is missing or not writable.';
        } else {
            $filename = uniqid('admin_', true) . '.jpg';
            $destPath = UPLOAD_DIR . $filename;
            if (!resizeAndSave($file['tmp_name'], $destPath, MAX_IMAGE_WIDTH, JPEG_QUALITY)) {
                $uploadError = 'Failed to process the image.';
            } else {
                try {
                    $stmt = getDB()->prepare('INSERT INTO photos (filename) VALUES (?)');
                    $stmt->execute([$filename]);
                    $uploadSuccess = true;
                } catch (PDOException $e) {
                    @unlink($destPath);
                    $uploadError = 'Failed to save to database.';
                }
            }
        }
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
  <link rel="stylesheet" href="style.css?v=3">
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
    <div class="alert alert-success" style="margin-bottom:1.5rem;">✓ Photo uploaded successfully.</div>
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
        <strong>Click to select or drag &amp; drop</strong>
        <p style="color:var(--gray);margin:.25rem 0 0;font-size:.85rem;">JPEG · PNG · GIF · WebP — max 10 MB</p>
        <input type="file" id="adminPhotoInput" name="photo" accept="image/*" required
               style="position:absolute;inset:0;opacity:0;cursor:pointer;" onchange="adminHandleSelect(this)">
      </div>
      <div id="adminFilePreview" style="margin-top:.5rem;font-size:.85rem;color:var(--gray);"></div>
      <button type="submit" class="btn btn-primary" id="adminSubmitBtn" style="margin-top:1.25rem;width:100%;">Upload Photo</button>
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

  <div style="margin-top:3rem;text-align:center;">
    <a href="index.php" class="btn btn-outline">← Back to site</a>
  </div>
</div>

<?php endif; ?>

<script>
  function adminHandleSelect(input) {
    var file = input.files[0];
    if (!file) return;
    var preview = document.getElementById('adminFilePreview');
    preview.textContent = '📎 ' + file.name + ' (' + (file.size/1024).toFixed(0) + ' KB)';
    if (file.type.indexOf('image/') === 0) {
      var url  = URL.createObjectURL(file);
      var icon = document.getElementById('adminUzIcon');
      icon.innerHTML = '<img src="' + url + '" style="max-width:100%;max-height:140px;border-radius:var(--radius);display:block;margin:0 auto .5rem;" alt="Preview">';
    }
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

  var form = document.getElementById('adminUploadForm');
  if (form) {
    form.addEventListener('submit', function(){
      var btn = document.getElementById('adminSubmitBtn');
      btn.textContent = 'Uploading…';
      btn.disabled = true;
    });
  }
</script>
</body>
</html>
