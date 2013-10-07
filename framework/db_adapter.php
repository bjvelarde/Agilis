<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

interface DbAdapter {

    public function addColumn(Table $table, TableField $field, $position='LAST', $ref='');

    public function create(Table $table);

    public function crossFind(Model $model, ModelAssociate $association, $wheres=array(), $options=array());

    public function delete(Model &$model);

    public function deleteMany(Table $table, array $criteria=array());

    public function drop(Table $table);
    
    public function dump($model_or_table);

    public function find(Table $table, $what=array(), $options=array());

    public function select(Table $table, $fields, $what=array(), $options=array());

    public function insert(Model &$model);

    public function modifyColumn(Table $table, $field, $newfield);

    public function removeColumn(Table $table, $field);

    public function total(Table $table, $where=array());

    public function update(Model &$model);

    public function updateMany(Table $table, array $pairs=array(), $criteria=array());

}
?>