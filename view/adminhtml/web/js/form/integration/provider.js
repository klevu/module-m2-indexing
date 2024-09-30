/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

/**
 * @api
 */
define([
    'Magento_Ui/js/form/components/fieldset',
    'Klevu_Configuration/js/form/integration/provider',
    'jquery',
    'mage/translate'
], function (Fieldset, KlevuIntegrationFormProvider, $) {
    'use strict';

    return Fieldset.extend({
        defaults: {
            queueOrdersFormSelector: 'klevu_indexing_integration',
            klevuIntegrationFormProvider: null,
        },

        /**
         * @returns {KlevuIntegrationFormProvider}
         */
        getKlevuIntegrationFormProvider: function () {
            const self = this;

            return self.klevuIntegrationFormProvider || KlevuIntegrationFormProvider();
        },

        /**
         * Initializes component.
         *
         * @returns {Fieldset} Chainable.
         */
        initialize: function () {
            const self = this;
            const klevuIntegrationForm = self.getKlevuIntegrationFormProvider();

            self._super();
            klevuIntegrationForm
                .hideTabsOnLoad();

            return self;
        },

        proceed: function () {
            const self = this;
            let check = $.Deferred();
            const klevuIntegrationForm = self.getKlevuIntegrationFormProvider();
            const currentElement = document.querySelector(
                "[data-index='" + self.queueOrdersFormSelector + "']"
            );

            klevuIntegrationForm.clearMessages()
                .startProcess()
                .closeAllTabs(currentElement)
                .showNextTab(currentElement)
                .openNextTab(currentElement);

            check.resolve();

            klevuIntegrationForm.stopProcess();

            return true;
        }
    });
});
