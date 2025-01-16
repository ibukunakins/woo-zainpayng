const zainpaySettings = window.wc.wcSettings.getSetting( 'zainpayng_data', {} );
const zainpayLabel = window.wp.htmlEntities.decodeEntities( zainpaySettings.title || 'ZainPayNG' );

const zainpayContent = () => {
    return window.wp.htmlEntities.decodeEntities( zainpaySettings.description || 'Complete payment using ZainPayNG' );
};
const ZainpayNG_Block = {
    name: 'zainpayng',
    label: zainpayLabel,
    content: Object( window.wp.element.createElement )( zainpayContent, null ),
    edit: Object( window.wp.element.createElement )( zainpayContent, null ),
    canMakePayment: () => true,
    ariaLabel: zainpayLabel,
    supports: {
        features: zainpaySettings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( ZainpayNG_Block );