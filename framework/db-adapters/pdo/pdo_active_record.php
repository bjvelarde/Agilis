<?php
namespace Agilis;

use \PDO as PDO;

class PdoActiveRecord extends SqlActiveRecord {

    protected function bindArgs(&$stmt, &$bind_args) {
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

    protected function findResults($stmt, $class, &$dataset) {
        $stmt->execute();
        $dataset = new ModelCollection($class, $stmt->fetchAll(PDO::FETCH_ASSOC));
        $stmt->closeCursor();
    }

    protected function getAffectedRows(Datasource $ds, &$stmt) { return $stmt->rowCount(); }

    protected function getInsertId(DataSource $ds, &$stmt) { return $ds->lastInsertId(); }
}
?>