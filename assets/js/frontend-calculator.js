/**
 * Frontend Calculator - REST API Integration
 */

(function ($) {
    'use strict';

    const AGCalculator = {

        init: function () {
            this.bindEvents();
            this.initAllCalculators();
        },

        /**
         * Привязка событий
         */
        bindEvents: function () {
            // Изменение любого поля
            $(document).on('change input', '.ag-field-input, .ag-field-select, .ag-field-radio-input, .ag-field-checkbox, .ag-field-range',
                this.onFieldChange.bind(this));

            // Переключение вкладок
            $(document).on('shown.bs.tab', '.ag-tabs-nav a[data-toggle="tab"]',
                this.onTabShown.bind(this));

            // Обновление значения слайдера
            $(document).on('input', '.ag-field-range', this.onRangeInput.bind(this));

            // Кнопка заказа
            $(document).on('click', '.ag-order-btn', this.onOrderClick.bind(this));
        },

        /**
         * Инициализация всех калькуляторов на странице
         */
        initAllCalculators: function () {
            $('.ag-product-calculator').each(function () {
                AGCalculator.initCalculator($(this));
            });
        },

        /**
         * Инициализация конкретного калькулятора
         */
        initCalculator: function ($calculator) {
            const productSlug = $calculator.data('product-slug');

            if (!productSlug) return;

            // Загружаем конфигурацию продукта
            this.loadProductConfig($calculator, productSlug);
        },

        /**
         * Загрузка конфигурации продукта
         */
        loadProductConfig: function ($calculator, productSlug) {
            $.ajax({
                url: ag_calc.api_root + '/products/' + productSlug,
                method: 'GET',
                headers: { 'X-WP-Nonce': ag_calc.nonce },
                success: (config) => {
                    $calculator.data('config', config);
                    this.populateFieldValues($calculator, config.fields);
                    // Первый расчет после загрузки конфига и заполнения полей
                    setTimeout(() => {
                        this.calculatePrice($calculator);
                    }, 100);
                },
                error: function (xhr) {
                    console.error('Failed to load product config:', xhr);
                }
            });
        },

        /**
         * Заполнение значений полей из конфига
         */
        populateFieldValues: function ($calculator, fields) {
            fields.forEach(function (field) {
                const key = field.key;
                const type = field.type;
                const config = field.config || {};

                if (type === 'hidden_number') return;

                if (type === 'range' && config.default) {
                    const $range = $calculator.find(`[data-field-key="${key}"].ag-field-range`);
                    const $value = $range.siblings('.ag-range-value');
                    $range.val(config.default);
                    $value.text(config.default);
                }

                if (type === 'select' && config.options && config.options.length > 0) {
                    const $field = $calculator.find(`[data-field-key="${key}"].ag-field-select`);
                    if ($field.length) {
                        $field.val(config.options[0].value);
                    }
                }

                if (type === 'radio' && config.options && config.options.length > 0) {
                    const $firstRadio = $calculator.find(`[data-field-key="${key}"].ag-field-radio-input`).first();
                    if ($firstRadio.length) {
                        $firstRadio.prop('checked', true);
                    }
                }
            });
        },

        /**
         * Обработчик изменения поля
         */
        onFieldChange: function (e) {
            const $field = $(e.currentTarget);
            const $calculator = $field.closest('.ag-product-calculator');

            clearTimeout($calculator.data('calcTimeout'));
            $calculator.data('calcTimeout', setTimeout(() => {
                this.calculatePrice($calculator);
            }, 300));
        },

        /**
         * Обработчик слайдера
         */
        onRangeInput: function (e) {
            const $range = $(e.currentTarget);
            const value = $range.val();
            $range.siblings('.ag-range-value').text(value);
        },

        /**
         * Обработчик переключения вкладки
         */
        onTabShown: function (e) {
            const $tab = $(e.target);
            const $pane = $($tab.attr('href'));
            const $calculator = $pane.find('.ag-product-calculator');

            if ($calculator.length && !$calculator.data('initialized')) {
                this.initCalculator($calculator);
                $calculator.data('initialized', true);
            }
        },

        /**
         * Сбор данных из формы
         */
        gatherFormData: function ($calculator) {
            const formData = {};

            $calculator.find('.ag-field').each(function () {
                const $field = $(this);
                const key = $field.data('field-key');

                if (!key) return;

                const type = $field.hasClass('ag-field-checkbox') ? 'checkbox' :
                    $field.hasClass('ag-field-radio') ? 'radio' :
                        $field.hasClass('ag-field-range') ? 'range' : 'default';

                let value;

                if (type === 'checkbox') {
                    // Отправляем 1 если отмечен, 0 если нет
                    value = $field.find('.ag-field-checkbox').is(':checked') ? 1 : 0;
                } else if (type === 'radio') {
                    // Для radio ищем выбранную кнопку внутри группы
                    value = $field.find('input[type="radio"]:checked').val();
                } else {
                    value = $field.find('input, select').val();
                }

                if (key && value !== undefined && value !== '') {
                    formData[key] = value;
                }
            });

            return formData;
        },

        /**
         * Расчет цены через REST API
         */
        calculatePrice: function ($calculator) {
            const productSlug = $calculator.data('product-slug');
            const fields = this.gatherFormData($calculator);

            if (!productSlug) return;

            // Показываем индикатор загрузки
            $calculator.find('.ag-loading-spinner').show();
            $calculator.find('.ag-error-message').hide();
            $calculator.find('.ag-order-btn').prop('disabled', true);

            $.ajax({
                url: ag_calc.api_root + '/calculate',
                method: 'POST',
                headers: { 'X-WP-Nonce': ag_calc.nonce },
                contentType: 'application/json',
                data: JSON.stringify({
                    product_slug: productSlug,
                    fields: fields
                }),
                success: (response) => {
                    $calculator.find('.ag-loading-spinner').hide();

                    if (response.success) {
                        const price = parseFloat(response.price).toFixed(2);
                        $calculator.find('.ag-total-price')
                            .text(price + ' ' + ag_calc.i18n.currency)
                            .data('price', price);
                        $calculator.find('.ag-order-btn').prop('disabled', false);
                    } else {
                        this.showError($calculator, response.error || ag_calc.i18n.error);
                    }
                },
                error: (xhr) => {
                    $calculator.find('.ag-loading-spinner').hide();
                    this.showError($calculator, ag_calc.i18n.error);
                    console.error('Calculation error:', xhr);
                }
            });
        },

        /**
         * Показ ошибки
         */
        showError: function ($calculator, message) {
            $calculator.find('.ag-error-message')
                .text(message)
                .show();
            $calculator.find('.ag-total-price')
                .text('—')
                .data('price', 0);
        },

        /**
         * Обработчик кнопки заказа
         */
        onOrderClick: function (e) {
            const $btn = $(e.currentTarget);
            const $calculator = $btn.closest('.ag-product-calculator');
            const price = $calculator.find('.ag-total-price').data('price');

            // Здесь можно добавить логику добавления в корзину
            // Или перенаправление на страницу оформления заказа
            alert(ag_calc.i18n.price_label + ' ' + price + ' ' + ag_calc.i18n.currency);

            // Пример: добавление в WooCommerce
            // this.addToCart($calculator, price);
        },

        /**
         * Пример: Добавление в корзину WooCommerce
         */
        addToCart: function ($calculator, price) {
            const productSlug = $calculator.data('product-slug');
            const fields = this.gatherFormData($calculator);

            $.ajax({
                url: '/wp-json/wc/store/v1/cart/add-item',
                method: 'POST',
                headers: { 'Nonce': ag_calc.nonce },
                data: {
                    id: 123, // ID товара в WooCommerce
                    quantity: fields.quantity || 1,
                    variation: fields
                },
                success: function (response) {
                    window.location.href = '/cart';
                }
            });
        }
    };

    // Запуск после загрузки DOM
    $(document).ready(function () {
        AGCalculator.init();
    });

})(jQuery);