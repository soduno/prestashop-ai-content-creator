(function () {
  'use strict';

  var MODAL_ID = 'soduno-ai-generate-modal';
  var OVERLAY_ID = 'soduno-ai-generate-overlay';
  var TARGET_LABEL_ID = 'soduno-ai-target-label';
  var TITLE_SELECTORS = [
    '#product_header_name_1',
    '#form_step1_name_1',
    'input[name="product[header][name][1]"]',
    'input[name="product[name][1]"]',
    'input[id^="product_header_name_"]',
    'input[id^="form_step1_name_"]'
  ];
  var activeTarget = '';
  var isTitleListenerBound = false;

  function findField(selectors) {
    for (var i = 0; i < selectors.length; i += 1) {
      var field = document.querySelector(selectors[i]);
      if (field) {
        return field;
      }
    }

    return null;
  }

  function findInsertContainer(summaryField) {
    return summaryField.closest('.form-group')
      || summaryField.closest('.form_field')
      || summaryField.closest('.col')
      || summaryField.parentElement;
  }

  function findTitleField() {
    return findField(TITLE_SELECTORS);
  }

  function hasTitleValue() {
    var titleField = findTitleField();
    if (!titleField) {
      return false;
    }

    return String(titleField.value || '').trim() !== '';
  }

  function updateGenerateButtonsState() {
    var enabled = hasTitleValue();
    var buttons = document.querySelectorAll('.soduno-ai-generate-button');

    for (var i = 0; i < buttons.length; i += 1) {
      buttons[i].disabled = !enabled;
      if (enabled) {
        buttons[i].removeAttribute('title');
      } else {
        buttons[i].setAttribute('title', 'Please enter product title first.');
      }
    }
  }

  function bindTitleListener() {
    if (isTitleListenerBound) {
      return;
    }

    var titleField = findTitleField();
    if (!titleField) {
      return;
    }

    titleField.addEventListener('input', updateGenerateButtonsState);
    titleField.addEventListener('change', updateGenerateButtonsState);
    isTitleListenerBound = true;
  }

  function buildModal() {
    if (document.getElementById(MODAL_ID)) {
      return;
    }

    var overlay = document.createElement('div');
    overlay.id = OVERLAY_ID;
    overlay.className = 'soduno-ai-overlay soduno-ai-hidden';

    var modal = document.createElement('div');
    modal.id = MODAL_ID;
    modal.className = 'soduno-ai-modal soduno-ai-hidden';

    modal.innerHTML = ''
      + '<div class="soduno-ai-modal-header">'
      + '  <h3>AI Generate</h3>'
      + '  <button type="button" class="soduno-ai-close" aria-label="Close">&times;</button>'
      + '</div>'
      + '<div class="soduno-ai-modal-body">'
      + '  <p id="' + TARGET_LABEL_ID + '" class="soduno-ai-target-label"></p>'
      + '  <label for="soduno-ai-word-count">Words</label>'
      + '  <select id="soduno-ai-word-count" class="form-control">'
      + '    <option value="30">30 words</option>'
      + '    <option value="50" selected>50 words</option>'
      + '    <option value="80">80 words</option>'
      + '    <option value="120">120 words</option>'
      + '    <option value="200">200 words</option>'
      + '  </select>'
      + '  <div id="soduno-ai-preview" class="soduno-ai-preview"></div>'
      + '</div>'
      + '<div class="soduno-ai-modal-actions">'
      + '  <button type="button" class="btn btn-primary" id="soduno-ai-generate-action">Generate</button>'
      + '  <button type="button" class="btn btn-default soduno-ai-cancel">Cancel</button>'
      + '</div>';

    document.body.appendChild(overlay);
    document.body.appendChild(modal);

    overlay.addEventListener('click', closeModal);
    modal.querySelector('.soduno-ai-close').addEventListener('click', closeModal);
    modal.querySelector('.soduno-ai-cancel').addEventListener('click', closeModal);

    modal.querySelector('#soduno-ai-generate-action').addEventListener('click', function () {
      var wordCount = parseInt(modal.querySelector('#soduno-ai-word-count').value, 10) || 50;
      var preview = modal.querySelector('#soduno-ai-preview');

      preview.textContent = 'Frontend ready: generation for ' + activeTarget + ' (' + wordCount + ' words) will be connected in backend next.';
      preview.classList.add('soduno-ai-preview-visible');
    });
  }

  function openModal(targetName) {
    if (!hasTitleValue()) {
      return;
    }

    buildModal();

    var overlay = document.getElementById(OVERLAY_ID);
    var modal = document.getElementById(MODAL_ID);
    var targetLabel = document.getElementById(TARGET_LABEL_ID);

    if (!overlay || !modal) {
      return;
    }

    activeTarget = targetName;
    if (targetLabel) {
      targetLabel.textContent = 'Target: ' + targetName;
    }

    overlay.classList.remove('soduno-ai-hidden');
    modal.classList.remove('soduno-ai-hidden');
  }

  function closeModal() {
    var overlay = document.getElementById(OVERLAY_ID);
    var modal = document.getElementById(MODAL_ID);

    if (overlay) {
      overlay.classList.add('soduno-ai-hidden');
    }
    if (modal) {
      modal.classList.add('soduno-ai-hidden');
    }
  }

  function injectButton(target) {
    if (document.getElementById(target.wrapperId)) {
      return;
    }

    var field = findField(target.selectors);
    if (!field) {
      return;
    }

    var container = findInsertContainer(field);
    if (!container || !container.parentNode) {
      return;
    }

    var wrapper = document.createElement('div');
    wrapper.id = target.wrapperId;
    wrapper.className = 'soduno-ai-generate-wrapper';

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline-secondary soduno-ai-generate-button';
    button.textContent = 'AI Generate';
    button.addEventListener('click', function () {
      openModal(target.label);
    });

    wrapper.appendChild(button);

    if (container.nextSibling) {
      container.parentNode.insertBefore(wrapper, container.nextSibling);
    } else {
      container.parentNode.appendChild(wrapper);
    }

    updateGenerateButtonsState();
  }

  function boot() {
    var targets = [
      {
        label: 'Summary',
        wrapperId: 'soduno-ai-generate-wrapper-summary',
        selectors: [
          '#product_description_description_short'
        ]
      },
      {
        label: 'Description',
        wrapperId: 'soduno-ai-generate-wrapper-description',
        selectors: [
          '#product_description_description',
        ]
      },
      {
        label: 'SEO Meta Title',
        wrapperId: 'soduno-ai-generate-wrapper-seo-meta-title',
        selectors: [
          '#product_seo_meta_title_1'
        ]
      },
      {
        label: 'SEO Meta Description',
        wrapperId: 'soduno-ai-generate-wrapper-seo-meta-description',
        selectors: [
          '#product_seo_meta_description_1'
        ]
      }
    ];

    for (var i = 0; i < targets.length; i += 1) {
      injectButton(targets[i]);
    }
    bindTitleListener();
    updateGenerateButtonsState();

    var tries = 0;
    var timer = setInterval(function () {
      for (var i = 0; i < targets.length; i += 1) {
        injectButton(targets[i]);
      }
      bindTitleListener();
      updateGenerateButtonsState();
      tries += 1;

      var allDone = true;
      for (var j = 0; j < targets.length; j += 1) {
        if (!document.getElementById(targets[j].wrapperId)) {
          allDone = false;
          break;
        }
      }

      if (allDone || tries > 30) {
        clearInterval(timer);
      }
    }, 500);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
