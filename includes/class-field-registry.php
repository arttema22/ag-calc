<?php
/**
 * Реестр фиксированных типов полей
 */

if (!defined('ABSPATH')) exit;

class FK_Field_Registry {
    
    private static $instance = null;
    private $fields = [];
    
    private function __construct() {
        $this->register_default_fields();
    }
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function register_default_fields() {
        $this->fields = [
            'number' => [
                'label' => __('Число', 'foto-kniga-calc'),
                'icon' => 'dashicons-editor-ol',
                'supports' => ['min', 'max', 'step', 'default', 'suffix'],
                'affects_price' => true
            ],
            'range' => [
                'label' => __('Слайдер', 'foto-kniga-calc'),
                'icon' => 'dashicons-slider',
                'supports' => ['min', 'max', 'step', 'default'],
                'affects_price' => true
            ],
            'select' => [
                'label' => __('Выпадающий список', 'foto-kniga-calc'),
                'icon' => 'dashicons-arrow-down-alt2',
                'supports' => ['options', 'pricing_table', 'default'],
                'affects_price' => true
            ],
            'radio' => [
                'label' => __('Радио-кнопки', 'foto-kniga-calc'),
                'icon' => 'dashicons-controls',
                'supports' => ['options', 'pricing_table', 'default'],
                'affects_price' => true
            ],
            'checkbox' => [
                'label' => __('Чекбокс', 'foto-kniga-calc'),
                'icon' => 'dashicons-yes-alt',
                'supports' => ['label_on', 'label_off', 'price_if_checked'],
                'affects_price' => true
            ],
            'text' => [
                'label' => __('Текст', 'foto-kniga-calc'),
                'icon' => 'dashicons-editor-paragraph',
                'supports' => ['placeholder', 'default'],
                'affects_price' => false
            ],
            'hidden_number' => [
                'label' => __('Скрытое число', 'foto-kniga-calc'),
                'icon' => 'dashicons-hidden',
                'supports' => ['value'],
                'affects_price' => true,
                'is_admin_only' => true
            ]
        ];
    }
    
    public static function get_all_fields() {
        return self::get_instance()->fields;
    }
    
    public static function get_field($key) {
        $fields = self::get_all_fields();
        return $fields[$key] ?? null;
    }
    
    public static function field_exists($key) {
        $fields = self::get_all_fields();
        return isset($fields[$key]);
    }
}