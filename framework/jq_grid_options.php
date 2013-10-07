<?php
namespace Agilis;

Conf::ifNotDefined('JQGRID_MAXWIDTH', 420);

class JqGridOptions extends DynaStruct {

	public $_width;

    public function __construct(Params $coldef, Params $params=NULL) {
        $params = $params ? $params : new Params;
        $coldef->checkRequired('colnames', 'colmodels');
        $params->checkRequired('url', 'editurl');
        $this->caption = Common::ifEmpty($params->caption, 'Data Grid');
        $this->url      = $params->url;
        $this->colNames = $coldef->colnames;
        $this->colModel = $coldef->colmodels;
        $this->sortname = $params->sortname;
		$this->editurl  = $params->editurl;
		$this->normalizeWidth();
    }

    protected function getWidth() {
	    $width = 0;
        foreach ($this->colModel as $model) {
			$width += ($model['hidden'] ? 0 : $model['width']);
        }
        $this->_width = $width;
    }

    protected function normalizeWidth() {
        $cols  = $this->colNames;
        $model = $this->colModel;
        $this->getWidth();
		if ($this->_width < JQGRID_MAXWIDTH) {
		    $diff    = JQGRID_MAXWIDTH - $this->_width;
			$cols[]  = '-';
			$model[] = array(
				'name'      => 'filler',
				'width'     => $diff,
				'align'     => 'center',
				'search'    => FALSE,
				'resizable' => FALSE
			);
            $this->url .= strstr($this->url, '?') ? '&add_filler=1' : '?add_filler=1';
		}
		$this->_width   = $this->_width < JQGRID_MAXWIDTH ? JQGRID_MAXWIDTH : $this->_width;
        $this->colNames = $cols;
        $this->colModel = $model;
    }

    //abstract public function totalRecords();
}
?>