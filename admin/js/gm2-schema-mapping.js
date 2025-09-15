jQuery(function ($) {
    const data = window.gm2SchemaMap || {};
    const $cpt = $('#gm2-schema-cpt');
    const $type = $('#gm2-schema-type');
    const $table = $('#gm2-schema-table tbody');
    const $preset = $('#gm2-preset');

    function addRow(prop = '', field = '') {
        const row = $('<tr>\
            <td><input type="text" class="gm2-prop" value="' + prop + '" /></td>\
            <td><input type="text" class="gm2-field" list="gm2-field-options" value="' + field + '" /></td>\
            <td><button type="button" class="button gm2-remove">&times;</button></td>\
        </tr>');
        $table.append(row);
    }

    function render(resp) {
        $type.val(resp.type || '');
        const list = $('#gm2-field-options').empty();
        (resp.fields || []).forEach(function (k) {
            list.append('<option value="' + k + '"></option>');
        });
        $table.empty();
        const map = resp.map || {};
        Object.keys(map).forEach(function (p) {
            addRow(p, map[p]);
        });
    }

    function fetchMap() {
        const slug = $cpt.val();
        if (!slug) {
            return;
        }
        $.get(data.ajax, { action: 'gm2_get_schema_map', nonce: data.nonce, cpt: slug }, function (r) {
            if (r && r.success) {
                render(r.data);
            } else {
                render({ type: '', map: {}, fields: [] });
            }
        });
    }

    $('#gm2-add-row').on('click', function (e) {
        e.preventDefault();
        addRow();
    });

    $table.on('click', '.gm2-remove', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    $cpt.on('change', fetchMap);

    $preset.on('change', function () {
        const val = $(this).val();
        if (!val || !data.presets || !data.presets[val]) {
            return;
        }
        $type.val(val);
        $table.empty();
        data.presets[val].forEach(function (p) {
            addRow(p, '');
        });
    });

    $('#gm2-save-schema').on('click', function (e) {
        e.preventDefault();
        const slug = $cpt.val();
        if (!slug) {
            return;
        }
        const map = {};
        $table.find('tr').each(function () {
            const prop = $(this).find('.gm2-prop').val();
            const field = $(this).find('.gm2-field').val();
            if (prop && field) {
                map[prop] = field;
            }
        });
        const payload = {
            action: 'gm2_save_schema_map',
            nonce: data.nonce,
            cpt: slug,
            type: $type.val(),
            map: JSON.stringify(map),
        };
        $.post(data.ajax, payload, function (r) {
            if (r && r.success) {
                alert(data.saved || 'Saved');
            }
        });
    });

    if (data.postTypes) {
        Object.keys(data.postTypes).forEach(function (slug) {
            $cpt.append('<option value="' + slug + '">' + data.postTypes[slug] + '</option>');
        });
    }

    if ($cpt.find('option').length) {
        $cpt.val($cpt.find('option:first').val());
        fetchMap();
    }
});

