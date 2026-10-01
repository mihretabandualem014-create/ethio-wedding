<?php
require_once 'db.php';

// ── Image resize helper ─────────────────────────────────────────────────────
function resizeAndSave(string $source, string $dest, int $maxWidth, int $quality): bool {
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
            switch ((int)$exif['Orientation']) {
                case 3: $img = imagerotate($img, 180, 0); break;
                case 6: $img = imagerotate($img, -90, 0); break;
                case 8: $img = imagerotate($img,  90, 0); break;
            }
        }
    }
    $w = imagesx($img); $h = imagesy($img);
    if ($w <= $maxWidth) { $r = imagejpeg($img, $dest, $quality); imagedestroy($img); return $r; }
    $ratio = $maxWidth / $w; $newH = (int)round($h * $ratio);
    $canvas = imagecreatetruecolor($maxWidth, $newH);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopyresampled($canvas, $img, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
    $r = imagejpeg($canvas, $dest, $quality);
    imagedestroy($img); imagedestroy($canvas);
    return $r;
}

// ── Handle guest photo upload ───────────────────────────────────────────────
$guestPhotoSuccess = isset($_GET['photo_shared']);
$guestPhotoError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['guest_photo'])) {
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    $file    = $_FILES['guest_photo'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $guestPhotoError = 'Upload failed. Please try again.';
    } elseif ($file['size'] > MAX_FILE_SIZE) {
        $guestPhotoError = 'File too large (max 10 MB).';
    } else {
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info['mime'], $allowed, true)) {
            $guestPhotoError = 'Please upload a JPEG, PNG, GIF or WebP image.';
        } elseif (!is_dir(UPLOAD_DIR) || !is_writable(UPLOAD_DIR)) {
            $guestPhotoError = 'Upload directory not writable.';
        } else {
            $filename = uniqid('guest_', true) . '.jpg';
            $destPath = UPLOAD_DIR . $filename;
            if (!resizeAndSave($file['tmp_name'], $destPath, MAX_IMAGE_WIDTH, JPEG_QUALITY)) {
                $guestPhotoError = 'Could not process the image.';
            } else {
                try {
                    getDB()->prepare('INSERT INTO guest_photos (filename) VALUES (?)')->execute([$filename]);
                    header('Location: index.php?photo_shared=1#share');
                    exit;
                } catch (PDOException $e) {
                    @unlink($destPath);
                    $guestPhotoError = 'Failed to save photo. Please try again.';
                }
            }
        }
    }
}

// ── Fetch guest photos ──────────────────────────────────────────────────────
$guestPhotos = [];
try {
    $guestPhotos = getDB()->query('SELECT filename FROM guest_photos ORDER BY id DESC')->fetchAll();
} catch (PDOException $e) {}

// ── Handle wish submission ──────────────────────────────────────────────────
$wishSuccess = isset($_GET['wished']);
$wishError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wish_name'])) {
    $name    = trim($_POST['wish_name']    ?? '');
    $message = trim($_POST['wish_message'] ?? '');

    if ($name === '' || $message === '') {
        $wishError = 'Please fill in both your name and your message.';
    } elseif (mb_strlen($name) > 100) {
        $wishError = 'Name is too long (max 100 characters).';
    } elseif (mb_strlen($message) > 1000) {
        $wishError = 'Message is too long (max 1,000 characters).';
    } else {
        try {
            $stmt = getDB()->prepare('INSERT INTO wishes (name, message) VALUES (?, ?)');
            $stmt->execute([$name, $message]);
            header('Location: index.php?wished=1#wishes');
            exit;
        } catch (PDOException $e) {
            $wishError = 'Something went wrong. Please try again.';
        }
    }
}

// ── Fetch data ──────────────────────────────────────────────────────────────
$previewPhotos = [];
try {
    $stmt          = getDB()->query('SELECT filename FROM photos ORDER BY id DESC');
    $previewPhotos = $stmt->fetchAll();
} catch (PDOException $e) {}

$wishes = [];
try {
    $stmt   = getDB()->query('SELECT name, message FROM wishes ORDER BY id DESC');
    $wishes = $stmt->fetchAll();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(SITE_TITLE) ?> — Our Wedding</title>
  <meta name="description" content="Join us to celebrate the wedding of <?= htmlspecialchars(BRIDE_NAME) ?> and <?= htmlspecialchars(GROOM_NAME) ?>. Share your wishes and photos.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=Great+Vibes&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=4">
</head>
<body>

  <!-- NAVIGATION -->
  <nav class="site-nav on-hero" id="siteNav">
    <div class="nav-inner">
      <span class="nav-logo"><?= mb_substr(BRIDE_NAME,0,1) ?> &amp; <?= mb_substr(GROOM_NAME,0,1) ?></span>
      <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
      <ul class="nav-links" id="navLinks">
        <li><a href="index.php" class="active">Home</a></li>
        <li><a href="#gallery">Gallery</a></li>
        <li><a href="#videos">Videos</a></li>
        <li><a href="#wishes">Wishes</a></li>
        <li><a href="#share">Share Photo</a></li>
      </ul>
    </div>
  </nav>

  <!-- HERO -->
  <section class="hero" id="hero" style="background-image:url('hero-image.jpg');">
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <p class="hero-eyebrow">We Are Getting Married</p>
      <h1 class="couple-names">
        <?= htmlspecialchars(BRIDE_NAME) ?>
        <span class="couple-amp">and</span>
        <?= htmlspecialchars(GROOM_NAME) ?>
      </h1>
      <div class="ornament"><span>♥</span></div>
      <p class="hero-date">24 &nbsp;&middot;&nbsp; 01 &nbsp;&middot;&nbsp; 2018</p>
      <p class="hero-tagline">Happily Ever After</p>
      <div class="hero-cta">
        <a href="#wishes" class="btn btn-primary">Leave a Wish &nbsp;♥</a>
        <a href="#share" class="btn btn-outline">Share a Moment</a>
      </div>
    </div>
    <div class="hero-scroll" onclick="document.getElementById('gallery').scrollIntoView({behavior:'smooth'})">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M6 9l6 6 6-6"/>
      </svg>
    </div>
  </section>

  <!-- GALLERY -->
  <section class="section gallery-section" id="gallery">
    <div class="container">
      <div class="section-title">
        <h2>Gallery</h2>
        <div class="ornament"><span>✦</span></div>
        <p class="sub">Moments captured by our guests</p>
      </div>

      <?php if (!empty($previewPhotos)): ?>
        <div class="gallery-grid" id="galleryGrid">
          <?php foreach ($previewPhotos as $i => $photo): ?>
            <?php $src = 'uploads/' . htmlspecialchars($photo['filename']); ?>
            <div class="gallery-item<?= $i >= 8 ? ' gallery-hidden' : '' ?>" onclick="openLightbox(<?= $i ?>, 'admin')">
              <img src="<?= $src ?>" alt="Wedding photo" loading="lazy">
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($previewPhotos) > 8): ?>
        <div class="text-center mt-3">
          <button class="btn btn-outline" id="viewMoreBtn" onclick="showAllPhotos()">View More Photos</button>
        </div>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-center" style="color:var(--muted);font-style:italic;">Photos coming soon.</p>
      <?php endif; ?>
    </div>
  </section>

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
      <button class="lb-tool-btn" onclick="lbZoomOut()">&#8722; Zoom</button>
      <button class="lb-tool-btn" onclick="lbZoomIn()">&#43; Zoom</button>
      <a id="lbDownload" href="" download class="lb-tool-btn">&#8595; Download</a>
    </div>
  </div>

  <!-- VIDEOS -->
  <section class="section video-section" id="videos">
    <div class="container">
      <div class="section-title">
        <h2>Our Videos</h2>
        <div class="ornament"><span>✦</span></div>
        <p class="sub">Precious moments on film</p>
      </div>
      <div class="video-grid">

        <!-- VIDEO 1 — replace src with your file, e.g. "videos/ceremony.mp4"
             Or for YouTube: replace the <video> block with:
             <iframe src="https://www.youtube.com/embed/VIDEO_ID" allowfullscreen></iframe> -->
        <div class="video-item">
          <video controls preload="metadata">
            <source src="videos/video1.mp4" type="video/mp4">
          </video>
          <p class="video-caption">Ceremony</p>
        </div>

        <!-- VIDEO 2 -->
        <div class="video-item">
          <video controls preload="metadata">
            <source src="videos/video2.mp4" type="video/mp4">
          </video>
          <p class="video-caption">Reception</p>
        </div>

        <!-- VIDEO 3 -->
        <div class="video-item">
          <video controls preload="metadata">
            <source src="videos/video3.mp4" type="video/mp4">
          </video>
          <p class="video-caption">First Dance</p>
        </div>

        <!-- VIDEO 4 — delete this block if you only have 3 videos -->
        <div class="video-item">
          <video controls preload="metadata">
            <source src="videos/video4.mp4" type="video/mp4">
          </video>
          <p class="video-caption">Highlights</p>
        </div>

      </div>
    </div>
  </section>

  <!-- SHARE YOUR MOMENTS -->
  <section class="section share-section" id="share">
    <div class="container">
      <div class="section-title">
        <h2>Share Your Moments</h2>
        <div class="ornament"><span>📷</span></div>
        <p class="sub">Take a photo or choose from your gallery</p>
      </div>

      <?php if ($guestPhotoSuccess): ?>
        <div class="alert alert-success" style="max-width:480px;margin:0 auto 2rem;">
          ♥ Your photo has been shared! Thank you.
        </div>
      <?php endif; ?>

      <?php if ($guestPhotoError): ?>
        <div class="alert alert-error" style="max-width:480px;margin:0 auto 2rem;">
          <?= htmlspecialchars($guestPhotoError) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="index.php#share" enctype="multipart/form-data" class="share-form">
        <label class="share-btn" for="guestPhotoInput">
          <span class="share-icon">📷</span>
          <span>Choose Photo or Take a Picture</span>
          <input type="file" id="guestPhotoInput" name="guest_photo" accept="image/*" required
                 onchange="previewGuestPhoto(this)" style="display:none;">
        </label>
        <div id="guestPreview"></div>
        <button type="submit" class="btn btn-primary" id="guestSubmitBtn" style="display:none;width:100%;max-width:320px;margin:1rem auto 0;">
          Share This Photo &nbsp;♥
        </button>
      </form>

      <?php if (!empty($guestPhotos)): ?>
        <div class="gallery-grid mt-3">
          <?php foreach ($guestPhotos as $i => $photo): ?>
            <?php $src = 'uploads/' . htmlspecialchars($photo['filename']); ?>
            <div class="gallery-item<?= $i >= 8 ? ' gallery-hidden' : '' ?>" onclick="openLightbox(<?= $i ?>, 'guest')">
              <img src="<?= $src ?>" alt="Guest photo" loading="lazy">
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($guestPhotos) > 8): ?>
          <div class="text-center mt-3">
            <button class="btn btn-outline" id="viewMoreGuestBtn" onclick="showAllGuestPhotos()">View More</button>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- WISHES -->
  <section class="section wishes-section" id="wishes">
    <div class="container">
      <div class="section-title">
        <h2>Send Your Wishes</h2>
        <div class="ornament"><span>♥</span></div>
        <p class="sub">Share your love and blessings with us</p>
      </div>

      <!-- Form -->
      <div class="wish-form-wrap">
        <?php if ($wishSuccess): ?>
          <div class="alert alert-success">♥ Thank you! Your wish has been received.</div>
          <p class="text-center" style="color:var(--muted);font-family:var(--font-heading);font-style:italic;">
            Scroll down to see all wishes ↓
          </p>
        <?php else: ?>
          <?php if ($wishError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($wishError) ?></div>
          <?php endif; ?>
          <form method="POST" action="index.php#wishes">
            <div class="form-group">
              <label for="wish_name">Your Name</label>
              <input type="text" id="wish_name" name="wish_name"
                     placeholder="Enter your name" maxlength="100" required
                     value="<?= htmlspecialchars($_POST['wish_name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="wish_message">Your Message</label>
              <textarea id="wish_message" name="wish_message"
                        placeholder="Write your heartfelt wish for the couple..."
                        maxlength="1000" rows="4" required><?= htmlspecialchars($_POST['wish_message'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">
              Send Your Wish &nbsp;♥
            </button>
          </form>
        <?php endif; ?>
      </div>

      <!-- All wishes displayed below -->
      <?php if (!empty($wishes)): ?>
        <div class="wishes-count">
          <?= count($wishes) ?> heartfelt wish<?= count($wishes) !== 1 ? 'es' : '' ?>
        </div>
        <div class="wishes-list">
          <?php foreach ($wishes as $wish): ?>
            <div class="wish-card">
              <div class="wish-name"><?= htmlspecialchars($wish['name']) ?></div>
              <div class="wish-message">&ldquo;<?= htmlspecialchars($wish['message']) ?>&rdquo;</div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php elseif (!$wishSuccess): ?>
        <p class="text-center mt-2" style="color:var(--muted);font-family:var(--font-heading);font-style:italic;">
          No wishes yet — be the first!
        </p>
      <?php endif; ?>

    </div>
  </section>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="footer-names"><?= htmlspecialchars(BRIDE_NAME) ?> &amp; <?= htmlspecialchars(GROOM_NAME) ?></div>
    <div class="footer-heart">♥ &nbsp; ♥ &nbsp; ♥</div>
    <p><?= htmlspecialchars(WEDDING_DATE) ?></p>
    <p>
      <a href="#gallery">Gallery</a> &nbsp;&middot;&nbsp;
      <a href="#wishes">Wishes</a> &nbsp;&middot;&nbsp;
      <a href="#share">Share Photo</a>
    </p>
    <p style="margin-top:1.5rem;font-size:0.72rem;opacity:0.35;">Made with love ♥</p>
  </footer>

  <script>
    function previewGuestPhoto(input) {
      var file = input.files[0];
      if (!file) return;
      var preview = document.getElementById('guestPreview');
      var url = URL.createObjectURL(file);
      preview.innerHTML = '<img src="' + url + '" style="max-width:100%;max-height:220px;border-radius:var(--radius);margin:1rem auto;display:block;">';
      document.getElementById('guestSubmitBtn').style.display = 'block';
      document.getElementById('guestSubmitBtn').addEventListener('click', function() {
        this.textContent = 'Uploading…'; this.disabled = true;
      }, { once: true });
    }
    function showAllGuestPhotos() {
      document.querySelectorAll('#share .gallery-hidden').forEach(function(el) { el.style.display = ''; });
      var btn = document.getElementById('viewMoreGuestBtn');
      if (btn) btn.style.display = 'none';
    }
    function showAllPhotos() {
      document.querySelectorAll('#gallery .gallery-hidden').forEach(function(el) { el.style.display = ''; });
      document.getElementById('viewMoreBtn').style.display = 'none';
    }

    document.getElementById('navToggle').addEventListener('click', function () {
      document.getElementById('navLinks').classList.toggle('open');
    });

    var nav  = document.getElementById('siteNav');
    var hero = document.getElementById('hero');
    function updateNav() {
      nav.classList.toggle('on-hero', hero.getBoundingClientRect().bottom > 68);
    }
    window.addEventListener('scroll', updateNav, { passive: true });
    updateNav();

    var lbAdmin = [<?php foreach ($previewPhotos as $p) { echo '"uploads/' . addslashes($p['filename']) . '",'; } ?>];
    var lbGuest = [<?php foreach ($guestPhotos as $p) { echo '"uploads/' . addslashes($p['filename']) . '",'; } ?>];
    var lbPhotos = [], lbIndex = 0, lbZoom = 1;

    function openLightbox(index, group) {
      lbPhotos = group === 'guest' ? lbGuest : lbAdmin;
      lbIndex = index; lbZoom = 1;
      document.getElementById('lightbox').classList.add('open');
      document.body.style.overflow = 'hidden';
      _lbRender();
    }
    function closeLightbox() {
      document.getElementById('lightbox').classList.remove('open');
      document.getElementById('lightboxImg').src = '';
      document.body.style.overflow = '';
      lbZoom = 1; _lbApplyZoom();
    }
    function shiftPhoto(dir) {
      lbIndex = (lbIndex + dir + lbPhotos.length) % lbPhotos.length;
      lbZoom = 1; _lbRender();
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
        img.style.maxWidth = 'none'; img.style.maxHeight = 'none';
        img.style.width = (88 * lbZoom) + 'vw'; img.style.height = 'auto';
      } else {
        img.style.maxWidth = '88vw'; img.style.maxHeight = '76vh';
        img.style.width = ''; img.style.height = '';
      }
      if (wrap) wrap.classList.toggle('zoomed', lbZoom > 1);
    }
    var lbWrap = document.getElementById('lbImgWrap');
    if (lbWrap) lbWrap.addEventListener('click', function(e) {
      e.stopPropagation();
      if (lbZoom === 1) lbZoomIn(); else { lbZoom = 1; _lbApplyZoom(); }
    });
    document.getElementById('lightbox').addEventListener('click', function(e) {
      if (e.target === this) closeLightbox();
    });
    document.addEventListener('keydown', function(e) {
      if (!document.getElementById('lightbox').classList.contains('open')) return;
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowLeft') shiftPhoto(-1);
      if (e.key === 'ArrowRight') shiftPhoto(1);
    });
    var lbTouchX = null;
    document.getElementById('lightbox').addEventListener('touchstart', function(e) {
      lbTouchX = e.touches[0].clientX;
    }, { passive: true });
    document.getElementById('lightbox').addEventListener('touchend', function(e) {
      if (lbTouchX === null) return;
      var dx = e.changedTouches[0].clientX - lbTouchX;
      if (Math.abs(dx) > 50) shiftPhoto(dx < 0 ? 1 : -1);
      lbTouchX = null;
    }, { passive: true });
  </script>
</body>
</html>
