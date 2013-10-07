<?php
namespace Agilis;

class Sqlite3Adapter extends SqlDbAdapter {

    public function modifyColumn(Table $table, $field, $newfield) {
        $sqls = $this->querygen->modifyColumn($table, $field, $newfield);
        foreach ($sqls as $sql) {
            $table->_db->query($sql);
        }
    }

    public function removeColumn(Table $table, $field) {
        $sqls = $this->querygen->removeColumn($table, $field);
        foreach ($sqls as $sql) {
            $table->_db->query($sql);
        }
    }

    protected function bindArgs(&$stmt, &$bind_args) {
        if ($bind_args) {
            for ($i = 0; $i < count($bind_args); $i++) {
                $stmt->bindValue(($i + 1), $bind_args[$i]);
            }
        }
    }

    protected function executeStatement(&$stmt) {
        $stmt->execute();
        $return = $table->_db->changes();
        $stmt->close();
        return $return;
    }

    protected function fetchTotalStmt(&$stmt) {
        $result = $stmt->execute();
        $total  = $result->fetchArray(SQLITE3_NUM);
        $stmt->close();
        return array_pop($total);
    }

    protected function findResults($stmt, $class, &$dataset) {
        $result = $stmt->execute();
        $dataset = new ModelCollection($class);
        while ($data = $result->fetchArray(SQLITE3_ASSOC)) {
            $dataset->addItem($data);
        }
        $stmt->close();
    }

    protected function getAffectedRows(DataSource $ds, &$stmt) { return $ds->changes(); }

    protected function getInsertId(DataSource $ds, &$stmt) { return $ds->lastInsertRowID(); }
}
?>