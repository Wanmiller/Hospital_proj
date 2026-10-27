'use strict';

const editHandlers = new Set(['editDept', 'editUser', 'editDoctor', 'editPatient', 'editApp', 'editRx']);
document.addEventListener('click', event => {
  const button = event.target.closest('[data-edit-handler]');
  if (!button) return;
  const handler = button.dataset.editHandler;
  if (editHandlers.has(handler) && typeof window[handler] === 'function') {
    window[handler](...JSON.parse(button.dataset.editValues));
  }
}, true);

document.addEventListener('submit', event => {
  const message = event.target.dataset.confirm;
  if (message && !window.confirm(message)) event.preventDefault();
});

// Keep native form feedback in Kazakh even when the browser uses another locale.
document.addEventListener('invalid', event => {
  const field = event.target;
  if (typeof field.setCustomValidity !== 'function') return;
  const messages = document.body.dataset;
  field.setCustomValidity(field.validity.valueMissing ? messages.requiredMessage
    : field.validity.typeMismatch && field.type === 'email' ? messages.emailMessage
    : messages.invalidMessage);
  field.classList.add('is-invalid');
}, true);
function clearFieldValidation(field) {
  if (typeof field.setCustomValidity !== 'function') return;
  const fields = field.type === 'radio' && field.form
    ? [...field.form.elements].filter(item => item.type === 'radio' && item.name === field.name)
    : [field];
  fields.forEach(item => {
    item.setCustomValidity('');
    item.classList.remove('is-invalid');
  });
}
document.addEventListener('input', event => clearFieldValidation(event.target));
document.addEventListener('change', event => clearFieldValidation(event.target));
document.addEventListener('show.bs.modal', event => {
  event.target.querySelectorAll('input, select, textarea').forEach(field => {
    field.setCustomValidity('');
    field.classList.remove('is-invalid');
  });
});

// Connect existing labels without changing form field names.
document.querySelectorAll('label:not([for])').forEach((label, index) => {
  const field = label.querySelector('input:not([type=hidden]), select, textarea')
    || label.parentElement.querySelector('input:not([type=hidden]), select, textarea');
  if (!field) return;
  if (!field.id) field.id = `form-field-${index}`;
  label.htmlFor = field.id;
});
document.querySelectorAll('.table-responsive').forEach(region => { region.tabIndex = 0; });
document.querySelectorAll('.modal').forEach(modal => {
  const title = modal.querySelector('.modal-title');
  if (title) {
    if (!title.id) title.id = `${modal.id}Label`;
    modal.setAttribute('aria-labelledby', title.id);
  }
});

const launch = document.getElementById('appointment-launch');
if (launch) {
  let trigger = null;
  if (launch.dataset.editId) {
    trigger = [...document.querySelectorAll('[data-edit-handler="editApp"]')]
      .find(button => String(JSON.parse(button.dataset.editValues)[0]) === launch.dataset.editId);
  }
  if (trigger) {
    trigger.click();
  } else if (launch.dataset.create === '1') {
    const opener = document.querySelector('[data-bs-target="#appModal"]:not([data-edit-handler])');
    if (opener) opener.click();
  }
}
