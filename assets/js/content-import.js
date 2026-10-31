(function () {
  'use strict';
  const form = document.getElementById('cff-content-import-form');
  if (!form || typeof CFFContentImport === 'undefined') return;
  const config = CFFContentImport;
  const el = name => document.getElementById('cff-import-' + name);
  const result = el('result'), message = el('message'), rows = el('rows');
  let job = {}, busy = false, automatic = false, pending = '';
  const activePhases = ['preview', 'ready', 'running', 'paused'];

  async function request(command, body) {
    body = body || new FormData();
    body.set('action', 'cff_content_import'); body.set('nonce', config.nonce); body.set('command', command);
    if (config.postId) { body.set('scope', 'page'); body.set('post_id', String(config.postId)); }
    if (job.token) body.set('job', job.token);
    const response = await fetch(config.ajax, {method: 'POST', credentials: 'same-origin', body: body});
    let data;
    try { data = await response.json(); }
    catch (_) { throw new Error('The server did not return an import response. Reload to check saved progress.'); }
    if (!response.ok || !data.success) throw new Error(data.data && data.data.message || 'The import request failed.');
    return data.data || {};
  }

  function render() {
    result.hidden = !job.token;
    const active = activePhases.includes(job.phase);
    form.querySelectorAll('input, select, button[type=submit]').forEach(input => { input.disabled = busy || active; });
    el('start').hidden = job.phase !== 'ready' || !job.valid;
    el('start').textContent = 'Import ' + (job.valid || 0) + ' valid items';
    el('start').disabled = busy;
    el('continue').hidden = !['preview', 'running', 'paused'].includes(job.phase) || automatic;
    el('continue').disabled = busy;
    el('pause').hidden = job.phase !== 'running' || !automatic;
    el('cancel').hidden = !active;
    el('pause').disabled = !!pending; el('cancel').disabled = !!pending;
    if (!job.token) return;
    const labels = {preview: 'Validating JSON. No content is being changed.', ready: 'Preview ready. Review the items before starting.',
      running: 'Importing content. Keep this page open.', paused: 'Import paused. Review any items marked for manual review before continuing.',
      completed: 'Import completed. Review failures and download the report.', cancelled: 'Remaining work cancelled. Completed changes have been kept.'};
    message.textContent = labels[job.phase] || job.phase;
    const done = job.phase === 'preview' ? job.preview_cursor : ['ready'].includes(job.phase) ? job.total : job.cursor;
    el('progress').value = job.total ? Math.round(done / job.total * 100) : 0;
    el('counts').replaceChildren();
    [['total', 'Total'], ['valid', 'Ready'], ['invalid', 'Invalid'], ['created', 'Created'], ['updated', 'Updated'], ['skip', 'Skipped'], ['failed', 'Failed'], ['needs_review', 'Needs review']].forEach(([key, label]) => {
      const count = document.createElement('span'); count.textContent = label + ': ' + (job[key] || 0); el('counts').appendChild(count);
    });
    rows.replaceChildren();
    (job.rows || []).forEach(row => {
      const tr = document.createElement('tr');
      const details = [row.message || (row.fields || []).join(', ')].concat(row.mapping || [], row.warnings || []).filter(Boolean).join('\n');
      [row.index, row.title || '(untitled)', row.action || '—', row.state, details].forEach((text, index) => {
        const td = document.createElement('td');
        if (index === 1 && row.url) {
          let url;
          try { url = new URL(row.url, location.href); } catch (_) { /* Render an invalid URL as plain text. */ }
          if (url && url.origin === location.origin && /^https?:$/.test(url.protocol)) {
            const link = document.createElement('a'); link.href = url.href; link.textContent = text;
            link.target = '_blank'; link.rel = 'noopener'; td.appendChild(link);
          } else td.textContent = text;
        } else td.textContent = text;
        tr.appendChild(td);
      });
      rows.appendChild(tr);
    });
  }

  async function drive(command, body, follow) {
    if (busy) { pending = command; automatic = false; render(); return; }
    busy = true; automatic = !!follow; render();
    try {
      while (command) {
        job = await request(command, body); body = null; render();
        if (pending) { command = pending; pending = ''; automatic = false; }
        else if (automatic && job.phase === 'preview') command = 'preview';
        else if (automatic && job.phase === 'running') command = 'batch';
        else command = '';
        if (command) await new Promise(resolve => setTimeout(resolve, 50));
      }
    } catch (error) {
      automatic = false; pending = '';
      // A response can be lost after a write. Refresh state, never retry the mutation automatically.
      try { job = await request('status'); } catch (_) { /* Keep last known state. */ }
      busy = false; render(); result.hidden = false;
      message.textContent = error.message + ' Processing stopped. Continue saved progress or reload to review the latest state.';
      return;
    } finally { busy = false; automatic = false; }
    render();
  }

  form.addEventListener('submit', event => {
    event.preventDefault();
    if (busy || !form.reportValidity()) return;
    const file = form.elements.content_json.files[0];
    if (!file || !/\.json$/i.test(file.name) || file.size > 5242880) {
      result.hidden = false; message.textContent = 'Choose a .json file no larger than 5 MB.'; return;
    }
    drive('upload', new FormData(form), true);
  });
  el('start').addEventListener('click', () => {
    if (busy || job.phase !== 'ready') return;
    const prompt = config.postId ? 'Import into this page using ' + (job.rows[0]?.policy === 'replace' ? 'Replace supplied fields' : 'Fill empty fields only') + '? Review the field mappings and media notes before proceeding.' : 'Import ' + job.valid + ' valid items? Existing content will be changed in update modes. ' + (job.invalid || 0) + ' invalid item(s) will be excluded.';
    if (!window.confirm(prompt)) return;
    drive('start', null, true);
  });
  el('pause').addEventListener('click', () => drive('pause', null, false));
  el('cancel').addEventListener('click', () => {
    if (window.confirm('Cancel the remaining work? Completed changes will be kept.')) drive('cancel', null, false);
  });
  el('continue').addEventListener('click', async () => {
    if (busy) return;
    busy = true; render();
    try { job = await request('status'); }
    catch (error) { busy = false; render(); result.hidden = false; message.textContent = error.message; return; }
    busy = false;
    if (job.phase === 'paused') drive('resume', null, true);
    else if (job.phase === 'running') drive('batch', null, true);
    else if (job.phase === 'preview') drive('preview', null, true);
    else render();
  });
  el('report').addEventListener('click', () => {
    if (!job.token) return;
    const report = Object.assign({}, job); delete report.token;
    const url = URL.createObjectURL(new Blob([JSON.stringify(report, null, 2)], {type: 'application/json'}));
    const link = document.createElement('a'); link.href = url; link.download = 'cff-content-import-report.json';
    link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
  });
  window.addEventListener('beforeunload', event => {
    if (busy && job.phase === 'running') { event.preventDefault(); event.returnValue = ''; }
  });
  drive('status', null, false);
}());
