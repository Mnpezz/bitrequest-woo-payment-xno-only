const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement, Fragment } = window.wp.element;
const { SelectControl } = window.wp.components;
const { useEffect, useState } = window.wp.element;

const BitrequestComponent = ({ eventRegistration, emitResponse }) => {
    const [selectedCrypto, setSelectedCrypto] = useState('nano');
    const { onPaymentProcessing } = eventRegistration;

    useEffect(() => {
        const unsubscribe = onPaymentProcessing(() => {
            return {
                type: 'success',
                meta: {
                    paymentMethodData: {
                        bitrequest_crypto: selectedCrypto,
                    },
                },
            };
        });

        return () => unsubscribe();
    }, [onPaymentProcessing, selectedCrypto]);

    return createElement(Fragment, null,
        createElement(SelectControl, {
            label: 'Choose cryptocurrency',
            value: selectedCrypto,
            options: [
                { label: 'Nano', value: 'nano' },
            ],
            onChange: (value) => setSelectedCrypto(value),
            id: 'bitrequest_crypto',
        })
    );
};

registerPaymentMethod({
    name: 'bitrequest',
    label: 'BitRequest',
    content: createElement(BitrequestComponent),
    edit: createElement(BitrequestComponent),
    canMakePayment: () => true,
    ariaLabel: 'BitRequest payment method',
});