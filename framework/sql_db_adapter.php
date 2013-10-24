<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

use \Exception;

abstract class SqlDbAdapter implements DbAdapter {

    private static $instances;

    protected $querygen;

    private function __construct($db_engine) {
        $qg = __NAMESPACE__ . "\\" . String::camelize($db_engine) . 'QueryGen';
        if (!class_exists($qg)) {
            throw new Exception("Can't find query-generator :" . $qg);
        }
        $this->querygen = $qg::getInstance();
    }

    private function __clone() {}

    public function addColumn(Table $table, TableField $field, $position='LAST', $ref='') {
        $sql = $this->querygen->addColumn($table, $field, $position, $ref);
        Common::devLog($sql);
        return $table->_db->query($sql);
    }

    public function create(Table $table) {
        $sql = $this->querygen->create($table);
        Common::devLog($sql);
        return $table->_db->query($sql);
    }

    public function crossFind(Model $model, ModelAssociate $association, $wheres=array(), $options=array()) {
        $table       = $model::getTable();
        $assoc_table = $association->getAssociateTable();
        $xref_table  = $association->getXrefTable();
        $id_key      = $table->_id_key;
        $assoc_id    = $association->name . '_id';
        $foreign_key = $model->foreignKey();
        $dataset     = array();
        $joins = $xref_table ? array(
            'b' => array('inner' => array('a' => $assoc_table->_id_key, 'b' => $assoc_id))
        ) : array();
        if ($xref_table && $xref_table->hasElement($foreign_key)) {
            $joins['c'] = array('inner' => array('b' => $foreign_key, 'c' => $id_key));
        } else  {
            if ($xref_table) {
                $xref_key = String::singularize($xref_table->_name)->underscore() . '_id';
                if ($table->hasElement($xref_key)) {
                    $joins['c'] = array('inner' => array('b' => $xref_table->_id_key, 'c' => $xref_key));
                } elseif ($assoc_table->hasElement($xref_key)) {
                    $joins['c'] = array('inner' => array('a' => $xref_key, 'b' => $xref_table->_id_key));
                }
            } else {
                if ($assoc_table->hasElement($foreign_key)) {
                    $joins['c'] = array('inner' => array('a' => $foreign_key, 'c' => $id_key));
                } elseif ($table->hasElement($assoc_id)) {
                    $joins['c'] = array('inner' => array('a' => $assoc_table->_id_key, 'c' => $assoc_id));
                } else {
                    throw new Exception("Could not establish a SQL JOIN $foreign_key $assoc_id $assoc_table->_id_key $id_key");
                }
            }
        }
        $tables   = $xref_table ? array('a' => $assoc_table, 'b' => $xref_table, 'c' => $table) : array('a' => $assoc_table, 'c' => $table);
        $criteria = $model->_persisted ? array('c' => array($id_key => $model[$id_key])) : array();
        if ($wheres) {
            if (is_string($wheres)) {
                $criteria = $model->_persisted ? "c.{$id_key} = ' " . $table->_db->escape($model[$id_key]) . "' AND {$wheres}" : $wheres;
            } else {
                $reverse_tables_lookup = array(
                    $association->model         => 'a',
                    $association->through       => 'b',
                    substr($foreign_key, 0, -3) => 'c'
                );
                foreach ($wheres as $t => $what) {
                    $key = $reverse_tables_lookup[$t];
                    $criteria[$key] = isset($criteria[$key]) ? array_merge($criteria[$key], $what) : $what;
                }
            }
        }
        $result_class = String::camelize($association->model)->to_s;
        if ($association->type == 'has_one') {
            $options['limit'] = 1;
        }
        if ($association->type == 'belongs_to') {
            $result_class = get_class($model);
        }
        $options['result_table'] = String::tableize($result_class)->to_s;
        if (isset($options['order_by'][$association->name])) {
            $options['order_by'][$association->model] = $options['order_by'][$association->name];
            unset($options['order_by'][$association->name]);
        }
        $sql = $this->querygen->prepareJoin($tables, $joins, $bind_args, $criteria, $options);
        $this->getFindResults(
            $table->_db,
            $sql,
            $result_class,
            $bind_args,
            $dataset
        );
        Common::devLog($sql, $bind_args);
        return (isset($options['limit']) && $options['limit'] == 1) ? $dataset->shift() : $dataset;
    }

    public function delete(Model &$model) {
        if ($model->_persisted) {
            $table = $model::getTable();
            $sql    = $this->querygen->prepareDelete($model, $bind_args);
            $return = $this->executeSql($table->_db, $sql, $bind_args);
            if ($return) {
                $model->_modified  = FALSE;
                $model->_persisted = FALSE;
                if ($table->_id_key) {
                    $model->{$table->_id_key} = NULL;
                }
                if ($table->_createstamp_key && $table->_timestamp_key) {
                    $model->{$table->_timestamp_key} = $model->{$table->_createstamp_key} = NULL;
                }
            }
            Common::devLog($sql, $bind_args);
            return $return;
        }
        return FALSE;
    }

    public function deleteMany(Table $table, array $criteria=array()) {
        $sql = $this->querygen->deleteMany($table, $bind_args, $criteria);
        Common::devLog($sql, $bind_args);
        return $this->executeSql($table->_db, $sql, $bind_args);
    }

    public function drop(Table $table) {
        $db = $table->_db;
        $db->query($this->querygen->enableForeignKeys(FALSE));
        $return = $db->query($this->querygen->drop($table));
        $db->query($this->querygen->enableForeignKeys());
        return $return;
    }

    public function dump($model_or_table) {
        if ($model_or_table instanceof Table) {
            return $this->querygen->create($model_or_table) . "\n\n";
        } elseif ($model_or_table instanceof Model) {
            return $this->querygen->dump($model_or_table) . "\n";
        }
    }

    public function find(Table $table, $what=array(), $options=array()) {
        if (isset($options['args']) && is_array($options['args'])) {
            $bind_args = $options['args'];
            unset($options['args']);
            $bind_type = implode('', array_fill(0 , count($bind_args), 's'));
            array_unshift($bind_args, $bind_type);
        }
        $sql = $this->querygen->prepareFind($table, $bind_args, $what, $options);
        $this->getFindResults(
            $table->_db,
            $sql,
            String::classify($table->_name)->to_s,
            $bind_args,
            $dataset
        );
        Common::devLog($sql, $bind_args);
        return (isset($options['limit']) && $options['limit'] == 1) ? $dataset->shift() : $dataset;
    }

    public function insert(Model &$model) {
        if (!$model->_persisted) {
            $table = $model::getTable();
            $sql    = $this->querygen->prepareInsert($model, $bind_args);
            $stmt   = $table->_db->prepare($sql);
            $this->bindArgs($stmt, $bind_args);
            $stmt->execute();
            if ($this->getAffectedRows($table->_db, $stmt)) {
                if ($insert_id = $this->getInsertId($table->_db, $stmt)) {
                    if ($table->_id_key && $table[$table->_id_key]->is_auto_increment) {
                        $model->{$table->_id_key} = $insert_id;
                    }
                }
                if ($table->_createstamp_key && $table->_timestamp_key) {
                    if (!$model->{$table->_createstamp_key}) {
                        $model->{$table->_createstamp_key} = date('Y-m-d H:i:s');
                    }
                    $model->{$table->_timestamp_key} = $model->{$table->_createstamp_key};
                }
                $model->_persisted = TRUE;
                $return = TRUE;
            } else {
                $return = FALSE;
            }
            $stmt->close();
            Common::devLog($sql, $bind_args);
            return $return;
        }
        return FALSE;
    }

    public function modifyColumn(Table $table, $field, $newfield) {
        $sql = $this->querygen->modifyColumn($table, $field, $newfield);
        Common::devLog($sql);
        $table->_db->query($sql);
    }

    public function removeColumn(Table $table, $field) {
        $sql = $this->querygen->removeColumn($table, $field);
        $table->_db->query($sql);
        Common::devLog($sql);
    }

    public function tableWhere(QueryWhere $where) {
        $sql = $this->querygen->prepareTableWhere($where, $bind_args);
        $this->getFindResults(
            $where->table->_db,
            $sql,
            String::classify($where->table->_name)->to_s,
            $bind_args,
            $dataset
        );
        Common::devLog($sql, $bind_args);
        return $dataset;
    }

    public function select(Table $table, $fields, $what=array(), $options=array()) {
        $fields = is_array($fields) ? $fields : array($fields);
        $sql    = $this->querygen->select($table, $bind_args, $what, $options, $fields);
        $this->getFindResults(
            $table->_db,
            $sql,
            String::classify($table->_name)->to_s,
            $bind_args,
            $dataset
        );
        Common::devLog($sql, $bind_args);
        return (isset($options['limit']) && $options['limit'] == 1) ? $dataset->shift() : $dataset;
    }

    public function total(Table $table, $where=array()) {
        $sql  = $this->querygen->prepareTotal($table, $bind_args, $where);
        Common::devLog($sql, $bind_args);
        return $this->fetchTotal($table->_db, $sql, $bind_args);
    }

    public function update(Model &$model) {
        if ($model->_persisted && $model->_modified) {
            $table = $model::getTable();
            $sql    = $this->querygen->prepareUpdate($model, $bind_args);
            $return = $this->executeSql($table->_db, $sql, $bind_args);
            if ($return) { $model->_modified = FALSE; }
            Common::devLog($sql, $bind_args);
            return $return;
        }
        return FALSE;
    }

    public function updateMany(Table $table, array $pairs=array(), $criteria=array()) {
        $sql = $this->querygen->updateMany($table, $bind_args, $pairs, $criteria);
        // dev log
        Common::devLog($sql, $bind_args);
        return $this->executeSql($table->_db, $sql, $bind_args);
    }

    protected function executeSql(DataSource $ds, $sql, &$bind_args) {
        $stmt = $ds->prepare($sql);
        $this->bindArgs($stmt, $bind_args);
        // dev log
        Common::devLog($sql, $bind_args);
        return $this->executeStatement($stmt);
    }

    protected function fetchTotal(DataSource $ds, $sql, &$bind_args) {
        $stmt = $ds->prepare($sql);
        $this->bindArgs($stmt, $bind_args);
        Common::devLog($sql, $bind_args);
        return $this->fetchTotalStmt($stmt);
    }

    protected function getFindResults(DataSource $ds, $sql, $class, &$bind_args, &$dataset) {
        $stmt = $ds->prepare($sql);
        $this->bindArgs($stmt, $bind_args);
        Common::devLog($sql, $bind_args);
        $this->findResults($stmt, $class, $dataset);
    }

    public static function getInstance($db_engine=NULL) {
        $class = get_called_class();
        return (isset(self::$instances[$class]) && (self::$instances[$class] instanceof $class)) ?
               self::$instances[$class]:
               self::$instances[$class] = new $class($db_engine);
    }

    abstract public function beginTransaction();
    abstract protected function bindArgs(&$stmt, &$bind_args);
    abstract protected function executeStatement(&$stmt);
    abstract protected function fetchTotalStmt(&$stmt);
    abstract protected function findResults(&$stmt, $class, &$dataset);
    abstract protected function getAffectedRows(DataSource $ds, &$stmt);
    abstract protected function getInsertId(DataSource $ds, &$stmt);

}
?>