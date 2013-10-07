<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

interface PayDollarDataFeedInterface {

    public function setStatus($status);
    public function setComment($comment);
    public function save();
    
}