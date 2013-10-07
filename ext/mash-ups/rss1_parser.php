<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class Rss1Parser extends XmlParser {

    private $rss;
    private $current_item;
    private $image;
    private $in_items;
    private $in_image;

    public function __construct() {
        parent::__construct();
        $this->rss = new Rss1('temp', 'temp', 'temp', 'temp');
        $this->image =
        $this->current_item = array();
        $this->in_items = $this->in_image = FALSE;
    }

    public function __get($var){
        return ($var == 'items') ? $this->items : NULL;
    }

    public function startElement($parser, $name, $attributes) {
        $name = strtolower($name);
        if ($name == 'channel') {
            foreach ($attributes as $key => $val) {
                $this->rss[$key] = $val;
            }
        } elseif ($name == 'image') {
            $this->in_image = TRUE;
        } elseif ($name == 'items') {
            $this->in_items = TRUE;
        } elseif ($this->in_items && $name == 'item') {
            $this->current_item = array();
            $this->current_item['rdf:about'] = $attributes['rdf:about'];
        }
        $this->tagname = $name;
    }

    public function endElement($parser, $name) {
        $name = strtolower($name);
        if ($name == 'items') {
            $this->in_items = FALSE;
            unset($this->current_item);
        } elseif ($name == 'image') {
            $this->rss->addImage($this->image['title'], $this->image['url']);
            unset($this->image);
        } elseif ($name == 'item') {
            $this->rss->addItem(
                $this->current_item['title'],
                $this->current_item['link'],
                $this->current_item['description'],
                $this->current_item['rdf:about']
            );
            $this->current_item = array();
        }
        $this->tagname = '';
    }

    public function characterData($parser, $data) {
        if ($this->in_items) {
            if (in_array($this->tagname, array('title', 'link', 'description'))) {
                $this->current_item[$this->tagname] = $data;
            }
        } elseif ($this->in_image) {
            if (in_array($this->tagname, array('title', 'url'))) {
                $this->image[$this->tagname] = $data;
            }
        } else {
            $this->rss->{$this->tagname} = $data;
        }
    }

    public function parse($xml='') {
        parent::parse($xml);
        return $this->rss;
    }
}
?>