/**
 * dotmgiftcard — pagina di acquisto: anteprima dal vivo, campi per la modalità di consegna,
 * tagli digitali/fisici, filtro per occasione, contatore del messaggio.
 * Senza JavaScript il modulo funziona comunque (tutti i campi visibili, controlli lato server).
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  ready(function () {
    var form = document.getElementById('dgc-buy');
    if (!form) {
      return;
    }

    var card = form.querySelector('[data-dgc-card]');
    var outs = {};
    Array.prototype.forEach.call(form.querySelectorAll('[data-dgc-out]'), function (el) {
      outs[el.getAttribute('data-dgc-out')] = el;
    });

    function checked(name) {
      return form.querySelector('input[name="' + name + '"]:checked');
    }

    function setText(key, value) {
      var el = outs[key];
      if (!el) {
        return;
      }
      var prefix = el.getAttribute('data-prefix');
      value = (value || '').trim();
      el.textContent = value && prefix ? prefix + ' ' + value : value;
      el.hidden = !value;
    }

    // modello
    function updateTemplate() {
      var tpl = checked('id_template');
      if (tpl && card) {
        card.style.backgroundImage = 'url("' + tpl.getAttribute('data-image') + '")';
        card.style.color = tpl.getAttribute('data-color') || '#ffffff';
      }
    }

    // consegna: campi email e tagli digitali/fisici
    function updateDelivery() {
      var delivery = (checked('delivery') || {}).value || '';
      Array.prototype.forEach.call(form.querySelectorAll('[data-show-for]'), function (el) {
        var show = el.getAttribute('data-show-for') === delivery;
        el.hidden = !show;
        Array.prototype.forEach.call(el.querySelectorAll('input'), function (input) {
          input.required = show && input.type !== 'date';
        });
      });
      var kind = delivery === 'post' ? 'physical' : 'digital';
      Array.prototype.forEach.call(form.querySelectorAll('.dgc-amounts'), function (group) {
        var active = group.getAttribute('data-kind') === kind;
        group.hidden = !active;
        Array.prototype.forEach.call(group.querySelectorAll('input'), function (input) {
          input.disabled = !active;
        });
        if (active && !group.querySelector('input:checked')) {
          var first = group.querySelector('input');
          if (first) {
            first.checked = true;
          }
        }
      });
      updateAmount();
    }

    function updateAmount() {
      var amount = checked('id_product');
      setText('amount', amount ? amount.getAttribute('data-amount') : '');
    }

    // testi
    function updateTexts() {
      setText('recipient', form.elements.recipient_name.value);
      setText('sender', form.elements.sender_name.value);
      setText('message', form.elements.message.value);
      var counter = form.querySelector('[data-dgc-counter]');
      if (counter) {
        counter.textContent = form.elements.message.value.length + ' / ' + counter.getAttribute('data-max');
      }
    }

    // conferma email
    function checkEmails() {
      var email = form.elements.recipient_email;
      var confirm = form.elements.recipient_email_confirm;
      if (!email || !confirm) {
        return;
      }
      var mismatch = confirm.value !== '' && email.value.trim().toLowerCase() !== confirm.value.trim().toLowerCase();
      confirm.classList.toggle('is-invalid', mismatch);
      confirm.setCustomValidity(mismatch ? ' ' : '');
    }

    form.addEventListener('change', function (event) {
      var name = event.target.name;
      if (name === 'id_template') {
        updateTemplate();
      } else if (name === 'delivery') {
        updateDelivery();
      } else if (name === 'id_product') {
        updateAmount();
      }
    });
    form.addEventListener('input', function (event) {
      if (event.target.hasAttribute('data-dgc-preview')) {
        updateTexts();
      }
      if (event.target.name === 'recipient_email' || event.target.name === 'recipient_email_confirm') {
        checkEmails();
      }
    });

    // filtro per occasione
    var filters = form.querySelector('[data-dgc-filters]');
    if (filters) {
      filters.hidden = false;
      filters.addEventListener('click', function (event) {
        var chip = event.target.closest('[data-occasion]');
        if (!chip) {
          return;
        }
        var occasion = chip.getAttribute('data-occasion');
        Array.prototype.forEach.call(filters.querySelectorAll('.dgc-chip'), function (c) {
          c.classList.toggle('is-active', c === chip);
        });
        Array.prototype.forEach.call(form.querySelectorAll('.dgc-tpl'), function (tpl) {
          tpl.hidden = occasion !== '' && tpl.getAttribute('data-occasion') !== occasion;
        });
      });
    }

    Array.prototype.forEach.call(form.querySelectorAll('[data-dgc-nojs]'), function (el) {
      el.hidden = true;
    });

    updateTemplate();
    updateDelivery();
    updateTexts();
  });
})();
