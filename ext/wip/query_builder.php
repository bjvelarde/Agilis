<?php
/*
QB::select_from_tbl1('f1', 'f2', 'f3')
  ->and_from_tbl2('f4', 'f5')
  ->inner_join_tabl3_on_name_of_tbl1_equals_name_of_tbl3()
  ->where_age_of_tbl1_at_least(18)
  ->and_grade_of_tbl2_more_than(80)
  ->group_by_name_of_tbl1()
  ->asc_order_by_name_of_tbl2()
  ->execute();

QB::update_tbl1($sets)->where_id_equals(1)->execute();

QB::insert_into_tbl1($sets)->execute();

QB::delete_from_tbl1()->where_id_equals(33)->execute();*/

class QueryBuilder {

    const OPERATOR_PATTERN    = '(is|is_not|not_equals|equals|more_than|less_than|at_least|at_most|has|starts_with|ends_with|in|not_in|between)';
    const TABLE_FIELD_PATTERN = '([a-z0-9_]+)_of_([a-z0-9_]+)';

    private $action;
    private $sources;
    private $joins;
    private $fields;
    private $criteria;
    private $options;
    private $last_called;

    public function __construct($action, $source, $fields=array()) {
        $this->action      = $action;
        $this->sources[]   = $source;
        $this->joins       = array();
        $this->fields      = $fields;
        $this->last_called = $action;
    }

    public static function __callStatic($method, $args) {
        if (preg_match('/^(select_from|update|insert_into|delete_from)_([a-z0-9_]+)$/', $method, $matches)) {
            $fields = ($matches[1] == 'select_from') ? array("{$matches[2]}" => $args) : $args;
            return new self($matches[1], $matches[2], $fields);
        }
    }

    public function __get($var) {
        return (property_exists($this, $var)) ? $this->{$var} : NULL;
    }

    public function __call($method, $args) {
        $join_pattern = '/^(inner|cross|straight|((natural)_)?(left|right)(_outer)?)_join_([a-z0-9_]+)'
                      . '_on_' . self::TABLE_FIELD_PATTERN . '_' . self::OPERATOR_PATTERN . '_([a-z0-9_]+)_of_([a-z0-9_]+)$/';
        if (($this->last_called == 'join' || $this->last_called == 'select_from') && preg_match($join_pattern, $method, $matches)) {
            $join = array(
                'type'   => $matches[1],
                'table'  => $matches[6],
                'oper'   => $matches[9],
                'fields' => array(
                    "{$matches[8]}"  => $matches[7],
                    "{$matches[11]}" => $matches[10]
                );
            );
            $this->joins[] = $join;
            $this->last_called = 'join';
        } elseif ($this->last_called == 'select_from' && preg_match('/^and_from_([a-z0-9_]+)$/', $method, $matches)) {
            $this->fields["{$matches[1]}"] = $args;
            $this->sources[] = $matches[1];
        } elseif (preg_match('/^(where|and|or)_([a-z0-9_]+)_' . self::OPERATOR_PATTERN . '$/', $method, $matches)) {
            if (($matches[1] == 'where' && in_array($this->last_called, array('select_from', 'delete_from', 'update', 'join'))) ||
               ($matches[1] != 'where' && in_array($this->last_called, array('where', 'and_where', 'or_where')))) {
                if (preg_match('/^' . self::TABLE_FIELD_PATTERN . '$/', $matches[2], $matches1) || count($this->sources) == 1) {
                    $value = count($args) > 1 ? $args : $args[0];
                    if ($matches[1] != 'where') {
                        $this->criteria[] = $matches[1];
                    }
                    if ($matches1) {                     
                        $this->criteria[] = array("{$matches1[2]}.{$matches1[1]}", $matches[3], $value);
                    } else {
                        $this->criteria[] = array("{$this->sources[0]}.{$matches[2]}", $matches[3], $value);
                    }
                    $this->last_called = ($matches[1] == 'where') ? 'where' : "{$matches[1]}_where";
                }
            }
        } elseif (preg_match('/^group_by_([a-z0-9_]+)$/', $method, $matches)) {
            if ($this->last_called != 'group_by' && $this->last_called != 'limit' && $this->action == 'select_from' &&
               (preg_match('/^' . self::TABLE_FIELD_PATTERN . '$/', $matches[1], $matches1) ||
               (count($this->sources) == 1))) {
                if ($matches1) {
                    $this->options['group_by'] => array("{$matches1[2]}" => $matches1[1]);
                } else {
                    $this->options['group_by'] => array("{$this->sources[0]}" => $matches1[1]);
                }
                $this->last_called = 'group_by';
            }
        } elseif (preg_match('/^((asc|desc)_)?order_by_([a-z0-9_]+)$/', $method, $matches)) {
            if ($this->action == 'select_from' && $this->last_called != 'limit' &&
               (preg_match('/^' . self::TABLE_FIELD_PATTERN . '$/', $matches[3], $matches1) ||
               (count($this->sources) == 1))) {
                $order = ($matches[2] == '') ? 'ASC' : strtoupper($matches[2]);
                if ($matches1) {
                    $this->options['order_by']["{$matches1[2]}"][] => array("{$matches1[1]}" => $order);
                } else {
                    $this->options['order_by']["{$this->sources[0]}"][] => array("{$matches[3]}" => $order);
                }
                $this->last_called = 'order_by';
            }
        }
        return $this;
    }

    public function group_by($field) {
        if ($this->last_called != 'group_by' && $this->last_called != 'limit' && $this->action == 'select_from') {
            if (is_array($field)) {
                list($table, $field) = each($field);
                $this->options['group_by'] = array($table => $field);
            } elseif (count($this->sources) == 1) {
                $this->options['group_by'] = array("{$this->sources[0]}" => $field);
            }
            $this->last_called = 'group_by';
        }
        return $this;
    }

    public function order_by($field) {
        if ($this->action == 'select_from' && $this->last_called != 'limit') {
            if (is_array($field)) {
                list($table, $field) = each($field);
                $this->options['order_by'][$table][] = array($field => 'ASC');
            } elseif (count($this->sources) == 1) {
                $this->options['order_by']["{$this->sources[0]}"][] = array($field => 'ASC');
            }
            $this->last_called = 'order_by';
        }
        return $this;
    }

    public function asc_order_by($field) {
        return $this->order_by($field);
    }

    public function desc_order_by($field) {
        if ($this->action == 'select_from' && $this->last_called != 'limit') {
            if (is_array($field)) {
                list($table, $field) = each($field);
                $this->options['order_by'][$table][] = array($field => 'DESC');
            } elseif (count($this->sources) == 1) {
                $this->options['order_by']["{$this->sources[0]}"][] = array($field => 'DESC');
            }
            $this->last_called = 'order_by';
        }
        return $this;
    }

    public function limit() {
        if ($this->action != 'insert_into') {
            $args = func_get_args();
            if ($args) {
                if (count($args) > 1) {
                    $this->options['offset'] = array_shift($args);
                    $this->options['limit']  = array_shift($args);
                } else {
                    $this->options['limit']  = array_shift($args);
                }
            }
            $this->last_called = 'limit';
        }
        return $this;
    }
}
?>