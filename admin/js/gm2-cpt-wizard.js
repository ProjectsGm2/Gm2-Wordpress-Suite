(function(wp){
    const { createElement: el, useState, useEffect } = wp.element;
    const { render } = wp.element;
    const { Button, TextControl, SelectControl, PanelBody, Panel } = wp.components;
    const { dispatch } = wp.data;

    const Wizard = () => {
        const existing = (window.gm2CPTWizard && window.gm2CPTWizard.models) || {};
        const steps = ['Post Type', 'Fields', 'Taxonomies', 'Review'];
        const [ step, setStep ] = useState(1);
        const [ data, setData ] = useState({ slug: '', label: '', fields: [], taxonomies: [] });
        const [ rawSlug, setRawSlug ] = useState('');
        const [ currentModel, setCurrentModel ] = useState('');
        const [ stepOneErrors, setStepOneErrors ] = useState({});

        const slugify = (str) => str.toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');

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

        const loadModel = (slug) => {
            if(!slug){
                setCurrentModel('');
                setData({ slug: '', label: '', fields: [], taxonomies: [] });
                setRawSlug('');
                return;
            }
            const model = existing[slug] || {};
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

        const StepOne = () => {
            const options = [ { label: 'New', value: '' } ];
            Object.keys(existing).forEach(sl => {
                options.push({ label: existing[sl].label || sl, value: sl });
            });
            return el('div', {},
                options.length > 1 && el(SelectControl, {
                    label: 'Existing Models',
                    value: currentModel,
                    options: options,
                    onChange: v => { setCurrentModel(v); loadModel(v); }
                }),
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
                setField(data.fields[i]);
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
                setTax(data.taxonomies[i]);
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
            if(step === 1) return el(StepOne);
            if(step === 2) return el(FieldsStep);
            if(step === 3) return el(TaxStep);
            return el(ReviewStep);
        };

        const save = () => {
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
                    dispatch('core/notices').createNotice('success', 'Model saved', { type: 'snackbar' });
                } else {
                    dispatch('core/notices').createNotice('error', 'Error saving', { type: 'snackbar' });
                }
            }).catch(() => {
                dispatch('core/notices').createNotice('error', 'Error saving', { type: 'snackbar' });
            });
        };

        return el('div', { className: 'gm2-cpt-wizard' },
            el(Panel, {},
                el(PanelBody, { title: 'CPT Wizard', initialOpen: true },
                    el(StepIndicator),
                    renderStep(),
                    el('div', { className: 'gm2-cpt-wizard-buttons' }, [
                        step > 1 && el(Button, { onClick: back }, 'Back'),
                        step < steps.length && el(Button, { isPrimary: true, onClick: next }, 'Next'),
                        step === steps.length && el(Button, { isPrimary: true, onClick: save }, 'Finish')
                    ])
                )
            )
        );
    };

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('gm2-cpt-wizard-root');
        if(root){
            render(el(Wizard), root);
        }
    });
})(window.wp);

