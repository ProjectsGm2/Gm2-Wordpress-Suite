(function(wp){
    const { createElement: h, render, useState } = wp.element;

    function CommentItem({comment, onApply}) {
        const patchMatch = comment.body && comment.body.match(/```suggestion\n([\s\S]*?)\n```/);
        const patch = patchMatch ? patchMatch[1] : '';
        return h('div', {className: 'gm2-comment'}, [
            h('p', {className: 'gm2-comment-path'}, comment.path + ':' + (comment.line || comment.original_line || '')),
            h('pre', {className: 'gm2-comment-body'}, comment.body),
            patch ? h('pre', {className: 'gm2-comment-patch'}, patch) : null,
            patch ? h('button', {onClick: () => onApply(comment.path, patch)}, 'Apply Patch') : null
        ]);
    }

    function App() {
        const [comments, setComments] = useState(gm2GithubComments.comments || []);
        const [notice, setNotice] = useState('');

        function applyPatch(file, patch) {
            setNotice('');
            const body = new URLSearchParams({
                action: 'gm2_apply_patch',
                nonce: gm2GithubComments.nonce,
                file: file,
                patch: patch
            });
            fetch(gm2GithubComments.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    setNotice(data.data.message);
                    setComments(data.data.comments || []);
                } else {
                    setNotice(data.data || 'Error');
                }
            });
        }

        return h('div', null, [
            notice ? h('div', {className: 'gm2-notice'}, notice) : null,
            comments.map(c => h(CommentItem, {key: c.id, comment: c, onApply: applyPatch}))
        ]);
    }

    document.addEventListener('DOMContentLoaded', function(){
        const root = document.getElementById('gm2-github-comments-root');
        if (root) {
            render(h(App), root);
        }
    });
})(window.wp);
