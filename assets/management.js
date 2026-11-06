/* Content management interactions; forms and links also work without JavaScript. */
(function () {
  'use strict';

  function init() {
    var root = document.querySelector('.cff-admin');
    if (!root) return;

    // Reveal the current section in the mobile navigation without scrolling the page.
    var navigation = root.querySelector('.cff-section-nav');
    function revealCurrentSection() {
      if (!navigation) return;
      var current = navigation.querySelector('[aria-current="page"]');
      if (!current) return;
      var viewport = navigation.getBoundingClientRect();
      var item = current.getBoundingClientRect();
      var offset = item.left < viewport.left ? item.left - viewport.left : (item.right > viewport.right ? item.right - viewport.right : 0);
      if (offset) navigation.scrollLeft += offset;
    }
    requestAnimationFrame(revealCurrentSection);
    window.addEventListener('resize', revealCurrentSection);

    var editor = root.querySelector('#cff-definition-editor');
    function openEditor(focus) {
      if (!editor) return;
      if (editor.tagName === 'DETAILS') editor.open = true;
      if (focus) {
        editor.scrollIntoView({ block: 'start' });
        var input = editor.querySelector('input[type="text"]:not([readonly])');
        if (input) input.focus({ preventScroll: true });
      }
    }
    root.querySelectorAll('[data-cff-open-editor]').forEach(function (link) {
      link.addEventListener('click', function (event) {
        var target = new URL(link.href, location.href);
        if (target.pathname === location.pathname && target.search === location.search) {
          event.preventDefault();
          history.replaceState(null, '', target.hash);
          openEditor(true);
        }
      });
    });
    // Old add links used a fragment to open a form beneath the list.
    if (!editor && location.hash === '#cff-definition-editor') {
      var legacyAdd = new URL(location.href);
      if (['cff-post-types', 'cff-taxonomies'].indexOf(legacyAdd.searchParams.get('page')) !== -1) {
        legacyAdd.searchParams.set('action', 'add');
        legacyAdd.hash = '';
        location.replace(legacyAdd.href);
        return;
      }
    }
    if (location.hash === '#cff-definition-editor') openEditor(true);
    window.addEventListener('hashchange', function () {
      if (location.hash === '#cff-definition-editor') openEditor(true);
    });

    root.querySelectorAll('[data-cff-editor]').forEach(function (form) {
      var taxonomyKey = form.querySelector('[name="tax_key"]');
      var singular = form.querySelector('[name="singular"]');
      if (taxonomyKey && singular && !taxonomyKey.readOnly) {
        var autoKey = !taxonomyKey.value;
        singular.addEventListener('input', function () {
          if (autoKey) taxonomyKey.value = singular.value.toLowerCase().replace(/[^a-z0-9_]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '').slice(0, 32);
        });
        taxonomyKey.addEventListener('input', function () { autoKey = !taxonomyKey.value; });
      }
      var hide = form.querySelector('[name="cpt_hide_frontend"], [name="hide_frontend"]');
      var publicInput = form.querySelector('[name="cpt_public"], [name="public"]');
      var publicSettings = form.querySelector('[data-cff-public-settings]');
      var summary = form.querySelector('[data-cff-visibility-summary]');
      var status = form.querySelector('.cff-save-status');
      var initial = '';
      var dirty = false;
      function snapshot() { return new URLSearchParams(new FormData(form)).toString(); }
      function update() {
        if (hide && summary) {
          var state = hide.checked ? 'hidden' : (publicInput && publicInput.checked ? 'public' : 'private');
          if (summary.textContent !== summary.dataset[state]) summary.textContent = summary.dataset[state];
          summary.classList.toggle('is-hidden', hide.checked);
          if (publicSettings) publicSettings.classList.toggle('is-overridden', hide.checked);
        }
        // Keep overridden values submitted so disabling the override restores them.
        var keyPreview = form.querySelector('[data-cff-key-preview]');
        var keyInput = form.querySelector('[name="cpt_key"]');
        if (keyPreview && keyInput && keyInput.value) keyPreview.textContent = keyInput.value;
        dirty = snapshot() !== initial;
        if (status) {
          var nextStatus = dirty ? status.dataset.dirty : status.dataset.clean;
          if (status.textContent !== nextStatus) status.textContent = nextStatus;
          status.classList.toggle('is-dirty', dirty);
        }
      }
      // CFF's label/slug initialization runs first, including Select2 enhancement.
      initial = snapshot();
      update();
      form.addEventListener('input', update);
      form.addEventListener('change', update);
      if (window.jQuery) window.jQuery(form).on('change.cffManagement', update);
      window.addEventListener('beforeunload', function (event) {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
      });
      form.addEventListener('submit', function () { dirty = false; });
      form.addEventListener('invalid', function (event) {
        var details = event.target.closest('details');
        while (details) { details.open = true; details = details.parentElement.closest('details'); }
      }, true);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 0); });
  else setTimeout(init, 0);
})();
