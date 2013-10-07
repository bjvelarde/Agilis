<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

abstract class XmlParser {
    
    protected $parser;
    protected $tagname;
    
    public function __construct($ns_support=FALSE) {   
        $this->parser = $ns_support ? $this->create_ns() : $this->create();
        $this->set_object(&$this);
        $this->set_element_handler('startElement', 'endElement');
        $this->set_character_data_handler('characterData');
    }
    
    public function __call($method, $args) {  
        if ($method != 'parse') {
            $funclist = array(                
                'xml_parser_create_ns',
                'xml_parser_create'
            );        
            if (function_exists('xml_' . $method)) {
                $func = 'xml_' . $method; 
                $funclist[] = 'xml_error_string';
            } elseif (function_exists('xml_parser_' . $method)) {
                $func = 'xml_parser_' . $method;            
            }
            if ($func) {
                if (!in_array($func, $funclist)) {
                    array_unshift($args, $this->parser);
                }
                return call_user_func_array($func, $args);
            } 
        }               
    }
    
    public function __set($var, $val) {
        switch ($var) {
            case 'case_folding':
                $val = ($val == 1) ? 1 : 0; 
                return $this->set_option(XML_OPTION_CASE_FOLDING, $val);
            case 'skip_tagstart': 
                if (is_numeric($val)) {                
                    return $this->set_option(XML_OPTION_SKIP_TAGSTART, $val);
                }
                break;
            case 'skip_white': 
                $val = ($val == 1) ? 1 : 0; 
                return $this->set_option(XML_OPTION_SKIP_WHITE, $val);
            case 'target_encoding': 
                $supported = array('ISO-8859-1', 'US-ASCII', 'UTF-8');
                $val = in_array($val, $supported) ? $val : 'UTF-8';
                return $this->set_option(XML_OPTION_TARGET_ENCODING, $val);
        }
    }    
   
    public function __destruct() {
        $this->free();
    }
    
    public function parse($xmldata, $mode='STRING') {
	    $xmldata = ($mode == 'FILE') ? file_get_contents($xmldata) : $xmldata; 
		if (!xml_parse($this->parser, $xmldata)) {
			throw new XmlParserException($this);
		}
    }
    
    abstract public function startElement($parser, $name, $attributes);
    
    abstract public function endElement($parser, $name);
    
    abstract public function characterData($parser, $data);
}

class XmlParserException extends \Exception {
    
    public function __construct(XmlParser $parser) {     
        parent::__construct(
            'ERROR: ' . $parser->error_string($parser->get_error_code()) . ' | '.             
            'LINE: ' . $parser->get_current_line_number() . ' | ' .
            'COLUMN: ' . $parser->get_current_column_number()
        );
    }        
}
?>