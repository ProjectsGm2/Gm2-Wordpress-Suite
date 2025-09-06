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
        const [allComments, setAllComments] = useState(gm2GithubComments.comments || []);
        const [codexOnly, setCodexOnly] = useState(true);
        const comments = codexOnly
            ? allComments.filter(c => c.user && c.user.login === 'ChatGPT Codex Connector')
            : allComments;
        const [notice, setNotice] = useState(gm2GithubComments.error || '');
        const [noticeIsError, setNoticeIsError] = useState(!!gm2GithubComments.error);

        function applyPatch(file, patch) {
            setNotice('');
            setNoticeIsError(false);
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
                    setAllComments(data.data.comments || []);
                } else {
                    setNotice(data.data || 'Error');
                    setNoticeIsError(true);
                }
            });
        }

        return h('div', null, [
            notice ? h('div', {className: noticeIsError ? 'gm2-notice gm2-notice-error' : 'gm2-notice'}, notice) : null,
            h('label', {className: 'gm2-comment-filter'}, [
                h('input', {
                    type: 'checkbox',
                    checked: codexOnly,
                    onChange: e => setCodexOnly(e.target.checked)
                }),
                codexOnly ? 'ChatGPT Codex Connector only' : 'All comments'
            ]),
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
