(function ($) {
  'use strict';

  const config = window.gm2ElementorControls || {};
  if (!config.ajaxUrl) {
    return;
  }

  const cache = {};

  function fetchOptions(action, payload) {
    const data = Object.assign({ action, nonce: config.nonce }, payload);
    const key = JSON.stringify(data);
    if (!cache[key]) {
      cache[key] = $.post(config.ajaxUrl, data)
        .then((response) => {
          if (!response || !response.success || !Array.isArray(response.data)) {
            return [];
          }
          return response.data;
        })
        .catch(() => []);
    }
    return cache[key];
  }

  function parseSelected($select) {
    const attr = $select.attr('data-selected');
    if (attr) {
      try {
        const parsed = JSON.parse(attr);
        if (Array.isArray(parsed)) {
          return parsed.map(String);
        }
        if (parsed === null || parsed === undefined || parsed === '') {
          return [];
        }
        return [String(parsed)];
      } catch (e) {
        // Ignore malformed JSON and fall through.
      }
    }
    const value = $select.val();
    if (Array.isArray(value)) {
      return value.map(String);
    }
    if (value === null || value === undefined || value === '') {
      return [];
    }
    return [String(value)];
  }

  function setSelected($select, selected) {
    if (!Array.isArray(selected)) {
      selected = selected === undefined ? [] : [selected];
    }

    if ($select.prop('multiple')) {
      $select.val(selected);
    } else {
      $select.val(selected.length ? selected[0] : '');
    }

    const stored = $select.prop('multiple') ? $select.val() || [] : $select.val() || '';
    $select.attr('data-selected', JSON.stringify(stored));
  }

  function findControlValue(name, $select) {
    if (!name) {
      return null;
    }
    const $container = $select.closest('.elementor-control').parent();
    const $inputs = $container.find('[data-setting="' + name + '"]');
    if (!$inputs.length) {
      return null;
    }
    const $input = $inputs.first();
    const value = $input.val();
    if ($input.prop('multiple')) {
      return value || [];
    }
    return value;
  }

  function refreshSelect($select) {
    const action = $select.data('action');
    if (!action) {
      return;
    }

    const payload = {};
    const mode = $select.data('mode');
    if (mode) {
      payload.mode = mode;
    }

    const taxonomyControl = $select.data('taxonomyControl');
    if (taxonomyControl) {
      const taxonomyValue = findControlValue(taxonomyControl, $select);
      if (taxonomyValue) {
        payload.taxonomy = taxonomyValue;
      }
    }

    const postTypeControl = $select.data('postTypeControl');
    if (postTypeControl) {
      let postTypes = findControlValue(postTypeControl, $select);
      if (postTypes) {
        if (!Array.isArray(postTypes)) {
          postTypes = [postTypes];
        }
        payload.post_types = postTypes.filter((item) => item !== '');
      }
    }

    const selected = parseSelected($select);

    fetchOptions(action, payload).then((options) => {
      const normalizedSelected = selected.map(String);
      const preserve = normalizedSelected.length ? normalizedSelected : parseSelected($select);
      $select.empty();
      options.forEach((option) => {
        const value = option.value !== undefined ? option.value : option.id;
        const label = option.label !== undefined ? option.label : option.text;
        const $option = $('<option></option>').attr('value', value).text(label);
        if (preserve.indexOf(String(value)) !== -1) {
          $option.prop('selected', true);
        }
        $select.append($option);
      });
      setSelected($select, preserve);
      $select.trigger('change');
    });
  }

  function bindSelect($select) {
    if ($select.data('gm2Bound')) {
      return;
    }
    $select.data('gm2Bound', true);

    const taxonomyControl = $select.data('taxonomyControl');
    if (taxonomyControl) {
      const $container = $select.closest('.elementor-control').parent();
      $container.on('change', '[data-setting="' + taxonomyControl + '"]', () => {
        refreshSelect($select);
      });
    }

    const postTypeControl = $select.data('postTypeControl');
    if (postTypeControl) {
      const $container = $select.closest('.elementor-control').parent();
      $container.on('change', '[data-setting="' + postTypeControl + '"]', () => {
        refreshSelect($select);
      });
    }

    refreshSelect($select);
  }

  function scan(node) {
    $(node)
      .find('.gm2-ajax-select')
      .addBack('.gm2-ajax-select')
      .each(function () {
        bindSelect($(this));
      });
  }

  $(function () {
    scan(document.body);
  });

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.type === 'childList') {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === 1) {
            scan(node);
          }
        });
      }
    });
  });

  observer.observe(document.body, { childList: true, subtree: true });
})(jQuery);
