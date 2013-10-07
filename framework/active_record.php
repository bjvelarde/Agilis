<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

interface ActiveRecord {

    public function addColumn(Schema $schema, SchemaField $field, $position='LAST', $ref='');

    public function create(Schema $schema);

    public function crossFind(Model $model, ModelAssociate $association, $wheres=array(), $options=array());

    public function delete(Model &$model);

    public function deleteMany(Schema $schema, array $criteria=array());

    public function drop(Schema $schema);
    
    public function dump($model_or_schema);

    public function find(Schema $schema, $what=array(), $options=array());

    public function select(Schema $schema, $fields, $what=array(), $options=array());

    public function insert(Model &$model);

    public function modifyColumn(Schema $schema, $field, $newfield);

    public function removeColumn(Schema $schema, $field);

    public function total(Schema $schema, $where=array());

    public function update(Model &$model);

    public function updateMany(Schema $schema, array $pairs=array(), $criteria=array());

}
?>