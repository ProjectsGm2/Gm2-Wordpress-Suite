(function () {
    const wizardData = window.gm2PresetWizard || {};
    const presets = Array.isArray(wizardData.presets) ? wizardData.presets : [];

    if (!window.wp || !window.wp.element || !document.getElementById('gm2-preset-wizard-root')) {
        return;
    }

    const { createElement: el, useState, useMemo, useEffect } = window.wp.element;
    const { render } = window.wp.element;
    const { __ } = window.wp.i18n;
    const {
        Button,
        Card,
        CardBody,
        CardHeader,
        CheckboxControl,
        Notice,
        PanelBody,
        SelectControl,
        Spinner,
        Modal,
    } = window.wp.components;

    const strings = wizardData.i18n || {};

    const sendRequest = async (action, payload) => {
        const body = new window.FormData();
        body.append('action', action);
        Object.keys(payload).forEach((key) => {
            const value = payload[key];
            if (Array.isArray(value)) {
                const formKey = key.endsWith('[]') ? key : `${key}[]`;
                value.forEach((item) => body.append(formKey, String(item)));
            } else if (value !== undefined && value !== null) {
                body.append(key, String(value));
            }
        });

        const response = await window.fetch(wizardData.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body,
        });

        if (!response.ok) {
            throw new Error('request_failed');
        }

        return response.json();
    };

    const PresetDetails = ({ preset, elementorActive, selection, onToggle }) => {
        if (!preset) {
            return null;
        }

        const description = preset.description || '';
        const postTypes = Array.isArray(preset.postTypes) ? preset.postTypes : [];
        const taxonomies = Array.isArray(preset.taxonomies) ? preset.taxonomies : [];
        const fieldGroups = Array.isArray(preset.fieldGroups) ? preset.fieldGroups : [];
        const defaultTerms = Array.isArray(preset.defaultTerms) ? preset.defaultTerms : [];
        const blockTemplates = Array.isArray(preset.blockTemplates) ? preset.blockTemplates : [];
        const elementorTemplates = Array.isArray(preset.elementorTemplates) ? preset.elementorTemplates : [];

        return el(
            'div',
            { className: 'gm2-preset-wizard-details' },
            description
                ? el('p', { className: 'gm2-preset-wizard-description' }, description)
                : null,
            el(
                PanelBody,
                { title: strings.postTypesHeading || __('Post types', 'gm2-wordpress-suite'), initialOpen: true },
                postTypes.length
                    ? el(
                        'ul',
                        null,
                        postTypes.map((pt) =>
                            el(
                                'li',
                                { key: pt.slug },
                                el('strong', null, pt.label || pt.slug),
                                pt.fields ? el('span', { className: 'gm2-preset-wizard-count' }, ` · ${pt.fields}`) : null
                            )
                        )
                    )
                    : el('p', null, __('No post types defined.', 'gm2-wordpress-suite'))
            ),
            el(
                PanelBody,
                { title: strings.taxonomiesHeading || __('Taxonomies', 'gm2-wordpress-suite'), initialOpen: false },
                taxonomies.length
                    ? el(
                        'ul',
                        null,
                        taxonomies.map((tax) =>
                            el('li', { key: tax.slug }, el('strong', null, tax.label || tax.slug))
                        )
                    )
                    : el('p', null, __('No taxonomies defined.', 'gm2-wordpress-suite'))
            ),
            el(
                PanelBody,
                { title: strings.fieldGroupsHeading || __('Field groups', 'gm2-wordpress-suite'), initialOpen: false },
                fieldGroups.length
                    ? el(
                        'ul',
                        null,
                        fieldGroups.map((group, index) =>
                            el(
                                'li',
                                { key: `${group.title || index}` },
                                el('strong', null, group.title || __('Field group', 'gm2-wordpress-suite')),
                                group.count
                                    ? el('span', { className: 'gm2-preset-wizard-count' }, ` · ${group.count}`)
                                    : null
                            )
                        )
                    )
                    : el('p', null, __('No field groups defined.', 'gm2-wordpress-suite'))
            ),
            el(
                PanelBody,
                { title: strings.defaultTermsHeading || __('Default terms', 'gm2-wordpress-suite'), initialOpen: false },
                defaultTerms.length
                    ? el(
                        'ul',
                        null,
                        defaultTerms.map((term) =>
                            el(
                                'li',
                                { key: term.taxonomy },
                                el('strong', null, term.taxonomy),
                                term.count
                                    ? el('span', { className: 'gm2-preset-wizard-count' }, ` · ${term.count}`)
                                    : null
                            )
                        )
                    )
                    : el('p', null, __('No default terms provided.', 'gm2-wordpress-suite'))
            ),
            el(
                PanelBody,
                { title: strings.blockTemplatesHeading || __('Block templates', 'gm2-wordpress-suite'), initialOpen: false },
                blockTemplates.length
                    ? el(
                        'ul',
                        null,
                        blockTemplates.map((tpl) =>
                            el(
                                'li',
                                { key: tpl.key },
                                el('strong', null, tpl.key),
                                tpl.description ? ` – ${tpl.description}` : null
                            )
                        )
                    )
                    : el('p', null, __('No block templates bundled.', 'gm2-wordpress-suite'))
            ),
            el(
                PanelBody,
                { title: strings.elementorHeading || __('Elementor templates', 'gm2-wordpress-suite'), initialOpen: false },
                elementorTemplates.length
                    ? elementorTemplates.map((tpl) =>
                        el(CheckboxControl, {
                            key: tpl.key,
                            label: tpl.label,
                            help:
                                tpl.hasFile
                                    ? tpl.description || ''
                                    : strings.templateUnavailable || __('Template bundle is not included with this preset.', 'gm2-wordpress-suite'),
                            checked: !!selection[tpl.key],
                            onChange: (checked) => onToggle(tpl.key, checked),
                            disabled: !tpl.hasFile,
                        })
                    )
                    : el('p', null, strings.noTemplates || __('Preset does not bundle Elementor templates.', 'gm2-wordpress-suite'))
            ),
            !elementorActive && elementorTemplates.length
                ? el(
                    Notice,
                    { status: 'warning', isDismissible: false },
                    strings.elementorInactive || __('Elementor must be active to import templates.', 'gm2-wordpress-suite')
                )
                : null
        );
    };

    const App = () => {
        const initialSlug = presets.length ? presets[0].slug : '';
        const [selectedSlug, setSelectedSlug] = useState(initialSlug);
        const [templateSelection, setTemplateSelection] = useState({});
        const [notice, setNotice] = useState(null);
        const [isApplying, setIsApplying] = useState(false);
        const [confirmVisible, setConfirmVisible] = useState(false);
        const [needsConfirmation, setNeedsConfirmation] = useState(Boolean(wizardData.hasExisting));
        const [isResetting, setIsResetting] = useState(false);

        const selectedPreset = useMemo(
            () => presets.find((preset) => preset.slug === selectedSlug) || null,
            [presets, selectedSlug]
        );

        useEffect(() => {
            setTemplateSelection({});
        }, [selectedSlug]);

        useEffect(() => {
            if (!selectedSlug && presets.length) {
                setSelectedSlug(presets[0].slug);
            }
        }, [presets, selectedSlug]);

        const selectedTemplates = useMemo(
            () => Object.keys(templateSelection).filter((key) => templateSelection[key]),
            [templateSelection]
        );

        const applyDisabled = !wizardData.capable || wizardData.locked || !selectedPreset || isApplying || isResetting;
        const resetDisabled = !wizardData.capable || wizardData.locked || isApplying || isResetting;

        const handleToggleTemplate = (key, checked) => {
            setTemplateSelection((prev) => ({
                ...prev,
                [key]: checked,
            }));
        };

        const handleApply = async (force = false) => {
            if (!selectedPreset) {
                return;
            }
            if (!force && needsConfirmation) {
                setConfirmVisible(true);
                return;
            }

            setConfirmVisible(false);
            setIsApplying(true);
            setNotice(null);

            try {
                const applyResponse = await sendRequest('gm2_presets_apply', {
                    nonce: wizardData.applyNonce,
                    preset: selectedPreset.slug,
                    force: force ? '1' : '0',
                });

                if (!applyResponse || !applyResponse.success) {
                    const data = applyResponse && applyResponse.data ? applyResponse.data : {};
                    if (!force && data.code === 'gm2_preset_conflict') {
                        setNeedsConfirmation(true);
                        setConfirmVisible(true);
                        return;
                    }
                    setNotice({
                        status: 'error',
                        text: data.message || strings.applyError || __('Failed to apply the preset.', 'gm2-wordpress-suite'),
                    });
                    return;
                }

                const messages = [];
                if (applyResponse.data && applyResponse.data.message) {
                    messages.push(applyResponse.data.message);
                } else {
                    messages.push(strings.applySuccess || __('Preset applied.', 'gm2-wordpress-suite'));
                }

                let status = 'success';

                if (selectedTemplates.length) {
                    try {
                        const importResponse = await sendRequest('gm2_presets_import_elementor', {
                            nonce: wizardData.importNonce,
                            preset: selectedPreset.slug,
                            templates: selectedTemplates,
                        });
                        if (importResponse && importResponse.success) {
                            if (importResponse.data && importResponse.data.message) {
                                messages.push(importResponse.data.message);
                            } else {
                                messages.push(strings.importSuccess || __('Elementor templates imported.', 'gm2-wordpress-suite'));
                            }
                        } else if (importResponse && importResponse.data) {
                            status = 'warning';
                            messages.push(
                                importResponse.data.message ||
                                    strings.importPartial ||
                                    __('Some Elementor templates failed to import.', 'gm2-wordpress-suite')
                            );
                        }
                    } catch (error) {
                        status = 'warning';
                        messages.push(strings.importError || __('Failed to import Elementor templates.', 'gm2-wordpress-suite'));
                    }
                } else if (!selectedPreset.elementorTemplates || !selectedPreset.elementorTemplates.length) {
                    messages.push(strings.noTemplates || __('Preset does not bundle Elementor templates.', 'gm2-wordpress-suite'));
                }

                setNotice({ status, text: messages.join(' ') });
                setNeedsConfirmation(true);
            } catch (error) {
                setNotice({
                    status: 'error',
                    text: strings.applyError || __('Failed to apply the preset.', 'gm2-wordpress-suite'),
                });
            } finally {
                setIsApplying(false);
            }
        };

        const handleReset = async () => {
            if (!wizardData.capable || wizardData.locked || isResetting) {
                return;
            }

            const confirmMessage =
                strings.resetConfirm ||
                __(
                    'This will remove existing custom post types, taxonomies, field groups, and schema mappings. Continue?',
                    'gm2-wordpress-suite'
                );

            if (!window.confirm(confirmMessage)) {
                return;
            }

            setIsResetting(true);
            setNotice(null);

            try {
                const resetResponse = await sendRequest('gm2_presets_reset', {
                    nonce: wizardData.resetNonce,
                });

                if (!resetResponse || !resetResponse.success) {
                    const data = resetResponse && resetResponse.data ? resetResponse.data : {};
                    setNotice({
                        status: 'error',
                        text:
                            data.message ||
                            strings.resetError ||
                            __('Failed to reset content definitions.', 'gm2-wordpress-suite'),
                    });
                    return;
                }

                setConfirmVisible(false);
                setNeedsConfirmation(false);
                setNotice({
                    status: 'success',
                    text:
                        (resetResponse.data && resetResponse.data.message) ||
                        strings.resetSuccess ||
                        __('Content definitions restored to defaults.', 'gm2-wordpress-suite'),
                });
            } catch (error) {
                setNotice({
                    status: 'error',
                    text: strings.resetError || __('Failed to reset content definitions.', 'gm2-wordpress-suite'),
                });
            } finally {
                setIsResetting(false);
            }
        };

        const noticeElement = notice
            ? el(
                  Notice,
                  {
                      status: notice.status || 'info',
                      isDismissible: true,
                      onRemove: () => setNotice(null),
                  },
                  notice.text
              )
            : null;

        const options = [
            {
                label: strings.selectPreset || __('Select a preset', 'gm2-wordpress-suite'),
                value: '',
            },
            ...presets.map((preset) => ({
                label: preset.label || preset.slug,
                value: preset.slug,
            })),
        ];

        const bodyChildren = [];

        if (wizardData.locked) {
            bodyChildren.push(
                el(
                    Notice,
                    { key: 'locked', status: 'warning', isDismissible: false },
                    strings.lockedMessage || __('Content model editing is locked for this environment.', 'gm2-wordpress-suite')
                )
            );
        }

        if (!wizardData.capable) {
            bodyChildren.push(
                el(
                    Notice,
                    { key: 'cap', status: 'warning', isDismissible: false },
                    strings.missingCapability || __('You do not have permission to apply presets.', 'gm2-wordpress-suite')
                )
            );
        }

        if (noticeElement) {
            bodyChildren.push(el('div', { key: 'notice' }, noticeElement));
        }

        if (!presets.length) {
            bodyChildren.push(
                el(
                    'p',
                    { key: 'empty' },
                    strings.noPresets || __('No presets are currently available.', 'gm2-wordpress-suite')
                )
            );
        } else {
            bodyChildren.push(
                el(SelectControl, {
                    key: 'select',
                    label: strings.selectPreset || __('Select a preset', 'gm2-wordpress-suite'),
                    value: selectedSlug,
                    options,
                    onChange: (value) => setSelectedSlug(value),
                })
            );

            bodyChildren.push(
                el(PresetDetails, {
                    key: 'details',
                    preset: selectedPreset,
                    elementorActive: !!wizardData.elementorActive,
                    selection: templateSelection,
                    onToggle: handleToggleTemplate,
                })
            );
        }

        const actionButtons = [];

        if (presets.length) {
            actionButtons.push(
                el(
                    Button,
                    {
                        key: 'apply',
                        variant: 'primary',
                        onClick: () => handleApply(false),
                        disabled: applyDisabled,
                    },
                    isApplying
                        ? el(Spinner, { key: 'spinner', className: 'gm2-preset-wizard-spinner' })
                        : strings.applyPreset || __('Apply preset', 'gm2-wordpress-suite')
                )
            );
        }

        actionButtons.push(
            el(
                Button,
                {
                    key: 'reset',
                    variant: 'secondary',
                    onClick: handleReset,
                    disabled: resetDisabled,
                },
                isResetting
                    ? el(Spinner, { key: 'spinner', className: 'gm2-preset-wizard-spinner' })
                    : strings.resetDefaults || __('Reset to defaults', 'gm2-wordpress-suite')
            )
        );

        bodyChildren.push(
            el('div', { key: 'actions', className: 'gm2-preset-wizard-actions' }, actionButtons)
        );

        return el(
            'div',
            { className: 'gm2-preset-wizard' },
            el(
                Card,
                null,
                el(CardHeader, null, strings.heading || __('Blueprint Preset Wizard', 'gm2-wordpress-suite')),
                el(CardBody, null, bodyChildren)
            ),
            confirmVisible
                ? el(
                      Modal,
                      {
                          title: strings.confirmTitle || __('Overwrite existing definitions?', 'gm2-wordpress-suite'),
                          onRequestClose: () => setConfirmVisible(false),
                      },
                      el('p', null, strings.confirmBody || __('Applying a preset will replace existing custom post types, taxonomies, field groups, and schema mappings.', 'gm2-wordpress-suite')),
                      el(
                          'div',
                          { className: 'gm2-preset-wizard-confirm' },
                          el(
                              Button,
                              {
                                  variant: 'primary',
                                  onClick: () => handleApply(true),
                                  disabled: isApplying,
                              },
                              strings.applyPresetAnyway || __('Apply preset anyway', 'gm2-wordpress-suite')
                          ),
                          el(
                              Button,
                              {
                                  variant: 'secondary',
                                  onClick: () => setConfirmVisible(false),
                              },
                              strings.cancel || __('Cancel', 'gm2-wordpress-suite')
                          )
                      )
                  )
                : null
        );
    };

    const root = document.getElementById('gm2-preset-wizard-root');
    if (root) {
        render(el(App), root);
    }
})();
