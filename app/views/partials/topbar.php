<header class="topbar">
  <button class="icon-button menu-toggle" id="menu-toggle" aria-label="Open navigation">&#9776;</button>
  <div class="breadcrumb"><span id="breadcrumb-section">Authentication</span><strong>/</strong><span id="breadcrumb-page">Welcome back</span></div>
  <div class="topbar-actions">
    <?php if ($currentUser !== null): ?>
    <button class="icon-button" aria-label="Notifications" id="notification-button">&#9830;<span class="notification-dot"></span></button>
    <?php $initials = strtoupper(substr((string) $currentUser['first_name'], 0, 1) . substr((string) $currentUser['last_name'], 0, 1)); ?>
    <button class="profile-button" id="profile-button"><span class="avatar"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span><span class="profile-copy"><strong><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(ucfirst((string) $currentUser['role']), ENT_QUOTES, 'UTF-8') ?></small></span><span>&#8964;</span></button>
    <div class="profile-menu" id="profile-menu"><form method="post" action="index.php?action=logout"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\ClubConnect\Config\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>"><button type="submit">Sign out</button></form></div>
    <?php endif; ?>
  </div>
</header>
<div class="mobile-overlay" id="mobile-overlay"></div>
