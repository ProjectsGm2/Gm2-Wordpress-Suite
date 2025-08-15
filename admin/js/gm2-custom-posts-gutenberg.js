(function(wp){
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar } = wp.editPost;
    const { PanelBody, TextControl } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const { createElement: el } = wp.element;

    const MetaPanel = () => {
        const meta = useSelect( select => select('core/editor').getEditedPostAttribute('meta'), [] );
        const { editPost } = useDispatch('core/editor');
        const fields = window.gm2BlockFields || [];
        return el(PluginSidebar, { name: 'gm2-meta', title: 'Gm2 Fields' },
            el(PanelBody, {},
                fields.map(f => el(TextControl, {
                    key: f.key,
                    label: f.label,
                    value: meta[f.key] || '',
                    onChange: v => editPost({ meta: { [f.key]: v } })
                }))
            )
        );
    };

    registerPlugin('gm2-meta', {
        icon: 'admin-post',
        render: MetaPanel
    });
})(window.wp);

