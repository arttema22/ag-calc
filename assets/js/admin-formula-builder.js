/**
 * Admin Formula Builder & Field Manager
 */

(function ($) {
    'use strict';

    const FKAdmin = {

        init: function () {
            this.bindEvents();
            this.initSortable();
        },

        bindEvents: function () {
            $('#fk-add-field').on('click', this.addField.bind(this));
            $(document).on('click', '.fk-remove-field', this.removeField.bind(this));
            $(document).on('change', '.fk-field-type', this.changeFieldType.bind(this));
            $(document).on('click', '.fk-add-pricing-row', this.addPricingRow.bind(this));
            $(document).on('click', '.fk-remove-pricing-row', this.removePricingRow.bind(this));
            $(document).on('click', '.fk-variable-tag', this.insertVariable.bind(this));
            $(document).on('click', '.fk-operator-tag', this.insertOperator.bind(this));
            $('#fk-test-formula').on('click', this.testFormula.bind(this));
            $(document).on('input change', '.fk-field-row input, .fk-field-row select',
                this.updateFieldsData.bind(this));
        },

        initSortable: function () {
            $('.fk-fields-list').sortable({
                handle: '.fk-field-handle',
                placeholder: 'fk-field-placeholder',
                update: this.updateFieldsData.bind(this)
            });
        },

        addField: function (e) {
            e.preventDefault();

            const fieldType = $('#fk-new-field-type').val();
            if (!fieldType) {
                alert(fk_admin.i18n.select_type);
                return;
            }

            const index = $('.fk-field-row').length;
            const fieldConfig = fk_admin.fields_registry[fieldType];

            const rowHtml = `
                <div class="fk-field-row" data-index="${index}">
                    <div class="fk-field-header">
                        <span class="fk-field-handle dashicons dashicons-menu"></span>
                        <strong>${fieldConfig.label}</strong>
                        <span class="fk-field-type-badge">${fieldConfig.label}</span>
                        <button type="button" class="fk-remove-field button-link-delete">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                    <div class="fk-field-settings">
                        <div class="fk-field-setting">
                            <label>${fk_admin.i18n.field_key}</label>
                            <input type="text" name="fk_field_key[${index}]" 
                                   value="field_${index}" class="fk-field-key" required>
                        </div>
                        <div class="fk-field-setting">
                            <label>${fk_admin.i18n.field_label}</label>
                            <input type="text" name="fk_field_label[${index}]" 
                                   value="${fieldConfig.label}" class="fk-field-label">
                        </div>
                        <div class="fk-field-setting">
                            <label>${fk_admin.i18n.field_type}</label>
                            <select name="fk_field_type[${index}]" class="fk-field-type">
                                ${this.renderFieldTypesOptions(fieldType)}
                            </select>
                        </div>
                        <div class="fk-field-type-settings" data-type="${fieldType}">
                            ${this.renderFieldTypeSettings(index, fieldType)}
                        </div>
                    </div>
                </div>
            `;

            $('.fk-fields-list').append(rowHtml);
            $('#fk-new-field-type').val('');
            this.updateFieldsData();
        },

        removeField: function (e) {
            e.preventDefault();
            if (confirm(fk_admin.i18n.remove_field)) {
                $(e.currentTarget).closest('.fk-field-row').remove();
                this.updateFieldsData();
            }
        },

        changeFieldType: function (e) {
            const $row = $(e.currentTarget).closest('.fk-field-row');
            const newType = $(e.currentTarget).val();
            const index = $row.data('index');

            $row.find('.fk-field-type-settings').html(this.renderFieldTypeSettings(index, newType));
            $row.find('.fk-field-type-badge').text(fk_admin.fields_registry[newType].label);
            this.updateFieldsData();
        },

        addPricingRow: function (e) {
            e.preventDefault();
            const $container = $(e.currentTarget).siblings('.fk-pricing-table');
            const fieldIndex = $container.data('field-index');
            const rowCount = $container.find('.fk-pricing-row').length;

            const rowHtml = `
                <div class="fk-pricing-row">
                    <input type="text" name="fk_field_options[${fieldIndex}][${rowCount}][value]" 
                           placeholder="${fk_admin.i18n.value}" class="regular-text">
                    <input type="number" name="fk_field_options[${fieldIndex}][${rowCount}][price]" 
                           placeholder="${fk_admin.i18n.price}" class="small-text" value="0">
                    <button type="button" class="fk-remove-pricing-row button-link-delete">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `;

            $container.append(rowHtml);
        },

        removePricingRow: function (e) {
            e.preventDefault();
            $(e.currentTarget).closest('.fk-pricing-row').remove();
        },

        insertVariable: function (e) {
            const variable = $(e.currentTarget).data('var');
            this.insertIntoFormula(`{{${variable}}}`);
        },

        insertOperator: function (e) {
            const operator = $(e.currentTarget).data('op');
            this.insertIntoFormula(` ${operator} `);
        },

        insertIntoFormula: function (text) {
            const $textarea = $('#fk_formula_expression');
            const textarea = $textarea[0];
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;

            const value = $textarea.val();
            $textarea.val(value.substring(0, start) + text + value.substring(end));
            textarea.selectionStart = textarea.selectionEnd = start + text.length;
            $textarea.focus();
        },

        testFormula: function (e) {
            e.preventDefault();

            const expression = $('#fk_formula_expression').val();
            const $resultDiv = $('#fk-formula-test-result');

            if (!expression) {
                $resultDiv.html('<span class="error">' + fk_admin.i18n.empty_formula + '</span>');
                return;
            }

            const testData = {};
            $('.fk-field-row').each(function () {
                const key = $(this).find('.fk-field-key').val();
                const type = $(this).find('.fk-field-type').val();

                if (key) {
                    if (['number', 'range'].includes(type)) {
                        testData[key] = 10;
                    } else if (type === 'checkbox') {
                        testData[key] = 1;
                    } else {
                        const firstOption = $(this).find('.fk-pricing-row input[type="text"]').first().val();
                        testData[key] = firstOption || 'default';
                    }
                }
            });

            $.post(fk_admin.ajax_url, {
                action: 'fk_test_formula',
                nonce: fk_admin.nonce,
                expression: expression,
                variables: testData,
                post_id: $('#post_ID').val()
            }, function (response) {
                if (response.success) {
                    $resultDiv.html(
                        '<span class="success">' +
                        fk_admin.i18n.calculation_result + ' <strong>' +
                        response.data.price + ' ₽</strong></span>'
                    );
                } else {
                    $resultDiv.html('<span class="error">' + response.data.error + '</span>');
                }
            });
        },

        updateFieldsData: function () {
            const fields = [];

            $('.fk-field-row').each(function (index) {
                const $row = $(this);
                const field = {
                    key: $row.find('.fk-field-key').val(),
                    label: $row.find('.fk-field-label').val(),
                    type: $row.find('.fk-field-type').val(),
                    config: {}
                };

                const type = field.type;
                if (['number', 'range'].includes(type)) {
                    field.config = {
                        min: $row.find('input[name*="[min]"]').val(),
                        max: $row.find('input[name*="[max]"]').val(),
                        step: $row.find('input[name*="[step]"]').val()
                    };
                }

                if (['select', 'radio'].includes(type)) {
                    const options = [];
                    $row.find('.fk-pricing-row').each(function () {
                        const $pricingRow = $(this);
                        options.push({
                            value: $pricingRow.find('input[type="text"]').val(),
                            price: $pricingRow.find('input[type="number"]').val()
                        });
                    });
                    field.config.options = options;
                }

                if (type === 'checkbox') {
                    field.config.price = $row.find('input[name*="[price]"]').val();
                }

                fields.push(field);
            });

            $('#fk_fields_data').val(JSON.stringify(fields));
        },

        renderFieldTypesOptions: function (selected) {
            let options = '<option value="">' + fk_admin.i18n.select_type + '</option>';

            for (const [key, config] of Object.entries(fk_admin.fields_registry)) {
                options += `<option value="${key}" ${key === selected ? 'selected' : ''}>${config.label}</option>`;
            }

            return options;
        },

        renderFieldTypeSettings: function (index, type) {
            let html = '';

            if (['number', 'range'].includes(type)) {
                html = `
                    <div class="fk-field-setting">
                        <label>Мин</label>
                        <input type="number" name="fk_field_config[${index}][min]" value="1" class="small-text">
                    </div>
                    <div class="fk-field-setting">
                        <label>Макс</label>
                        <input type="number" name="fk_field_config[${index}][max]" value="100" class="small-text">
                    </div>
                    <div class="fk-field-setting">
                        <label>Шаг</label>
                        <input type="number" name="fk_field_config[${index}][step]" value="1" class="small-text">
                    </div>
                `;
            }

            if (['select', 'radio'].includes(type)) {
                html = `
                    <div class="fk-field-setting">
                        <label>Значения и цены</label>
                        <div class="fk-pricing-table" data-field-index="${index}">
                            <div class="fk-pricing-row">
                                <input type="text" name="fk_field_options[${index}][0][value]" 
                                       placeholder="Значение" class="regular-text">
                                <input type="number" name="fk_field_options[${index}][0][price]" 
                                       placeholder="Цена" class="small-text" value="0">
                                <button type="button" class="fk-remove-pricing-row button-link-delete">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="fk-add-pricing-row button button-secondary">
                            <span class="dashicons dashicons-plus-alt"></span> Добавить значение
                        </button>
                    </div>
                `;
            }

            if (type === 'checkbox') {
                html = `
                    <div class="fk-field-setting">
                        <label>Цена если отмечено</label>
                        <input type="number" name="fk_field_config[${index}][price]" value="0" class="small-text">
                    </div>
                `;
            }

            return html;
        }
    };

    $(document).ready(function () {
        FKAdmin.init();
    });

})(jQuery);