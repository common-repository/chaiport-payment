const {registerPaymentMethod} = window.wc.wcBlocksRegistry

const settings = window.wc.wcSettings.getSetting('chaiport_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title);
const Content = ({description}) => {
    return React.createElement('div', {style: {paddingTop: '10px'}}, [
        React.createElement('img', {
            src: settings.icon,
            alt: 'Icon',
            style: {float: 'right', margin: 'auto', display: 'block'}
        }),
        React.createElement('div', {
            dangerouslySetInnerHTML: {__html: window.wp.htmlEntities.decodeEntities(description)},
            style: {paddingRight: '50px'}
        })
    ]);
};

const portone = {
    name: 'chaiport',
    icon: settings.icon,
    label: label,
    content: Object(window.wp.element.createElement)(Content, {description: settings.description}),
    edit: Object(window.wp.element.createElement)(Content, {description: settings.description}),
    placeOrderButtonLabel: window.wp.i18n.__('Continue', 'chaiport'),
    canMakePayment: () => true,
    ariaLabel: label
};

registerPaymentMethod(portone);

