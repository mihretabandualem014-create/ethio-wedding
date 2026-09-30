<?php
require_once 'db.php';

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $message === '') {
        $error = 'Please fill in both your name and your message.';
    } elseif (mb_strlen($name) > 100) {
        $error = 'Name is too long (max 100 characters).';
    } elseif (mb_strlen($message) > 1000) {
        $error = 'Message is too long (max 1,000 characters).';
    } else {
        try {
            $stmt = getDB()->prepare('INSERT INTO wishes (name, message) VALUES (?, ?)');
            $stmt->execute([$name, $message]);
            $success = true;
        } catch (PDOException $e) {
            $error = 'Something went wrong saving your wish. Please try again.';
        }
    }
}

$wishes  = [];
$dbError = false;
try {
    $stmt   = getDB()->query('SELECT name, message FROM wishes ORDER BY id DESC');
    $wishes = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Wishes &mdash; <?= htmlspecialchars(SITE_TITLE) ?></title>
  <meta name="description" content="Leave your wishes and blessings for <?= htmlspecialchars(SITE_TITLE) ?>.">
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
        <li><a href="wishes.php" class="active">Wishes</a></li>
        <li><a href="upload.php">Upload Photo</a></li>
      </ul>
    </div>
  </nav>

  <!-- PAGE HEADER -->
  <header class="page-header">
    <div class="icon">♥</div>
    <h1>Leave a Wish</h1>
    <p>Share your love and blessings with us</p>
  </header>

  <main class="section" style="padding-top:2.5rem;">
    <div class="container">

      <!-- WISH FORM -->
      <div class="form-container mb-2">
        <?php if ($success): ?>
          <div class="alert alert-success">
            ♥ Thank you for your beautiful wish! It means the world to us.
          </div>
          <div class="text-center">
            <a href="wishes.php" class="btn btn-primary">See All Wishes</a>
          </div>
        <?php else: ?>
          <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="POST" action="wishes.php">
            <div class="form-group">
              <label for="name">Your Name</label>
              <input type="text" id="name" name="name"
                     placeholder="Enter your name" maxlength="100" required
                     value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="message">Your Wish / Message</label>
              <textarea id="message" name="message"
                        placeholder="Write your heartfelt wish for the couple..."
                        maxlength="1000" rows="5" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">
              Send Your Wish ♥
            </button>
          </form>
        <?php endif; ?>
      </div>

      <!-- WISHES LIST -->
      <?php if ($dbError): ?>
        <div class="alert alert-error" style="max-width:600px;margin:0 auto;">
          Unable to load wishes. Please check the database configuration in db.php.
        </div>
      <?php elseif (!empty($wishes)): ?>
        <div class="section-title mt-3">
          <h2>All Wishes</h2>
          <div class="ornament"><span>♥</span></div>
          <p style="color:var(--muted);margin-top:.5rem;font-family:var(--font-heading);font-style:italic;">
            <?= count($wishes) ?> heartfelt wish<?= count($wishes) !== 1 ? 'es' : '' ?>
          </p>
        </div>
        <div class="wishes-list">
          <?php foreach ($wishes as $wish): ?>
            <div class="wish-card">
              <div class="wish-name"><?= htmlspecialchars($wish['name']) ?></div>
              <div class="wish-message">&ldquo;<?= htmlspecialchars($wish['message']) ?>&rdquo;</div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php elseif (!$success): ?>
        <p class="text-center mt-3" style="color:var(--muted);font-family:var(--font-heading);font-style:italic;">
          No wishes yet — be the first to share yours!
        </p>
      <?php endif; ?>

    </div>
  </main>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="footer-names"><?= htmlspecialchars(BRIDE_NAME) ?> &amp; <?= htmlspecialchars(GROOM_NAME) ?></div>
    <p><?= htmlspecialchars(WEDDING_DATE) ?></p>
    <p style="margin-top:0.75rem;">
      <a href="gallery.php">Gallery</a> &nbsp;&middot;&nbsp;
      <a href="upload.php">Upload</a>
    </p>
  </footer>

  <script>
    document.getElementById('navToggle').addEventListener('click', function () {
      document.getElementById('navLinks').classList.toggle('open');
    });
  </script>
</body>
</html>
