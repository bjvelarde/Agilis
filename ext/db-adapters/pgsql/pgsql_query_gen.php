<?php
namespace Agilis;

class PgsqlQueryGen extends QueryGenerator {

    public function addColumn(Table $table, TableField $field, $position='LAST', $ref='') {
        $sql = 'ALTER TABLE ' . $table->_name . ' ADD COLUMN ' . self::tableField($table->_db, $field);
        return $sql;        
    }

    public function create(Table $table) {
        $table::refresh();
        $ds = $table->_db;
        $fields = $ukeys = $enums = $fkeys = array();
        foreach ($table as $field) {
            $fields[] = self::tableField($ds, $field);
            if ($field->is_unique) {
                $ukeys[] = $field->name;
            } elseif ($field->is_foreign_key) {
                $fkeys[] = $field->name;
            } 
            if ($field->is_enum || $field->is_set) {
                $enums[] = $field;
            }
        }
        $str = "CREATE TABLE {$table->_name} (\n    " . implode(",\n    ", $fields);
        $keys = array();
        if ($table->_primary_keys) {
            $pkeys = array();
            foreach ($table->_primary_keys as $pkey) {
                $pkeys[] = $pkey;
            }
            $keys[] = 'PRIMARY KEY (' . implode(',', $pkeys) . ')';
        }
        if ($table->_unique_keys) {
            foreach ($table->_unique_keys as $key_name => $u) {
                $u_keys = array();
                foreach ($u as $k) {
                    $u_keys[] = $k;
                }
                $keys[] = "UNIQUE {$key_name}_ukey (" . implode(', ', $u_keys) . ")";
            }
        }
        if ($ukeys) {
            foreach ($ukeys as $ukey) {
                $keys[] = "UNIQUE {$ukey}_ukey ($ukey)";
            }
        }
        if ($fkeys) {
            foreach ($fkeys as $fkey) {
                $s = new String($fkey);
                $ref = $s->substr(0, -3)->pluralize();
                $ref_class = $ref->camelize()->singularize()->to_s;
                $ref_table = Table::$ref_class();
                $keys[] = "FOREIGN KEY {$fkey}_fkey ($fkey) REFERENCES $ref (id) ON DELETE CASCADE DEFERRABLE";
            }
        }
        if (!empty($keys)) {
            $str .= ",\n    " . implode(",\n    ", $keys);
        }
        $str .= "\n) WITH ( OIDS = FALSE );\n"; 
        $sqls = array();
        if ($enums) {
            $sqls = $this->createEnums($table, $enums);            
        }        
        $sqls[] = $str;
        return $sqls;
    }
    
    public function drop(Table $table) {
        return 'DROP TABLE IF EXISTS ' . $table->_name . ' CASCADE';
    }

    public function enableForeignKeys($on=TRUE) {
        // won't really do the trick, but at least this will prevent us having an error
        return "SELECT table_name FROM information_table.tables WHERE table_table = 'public'";
    }  
    
    public function insert(Model $model) {
        $data   = $model->getElements();
        $table = $model->_table;
        $cols   = $vals = array();
        $serial_key = NULL;
        foreach ($data as $k => $v) {
            $field = $table[$k];
            if ($field->is_id || $field->is_timestamp) {
                if ($field->is_auto_increment) {
                    $serial_key = $field->name;
                }
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
        return "INSERT INTO {$model->_table->_name} (" . implode(', ', $cols)
             . ") VALUES(" . implode(', ', $vals) . ")" . ($serial_key ? " RETURNING $serial_key" : '');
    }    
    
    public function modifyColumn(Table $table, $field, $newfield) {
        $sql = array();
        $sql[] = $this->removeColumn($table, $field);
        $sql[] = $this->raddColumn($table, $newfield);
        return $sql;
    }

	public function truncate(Table $table) { return 'TRUNCATE TABLE ' . $table->_name; } 
    
    protected function placeholder($bind_args) { return '$' . count($bind_args); }

    protected function prepareUpdatePairs(Table $table, $params, &$set, &$where, &$set_keys, &$where_keys) {
        $counter = 1;
        foreach ($params as $key => $val) {
            $field = $table[$key];
            $v = self::formatValue($field, $val);
            if (!$field->is_createstamp && !$field->is_id && !$field->is_timestamp) {
                if (preg_match('/^(\+\+|--|((\+|-)=)\s*(\d+))$/', $v, $matches)) {
                    if (isset($matches[4])) {
                        $oper = ($matches[2] == '-=' ? '-' : '+');
                        $set[] = "$key = $key $oper " . $matches[4];
                    } else {
                        $oper = ($matches[2] == '--' ? '-' : '+');
                        $set[] = "$key = $key $oper 1";
                    }
                } else {
                    $set[] = "$key = " . ($v == 'NULL' ? $v : '$' . $counter++);
                    $set_keys[] = $key;
                }
            } elseif ($field->is_id || $field->is_primary_key) {
                $where[] = "$key = " . ($v == 'NULL' ? $v : '$' . $counter++);
                $where_keys[] = $key;
            }
        }
    }      

    protected function tableField(Table $table, TableField $field) {
        $str  = "`{$field->name}` __DATA_TYPE__";
        $str .= $field->is_required       ? ' NOT NULL' : '';
        //$str .= $field->is_auto_increment ? ' auto_increment' : '';
        if (!$field->is_auto_increment) {
            $str .= ' DEFAULT ';
            $str .= ($field->default !== NULL) ? ($field->is_boolean ? "'" . ($field->default ? 1 : 0) . "'" : "'{$field->default}'") :
                    (($field->is_required || $field->is_createstamp || $field->is_timestamp) ? "'__DEFAULT__'" : 'NULL');
        }
        $default = "''";
        if ($field->is_auto_increment) {
            $type = 'BIGSERIAL';
            $default = "'0'";
        } elseif ($field->is_boolean) {
            $type = 'BOOLEAN';
            $default = "'0'";
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
            $default = 'CURRENT_TIMESTAMP';
        }  elseif ($field->is_string) {
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
        } elseif ($field->enum || $field->set) {
            $type = $table->_name . '_' . $field->name;
        } elseif ($field->is_numeric) {
            $default = "'0'";
            if ($field->is_integer) {
                $type = 'INTEGER';
            } else {
                if ($field->is_float) {
                    $type = 'FLOAT';
                } else {
                    $type = ($field->precision && $field->precision == 'exact') ? 'NUMERIC' : 'DOUBLE';
                }
                if ($field->whole !== NULL) {
                    $type .= '(' . $field->whole;
                    if ($field->scale !== NULL) { $type .= ',' . $field->scale; }
                    $type .= ')';
                }
            }
        }
        $str = str_replace('__DATA_TYPE__', $type, $str);
        $str = str_replace("'__DEFAULT__'", $default, $str);
        return $str;
    }

	private function createEnums(Table $table, array $enums) {
        $sqls = array();
        foreach ($enums as $enum) {
            $set_type = $field->enum ? 'enum' : 'set';
            $values = array();
            foreach ($field->{$set_type} as $val) {
                $values[] = "'" . $table->_db->escape($val) . "'";
            }            
            $sqls[] = 'CREATE TYPE ' . $table->_name . '_' . $enum->field . 
                      ' AS ENUM (' . implode(',', $values) . ')';
        }
        return $sqls;		
	}    
    
}
?>