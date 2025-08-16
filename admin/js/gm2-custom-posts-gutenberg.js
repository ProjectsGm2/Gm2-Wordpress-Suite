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
        const selectedBlock = useSelect( select => select('core/block-editor').getSelectedBlock(), [] );
        const { editPost } = useDispatch('core/editor');
        const fields = window.gm2BlockFields || [];

        const filtered = fields.filter(f => {
            if(!f.block){
                return true;
            }
            const blocks = Array.isArray(f.block) ? f.block : [ f.block ];
            return selectedBlock && blocks.includes(selectedBlock.name);
        });

        const sections = filtered.reduce((acc, field) => {
            const section = field.section || 'General';
            acc[section] = acc[section] || [];
            acc[section].push(field);
            return acc;
        }, {});

        return el(PluginSidebar, { name: 'gm2-meta', title: 'Gm2 Fields' },
            Object.keys(sections).map(sec =>
                el(PanelBody, { key: sec, title: sec },
                    sections[sec].map(f => controlFactory(f, meta[f.key], v => editPost({ meta: { [f.key]: v } })))
                )
            )
        );
    };

    registerPlugin('gm2-meta', {
        icon: 'admin-post',
        render: MetaPanel
    });
})(window.wp);

