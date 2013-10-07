<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

class Paginator extends Template {

    public static $PER_PAGE = 25;
    
    private $per_page;
    private $inner_window;
    private $outer_window;
    
    private $page;
    private $prev;
    private $next;
    private $gap;
    private $separator;
    
    public function __construct($params=array()) {
        parent::__construct('paginator');
        if (isset($params['total'])) {
            $total      = $params['total'];            
            $per_page   = isset($params['per_page']) ? $params['per_page'] : self::$PER_PAGE;
            $numpages   = intval($total / $per_page) + (($total % $per_page)? 1: 0);        
            $this->page = isset($params['page']) ? $params['page'] : 1;
            $this->prev = $this->page - 1;
            $this->next = ($this->page == $numpages) ? 0 : ($this->page + 1);          
            $this->gap  = isset($params['gap']) ? $params['gap'] : '...';
            $this->key  = isset($params['key']) ? "{$params['key']}/" : '';
            $this->separator = isset($params['separator']) ? $params['separator'] : '&nbsp;';
            $this->action    = isset($params['action']) ? $params['action'] : $_SERVER['PATH_INFO'];
            $inner_window    = isset($params['inner_window']) ? $params['inner_window'] : 4;
            $outer_window    = isset($params['outer_window']) ? $params['outer_window'] : 1;              
            $pages = array();
            for ($i = 1; $i <= $numpages; $i++) {
                if ((($i >= $this->page - $inner_window) && ($i <= $this->page + $inner_window)) || 
                    ($i <= $outer_window) || ($i > $numpages - $outer_window)) {
                    $pages[] = $i;
                } elseif ($i > $outer_window && $i < $this->page - $inner_window && !in_array('left_gap', $pages)) {
                    $pages[] = 'left_gap';
                } elseif ($i < $numpages - $outer_window && $i > $this->page + $inner_window && !in_array('right_gap', $pages)) {
                    $pages[] = 'right_gap';
                }
            }
            $this->pages = $pages;          
        }    
    }
    
    public function __toString() { return ($this->pages) ? parent::__toString() : ''; }    
    
    public static function paginateModel($model, $params=array()) {
        $where   = isset($params['condition']) ? $params['condition'] : array();
        $options = isset($params['options']) ? $params['options'] : array();
        $params['page']    = isset($params['page']) ? $params['page'] : 1;
        $options['limit']  = isset($params['per_page']) ? $params['per_page'] : self::$PER_PAGE;
        $options['offset'] = ($params['page'] - 1) * $options['limit']; //($options['limit'] - 1);        
        $params['action']  = isset($params['action']) ? $params['action'] : $_SERVER['PATH_INFO'];
        $params['total']   = $model::total($where);
        $params['key']     = 'page';
        $dataset = $model::find($where, $options);
        $dataset->addMetaData('paginate', $params);
        return $dataset;
    }
    
    public static function paginateCollection(ModelCollection $dataset, $params=array()) {
        $params['page'] = isset($params['page']) ? $params['page'] : 1;
        $limit  = isset($params['per_page']) ? $params['per_page'] : self::$PER_PAGE;
        $offset = ($params['page'] - 1) * $limit; //($limit - 1);        
        $params['action']  = isset($params['action']) ? $params['action'] : $_SERVER['PATH_INFO'];        
        $params['key']     = 'page';
        $params['total']   = $dataset->count();
        $dataset->addMetaData('paginate', $params);
        return $dataset->slice($offset, $limit);
    }        
   
    public static function paginate($params=array()) {
        $params = ($params instanceof ModelCollection) ? $params->getMetaData('paginate') : $params;
        return new Paginator($params);
    }
}
?>