  </main><!-- /content -->
</div><!-- /main-wrap -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ---- Sidebar toggle (mobile) ----
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// ---- Dark mode toggle ----
(function(){
  const stored = localStorage.getItem('theme');
  if (stored) document.body.setAttribute('data-theme', stored);
})();
function toggleDark() {
  const cur = document.body.getAttribute('data-theme');
  const next = cur === 'dark' ? 'light' : 'dark';
  document.body.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
}

// ---- Auto-dismiss flash ----
setTimeout(() => {
  document.querySelectorAll('.flash-alert').forEach(el => {
    el.style.transition = 'opacity .5s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  });
}, 4000);

// ---- CSRF header for all AJAX ----
const CSRF = document.querySelector('meta[name=csrf]')?.content ?? '';
function ajax(url, data = {}) {
  return fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': CSRF,
    },
    body: JSON.stringify(data),
  }).then(r => r.json());
}
</script>

<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
