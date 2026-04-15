<?php
/**
 * парсер и исполнитель математических формул
 */

if (!defined('ABSPATH'))
    exit;

class FK_Formula_Engine
{

    private static $instance = null;
    private $operators = [];

    private function __construct()
    {
        $this->operators = [
            '+' => __('Сложение', 'foto-kniga-calc'),
            '-' => __('Вычитание', 'foto-kniga-calc'),
            '*' => __('Умножение', 'foto-kniga-calc'),
            '/' => __('Деление', 'foto-kniga-calc'),
            '(' => __('Открыть скобку', 'foto-kniga-calc'),
            ')' => __('Закрыть скобку', 'foto-kniga-calc'),
            'pow' => __('Степень', 'foto-kniga-calc'),
            'min' => __('Минимум', 'foto-kniga-calc'),
            'max' => __('Максимум', 'foto-kniga-calc'),
            'round' => __('Округление', 'foto-kniga-calc'),
            'abs' => __('Модуль', 'foto-kniga-calc'),
            'ceil' => __('Округление вверх', 'foto-kniga-calc'),
            'floor' => __('Округление вниз', 'foto-kniga-calc'),
            'if' => __('Условие', 'foto-kniga-calc')
        ];
    }

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function get_allowed_operators()
    {
        return self::get_instance()->operators;
    }

    private static function replace_if_functions($expression)
    {
        $max_iterations = 50;
        $iteration = 0;

        while (strpos($expression, 'if') !== false && $iteration < $max_iterations) {
            $iteration++;
            $new_expression = self::replace_first_if($expression);

            if ($new_expression === $expression) {
                break;
            }

            $expression = $new_expression;
        }

        return $expression;
    }

    private static function replace_first_if($expression)
    {
        $pos = 0;
        while (($pos = strpos($expression, 'if', $pos)) !== false) {
            if ($pos > 0) {
                $prev_char = $expression[$pos - 1];
                if (preg_match('/[a-zA-Z_]/', $prev_char)) {
                    $pos++;
                    continue;
                }
            }

            $after_if = $pos + 2;
            $temp_pos = $after_if;

            while ($temp_pos < strlen($expression) && $expression[$temp_pos] === ' ') {
                $temp_pos++;
            }

            if ($temp_pos < strlen($expression) && $expression[$temp_pos] === '(') {
                $open_paren = $temp_pos;
                $close_paren = self::find_matching_paren($expression, $open_paren);

                if ($close_paren !== -1) {
                    $content = substr($expression, $open_paren + 1, $close_paren - $open_paren - 1);

                    if (self::has_nested_if($content)) {
                        $pos++;
                        continue;
                    }

                    $args = self::split_if_args($content);

                    if (count($args) === 3) {
                        $condition = trim($args[0]);
                        $if_true = trim($args[1]);
                        $if_false = trim($args[2]);

                        $condition = str_replace(
                            ['>=', '<=', '==', '!=', '&&', '||'],
                            ['>=', '<=', '===', '!==', '&&', '||'],
                            $condition
                        );

                        $replacement = '((' . $condition . ') ? (' . $if_true . ') : (' . $if_false . '))';

                        return substr($expression, 0, $pos) . $replacement . substr($expression, $close_paren + 1);
                    }
                }
            }

            $pos++;
        }

        return $expression;
    }

    private static function has_nested_if($content)
    {
        $pos = 0;
        while (($pos = strpos($content, 'if', $pos)) !== false) {
            if ($pos > 0) {
                $prev_char = $content[$pos - 1];
                if (preg_match('/[a-zA-Z_]/', $prev_char)) {
                    $pos++;
                    continue;
                }
            }

            $after_if = $pos + 2;
            $temp_pos = $after_if;
            while ($temp_pos < strlen($content) && $content[$temp_pos] === ' ') {
                $temp_pos++;
            }

            if ($temp_pos < strlen($content) && $content[$temp_pos] === '(') {
                return true;
            }

            $pos++;
        }

        return false;
    }

    private static function find_matching_paren($str, $open_pos)
    {
        if ($open_pos >= strlen($str) || $str[$open_pos] !== '(') {
            return -1;
        }

        $depth = 0;
        for ($i = $open_pos; $i < strlen($str); $i++) {
            if ($str[$i] === '(') {
                $depth++;
            } elseif ($str[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return -1;
    }

    private static function split_if_args($content)
    {
        $args = [];
        $current = '';
        $depth = 0;

        for ($i = 0; $i < strlen($content); $i++) {
            $char = $content[$i];

            if ($char === '(') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                $args[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $args[] = $current;
        }

        return $args;
    }

    /**
     * Расчет по формуле
     */
    public static function calculate($expression, $variables)
    {
        try {
            $expression = html_entity_decode($expression, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (empty(trim($expression))) {
                throw new Exception(__('Формула не может быть пустой', 'foto-kniga-calc'));
            }

            $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', $expression)));
            $custom_variables = [];
            $main_formula = '';

            foreach ($lines as $line) {
                if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+?)\s*;?\s*$/', $line, $matches)) {
                    $var_name = $matches[1];
                    $var_expression = trim(rtrim($matches[2], ';'));
                    $var_value = self::evaluate_single_expression($var_expression, array_merge($variables, $custom_variables));
                    $custom_variables[$var_name] = $var_value;
                } else {
                    $main_formula .= $line . ' ';
                }
            }

            $all_variables = array_merge($variables, $custom_variables);

            if (!empty($main_formula)) {
                $validation = self::validate_formula($main_formula, array_keys($all_variables));
                if (!$validation['valid']) {
                    throw new Exception(implode(', ', $validation['errors']));
                }
            } else {
                return [
                    'success' => true,
                    'price' => 0,
                    'expression' => ''
                ];
            }

            $parsed_expression = preg_replace_callback(
                '/\{\{([a-zA-Z0-9_]+)\}\}/',
                function ($matches) use ($all_variables) {
                    $var_name = $matches[1];
                    $value = isset($all_variables[$var_name]) ? floatval($all_variables[$var_name]) : 0;
                    return '(' . $value . ')';
                },
                $main_formula
            );

            $parsed_expression = self::replace_if_functions($parsed_expression);

            $parsed_expression = trim($parsed_expression);

            if (!self::is_safe_expression($parsed_expression)) {
                throw new Exception(__('Недопустимые символы в формуле', 'foto-kniga-calc') . ': ' . $parsed_expression);
            }

            $result = self::safe_eval($parsed_expression);

            return [
                'success' => true,
                'price' => round($result, 2),
                'expression' => $parsed_expression
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'price' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Вычисление одиночного выражения (для пользовательских переменных)
     */
    private static function evaluate_single_expression($expression, $variables)
    {
        $parsed = preg_replace_callback(
            '/\{\{([a-zA-Z0-9_]+)\}\}/',
            function ($matches) use ($variables) {
                $var_name = $matches[1];
                $value = isset($variables[$var_name]) ? floatval($variables[$var_name]) : 0;
                return '(' . $value . ')';
            },
            $expression
        );

        $parsed = self::replace_if_functions($parsed);

        $parsed = trim($parsed);

        if (!self::is_safe_expression($parsed)) {
            return 0;
        }

        $result = self::safe_eval($parsed);
        return is_numeric($result) ? $result : 0;
    }

    /**
     * Проверка безопасности выражения (после замены переменных)
     */
    private static function is_safe_expression($expression)
    {
        $dangerous = [
            'eval',
            'exec',
            'system',
            'passthru',
            'shell_exec',
            'include',
            'require',
            'include_once',
            'require_once',
            '$',
            '`',
            '"',
            "'",
            '\\'
        ];

        foreach ($dangerous as $danger) {
            if (stripos($expression, $danger) !== false) {
                error_log('FK Blocked dangerous: ' . $danger . ' in: ' . $expression);
                return false;
            }
        }

        if (!preg_match('/^[0-9a-zA-Z_\s+\-*\/().,<>!?=:&|;]+$/', $expression)) {
            error_log('FK Regex failed: ' . $expression);
            return false;
        }

        $open = substr_count($expression, '(');
        $close = substr_count($expression, ')');
        if ($open !== $close) {
            error_log('FK Bracket mismatch: ' . $expression);
            return false;
        }

        return true;
    }

    /**
     * Безопасное вычисление
     */
    private static function safe_eval($expression)
    {
        // Разрешаем только математические функции
        $allowed_functions = ['min', 'max', 'pow', 'round', 'abs', 'ceil', 'floor'];

        // Проверяем, что нет вызовов запрещенных функций
        if (preg_match('/\b(?!' . implode('|', $allowed_functions) . '\b)[a-z]+\s*\(/i', $expression)) {
            throw new Exception(__('Запрещенная функция в формуле', 'foto-kniga-calc'));
        }

        // Вычисляем
        $result = @eval ('return (' . $expression . ');');

        if ($result === false && !is_numeric($result)) {
            throw new Exception(__('Ошибка вычисления формулы', 'foto-kniga-calc'));
        }

        if (!is_numeric($result)) {
            throw new Exception(__('Результат не является числом', 'foto-kniga-calc'));
        }

        return $result;
    }

    /**
     * Валидация формулы
     */
    public static function validate_formula($expression, $available_variables)
    {
        $expression = html_entity_decode($expression, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $errors = [];

        if (empty(trim($expression))) {
            $errors[] = __('Формула не может быть пустой', 'foto-kniga-calc');
            return ['valid' => false, 'errors' => $errors];
        }

        $open_brackets = substr_count($expression, '(');
        $close_brackets = substr_count($expression, ')');
        if ($open_brackets !== $close_brackets) {
            $errors[] = __('Несбалансированные скобки', 'foto-kniga-calc');
        }

        // Проверка переменных {{var}}
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $expression, $matches);
        $used_variables = $matches[1] ?? [];

        foreach ($used_variables as $var) {
            $base_var = preg_replace('/_(value|price)$/', '', $var);
            $is_valid = in_array($var, $available_variables) || in_array($base_var, $available_variables);
        }

        if (preg_match('/[\'"\\\\$`]/', $expression)) {
            $errors[] = __('Запрещенные символы в формуле', 'foto-kniga-calc');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}