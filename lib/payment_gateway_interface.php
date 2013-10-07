<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

interface PaymentGatewayInterface {

    public function buildParams(array $payment);
    public function getPostUrl();
    
}