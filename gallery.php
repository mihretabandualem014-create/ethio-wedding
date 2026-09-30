<?php
require_once 'db.php';

$photos  = [];
$dbError = false;

try {
    $stmt   = getDB()->query('SELECT id, filename FROM photos ORDER BY id DESC');
    $photos = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gallery &mdash; <?= htmlspecialchars(SITE_TITLE) ?></title>
  <meta name="description" content="Guest photo gallery for <?= htmlspecialchars(SITE_TITLE) ?>.">
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
        <li><a href="gallery.php" class="active">Gallery</a></li>
        <li><a href="index.php#wishes">Wishes</a></li>
        <li><a href="upload.php">Upload Photo</a></li>
      </ul>
    </div>
  </nav>

  <!-- PAGE HEADER -->
  <header class="page-header">
    <div class="icon">📷</div>
    <h1>Guest Gallery</h1>
    <p>Beautiful moments captured by you</p>
  </header>

  <!-- GALLERY -->
  <main class="section" style="padding-top:2.5rem;">
    <div class="container">

      <?php if ($dbError): ?>
        <div class="alert alert-error" style="max-width:600px;margin:0 auto 2rem;">
          Unable to load photos. Please check the database configuration in db.php.
        </div>
      <?php elseif (!empty($photos)): ?>
        <p class="text-center" style="color:var(--gray);margin-bottom:2rem;">
          <?= count($photos) ?> photo<?= count($photos) !== 1 ? 's' : '' ?> shared
        </p>
        <div class="gallery-grid">
          <?php foreach ($photos as $photo): ?>
            <?php $src = 'uploads/' . htmlspecialchars($photo['filename']); ?>
            <div class="gallery-item" onclick="openLightbox('<?= $src ?>')">
              <img src="<?= $src ?>" alt="Guest photo" loading="lazy">
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="gallery-empty">
          <div class="icon">📷</div>
          <h3>No photos yet</h3>
          <p>Be the first to share a memory!</p>
          <a href="upload.php" class="btn btn-primary mt-2">Upload a Photo</a>
        </div>
      <?php endif; ?>

      <div class="text-center mt-3">
        <a href="upload.php" class="btn btn-outline">Share Your Photo</a>
      </div>

    </div>
  </main>

  <!-- LIGHTBOX -->
  <div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()" aria-label="Close">&times;</button>
    <img id="lightboxImg" src="" alt="Full size photo" onclick="event.stopPropagation()">
  </div>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="footer-names"><?= htmlspecialchars(BRIDE_NAME) ?> &amp; <?= htmlspecialchars(GROOM_NAME) ?></div>
    <p><?= htmlspecialchars(WEDDING_DATE) ?></p>
    <p style="margin-top:0.75rem;">
      <a href="index.php#wishes">Wishes</a> &nbsp;&middot;&nbsp;
      <a href="upload.php">Upload</a>
    </p>
  </footer>

  <script>
    document.getElementById('navToggle').addEventListener('click', function () {
      document.getElementById('navLinks').classList.toggle('open');
    });

    function openLightbox(src) {
      document.getElementById('lightboxImg').src = src;
      document.getElementById('lightbox').classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
      document.getElementById('lightbox').classList.remove('open');
      document.getElementById('lightboxImg').src = '';
      document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeLightbox();
    });
  </script>
</body>
</html>
