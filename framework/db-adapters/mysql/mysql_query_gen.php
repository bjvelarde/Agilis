<?php
namespace Agilis;

class MysqlQueryGen extends QueryGenerator {

    public function addColumn(Table $table, TableField $field, $position='LAST', $ref='') {
        $sql = 'ALTER TABLE ' . $table->_name . ' ADD COLUMN ' . self::tableField($table, $field);
        if ($position == 'FIRST') {
            $sql .= ' FIRST';
        } elseif ($position == 'AFTER' && $ref) {
            $sql .= ' AFTER ' . $ref;
        }
        return $sql;
    }

    public function create(Table $table) {
        $fields = $ukeys = $indices = $fkeys = array();
        foreach ($table as $field) {
            $fields[] = self::tableField($table, $field);
            if ($field->is_unique) {
                $ukeys[] = $field->name;
            } elseif ($field->is_key) {
                $indices[] = $field->name;
            } if ($field->is_foreign_key) {
                //if ($table->_db->storage == 'InnoDB') {
                //    $fkeys[] = $field->name;
                //} else {
                if (!in_array($field->name, $indices) && !in_array($field->name, $table->_primary_keys)) {
                    $indices[] = $field->name;
                }
            }
        }
        $str = "CREATE TABLE IF NOT EXISTS `{$table->_name}` (\n    " . implode(",\n    ", $fields);
        $keys = array();
        if ($table->_primary_keys) {
            $pkeys = array();
            foreach ($table->_primary_keys as $pkey) {
                $pkeys[] = "`$pkey`";
            }
            $keys[] = 'PRIMARY KEY (' . implode(',', $pkeys) . ')';
        }
        if ($table->_unique_keys) {
            foreach ($table->_unique_keys as $key_name => $u) {
                $u_keys = array();
                foreach ($u as $k) {
                    $u_keys[] = "`$k`";
                }
                $keys[] = "UNIQUE KEY `{$key_name}_ukey` (" . implode(', ', $u_keys) . ")";
            }
        }
        if ($ukeys) {
            foreach ($ukeys as $ukey) {
                $keys[] = "UNIQUE KEY `{$ukey}_ukey` (`$ukey`)";
            }
        }
        if ($indices) {
            $kkeys = array();
            foreach ($indices as $index) {
                $kkeys[] = "`$index`";
            }
            $keys[] = "KEY `" . $table->_name . '_idx` (' . implode(',', $kkeys) . ')';
        }
        //if ($fkeys) {
        //    foreach ($fkeys as $fkey) {
        //        $s = new String($fkey);
        //        $ref = $s->substr(0, -3)->pluralize();
        //        $ref_class = $ref->camelize()->singularize()->to_s;
        //        //$ref_table = Table::$ref_class();
        //        $keys[] = "FOREIGN KEY `{$fkey}_fkey` (`$fkey`) REFERENCES $ref (id) ON DELETE CASCADE";
        //    }
        //}
        if (!empty($keys)) {
            $str .= ",\n    " . implode(",\n    ", $keys);
        }
        $str .= "\n) ENGINE=" . $table->_db->storage
              . ' DEFAULT CHARSET='
              . $table->_db->charset
              . ";\n";
        return $str;
    }
    
    public function drop(Table $table) {
        $sql = "DROP TABLE IF EXISTS `" . $table->_name . "`";
        $datasrc = $table->data_source_config;
        if ($datasrc->storage == 'InnoDB') {
            $sql .= ' CASCADE';
        }
        return $sql;
    }

    public function enableForeignKeys($on=TRUE) {
        return 'SET foreign_key_checks = ' . ($on ? '1' : '0');
    }  
    
    public function modifyColumn(Table $table, $field, $newfield) {
        $sql = 'ALTER ' . $table->_name;
        if ($newfield instanceof TableField) {
            if ($newfield->name == $field) {
                $sql .= ' MODIFY ' . $newfield;
            } else {
                $sql .= ' CHANGE ' . $field . ' ' . self::tableField($table, $newfield);
            }
        } else {
            $sql .= ' CHANGE ' . $field . ' ' . $newfield . ' ' . self::tableField($table, $table[$field]);
        }
        return $sql;
    }

	public function truncate(Table $table) { return "TRUNCATE TABLE `" . $table->_name . "`";} 
    
    public function prepareFind(Table $table, &$bind_args, $what=array(), $options=array()) {    
        if (isset($options['args']) && is_array($options['args'])) {
            $args  = array();
            $types = '';
            foreach ($options['args'] as $arg) {
                if (is_array($arg)) {
                    $args[] = $arg[0];
                    $types .=  $arg[1];
                } else {
                    $args[] = $arg;
                    $types .= 's';
                }
            }
            $bind_args = $args; //$options['args'];
            unset($options['args']);
            //$bind_type = implode('', array_fill(0 , count($bind_args), 's'));
            array_unshift($bind_args, $types);
        }    
        return parent::prepareFind($table, $bind_args, $what, $options);
    }

    protected function getBindType($field, &$types) {
        $subject = ($field instanceof TableField) ? $field->type : gettype($field);
        switch ($subject) {
            case 'float'  :
            case 'double' :
                $types .= 'd';
                break;
            case 'integer':
            case 'boolean':
                $types .= 'i';
                break;
            case 'blob'   :
                $types .= 'b';
                break;            
            case 'text'   :
                $types .= 's';
                break;
            default:
                $types .= 's';
                break;
        }       
    }

    protected function tableField(Table $table, TableField $field) {
        $ds   = $table->_db;
        $str  = "`{$field->name}` __DATA_TYPE__";
        $str .= $field->is_required       ? ' NOT NULL' : '';
        $str .= $field->is_auto_increment ? ' auto_increment' : '';
        if (!$field->is_auto_increment) {
            $str .= ' DEFAULT ';
            $str .= ($field->default !== NULL) ? ($field->is_boolean ? "'" . ($field->default ? 1 : 0) . "'" : "'{$field->default}'") :
                    (($field->is_required || $field->is_createstamp || $field->is_timestamp) ? "'__DEFAULT__'" : 'NULL');
        }
        $default = "''";
        if ($field->enum || $field->set) {
            $set_type = $field->enum ? 'enum' : 'set';
            $values = array();
            foreach ($field->{$set_type} as $val) {
                $values[] = "'" . $ds->escape($val) . "'";
            }
            $type = strtoupper($set_type) . '(' . implode(', ', $values) . ')';
        } elseif ($field->is_boolean) {
            $type    = 'TINYINT(1)';
        } elseif ($field->is_date) {
            $type    = 'DATE';
            $default = "'0000-00-00'";
        } elseif ($field->is_time) {
            $type    = 'TIME';
            $default = "'00:00:00'";
        } elseif ($field->is_datetime) {
            $type    = 'DATETIME';
            $default = "'0000-00-00 00:00:00'";
        } elseif ($field->is_timestamp) {
            $type    = 'TIMESTAMP';
            $default = 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        } elseif ($field->is_string) {
            $length = 255;
            if ($field->maxlen) {
                $length = $field->maxlen;
                if ($field->maxlen == $field->maxlen) {
                    $type = "CHAR({$field->maxlen})";
                }
            }
            if (!isset($type)) {
                $type = "VARCHAR($length)";
            }
        } elseif ($field->is_text) {
            $type = "TEXT";
        } elseif ($field->is_numeric) {
            $default = "'0'";
            if ($field->is_integer) {
                $type = 'INT(11)';
            } else {
                if ($field->is_float) {
                    $type = 'FLOAT';
                } else {
                    $type = ($field->precision && $field->precision == 'exact') ? 'DECIMAL' : 'DOUBLE';
                }
                if ($field->whole !== NULL) {
                    $type .= '(' . $field->whole;
                    if ($field->scale !== NULL) { $type .= ',' . $field->scale; }
                    $type .= ')';
                }
            }
        } elseif ($field->is_ip) {
            $type = "VARCHAR(15)";
        } else {
            $type = "VARCHAR(255)";
        }
        $str = str_replace('__DATA_TYPE__', $type, $str);
        $str = str_replace("'__DEFAULT__'", $default, $str);
        return $str;
    }
    
    protected static function tick($str) { return "`$str`"; }
    
    protected static function funcRandom() { return 'RAND()'; }

}
?>