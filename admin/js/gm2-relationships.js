jQuery(function($){
    var config = window.gm2Relationships || {};
    var strings = config.strings || {};
    var ajaxUrl = config.ajax || (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '');
    var state = {
        relationships: $.isArray(config.relationships) ? config.relationships.slice() : []
    };

    function t(key, fallback){
        if (strings[key]) {
            return strings[key];
        }
        return fallback;
    }

    function showMessage(message, type){
        var container = $('#gm2-rel-messages');
        if (!container.length) {
            return;
        }
        if (!message) {
            container.hide();
            return;
        }
        container.removeClass('notice-error notice-success');
        container.addClass(type === 'success' ? 'notice-success' : 'notice-error');
        container.find('p').text(message);
        container.show();
    }

    function resetForm(){
        $('#gm2-rel-original').val('');
        $('#gm2-rel-type').val('');
        $('#gm2-rel-from').val('');
        $('#gm2-rel-to').val('');
        $('#gm2-rel-label').val('');
        $('#gm2-rel-reverse-label').val('');
        $('#gm2-rel-direction').val('');
        $('#gm2-rel-cardinality').val('');
        $('#gm2-rel-description').val('');
    }

    function openForm(rel){
        if (rel) {
            $('#gm2-rel-original').val(rel.type || '');
            $('#gm2-rel-type').val(rel.type || '');
            $('#gm2-rel-from').val(rel.from || '');
            $('#gm2-rel-to').val(rel.to || '');
            $('#gm2-rel-label').val(rel.label || '');
            $('#gm2-rel-reverse-label').val(rel.reverse_label || '');
            $('#gm2-rel-direction').val(rel.direction || '');
            $('#gm2-rel-cardinality').val(rel.cardinality || '');
            $('#gm2-rel-description').val(rel.description || '');
        } else {
            resetForm();
        }
        $('#gm2-relationship-form').stop(true, true).slideDown(200);
    }

    function hideForm(){
        $('#gm2-relationship-form').stop(true, true).slideUp(200, function(){
            resetForm();
        });
    }

    function findRelationship(type){
        var result = null;
        $.each(state.relationships, function(i, rel){
            if (rel && rel.type === type) {
                result = rel;
                return false;
            }
            return true;
        });
        return result;
    }

    function updateRelationships(list){
        if ($.isArray(list)) {
            state.relationships = list;
        } else {
            state.relationships = [];
        }
        renderList();
    }

    function renderList(){
        var tbody = $('#gm2-relationships-table tbody');
        if (!tbody.length) {
            return;
        }
        tbody.empty();
        if (!state.relationships.length) {
            var emptyRow = $('<tr/>');
            emptyRow.append(
                $('<td/>').attr('colspan', 6).text(t('noRelationships', 'No relationships defined.'))
            );
            tbody.append(emptyRow);
            return;
        }
        $.each(state.relationships, function(i, rel){
            if (!rel) {
                return;
            }
            var row = $('<tr/>');
            row.append($('<td/>').text(rel.type || ''));
            row.append($('<td/>').text(rel.from || ''));
            row.append($('<td/>').text(rel.to || ''));
            row.append($('<td/>').text(rel.label || ''));
            row.append($('<td/>').text(rel.cardinality || ''));
            var actions = $('<td/>');
            var editBtn = $('<button type="button" class="button-link gm2-rel-edit"/>').text(t('edit', 'Edit'));
            editBtn.data('rel', rel.type || '');
            var deleteBtn = $('<button type="button" class="button-link delete-link gm2-rel-delete"/>').text(t('delete', 'Delete'));
            deleteBtn.data('rel', rel.type || '');
            actions.append(editBtn).append(' ').append(deleteBtn);
            row.append(actions);
            tbody.append(row);
        });
    }

    $('#gm2-add-relationship').on('click', function(){
        showMessage('');
        openForm(null);
    });

    $(document).on('click', '.gm2-rel-edit', function(e){
        e.preventDefault();
        var type = $(this).data('rel');
        if (!type) {
            return;
        }
        var rel = findRelationship(type);
        if (rel) {
            showMessage('');
            openForm(rel);
        }
    });

    $('#gm2-rel-cancel').on('click', function(e){
        e.preventDefault();
        hideForm();
        showMessage('');
    });

    $('#gm2-rel-save').on('click', function(e){
        e.preventDefault();
        if (!ajaxUrl) {
            showMessage(t('errorSaving', 'Error saving relationship.'), 'error');
            return;
        }
        var button = $(this);
        if (button.prop('disabled')) {
            return;
        }
        showMessage('');
        var payload = {
            action: 'gm2_save_relationship',
            nonce: config.nonce || '',
            original: $('#gm2-rel-original').val() || '',
            type: $.trim($('#gm2-rel-type').val() || ''),
            from: $.trim($('#gm2-rel-from').val() || ''),
            to: $.trim($('#gm2-rel-to').val() || ''),
            label: $.trim($('#gm2-rel-label').val() || ''),
            reverse_label: $.trim($('#gm2-rel-reverse-label').val() || ''),
            direction: $.trim($('#gm2-rel-direction').val() || ''),
            cardinality: $('#gm2-rel-cardinality').val() || '',
            description: $.trim($('#gm2-rel-description').val() || '')
        };
        if (!payload.type || !payload.from || !payload.to) {
            showMessage(t('missingRequired', 'Relationship key, from, and to fields are required.'), 'error');
            return;
        }
        button.prop('disabled', true);
        $.post(ajaxUrl, payload)
            .done(function(response){
                if (response && response.success && response.data && $.isArray(response.data.relationships)) {
                    updateRelationships(response.data.relationships);
                    hideForm();
                    showMessage(t('saved', 'Relationship saved.'), 'success');
                } else {
                    var message = response && response.data ? response.data : t('errorSaving', 'Error saving relationship.');
                    if ($.isArray(message)) {
                        message = message.join(', ');
                    }
                    showMessage(message || t('errorSaving', 'Error saving relationship.'), 'error');
                }
            })
            .fail(function(){
                showMessage(t('errorSaving', 'Error saving relationship.'), 'error');
            })
            .always(function(){
                button.prop('disabled', false);
            });
    });

    $(document).on('click', '.gm2-rel-delete', function(e){
        e.preventDefault();
        if (!ajaxUrl) {
            showMessage(t('errorDeleting', 'Error deleting relationship.'), 'error');
            return;
        }
        var button = $(this);
        if (button.prop('disabled')) {
            return;
        }
        var type = button.data('rel');
        if (!type) {
            return;
        }
        if (!window.confirm(t('confirmDelete', 'Delete this relationship?'))) {
            return;
        }
        showMessage('');
        button.prop('disabled', true);
        $.post(ajaxUrl, {
            action: 'gm2_delete_relationship',
            nonce: config.deleteNonce || '',
            type: type
        })
            .done(function(response){
                if (response && response.success && response.data && $.isArray(response.data.relationships)) {
                    updateRelationships(response.data.relationships);
                    hideForm();
                    showMessage(t('deleted', 'Relationship deleted.'), 'success');
                } else {
                    var message = response && response.data ? response.data : t('errorDeleting', 'Error deleting relationship.');
                    if ($.isArray(message)) {
                        message = message.join(', ');
                    }
                    showMessage(message || t('errorDeleting', 'Error deleting relationship.'), 'error');
                }
            })
            .fail(function(){
                showMessage(t('errorDeleting', 'Error deleting relationship.'), 'error');
            })
            .always(function(){
                button.prop('disabled', false);
            });
    });

    updateRelationships(state.relationships);
});
