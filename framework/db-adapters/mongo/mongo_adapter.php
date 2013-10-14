<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

use \MongoId;

class MongoAdapter implements DbAdapter {

    private static $instance;

    public static function getInstance($db_engine=NULL) {
        if (!self::$instance instanceof self) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function addColumn(Table $table, TableField $field, $position='LAST', $ref='') { return TRUE; }

    public function create(Table $table) { return $table->_db->createCollection($table->_name); }

    public function crossFind(Model $model, ModelAssociate $association, $wheres=array(), $options=array()) {
        $table       = $association->getAssociateTable();
        $xref_table  = $association->getXrefTable();
        $collection  = $table->_db->{$table->_name};
        $xcollection = $xref_table->_db->{$xref_table->_name};
        $foreign_key = $model->foreignKey();
        $assoc_id    = $association->name . '_id';
        $assoc_id2   = $association->model . '_id';
        switch ($association->type) {
            case 'has_one':
                if (!$xref_table) {
                    $condition = array(
                        $foreign_key => $model->getId()
                    );
                    foreach ($wheres as $t => $what) {
                        if ($t == $association->name) {
                            $condition = array_merge($what, $condition);
                        }
                    }
                    return $collection->find($condition)->limit(1);
                } else {
                    $xcondition = array(
                        $foreign_key => $model->getId()
                    );
                    $condition = array();
                    foreach ($wheres as $t => $what) {
                        if ($t == $association->through) {
                            $xcondition = array_merge($what, $xcondition);
                        } elseif ($t == $association->name) {
                            $condition = array_merge($what, $condition);
                        }
                    }
                    $xrefdata = $xcollection->find($xcondition)->limit(1);
                    $condition['_id'] = $xrefdata[$assoc_id2];
                    return $collection->find($condition)->limit(1);
                }
            case 'has_many':
            case 'has_and_belongs_to_many':
                if (!$xref_table) {
                    $condition   = array(
                        $foreign_key => $model->getId()
                    );
                    foreach ($wheres as $t => $what) {
                        if ($t == $association->model) {
                            $condition = array_merge($what, $condition);
                        }
                    }
                    $data = $collection->find($condition);
                    if (isset($options['limit'])) {
                        $data = $data->limit($options['limit']);
                    }
                    if (isset($options['limit'])) {
                        $data = $data->limit($options['limit']);
                    }
                    return $data;
                } else {
                    $xcondition = array(
                        $foreign_key => $model->getId()
                    );
                    $condition = array();
                    foreach ($wheres as $t => $what) {
                        if ($t == $association->through) {
                            $xcondition = array_merge($what, $xcondition);
                        } elseif ($t == $association->name) {
                            $condition = array_merge($what, $condition);
                        }
                    }
                    $xrefdata = $xcollection->find($xcondition);
                    if ($xrefdata) {
                        $assoc_ids = array();
                        foreach ($xrefdata as $x) {
                            $assoc_ids[] = array('_id' => $x[$assoc_id2]);
                        }
                        $condition['$or'] = $assoc_ids;
                        return $collection->find($condition);
                    }
                    return NULL;
                }
            case 'belongs_to':
                $condition = array(
                    '_id' => $model->{$assoc_id}
                );
                foreach ($wheres as $t => $what) {
                    if ($t == $association->model) {
                        $condition = array_merge($what, $condition);
                    }
                }
                return $collection->find($condition)->limit(1);
        }
    }

    public function dump($model_or_table) { return TRUE; }

    public function delete(Model &$model) {
        if ($model->_persisted) {
            $result = $table->_db->{$table->_name}->remove(
                array('_id'     => new MongoId($model->getId())),
                array('justOne' => TRUE)
            );
            if ($result) {
                $model->_modified  = FALSE;
                $model->_persisted = FALSE;
                if ($table->_id_key) {
                    $model->{$table->_id_key} = NULL;
                }
                if ($table->_createstamp_key && $table->_timestamp_key) {
                    $model->{$table->_timestamp_key} = $model->{$table->_createstamp_key} = NULL;
                }
            }
        }
        return FALSE;
    }

    public function deleteMany(Table $table, array $criteria=array()) {
        $table->_db->{$table->_name}->remove($criteria);
    }

    public function drop(Table $table) {
        if ($table->_db->{$table->_name}) {
            return $table->_db->{$table->_name}->drop();
        }
        return TRUE;
    }

    public function find(Table $table, $what=array(), $options=array()) {
        $class = String::classify($table->_name)->to_s;
        $collection = $table->_db->{$table->_name};
        $what = $what ? $what : array();
        if (isset($options['limit'])) {
            if ($options['limit'] == 1) {
                $data = $collection->findOne($what);
                if ($data) {
                    $obj = new $class($data);
                    $obj->_persisted = TRUE;
                    $obj->_modified  = FALSE;
                    return $obj;
                }
                return NULL;                 
            } else {
                $data = $collection->find($what)->limit($option['limit']);
            }
        } else {
            $data = $collection->find($what);
        }
        if (isset($options['order_by'])) {
            $sort = array();
            if (is_string($options['order_by']) && strtoupper($options['order_by']) == 'RANDOM') {
                $fields = $table->getFieldNames();
                $randidx = mt_rand(0, count($fields));
                $sort = array($fields[$randidx] => 1);
            } else {
                $order_by_arr = array();
                foreach ($options['order_by'] as $order_by => $order) {
                    $sort[$order_by] = strtolower($order) === 'desc' ? -1 : 1;
                }
            }
            $data = $data->sort($sort);
        }
        $coll = new ModelCollection($class);
        foreach ($data as $row) {
            $coll->addItem($row);
        }
        return $coll;
    }

    public function insert(Model &$model) {
        if (!$model->_persisted) {
            $class = get_class($model);
            $data  = $model->getData();
            $data['_id'] = new MongoId();
            $table = $model::getTable();
            return $table->_db->{$table->_name}->insert($data);
        }
        return FALSE;
    }

    public function modifyColumn(Table $table, $field, $newfield) { return TRUE; }

    public function removeColumn(Table $table, $field) { return TRUE; }

    public function select(Table $table, $fields, $what=array(), $options=array()) {
        $fields = is_array($fields) ? $fields : array($field);
        $collection = $table->_db->{$table->_name};
        $data = $collection->find($what);
        $dataset = array();
        foreach ($data as $record) {
            $entry = array();
            foreach ($fields as $field) {
                $entry[$field] = $record[$field];
            }
            $dataset[] = $entry;
        }
        return $dataset;
    }

    public function total(Table $table, $where=array()) {
        $data = $table->_db->{$table->_name}->find($where);
        return count($data);
    }

    public function update(Model &$model) {
        if ($model->_persisted && $model->_modified) {
            $table = $model::getTable();
            $data = $model->getData();
            $obj = $table->_db->{$table->_name}->findOne(array('_id' => $model->_id));
            //$data['_id'] = $model->_id;
            return $table->_db->{$table->_name}->update($data, $obj);
        }
        return FALSE;
    }

    public function updateMany(Table $table, array $pairs=array(), $criteria=array()) {
        $table->_db->{$table->_name}->update($pairs, array('multiple' => TRUE));
    }

}
?>