// assets/app.js — UniCMS shared JavaScript

// ── MODAL HELPERS ─────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById('modal-' + id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}

// Close on overlay click
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// Close on Escape key
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(function (el) {
      el.classList.remove('open');
      document.body.style.overflow = '';
    });
  }
});

// ── DELETE / CONFIRM ───────────────────────────────────────
function confirmDelete(id, name) {
  const idField  = document.getElementById('delete-id');
  const msgField = document.getElementById('confirm-msg');
  if (idField)  idField.value = id;
  if (msgField && name) msgField.textContent = 'Are you sure you want to remove "' + name + '"?';
  openModal('delete');
}

// ── AUTO-DISMISS ALERTS ────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  var alerts = document.querySelectorAll('.alert');
  alerts.forEach(function (a) {
    setTimeout(function () {
      a.style.transition = 'opacity .4s';
      a.style.opacity = '0';
      setTimeout(function () { a.remove(); }, 400);
    }, 4000);
  });

  // Live search on filter forms (debounced)
  var searchInputs = document.querySelectorAll('.filter-input[type="text"]');
  searchInputs.forEach(function (input) {
    var form = input.closest('form');
    if (!form) return;
    var timer;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { form.submit(); }, 500);
    });
  });

  // Select filters auto-submit
  var selects = document.querySelectorAll('.filter-select');
  selects.forEach(function (sel) {
    var form = sel.closest('form');
    if (!form) return;
    sel.addEventListener('change', function () { form.submit(); });
  });
});