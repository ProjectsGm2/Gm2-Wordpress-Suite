(function(global){
    function init() {
        var loadBtn = document.getElementById('gm2-load-github-comments');
        if (!loadBtn) { return; }

        loadBtn.addEventListener('click', function(){
            var branchSelect = document.getElementById('gm2-github-branch');
            var branch = '';
            if (branchSelect && branchSelect.selectedIndex >= 0) {
                var opt = branchSelect.options[branchSelect.selectedIndex];
                branch = opt ? opt.value : '';
            }

            // Placeholder for request logic using `branch`
            console.log('Fetching comments for branch:', branch);
        });
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', init);
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = init;
    }
})(this);
