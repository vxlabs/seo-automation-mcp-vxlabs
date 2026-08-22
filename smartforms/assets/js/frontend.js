(function () {
  'use strict';

  function waitForRecaptcha(callback, tries) {
    if (window.grecaptcha && window.grecaptcha.render) return callback();
    if ((tries || 0) > 100) return;
    window.setTimeout(function () { waitForRecaptcha(callback, (tries || 0) + 1); }, 100);
  }

  function renderCaptcha(form) {
    const mode = form.dataset.captchaMode;
    const container = form.querySelector('.smartforms-recaptcha');
    if (!container || !['v2_checkbox', 'v2_invisible'].includes(mode)) return;
    waitForRecaptcha(function () {
      if (container.dataset.widgetId) return;
      const widgetId = window.grecaptcha.render(container, {
        sitekey: form.dataset.siteKey,
        size: container.dataset.size,
        callback: function (token) {
          form.querySelector('[name="recaptcha_token"]').value = token;
          if (mode === 'v2_invisible') submit(form, true);
        },
        'expired-callback': function () { form.querySelector('[name="recaptcha_token"]').value = ''; }
      });
      container.dataset.widgetId = widgetId;
    });
  }

  function payload(form) {
    const data = { fields: {} };
    new FormData(form).forEach(function (value, key) {
      const match = key.match(/^fields\[([^\]]+)\](\[\])?$/);
      if (match) {
        if (match[2]) {
          data.fields[match[1]] = data.fields[match[1]] || [];
          data.fields[match[1]].push(value);
        } else data.fields[match[1]] = value;
      } else data[key] = value;
    });
    return data;
  }

  function clearErrors(form) {
    form.querySelectorAll('.smartforms-error').forEach(function (node) { node.textContent = ''; });
    form.querySelectorAll('.has-error').forEach(function (node) { node.classList.remove('has-error'); });
  }

  async function submit(form, captchaReady) {
    if (form.dataset.submitting === '1') return;
    clearErrors(form);
    const mode = form.dataset.captchaMode;
    const tokenInput = form.querySelector('[name="recaptcha_token"]');

    if (mode === 'v2_invisible' && !captchaReady && !tokenInput.value) {
      const container = form.querySelector('.smartforms-recaptcha');
      if (container && container.dataset.widgetId !== undefined) window.grecaptcha.execute(Number(container.dataset.widgetId));
      return;
    }
    if (mode === 'v3' && !captchaReady) {
      form.dataset.submitting = '1';
      try {
		await new Promise(function (resolve, reject) {
		  let attempts = 0;
		  (function poll() {
		    if (window.grecaptcha && window.grecaptcha.ready) return window.grecaptcha.ready(resolve);
		    if (++attempts > 100) return reject(new Error('reCAPTCHA did not load.'));
		    window.setTimeout(poll, 100);
		  }());
		});
        const token = await window.grecaptcha.execute(form.dataset.siteKey, { action: 'smartforms_submit' });
        tokenInput.value = token;
      } catch (e) {
        form.dataset.submitting = '0';
        form.querySelector('.smartforms-response').textContent = 'reCAPTCHA could not be loaded. Please try again.';
        return;
      }
      form.dataset.submitting = '0';
    }

    form.dataset.submitting = '1';
    const button = form.querySelector('[type="submit"]');
    const original = button ? button.textContent : '';
    if (button) { button.disabled = true; button.textContent = (window.smartFormsConfig && smartFormsConfig.submitting) || 'Submitting…'; }

    try {
      const id = form.closest('.smartforms-wrap').dataset.formId;
      const response = await fetch(smartFormsConfig.restUrl + id + '/submit', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin', body: JSON.stringify(payload(form))
      });
      const result = await response.json();
      if (!response.ok) {
        if (result.fields) Object.keys(result.fields).forEach(function (fieldId) {
          const field = form.querySelector('[data-field-id="' + CSS.escape(fieldId) + '"]');
          if (field) { field.classList.add('has-error'); field.querySelector('.smartforms-error').textContent = result.fields[fieldId]; }
        });
        throw new Error(result.message || 'The form could not be submitted.');
      }
      if (result.redirect) { window.location.assign(result.redirect); return; }
      form.reset(); tokenInput.value = '';
      form.querySelector('.smartforms-response').textContent = result.message;
      if (mode.indexOf('v2_') === 0) {
        const container = form.querySelector('.smartforms-recaptcha');
        if (container && container.dataset.widgetId !== undefined) window.grecaptcha.reset(Number(container.dataset.widgetId));
      }
    } catch (error) {
      form.querySelector('.smartforms-response').textContent = error.message;
    } finally {
      form.dataset.submitting = '0';
      if (button) { button.disabled = false; button.textContent = original; }
    }
  }

  document.querySelectorAll('.smartforms-form').forEach(function (form) {
    renderCaptcha(form);
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!form.reportValidity()) return;
      submit(form, false);
    });
  });
}());
