(function(wp){
    const { createElement: el, useState } = wp.element;
    const { render } = wp.element;
    const { Button, TextControl, SelectControl, FormTokenField, PanelBody, Panel } = wp.components;
    const { dispatch } = wp.data;

    const StepOne = ({ data, setData, existing, loadGroup }) => {
        const options = [ { label: 'New', value: '' } ];
        Object.keys(existing).forEach(sl => {
            options.push({ label: existing[sl].title || sl, value: sl });
        });
        const wizard = window.gm2FGWizard || {};
        const scopeOptions = [];
        if (wizard.postTypes && Object.keys(wizard.postTypes).length) {
            scopeOptions.push({ label: 'Post Types', value: 'post_type' });
        }
        if (wizard.taxonomies && Object.keys(wizard.taxonomies).length) {
            scopeOptions.push({ label: 'Taxonomies', value: 'taxonomy' });
        }
        const source = data.scope === 'taxonomy' ? wizard.taxonomies : wizard.postTypes;
        const suggestions = Object.keys(source || {});
        return el('div', {},
            options.length > 1 && el(SelectControl, {
                label: 'Existing Groups',
                value: data.slug && existing[data.slug] ? data.slug : '',
                options: options,
                onChange: v => loadGroup(v)
            }),
            el(TextControl, {
                label: 'Group Slug',
                value: data.slug,
                onChange: v => setData({ ...data, slug: v })
            }),
            el(TextControl, {
                label: 'Title',
                value: data.title,
                onChange: v => setData({ ...data, title: v })
            }),
            el(SelectControl, {
                label: 'Scope',
                value: data.scope,
                options: scopeOptions,
                onChange: v => setData({ ...data, scope: v, objects: [] }),
                help: 'Scope determines the type of content (post types or taxonomies) this field group attaches to.'
            }),
            el(FormTokenField, {
                label: 'Objects',
                value: data.objects,
                suggestions: suggestions,
                onChange: tokens => setData({ ...data, objects: tokens }),
                help: 'Select the specific ' + (data.scope === 'taxonomy' ? 'taxonomies' : 'post types') + ' where this field group should appear.'
            })
        );
    };

    const FieldsStep = ({ data, setData }) => {
        const [ field, setField ] = useState({ label: '', slug: '', type: 'text' });
        const addField = () => {
            if(!field.slug){ return; }
            setData({ ...data, fields: [ ...data.fields, field ] });
            setField({ label: '', slug: '', type: 'text' });
        };
        const removeField = (i) => {
            const copy = data.fields.slice();
            copy.splice(i,1);
            setData({ ...data, fields: copy });
        };
        return el('div', {},
            data.fields.map((f,i) => el('div', { key: i },
                `${f.label} (${f.slug})`,
                el(Button, { isLink: true, onClick: () => removeField(i) }, 'Delete')
            )),
            el(TextControl, {
                label: 'Field Label',
                value: field.label,
                onChange: v => setField({ ...field, label: v })
            }),
            el(TextControl, {
                label: 'Field Slug',
                value: field.slug,
                onChange: v => setField({ ...field, slug: v })
            }),
            el(TextControl, {
                label: 'Field Type',
                value: field.type,
                onChange: v => setField({ ...field, type: v })
            }),
            el(Button, { isPrimary: true, onClick: addField }, 'Add Field')
        );
    };

    const LocationStep = ({ data, setData }) => {
        const addGroup = () => {
            setData({ ...data, location: [ ...data.location, { relation: 'AND', rules: [] } ] });
        };
        const removeGroup = (i) => {
            const copy = data.location.slice();
            copy.splice(i,1);
            setData({ ...data, location: copy });
        };
        const addRule = (gi) => {
            const copy = data.location.slice();
            copy[gi].rules.push({ param: '', operator: '==', value: '' });
            setData({ ...data, location: copy });
        };
        const updateRule = (gi,ri,field,val) => {
            const copy = data.location.slice();
            copy[gi].rules[ri][field] = val;
            setData({ ...data, location: copy });
        };
        const removeRule = (gi,ri) => {
            const copy = data.location.slice();
            copy[gi].rules.splice(ri,1);
            setData({ ...data, location: copy });
        };
        const updateGroupRel = (gi,v) => {
            const copy = data.location.slice();
            copy[gi].relation = v;
            setData({ ...data, location: copy });
        };
        return el('div', {},
            data.location.map((g,gi) => el('div', { key: gi },
                el(SelectControl, {
                    label: 'Group Relation',
                    value: g.relation || 'AND',
                    options: [ { label: 'AND', value: 'AND' }, { label: 'OR', value: 'OR' } ],
                    onChange: v => updateGroupRel(gi,v)
                }),
                g.rules.map((r,ri) => el('div', { key: ri },
                    el(TextControl, {
                        label: 'Param',
                        value: r.param,
                        onChange: v => updateRule(gi,ri,'param',v)
                    }),
                    el(SelectControl, {
                        label: 'Operator',
                        value: r.operator,
                        options: [ { label: '==', value: '==' }, { label: '!=', value: '!=' } ],
                        onChange: v => updateRule(gi,ri,'operator',v)
                    }),
                    el(TextControl, {
                        label: 'Value',
                        value: r.value,
                        onChange: v => updateRule(gi,ri,'value',v)
                    }),
                    el(Button, { isLink: true, onClick: () => removeRule(gi,ri) }, 'Delete Rule')
                )),
                el(Button, { isLink: true, onClick: () => addRule(gi) }, 'Add Rule'),
                el(Button, { isLink: true, onClick: () => removeGroup(gi) }, 'Remove Group')
            )),
            el(Button, { isPrimary: true, onClick: addGroup }, 'Add Location Group')
        );
    };

    const ReviewStep = ({ data }) => el('div', {},
        el('pre', { className: 'gm2-fg-review' }, JSON.stringify(data, null, 2))
    );

    const Wizard = () => {
        const existing = (window.gm2FGWizard && window.gm2FGWizard.groups) || {};
        const [ step, setStep ] = useState(1);
        const [ data, setData ] = useState({ slug: '', title: '', scope: 'post_type', objects: [], fields: [], location: [] });

        const next = () => setStep(step + 1);
        const back = () => setStep(step - 1);

        const loadGroup = (slug) => {
            if(!slug){
                setData({ slug: '', title: '', scope: 'post_type', objects: [], fields: [], location: [] });
                return;
            }
            const group = existing[slug] || {};
            const fields = Object.keys(group.fields || {}).map(key => ({
                slug: key,
                label: group.fields[key].label || '',
                type: group.fields[key].type || 'text'
            }));
            const objects = group.objects || [];
            setData({ slug: slug, title: group.title || '', scope: group.scope || 'post_type', objects, fields, location: group.location || [] });
        };

        const renderStep = () => {
            if(step === 1) return el(StepOne, { data, setData, existing, loadGroup });
            if(step === 2) return el(FieldsStep, { data, setData });
            if(step === 3) return el(LocationStep, { data, setData });
            return el(ReviewStep, { data });
        };

        const save = () => {
            const payload = new URLSearchParams();
            payload.append('action','gm2_save_field_group');
            payload.append('nonce', window.gm2FGWizard.nonce);
            payload.append('slug', data.slug);
            payload.append('title', data.title);
            payload.append('scope', data.scope);
            payload.append('objects', JSON.stringify(data.objects));
            payload.append('fields', JSON.stringify(data.fields));
            payload.append('location', JSON.stringify(data.location));
            fetch(window.gm2FGWizard.ajax, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            }).then(r => r.json()).then(resp => {
                if(resp && resp.success){
                    dispatch('core/notices').createNotice('success', 'Field group saved', { type: 'snackbar' });
                } else {
                    dispatch('core/notices').createNotice('error', 'Error saving', { type: 'snackbar' });
                }
            }).catch(() => {
                dispatch('core/notices').createNotice('error', 'Error saving', { type: 'snackbar' });
            });
        };

        return el('div', { className: 'gm2-fg-wizard' },
            el(Panel, {},
                el(PanelBody, { title: 'Field Group Wizard', initialOpen: true },
                    renderStep(),
                    el('div', { className: 'gm2-fg-wizard-buttons' }, [
                        step > 1 && el(Button, { onClick: back }, 'Back'),
                        step < 4 && el(Button, { isPrimary: true, onClick: next }, 'Next'),
                        step === 4 && el(Button, { isPrimary: true, onClick: save }, 'Finish')
                    ])
                )
            )
        );
    };

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('gm2-fg-wizard-root');
        if(root){
            render(el(Wizard), root);
        }
    });
})(window.wp);
