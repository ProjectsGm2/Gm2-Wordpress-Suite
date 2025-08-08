(function(global){
    function init() {
        var button = document.getElementById('gm2-select-all');
        if (!button) { return; }
        var selected = false;
        var ids = [];
        var hidden = document.getElementById('gm2-selected-ids');

        button.addEventListener('click', function(){
            if (!selected) {
                var query = window.location.search.slice(1);
                fetch(gm2BulkReviewData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=gm2_fetch_filtered_post_ids&nonce=' + encodeURIComponent(gm2BulkReviewData.nonce) + '&query=' + encodeURIComponent(query)
                }).then(function(resp){
                    return resp.json();
                }).then(function(data){
                    ids = data.data || [];
                    ids.forEach(function(id){
                        var cb = document.getElementById('cb-select-' + id);
                        if (cb) { cb.checked = true; }
                    });
                    hidden.value = ids.join(',');
                    button.textContent = 'Un-Select All';
                    selected = true;
                });
            } else {
                ids.forEach(function(id){
                    var cb = document.getElementById('cb-select-' + id);
                    if (cb) { cb.checked = false; }
                });
                ids = [];
                hidden.value = '';
                button.textContent = 'Select All';
                selected = false;
            }
        });

        var form = document.getElementById('posts-filter');
        if (form) {
            form.addEventListener('submit', function(){
                if (ids.length) {
                    ids.forEach(function(id){
                        if (!document.getElementById('cb-select-' + id)) {
                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'post[]';
                            input.value = id;
                            form.appendChild(input);
                        }
                    });
                }
            });
        }
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', init);
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = init;
    }
})(this);
