<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class PaypalItem extends FixedStruct {

    public function __construct(Params $data=NULL) {
        parent::__construct(
            'item_name', 
            'item_number', 
            'amount', 
            'shipping', 
            'shipping2', 
            'handling', 
            'on0', 
            'os0', 
            'on1', 
            'os1'
        );
        if ($data) {
            foreach($data as $k => $v) {
                $this->{$v} = $k;
            }
        }
    }
}
?>