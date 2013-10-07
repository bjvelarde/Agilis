<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * RSS 2.0 parser
 */
class Rss2Parser extends XmlParser {
    /**
     * @var Rss2
     */
    private $rss;
    /**
     * @var Rss2Item
     */
    private $current_item;
    /**
     * @var array
     */
    private $image;
    /**#@+
     * @var bool
     */
    private $in_item;
    private $in_image;
    /**#@-*/
    /*
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->rss = new Rss2('temp', 'temp', 'temp');
        $this->image = array();
        $this->current_item = NULL;
        $this->in_item = $this->in_image = FALSE;
    }
    /*
     * Start tag parser callback
     */
    public function startElement($parser, $name, $attributes) {
        $name = strtolower($name);
        if ($name == 'image') {
            $this->in_image = TRUE;
        } elseif ($name == 'item') {
            $this->in_item = TRUE;
            $this->current_item = new Rss2Item('temp', 'temp', 'temp');
        }
        $this->tagname = $name;
    }
    /*
     * End tag parser callback
     */
    public function endElement($parser, $name) {
        $name = strtolower($name);
        if ($name == 'image') {
            $this->rss->addImage($this->image['title'], $this->image['url'], $this->image['link']);
            unset($this->image);
            $this->in_image = FALSE;
        } elseif ($name == 'item') {
            $this->rss->addItem($this->current_item);
            $this->current_item = NULL;
            $this->in_item = FALSE;
        }
        $this->tagname = '';
    }
    /*
     * Character data parser callback
     */
    public function characterData($parser, $data) {
        if ($this->in_item) {
            $this->current_item->{$this->tagname} = $data;
        } elseif ($this->in_image) {
            $this->image[$this->tagname] = $data;
        } else {
            $this->rss->{$this->tagname} = $data;
        }
    }
    /**
     * Parse the XML
     *
     * @param string $xml Optional XML from external source
     * @return Rss2
     */
    public function parse($xml='') {
        parent::parse($xml);
        return $this->rss;
    }
}
?>