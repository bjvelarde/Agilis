<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class PaypalRequest extends HttpRequest {

    private $data;
    private $items;

    public function __construct($currency_code='USD') {
        $this->data = array(
            'cmd'           => '_cart',
            'upload'        => 1,
            'currency_code' => $currency_code
        );
        $this->items = array();
        parent::__construct('www.paypal.com', '/cgi-bin/webscr');
    }

    public function addItem(PaypalItem $item) {
        $this->items[] = $item;
    }

    private function getContent() {
        if ($this->items) {
            for ($i = 0; $i < $this->items; $i++) {
                $item = $this->items[$i];
                $index = $i ? $i : '0';
                foreach ($item as $k => $v) {
                    $this->data[$k . "_{$index}"] = $v;
                }
            }
            $this->buildContent($this->data);
            return TRUE;
        }
        return FALSE;
    }

    public function send() {
        if ($this->getContent()) {
            return parent::send();
        }
        return NULL;
    }
}
?>