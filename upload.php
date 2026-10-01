<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}

$uploadedFiles = [];
$errors        = [];

$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

function resizeAndSave(string $source, string $dest, int $maxWidth, int $quality): bool
{
    $info = @getimagesize($source);
    if (!$info) {
        return false;
    }

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($source); break;
        case 'image/png':  $img = @imagecreatefrompng($source);  break;
        case 'image/gif':  $img = @imagecreatefromgif($source);  break;
        case 'image/webp': $img = @imagecreatefromwebp($source); break;
        default:           return false;
    }
    if (!$img) {
        return false;
    }

    // Fix EXIF rotation so portrait photos display upright
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($source);
        if (!empty($exif['Orientation'])) {
            switch ((int) $exif['Orientation']) {
                case 3: $img = imagerotate($img, 180, 0);  break;
                case 6: $img = imagerotate($img, -90, 0);  break;
                case 8: $img = imagerotate($img,  90, 0);  break;
            }
        }
    }

    $w = imagesx($img);
    $h = imagesy($img);

    // No resize needed — just re-encode as JPEG
    if ($w <= $maxWidth) {
        $result = imagejpeg($img, $dest, $quality);
        imagedestroy($img);
        return $result;
    }

    $ratio  = $maxWidth / $w;
    $newH   = (int) round($h * $ratio);
    $canvas = imagecreatetruecolor($maxWidth, $newH);

    // Fill with white (handles transparency in PNGs)
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);

    imagecopyresampled($canvas, $img, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
    $result = imagejpeg($canvas, $dest, $quality);

    imagedestroy($img);
    imagedestroy($canvas);
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    $files = $_FILES['photos'];
    $count = count($files['name']);

    if (!is_dir(UPLOAD_DIR) || !is_writable(UPLOAD_DIR)) {
        $errors[] = 'The uploads directory is missing or not writable.';
    } else {
        for ($i = 0; $i < $count; $i++) {
            $errorCode = $files['error'][$i];
            $tmpName   = $files['tmp_name'][$i];
            $size      = $files['size'][$i];
            $origName  = $files['name'][$i];

            if ($errorCode === UPLOAD_ERR_NO_FILE) continue;

            if ($errorCode !== UPLOAD_ERR_OK) {
                $errors[] = htmlspecialchars($origName) . ': upload error (code ' . $errorCode . ').';
                continue;
            }
            if ($size > MAX_FILE_SIZE) {
                $errors[] = htmlspecialchars($origName) . ': file too large (max 10 MB).';
                continue;
            }
            $info = @getimagesize($tmpName);
            if (!$info || !in_array($info['mime'], $allowedMimes, true)) {
                $errors[] = htmlspecialchars($origName) . ': not a valid image.';
                continue;
            }
            $filename = uniqid('photo_', true) . '.jpg';
            $destPath = UPLOAD_DIR . $filename;
            if (!resizeAndSave($tmpName, $destPath, MAX_IMAGE_WIDTH, JPEG_QUALITY)) {
                $errors[] = htmlspecialchars($origName) . ': could not process image.';
                continue;
            }
            try {
                getDB()->prepare('INSERT INTO photos (filename) VALUES (?)')->execute([$filename]);
                $uploadedFiles[] = $filename;
            } catch (PDOException $e) {
                @unlink($destPath);
                $errors[] = htmlspecialchars($origName) . ': failed to save.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Upload Photo &mdash; <?= htmlspecialchars(SITE_TITLE) ?></title>
  <meta name="description" content="Upload your photos to the guest gallery for <?= htmlspecialchars(SITE_TITLE) ?>.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=Great+Vibes&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=3">
</head>
<body>

  <!-- NAVIGATION -->
  <nav class="site-nav">
    <div class="nav-inner">
      <a href="index.php" class="nav-logo">
        <?= htmlspecialchars(BRIDE_NAME) ?> &amp; <?= htmlspecialchars(GROOM_NAME) ?>
      </a>
      <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
      <ul class="nav-links" id="navLinks">
        <li><a href="index.php">Home</a></li>
        <li><a href="gallery.php">Gallery</a></li>
        <li><a href="index.php#wishes">Wishes</a></li>
        <li><a href="upload.php" class="active">Upload Photo</a></li>
      </ul>
    </div>
  </nav>

  <!-- PAGE HEADER -->
  <header class="page-header">
    <div class="icon">📷</div>
    <h1>Share a Photo</h1>
    <p>Upload your moments to our shared gallery</p>
  </header>

  <main class="section" style="padding-top:2.5rem;">
    <div class="container">
      <div class="form-container">

        <?php if (!empty($uploadedFiles)): ?>

          <div class="alert alert-success">
            &#10003; <?= count($uploadedFiles) ?> photo<?= count($uploadedFiles) !== 1 ? 's' : '' ?> uploaded successfully!
          </div>

          <?php if (!empty($errors)): ?>
            <div class="alert alert-error" style="margin-top:0.75rem;">
              <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:0.75rem;margin:1.5rem 0;">
            <?php foreach ($uploadedFiles as $f): ?>
              <img src="uploads/<?= htmlspecialchars($f) ?>" alt="Uploaded photo"
                   style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:var(--radius);">
            <?php endforeach; ?>
          </div>

          <div style="display:flex;gap:1rem;flex-wrap:wrap;justify-content:center;">
            <a href="index.php#gallery" class="btn btn-primary">View Gallery</a>
            <a href="upload.php" class="btn btn-outline">Upload More</a>
          </div>

        <?php else: ?>

          <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
              <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="upload.php" enctype="multipart/form-data" id="uploadForm">
            <div class="form-group">
              <div class="upload-zone" id="uploadZone">
                <div class="uz-icon" id="uzIcon">📷</div>
                <strong>Click to select photos</strong>
                <p>or drag &amp; drop here &mdash; multiple allowed</p>
                <p class="uz-hint">JPEG &middot; PNG &middot; GIF &middot; WebP &mdash; max 10 MB each</p>
                <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple required
                       style="position:absolute;inset:0;opacity:0;cursor:pointer;"
                       onchange="handleSelect(this)">
              </div>
              <div class="file-preview" id="filePreview"></div>
            </div>

            <button type="submit" class="btn btn-gold" style="width:100%;" id="submitBtn">
              Upload Photos
            </button>
          </form>

        <?php endif; ?>

      </div>

      <p class="text-center mt-2" style="color:var(--gray);font-size:0.85rem;">
        All photos appear in the <a href="gallery.php">Guest Gallery</a> immediately after upload.
      </p>
    </div>
  </main>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="footer-names"><?= htmlspecialchars(BRIDE_NAME) ?> &amp; <?= htmlspecialchars(GROOM_NAME) ?></div>
    <p><?= htmlspecialchars(WEDDING_DATE) ?></p>
    <p style="margin-top:0.75rem;">
      <a href="gallery.php">Gallery</a> &nbsp;&middot;&nbsp;
      <a href="index.php#wishes">Wishes</a>
    </p>
  </footer>

  <script>
    document.getElementById('navToggle').addEventListener('click', function () {
      document.getElementById('navLinks').classList.toggle('open');
    });

    function handleSelect(input) {
      var files   = input.files;
      if (!files.length) return;
      var preview = document.getElementById('filePreview');
      var icon    = document.getElementById('uzIcon');
      icon.textContent = '📷';
      preview.innerHTML = '';
      var grid = document.createElement('div');
      grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:0.5rem;margin-top:0.75rem;';
      for (var i = 0; i < files.length; i++) {
        (function(file) {
          if (file.type.indexOf('image/') !== 0) return;
          var url = URL.createObjectURL(file);
          var img = document.createElement('img');
          img.src = url;
          img.style.cssText = 'width:100%;aspect-ratio:1;object-fit:cover;border-radius:var(--radius);';
          grid.appendChild(img);
        })(files[i]);
      }
      preview.appendChild(grid);
      document.getElementById('submitBtn').textContent = 'Upload ' + files.length + ' Photo' + (files.length !== 1 ? 's' : '');
    }

    // Drag & drop
    var zone = document.getElementById('uploadZone');
    if (zone) {
      zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.classList.add('drag-over');
      });
      zone.addEventListener('dragleave', function () {
        zone.classList.remove('drag-over');
      });
      zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('drag-over');
        var input = document.getElementById('photoInput');
        if (e.dataTransfer.files.length) {
          // DataTransfer sets the files — trigger our handler manually
          var dt = e.dataTransfer;
          try { input.files = dt.files; } catch (err) { /* Firefox fallback */ }
          handleSelect({ files: dt.files });
        }
      });
    }

    // Disable submit button while uploading to prevent double-submit
    var form = document.getElementById('uploadForm');
    if (form) {
      form.addEventListener('submit', function () {
        var btn = document.getElementById('submitBtn');
        btn.textContent = 'Uploading…';
        btn.disabled    = true;
      });
    }
  </script>
</body>
</html>
