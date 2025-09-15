(function(wp){
    const { createElement: el, useState, useEffect } = wp.element;
    const { render } = wp.element;
    const { Button, TextControl, SelectControl, PanelBody, Panel, NoticeList, SnackbarList } = wp.components;
    const { dispatch, useSelect } = wp.data;
    const addPassive = !window.AE_PERF_DISABLE_PASSIVE && window.aePerf?.addPassive
        ? window.aePerf.addPassive
        : (el, type, handler, options) => el.addEventListener(type, handler, options);

    const slugify = (str) => str.toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');

    const StepOne = ({ existing, currentModel, setCurrentModel, loadModel, rawSlug, setRawSlug, stepOneErrors, data, setData, setStepOneErrors }) => {
        const options = [ { label: 'New', value: '' } ];
        Object.keys(existing).forEach(sl => {
            options.push({ label: existing[sl].label || sl, value: sl });
        });
        const [ selectedPreset, setSelectedPreset ] = useState('');
        const [ importingPreset, setImportingPreset ] = useState(false);
        const presetSource = (window.gm2CPTWizard && window.gm2CPTWizard.presets) || [];
        const humanizePreset = (value) => {
            if(!value || typeof value !== 'string'){ return value; }
            return value.replace(/\.json$/,'').replace(/[-_]+/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        };
        const presetOptions = Array.isArray(presetSource)
            ? presetSource.map(item => {
                if(typeof item === 'string'){
                    return { value: item, label: humanizePreset(item) };
                }
                if(item && typeof item === 'object'){
                    if(item.value){
                        return { value: item.value, label: item.label || humanizePreset(item.value) };
                    }
                    if(item.file){
                        return { value: item.file, label: item.label || humanizePreset(item.file) };
                    }
                }
                return null;
            }).filter(Boolean)
            : Object.keys(presetSource).map(key => {
                const val = presetSource[key];
                const value = val && typeof val === 'object' && val.value ? val.value : key;
                const label = (val && typeof val === 'object' && (val.label || val.name)) || (typeof val === 'string' ? val : null) || humanizePreset(value);
                return { value, label };
            });
        const validFieldTypes = [ 'text', 'textarea', 'number', 'select', 'checkbox', 'radio', 'email', 'url', 'date' ];
        const parsePreset = (raw) => {
            if(!raw){
                throw new Error('Preset data missing.');
            }
            let blueprint = raw;
            if(typeof raw === 'string'){
                try {
                    blueprint = JSON.parse(raw);
                } catch (err) {
                    throw new Error('Invalid preset JSON.');
                }
            }
            if(!blueprint || typeof blueprint !== 'object'){
                throw new Error('Invalid preset data.');
            }
            const postTypes = blueprint.post_types || {};
            const slugs = Object.keys(postTypes);
            if(!slugs.length){
                throw new Error('Preset is missing a post type.');
            }
            const presetSlug = slugify(slugs[0]);
            const presetPT = postTypes[slugs[0]] || {};
            const presetLabel = presetPT.label || (presetPT.labels && (presetPT.labels.singular_name || presetPT.labels.name)) || humanizePreset(presetSlug);
            const seenFields = new Set();
            const fields = [];
            (blueprint.field_groups || []).forEach(group => {
                (group && group.fields || []).forEach(field => {
                    if(!field){ return; }
                    const slug = slugify(field.name || field.key || '');
                    if(!slug || seenFields.has(slug)){ return; }
                    let type = String(field.type || 'text').toLowerCase();
                    if(type === 'datetime' || type === 'datetime-local'){ type = 'date'; }
                    if(!validFieldTypes.includes(type)){
                        type = 'text';
                    }
                    fields.push({
                        label: field.label || humanizePreset(slug),
                        slug,
                        type
                    });
                    seenFields.add(slug);
                });
            });
            const taxonomies = [];
            const presetTax = blueprint.taxonomies || {};
            Object.keys(presetTax).forEach(taxSlug => {
                const tax = presetTax[taxSlug] || {};
                const objects = Array.isArray(tax.object_type) ? tax.object_type : [];
                if(objects.includes(slugs[0])){
                    taxonomies.push({
                        slug: slugify(taxSlug),
                        label: (tax.labels && (tax.labels.singular_name || tax.labels.name)) || tax.label || humanizePreset(taxSlug)
                    });
                }
            });
            return { slug: presetSlug, label: presetLabel, fields, taxonomies };
        };
        const notify = (status, message) => {
            dispatch('core/notices').createNotice(status, message, { isDismissible: true, type: 'snackbar' });
        };
        const importPreset = () => {
            if(!selectedPreset){
                notify('error', 'Please select a preset to import.');
                return;
            }
            const nonce = (window.gm2CPTWizard && (window.gm2CPTWizard.importNonce || window.gm2CPTWizard.presetNonce));
            if(!nonce){
                notify('error', 'Preset import is not available.');
                return;
            }
            const payload = new URLSearchParams();
            payload.append('action', 'gm2_import_preset');
            payload.append('nonce', nonce);
            payload.append('file', selectedPreset);
            setImportingPreset(true);
            fetch(window.gm2CPTWizard.ajax, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            }).then(r => r.json()).then(resp => {
                if(resp && resp.success){
                    const blueprint = resp.data && (resp.data.blueprint || resp.data.preset || resp.data);
                    try {
                        const parsed = parsePreset(blueprint);
                        setCurrentModel('');
                        setStepOneErrors({});
                        setData({ slug: parsed.slug, label: parsed.label, fields: parsed.fields, taxonomies: parsed.taxonomies });
                        setRawSlug(parsed.slug);
                        notify('success', (resp.data && resp.data.message) || 'Preset applied.');
                    } catch (err) {
                        notify('error', err.message || 'Failed to apply preset.');
                    }
                } else {
                    const message = resp && resp.data && resp.data.message ? resp.data.message : 'Failed to import preset.';
                    notify('error', message);
                }
            }).catch(() => {
                notify('error', 'Failed to import preset.');
            }).finally(() => {
                setImportingPreset(false);
            });
        };
        return el('div', {},
            options.length > 1 && el(SelectControl, {
                label: 'Existing Models',
                value: currentModel,
                options: options,
                onChange: v => { setCurrentModel(v); loadModel(v); }
            }),
            el('div', { className: 'gm2-cpt-preset-import' },
                el(SelectControl, {
                    label: 'Blueprint Presets',
                    value: selectedPreset,
                    options: [ { label: presetOptions.length ? 'Select a preset' : 'No presets available', value: '' }, ...presetOptions ],
                    onChange: v => setSelectedPreset(v),
                    disabled: !presetOptions.length
                }),
                el(Button, {
                    isSecondary: true,
                    onClick: importPreset,
                    disabled: !presetOptions.length || importingPreset,
                    isBusy: importingPreset
                }, 'Add from Preset')
            ),
            el(TextControl, {
                label: 'Post Type Slug',
                value: rawSlug,
                onChange: v => { setRawSlug(v); setStepOneErrors(e => ({ ...e, slug: undefined })); },
                onBlur: () => setRawSlug(slugify(rawSlug)),
                help: stepOneErrors.slug
            }),
            el(TextControl, {
                label: 'Label',
                value: data.label,
                onChange: v => {
                    setData(d => ({ ...d, label: v }));
                    if(!rawSlug){
                        setRawSlug(slugify(v));
                    }
                    setStepOneErrors(e => ({ ...e, label: undefined, slug: undefined }));
                },
                help: stepOneErrors.label
            })
        );
    };

    const Wizard = () => {
        const [ existing, setExisting ] = useState((window.gm2CPTWizard && window.gm2CPTWizard.models) || {});
        const steps = ['Post Type', 'Fields', 'Taxonomies', 'Review'];
        const [ step, setStep ] = useState(1);
        const [ data, setData ] = useState({ slug: '', label: '', fields: [], taxonomies: [] });
        const [ rawSlug, setRawSlug ] = useState('');
        const [ currentModel, setCurrentModel ] = useState('');
        const [ stepOneErrors, setStepOneErrors ] = useState({});
        const [ showNewButton, setShowNewButton ] = useState(false);
        const [ saving, setSaving ] = useState(false);
        const errorMap = {
            permission: 'You do not have permission to save models.',
            nonce: 'Security check failed. Please refresh and try again.',
            data: 'Invalid data provided. Please check your inputs and try again.'
        };
        const notices = useSelect( select => select('core/notices').getNotices(), [] );
        const { removeNotice } = dispatch('core/notices');
        const snackbarNotices = notices.filter(n => n.type === 'snackbar');
        const inlineNotices = notices.filter(n => n.type !== 'snackbar');

        const validateStepOne = () => {
            const errs = {};
            if(!data.label.trim()){
                errs.label = 'Label is required';
            }
            if(!data.slug.trim()){
                errs.slug = 'Slug is required';
            } else if(!/^[a-z][a-z0-9_-]*$/.test(data.slug)){
                errs.slug = 'Slug must start with a letter and contain only lowercase letters, numbers, hyphens, or underscores';
            } else if(existing[data.slug] && data.slug !== currentModel){
                errs.slug = 'Slug already exists';
            }
            setStepOneErrors(errs);
            return Object.keys(errs).length === 0;
        };

        const next = () => {
            if(step === 1 && !validateStepOne()){
                return;
            }
            setStep(Math.min(step + 1, steps.length));
        };
        const back = () => setStep(Math.max(step - 1, 1));

        const loadModel = (slug, source = existing) => {
            if(!slug){
                setCurrentModel('');
                setData({ slug: '', label: '', fields: [], taxonomies: [] });
                setRawSlug('');
                return;
            }
            const model = source[slug] || {};
            const fields = (model.fields || []).map(f => ({ label: f.label || '', slug: f.slug || '', type: f.type || 'text' }));
            const taxonomies = (model.taxonomies || []).map(t => ({ slug: t.slug || '', label: t.label || '' }));
            setCurrentModel(slug);
            setStepOneErrors({});
            setData({ slug: slug, label: model.label || '', fields, taxonomies });
            setRawSlug(slug);
        };

        useEffect(() => {
            setData(d => ({ ...d, slug: slugify(rawSlug) }));
        }, [ rawSlug ]);

        const FieldsStep = () => {
            const [ field, setField ] = useState({ label: '', slug: '', type: '' });
            const [ editIndex, setEditIndex ] = useState(null);
            const [ dragIndex, setDragIndex ] = useState(null);
            const [ fieldError, setFieldError ] = useState('');
            const typeOptions = [
                { label: 'Text', value: 'text' },
                { label: 'Textarea', value: 'textarea' },
                { label: 'Number', value: 'number' },
                { label: 'Select', value: 'select' },
                { label: 'Checkbox', value: 'checkbox' },
                { label: 'Radio', value: 'radio' },
                { label: 'Email', value: 'email' },
                { label: 'URL', value: 'url' },
                { label: 'Date', value: 'date' }
            ];
            const validTypes = typeOptions.map(o => o.value);
            const addField = () => {
                if(!field.slug || !validTypes.includes(field.type)){ return; }
                const copy = data.fields.slice();
                const isDuplicate = copy.some((f,i) => f.slug === field.slug && i !== editIndex);
                if(isDuplicate){
                    setFieldError('Field slug must be unique');
                    return;
                }
                if(editIndex !== null){
                    copy[editIndex] = field;
                } else {
                    copy.push(field);
                }
                setData({ ...data, fields: copy });
                setField({ label: '', slug: '', type: '' });
                setEditIndex(null);
                setFieldError('');
            };
            const removeField = (i) => {
                const copy = data.fields.slice();
                copy.splice(i,1);
                setData({ ...data, fields: copy });
                setFieldError('');
            };
            const editField = (i) => {
                setField({ ...data.fields[i] });
                setEditIndex(i);
                setFieldError('');
            };
            const onDragStart = (i) => setDragIndex(i);
            const onDragOver = (i) => {
                if(dragIndex === null || dragIndex === i){ return; }
                const updated = data.fields.slice();
                const [moved] = updated.splice(dragIndex, 1);
                updated.splice(i, 0, moved);
                setDragIndex(i);
                setData({ ...data, fields: updated });
            };
            const onDragEnd = () => setDragIndex(null);
            return el('div', {},
                data.fields.map((f,i) => el('div', {
                    key: i,
                    draggable: true,
                    onDragStart: () => onDragStart(i),
                    onDragOver: e => { e.preventDefault(); onDragOver(i); },
                    onDragEnd,
                    className: 'gm2-sortable-item'
                },
                    `${f.label} (${f.slug})`,
                    el(Button, { isLink: true, onClick: () => editField(i) }, 'Edit'),
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
                    onChange: v => setField({ ...field, slug: slugify(v) }),
                    onBlur: () => setField({ ...field, slug: slugify(field.slug) }),
                    help: fieldError
                }),
                el(SelectControl, {
                    label: 'Field Type',
                    value: field.type,
                    options: [ { label: 'Select Type', value: '' }, ...typeOptions ],
                    onChange: v => setField({ ...field, type: v })
                }),
                el(Button, { isPrimary: true, onClick: addField }, editIndex !== null ? 'Update Field' : 'Add Field')
            );
        };

        const TaxStep = () => {
            const [ tax, setTax ] = useState({ slug: '', label: '' });
            const [ editIndex, setEditIndex ] = useState(null);
            const [ dragIndex, setDragIndex ] = useState(null);
            const [ taxError, setTaxError ] = useState('');
            const addTax = () => {
                if(!tax.slug){ return; }
                const copy = data.taxonomies.slice();
                const isDuplicate = copy.some((t,i) => t.slug === tax.slug && i !== editIndex);
                if(isDuplicate){
                    setTaxError('Taxonomy slug must be unique');
                    return;
                }
                if(editIndex !== null){
                    copy[editIndex] = tax;
                } else {
                    copy.push(tax);
                }
                setData({ ...data, taxonomies: copy });
                setTax({ slug: '', label: '' });
                setEditIndex(null);
                setTaxError('');
            };
            const removeTax = (i) => {
                const copy = data.taxonomies.slice();
                copy.splice(i,1);
                setData({ ...data, taxonomies: copy });
                setTaxError('');
            };
            const editTax = (i) => {
                setTax({ ...data.taxonomies[i] });
                setEditIndex(i);
                setTaxError('');
            };
            const onDragStart = (i) => setDragIndex(i);
            const onDragOver = (i) => {
                if(dragIndex === null || dragIndex === i){ return; }
                const updated = data.taxonomies.slice();
                const [moved] = updated.splice(dragIndex, 1);
                updated.splice(i, 0, moved);
                setDragIndex(i);
                setData({ ...data, taxonomies: updated });
            };
            const onDragEnd = () => setDragIndex(null);
            return el('div', {},
                data.taxonomies.map((t,i) => el('div', {
                    key: i,
                    draggable: true,
                    onDragStart: () => onDragStart(i),
                    onDragOver: e => { e.preventDefault(); onDragOver(i); },
                    onDragEnd,
                    className: 'gm2-sortable-item'
                },
                    `${t.label} (${t.slug})`,
                    el(Button, { isLink: true, onClick: () => editTax(i) }, 'Edit'),
                    el(Button, { isLink: true, onClick: () => removeTax(i) }, 'Delete')
                )),
                el(TextControl, {
                    label: 'Taxonomy Slug',
                    value: tax.slug,
                    onChange: v => setTax({ ...tax, slug: slugify(v) }),
                    onBlur: () => setTax({ ...tax, slug: slugify(tax.slug) }),
                    help: taxError
                }),
                el(TextControl, {
                    label: 'Taxonomy Label',
                    value: tax.label,
                    onChange: v => setTax({ ...tax, label: v })
                }),
                el(Button, { isPrimary: true, onClick: addTax }, editIndex !== null ? 'Update Taxonomy' : 'Add Taxonomy')
            );
        };

        const ReviewStep = () => el('div', { className: 'gm2-cpt-review' },
            el('div', { className: 'gm2-cpt-review-section' },
                el('h3', {}, 'Post Type'),
                el('p', {}, `${data.label} (${data.slug})`),
                el(Button, { isLink: true, onClick: () => setStep(1) }, 'Edit')
            ),
            el('div', { className: 'gm2-cpt-review-section' },
                el('h3', {}, 'Fields'),
                data.fields.length
                    ? el('ul', {}, data.fields.map((f,i) =>
                        el('li', { key: i }, `${f.label} (${f.slug}) - ${f.type}`)
                    ))
                    : el('p', {}, 'None'),
                el(Button, { isLink: true, onClick: () => setStep(2) }, 'Edit')
            ),
            el('div', { className: 'gm2-cpt-review-section' },
                el('h3', {}, 'Taxonomies'),
                data.taxonomies.length
                    ? el('ul', {}, data.taxonomies.map((t,i) =>
                        el('li', { key: i }, `${t.label} (${t.slug})`)
                    ))
                    : el('p', {}, 'None'),
                el(Button, { isLink: true, onClick: () => setStep(3) }, 'Edit')
            )
        );

        const StepIndicator = () => el('div', { className: 'gm2-cpt-stepper' },
            steps.map((label, i) =>
                el('div', {
                    key: i,
                    className: 'gm2-cpt-step' + (step === i + 1 ? ' active' : '')
                },
                    el('span', { className: 'gm2-cpt-step-number' }, i + 1),
                    el('span', { className: 'gm2-cpt-step-label' }, label)
                )
            )
        );

        const renderStep = () => {
            if(step === 1) return el(StepOne, {
                existing,
                currentModel,
                setCurrentModel,
                loadModel,
                rawSlug,
                setRawSlug,
                stepOneErrors,
                data,
                setData,
                setStepOneErrors
            });
            if(step === 2) return el(FieldsStep);
            if(step === 3) return el(TaxStep);
            return el(ReviewStep);
        };

        const save = () => {
            setSaving(true);
            const payload = new URLSearchParams();
            payload.append('action','gm2_save_cpt_model');
            payload.append('nonce', window.gm2CPTWizard.nonce);
            payload.append('slug', slugify(data.slug));
            payload.append('label', data.label);
            payload.append('fields', JSON.stringify(data.fields.map(f => ({ ...f, slug: slugify(f.slug) }))));
            payload.append('taxonomies', JSON.stringify(data.taxonomies.map(t => ({ ...t, slug: slugify(t.slug) }))));
            fetch(window.gm2CPTWizard.ajax, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            }).then(r => r.json()).then(resp => {
                if(resp && resp.success){
                    const slug = slugify(data.slug);
                    const saved = (resp.data && resp.data.post_type) ? resp.data.post_type : {};
                    const model = { ...saved, taxonomies: data.taxonomies };
                    const updated = { ...existing, [slug]: model };
                    setExisting(updated);
                    dispatch('core/notices').createNotice('success', 'Model saved', { isDismissible: true, type: 'snackbar' });
                    loadModel('');
                    setStep(1);
                    setShowNewButton(true);
                } else {
                    const code = resp && resp.data && (resp.data.code || resp.data) || '';
                    const message = (resp && resp.data && resp.data.message) || errorMap[code] || 'Error saving';
                    dispatch('core/notices').createNotice('error', message, { isDismissible: true, type: 'snackbar' });
                }
                setSaving(false);
            }).catch(() => {
                dispatch('core/notices').createNotice('error', 'Error saving', { isDismissible: true, type: 'snackbar' });
                setSaving(false);
            });
        };

        if(showNewButton){
            return el('div', { className: 'gm2-cpt-wizard' },
                el(NoticeList, { notices: inlineNotices, onRemove: removeNotice }),
                el(SnackbarList, { notices: snackbarNotices, onRemove: removeNotice }),
                el('div', { className: 'gm2-cpt-success-actions' },
                    el(Button, { isPrimary: true, onClick: () => setShowNewButton(false) }, 'Create New Model'),
                    el(Button, { onClick: () => window.location.reload() }, 'Reload Page')
                )
            );
        }
        return el('div', { className: 'gm2-cpt-wizard' },
            el(NoticeList, { notices: inlineNotices, onRemove: removeNotice }),
            el(SnackbarList, { notices: snackbarNotices, onRemove: removeNotice }),
            el(Panel, {},
                el(PanelBody, { title: 'CPT Wizard', initialOpen: true },
                    el(StepIndicator),
                    renderStep(),
                    el('div', { className: 'gm2-cpt-wizard-buttons' }, [
                        step > 1 && el(Button, { onClick: back }, 'Back'),
                        step < steps.length && el(Button, { isPrimary: true, onClick: next }, 'Next'),
                        step === steps.length && el(Button, { isPrimary: true, onClick: save, isBusy: saving, disabled: saving }, 'Finish')
                    ])
                )
            )
        );
    };

    addPassive(document, 'DOMContentLoaded', () => {
        const root = document.getElementById('gm2-cpt-wizard-root');
        if(root){
            render(el(Wizard), root);
        }
    });
})(window.wp);

