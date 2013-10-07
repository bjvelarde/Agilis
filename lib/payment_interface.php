<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

interface PaymentInterface {

    public function getMerchantId();
    public function getAmount();
    public function getRefCode();
    public function getCurrency();
    
}