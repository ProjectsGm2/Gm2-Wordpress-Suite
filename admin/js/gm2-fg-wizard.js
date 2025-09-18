(function(wp){
    const { createElement: el, useState, useEffect } = wp.element;
    const { render } = wp.element;
    const { Button, TextControl, SelectControl, FormTokenField, PanelBody, Panel, Card, CardBody, Sortable, ToggleControl, Modal, CheckboxControl } = wp.components;
    const { __, sprintf } = wp.i18n;
    const { dispatch } = wp.data;
    const addPassive = !window.AE_PERF_DISABLE_PASSIVE && window.aePerf?.addPassive
        ? window.aePerf.addPassive
        : (el, type, handler, options) => el.addEventListener(type, handler, options);

    const StepOne = ({ data, setData, existing, loadGroup, setExisting }) => {
        const options = [ { label: __('New', 'gm2-wordpress-suite'), value: '' } ];
        Object.keys(existing).forEach(sl => {
            options.push({ label: existing[sl].title || sl, value: sl });
        });
        const wizard = window.gm2FGWizard || {};
        const scopeOptions = [];
        if (wizard.postTypes && Object.keys(wizard.postTypes).length) {
            scopeOptions.push({ label: __('Post Types', 'gm2-wordpress-suite'), value: 'post_type' });
        }
        if (wizard.taxonomies && Object.keys(wizard.taxonomies).length) {
            scopeOptions.push({ label: __('Taxonomies', 'gm2-wordpress-suite'), value: 'taxonomy' });
        }
        const source = data.scope === 'taxonomy' ? wizard.taxonomies : wizard.postTypes;
        const suggestions = Object.keys(source || {});
        const [ slugError, setSlugError ] = useState('');
        const [ exportOpen, setExportOpen ] = useState(false);
        const [ exportSelection, setExportSelection ] = useState([]);
        const [ exportError, setExportError ] = useState('');
        const [ exporting, setExporting ] = useState(false);
        const onSlugChange = (v) => {
            const duplicate = existing[v] && v !== data.slug;
            if(!v){
                setSlugError(__('Slug is required', 'gm2-wordpress-suite'));
            } else if(duplicate){
                setSlugError(__('Slug must be unique', 'gm2-wordpress-suite'));
            } else {
                setSlugError('');
            }
            setData({ ...data, slug: v });
        };

        const deleteGroup = () => {
            if(!data.slug) return;
            if(!window.confirm(__('Delete this group?', 'gm2-wordpress-suite'))) return;
            const payload = new URLSearchParams();
            payload.append('action','gm2_delete_field_group');
            payload.append('nonce', window.gm2FGWizard.nonce);
            payload.append('slug', data.slug);
            fetch(window.gm2FGWizard.ajax, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            }).then(r => r.json()).then(resp => {
                if(resp && resp.success){
                    setExisting(resp.data.groups || {});
                    loadGroup('');
                    dispatch('core/notices').createNotice('success', __('Field group deleted', 'gm2-wordpress-suite'));
                } else {
                    dispatch('core/notices').createNotice('error', __('Error deleting group', 'gm2-wordpress-suite'));
                }
            }).catch(() => {
                dispatch('core/notices').createNotice('error', __('Error deleting group', 'gm2-wordpress-suite'));
            });
        };

        const renameGroup = () => {
            if(!data.slug) return;
            const newSlug = window.prompt(__('Enter new slug', 'gm2-wordpress-suite'), data.slug);
            if(!newSlug || newSlug === data.slug) return;
            const payload = new URLSearchParams();
            payload.append('action','gm2_rename_field_group');
            payload.append('nonce', window.gm2FGWizard.nonce);
            payload.append('slug', data.slug);
            payload.append('new_slug', newSlug);
            fetch(window.gm2FGWizard.ajax, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            }).then(r => r.json()).then(resp => {
                if(resp && resp.success){
                    setExisting(resp.data.groups || {});
                    loadGroup(newSlug);
                    dispatch('core/notices').createNotice('success', __('Field group renamed', 'gm2-wordpress-suite'));
                } else {
                    dispatch('core/notices').createNotice('error', __('Error renaming group', 'gm2-wordpress-suite'));
                }
            }).catch(() => {
                dispatch('core/notices').createNotice('error', __('Error renaming group', 'gm2-wordpress-suite'));
            });
        };

        const openExportModal = () => {
            const initial = data.slug && existing[data.slug] ? [ data.slug ] : [];
            setExportSelection(initial);
            setExportError('');
            setExportOpen(true);
        };

        const toggleExportGroup = (slug) => {
            setExportSelection(prev => {
                if(prev.includes(slug)){
                    return prev.filter(item => item !== slug);
                }
                return [ ...prev, slug ];
            });
        };

        const performExport = () => {
            if(exporting) return;
            if(exportSelection.length === 0){
                setExportError(__('Select at least one field group to export.', 'gm2-wordpress-suite'));
                return;
            }
            setExportError('');
            setExporting(true);
            const payload = new URLSearchParams();
            payload.append('action','gm2_export_field_groups');
            payload.append('nonce', window.gm2FGWizard.exportNonce || window.gm2FGWizard.nonce);
            payload.append('groups', JSON.stringify(exportSelection));
            fetch(window.gm2FGWizard.ajax, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            }).then(r => r.json()).then(resp => {
                if(resp && resp.success && resp.data && resp.data.content){
                    const filename = resp.data.filename || 'gm2-field-groups.json';
                    const blob = new Blob([resp.data.content], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    dispatch('core/notices').createNotice('success', __('Field groups exported', 'gm2-wordpress-suite'));
                    setExportOpen(false);
                } else {
                    const msg = resp && resp.data && resp.data.message ? resp.data.message : __('Error exporting field groups', 'gm2-wordpress-suite');
                    setExportError(msg);
                    dispatch('core/notices').createNotice('error', msg);
                }
            }).catch(() => {
                const msg = __('Error exporting field groups', 'gm2-wordpress-suite');
                setExportError(msg);
                dispatch('core/notices').createNotice('error', msg);
            }).finally(() => {
                setExporting(false);
            });
        };

        return el('div', {},
            options.length > 1 && el(SelectControl, {
                label: __('Existing Groups', 'gm2-wordpress-suite'),
                id: 'gm2-existing-groups',
                value: data.slug && existing[data.slug] ? data.slug : '',
                options: options,
                onChange: v => loadGroup(v)
            }),
            el(TextControl, {
                label: __('Group Slug', 'gm2-wordpress-suite'),
                id: 'gm2-group-slug',
                value: data.slug,
                onChange: onSlugChange,
                help: slugError
            }),
            el(TextControl, {
                label: __('Title', 'gm2-wordpress-suite'),
                id: 'gm2-group-title',
                value: data.title,
                onChange: v => setData({ ...data, title: v })
            }),
            el(SelectControl, {
                label: __('Scope', 'gm2-wordpress-suite'),
                id: 'gm2-group-scope',
                value: data.scope,
                options: scopeOptions,
                onChange: v => setData({ ...data, scope: v, objects: [] }),
                help: __('Scope determines the type of content (post types or taxonomies) this field group attaches to.', 'gm2-wordpress-suite')
            }),
            el(FormTokenField, {
                label: __('Objects', 'gm2-wordpress-suite'),
                id: 'gm2-group-objects',
                value: data.objects,
                suggestions: suggestions,
                onChange: tokens => setData({ ...data, objects: tokens }),
                help: sprintf(
                    __('Select the specific %s where this field group should appear.', 'gm2-wordpress-suite'),
                    data.scope === 'taxonomy' ? __('taxonomies', 'gm2-wordpress-suite') : __('post types', 'gm2-wordpress-suite')
                )
            }),
            existing[data.slug] && el('div', { className: 'gm2-fg-group-actions' },
                el(Button, { isDestructive: true, onClick: deleteGroup }, __('Delete Group', 'gm2-wordpress-suite')),
                el(Button, { onClick: renameGroup }, __('Rename', 'gm2-wordpress-suite')),
                el(Button, { onClick: openExportModal }, __('Export JSON', 'gm2-wordpress-suite'))
            ),
            exportOpen && el(Modal, {
                title: __('Export Field Groups', 'gm2-wordpress-suite'),
                onRequestClose: () => { if(!exporting){ setExportOpen(false); } },
                shouldCloseOnClickOutside: !exporting,
                shouldCloseOnEsc: !exporting
            },
                el('p', {}, __('Select the field groups to include in the JSON export.', 'gm2-wordpress-suite')),
                Object.keys(existing).length ? el('div', { className: 'gm2-fg-export-list' },
                    Object.keys(existing).sort().map(sl => el(CheckboxControl, {
                        key: sl,
                        label: existing[sl].title || sl,
                        checked: exportSelection.includes(sl),
                        onChange: () => toggleExportGroup(sl)
                    }))
                ) : el('p', {}, __('No field groups available.', 'gm2-wordpress-suite')),
                exportError && el('p', { className: 'gm2-fg-error' }, exportError),
                el('div', { className: 'gm2-fg-export-actions' },
                    el(Button, { onClick: () => setExportOpen(false), disabled: exporting }, __('Cancel', 'gm2-wordpress-suite')),
                    el(Button, { isPrimary: true, onClick: performExport, disabled: exporting || exportSelection.length === 0, isBusy: exporting }, __('Download JSON', 'gm2-wordpress-suite'))
                )
            )
        );
    };

    const FieldsStep = ({ data, setData }) => {
        const [ field, setField ] = useState({ label: '', slug: '', type: 'text', expose_in_rest: false });
        const [ editIndex, setEditIndex ] = useState(null);
        const [ error, setError ] = useState('');
        const [ fieldsOpen, setFieldsOpen ] = useState(true);

        const fieldTypes = [
            { label: __('Text', 'gm2-wordpress-suite'), value: 'text' },
            { label: __('Textarea', 'gm2-wordpress-suite'), value: 'textarea' },
            { label: __('Select', 'gm2-wordpress-suite'), value: 'select' },
            { label: __('Number', 'gm2-wordpress-suite'), value: 'number' },
            { label: __('Color', 'gm2-wordpress-suite'), value: 'color' }
        ];
        const restExposureMessage = __('Fields exposed to the REST API require their post type or taxonomy to enable "Show in REST" in the model settings.', 'gm2-wordpress-suite');
        const hasRestExposure = field.expose_in_rest || data.fields.some(f => f.expose_in_rest);

        const addField = () => {
            if(!field.slug){
                setError(__('Field slug is required', 'gm2-wordpress-suite'));
                dispatch('core/notices').createNotice('error', __('Field slug is required', 'gm2-wordpress-suite'));
                return;
            }
            if(data.fields.some((f,i) => f.slug === field.slug && i !== editIndex)){
                setError(__('Field slug must be unique', 'gm2-wordpress-suite'));
                dispatch('core/notices').createNotice('error', __('Field slug must be unique', 'gm2-wordpress-suite'));
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
            setField({ label: '', slug: '', type: 'text', expose_in_rest: false });
            setEditIndex(null);
        };

        const editField = (i) => {
            setField({ expose_in_rest: false, ...data.fields[i] });
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
            data.fields.length > 0 && el(PanelBody, { title: __('Fields', 'gm2-wordpress-suite'), opened: fieldsOpen, onToggle: () => setFieldsOpen(!fieldsOpen), 'aria-expanded': fieldsOpen, role: 'region' },
                el(Sortable, { onSortEnd },
                    data.fields.map((f,i) => el(Sortable.Item, { key: f.slug || i },
                        el(Card, { className: 'gm2-field-item' },
                            el(CardBody, {},
                                el('strong', {}, f.label || __('(no label)', 'gm2-wordpress-suite')),
                                el('div', {}, sprintf(__('Slug: %s', 'gm2-wordpress-suite'), f.slug)),
                                el('div', {}, sprintf(__('Type: %s', 'gm2-wordpress-suite'), f.type)),
                                el(Button, { isLink: true, onClick: () => editField(i) }, __('Edit', 'gm2-wordpress-suite')),
                                el(Button, { isLink: true, onClick: () => removeField(i) }, __('Delete', 'gm2-wordpress-suite'))
                            )
                        )
                    ))
                )
            ),
            hasRestExposure && el('p', { className: 'gm2-fg-rest-notice', role: 'note' }, restExposureMessage),
            el(TextControl, {
                label: __('Field Label', 'gm2-wordpress-suite'),
                id: 'gm2-field-label',
                value: field.label,
                onChange: v => setField({ ...field, label: v })
            }),
            el(TextControl, {
                label: __('Field Slug', 'gm2-wordpress-suite'),
                id: 'gm2-field-slug',
                value: field.slug,
                onChange: v => setField({ ...field, slug: v }),
                help: error
            }),
            el(SelectControl, {
                label: __('Field Type', 'gm2-wordpress-suite'),
                id: 'gm2-field-type',
                value: field.type,
                options: fieldTypes,
                onChange: v => setField({ ...field, type: v })
            }),
            el(ToggleControl, {
                label: __('Expose in REST API', 'gm2-wordpress-suite'),
                id: 'gm2-field-expose',
                checked: !!field.expose_in_rest,
                onChange: v => setField({ ...field, expose_in_rest: v }),
                help: __('Requires the related post type or taxonomy to enable "Show in REST" for the field to appear in REST responses.', 'gm2-wordpress-suite')
            }),
            el(Button, { isPrimary: true, onClick: addField }, editIndex !== null ? __('Update Field', 'gm2-wordpress-suite') : __('Add Field', 'gm2-wordpress-suite'))
        );
    };


    
    const LocationStep = ({ data, setData }) => {
        const wizard = window.gm2FGWizard || {};
        const paramOptions = [
            { label: __('Post Type', 'gm2-wordpress-suite'), value: 'post_type' },
            { label: __('Taxonomy', 'gm2-wordpress-suite'), value: 'taxonomy' },
            { label: __('Template', 'gm2-wordpress-suite'), value: 'template' }
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
        const ValueControl = ({ rule, onChange, idBase }) => {
            if(rule.param === 'post_type'){
                const options = Object.keys(wizard.postTypes || {}).map(slug => ({ label: wizard.postTypes[slug], value: slug }));
                return el(SelectControl, { label: __('Value', 'gm2-wordpress-suite'), id: idBase, value: rule.value, options, onChange });
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
                        label: __('Taxonomy', 'gm2-wordpress-suite'),
                        id: idBase + '-taxonomy',
                        value: selectedTax,
                        options: taxOptions,
                        onChange: v => { setSelectedTax(v); onChange(v + ':'); }
                    }),
                    selectedTax && el(SelectControl, {
                        label: __('Term', 'gm2-wordpress-suite'),
                        id: idBase + '-term',
                        value: term,
                        options: terms,
                        onChange: v => onChange(selectedTax + ':' + v)
                    })
                );
            }
            return el(TextControl, { label: __('Value', 'gm2-wordpress-suite'), id: idBase, value: rule.value, onChange });
        };
        return el('div', {},
            el('p', { className: 'gm2-location-help' }, __('Each group is evaluated separately. Rules inside a group use the selected relation, and the field group is displayed when any group matches.', 'gm2-wordpress-suite')),
            data.location.map((g,gi) => el(Card, { key: gi, className: 'gm2-location-group', role: 'group', 'aria-label': sprintf(__('Location group %d', 'gm2-wordpress-suite'), gi + 1) },
                el(CardBody, {},
                    el(SelectControl, {
                        label: __('Group Relation', 'gm2-wordpress-suite'),
                        id: 'gm2-group-relation-' + gi,
                        value: g.relation || 'AND',
                        options: [ { label: __('AND', 'gm2-wordpress-suite'), value: 'AND' }, { label: __('OR', 'gm2-wordpress-suite'), value: 'OR' } ],
                        onChange: v => updateGroupRel(gi,v),
                        help: __('Choose how rules in this group are combined.', 'gm2-wordpress-suite')
                    }),
                    g.rules.map((r,ri) => el(Card, { key: ri, className: 'gm2-location-rule', role: 'group', 'aria-label': sprintf(__('Rule %d', 'gm2-wordpress-suite'), ri + 1) },
                        el(CardBody, {},
                            el(SelectControl, {
                                label: __('Parameter', 'gm2-wordpress-suite'),
                                id: 'gm2-rule-param-' + gi + '-' + ri,
                                value: r.param,
                                options: paramOptions,
                                onChange: v => updateRule(gi,ri,'param',v)
                            }),
                            el(SelectControl, {
                                label: __('Operator', 'gm2-wordpress-suite'),
                                id: 'gm2-rule-operator-' + gi + '-' + ri,
                                value: r.operator,
                                options: operatorOptions,
                                onChange: v => updateRule(gi,ri,'operator',v)
                            }),
                            el(ValueControl, {
                                rule: r,
                                idBase: 'gm2-rule-value-' + gi + '-' + ri,
                                onChange: v => updateRule(gi,ri,'value',v)
                            }),
                            el(Button, { isLink: true, onClick: () => removeRule(gi,ri) }, __('Delete Rule', 'gm2-wordpress-suite'))
                        )
                    )),
                    el(Button, { isSecondary: true, onClick: () => addRule(gi) }, __('Add Rule', 'gm2-wordpress-suite')),
                    el(Button, { isDestructive: true, onClick: () => removeGroup(gi) }, __('Remove Group', 'gm2-wordpress-suite'))
                )
            )),
            el(Button, { isPrimary: true, onClick: addGroup }, __('Add Location Group', 'gm2-wordpress-suite'))
        );
    };

    const ReviewStep = ({ data, onEdit }) => {
        const fieldsTable = data.fields.length ?
            el('table', { className: 'gm2-fg-review-fields' },
                el('thead', {},
                    el('tr', {},
                        el('th', {}, __('Label', 'gm2-wordpress-suite')),
                        el('th', {}, __('Slug', 'gm2-wordpress-suite')),
                        el('th', {}, __('Type', 'gm2-wordpress-suite'))
                    )
                ),
                el('tbody', {},
                    data.fields.map((f,i) => el('tr', { key: i },
                        el('td', {}, f.label),
                        el('td', {}, f.slug),
                        el('td', {}, f.type)
                    ))
                )
            ) : el('p', {}, __('No fields defined.', 'gm2-wordpress-suite'));

        const locationSummary = data.location.length ?
            data.location.map((g,gi) => {
                const relationLabel = g.relation === 'OR' ? __('OR', 'gm2-wordpress-suite') : __('AND', 'gm2-wordpress-suite');
                return el('div', { key: gi },
                    el('strong', {}, sprintf(__('Group %1$d (%2$s)', 'gm2-wordpress-suite'), gi + 1, relationLabel)),
                    el('ul', {},
                        g.rules.map((r,ri) => el('li', { key: ri },
                            sprintf(__('%1$s %2$s %3$s', 'gm2-wordpress-suite'), r.param, r.operator, Array.isArray(r.value) ? r.value.join(', ') : r.value)
                        ))
                    )
                );
            }) : el('p', {}, __('No location rules.', 'gm2-wordpress-suite'));

        const scopeLabel = data.scope === 'taxonomy' ? __('Taxonomy', 'gm2-wordpress-suite') : data.scope === 'template' ? __('Template', 'gm2-wordpress-suite') : __('Post Type', 'gm2-wordpress-suite');

        return el('div', {},
            el('h3', {}, __('General', 'gm2-wordpress-suite'), ' ', el(Button, { isLink: true, onClick: () => onEdit(1) }, __('Edit', 'gm2-wordpress-suite'))),
            el('ul', {},
                el('li', {}, sprintf(__('Slug: %s', 'gm2-wordpress-suite'), data.slug)),
                el('li', {}, sprintf(__('Title: %s', 'gm2-wordpress-suite'), data.title)),
                el('li', {}, sprintf(__('Scope: %s', 'gm2-wordpress-suite'), scopeLabel)),
                el('li', {}, sprintf(__('Objects: %s', 'gm2-wordpress-suite'), data.objects.length ? data.objects.join(', ') : __('None', 'gm2-wordpress-suite')))
            ),
            el('h3', {}, __('Fields', 'gm2-wordpress-suite'), ' ', el(Button, { isLink: true, onClick: () => onEdit(2) }, __('Edit', 'gm2-wordpress-suite'))),
            fieldsTable,
            el('h3', {}, __('Location Rules', 'gm2-wordpress-suite'), ' ', el(Button, { isLink: true, onClick: () => onEdit(3) }, __('Edit', 'gm2-wordpress-suite'))),
            locationSummary
        );
    };

    const Wizard = () => {
        const [ existing, setExisting ] = useState((window.gm2FGWizard && window.gm2FGWizard.groups) || {});
        const [ step, setStep ] = useState(1);
        const [ data, setData ] = useState({ slug: '', title: '', scope: 'post_type', objects: [], fields: [], location: [] });
        const [ saving, setSaving ] = useState(false);
        const [ error, setError ] = useState('');
        const steps = [
            __('Details', 'gm2-wordpress-suite'),
            __('Fields', 'gm2-wordpress-suite'),
            __('Location', 'gm2-wordpress-suite'),
            __('Review', 'gm2-wordpress-suite')
        ];

        const next = () => {
            if(step === 1 && !data.slug){
                setError(__('Slug is required', 'gm2-wordpress-suite'));
                return;
            }
            if(step === 2 && data.fields.length === 0){
                setError(__('At least one field is required', 'gm2-wordpress-suite'));
                return;
            }
            setError('');
            setStep(step + 1);
        };
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
                type: group.fields[key].type || 'text',
                expose_in_rest: !!group.fields[key].expose_in_rest
            }));
            const objects = group.objects || [];
            setData({ slug: slug, title: group.title || '', scope: group.scope || 'post_type', objects, fields, location: group.location || [] });
        };

        const renderStep = () => {
            if(step === 1) return el(StepOne, { data, setData, existing, loadGroup, setExisting });
            if(step === 2) return el(FieldsStep, { data, setData });
            if(step === 3) return el(LocationStep, { data, setData });
            return el(ReviewStep, { data, onEdit: goto });
        };

        const save = () => {
            if(saving) return;
            if(!data.slug){
                setError(__('Slug is required', 'gm2-wordpress-suite'));
                setStep(1);
                return;
            }
            setSaving(true);
            setError('');
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
                    dispatch('core/notices').createNotice('success', __('Field group saved', 'gm2-wordpress-suite'), { type: 'snackbar' });
                    setTimeout(() => {
                        window.location.href = (window.gm2FGWizard && window.gm2FGWizard.listUrl) || 'admin.php?page=gm2-custom-posts';
                    }, 1500);
                } else {
                    const msg = resp && resp.data && resp.data.message ? resp.data.message : __('Error saving', 'gm2-wordpress-suite');
                    setError(msg);
                }
            }).catch(() => {
                setError(__('Error saving', 'gm2-wordpress-suite'));
            }).finally(() => {
                setSaving(false);
            });
        };

        return el('div', { className: 'gm2-fg-wizard' },
            el(Panel, {},
                el(PanelBody, { title: __('Field Group Wizard', 'gm2-wordpress-suite'), initialOpen: true },
                    el('div', { className: 'gm2-fg-stepper' },
                        el('div', { className: 'gm2-fg-stepper-label' }, sprintf(__('Step %1$d of %2$d', 'gm2-wordpress-suite'), step, steps.length)),
                        el('progress', { max: steps.length, value: step })
                    ),
                    renderStep(),
                    error && el('p', { className: 'gm2-fg-error' }, error),
                    el('div', { className: 'gm2-fg-wizard-buttons' }, [
                        step > 1 && el(Button, { onClick: back, disabled: saving }, __('Back', 'gm2-wordpress-suite')),
                        step < steps.length && el(Button, { isPrimary: true, onClick: next, disabled: saving }, __('Next', 'gm2-wordpress-suite')),
                        step === steps.length && el(Button, { isPrimary: true, onClick: save, isBusy: saving, disabled: saving }, __('Finish', 'gm2-wordpress-suite'))
                    ])
                )
            )
        );
    };

    addPassive(document, 'DOMContentLoaded', () => {
        const root = document.getElementById('gm2-fg-wizard-root');
        if(root){
            render(el(Wizard), root);
        }
    });
})(window.wp);
