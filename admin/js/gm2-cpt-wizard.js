(function(wp){
    const { createElement: el, useState } = wp.element;
    const { render } = wp.element;
    const { Button, TextControl, SelectControl, PanelBody, Panel } = wp.components;
    const { dispatch } = wp.data;

    const Wizard = () => {
        const existing = (window.gm2CPTWizard && window.gm2CPTWizard.models) || {};
        const steps = ['Post Type', 'Fields', 'Taxonomies', 'Review'];
        const [ step, setStep ] = useState(1);
        const [ data, setData ] = useState({ slug: '', label: '', fields: [], taxonomies: [] });

        const next = () => setStep(Math.min(step + 1, steps.length));
        const back = () => setStep(Math.max(step - 1, 1));

        const loadModel = (slug) => {
            if(!slug){
                setData({ slug: '', label: '', fields: [], taxonomies: [] });
                return;
            }
            const model = existing[slug] || {};
            const fields = (model.fields || []).map(f => ({ label: f.label || '', slug: f.slug || '', type: f.type || 'text' }));
            const taxonomies = (model.taxonomies || []).map(t => ({ slug: t.slug || '', label: t.label || '' }));
            setData({ slug: slug, label: model.label || '', fields, taxonomies });
        };

        const StepOne = () => {
            const options = [ { label: 'New', value: '' } ];
            Object.keys(existing).forEach(sl => {
                options.push({ label: existing[sl].label || sl, value: sl });
            });
            return el('div', {},
                options.length > 1 && el(SelectControl, {
                    label: 'Existing Models',
                    value: data.slug && existing[data.slug] ? data.slug : '',
                    options: options,
                    onChange: v => loadModel(v)
                }),
                el(TextControl, {
                    label: 'Post Type Slug',
                    value: data.slug,
                    onChange: v => setData({ ...data, slug: v })
                }),
                el(TextControl, {
                    label: 'Label',
                    value: data.label,
                    onChange: v => setData({ ...data, label: v })
                })
            );
        };

        const FieldsStep = () => {
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

        const TaxStep = () => {
            const [ tax, setTax ] = useState({ slug: '', label: '' });
            const addTax = () => {
                if(!tax.slug){ return; }
                setData({ ...data, taxonomies: [ ...data.taxonomies, tax ] });
                setTax({ slug: '', label: '' });
            };
            const removeTax = (i) => {
                const copy = data.taxonomies.slice();
                copy.splice(i,1);
                setData({ ...data, taxonomies: copy });
            };
            return el('div', {},
                data.taxonomies.map((t,i) => el('div', { key: i },
                    `${t.label} (${t.slug})`,
                    el(Button, { isLink: true, onClick: () => removeTax(i) }, 'Delete')
                )),
                el(TextControl, {
                    label: 'Taxonomy Slug',
                    value: tax.slug,
                    onChange: v => setTax({ ...tax, slug: v })
                }),
                el(TextControl, {
                    label: 'Taxonomy Label',
                    value: tax.label,
                    onChange: v => setTax({ ...tax, label: v })
                }),
                el(Button, { isPrimary: true, onClick: addTax }, 'Add Taxonomy')
            );
        };

        const ReviewStep = () => el('div', {},
            el('pre', { className: 'gm2-cpt-review' }, JSON.stringify(data, null, 2))
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
            payload.append('slug', data.slug);
            payload.append('label', data.label);
            payload.append('fields', JSON.stringify(data.fields));
            payload.append('taxonomies', JSON.stringify(data.taxonomies));
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

