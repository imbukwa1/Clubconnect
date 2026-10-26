<?php declare(strict_types=1); ?>
<section class="view auth-view hidden" id="view-register" data-title="Create account" data-section="Authentication">
  <div class="auth-layout">
    <div class="auth-art register-art"><span class="eyebrow light">MAKE CAMPUS COUNT</span><h1>There is a club<br><em>for everyone.</em></h1><p>Share your interests, meet new people and make your university experience memorable.</p><div class="avatar-stack"><span>JM</span><span>LO</span><span>AK</span><span>+2k</span></div></div>
    <div class="auth-card"><div class="auth-logo"><span class="brand-mark">CC</span><div><strong>Club<span class="brand-accent">Connect</span></strong><small>Campus community, connected</small></div></div>
      <h2>Create your account</h2><p class="muted">Join the community in less than a minute.</p>
      <form class="validate-form" data-server-form="true" method="post" action="index.php?action=register">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\ClubConnect\Config\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-grid"><div><label for="register-first">First name</label><input id="register-first" name="first_name" type="text" placeholder="Alex" required></div><div><label for="register-last">Last name</label><input id="register-last" name="last_name" type="text" placeholder="Mwangi" required></div></div>
        <label for="register-email">University email</label><input id="register-email" name="email" type="email" placeholder="you@university.ac.ke" required>
        <label for="register-password">Create password</label><input id="register-password" name="password" type="password" placeholder="At least 8 characters" minlength="8" required>
        <label class="check-label terms"><input type="checkbox" name="terms" value="1" required> I agree to the <a href="#">community guidelines</a></label>
        <button class="button button-primary full-width" type="submit">Create account <span>→</span></button>
      </form>
      <p class="auth-switch">Already have an account? <button data-view="login">Sign in</button></p>
    </div>
  </div>
</section>
