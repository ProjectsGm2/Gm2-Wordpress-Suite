(function(wp){
    const { createElement: el, useState, useEffect } = wp.element;
    const { render } = wp.element;
    const { Button, TextControl, SelectControl, FormTokenField, PanelBody, Panel, Card, CardBody, Sortable } = wp.components;
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
        const [ slugError, setSlugError ] = useState('');
        const onSlugChange = (v) => {
            const duplicate = existing[v] && v !== data.slug;
            if(!v){
                setSlugError('Slug is required');
            } else if(duplicate){
                setSlugError('Slug must be unique');
            } else {
                setSlugError('');
            }
            setData({ ...data, slug: v });
        };
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
                onChange: onSlugChange,
                help: slugError
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
        const [ editIndex, setEditIndex ] = useState(null);
        const [ error, setError ] = useState('');

        const fieldTypes = [
            { label: 'Text', value: 'text' },
            { label: 'Textarea', value: 'textarea' },
            { label: 'Select', value: 'select' },
            { label: 'Number', value: 'number' }
        ];

        const addField = () => {
            if(!field.slug){
                setError('Field slug is required');
                dispatch('core/notices').createNotice('error', 'Field slug is required');
                return;
            }
            if(data.fields.some((f,i) => f.slug === field.slug && i !== editIndex)){
                setError('Field slug must be unique');
                dispatch('core/notices').createNotice('error', 'Field slug must be unique');
                return;
            }
            setError('');
            const fields = data.fields.slice();
            if(editIndex !== null){
                fields[editIndex] = field;
            } else {
                fields.push(field);
            }
            setData({ ...data, fields });
            setField({ label: '', slug: '', type: 'text' });
            setEditIndex(null);
        };

        const editField = (i) => {
            setField(data.fields[i]);
            setEditIndex(i);
        };

        const removeField = (i) => {
            const copy = data.fields.slice();
            copy.splice(i,1);
            setData({ ...data, fields: copy });
        };

        const onSortEnd = ({ oldIndex, newIndex }) => {
            const fields = data.fields.slice();
            fields.splice(newIndex, 0, fields.splice(oldIndex,1)[0]);
            setData({ ...data, fields });
        };

        return el('div', {},
            data.fields.length > 0 && el(PanelBody, { title: 'Fields', initialOpen: true },
                el(Sortable, { onSortEnd },
                    data.fields.map((f,i) => el(Sortable.Item, { key: f.slug || i },
                        el(Card, { className: 'gm2-field-item' },
                            el(CardBody, {},
                                el('strong', {}, f.label || '(no label)'),
                                el('div', {}, 'Slug: ' + f.slug),
                                el('div', {}, 'Type: ' + f.type),
                                el(Button, { isLink: true, onClick: () => editField(i) }, 'Edit'),
                                el(Button, { isLink: true, onClick: () => removeField(i) }, 'Delete')
                            )
                        )
                    ))
                )
            ),
            el(TextControl, {
                label: 'Field Label',
                value: field.label,
                onChange: v => setField({ ...field, label: v })
            }),
            el(TextControl, {
                label: 'Field Slug',
                value: field.slug,
                onChange: v => setField({ ...field, slug: v }),
                help: error
            }),
            el(SelectControl, {
                label: 'Field Type',
                value: field.type,
                options: fieldTypes,
                onChange: v => setField({ ...field, type: v })
            }),
            el(Button, { isPrimary: true, onClick: addField }, editIndex !== null ? 'Update Field' : 'Add Field')
        );
    };


    
    const LocationStep = ({ data, setData }) => {
        const wizard = window.gm2FGWizard || {};
        const paramOptions = [
            { label: 'Post Type', value: 'post_type' },
            { label: 'Taxonomy', value: 'taxonomy' },
            { label: 'Template', value: 'template' }
        ];
        const operatorOptions = [
            { label: '==', value: '==' },
            { label: '!=', value: '!=' }
        ];
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
            copy[gi].rules.push({ param: 'post_type', operator: '==', value: '' });
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
        const ValueControl = ({ rule, onChange }) => {
            if(rule.param === 'post_type'){
                const options = Object.keys(wizard.postTypes || {}).map(slug => ({ label: wizard.postTypes[slug], value: slug }));
                return el(SelectControl, { label: 'Value', value: rule.value, options, onChange });
            }
            if(rule.param === 'taxonomy'){
                const taxOptions = Object.keys(wizard.taxonomies || {}).map(slug => ({ label: wizard.taxonomies[slug], value: slug }));
                const [ tax, term ] = (rule.value || ':').split(':');
                const [ selectedTax, setSelectedTax ] = useState(tax);
                const [ terms, setTerms ] = useState([]);
                useEffect(() => {
                    if(selectedTax){
                        const restRoot = (window.wpApiSettings && window.wpApiSettings.root) || '/wp-json/';
                        fetch(restRoot + 'wp/v2/' + selectedTax + '?per_page=100').then(r => r.json()).then(res => {
                            setTerms(res.map(t => ({ label: t.name, value: t.slug })));
                        });
                    }
                }, [selectedTax]);
                return el('div', {},
                    el(SelectControl, {
                        label: 'Taxonomy',
                        value: selectedTax,
                        options: taxOptions,
                        onChange: v => { setSelectedTax(v); onChange(v + ':'); }
                    }),
                    selectedTax && el(SelectControl, {
                        label: 'Term',
                        value: term,
                        options: terms,
                        onChange: v => onChange(selectedTax + ':' + v)
                    })
                );
            }
            return el(TextControl, { label: 'Value', value: rule.value, onChange });
        };
        return el('div', {},
            el('p', { className: 'gm2-location-help' }, 'Each group is evaluated separately. Rules inside a group use the selected relation, and the field group is displayed when any group matches.'),
            data.location.map((g,gi) => el(Card, { key: gi, className: 'gm2-location-group' },
                el(CardBody, {},
                    el(SelectControl, {
                        label: 'Group Relation',
                        value: g.relation || 'AND',
                        options: [ { label: 'AND', value: 'AND' }, { label: 'OR', value: 'OR' } ],
                        onChange: v => updateGroupRel(gi,v),
                        help: 'Choose how rules in this group are combined.'
                    }),
                    g.rules.map((r,ri) => el(Card, { key: ri, className: 'gm2-location-rule' },
                        el(CardBody, {},
                            el(SelectControl, {
                                label: 'Parameter',
                                value: r.param,
                                options: paramOptions,
                                onChange: v => updateRule(gi,ri,'param',v)
                            }),
                            el(SelectControl, {
                                label: 'Operator',
                                value: r.operator,
                                options: operatorOptions,
                                onChange: v => updateRule(gi,ri,'operator',v)
                            }),
                            el(ValueControl, {
                                rule: r,
                                onChange: v => updateRule(gi,ri,'value',v)
                            }),
                            el(Button, { isLink: true, onClick: () => removeRule(gi,ri) }, 'Delete Rule')
                        )
                    )),
                    el(Button, { isSecondary: true, onClick: () => addRule(gi) }, 'Add Rule'),
                    el(Button, { isDestructive: true, onClick: () => removeGroup(gi) }, 'Remove Group')
                )
            )),
            el(Button, { isPrimary: true, onClick: addGroup }, 'Add Location Group')
        );
    };

    const ReviewStep = ({ data, onEdit }) => {
        const fieldsTable = data.fields.length ?
            el('table', { className: 'gm2-fg-review-fields' },
                el('thead', {},
                    el('tr', {},
                        el('th', {}, 'Label'),
                        el('th', {}, 'Slug'),
                        el('th', {}, 'Type')
                    )
                ),
                el('tbody', {},
                    data.fields.map((f,i) => el('tr', { key: i },
                        el('td', {}, f.label),
                        el('td', {}, f.slug),
                        el('td', {}, f.type)
                    ))
                )
            ) : el('p', {}, 'No fields defined.');

        const locationSummary = data.location.length ?
            data.location.map((g,gi) => el('div', { key: gi },
                el('strong', {}, 'Group ' + (gi + 1) + ' (' + (g.relation || 'AND') + ')'),
                el('ul', {},
                    g.rules.map((r,ri) => el('li', { key: ri },
                        r.param + ' ' + r.operator + ' ' + (Array.isArray(r.value) ? r.value.join(', ') : r.value)
                    ))
                )
            )) : el('p', {}, 'No location rules.');

        return el('div', {},
            el('h3', {}, 'General ', el(Button, { isLink: true, onClick: () => onEdit(1) }, 'Edit')),
            el('ul', {},
                el('li', {}, 'Slug: ' + data.slug),
                el('li', {}, 'Title: ' + data.title),
                el('li', {}, 'Scope: ' + data.scope),
                el('li', {}, 'Objects: ' + (data.objects.join(', ') || 'None'))
            ),
            el('h3', {}, 'Fields ', el(Button, { isLink: true, onClick: () => onEdit(2) }, 'Edit')),
            fieldsTable,
            el('h3', {}, 'Location Rules ', el(Button, { isLink: true, onClick: () => onEdit(3) }, 'Edit')),
            locationSummary
        );
    };

    const Wizard = () => {
        const existing = (window.gm2FGWizard && window.gm2FGWizard.groups) || {};
        const [ step, setStep ] = useState(1);
        const [ data, setData ] = useState({ slug: '', title: '', scope: 'post_type', objects: [], fields: [], location: [] });
        const steps = [ 'Details', 'Fields', 'Location', 'Review' ];

        const next = () => setStep(step + 1);
        const back = () => setStep(step - 1);
        const goto = (s) => setStep(s);

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
            return el(ReviewStep, { data, onEdit: goto });
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
                    setTimeout(() => {
                        window.location.href = (window.gm2FGWizard && window.gm2FGWizard.listUrl) || 'admin.php?page=gm2-custom-posts';
                    }, 1500);
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
                    el('div', { className: 'gm2-fg-stepper' },
                        el('div', { className: 'gm2-fg-stepper-label' }, 'Step ' + step + ' of ' + steps.length),
                        el('progress', { max: steps.length, value: step })
                    ),
                    renderStep(),
                    el('div', { className: 'gm2-fg-wizard-buttons' }, [
                        step > 1 && el(Button, { onClick: back }, 'Back'),
                        step < steps.length && el(Button, { isPrimary: true, onClick: next }, 'Next'),
                        step === steps.length && el(Button, { isPrimary: true, onClick: save }, 'Finish')
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
