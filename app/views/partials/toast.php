<?php
$success = \ClubConnect\Config\Session::pullFlash('success');
$error = \ClubConnect\Config\Session::pullFlash('error');
$flash = $error ?? $success;
?>
<div class="toast" id="toast" role="status" aria-live="polite" data-message="<?= htmlspecialchars($flash ?? '', ENT_QUOTES, 'UTF-8') ?>"><span>&#10003;</span><p id="toast-message"></p></div>
