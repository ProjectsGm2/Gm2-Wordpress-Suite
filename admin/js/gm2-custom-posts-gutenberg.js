(function(wp){
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar } = wp.editPost;
    const { PanelBody, TextControl, TextareaControl, SelectControl, ToggleControl, Button } = wp.components;
    const { MediaUpload } = wp.blockEditor || wp.editor;
    const { useSelect, useDispatch } = wp.data;
    const { createElement: el } = wp.element;

    const controlFactory = (field, value, onChange) => {
        switch(field.type){
            case 'select':
                return el(SelectControl, {
                    key: field.key,
                    label: field.label,
                    value: value || '',
                    options: field.options || [],
                    onChange
                });
            case 'checkbox':
            case 'toggle':
                return el(ToggleControl, {
                    key: field.key,
                    label: field.label,
                    checked: !!value,
                    onChange
                });
            case 'media':
                return el('div', { key: field.key }, [
                    el('p', {}, field.label),
                    el(MediaUpload, {
                        onSelect: m => onChange(m.id),
                        value: value,
                        render: ({ open }) => el(Button, { onClick: open, isSecondary: true }, value ? 'Change Media' : 'Select Media')
                    })
                ]);
            case 'textarea':
                return el(TextareaControl, {
                    key: field.key,
                    label: field.label,
                    value: value || '',
                    onChange
                });
            default:
                return el(TextControl, {
                    key: field.key,
                    label: field.label,
                    value: value || '',
                    onChange
                });
        }
    };

    const MetaPanel = () => {
        const meta = useSelect( select => select('core/editor').getEditedPostAttribute('meta'), [] );
        const { editPost } = useDispatch('core/editor');
        const fields = window.gm2BlockFields || [];
        return el(PluginSidebar, { name: 'gm2-meta', title: 'Gm2 Fields' },
            el(PanelBody, {},
                fields.map(f => controlFactory(f, meta[f.key], v => editPost({ meta: { [f.key]: v } })))
            )
        );
    };

    registerPlugin('gm2-meta', {
        icon: 'admin-post',
        render: MetaPanel
    });
})(window.wp);

