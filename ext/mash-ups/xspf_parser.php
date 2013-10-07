<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * XSPF Parser
 */
class XspfParser extends XmlParser {

    private $tracks;
    private $current_track;
    private $in_track;

    public function __construct() {
        parent::__construct();
        $this->tracks = array();
        $this->current_track = array();
    }
    /*
     * allow read-only access to tracks
     */
    public function __get($var){
        return ($var == 'tracks') ? $this->tracks : NULL;
    }
    /**#@+
     * Callbacks
     */
    public function startElement($parser, $name, $attributes) {
        if ($name == 'track') {
            $this->in_track = TRUE;
            $this->current_track = array();
        }
        $this->tagname = $name;
    }

    public function endElement($parser, $name) {
        if ($this->tagname == 'track') {
            $this->in_track = FALSE;
            array_push($this->tracks, $this->current_track);
            unset($this->current_track);
        }
        $this->tagname = '';
    }

    public function characterData($parser, $data) {
        if ($this->in_track) {
            $this->current_track[$this->tagname] = $data;
        }
    }
    /**#@-*/
}
?>