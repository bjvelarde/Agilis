<?php
namespace Agilis;

Conf::ifNotDefined('JQGRID_PER_PAGE', 50);

class JqGridCollection {

    protected $response;
    protected $key;
    protected $find_options;
    protected $model;
    protected $cols;
    protected $collection;
    protected $shown_fkeys;

    public function __construct($class, $config=array()) {
        $config = new Params(is_null($config) ? array() : $config);
        $page  = Common::ifEmpty($config->page, 1);
        $limit = Common::ifEmpty($config->rows, JQGRID_PER_PAGE);  // get how many rows we want to have into the grid
        $sidx  = Common::ifEmpty($config->sidx, 'id'); // get index row - i.e. user click to sort
        $sord  = strtoupper($config->sord); // get the direction
        $sord  = Common::ifEmpty($sord, 'ASC');
        if ($class instanceof ModelCollection) {
            $model = $class->getModel();
            $this->collection = $class;
        } else {
            $model = $class;
            $this->collection = NULL;
        }
        if (isset($config->cols)) {
            $this->cols = $config->cols;
        } else {
            $table = Table::$model();
            $this->cols = $table->getFieldNames();
        }
        $this->model = $model;
        $this->key = Common::ifEmpty($config->key, NULL);
        $this->shown_fkeys = Common::ifEmpty($config->shown_fkeys, array());
        $total = $this->collection ? $this->collection->all($this->key)->count() : $model::total($this->key);
        $total_pages = ($total > 0) ? ceil($total / $limit) : 0;
        if ($page > $total_pages) { $page = $total_pages; }
        $start = $limit * $page - $limit;
        $start = $start < 0 ? 0 : $start;

        $this->response = new \stdClass;
        $this->response->page = $page;
        $this->response->total = $total_pages;
        $this->response->records = $total;

        $this->find_options = array(
            'offset'   => $start,
            'limit'    => $limit,
            'order_by' => array($sidx => $sord)
        );
        $this->getSearchOptions($config);
        $this->getRows($config->add_filler);
    }

    public function __toString() { return json_encode($this->response); }

    public static function grid($class, $config=NULL) {
        return new static($class, $config);
    }

    protected function getSearchOptions(Params $config) {
        $search_key   = $config->searchField;
        $search_value = $config->searchString;
        $search_oper  = $config->searchOper;
        $condition    = array();

        if ($search_key) {
            if ($search_oper == 'eq') {
                $this->key = array($search_key => $search_value);
            } else {
                switch ($search_oper) {
                    case 'ne': $oper = '<>'; break;
                    case 'lt': $oper = '<'; break;
                    case 'le': $oper = '<='; break;
                    case 'gt': $oper = '>'; break;
                    case 'ge': $oper = '>='; break;
                    case 'bw': $oper = 'like%'; break;
                    case 'ew': $oper = '%like'; break;
                    case 'cn': $oper = 'like'; break;
                }
                $condition = array($search_key => array($oper, $search_value));
            }
        }
        if (!empty($condition)) { $this->key = is_array($this->key) ? array_merge($this->key, $condition) : $condition; }
    }

    protected function getRows($add_filler=FALSE) {
        $class   = $this->model;
        $dataset = $this->collection ? $this->collection->all($this->key, $this->find_options) : $class::all($this->key, $this->find_options);
        $rows = array();
        if ($dataset) {
            $i = 0;
            foreach ($dataset as $obj) {
                if ($add_filler) { $data['filler'] = ''; }
                $rows[$i] = $this->getRow($obj);
                $i++;
            }
        }
        if ($rows) { $this->response->rows = $rows;	}
    }

    protected function getRow(Model $obj) {
        $class  = $this->model;
        $table = Table::$class();
        $data   = array();        
        foreach ($this->cols as $col) {
            if ($table[$col]->is_timestamp && $obj[$col] == '0000-00-00 00:00:00') {
                $data[$col] = NULL;
            } elseif ($table[$col]->is_date && $obj[$col] == '0000-00-00') {
                $data[$col] = NULL;
            } elseif ($table[$col]->is_foreign_key && in_array($col, $this->shown_fkeys)) {
		        $var = substr($col, 0, -3);
                $data[$col] = $obj[$var] . '';
            } else {
                $data[$col] = $obj[$col] . '';
            }
        }
        return array(
            'id'   => $obj->getId(),
            'cell' => array_values($data)
        );
    }
}
?>