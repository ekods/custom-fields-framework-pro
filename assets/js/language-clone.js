(function () {
  'use strict';
  const form = document.getElementById('cff-language-clone-form');
  if (!form || typeof CFFLanguageClone === 'undefined') return;
  const config = CFFLanguageClone;
  const preview = document.getElementById('cff-clone-preview');
  const start = document.getElementById('cff-clone-start');
  const result = document.getElementById('cff-clone-result');
  const message = document.getElementById('cff-clone-message');
  const errors = document.getElementById('cff-clone-errors');
  const links = document.getElementById('cff-clone-links');
  const progress = document.getElementById('cff-clone-progress');
  const inputs = Array.from(form.querySelectorAll('select, input'));
  let job = '';
  let busy = false;
  let started = false;
  const startLabel = start.textContent;

  async function request(action, data) {
    data.set('action', action);
    data.set('nonce', config.nonce);
    const response = await fetch(config.ajax, { method: 'POST', body: data, credentials: 'same-origin' });
    let payload;
    try {
      payload = await response.json();
    } catch (error) {
      throw new Error(config.failed);
    }
    if (!response.ok || !payload.success) {
      throw new Error(payload.data && payload.data.message ? payload.data.message : config.failed);
    }
    return payload.data;
  }

  function setBusy(value) {
    busy = value;
    preview.disabled = value;
    start.disabled = value;
    inputs.forEach(function (input) { input.disabled = value || started; });
  }

  function render(data) {
    result.hidden = false;
    ['total', 'created', 'updated', 'skipped', 'failed'].forEach(function (key) {
      document.getElementById('cff-clone-' + key).textContent = data[key];
    });
    document.getElementById('cff-clone-updated-label').textContent = config.filled;
    progress.value = data.progress;
    errors.replaceChildren();
    (data.errors || []).forEach(function (error) {
      const item = document.createElement('li');
      item.textContent = error;
      errors.appendChild(item);
    });
    links.replaceChildren();
    (data.links || []).forEach(function (item) {
      if (!item.url) return;
      const link = document.createElement('a');
      link.href = item.url;
      link.textContent = item.title || item.url;
      link.target = '_blank';
      link.rel = 'noopener';
      links.appendChild(link);
      links.appendChild(document.createElement('br'));
    });
  }

  form.addEventListener('change', function () {
    if (busy || started) return;
    job = '';
    start.hidden = true;
    result.hidden = true;
  });

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (busy) return;
    const selection = new FormData(form);
    job = '';
    started = false;
    setBusy(true);
    start.hidden = true;
    result.hidden = false;
    message.textContent = config.preparing;
    try {
      const data = await request('cff_language_clone_prepare', selection);
      job = data.job;
      render(data);
      document.getElementById('cff-clone-skipped').textContent = data.existing;
      document.getElementById('cff-clone-updated').textContent = data.fillable;
      document.getElementById('cff-clone-updated-label').textContent = config.toFill;
      message.textContent = data.total ? config.ready : config.empty;
      start.textContent = startLabel;
      start.hidden = !data.total;
    } catch (error) {
      message.textContent = error.message;
    } finally {
      setBusy(false);
    }
  });

  start.addEventListener('click', async function () {
    if (busy || !job) return;
    started = true;
    setBusy(true);
    preview.hidden = true;
    try {
      let data;
      do {
        message.textContent = data && data.phase === 'finalize' ? config.finalizing : config.working;
        const body = new FormData();
        body.set('job', job);
        data = await request('cff_language_clone_batch', body);
        render(data);
      } while (data.phase !== 'done');
      message.textContent = data.failed ? config.doneWithErrors : config.done;
      start.hidden = true;
      job = '';
      started = false;
      preview.hidden = false;
    } catch (error) {
      message.textContent = error.message + ' ' + config.failed;
      start.textContent = config.retry;
    } finally {
      setBusy(false);
    }
  });

  window.addEventListener('beforeunload', function (event) {
    if (!busy || !started) return;
    event.preventDefault();
    event.returnValue = '';
  });
}());
