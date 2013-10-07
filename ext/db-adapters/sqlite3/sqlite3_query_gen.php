<?php
namespace Agilis;

final class Sqlite3QueryGen extends QueryGenerator  {

    public function addColumn(Table $table, TableField $field, $position='LAST', $ref='') {
        return 'ALTER TABLE ' . $table->_name . ' ADD ' . self::tableField($field);
    }    
    
    public function create(Table $table) {
        $table::refresh();
        $fields = $ukeys = $indices = $fkeys = array();
        foreach ($table as $field) {
            $fields[] = self::tableField($field);
            if ($field->is_unique) {
                $ukeys[] = $field->name;
            } elseif ($field->is_key) {
                $indices[] = $field->name;
            } if ($field->is_foreign_key) {
                $fkeys[] = $field->name;
            }
        }
        $str = "CREATE TABLE IF NOT EXISTS `{$table->_name}` (\n    " . implode(",\n    ", $fields);
        $keys = array();
        if ($table->_primary_keys) {
            $pkeys = array();
            foreach ($table->_primary_keys as $pkey) {
                $pkeys[] = $pkey;
            }
            $keys[] = 'CONSTRAINT pkeys PRIMARY KEY (' . implode(',', $pkeys) . ')';
        }
        if ($table->_unique_keys) {
            foreach ($table->_unique_keys as $key_name => $u) {
                $u_keys = array();
                foreach ($u as $k) {
                    $u_keys[] = $k;
                }
                $keys[] = "CONSTRAINT unique_{$key_name} UNIQUE (" . implode(',', $u_keys) . ')';
            }
        }
        if ($ukeys) {
            foreach ($ukeys as $ukey) {
                $keys[] = "CONSTRAINT unique_{$ukey} UNIQUE ($ukey)"; 
            }
        }
        if ($indices) {
            $kkeys = array();
            foreach ($indices as $index) {
                $kkeys[] = $index;
            }
            $keys[] = 'CONSTRAINT keys_idx KEY (' . implode(',', $kkeys) . ')';
        }
        if ($fkeys) {
            foreach ($fkeys as $fkey) {
                $s = new String($fkey);
                $ref = $s->substr(0, -3)->pluralize();
                $ref_class = $ref->camelize()->singularize()->to_s;
                $ref_table = Table::$ref_class();
                $keys[] = "FOREIGN KEY($fkey) REFERENCES $ref (id) ON DELETE CASCADE";
            }
        }
        if (!empty($keys)) {
            $str .= ",\n    " . implode(",\n    ", $keys);
        }
        $str .= "\n);\n";
        return $str;
    }    

    public function drop(Table $table) {
        return 'DROP TABLE IF EXISTS ' . $table->_name;
    }  

    public function enableForeignKeys($on=TRUE) {
        return 'PRAGMA foreign_keys = ' . ($on ? 'ON' : 'OFF');
    }     
    
    public function removeColumn(Table $table, $field) {
        $sql = array();
        $fields = $table->getFieldNames();
        $flipped = array_flip($fields);
        $index = $flipped[$field];
        unset($fields[$index]);
        $backup = $table->_name . '_backup';
        $fields = implode(',', $fields);
        $sql[] = 'BEGIN TRANSACTION';
        $sql[] = 'CREATE TEMPORARY TABLE ' . $backup . '(' . $fields . ')';
        $sql[] = 'INSERT INTO ' . $backup . ' SELECT ' . $fields . ' FROM t1';
        $sql[] = 'DROP TABLE ' . $table->_name;
        $sql[] = 'CREATE TABLE ' . $table->_name . '(' . $fields . ')';
        $sql[] = 'INSERT INTO ' . $table->_name . ' SELECT ' . $fields . ' FROM ' . $backup;
        $sql[] = 'DROP TABLE ' . $backup;
        $sql[] = 'COMMIT';    
        return $sql;
    }    
    
    public function modifyColumn(Table $table, $field, $newfield) {
        $sql = self::removeColumn($table, $field);
        $sql[] = self::addColumn($table, $newfield);
        return $sql;
    }   
    
	public function truncate(Table $table) {
	    return 'DELETE FROM ' . $table->_name;
	}    
   
    protected function currentDateTime() { return 'now'; }
    
    protected function tableField(TableField $field) {
        $str  = "`{$field->name}` __DATA_TYPE__";
        $str .= $field->is_required       ? ' NOT NULL' : '';
        $str .= $field->is_auto_increment ? ' AUTOINCREMENT' : '';
        if (!$field->is_auto_increment) {
            $str .= ' DEFAULT ';
            $str .= ($field->default !== NULL) ? ($field->is_boolean ? "'" . ($field->default ? 1 : 0) . "'" : "'{$field->default}'") :
                    (($field->is_required || $field->is_createstamp || $field->is_timestamp) ? "'__DEFAULT__'" : 'NULL');
        }
        $default = "''";
        if ($field->is_integer || $field->is_boolean) {
            $type = 'INTEGER';      
        } elseif ($field->is_float || $field->is_double) {
            $type = 'REAL'; 
        } else {
            $type = 'TEXT';
            if ($field->is_date) {
                $default = "'0000-00-00'";
            } elseif ($field->is_time) {
                $default = "'00:00:00'";
            } elseif ($field->is_datetime) {
                $default = "'0000-00-00 00:00:00'";
            } elseif ($field->is_timestamp) {
                $default = 'now';
            }
        }
        $str = str_replace('__DATA_TYPE__', $type, $str);
        $str = str_replace("'__DEFAULT__'", $default, $str);
        return $str;
    }
     
}
?>
