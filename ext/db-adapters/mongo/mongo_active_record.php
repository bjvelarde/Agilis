<?php
/*
* WIP - Do not use yet!!!
*/
namespace Agilis;

class MongoActiveRecord extends ActiveRecord {

    public function addColumn(Schema $schema, SchemaField $field, $position='LAST', $ref='') { return TRUE; }
    
    public function create(Schema $schema) { return $schema->_db->{$schema->_table}; }
    
    public function crossFind(Model $model, ModelAssociate $association, $wheres=array(), $options=array());
    
    public function delete(Model $model) {
        $schema->_db->{$schema->_table}->remove(
            array('_id'     => new MongoId($model->_id)), 
            array('justOne' => TRUE)
        );
    }
    
    public function deleteMany(Schema $schema, array $criteria=array()) {
        $schema->_db->{$schema->_table}->remove($criteria);    
    }
    
    public function drop(Schema $schema) { $schema->_db->{$schema->_table}->drop(); }
    
    public function find(Schema $schema, $what=array(), $options=array()) {
        $collection = $schema->_db->{$schema->_table};
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
        $schema->_db->{$schema->_table}->insert($data);
    }
    
    public function modifyColumn(Schema $schema, $field, $newfield) { return TRUE; }
    
    public function removeColumn(Schema $schema, $field) { return TRUE; }
    
    public function select(Schema $schema, $fields, $what=array(), $options=array()) {
        $fields = is_array($fields) ? $fields : array($field);
        $collection = $schema->_db->{$schema->_table};
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
    
    public function total(Schema $schema, $where=array()) {
        $data = $schema->_db->{$schema->_table}->find($where);
        return count($data);
    }    
    
    public function update(Model $model) {
        $data = $model->getData();
        $data['_id'] = $model->id; 
        $schema->_db->{$schema->_table}->update($data); 
    }
    
    public function updateMany(Schema $schema, array $pairs=array(), $criteria=array()) {
        $schema->_db->{$schema->_table}->update($pairs, array('multiple' => TRUE)); 
    }
    
}
?>