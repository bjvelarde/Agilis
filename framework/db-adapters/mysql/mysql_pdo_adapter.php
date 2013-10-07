<?php
namespace Agilis;

use \PDO as PDO;

class MysqlPdoAdapter extends MysqlAdapter {

    protected function bindArgs(&$stmt, &$bind_args) {
        array_shift($bind_args); // remove bind types for mysqli
        if ($bind_args) {
            for ($i = 0; $i < count($bind_args); $i++) {
                $stmt->bindValue(($i + 1), $bind_args[$i]);
            }
        }
    }

    protected function executeStatement(&$stmt) {
        $stmt->execute();
        $return = $stmt->rowCount();
        $stmt->closeCursor();
        return $return;
    }

    protected function fetchTotalStmt(&$stmt) {
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_NUM);
        $stmt->closeCursor();
        return array_pop($result);
    }

    protected function findResults(&$stmt, $class, &$dataset) {
        $stmt->execute();
        $dataset = new ModelCollection($class, $stmt->fetchAll(PDO::FETCH_ASSOC));
        $stmt->closeCursor();
    }
    
    protected function getFindResults(DataSource $ds, $sql, $class, &$bind_args, &$dataset) {
        if ($bind_args) {
            $stmt = $ds->prepare($sql);
            $this->bindArgs($stmt, $bind_args);
            $this->findResults($stmt, $class, $dataset);
        } else {
            $result = $ds->query($sql);
            $all = $result->fetchAll(PDO::FETCH_ASSOC);
            $dataset = new ModelCollection($class, $all);
        }
    }    

    protected function getAffectedRows(Datasource $ds, &$stmt) { return $stmt->rowCount(); }

    protected function getInsertId(DataSource $ds, &$stmt) { return $ds->lastInsertId(); }
}
?>