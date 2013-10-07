<?php
namespace Agilis;

class QueryWhere {
    
    private $criteria;
    private $alternative;
    private $children;
    private $table;
    private $options;
    
    public function __construct(Table &$table, array $criteria) {
        $this->criteria     = $criteria;
        $this->alternatives = array();
        $this->table       = $table;
        $this->children     = array();
        $this->options      = array(
            'offset'   => NULL,
            'limit'    => NULL,
            'group_by' => NULL,
            'order_by' => array()
        );
    } 

    public function __call($m, $a) {
        if ($m == 'or' || $m == 'and') {
            return call_user_func_array(array($this, "{$m}_where"), $a);
        }
    }
    
    public function __get($var) {
        return (property_exists($this, $var)) ? $this->{$var} : NULL;
    }
   
    public function &or_where(array $where, Table $table=NULL) {
        $table = $table ? $table : $this->table;
        $this->alternatives[] = new self($table, $where);
        return $this;
    }
    
    public function &and_where(array $where, Table $table=NULL) {
        $table = $table ? $table : $this->table;
        $this->children[] = new self($table, $where);
        return $this;
    }
    
    public function &order_by() {
        $args = func_get_args();
        if ($args) {
            foreach ($args as $arg) {
                if (is_array($arg) && CraftyArray::isAssoc($arg)) {
                    list($field, $order) = each($arg);
                    $order = (strtolower($order) == 'asc') ? 'asc' : 'desc';
                } elseif (is_string($arg)) {
                    $field = $arg;
                    $order = 'asc';
                }
                $this->options['order_by'][] = array($field => $order);
            }
        }
        return $this;
    }

    public function &group_by($field) {
        $this->options['group_by'] = $field;
        return $this;
    }    
    
    public function &limit() {
        $args = func_get_args();
        if ($args) {
            if (count($args) > 1) {
                $this->options['offset'] = array_shift($args);
                $this->options['limit']  = array_shift($args);
            } else {
                $this->options['limit']  = array_shift($args);
            }
        }    
        return $this;
    }

    public function find() {
        return $this->table->getActiveRecord()->tableWhere($this);
    }
}
?>