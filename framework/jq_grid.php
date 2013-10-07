<?php
namespace Agilis;

Conf::ifNotDefined('JQGRID_PER_PAGE', 50);
Conf::ifNotDefined('JQGRID_MAXLIMIT', 1000);
Conf::ifNotDefined('JQGRID_IMGPATH', '/css/images'); //str_replace("\\", '/', PUBLIC_PATH . 'css/images'));

class JqGrid extends DynaStruct {

    private $_gridmodel;
    private $_gridname;
    private $_navgrid;
    private $_importdlg;
    private $_exportdlg;
    private $_importcsv;
    private $_export;
    private $_extrabuttons;
    private $_uploader;
    private $_formactions;
    private $_formoptions;
    private $_actiontitles;

    public function __construct(JqGridOptions $struct, Params $config=NULL) {
        $config = $config ? $config : new Params;
        $this->_extrabuttons =
        $this->_formactions  =
        $this->_actiontitles = array();
        $this->_formoptions  = array('edit' => array(), 'add' => array(), 'delete' => array(), 'search' => array(), 'view' => array());
        $this->_gridname     = Common::ifEmpty($config->gridname, 'datagrid');
        $this->_navgrid      = Common::ifEmpty($config->navgrid, 'navgrid');
        $this->_importdlg    = Common::ifEmpty($config->importdlg, 'gridimport');
        $this->_exportdlg    = Common::ifEmpty($config->exportdlg, 'gridexport');
        $this->_importcsv    = Common::ifEmpty($config->importcsv, FALSE);
        $this->_export       = Common::ifEmpty($config->export, FALSE);
        $this->_uploader     = isset($config->uploader) ? $config->uploader : NULL;
        $this->_gridmodel    = $struct;
        $totalrecs           = $config->total; //$this->_gridmodel->totalRecords();
        parent::__construct($this->_gridmodel->getElements());
        $this->datatype = Common::ifEmpty($config->datatype, 'json');
        $this->height   = Common::ifEmpty($config->height, 'auto');
        $this->altRows  = Common::ifEmpty($config->alt_rows, FALSE);
        $this->width    = Common::ifEmpty($config->width, $this->_gridmodel->_width);
        //$this->width         = (($this->width > 1700) ? 1700 : $this->width);
        //$this->autowidth     = FALSE;
        //$this->shrinkToFit   = $this->width;
        $this->gridview      = TRUE;
        //$this->multiselect   = TRUE;
        if ($totalrecs > JQGRID_PER_PAGE) {
            $this->rowNum = Common::ifEmpty($config->rowNum, JQGRID_PER_PAGE);
            if (JQGRID_PER_PAGE < JQGRID_MAXLIMIT) {
                if ($totalrecs > JQGRID_MAXLIMIT) {
                    $range = range(JQGRID_PER_PAGE, JQGRID_MAXLIMIT, JQGRID_PER_PAGE);
                } elseif ((JQGRID_PER_PAGE * 2) > $totalrecs) {
                    $range = array(JQGRID_PER_PAGE, $totalrecs);
                } else {
                    $range = range(JQGRID_PER_PAGE, $totalrecs, JQGRID_PER_PAGE);
                }
                $this->rowList = Common::ifEmpty($config->rowList, $range);
            }
        }
        $this->pager       = Common::ifEmpty($config->sortorder, '#' . $this->_navgrid);
        $this->sortorder   = Common::ifEmpty($config->sortorder, 'asc');
        $this->viewrecords = Common::ifEmpty($config->viewrecords, TRUE);
        $this->imgpath     = Common::ifEmpty($config->imgpath, JQGRID_IMGPATH);
    }

    public function __toString() {
        $js = JsEncoder::encode($this->_elements);
        $jqgrid = "\$('#{$this->_gridname}').jqGrid($js).jqGrid('navGrid', '#{$this->_navgrid}', "
                . $this->getNavGridParams() . $this->getFormOptions() . ')';
        if ($this->_extrabuttons) {
            foreach ($this->_extrabuttons as $button) {
                $jqgrid .= ".jqGrid('navButtonAdd', '#{$this->_navgrid}', $button)";
            }
        }
        if ($this->_allow_add || $this->_allow_edit) {
            $jqgrid .= !$this->_importcsv ? '' :
                      ".jqGrid('navButtonAdd', '#{$this->_navgrid}',{title:'Import CSV', "
                    . "caption:'', buttonicon:'ui-icon-arrowreturnthick-1-s', onClickButton: function(){ $('#{$this->_importdlg}').dialog('open'); return false; }, position:'last'})";
        }
        $jqgrid .= !$this->_export ? ';' :
                  ".jqGrid('navButtonAdd', '#{$this->_navgrid}',{title:'Export Data', "
                . "caption:'', buttonicon:'ui-icon-arrowreturnthick-1-n', onClickButton: function(){ $('#{$this->_exportdlg}').dialog('open'); return false; }, position:'last'});";
        return $jqgrid;
    }

    public function addNavButton(JqGridNavButton $button) {
        $this->_extrabuttons[] = $button;
    }

    public function addFormOptions($action, array $pair) {
        $this->_formoptions[$action] = isset($this->_formoptions[$action]) ? array_merge($this->_formoptions[$action], $pair) : $pair;
    }

    public function setFormActions() {
        $valid_actions = array('edit', 'add', 'del', 'search', 'view');
        $actions = func_get_args();
        $this->_formactions = array_intersect($actions, $valid_actions);
    }

    public function setActionTitles(array $cfg) { $this->_actiontitles = $cfg; }

    protected function getFormOptions() {
        $quoted = array('topinfo', 'bottominfo', 'addCaption', 'editCaption',
                        'bSubmit', 'bCancel', 'bClose', 'saveData', 'caption',
                        'bYes', 'bNo', 'bExit');
        $options = array();
        foreach ($this->_formoptions as $action => $pairs) {
            $o = array();
            foreach ($pairs as $k => $v) {
                $o[] = $k . ':' . (in_array($k, $quoted) ? "'{$v}'" : $v);
            }
            $options[] = '{' . implode(', ', $o) . '}';
        }
        return $options ? ',' . implode(', ', $options) : '';
    }

    protected function getFormActions() {
        $valid_actions = array('edit', 'add', 'del', 'search', 'view');
        $actions = array();        
        if (count($this->_formactions) > 0) {
            foreach ($valid_actions as $a) {
                $actions[] = $a . ':' . (in_array($a, $this->_formactions) ? 'true' : 'false');
            }
        }
        return $actions;
    }

    protected function getNavGridParams() {
        $params = $this->getFormActions();
        if (!empty($this->_actiontitles)) {
            foreach ($this->_actiontitles as $k => $v) {
                $params[] = "{$k}:'{$v}'";
            }
        }
        return '{' . implode(', ', $params) . '}';
    }
}
?>