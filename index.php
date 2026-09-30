<?php
require_once 'db.php';

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
  <link rel="stylesheet" href="style.css?v=3">
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
        <a href="#gallery" class="btn btn-outline">View Gallery</a>
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
            <div class="gallery-item<?= $i >= 8 ? ' gallery-hidden' : '' ?>" onclick="openLightbox('<?= $src ?>')">
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
  <div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
    <img id="lightboxImg" src="" alt="Full size photo" onclick="event.stopPropagation()">
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
      <a href="#wishes">Wishes</a>
    </p>
    <p style="margin-top:1.5rem;font-size:0.72rem;opacity:0.35;">Made with love ♥</p>
  </footer>

  <script>
    function showAllPhotos() {
      document.querySelectorAll('.gallery-hidden').forEach(function(el) { el.style.display = ''; });
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
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeLightbox(); });
  </script>
</body>
</html>
