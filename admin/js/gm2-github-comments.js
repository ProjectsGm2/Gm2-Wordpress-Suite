(function(wp){
    const { createElement: h, render, useState, useEffect } = wp.element;

    function CommentItem({comment, onApply, selected, onToggle}) {
        const patchMatch = comment.body && comment.body.match(/```suggestion\n([\s\S]*?)\n```/);
        const patch = patchMatch ? patchMatch[1] : '';
        return h('div', {className: 'gm2-comment'}, [
            h('p', {className: 'gm2-comment-path'}, [
                patch ? h('input', {
                    type: 'checkbox',
                    checked: selected,
                    onChange: e => onToggle(e.target.checked)
                }) : null,
                ' ' + comment.path + ':' + (comment.line || comment.original_line || '')
            ]),
            h('pre', {className: 'gm2-comment-body'}, comment.body),
            patch ? h('pre', {className: 'gm2-comment-patch'}, patch) : null,
            patch ? h('button', {onClick: () => onApply(comment.path, patch)}, 'Apply Patch') : null
        ]);
    }

    function App() {
        const [allComments, setAllComments] = useState([]);
        const [loading, setLoading] = useState(true);
        useEffect(() => {
            setAllComments(gm2GithubComments.comments || []);
            setLoading(false);
        }, []);
        const [codexOnly, setCodexOnly] = useState(true);
        const comments = codexOnly
            ? allComments.filter(c => c.user && c.user.login === 'ChatGPT Codex Connector')
            : allComments;
        const [notice, setNotice] = useState(gm2GithubComments.error || '');
        const [noticeIsError, setNoticeIsError] = useState(!!gm2GithubComments.error);
        const [selected, setSelected] = useState({});

        function applyPatch(file, patch) {
            setNotice('');
            setNoticeIsError(false);
            setLoading(true);
            const body = new URLSearchParams({
                action: 'gm2_apply_patch',
                nonce: gm2GithubComments.nonce,
                file: file,
                patch: patch,
                repo: gm2GithubComments.currentRepo || '',
                pr: gm2GithubComments.currentPr || ''
            });
            fetch(gm2GithubComments.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(r => r.json()).then(data => {
                const results = data.data && data.data.results ? data.data.results : [];
                const message = results.map(r => r.file + ': ' + r.message).join('\n') || data.data.message;
                if (data.success) {
                    setNotice(message);
                    setAllComments(data.data.comments || []);
                    setSelected({});
                } else {
                    setNotice(message || data.data || 'Error');
                    setNoticeIsError(true);
                    setAllComments(data.data.comments || []);
                    setSelected({});
                }
            }).finally(() => setLoading(false));
        }

        function applySelected() {
            const patches = comments.filter(c => selected[c.id]).map(c => {
                const m = c.body && c.body.match(/```suggestion\n([\s\S]*?)\n```/);
                return m ? {file: c.path, patch: m[1]} : null;
            }).filter(Boolean);
            if (patches.length === 0) {
                setNotice('No patches selected');
                setNoticeIsError(true);
                return;
            }
            setNotice('');
            setNoticeIsError(false);
            setLoading(true);
            const body = new URLSearchParams({
                action: 'gm2_apply_patch',
                nonce: gm2GithubComments.nonce,
                patches: JSON.stringify(patches),
                repo: gm2GithubComments.currentRepo || '',
                pr: gm2GithubComments.currentPr || ''
            });
            fetch(gm2GithubComments.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(r => r.json()).then(data => {
                const results = data.data && data.data.results ? data.data.results : [];
                const message = results.map(r => r.file + ': ' + r.message).join('\n') || data.data.message;
                if (data.success) {
                    setNotice(message);
                    setAllComments(data.data.comments || []);
                    setSelected({});
                } else {
                    setNotice(message || data.data || 'Error');
                    setNoticeIsError(true);
                    setAllComments(data.data.comments || []);
                    setSelected({});
                }
            }).finally(() => setLoading(false));
        }

        const toggleSelect = (id, checked) => {
            setSelected(prev => ({ ...prev, [id]: checked }));
        };

        const patchable = comments.filter(c => {
            const m = c.body && c.body.match(/```suggestion\n([\s\S]*?)\n```/);
            return !!m;
        });
        const allSelected = patchable.length > 0 && patchable.every(c => selected[c.id]);

        function toggleSelectAll(checked) {
            if (!checked) {
                setSelected({});
                return;
            }
            const newSel = {};
            patchable.forEach(c => { newSel[c.id] = true; });
            setSelected(newSel);
        }

        useEffect(() => {
            const loadingHandler = () => {
                setLoading(true);
                setNotice('');
                setNoticeIsError(false);
            };
            const loadedHandler = e => {
                setLoading(false);
                if (e.detail && e.detail.error) {
                    setNotice(e.detail.error);
                    setNoticeIsError(true);
                    setAllComments([]);
                    setSelected({});
                } else {
                    setAllComments(e.detail && e.detail.comments ? e.detail.comments : []);
                    setSelected({});
                }
            };
            document.addEventListener('gm2CommentsLoading', loadingHandler);
            document.addEventListener('gm2CommentsLoaded', loadedHandler);
            return () => {
                document.removeEventListener('gm2CommentsLoading', loadingHandler);
                document.removeEventListener('gm2CommentsLoaded', loadedHandler);
            };
        }, []);

        return h('div', null, [
            loading ? h('div', {className: 'gm2-notice'}, 'Loading comments...') :
                (notice ? h('div', {className: noticeIsError ? 'gm2-notice gm2-notice-error' : 'gm2-notice'}, notice) : null),
            h('label', {className: 'gm2-comment-filter'}, [
                h('input', {
                    type: 'checkbox',
                    checked: codexOnly,
                    onChange: e => setCodexOnly(e.target.checked)
                }),
                codexOnly ? 'ChatGPT Codex Connector only' : 'All comments'
            ]),
            patchable.length ? h('div', {className: 'gm2-comment-actions'}, [
                h('label', null, [
                    h('input', {
                        type: 'checkbox',
                        checked: allSelected,
                        onChange: e => toggleSelectAll(e.target.checked)
                    }),
                    ' Select All'
                ]),
                h('button', {onClick: applySelected}, 'Apply Selected Patches')
            ]) : null,
            !loading && comments.length === 0 ? h('p', {className: 'gm2-no-comments'}, 'No comments found') : null,
            comments.map(c => h(CommentItem, {
                key: c.id,
                comment: c,
                onApply: applyPatch,
                selected: !!selected[c.id],
                onToggle: checked => toggleSelect(c.id, checked)
            }))
        ]);
    }

    document.addEventListener('DOMContentLoaded', function(){
        const root = document.getElementById('gm2-github-comments-root');
        if (root) {
            render(h(App), root);
        }
        const btn = document.getElementById('gm2-load-comments');
        if (btn) {
            btn.addEventListener('click', function(){
                const repoInput = document.getElementById('gm2-repo');
                const prSelect = document.getElementById('gm2-pr');
                const repo = repoInput ? repoInput.value.trim() : '';
                const pr = prSelect ? prSelect.options[prSelect.selectedIndex].value : '';
                gm2GithubComments.currentRepo = repo;
                gm2GithubComments.currentPr = pr;
                const body = new URLSearchParams({
                    action: 'gm2_get_github_comments',
                    nonce: gm2GithubComments.commentsNonce,
                    repo: repo,
                    pr: pr === 'all' ? 'all' : pr
                });
                document.dispatchEvent(new CustomEvent('gm2CommentsLoading'));
                fetch(gm2GithubComments.ajax_url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        document.dispatchEvent(new CustomEvent('gm2CommentsLoaded', {detail: {comments: data.data}}));
                    } else {
                        const message = data.data || data.message || 'Error';
                        document.dispatchEvent(new CustomEvent('gm2CommentsLoaded', {detail: {error: message}}));
                    }
                }).catch(() => {
                    document.dispatchEvent(new CustomEvent('gm2CommentsLoaded', {detail: {error: 'Error'}}));
                });
            });
        }
    });
})(window.wp);
