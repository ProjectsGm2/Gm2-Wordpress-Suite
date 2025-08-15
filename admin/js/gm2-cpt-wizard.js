(function(wp){
    const { createElement: el, useState } = wp.element;
    const { render } = wp.element;
    const { Button, TextControl, PanelBody, Panel } = wp.components;

    const Wizard = () => {
        const [ step, setStep ] = useState(1);
        const [ data, setData ] = useState({ slug: '', label: '' });

        const next = () => setStep(step + 1);
        const back = () => setStep(step - 1);

        const StepOne = () => el('div', {},
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

        const StepTwo = () => el('div', {},
            el('p', null, 'Field builder coming soon.')
        );

        const StepThree = () => el('div', {},
            el('pre', { className: 'gm2-cpt-review' }, JSON.stringify(data, null, 2))
        );

        const renderStep = () => {
            if (step === 1) return el(StepOne);
            if (step === 2) return el(StepTwo);
            return el(StepThree);
        };

        return el('div', { className: 'gm2-cpt-wizard' },
            el(Panel, {},
                el(PanelBody, { title: 'CPT Wizard', initialOpen: true },
                    renderStep(),
                    el('div', { className: 'gm2-cpt-wizard-buttons' }, [
                        step > 1 && el(Button, { onClick: back }, 'Back'),
                        step < 3 && el(Button, { isPrimary: true, onClick: next }, 'Next'),
                        step === 3 && el(Button, { isPrimary: true, onClick: () => alert('Save not implemented') }, 'Finish')
                    ])
                )
            )
        );
    };

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('gm2-cpt-wizard-root');
        if (root) {
            render(el(Wizard), root);
        }
    });
})(window.wp);

