jQuery(function ($) {
    $('.gm2-gradient-field').each(function () {
        var $field = $(this);
        function updateGradient() {
            var start = $field.find('.gm2-gradient-start').val();
            var end = $field.find('.gm2-gradient-end').val();
            if (start && end) {
                $field.find('.gm2-gradient-preview').css('background', 'linear-gradient(' + start + ',' + end + ')');
            }
        }
        $field.find('.gm2-color').wpColorPicker({
            change: updateGradient,
            clear: updateGradient
        });
        updateGradient();
    });

    $('.gm2-icon-field').on('input', '.gm2-icon-input', function () {
        var cls = $(this).val();
        $(this).siblings('.gm2-icon-preview').attr('class', 'gm2-icon-preview dashicons ' + cls);
    });

    $('.gm2-badge-field').each(function () {
        var $field = $(this);
        function updateBadge() {
            var text = $field.find('.gm2-badge-text').val();
            var color = $field.find('.gm2-badge-color').val();
            $field.find('.gm2-badge-preview').text(text).css('background-color', color);
        }
        $field.find('.gm2-badge-color').wpColorPicker({
            change: updateBadge,
            clear: updateBadge
        });
        $field.on('input', '.gm2-badge-text', updateBadge);
        updateBadge();
    });

    $('.gm2-rating-picker').each(function () {
        var $picker = $(this);
        $picker.on('click', '.star', function () {
            var val = $(this).data('value');
            $picker.find('input[type="hidden"]').val(val);
            $picker.find('.star').each(function () {
                var $s = $(this);
                $s.toggleClass('active', $s.data('value') <= val);
            });
        });
    });
});
