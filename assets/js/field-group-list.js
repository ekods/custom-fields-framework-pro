(function () {
  'use strict';
  function init() {
    if (!document.body.classList.contains('post-type-cff_group')) return;
    const table = document.querySelector('.wp-list-table');
    if (!table) return;
    function alignActions() {
      const body = table.querySelector('#the-list');
      if (!body) return;
      Array.from(body.children).forEach(row => {
        const cell = row.querySelector('td.column-cff_actions');
        const slot = cell && cell.querySelector('[data-cff-group-actions]');
        const primary = row.querySelector('.column-primary');
        const actions = row.querySelector('.row-actions');
        if (!slot || !primary || !actions) return;
        // Screen Options can hide this column; keep its actions reachable under the title.
        const destination = cell.classList.contains('hidden') ? primary : slot;
        if (actions.parentElement !== destination) destination.appendChild(actions);
      });
    }
    alignActions();
    new MutationObserver(records => {
      if (records.some(record => record.type === 'childList' || record.target.matches('td.column-cff_actions'))) alignActions();
    }).observe(table, {subtree: true, childList: true, attributes: true, attributeFilter: ['class']});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true});
  else init();
}());
