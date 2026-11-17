(function ($) {
  'use strict';
  const __ = wp.i18n.__;
  const element = function (tag, text, className) {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
  };
  const revisionRequest = async function (context, mode, args) {
    try {
      const response = await $.ajax({url: cffContentRevisions.ajaxUrl, method: 'POST', data: Object.assign({
        action: 'cff_content_revisions', mode: mode, post_id: context.dataset.postId, nonce: context.dataset.nonce
      }, args || {})});
      if (!response.success) throw new Error(response.data && response.data.message || __('Revision request failed.', 'cff'));
      return response.data;
    } catch (failure) {
      throw new Error(failure.responseJSON && failure.responseJSON.data && failure.responseJSON.data.message
        || failure.message || __('Unable to load revisions. Please try again.', 'cff'));
    }
  };
  $(function () {
    const panel = document.querySelector('.cff-revisions');
    if (panel) {
      let dirty = false;
      const form = panel.closest('form') || document.getElementById('post') || document.getElementById('metaboxes');
      const fieldState = function () {
        return JSON.stringify(form ? $(form).serializeArray().filter(function (input) {
          return /^(cff_values\[|cff_group_field_order|cff_hidden_sections\[)/.test(input.name);
        }) : []);
      };
      let savedFields = fieldState();
      setTimeout(function () { if (!dirty) savedFields = fieldState(); }, 0);
      window.addEventListener('load', function () { if (!dirty) savedFields = fieldState(); });
      if (form) $(form).on('input change', function (event) { if (!panel.contains(event.target)) dirty = true; });
      const trigger = panel.querySelector('.cff-revisions-open');
      const guardNavigation = function (event) {
        const editor = wp.data && wp.data.select('core/editor');
        if (dirty || savedFields !== fieldState() || (editor && editor.isEditedPostDirty())
          || (window.tinymce && tinymce.editors.some(function (editor) { return editor.isDirty(); }))) {
          event.preventDefault();
          panel.querySelector('[role="alert"]').textContent = __('Save your editor changes before viewing revisions.', 'cff');
          return false;
        }
        panel.querySelector('[role="alert"]').textContent = '';
        return true;
      };
      const dialog = element('dialog', undefined, 'cff-revisions-history-dialog');
      dialog.id = 'cff-revisions-history-dialog';
      dialog.setAttribute('aria-labelledby', 'cff-revisions-history-title');
      trigger.setAttribute('aria-haspopup', 'dialog');
      trigger.setAttribute('aria-controls', dialog.id);
      const header = element('div', undefined, 'cff-revisions-history-header');
      const title = element('h2', __('Revisions', 'cff'));
      title.id = 'cff-revisions-history-title';
      const close = element('button', '×', 'cff-revisions-history-close');
      close.type = 'button';
      close.setAttribute('aria-label', __('Close revisions', 'cff'));
      close.title = __('Close', 'cff');
      header.append(title, close);
      const body = element('div', undefined, 'cff-revisions-history-body');
      body.tabIndex = 0;
      body.setAttribute('role', 'region');
      body.setAttribute('aria-label', __('Revision list', 'cff'));
      const loading = element('p');
      loading.setAttribute('role', 'status');
      const failure = element('p', undefined, 'cff-revisions-error');
      failure.setAttribute('role', 'alert');
      const retry = element('button', __('Try again', 'cff'), 'button cff-revisions-history-retry');
      retry.type = 'button';
      retry.hidden = true;
      const list = element('ul', undefined, 'cff-revisions-history-list');
      body.append(loading, failure, retry, list);
      const footer = element('div', undefined, 'cff-revisions-history-footer');
      const compare = element('a', __('Compare revisions', 'cff'), 'button cff-revisions-history-compare');
      compare.href = trigger.href;
      compare.addEventListener('click', guardNavigation);
      footer.append(compare);
      dialog.append(header, body, footer);
      document.body.append(dialog);
      let opening = 0;
      const loadHistory = async function () {
        const request = ++opening;
        loading.textContent = __('Loading revisions…', 'cff');
        failure.textContent = '';
        retry.hidden = true;
        compare.hidden = true;
        footer.hidden = true;
        list.replaceChildren();
        body.setAttribute('aria-busy', 'true');
        try {
          const result = await revisionRequest(panel, 'history');
          if (!dialog.open || request !== opening) return;
          loading.textContent = result.items.length ? '' : __('No revisions yet. Save a change to CFF values to start this content’s history.', 'cff');
          compare.hidden = !result.items.length;
          footer.hidden = !result.items.length;
          result.items.forEach(function (item) {
            const row = element('li', undefined, 'cff-revisions-history-item');
            if (item.avatar) {
              const avatar = element('img');
              avatar.src = item.avatar;
              avatar.alt = '';
              avatar.width = 40;
              avatar.height = 40;
              row.append(avatar);
            }
            const details = element('div');
            const text = element('p');
            if (item.kind === 'before') text.append(document.createTextNode(__('Captured by ', 'cff')));
            text.append(document.createTextNode(item.user + ', ' + item.relative + ' ('));
            const link = element('a', undefined, 'cff-revisions-history-date');
            const url = new URL(trigger.href);
            url.searchParams.set('revision_id', item.id);
            link.href = url.href;
            link.title = item.date;
            link.dataset.revisionId = item.id;
            const time = element('time', item.date_long || item.date);
            time.dateTime = item.datetime;
            link.append(time);
            link.addEventListener('click', guardNavigation);
            text.append(link, document.createTextNode(')'));
            details.append(text);
            if (item.kind === 'before') details.append(element('p', __('Capture time shown; original editor and change time unavailable.', 'cff'), 'description'));
            row.append(details);
            list.append(row);
          });
        } catch (error) {
          if (!dialog.open || request !== opening) return;
          loading.textContent = '';
          failure.textContent = error.message;
          retry.hidden = false;
        } finally {
          if (request === opening) body.setAttribute('aria-busy', 'false');
        }
      };
      close.addEventListener('click', function () { dialog.close(); });
      dialog.addEventListener('close', function () { ++opening; });
      retry.addEventListener('click', loadHistory);
      trigger.addEventListener('click', function (event) {
        event.preventDefault();
        if (!guardNavigation(event) || dialog.open) return;
        dialog.showModal();
        loadHistory();
      });
    }

    const page = document.querySelector('.cff-revisions-page');
    if (!page) return;
    const workspace = page.querySelector('.cff-revisions-workspace');
    const status = page.querySelector('[role="status"]');
    const error = page.querySelector('[role="alert"]');
    const from = page.querySelector('#cff-revision-from');
    const to = page.querySelector('#cff-revision-to');
    const fromRange = page.querySelector('#cff-revision-from-range');
    const toRange = page.querySelector('#cff-revision-to-range');
    const any = page.querySelector('#cff-compare-any');
    const previous = page.querySelector('.cff-revision-previous');
    const next = page.querySelector('.cff-revision-next');
    const restore = page.querySelector('.cff-revision-restore');
    const results = page.querySelector('.cff-revisions-results');
    const filters = page.querySelector('.cff-revisions-filters');
    const filterUser = page.querySelector('#cff-revision-user');
    const filterFrom = page.querySelector('#cff-revision-date-from');
    const filterTo = page.querySelector('#cff-revision-date-to');
    const filterCount = page.querySelector('.cff-revision-filter-count');
    const fieldOptions = page.querySelector('.cff-revision-field-options');
    const restoreSelected = page.querySelector('.cff-revision-restore-selected');
    let allVersions = [];
    let currentRecorded = true;
    let versions = [];
    let fromIndex = 0;
    let toIndex = 0;
    let comparison = null;
    let requestNumber = 0;
    let restoring = false;
    let requestedRevision = new URL(window.location.href).searchParams.get('revision_id') || '';
    const request = function (mode, args) { return revisionRequest(page, mode, args); };
    const label = function (item) {
      const kind = item.current ? __('Current revision', 'cff') : item.kind === 'restore' ? __('Restored', 'cff')
        : item.kind === 'before' ? __('Before change (captured)', 'cff') : __('Revision', 'cff');
      return kind + ' · ' + item.user + (item.date ? ' · ' + item.date : '');
    };
    const versionOption = function (item, index) {
      const title = item.current ? __('Current', 'cff') : wp.i18n.sprintf(__('Revision %d', 'cff'), index + 1);
      const option = new Option(title + (item.date ? ' · ' + item.date : ''), String(index));
      option.title = label(item);
      return option;
    };
    const person = function (container, item, timeline) {
      container.replaceChildren();
      if (item.avatar) {
        const avatar = element('img');
        avatar.src = item.avatar;
        avatar.alt = '';
        avatar.width = 40;
        avatar.height = 40;
        container.append(avatar);
      }
      const details = element('div', undefined, 'cff-revision-person-details');
      const byline = element('p');
      if (item.kind === 'before') {
        byline.append(document.createTextNode(__('Captured by ', 'cff')), element('strong', item.user));
      } else if (item.author_known) {
        byline.append(document.createTextNode(item.current && !timeline ? __('Current revision by ', 'cff') : __('Revision by ', 'cff')), element('strong', item.user));
        if (item.kind === 'restore') byline.append(document.createTextNode(' · ' + (item.restored_fields && item.restored_fields.length ? __('Restored fields', 'cff') : __('Restored', 'cff'))));
      } else {
        byline.textContent = __('Original editor and change time unavailable', 'cff');
      }
      details.append(byline);
      if (item.datetime) {
        const time = element('time', timeline ? item.relative + ' (' + item.date + ')' : item.date);
        time.dateTime = item.datetime;
        details.append(time);
        if (!timeline) details.append(element('span', item.relative, 'cff-revision-relative'));
      }
      if (item.kind === 'before') details.append(element('p', __('Capture time shown; original editor and change time unavailable.', 'cff'), 'description cff-revision-capture-note'));
      container.append(details);
    };
    const positionTooltip = function (input) {
      const control = input.closest('.cff-revision-range');
      if (control.hidden) return;
      const tooltip = control.querySelector('.cff-revision-tooltip');
      const width = input.getBoundingClientRect().width;
      const ratio = Number(input.max) > 0 ? Number(input.value) / Number(input.max) : 0;
      const handle = 22 + Math.max(0, width - 44) * (getComputedStyle(input).direction === 'rtl' ? 1 - ratio : ratio);
      const left = Math.max(0, Math.min(width - tooltip.offsetWidth, handle - tooltip.offsetWidth / 2));
      tooltip.style.left = left + 'px';
      tooltip.style.setProperty('--cff-tooltip-arrow', (handle - left) + 'px');
    };
    const positionTooltips = function () {
      if (!versions.length || workspace.hidden) return;
      positionTooltip(fromRange);
      positionTooltip(toRange);
    };
    const makeTicks = function (input) {
      const ticks = input.closest('.cff-revision-slider').querySelector('.cff-revision-ticks');
      ticks.replaceChildren();
      for (let index = 1; index < Number(input.max); index++) {
        const tick = element('span');
        tick.style.left = (index / Number(input.max) * 100) + '%';
        ticks.append(tick);
      }
    };
    window.addEventListener('resize', positionTooltips);
    if (window.ResizeObserver) new ResizeObserver(positionTooltips).observe(page.querySelector('.cff-revisions-timeline'));
    const syncControls = function () {
      if (!any.checked) fromIndex = toIndex === 0 ? from.options.length - 1 : Math.min(toIndex - 1, from.options.length - 1);
      from.value = String(fromIndex);
      to.value = String(toIndex);
      from.title = label(versions[fromIndex]);
      to.title = label(versions[toIndex]);
      fromRange.value = String(fromIndex);
      toRange.value = String(toIndex);
      fromRange.setAttribute('aria-valuetext', label(versions[fromIndex]));
      toRange.setAttribute('aria-valuetext', label(versions[toIndex]));
      from.disabled = !any.checked;
      fromRange.disabled = !any.checked;
      page.querySelector('.cff-revision-from-range').hidden = !any.checked;
      previous.disabled = toIndex === 0;
      next.disabled = toIndex === versions.length - 1;
      person(page.querySelector('.cff-revision-from .cff-revision-person'), versions[fromIndex]);
      person(page.querySelector('.cff-revision-to .cff-revision-person'), versions[toIndex]);
      person(page.querySelector('#cff-revision-from-tooltip'), versions[fromIndex], true);
      person(page.querySelector('#cff-revision-to-tooltip'), versions[toIndex], true);
      positionTooltips();
    };
    const compare = async function () {
      const number = ++requestNumber;
      comparison = null;
      restore.disabled = true;
      restoreSelected.disabled = true;
      fieldOptions.replaceChildren();
      syncControls();
      error.textContent = '';
      status.textContent = __('Loading comparison…', 'cff');
      results.replaceChildren();
      results.setAttribute('aria-busy', 'true');
      const target = versions[toIndex].id;
      try {
        const result = await request('compare', {revision_id: versions[fromIndex].id, compare_id: target});
        if (number !== requestNumber) return;
        comparison = {revisionId: target, hash: result.current_hash, fields: result.restore_fields || []};
        comparison.fields.forEach(function (field) {
          const label = element('label');
          const checkbox = element('input');
          checkbox.type = 'checkbox';
          checkbox.value = field.id;
          label.append(checkbox, document.createTextNode(field.label));
          if (field.id.startsWith('field:')) label.append(element('code', field.id.slice(6)));
          fieldOptions.append(label);
        });
        if (!comparison.fields.length) fieldOptions.append(element('p', __('No fields need restoring from this To revision.', 'cff'), 'description'));
        status.textContent = result.changes.length ? wp.i18n.sprintf(__('%d changed values.', 'cff'), result.changes.length)
          : __('No differences between these versions.', 'cff');
        const unitButtons = new Set();
        result.changes.forEach(function (change) {
          const section = element('section', undefined, 'cff-revision-field');
          const header = element('div', undefined, 'cff-revision-field-header');
          header.append(element('h2', change.label || change.field));
          const field = comparison.fields.find(function (field) { return field.id === change.restore_id; });
          if (field && !unitButtons.has(field.id)) {
            unitButtons.add(field.id);
            const button = element('button', wp.i18n.sprintf(__('Restore: %s', 'cff'), field.label), 'button cff-revision-restore-unit');
            button.type = 'button';
            button.dataset.field = field.id;
            button.addEventListener('click', function () { performRestore([field.id]); });
            header.append(button);
          }
          section.append(header);
          const path = element('p', change.field, 'description cff-revision-field-path');
          section.append(path);
          const diff = element('div', undefined, 'cff-revision-diff');
          // Only server-generated WordPress diff markup enters HTML; field values are escaped there.
          diff.innerHTML = change.diff_html;
          section.append(diff);
          results.append(section);
        });
        restore.disabled = !result.target_can_restore;
      } catch (failure) {
        if (number !== requestNumber) return;
        status.textContent = '';
        error.textContent = failure.message;
      } finally {
        if (number === requestNumber) results.setAttribute('aria-busy', 'false');
      }
    };
    to.addEventListener('change', function () { toIndex = Number(to.value); compare(); });
    from.addEventListener('change', function () { fromIndex = Number(from.value); compare(); });
    toRange.addEventListener('input', function () { toIndex = Number(toRange.value); compare(); });
    fromRange.addEventListener('input', function () { fromIndex = Number(fromRange.value); compare(); });
    any.addEventListener('change', compare);
    previous.addEventListener('click', function () { if (toIndex > 0) { toIndex--; compare(); } });
    next.addEventListener('click', function () { if (toIndex < versions.length - 1) { toIndex++; compare(); } });
    const performRestore = async function (fields) {
      if (restoring || !comparison || !comparison.revisionId || comparison.revisionId !== versions[toIndex].id) return;
      if (fields && !fields.length) return;
      const selectedLabels = fields && comparison.fields.filter(function (field) { return fields.includes(field.id); }).map(function (field) { return field.label; });
      const message = fields ? wp.i18n.sprintf(__('Restore only these fields/sections from the To revision: %s? Nested fields and their own Hide Section state are included. Other fields stay unchanged.', 'cff'), selectedLabels.join(', '))
        : __('Restore the To revision for this content? The current saved CFF values will remain in history.', 'cff');
      if (!window.confirm(message)) return;
      restoring = true;
      page.querySelectorAll('button, select, input').forEach(function (control) { control.disabled = true; });
      status.textContent = __('Restoring revision…', 'cff');
      error.textContent = '';
      try {
        const args = {revision_id: comparison.revisionId, current_hash: comparison.hash};
        if (fields) args.fields = fields;
        await request('restore', args);
        window.location.assign(page.dataset.editUrl);
      } catch (failure) {
        restoring = false;
        comparison = null;
        status.textContent = '';
        error.textContent = failure.message;
        page.querySelectorAll('button, select, input').forEach(function (control) { control.disabled = false; });
        syncControls();
        restore.disabled = true;
        restoreSelected.disabled = true;
        page.querySelectorAll('.cff-revision-restore-unit').forEach(function (button) { button.disabled = true; });
      }
    };
    restore.addEventListener('click', function () { performRestore(null); });
    const selectedFields = function () { return Array.from(fieldOptions.querySelectorAll('input:checked'), function (input) { return input.value; }); };
    fieldOptions.addEventListener('change', function () { restoreSelected.disabled = !comparison || !selectedFields().length; });
    restoreSelected.addEventListener('click', function () { performRestore(selectedFields()); });

    const applyFilters = async function () {
      const previousTo = requestedRevision || (versions[toIndex] && versions[toIndex].id);
      requestedRevision = '';
      const previousFrom = versions[fromIndex] && versions[fromIndex].id;
      ++requestNumber;
      comparison = null;
      restore.disabled = true;
      restoreSelected.disabled = true;
      fieldOptions.replaceChildren();
      results.replaceChildren();
      results.setAttribute('aria-busy', 'false');
      error.textContent = '';
      from.replaceChildren();
      to.replaceChildren();
      if (filterFrom.value && filterTo.value && filterFrom.value > filterTo.value) {
        workspace.hidden = true;
        status.textContent = '';
        error.textContent = __('From date must be on or before To date.', 'cff');
        return;
      }
      versions = allVersions.filter(function (item) {
        return (!filterUser.value || String(item.user_id) === filterUser.value)
          && (!filterFrom.value || item.local_date >= filterFrom.value)
          && (!filterTo.value || item.local_date <= filterTo.value);
      });
      filterCount.textContent = wp.i18n.sprintf(__('%d of %d revisions.', 'cff'), versions.length, allVersions.length);
      if (!versions.length) {
        workspace.hidden = true;
        status.textContent = __('No revisions match these filters. Change the filters or choose Reset.', 'cff');
        return;
      }
      versions.forEach(function (item, index) { from.append(versionOption(item, index)); });
      if (!filterUser.value && !filterFrom.value && !filterTo.value && !currentRecorded) {
        versions.push({id: '', current: true, user: __('Unknown user', 'cff'), kind: 'current', author_known: false});
      }
      versions.forEach(function (item, index) { to.append(versionOption(item, index)); });
      fromRange.max = String(from.options.length - 1);
      toRange.max = String(versions.length - 1);
      makeTicks(fromRange);
      makeTicks(toRange);
      toIndex = versions.findIndex(function (item) { return item.id === previousTo; });
      if (toIndex < 0) toIndex = versions.length - 1;
      fromIndex = versions.slice(0, from.options.length).findIndex(function (item) { return item.id === previousFrom; });
      if (fromIndex < 0) fromIndex = Math.min(Math.max(0, toIndex - 1), from.options.length - 1);
      workspace.hidden = false;
      await compare();
    };
    filters.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!restoring) applyFilters();
    });
    page.querySelector('.cff-revision-reset-filters').addEventListener('click', function () {
      if (restoring) return;
      filters.reset();
      applyFilters();
    });
    const load = async function () {
      status.textContent = __('Loading revisions…', 'cff');
      try {
        const result = await request('history');
        if (!result.items.length) {
          status.textContent = __('No revisions yet. Save a change to CFF values to start this content’s history.', 'cff');
          return;
        }
        allVersions = result.items.slice().reverse();
        currentRecorded = result.current_is_recorded;
        if (requestedRevision && !allVersions.some(function (item) { return item.id === requestedRevision; })) {
          status.textContent = '';
          error.textContent = __('This revision is no longer available for this content. Return to the editor and choose another revision.', 'cff');
          return;
        }
        const users = new Map();
        allVersions.forEach(function (item) { users.set(String(item.user_id), item.user); });
        users.forEach(function (name, id) { filterUser.append(new Option(name, id)); });
        filters.hidden = false;
        await applyFilters();
      } catch (failure) {
        status.textContent = '';
        error.textContent = failure.message;
      }
    };
    load();
  });
})(jQuery);
