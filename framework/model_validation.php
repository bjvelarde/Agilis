<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

final class ModelValidation extends Singleton {

    public static function validates_presence_of() {
        $args = func_get_args();
        if (count($args) > 1 && $args[0] instanceof Table) {
            $table = array_shift($args);
            foreach ($args as $field) {
                if ($table->hasElement($field)) {
                    $table[$field] = $table[$field]->required();
                }
            }
        }
    }

    public static function validates_uniqueness_of() {
        $args = func_get_args();
        if (count($args) > 1 && $args[0] instanceof Table) {
            $table = array_shift($args);
            if (count($args) > 1) {
                $table->addUniqueKey($args[0], $args);
            } else {
                $field = $args[0];
                $table[$field] = $table[$field]->unique();
            }
        }
    }

    public static function validates_maxvalue_of(Table &$table, $field, $value) {
        if ($table->hasElement($field)) {
            $table[$field] = $table[$field]->maxvalue($value);
        }
    }

    public static function validates_minvalue_of(Table &$table, $field, $value) {
        if ($table->hasElement($field)) {
            $table[$field] = $table[$field]->minvalue($value);
        }
    }

    public static function validates_range_of(Table &$table, $field, $min, $max) {
        if ($table->hasElement($field)) {
            $table[$field] = $table[$field]->maxvalue($max);
            $table[$field] = $table[$field]->minvalue($min);
        }
    }

    public static function validates_maxlen_of(Table &$table, $field, $value) {
        if ($table->hasElement($field)) {
            $table[$field] = $table[$field]->maxlen($value);
        }
    }

    public static function validates_minlen_of(Table &$table, $field, $value) {
        if ($table->hasElement($field)) {
            $table[$field] = $table[$field]->minlen($value);
        }
    }

    public static function validates_length_of(Table &$table, $field, $value) {
        if ($table->hasElement($field)) {
            $table[$field] = $table[$field]->maxlen($value);
            $table[$field] = $table[$field]->minlen($value);
        }
    }

    public static function validates_pattern_of(Table &$table, $field, $pattern) {
        if ($table->hasElement($field)) {
            $table[$field] = $table[$field]->pattern($pattern);
        }
    }

    public static function validates_accepted_values_of(Table &$table, $field, array $values) {
        if ($table->hasElement($field)) {
            $table[$field] = $table[$field]->enum($values);
        }
    }

    public static function validates_set_values_of(Table &$table, $field, array $values) {
        if ($table->hasElement($field)) {
            $table[$field] = $table[$field]->set($values);
        }
    }

    public static function validates_accepted_mimes_of(Table &$table, $field, array $mimes) {
        if ($table->hasElement($field)) {
            $table[$field] = $table[$field]->mimes($mimes);
        }
    }

    public static function defineValidation(Table &$table, $method, $args) {
        $m = new String($method);
        if ($m->startsWith('validates_')) {
            if (($start = $m->substr(10)->startsWith(
                'uniqueness_of_',
                'presence_of_',
                'maxvalue_of_',
                'minvalue_of_',
                'maxlen_of_',
                'minlen_of_',
                'length_of_',
                'range_of_'
            )) !== FALSE) {
                $field = $m->substr(10 + strlen($start))->to_s;
                if ($table->hasElement($field)) {
                    $what  = substr($start, 0, -4);
                    switch ($what) {
                        case 'uniqueness':
                            if (strpos($field, '_and_') > 0) {
                                $table->addUniqueKey($field, explode('_and_', $field));
                            } else {
                                $table[$field] = $table[$field]->unique();
                            }
                            break;
                        case 'presence':
                            $table[$field] = $table[$field]->required();
                            break;
                        case 'maxvalue':
                            $table[$field] = $table[$field]->maxvalue($args[0]);
                            break;
                        case 'minvalue':
                            $table[$field] = $table[$field]->minvalue($args[0]);
                            break;
                        case 'maxlen':
                            $table[$field] = $table[$field]->maxlen($args[0]);
                            break;
                        case 'minlen':
                            $table[$field] = $table[$field]->minlen($args[0]);
                            break;
                        case 'length':
                            $table[$field] = $table[$field]->maxlen($args[0]);
                            $table[$field] = $table[$field]->minlen($args[0]);
                            break;
                        case 'range':
                            $table[$field] = $table[$field]->minvalue($args[0]);
                            $table[$field] = $table[$field]->maxvalue($args[1]);
                            break;
                        default:
                            return FALSE;
                    }
                    return TRUE;
                }
            } elseif ($args) {
                if (preg_match('/^validates_([a-z0-9]+)_with$/', $method, $matches)) {
                    $field = array_pop($matches);
                    if ($table->hasElement($field)) {
                        $table[$field] = $table[$field]->pattern($args[0]);
                    } else {
                        return FALSE;
                    }
                } elseif (preg_match('/^validates_accepted_values_of_([a-z0-9]+)_with$/', $method, $matches)) {
                    $field = array_pop($matches);
                    if ($table->hasElement($field)) {
                        $table[$field] = $table[$field]->enum($args);
                    } else {
                        return FALSE;
                    }
                } elseif (preg_match('/^validates_set_values_of_([a-z0-9]+)_with$/', $method, $matches)) {
                    $field = array_pop($matches);
                    if ($table->hasElement($field)) {
                        $table[$field] = $table[$field]->set($args);
                    } else {
                        return FALSE;
                    }
                } elseif (preg_match('/^validates_accepted_mimes_of_([a-z0-9]+)_with$/', $method, $matches)) {
                    $field = array_pop($matches);
                    if ($table->hasElement($field)) {
                        $table[$field] = $table[$field]->mimes($args);
                    } else {
                        return FALSE;
                    }
                } else {
                    return FALSE;
                }
                return TRUE;
            }
        }
        return FALSE;
    }
}
?>