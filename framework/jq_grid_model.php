<?php
namespace Agilis;

class JqGridModel extends JqGridOptions {

    private $_table;
    private $_columns;
    private $_config;

    public function __construct($class, $ajax_url, $action_url, $config=array()) {
        $this->_table   = Table::$class();
        $this->_columns = $this->_table->getFieldNames();
        $this->_config  = array(
            'hidden'          => (isset($config['hidden'])          ? (is_array($config['hidden'])          ? $config['hidden']          : array($config['hidden']))          : array()),
            'shown_fkey'      => (isset($config['shown_fkey'])      ? (is_array($config['shown_fkey'])      ? $config['shown_fkey']      : array($config['shown_fkey']))      : array()),
            'edit_hidden'     => (isset($config['edit_hidden'])     ? (is_array($config['edit_hidden'])     ? $config['edit_hidden']     : array($config['edit_hidden']))     : array()),
            'nosearch_hidden' => (isset($config['nosearch_hidden']) ? (is_array($config['nosearch_hidden']) ? $config['nosearch_hidden'] : array($config['nosearch_hidden'])) : array()),
            'disabled'        => (isset($config['disabled'])        ? (is_array($config['disabled'])        ? $config['disabled']        : array($config['disabled']))        : array())
        );
        if (isset($config['key'])) {
            $this->_config['key'] = $config['key'];
        }      
		parent::__construct(
		    $this->getColDef(),
		    Params::url($ajax_url)
                  ->caption(String::humanize($this->_table->_table)->ucwords()->to_s)
                  ->sortname($this->_columns[0])
                  ->editurl($action_url)
        );
    }

    private function getColDef() {
	    $width = 0;
        $cols = $model = array();
        foreach ($this->_columns as $field) {
            $cfg = array();
            if (in_array($field, $this->_config['shown_fkey'])) { $cfg[] = 'shown_fkey'; }
            if (in_array($field, $this->_config['edit_hidden'])) { $cfg[] = 'edit_hidden'; }
            if (in_array($field, $this->_config['nosearch_hidden'])) { $cfg[] = 'nosearch_hidden'; }
            if (in_array($field, $this->_config['hidden'])) { $cfg[] = 'hidden'; }
            if (in_array($field, $this->_config['disabled'])) { $cfg[] = 'disabled'; }
            if (isset($this->_config['key']) && $this->_config['key'] == $field) {
                $cfg[] = 'key';
            }
            $name     = in_array($field, $this->_config['shown_fkey']) ? substr($field, 0 , -3) : $field;
            $cols[]   = String::titleize($name)->to_s;
            $colmodel = new JqGridColModel($this->_table[$field], $cfg);
            $model[]  = $colmodel->getElements();
        }
        $class = $this->_table->_model;
       /* if ($this->_table->_translatables) {
            foreach (Config::get('LOCALES') as $locale) {
                if ($locale != CONF_DEFAULT_LOCALE) {
                    $cols[]  = $locale;
                    $model[] = array(
                        'name'      => $locale,
                        'index'     => $locale,
                        'width'     => 36,
                        'align'     => 'center',
                        'search'    => FALSE,
						'resizable' => FALSE
                    );
                }
            }
        }*/
        return Params::colnames($cols)->colmodels($model);
    }

    //public function totalRecords() {
        //$model = $this->getModelClass();
        //return $model::total();
    //}

    public function getModelClass() {
        return $this->_table->_model;
    }

    public function addColumns() {
        $args = func_get_args();
        if ($args) {
            $cols  = $this->colNames;
            $model = $this->colModel;
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    $cols[]  = String::titleize($arg['name'])->to_s;
                    $model[] = $arg;
                }
            }
            $this->colNames = $cols;
            $this->colModel = $model;
        }
        $this->normalizeWidth();
    }
}
?>