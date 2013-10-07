<?php
/*
* WIP - Do not use yet!!!
*/
namespace Agilis;

class MongoAdapter extends DbAdapter {

    public function addColumn(Table $table, TableField $field, $position='LAST', $ref='') { return TRUE; }
    
    public function create(Table $table) { return $table->_db->{$table->_name}; }
    
    public function crossFind(Model $model, ModelAssociate $association, $wheres=array(), $options=array());
    
    public function delete(Model $model) {
        $table->_db->{$table->_name}->remove(
            array('_id'     => new MongoId($model->_id)), 
            array('justOne' => TRUE)
        );
    }
    
    public function deleteMany(Table $table, array $criteria=array()) {
        $table->_db->{$table->_name}->remove($criteria);    
    }
    
    public function drop(Table $table) { $table->_db->{$table->_name}->drop(); }
    
    public function find(Table $table, $what=array(), $options=array()) {
        $collection = $table->_db->{$table->_name};
        $data = $collection->find($what);
        if (isset($options['limit'])) {
            $data = $data->limit($option['limit']);
        }
        if (isset($options['order_by'])) {
            $sort = array(
                // FIX THIS
                $options['order_by'] => (isset($options['sort'] && $options['sort'] == 'DESC') ? -1 : 1)
            );
            $data = $data->sort($sort);
        }
        return $data;
    }
    
    public function insert(Model $model) {
        $class  = get_class($model);
        $data   = $model->getData();
        $id_key = $class::getIdKey();
        $data['_id'] = $data[$id_key] = new MongoId();
        $table->_db->{$table->_name}->insert($data);
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
    
    public function update(Model $model) {
        $data = $model->getData();
        $data['_id'] = $model->id; 
        $table->_db->{$table->_name}->update($data); 
    }
    
    public function updateMany(Table $table, array $pairs=array(), $criteria=array()) {
        $table->_db->{$table->_name}->update($pairs, array('multiple' => TRUE)); 
    }
    
}
?>