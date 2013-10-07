<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class AmazonXmlParser extends XmlParser {

    private $in_image;
    private $asin;
    private $image_url;
    private $formatted_price;
    private $cart_id;
    private $hmac;
    private $purchase_url;
    private $in_error;
    private $error_message;

    public function __get($var){ return $this->{$var}; }

    public function startElement($parser, $name, $attributes) {
        $name = strtolower($name);
        switch ($name) {
            case 'errors':
                $this->error_message = array();
                $this->in_error = TRUE;
                break;
            case 'smallimage':
                $this->in_image = TRUE;
                break;
        }
        $this->tagname = $name;
    }

    public function endElement($parser, $name) {
        $name = strtolower($name);
        if ($name == 'errors') {
            $this->in_error = FALSE;
        }
        if ($name == 'smallimage') {
            $this->in_image = FALSE;
        }
        $this->tagname = '';
    }

    public function characterData($parser, $data) {
        if ($this->in_error && $this->tagname == 'message') {
            array_push($this->error_message, $data);
        }
        if ($this->tagname == 'asin') {
            $this->asin = $data;
        }
        if ($this->in_image && $this->tagname == 'url') {
            $this->image_url = $data;
        }
        if ($this->tagname == 'formatted_price') {
            $this->formatted_price = $data;
        }
        if ($this->tagname == 'cart_id') {
            $this->cart_id = $data;
        }
        if ($this->tagname == 'hmac') {
            $this->hmac = $data;
        }
    }
}
?>