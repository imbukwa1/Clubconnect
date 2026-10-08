<?php
declare(strict_types=1);

$viewFiles = [
    __DIR__ . '/../auth/login.php',
    __DIR__ . '/../auth/register.php',
    __DIR__ . '/../student/dashboard.php',
    __DIR__ . '/../student/clubs.php',
    __DIR__ . '/../student/club-details.php',
    __DIR__ . '/../student/events.php',
    __DIR__ . '/../student/event-details.php',
    __DIR__ . '/../student/registrations.php',
    __DIR__ . '/../admin/dashboard.php',
    __DIR__ . '/../admin/clubs.php',
    __DIR__ . '/../admin/club-form.php',
    __DIR__ . '/../admin/events.php',
    __DIR__ . '/../admin/event-form.php',
    __DIR__ . '/../admin/registrations.php',
];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="ClubConnect campus clubs and events platform">
  <title>ClubConnect | Campus community, connected</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/app.css">
</head>
<body>
  <div class="app-shell">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main-area">
      <?php require __DIR__ . '/../partials/topbar.php'; ?>
      <div class="content-wrap">
        <?php foreach ($viewFiles as $viewFile) { require $viewFile; } ?>
      </div>
    </main>
  </div>
  <?php require __DIR__ . '/../partials/toast.php'; ?>
  <script src="js/app.js"></script>
</body>
</html>

