<?php
namespace Agilis;

class PgsqlAdapter extends SqlDbAdapter {

    public function drop(Table $table) {
        return $table->_db->query($this->querygen->drop($table));
    } 

    public function insert(Model $model) {
        $table = $model->_table;
        $sql    = $this->querygen->prepareInsert($model, $bind_args);
        if ($table->_db->prepare($sql)) {
            $result = $table->_db->execute($bind_args);
            if (($result instanceof PgsqlResult) && $result->affected_rows()) {
                $return = $result->fetch_row();
                if ($return) {
                    if ($table->_id_key && $table[$table->_id_key]->is_auto_increment) {
                        $model->{$table->_id_key} = $return[0];
                    }                    
                }
                if ($table->_createstamp_key && $table->_timestamp_key) {
                    $model->{$table->_timestamp_key} = $model->{$table->_createstamp_key};
                }
                $model->_persisted = TRUE;
                return TRUE;                
            }
        }
        return FALSE;
    }    

    public function modifyColumn(Table $table, $field, $newfield) {
        $sqls = $this->querygen->modifyColumn($table, $field, $newfield);
        foreach ($sqls as $sql) {
            $table->_db->query($sql);
        }
    }       

    protected function getFindResults(DataSource $ds, $sql, $class, &$bind_args, &$dataset) {
        if ($ds->prepare($sql)) {
             $result = $ds->execute($bind_args);
             if ($result instanceof PgsqlResult) {
                 $recordset = $result->fetch_all();
                 if ($recordset) {
                     $dataset = new ModelCollection($class, $recordset);
                 }
             }
        }
    }   

    protected function executeSql(DataSource $ds, $sql, &$bind_args) {
        if ($ds->prepare($sql)) {
            $result = $ds->execute($bind_args);
            if ($result instanceof PgsqlResult) {
                return $result->affected_rows();
            }             
        }
        return 0;
    } 

    protected function fetchTotal(DataSource $ds, $sql, &$bind_args) {
        if ($ds->prepare($sql)) {
            $result = $ds->execute($bind_args);
            if ($result instanceof PgsqlResult) {
                $data = $result->fetch_row();
                if ($data) {
                    return array_pop($data);
                }
            }             
        }
        return 0;
    }    

    protected function bindArgs(&$stmt, &$bind_args) { return; }
    protected function closeStmt(&$stmt) { return }
    protected function executeStatement(&$stmt) { return; }
    protected function fetchTotalStmt(&$stmt) { return; }
    protected function findResults($stmt, $class, &$dataset) { return; }
    protected function getAffectedRows(Datasource $ds, &$stmt) { return; }
    protected function getInsertId(DataSource $ds, &$stmt) { return; }
}
?>