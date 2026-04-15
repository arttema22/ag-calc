/**
 * Frontend Calculator - REST API Integration
 */

(function ($) {
    'use strict';

    const FKCalculator = {

        init: function () {
            this.bindEvents();
            this.initAllCalculators();
        },

        /**
         * Привязка событий
         */
        bindEvents: function () {
            // Изменение любого поля
            $(document).on('change input', '.fk-field-input, .fk-field-select, .fk-field-radio-input, .fk-field-checkbox, .fk-field-range',
                this.onFieldChange.bind(this));

            // Переключение вкладок
            $(document).on('shown.bs.tab', '.fk-tabs-nav a[data-toggle="tab"]',
                this.onTabShown.bind(this));

            // Обновление значения слайдера
            $(document).on('input', '.fk-field-range', this.onRangeInput.bind(this));

            // Кнопка заказа
            $(document).on('click', '.fk-order-btn', this.onOrderClick.bind(this));
        },

        /**
         * Инициализация всех калькуляторов на странице
         */
        initAllCalculators: function () {
            $('.fk-product-calculator').each(function () {
                FKCalculator.initCalculator($(this));
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
                url: fk_calc.api_root + '/products/' + productSlug,
                method: 'GET',
                headers: { 'X-WP-Nonce': fk_calc.nonce },
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
                    const $range = $calculator.find(`[data-field-key="${key}"].fk-field-range`);
                    const $value = $range.siblings('.fk-range-value');
                    $range.val(config.default);
                    $value.text(config.default);
                }

                if (type === 'select' && config.options && config.options.length > 0) {
                    const $field = $calculator.find(`[data-field-key="${key}"].fk-field-select`);
                    if ($field.length) {
                        $field.val(config.options[0].value);
                    }
                }

                if (type === 'radio' && config.options && config.options.length > 0) {
                    const $firstRadio = $calculator.find(`[data-field-key="${key}"].fk-field-radio-input`).first();
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
            const $calculator = $field.closest('.fk-product-calculator');

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
            $range.siblings('.fk-range-value').text(value);
        },

        /**
         * Обработчик переключения вкладки
         */
        onTabShown: function (e) {
            const $tab = $(e.target);
            const $pane = $($tab.attr('href'));
            const $calculator = $pane.find('.fk-product-calculator');

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

            $calculator.find('.fk-field').each(function () {
                const $field = $(this);
                const key = $field.data('field-key');

                if (!key) return;

                const type = $field.hasClass('fk-field-checkbox') ? 'checkbox' :
                    $field.hasClass('fk-field-radio') ? 'radio' :
                        $field.hasClass('fk-field-range') ? 'range' : 'default';

                let value;

                if (type === 'checkbox') {
                    // Отправляем 1 если отмечен, 0 если нет
                    value = $field.find('.fk-field-checkbox').is(':checked') ? 1 : 0;
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
            $calculator.find('.fk-loading-spinner').show();
            $calculator.find('.fk-error-message').hide();
            $calculator.find('.fk-order-btn').prop('disabled', true);

            $.ajax({
                url: fk_calc.api_root + '/calculate',
                method: 'POST',
                headers: { 'X-WP-Nonce': fk_calc.nonce },
                contentType: 'application/json',
                data: JSON.stringify({
                    product_slug: productSlug,
                    fields: fields
                }),
                success: (response) => {
                    $calculator.find('.fk-loading-spinner').hide();

                    if (response.success) {
                        const price = parseFloat(response.price).toFixed(2);
                        $calculator.find('.fk-total-price')
                            .text(price + ' ' + fk_calc.i18n.currency)
                            .data('price', price);
                        $calculator.find('.fk-order-btn').prop('disabled', false);
                    } else {
                        this.showError($calculator, response.error || fk_calc.i18n.error);
                    }
                },
                error: (xhr) => {
                    $calculator.find('.fk-loading-spinner').hide();
                    this.showError($calculator, fk_calc.i18n.error);
                    console.error('Calculation error:', xhr);
                }
            });
        },

        /**
         * Показ ошибки
         */
        showError: function ($calculator, message) {
            $calculator.find('.fk-error-message')
                .text(message)
                .show();
            $calculator.find('.fk-total-price')
                .text('—')
                .data('price', 0);
        },

        /**
         * Обработчик кнопки заказа
         */
        onOrderClick: function (e) {
            const $btn = $(e.currentTarget);
            const $calculator = $btn.closest('.fk-product-calculator');
            const price = $calculator.find('.fk-total-price').data('price');

            // Здесь можно добавить логику добавления в корзину
            // Или перенаправление на страницу оформления заказа
            alert(fk_calc.i18n.price_label + ' ' + price + ' ' + fk_calc.i18n.currency);

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
                headers: { 'Nonce': fk_calc.nonce },
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
        FKCalculator.init();
    });

})(jQuery);