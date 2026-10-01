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
  <link rel="stylesheet" href="style.css?v=4">
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
          <?php foreach ($photos as $i => $photo): ?>
            <?php $src = 'uploads/' . htmlspecialchars($photo['filename']); ?>
            <div class="gallery-item" onclick="openLightbox(<?= $i ?>)">
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
  <div class="lightbox" id="lightbox">
    <button class="lightbox-close" onclick="closeLightbox()" aria-label="Close">&times;</button>
    <span class="lb-counter" id="lbCounter"></span>

    <button class="lb-nav lb-prev" onclick="shiftPhoto(-1)" aria-label="Previous">&#8249;</button>

    <div class="lb-img-wrap" id="lbImgWrap">
      <img id="lightboxImg" src="" alt="Full size photo">
    </div>

    <button class="lb-nav lb-next" onclick="shiftPhoto(1)" aria-label="Next">&#8250;</button>

    <div class="lb-toolbar">
      <button class="lb-tool-btn" onclick="lbZoomOut()" title="Zoom out">&#8722; Zoom</button>
      <button class="lb-tool-btn" onclick="lbZoomIn()" title="Zoom in">&#43; Zoom</button>
      <a id="lbDownload" href="" download class="lb-tool-btn" title="Download">&#8595; Download</a>
    </div>
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

    // Build photo list from PHP
    var lbPhotos = [<?php foreach ($photos as $p) { echo '"uploads/' . addslashes($p['filename']) . '",'; } ?>];
    var lbIndex  = 0;
    var lbZoom   = 1;

    function openLightbox(index) {
      lbIndex = index;
      lbZoom  = 1;
      document.getElementById('lightbox').classList.add('open');
      document.body.style.overflow = 'hidden';
      _lbRender();
    }

    function closeLightbox() {
      document.getElementById('lightbox').classList.remove('open');
      document.getElementById('lightboxImg').src = '';
      document.body.style.overflow = '';
      lbZoom = 1;
      _lbApplyZoom();
    }

    function shiftPhoto(dir) {
      lbIndex = (lbIndex + dir + lbPhotos.length) % lbPhotos.length;
      lbZoom  = 1;
      _lbRender();
    }

    function _lbRender() {
      var src = lbPhotos[lbIndex];
      document.getElementById('lightboxImg').src = src;
      var counter = document.getElementById('lbCounter');
      if (counter) counter.textContent = (lbIndex + 1) + ' / ' + lbPhotos.length;
      var dl = document.getElementById('lbDownload');
      if (dl) { dl.href = src; dl.download = src.split('/').pop(); }
      _lbApplyZoom();
    }

    function lbZoomIn()  { lbZoom = Math.min(lbZoom + 0.5, 4); _lbApplyZoom(); }
    function lbZoomOut() { lbZoom = Math.max(lbZoom - 0.5, 1); _lbApplyZoom(); }

    function _lbApplyZoom() {
      var img  = document.getElementById('lightboxImg');
      var wrap = document.getElementById('lbImgWrap');
      if (!img) return;
      if (lbZoom > 1) {
        img.style.maxWidth  = 'none';
        img.style.maxHeight = 'none';
        img.style.width     = (88 * lbZoom) + 'vw';
        img.style.height    = 'auto';
      } else {
        img.style.maxWidth  = '88vw';
        img.style.maxHeight = '76vh';
        img.style.width     = '';
        img.style.height    = '';
      }
      if (wrap) wrap.classList.toggle('zoomed', lbZoom > 1);
    }

    var lbWrap = document.getElementById('lbImgWrap');
    if (lbWrap) lbWrap.addEventListener('click', function (e) {
      e.stopPropagation();
      if (lbZoom === 1) lbZoomIn(); else { lbZoom = 1; _lbApplyZoom(); }
    });

    // Close on backdrop click
    document.getElementById('lightbox').addEventListener('click', function (e) {
      if (e.target === this) closeLightbox();
    });

    // Keyboard navigation
    document.addEventListener('keydown', function (e) {
      if (!document.getElementById('lightbox').classList.contains('open')) return;
      if (e.key === 'Escape')      closeLightbox();
      if (e.key === 'ArrowLeft')   shiftPhoto(-1);
      if (e.key === 'ArrowRight')  shiftPhoto(1);
      if (e.key === '+')           lbZoomIn();
      if (e.key === '-')           lbZoomOut();
    });

    // Swipe support for mobile
    var lbTouchX = null;
    document.getElementById('lightbox').addEventListener('touchstart', function (e) {
      lbTouchX = e.touches[0].clientX;
    }, { passive: true });
    document.getElementById('lightbox').addEventListener('touchend', function (e) {
      if (lbTouchX === null) return;
      var dx = e.changedTouches[0].clientX - lbTouchX;
      if (Math.abs(dx) > 50) shiftPhoto(dx < 0 ? 1 : -1);
      lbTouchX = null;
    }, { passive: true });
  </script>
</body>
</html>
