<?php
namespace Agilis;

class MysqlAdapter extends SqlDbAdapter {

    public function beginTransaction() { $this->autocommit(FALSE); }

    protected function bindArgs(&$stmt, &$bind_args) {
        if ($bind_args) {
            call_user_func_array(
                array($stmt, 'bind_param'),
                CraftyArray::makeValuesReferenced($bind_args)
            );
        }
    }

    protected function executeStatement(&$stmt) {
        $stmt->execute();
        $return = $stmt->affected_rows;
        $stmt->close();
        return $return;
    }

    protected function fetchTotalStmt(&$stmt) {
        $stmt->execute();
        $stmt->bind_result($total);
        $stmt->fetch();
        $stmt->close();
        return $total;
    }

    protected function findResults(&$stmt, $class, &$dataset) {
        $stmt->execute();
        $bind_results = array();
        $meta = $stmt->result_metadata();
        while ($field = $meta->fetch_field()) {
            $bind_results[] = &$row[$field->name];
        }
        call_user_func_array(array($stmt, 'bind_result'), $bind_results);
        $dataset = new ModelCollection($class);
        while ($stmt->fetch()) {
            $data = array();
            foreach($row as $key => $val) {
                $data[$key] = $val;
            }
            $dataset->addItem($data);
        }
        $stmt->close();
    }

    protected function getAffectedRows(DataSource $ds, &$stmt) {
        if ($stmt->error) {
            throw new MysqlStmtException($stmt);
        }
        return $stmt->affected_rows;
    }

    protected function getFindResults(DataSource $ds, $sql, $class, &$bind_args, &$dataset) {
        if ($bind_args) {
            $stmt = $ds->prepare($sql);
            $this->bindArgs($stmt, $bind_args);
            $this->findResults($stmt, $class, $dataset);
        } else {
            $result = $ds->query($sql);
            if (function_exists('mysqli_fetch_all')) {
                $all = $result->fetch_all(MYSQLI_ASSOC);
            } else {
                $all = array();
                while ($row = $result->fetch_assoc()) {
                    $all[] = $row;
                }
            }
            $dataset = new ModelCollection($class, $all);
        }
    }

    protected function getInsertId(DataSource $ds, &$stmt) { return $stmt->insert_id; }
}

class MysqlStmtException extends \Exception {

    public function __construct(\mysqli_stmt $stmt) {
        parent::__construct($stmt->error);
    }

}
?>