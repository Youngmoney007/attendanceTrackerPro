<?php
// =============================================================
// includes/footer.php
// Closes the app-layout and adds shared JS
// =============================================================
?>
    </main>
  </div><!-- /main-wrapper -->
</div><!-- /app-layout -->

<!-- Toast container -->
<div id="toast-container"></div>

<script>
// ---- Live clock ------------------------------------------
(function liveClock() {
  const el = document.getElementById('live-clock');
  if (!el) return;
  const update = () => {
    const d = new Date();
    el.textContent = d.toLocaleDateString('en-GB', {weekday:'short',day:'2-digit',month:'short'})
                   + '  ' + d.toLocaleTimeString('en-GB', {hour:'2-digit',minute:'2-digit'});
  };
  update();
  setInterval(update, 1000);
})();

// ---- Sidebar mobile toggle --------------------------------
function openSidebar()  {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebar-overlay').classList.add('open');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('open');
}

// ---- Toast helper -----------------------------------------
function showToast(msg, type = 'info', duration = 3500) {
  const icons = { success: '✓', error: '✕', info: 'ℹ' };
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<span>${icons[type] || 'ℹ'}</span> ${msg}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), duration);
}

// ---- Confirm-action helper --------------------------------
function confirmAction(msg, formId) {
  if (confirm(msg)) document.getElementById(formId).submit();
}

// ---- Modal helpers ----------------------------------------
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
// Close on outside click
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

// ---- Chart.js global defaults ----------------------------
if (typeof Chart !== 'undefined') {
  Chart.defaults.color          = '#888';
  Chart.defaults.borderColor    = '#2e2e2e';
  Chart.defaults.font.family    = "'DM Sans', sans-serif";
  Chart.defaults.font.size      = 12;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
}
</script>
</body>
</html>
