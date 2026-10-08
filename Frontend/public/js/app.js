(() => {
  const views = [...document.querySelectorAll('.view')];
  const navLinks = [...document.querySelectorAll('[data-view]')];
  const toast = document.querySelector('#toast');
  let toastTimer;

  function showToast(message) {
    document.querySelector('#toast-message').textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3400);
  }

  function setView(name) {
    const target = document.querySelector(`#view-${name}`);
    if (!target) return;
    views.forEach(view => view.classList.toggle('hidden', view !== target));
    document.querySelectorAll('.nav-link').forEach(link => link.classList.toggle('active', link.dataset.view === name));
    document.querySelector('#breadcrumb-section').textContent = target.dataset.section;
    document.querySelector('#breadcrumb-page').textContent = target.dataset.title;
    document.querySelector('#profile-menu').classList.remove('open');
    document.querySelector('#sidebar').classList.remove('open');
    document.querySelector('#mobile-overlay').classList.remove('show');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  navLinks.forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    setView(link.dataset.view);
  }));

  document.querySelector('#menu-toggle').addEventListener('click', () => {
    document.querySelector('#sidebar').classList.add('open');
    document.querySelector('#mobile-overlay').classList.add('show');
  });
  document.querySelector('#mobile-overlay').addEventListener('click', () => {
    document.querySelector('#sidebar').classList.remove('open');
    document.querySelector('#mobile-overlay').classList.remove('show');
  });
  document.querySelector('#profile-button').addEventListener('click', () => document.querySelector('#profile-menu').classList.toggle('open'));
  document.querySelector('#notification-button').addEventListener('click', () => showToast('You have 3 new community updates.'));

  document.querySelectorAll('[data-toggle-password]').forEach(button => button.addEventListener('click', () => {
    const input = document.querySelector(`#${button.dataset.togglePassword}`);
    input.type = input.type === 'password' ? 'text' : 'password';
    button.textContent = input.type === 'password' ? 'Show' : 'Hide';
  }));

  document.querySelectorAll('.validate-form').forEach(form => form.addEventListener('submit', event => {
    event.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    showToast(form.dataset.success || 'Saved successfully.');
    if (form.dataset.redirect) setTimeout(() => setView(form.dataset.redirect), 600);
    form.reset();
  }));

  const filterCards = (input, cards, empty) => {
    const query = input.value.toLowerCase().trim();
    let visible = 0;
    cards.forEach(card => { const match = card.dataset.search.includes(query); card.classList.toggle('hidden', !match); if (match) visible++; });
    if (empty) empty.classList.toggle('hidden', visible > 0);
  };
  const clubSearch = document.querySelector('#club-search');
  clubSearch.addEventListener('input', () => filterCards(clubSearch, [...document.querySelectorAll('.club-card')], document.querySelector('#club-empty')));
  document.querySelectorAll('.filter-pill').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('.filter-pill').forEach(item => item.classList.remove('active')); button.classList.add('active');
    document.querySelectorAll('.club-card').forEach(card => card.classList.toggle('hidden', button.dataset.filter !== 'all' && card.dataset.category !== button.dataset.filter));
  }));
  document.querySelector('#event-search').addEventListener('input', event => filterCards(event.target, [...document.querySelectorAll('.event-card')], null));

  document.querySelectorAll('.tab').forEach(tab => tab.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(item => item.classList.remove('active')); tab.classList.add('active');
    document.querySelector('#tab-current').classList.toggle('hidden', tab.dataset.tab !== 'current');
    document.querySelector('#tab-past').classList.toggle('hidden', tab.dataset.tab !== 'past');
  }));
  document.querySelectorAll('.cancel-registration').forEach(button => button.addEventListener('click', () => { button.closest('.table-row').remove(); showToast('Registration cancelled.'); }));
  document.querySelector('#register-event').addEventListener('click', event => { event.currentTarget.innerHTML = '✓ Registered'; event.currentTarget.disabled = true; event.currentTarget.style.background = '#26966d'; showToast('You are registered for this event.'); });

  const title = document.querySelector('#event-title');
  const date = document.querySelector('#event-date');
  const time = document.querySelector('#event-time');
  const updatePreview = () => {
    if (title) document.querySelector('#event-preview-title').textContent = title.value || 'Your event title';
    if (date) document.querySelector('#event-preview-date').textContent = `${date.value || 'Choose a date'} · ${time.value || 'Choose a time'}`;
  };
  [title, date, time].filter(Boolean).forEach(input => input.addEventListener('input', updatePreview));
})();
