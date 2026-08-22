(function () {
  'use strict';

  const input = document.getElementById('smartforms-schema');
  const list = document.getElementById('smartforms-field-list');
  const palette = document.getElementById('smartforms-field-palette');
  const canvas = document.querySelector('.smartforms-canvas');
  if (!input || !list || !palette || !canvas || !window.smartFormsBuilder) return;

  let schema;
  try { schema = JSON.parse(input.value); } catch (e) { schema = { version: 1, fields: [] }; }
  schema.fields = Array.isArray(schema.fields) ? schema.fields : [];
  let draggingIndex = null;
  let draggingType = null;
  let pendingDropIndex = null;

  function id() {
    return 'fld_' + Math.random().toString(36).slice(2, 10);
  }

  function defaultField(type) {
    const def = smartFormsBuilder.types[type];
    return {
      id: id(), type: type, label: type === 'button' ? 'Submit' : def.label,
      placeholder: '', help: '', default: '', required: type === 'gdpr', width: 100,
      css_class: '', error_message: '', options: ['Option 1', 'Option 2'],
      min: '', max: '', max_length: '', content: type === 'heading' ? 'Form section' : '', image_url: ''
    };
  }

  function sync() {
    input.value = JSON.stringify(schema);
    document.querySelector('.smartforms-empty').hidden = schema.fields.length > 0;
  }

  function textControl(label, key, field, type) {
    const value = field[key] === undefined ? '' : field[key];
    return '<label>' + label + '<input type="' + (type || 'text') + '" data-key="' + key + '" value="' + escapeAttr(value) + '"></label>';
  }

  function escapeAttr(value) {
    return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function render() {
    list.innerHTML = '';
    schema.fields.forEach(function (field, index) {
      const def = smartFormsBuilder.types[field.type] || { label: field.type };
      const card = document.createElement('article');
      card.className = 'smartforms-field-card';
      card.dataset.index = index;
      card.draggable = true;
      let controls = '<div class="smartforms-field-card-head"><span class="dashicons dashicons-move smartforms-drag-handle" title="Drag to reorder" aria-hidden="true"></span><strong>' + escapeAttr(def.label) + '</strong><span>' + escapeAttr(field.id) + '</span><div><button type="button" data-action="up" title="Move up">↑</button><button type="button" data-action="down" title="Move down">↓</button><button type="button" data-action="remove" title="Remove">×</button></div></div><div class="smartforms-field-controls">';

      if (field.type === 'heading') {
        controls += textControl('Heading', 'content', field);
      } else if (field.type === 'image') {
        controls += textControl('Image URL', 'image_url', field, 'url') + textControl('Alt text', 'label', field);
      } else if (field.type === 'separator' || field.type === 'icon') {
        controls += textControl('CSS class', 'css_class', field);
      } else {
        controls += textControl(field.type === 'button' ? 'Button text' : 'Label', 'label', field);
        if (!['button', 'gdpr', 'radio', 'checkbox'].includes(field.type)) controls += textControl('Placeholder', 'placeholder', field);
        if (!['button'].includes(field.type)) controls += textControl('Help text', 'help', field);
        if (['radio', 'checkbox', 'select'].includes(field.type)) controls += '<label class="smartforms-wide">Options (one per line)<textarea data-key="options">' + escapeAttr((field.options || []).join('\n')) + '</textarea></label>';
        if (['text', 'email', 'url', 'textarea', 'phone', 'address'].includes(field.type)) controls += textControl('Maximum length', 'max_length', field, 'number');
        if (field.type === 'number') controls += textControl('Minimum', 'min', field, 'number') + textControl('Maximum', 'max', field, 'number');
        if (!['button'].includes(field.type)) controls += textControl('Required error override', 'error_message', field);
        if (!['button'].includes(field.type)) controls += '<label class="smartforms-check"><input type="checkbox" data-key="required" ' + (field.required ? 'checked' : '') + '> Required</label>';
      }
      controls += '<label>Width<select data-key="width">' + [25, 50, 75, 100].map(function (width) { return '<option value="' + width + '" ' + (Number(field.width) === width ? 'selected' : '') + '>' + width + '%</option>'; }).join('') + '</select></label>';
      controls += textControl('CSS class', 'css_class', field) + '</div>';
      card.innerHTML = controls;
      list.appendChild(card);
    });
    sync();
  }

  Object.keys(smartFormsBuilder.types).forEach(function (type) {
    const def = smartFormsBuilder.types[type];
    const button = document.createElement('button');
    button.type = 'button'; button.className = 'smartforms-palette-item'; button.dataset.type = type;
    button.draggable = true;
    button.title = 'Drag ' + def.label + ' into the form';
    button.innerHTML = '<span class="dashicons dashicons-' + escapeAttr(def.icon) + '"></span><span>' + escapeAttr(def.label) + '</span>';
    palette.appendChild(button);
  });

  palette.addEventListener('click', function (event) {
    const button = event.target.closest('[data-type]');
    if (!button) return;
    schema.fields.push(defaultField(button.dataset.type)); render();
  });

  list.addEventListener('input', function (event) {
    const card = event.target.closest('.smartforms-field-card');
    const key = event.target.dataset.key;
    if (!card || !key) return;
    const field = schema.fields[Number(card.dataset.index)];
    if (key === 'options') field[key] = event.target.value.split(/\r?\n/).map(function (v) { return v.trim(); }).filter(Boolean);
    else if (key === 'required') field[key] = event.target.checked;
    else if (key === 'width') field[key] = Number(event.target.value);
    else field[key] = event.target.value;
    sync();
  });
  list.addEventListener('change', function (event) { event.target.dispatchEvent(new Event('input', { bubbles: true })); });

  list.addEventListener('click', function (event) {
    const button = event.target.closest('[data-action]');
    const card = event.target.closest('.smartforms-field-card');
    if (!button || !card) return;
    const index = Number(card.dataset.index);
    if (button.dataset.action === 'remove') schema.fields.splice(index, 1);
    if (button.dataset.action === 'up' && index > 0) [schema.fields[index - 1], schema.fields[index]] = [schema.fields[index], schema.fields[index - 1]];
    if (button.dataset.action === 'down' && index < schema.fields.length - 1) [schema.fields[index + 1], schema.fields[index]] = [schema.fields[index], schema.fields[index + 1]];
    render();
  });

  function dropIndexAt(clientY) {
    const cards = Array.from(list.querySelectorAll('.smartforms-field-card:not(.is-dragging)'));
    for (let i = 0; i < cards.length; i += 1) {
      const box = cards[i].getBoundingClientRect();
      if (clientY < box.top + box.height / 2) return Number(cards[i].dataset.index);
    }
    return schema.fields.length;
  }

  function clearDropState() {
    canvas.classList.remove('is-drag-over', 'is-drop-at-end');
    list.querySelectorAll('.is-drop-before').forEach(function (card) { card.classList.remove('is-drop-before'); });
    pendingDropIndex = null;
  }

  function showDropState(index) {
    canvas.classList.add('is-drag-over');
    list.querySelectorAll('.is-drop-before').forEach(function (card) { card.classList.remove('is-drop-before'); });
    canvas.classList.toggle('is-drop-at-end', index >= schema.fields.length);
    const target = list.querySelector('[data-index="' + index + '"]');
    if (target && !target.classList.contains('is-dragging')) target.classList.add('is-drop-before');
  }

  palette.addEventListener('dragstart', function (event) {
    const button = event.target.closest('[data-type]');
    if (!button) return;
    draggingType = button.dataset.type;
    draggingIndex = null;
    event.dataTransfer.effectAllowed = 'copy';
    event.dataTransfer.setData('text/plain', 'smartforms-type:' + draggingType);
  });

  palette.addEventListener('dragend', function () {
    draggingType = null;
    clearDropState();
  });

  list.addEventListener('dragstart', function (event) {
    const card = event.target.closest('.smartforms-field-card');
    if (!card) return;
	if (!event.target.closest('.smartforms-field-card-head') || event.target.closest('button')) {
	  event.preventDefault();
	  return;
	}
    draggingIndex = Number(card.dataset.index);
    draggingType = null;
    card.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', 'smartforms-index:' + draggingIndex);
  });

  list.addEventListener('dragend', function () {
    draggingIndex = null;
    list.querySelectorAll('.is-dragging').forEach(function (card) { card.classList.remove('is-dragging'); });
    clearDropState();
  });

  canvas.addEventListener('dragover', function (event) {
    if (draggingType === null && draggingIndex === null) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = draggingType !== null ? 'copy' : 'move';
    pendingDropIndex = dropIndexAt(event.clientY);
    showDropState(pendingDropIndex);
  });

  canvas.addEventListener('dragleave', function (event) {
    if (!canvas.contains(event.relatedTarget)) clearDropState();
  });

  canvas.addEventListener('drop', function (event) {
    if (draggingType === null && draggingIndex === null) return;
    event.preventDefault();
    let targetIndex = pendingDropIndex === null ? schema.fields.length : pendingDropIndex;
    if (draggingType !== null) {
      schema.fields.splice(targetIndex, 0, defaultField(draggingType));
    } else {
      const moved = schema.fields.splice(draggingIndex, 1)[0];
      if (targetIndex > draggingIndex) targetIndex -= 1;
      schema.fields.splice(Math.max(0, targetIndex), 0, moved);
    }
    draggingIndex = null;
    draggingType = null;
    clearDropState();
    render();
  });

  render();
}());
