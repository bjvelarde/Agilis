<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;
/* ---  prepareJoin Guide
$tables = array('a' => 't1', 'b' => 't2', 'c' => 't3')
$joins = array(
    'b' => array('left' => array('a' => 'f1', 'b' => 'f2'),),
    'c' => array('inner' => array('b' => 'f1', 'c' => 'f2'))
)
$wheres = array(
    array('a' => array('f1' => 'v1'))
)
$fields = array(
    'a' => array('f1', 'f2', 'f3'),
    'b' => array('f1', 'f2', 'f3')
)
*/
abstract class QueryGenerator extends Singleton {

    public function delete(Model $model) {
        $table  = $model::getTable();
        $where   = array();
        $elements = $model->getElements();
        foreach ($table->_primary_keys as $key) {
            $w = array($key => $model[$key]);
            $where[] = self::where($table, $w);
        }
        return "DELETE FROM $table->_name WHERE " . implode(' AND ', $where);
    }

    public function deleteMany(Table $table, &$bind_args, $criteria=array()) {
        if (self::isPartialSQL($criteria)) {
            $where = $criteria;
        } else {
            $where = array();
            foreach ($criteria as $key => $val) {
                if ($key == '__PARTIAL_SQL__' && self::isPartialSQL($val)) {
                    $where[] = $val;
                } else {
                    $where[] = $this->parseWhere($table, array($key => $val), $bind_args, $types);
                }
            }
            $where = implode(' AND ', $where);
            $bind_args = $bind_args ? array_merge((array)$types, $bind_args) : $bind_args;
        }
        return "DELETE FROM $table->_name" . ($where ? " WHERE $where" : '');
    }

    public function dump(Model $model) {
        if ($model->_persisted) {
            $data   = $model->getElements();
            $table = $model::getTable();
            $cols   = $vals = array();
            foreach ($data as $k => $v) {
                $field = $table[$k];
                $cols[] = $k;
                $vals[] = "'" . $table->_db->escape($v) . "'";
            }
            return "INSERT INTO {$table->_name} (" . implode(', ', $cols) . ") VALUES(" . implode(', ', $vals) . ");\n";
        } else {
            return self::insert($model) . ";\n";
        }
    }

    public function insert(Model $model) {
        $data   = $model->getElements();
        $table = $model::getTable();
        $cols   = $vals = array();
        foreach ($data as $k => $v) {
            $field = $table[$k];
            if ($field->is_id || $field->is_timestamp) {
                continue;
            }
            $cols[] = $k;
            if ($field->is_createstamp) {
                $vals[] = self::currentDateTime();
            } else {
                $v = self::formatValue($table[$k], $v);
                $vals[] = ($v != 'NULL') ? "'" . $table->_db->escape($v) . "'" : $v;
            }
        }
        return "INSERT INTO {$model->_table} (" . implode(', ', $cols) . ") VALUES(" . implode(', ', $vals) . ")";
    }

    public function prepareDelete(Model $model, &$bind_args) {
        $table = $model::getTable();
        $where  = $where_keys = array();
        $elements = $model->getElements();
        foreach ($table->_primary_keys as $key) {
            $w = array($key => $elements[$key]);
            $where[] = $this->parseWhere($table, $w, $bind_args, $types);
        }
        $bind_args = $bind_args ? array_merge((array)$types, $bind_args) : $bind_args;
        return "DELETE FROM $table->_name WHERE " . implode(' AND ', $where);
    }

    public function prepareFind(Table $table, &$bind_args, $what=array(), $options=array()) {    
        $id_key = $table->_id_key;
        if ($what && !is_array($what) && !self::isPartialSQL($what)) {
            if ($id_key) {
                $what = array($id_key => $what);
                $options['limit'] = 1;
            }
        }
        $options['order_by'] = isset($options['order_by']) ? $options['order_by'] : (!empty($id_key) ? array($id_key => 'asc') : NULL);
        $where = $invalid = array();
        if (self::isPartialSQL($what)) {
            $where = $what;
        } elseif (is_array($what) && CraftyArray::isAssoc($what)) {
            foreach ($what as $key => $val) {
                if ($key == '__PARTIAL_SQL__' && self::isPartialSQL($val)) {
                    $where[] = $val;
                } else {
                    $where[] = $this->parseWhere($table, array($key => $val), $bind_args, $types);
                }
            }
            $where = implode(" AND ", $where);
            $bind_args = $bind_args ? array_merge((array)$types, $bind_args) : $bind_args;
        }
        $sql  = "SELECT * FROM $table->_name";
        $sql .= $where ? " WHERE $where" : '';
        $sql .= self::options($table->_name, $options);
        //Common::devLog($sql, $bind_args);
        return $sql;
    }

    public function prepareInsert(Model $model, &$bind_args) {
        $cols   = $vals = array();
        $data   = $model->getElements();
        $table = $model::getTable();
        if ($table->_createstamp_key) {
            $data[$table->_createstamp_key] = date('Y-m-d H:i:s');
        }
        foreach ($model->_fields as $key) {
            $field = $table[$key];
            if (!$field->is_auto_increment && !$field->is_timestamp) {
                $value      = isset($data[$key]) ? $data[$key] : NULL;
                if ($table[$key]->encrypt_with) {
                    $encryptor = $table[$key]->encrypt_with;
                    $value = $encryptor($value);                    
                }
                $data[$key] = self::formatValue($table[$key], $value);
                $cols[]     = $key;
                if ($data[$key] != 'NULL') {
                    $bind_args[] = $data[$key];
                    $vals[] = $this->placeholder($bind_args);
                    $this->getBindType($table[$key], $types);
                } else {
                    $vals[] = 'NULL';
                }
            }
        }
        $bind_args = $bind_args ? array_merge((array)$types, $bind_args) : $bind_args;
        return "INSERT INTO {$model->_table} (" . implode(', ', $cols) . ") VALUES(" . implode(', ', $vals) . ")";
    }

    public function prepareJoin(array $tables, array $joins, &$bind_args, $wheres=array(), $options=array()) {
        list($alias1, $table1) = each($tables);
        if (isset($options['result_table'])) {
            $revlookup = array();
            foreach ($tables as $a => $t) {
                $revlookup["$t"] = $a;
            }
            $result_alias = $revlookup[$options['result_table']];
        } else {
            $result_alias = $alias1;
        }
        $from = $where = array();
        foreach ($joins as $a => $join) {
            list($type, $on) = each($join);
            list($a1, $f1)   = each($on);
            list($a2, $f2)   = each($on);
            $t      = ($tables[$a] instanceof Table) ? $tables[$a]->_name : $tables[$a];
            $from[] = "$type JOIN $t $a ON {$a1}.{$f1} = {$a2}.{$f2}";
        }
        $table1 = ($table1 instanceof Table) ? $table1->_name : $table1;
        $from  = "$table1 $alias1 " . implode(' ', $from);
        if ($wheres) {
            if (self::isPartialSQL($wheres)) {
                $where = $wheres;
            } else {
                foreach ($wheres as $a => $w) {
                    if ($a == '__PARTIAL_SQL__' && self::isPartialSQL($w)) {
                        $where[] = $w;
                    } else {
                        $where[] = $this->parseWhere($tables[$a], $w, $bind_args, $types, $a);
                    }
                }
                $where = implode(" AND ", $where);
            }
        }
        $bind_args = $bind_args ? array_merge((array)$types, $bind_args) : $bind_args;
        $sql  = "SELECT {$result_alias}.* FROM $from";
        $sql .= $where ? " WHERE $where" : '';
        //$sql .= self::options($alias1, $options);
        $sql .= self::options($tables, $options);
        //Common::devLog($sql, $bind_args);
        return $sql;
    }

    public function prepareModel(Model $model, &$bind_args) {
        $method = $model->_persisted ? 'prepareUpdate' : 'prepareInsert';
        return self::$method($model, $bind_args);
    }

    public function prepareQueryWhere(QueryWhere $where, &$bind_args) {
        $parts = array();
        if ($where->alternatives || $where->children) { $parts[] = '('; }
        foreach ($this->criteria as $k => $v) {
            $parts[] = $this->parseWhere($where->table, array($k => $v), $bind_args, $types, $where->table->_name);
        }
        if ($where->alternatives || $where->children) { $parts[] = ')'; }
        $str = implode(' AND ', $parts);
        $or_where = array();
        if ($where->alternatives) {
            foreach ($where->alternatives as $alternative) {
                $or_where[] = $this->prepareQueryWhere($alternative, $bind_args);
            }
            $str .= 'OR (' . implode(' OR ', $or_where) . ')';
        }
        $and_where = array();
        if ($where->children) {
            foreach ($where->children as $child) {
                $and_where[] = $this->prepareQueryWhere($child, $bind_args);
            }
            $str .= 'AND (' . implode(' AND ', $and_where) . ')';
        }
        if ($where->options) {
            $str .= self::options($where->table->_name, $where->options);
        }
        return $str;
    }

    public function prepareTableWhere(QueryWhere $where, &$bind_args) {
        $sql = 'SELECT * FROM {$where->table->_name} WHERE ' . $this->prepareQueryWhere($where, $bind_args);
        //Common::devLog($sql, $bind_args);
        return $sql;
    }

    public function prepareUpdate(Model $model, &$bind_args) {
        $set = $where = $set_keys = $where_keys = array();
        $params = $model->getElements();
        $table = $model::getTable();
        $this->prepareUpdatePairs($table, $params, $set, $where, $set_keys, $where_keys);
        foreach ($set_keys as $key) {
            $params[$key] = isset($params[$key]) ? $params[$key] : '';
            $v = self::formatValue($table[$key], $params[$key]);
            if ($v != 'NULL') {
                $bind_args[] = $v;
                $this->getBindType($table[$key], $types);
            }
        }
        foreach ($where_keys as $key) {
            $params[$key] = isset($params[$key]) ? $params[$key] : '';
            $v = self::formatValue($table[$key], $params[$key]);
            if ($v != 'NULL') {
                $bind_args[] = $v;
                $this->getBindType($table[$key], $types);
            }
        }
        $bind_args = $bind_args ? array_merge((array)$types, $bind_args) : $bind_args;
        return "UPDATE $table->_name SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where);
    }

    public function prepareTotal(Table $table, &$bind_args, $where=array()) {
        $sql = 'SELECT COUNT(*) FROM ' . $table->_name;
        if (self::isPartialSQL($where)) {
            $sql .= " WHERE $where";
        } else {
            $w = array();
		    if ($where) {
                foreach ($where as $k => $v) {
                    if ($k == '__PARTIAL_SQL__' && self::isPatialSQL($v)) {
                        $w[] = $v;
                    } else {
                        $w[] = $this->parseWhere($table, array($k => $v), $bind_args, $types);
                    }
                }
		        $sql .= ' WHERE ' . implode(' AND ', $w);
		    }
            $bind_args = $bind_args ? array_merge((array)$types, $bind_args) : $bind_args;
        }
        //Common::devLog($sql, $bind_args);
		return $sql;
    }

    public function removeColumn(Table $table, $field) {
        return 'ALTER TABLE ' . $table->_name . ' DROP COLUMN ' . $field;
    }

    public function select(Table $table, &$bind_args, $where=array(), $options=array(), $fields=array()) {
        $fields = $fields ? implode(', ', $fields) : '*';
        $sql   = "SELECT $fields FROM $table->_name";
        if (!self::isPartialSQL($where)) {
            if (is_array($where)) {
                if (CraftyArray::isAssoc($where)) {
                    $w = array();
                    foreach ($where as $key => $val) {
                        if ($key == '__PARTIAL_SQL__' && self::isPartialSQL($val)) {
                            $w[] = $val;
                        } else {
                            $w[] = $this->parseWhere($table, array($key => $val), $bind_args, $types);
                        }
                    }
                    $where = implode(" AND ", $w);
                } else {
                    $where = implode(" AND ", $where);
                }
            }
        }
        $bind_args = $bind_args ? array_merge((array)$types, $bind_args) : $bind_args;
        $sql .= $where ? " WHERE $where" : '';
        $sql .= self::options($table, $options);
        //Common::devLog($sql, $bind_args);
        return $sql;
    }

    public function update(Model $model) {
        $data   = $model->getElements();
        $table = $model::getTable();
        $set    = $where = array();
        foreach ($data as $k => $v) {
            $field = $table[$k];
            if ($field->is_timestamp) {
                continue;
            } else {
                if ($field->is_id || $field->is_primary_key) {
                    $w = array($k => $v);
                    $where[] = self::where($table, $w);
                } else {
                    $v = self::formatValue($field, $v);
                    if (preg_match('/^(\+\+|--|((\+|-)=)\s*(\d+))$/', $v, $matches)) {
                        if (isset($matches[4])) {
                            $oper = ($matches[2] == '-=' ? '-' : '+');
                            $set[] = "$key = $key $oper " . $matches[4];
                        } else {
                            $oper = ($matches[2] == '--' ? '-' : '+');
                            $set[] = "$key = $key $oper 1";
                        }
                    } else {
                        $set[] = "$k = " . (($v != 'NULL') ? "'" . $table->_db->escape($v) . "'" : 'NULL');
                    }
                }
            }
        }
        return "UPDATE $table->_name SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where);
    }

    public function updateMany(Table $table, &$bind_args, array $pairs=array(), $criteria=array()) {
        $set = $set_keys = $where_keys = array();
        $this->prepareUpdatePairs($table, $pairs, $set, $where, $set_keys, $where_keys);
        $where = array(); // erase what's been done by prepareUpdatePairs
        foreach ($set_keys as $key) {
            $pairs[$key] = isset($pairs[$key]) ? $pairs[$key] : '';
            $v = self::formatValue($table[$key], $pairs[$key]);
            if ($v != 'NULL') {
                $bind_args[] = $v;
                $this->getBindType($table[$key], $types);
            }
        }
        //foreach ($where_keys as $key) {
        //    $pairs[$key] = isset($pairs[$key]) ? $pairs[$key] : '';
        //    $v = self::formatValue($table[$key], $pairs[$key]);
        //    if ($v != 'NULL') {
        //        $bind_args[] = $v;
        //        $this->getBindType($table[$key], $types);
        //    }
        //}
        if (self::isPartialSQL($criteria)) {
            $where = $criteria;
        } else {
            if ($criteria) {
                foreach ($criteria as $key => $val) {
                    if ($key == '__PARTIAL_SQL__' && self::isPatialSQL($val)) {
                        $where[] = $val;
                    } else {
                        $where[] = $this->parseWhere($table, array($key => $val), $bind_args, $types);
                    }
                }
                $where = implode(' AND ', $where);
            }
        }
        $bind_args = $bind_args ? array_merge((array)$types, $bind_args) : $bind_args;
        $sql  = "UPDATE $table->_name SET " . implode(', ', $set);
        $sql .= $where ? " WHERE $where" : '';
        return $sql;
    }

    protected function currentDateTime() { return 'NOW()'; }

    protected function formatValue(TableField $field, $value) {
        if (is_array($value)) {
            for ($i = 0; $i < count($value); $i++) {
                $value[$i] = $this->formatValue($field, $value[$i]);
            }
            array_walk($value, array('CraftyArray', 'quote'));
            return $value;
            //$value = implode(',', $value);
        } else {
            if ($field->is_datetime) {
                $value = !empty($value) ? date('Y-m-d H:i:s', strtotime($value)) : 'NULL';
            } elseif ($field->is_date) {
                $value = !empty($value) ? date('Y-m-d', strtotime($value)) : 'NULL';
            } elseif ($field->is_time) {
                $value = !empty($value) ? date('H:i:s', strtotime($value)) : 'NULL';
            }
            //if ($value && $field->encrypt_with) {
            //    $encryptor = $field->encrypt_with;
            //    $value = $encryptor($value);
            //}
            return (!$value && $field->is_required) ?
                   (($field->default === NULL) ? 'NULL' : $field->default) :
                   ($value === NULL ? 'NULL' : $value);
        }
    }

    protected function getBindType($table, &$types) { $types = NULL; }

    protected static function operator($oper, &$placeholder, $value=NULL) {
        $placeholder = '?';
        if (isset($value) && is_array($value)) {
            switch ($oper) {
                case '=':
                    return 'IN';
                case '!=':
                case '<>':
                    return 'NOT IN';
            }
        } elseif (is_null($value)) {
            switch ($oper) {
                case '=':
                    return 'IS';
                case '!=':
                case '<>':
                    return 'IS NOT';
            }
        }
        if (stristr($oper, 'like')) {
            switch ($oper) {
                case '%like': $placeholder = '%?';  break;
                case 'like%': $placeholder = '?%';  break;
                default     : $placeholder = '%?%'; break;
            }
            return 'LIKE';
        }
        return strtoupper($oper);
    }

    protected static function options($table, $options) {        
        $sql = '';
        if (is_array($table)) {            
            $revlookup = array();
            foreach ($table as $a => $t) {
                $revlookup["$t"] = $a;
            }
            list($alias1, $table1) = each($table);
            if ($options) {
                if (!empty($options['group_by'])) {
                    // 'group_by' => array('table1' => 'field1')
                    if (is_array($options['group_by'])) {
                        list($t, $gb) = each($table);
                        $sql .= ' GROUP BY ' . static::tick($revlookup[$t]) . '.' . static::tick($gb);
                    } else {
                        $sql .= ' GROUP BY  ' . static::tick($alias1) . '.' . static::tick($group_by);
                    }
                }
                if (!empty($options['order_by'])) {
                    if (is_string($options['order_by']) && strtoupper($options['order_by']) == 'RANDOM') {
                        $sql .= ' ORDER BY ' . static::funcRandom();
                    } else {
                        $options['order_by'] = is_array($options['order_by']) ? $options['order_by'] : array($options['order_by'] => 'asc');
                        $order_by_arr = array();
                        foreach ($options['order_by'] as $order_by => $order) {
                            if (is_array($order)) {
                                //var_dump($revlookup, $order_by, $order);
                                // order_by is assumed to be a table name e.g. 'table1' => array('field1' => 'asc')
                                $order_by = String::tableize($order_by)->to_s;
                                list($f, $o) = each($order);
                                $order_by_arr[] = static::tick($revlookup[$order_by]) . '.' . static::tick($f) . ' ' . strtoupper($o);
                            } else {
                                $order_by_arr[] = static::tick($alias1) . '.' . static::tick($order_by) . ' ' . strtoupper($order);
                            }
                        }
                        $sql .= ' ORDER BY ' . implode(', ', $order_by_arr);
                    }    
                }
            }
        } else {
            $table = ($table instanceof Table) ? $table->_name : $table;            
            if ($options) {
                if (!empty($options['group_by'])) {
                    $sql .= ' GROUP BY ' . static::tick($table) . '.' . static::tick($group_by);
                }
                if (!empty($options['order_by'])) {
                    if (is_string($options['order_by']) && strtoupper($options['order_by']) == 'RANDOM') {
                        $sql .= ' ORDER BY ' . static::funcRandom();
                    } else {
                        $order_by_arr = array();
                        foreach ($options['order_by'] as $order_by => $order) {
                            $order_by_arr[] = static::tick($table) . '.' . static::tick($order_by) . ' ' . strtoupper($order);
                        }
                        $sql .= ' ORDER BY ' . implode(', ', $order_by_arr);
                    }    
                }
            }
        }
        if (!empty($options['limit'])) {
            $sql .= ' LIMIT ' . (isset($options['offset']) ? "{$options['offset']}, {$options['limit']}" : $options['limit']);
        }
        return $sql;
    }

    /* array('f1' => array('between', array(36, 48))) */
    protected function parseWhere(Table $table, array $where, &$bind_args, &$types, $table_alias='') {
        $pattern = "/^(>|<|!=|>=|<=|<>|=|like|like%|%like|between)$/i";
        list($lhs, $v) = each($where);
        if (is_array($v) && preg_match($pattern, $v[0], $matches)) {
            $oper = self::operator($v[0], $placeholder, $v[1]);
            $rhs  = $v[1];
        } else {
            $oper = self::operator('=', $placeholder, $v);
            $rhs  = $v;
        }
        $lhs_as_is = FALSE;
        if ($table->hasElement($lhs)) {
            if ($table[$lhs]->encrypt_with) {
                $encryptor = $table[$lhs]->encrypt_with;
                $rhs = $encryptor($rhs);                    
            }
            $rhs = self::formatValue($table[$lhs], $rhs);
        } elseif ($table->hasElement("{$lhs}_id")) {
            $model_name = $table->_model;
            $assoc_class = $model_name::getAssociateClass($lhs);
            if ($rhs instanceof $assoc_class) {
                $lhs = "{$lhs}_id";
                $rhs = $rhs->getId();
            }
        } else {
            $lhs_as_is = TRUE;
        }
        $str        = ($table_alias && !$lhs_as_is) ? "{$table_alias}.{$lhs}" : $lhs;
        $applied_to = 'both';
        $function   = NULL;
        if (is_array($v) && isset($v['treatment'])) {            
            $function   = $v['treatment'];
            if (is_array($v['treatment'])) { 
                $function   = isset($v['treatment']['function']) ? $v['treatment']['function'] : NULL;            
                $applied_to = isset($v['treatment']['applied_to']) && in_array($v['treatment']['applied_to'], array('left', 'right', 'both')) ? 
                              $v['treatment']['applied_to'] : 'both';
            }
            if ($function) {
                $function = static::getFunction($function);
                if ($applied_to != 'right' && !$lhs_as_is) {
                    $str = $table_alias ? "{$function}({$table_alias}.{$lhs})" : "{$function}({$lhs})";
                }    
            }
        }
        if (is_null($rhs) || $rhs === 'NULL') {
            $str .= " $oper NULL";
        } else {
            switch ($oper) {
                case 'IN'    :
                case 'NOT IN':
                    $rhs = is_array($rhs) ? $rhs : array($rhs);
                    $str .= " $oper (" . implode(',', array_fill(0, count($rhs), $placeholder)) . ')';
                    foreach ($rhs as $v) {
                        $bind_args[] = $v;
                        $this->getBindType($table[$lhs], $types);
                    }
                    break;
                case 'BETWEEN':
                    $bind_args[] = array_shift($rhs);
                    $bind_args[] = array_pop($rhs);
                    $this->getBindType($table[$lhs], $types);
                    $this->getBindType($table[$lhs], $types);
                    $str .= " $oper $placeholder AND $placeholder";
                    break;
                default:
                    $bind_args[] = $rhs === FALSE ? '0' : $rhs;
                    $this->getBindType($table[$lhs], $types);
                    if ($function && $applied_to != 'left') {
                        $str .= " $oper {$function}({$placeholder})";
                    } else {
                        $str .= " $oper $placeholder";
                    }    
            }
        }
        return $str;
    }

    protected function placeholder($bind_args) { return '?'; }

    protected function prepareUpdatePairs(Table $table, $params, &$set, &$where, &$set_keys, &$where_keys) {
        foreach ($params as $key => $val) {
            $field = $table[$key];
            $v = self::formatValue($field, $val);
            if (!$field->is_createstamp && !$field->is_id && !$field->is_timestamp) { // && !$field->is_primary_key) {
                if (preg_match('/^(\+\+|--|((\+|-)=)\s*(\d+))$/', $v, $matches)) {
                    if (isset($matches[4])) {
                        $oper = ($matches[2] == '-=' ? '-' : '+');
                        $set[] = "$key = $key $oper " . $matches[4];
                    } else {
                        $oper = ($matches[0] == '--' ? '-' : '+');
                        $set[] = "$key = $key $oper 1";
                        //var_dump($v, $matches, $oper); exit;
                    }
                } else {
                    $set[] = "$key = " . ($v == 'NULL' ? $v : '?');
                    $set_keys[] = $key;
                }
            }
            if ($field->is_id || $field->is_primary_key) {
                $where[] = "$key = " . ($v == 'NULL' ? $v : '?');
                $where_keys[] = $key;
            }
        }
    }

    protected static function isPartialSQL($string) {
        if (!empty($string) && is_string($string)) {
            $pattern = "/((\s+(AND|OR|LIKE|NOT IN|IN|BETWEEN|IS|IS NOT)\s+)|(\S+\s*(=|<|<=|>|>=|!=|<>)\s*))(\d+|NULL|'[\w%]+')/i";
            return preg_match($pattern, $string);
        }
        return FALSE;
    }

    protected static function tick($str) { return $str; }
    protected static function funcRandom() { return ''; }
    protected static function getFunction($func) { return $func; }

    abstract public function addColumn(Table $table, TableField $field, $position='LAST', $ref='');
    abstract public function create(Table $table);
    abstract public function drop(Table $table);
    abstract public function modifyColumn(Table $table, $field, $newfield);
	abstract public function truncate(Table $table);   
	

}

class QueryGenException extends \Exception {}
?>