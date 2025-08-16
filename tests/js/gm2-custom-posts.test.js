const { JSDOM } = require('jsdom');

test('renders fields for selected block grouped by section', () => {
  const dom = new JSDOM('', { url: 'http://localhost' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;

  const store = {
    meta: {},
    selectedBlock: { name: 'core/paragraph' }
  };

  let pluginConfig;

  window.wp = {
    plugins: {
      registerPlugin: (name, obj) => { pluginConfig = obj; }
    },
    editPost: {
      PluginSidebar: (props, ...children) => ({ type: 'PluginSidebar', props, children })
    },
    components: {
      PanelBody: (props, ...children) => ({ type: 'PanelBody', props, children }),
      TextControl: (props) => ({ type: 'TextControl', props }),
      TextareaControl: (props) => ({ type: 'TextareaControl', props }),
      SelectControl: (props) => ({ type: 'SelectControl', props }),
      ToggleControl: (props) => ({ type: 'ToggleControl', props }),
      Button: (props, ...children) => ({ type: 'Button', props, children })
    },
    blockEditor: {
      MediaUpload: (props) => ({ type: 'MediaUpload', props })
    },
    data: {
      useSelect: (mapFn) => mapFn((storeName) => {
        if (storeName === 'core/editor') {
          return { getEditedPostAttribute: () => store.meta };
        }
        if (storeName === 'core/block-editor') {
          return { getSelectedBlock: () => store.selectedBlock };
        }
      }),
      useDispatch: () => ({ editPost: ({ meta }) => { store.meta = { ...store.meta, ...meta }; } })
    },
    element: {
      createElement: (type, props, ...children) => ({ type, props: props || {}, children })
    }
  };

  window.gm2BlockFields = [
    { key: 'global', label: 'Global', type: 'text', section: 'General' },
    { key: 'para', label: 'Paragraph', type: 'text', block: 'core/paragraph', section: 'Text' },
    { key: 'quote', label: 'Quote', type: 'text', block: 'core/quote', section: 'Quote' }
  ];

  require('../../admin/js/gm2-custom-posts-gutenberg.js');

  const render = pluginConfig.render;

  const sidebar = render();
  const panels = Array.isArray(sidebar.children) ? sidebar.children.flat() : [];
  const sections = panels.map(p => p.props.title);
  expect(sections).toEqual(['General', 'Text']);

  store.selectedBlock = { name: 'core/quote' };
  const sidebar2 = render();
  const panels2 = Array.isArray(sidebar2.children) ? sidebar2.children.flat() : [];
  const sections2 = panels2.map(p => p.props.title);
  expect(sections2).toEqual(['General', 'Quote']);
});
