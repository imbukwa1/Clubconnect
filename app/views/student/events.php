<?php declare(strict_types=1); ?>
<section class="view hidden" id="view-events" data-title="Upcoming events" data-section="Student workspace">
  <div class="page-heading">
    <div><span class="eyebrow">MAKE YOUR NEXT MEMORY</span><h1>Upcoming events</h1><p class="muted">Find something worth showing up for.</p></div>
    <button class="button button-primary">My calendar <span>⌗</span></button>
  </div>
  <form class="toolbar" method="get" action="index.php">
    <input type="hidden" name="view" value="events">
    <div class="search-box"><span>⌕</span><input id="event-search" name="q" type="search" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search events..." aria-label="Search events"></div>
    <select name="category" aria-label="Filter events"><option value="">All categories</option>
      <?php foreach (['Academic', 'Creative', 'Social', 'Sports'] as $category): ?><option value="<?= e($category) ?>" <?= strtolower((string) ($_GET['category'] ?? '')) === strtolower($category) ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?>
    </select>
    <button class="button button-outline" type="submit">Search</button>
  </form>
  <?php if ($dataError !== null): ?>
    <div class="panel empty-state"><h3>Events are temporarily unavailable.</h3><p class="muted"><?= e($dataError) ?></p></div>
  <?php elseif ($events === []): ?>
    <p class="empty-state">No events match your search.</p>
  <?php else: ?>
    <div class="card-grid event-cards">
      <?php foreach ($events as $event): $tone = event_tone((string) $event['club_category']); $timestamp = strtotime((string) $event['event_date']); $searchText = strtolower((string) $event['title'] . ' ' . (string) $event['description'] . ' ' . (string) $event['venue']); ?>
        <article class="event-card" data-search="<?= e($searchText) ?>">
          <div class="event-banner banner-<?= e($tone) ?>"><span><?= e(strtoupper(date('M', $timestamp))) ?><br><strong><?= e(date('d', $timestamp)) ?></strong></span><i>✦</i></div>
          <div class="card-body"><span class="tag tag-<?= e($tone) ?>"><?= e($event['club_category']) ?></span><h3><?= e($event['title']) ?></h3><p><?= e($event['description']) ?></p><div class="card-meta"><span><?= e(date('g:i A', strtotime((string) $event['event_time']))) ?></span><span><?= e($event['venue']) ?></span></div><a class="button button-primary full-width" data-view="event-details" data-route="true" href="index.php?view=event-details&amp;id=<?= (int) $event['event_id'] ?>">View details</a></div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
