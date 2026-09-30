<?php
require_once 'db.php';

$success      = false;
$error        = '';
$uploadedFile = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file      = $_FILES['photo'] ?? null;
    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if (!$file || $errorCode !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        ];
        $error = $uploadErrors[$errorCode] ?? 'Upload failed. Please try again.';

    } elseif ($file['size'] > MAX_FILE_SIZE) {
        $error = 'File is too large. Maximum allowed size is 10 MB.';

    } else {
        // Validate by actual image content, not just extension
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info['mime'], $allowedMimes, true)) {
            $error = 'Invalid file. Please upload a JPEG, PNG, GIF, or WebP image.';
        } elseif (!is_dir(UPLOAD_DIR) || !is_writable(UPLOAD_DIR)) {
            $error = 'The uploads directory is missing or not writable. Please contact the site administrator.';
        } else {
            $filename = uniqid('photo_', true) . '.jpg'; // always save as JPEG
            $destPath = UPLOAD_DIR . $filename;

            if (!resizeAndSave($file['tmp_name'], $destPath, MAX_IMAGE_WIDTH, JPEG_QUALITY)) {
                $error = 'Failed to process the image. Please try another photo.';
            } else {
                try {
                    $stmt = getDB()->prepare('INSERT INTO photos (filename) VALUES (?)');
                    $stmt->execute([$filename]);
                    $success      = true;
                    $uploadedFile = $filename;
                } catch (PDOException $e) {
                    @unlink($destPath); // roll back the saved file
                    $error = 'Failed to save the photo. Please try again.';
                }
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

        <?php if ($success): ?>

          <div class="alert alert-success">
            &#10003; Your photo has been uploaded successfully! Thank you for sharing this moment.
          </div>

          <?php if ($uploadedFile): ?>
            <div style="text-align:center;margin-bottom:1.5rem;">
              <img src="uploads/<?= htmlspecialchars($uploadedFile) ?>"
                   alt="Your uploaded photo"
                   style="max-width:100%;border-radius:var(--radius);box-shadow:var(--shadow-md);">
            </div>
          <?php endif; ?>

          <div style="display:flex;gap:1rem;flex-wrap:wrap;justify-content:center;">
            <a href="gallery.php" class="btn btn-primary">View Gallery</a>
            <a href="upload.php"  class="btn btn-outline">Upload Another</a>
          </div>

        <?php else: ?>

          <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="POST" action="upload.php" enctype="multipart/form-data" id="uploadForm">
            <div class="form-group">
              <div class="upload-zone" id="uploadZone">
                <div class="uz-icon" id="uzIcon">📷</div>
                <strong>Click to select a photo</strong>
                <p>or drag &amp; drop here</p>
                <p class="uz-hint">JPEG &middot; PNG &middot; GIF &middot; WebP &mdash; max 10 MB</p>
                <input type="file" id="photoInput" name="photo" accept="image/*" required
                       style="position:absolute;inset:0;opacity:0;cursor:pointer;"
                       onchange="handleSelect(this)">
              </div>
              <div class="file-preview" id="filePreview"></div>
            </div>

            <button type="submit" class="btn btn-gold" style="width:100%;" id="submitBtn">
              Upload Photo
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
      var file = input.files[0];
      if (!file) return;

      var preview = document.getElementById('filePreview');
      var sizeKB  = (file.size / 1024).toFixed(0);
      preview.textContent = '📎 ' + file.name + ' (' + sizeKB + ' KB)';

      if (file.type.indexOf('image/') === 0) {
        var url  = URL.createObjectURL(file);
        var icon = document.getElementById('uzIcon');
        icon.innerHTML = '<img src="' + url + '" style="max-width:100%;max-height:160px;'
          + 'border-radius:var(--radius);margin:0 auto 0.5rem;display:block;" alt="Preview">';
      }
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
