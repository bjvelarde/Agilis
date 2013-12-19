<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

class TableField extends DynaStruct {

    public function __construct($type) {
        $defaults = array(
            'type'           => $type,
            'auto_increment' => FALSE,
            'primary_key'    => FALSE,
            'key'            => FALSE,
            'foreign_key'    => FALSE,
            'required'       => FALSE,
            'immutable'      => FALSE,
            'unique'         => FALSE,
            'title'          => FALSE,
            'hidden'         => FALSE,
            'mimes'          => NULL,
            'enum'           => NULL,
            'set'            => NULL,
            'encrypt_with'   => NULL,
            'default'        => NULL
        );
        if ($type == 'audio' || $type == 'video' || $type == 'image') {
            $defaults['type']  = 'string';
            $defaults['mimes'] = array("{$type}/*");
        } elseif ($type == 'file') {
            $defaults['type']  = 'string';
            $defaults['mimes'] = array('text/plain');
        }
        if (in_array($type, array('id', 'createstamp', 'timestamp'))) {
            $defaults['immutable'] = $defaults['required'] = TRUE;
            if ($type == 'id') {
                $defaults['primary_key'] = TRUE;
                $defaults['auto_increment'] = TRUE;
                $defaults['type']           = 'integer';
            } elseif ($type == 'createstamp') {
                $defaults['type'] = 'datetime';
            }
        }
        return parent::__construct($defaults);
    }

    public function __call($method, $args) {
        if (in_array($method, array('auto_increment', 'primary_key', 'key', 'foreign_key', 'required', 'title', 'unique', 'hidden'))) {
            $args = array((empty($args) || $args[0] === TRUE));
            if ($method == 'foreign_key' && $args[0] === TRUE && !$this->is_key) {
                $this->key();
            }
        }
        $return = parent::__call($method, $args);
        if (($method == 'auto_increment' || $method == 'primary_key') && $args[0] === TRUE) {
            $this->required = TRUE;
            if ($method == 'auto_increment') {
                $this->immutable = TRUE;
            }
        }
        return $return;
    }

    public function __get($var) {
        if (strpos($var, 'is_') === 0) {
            $types = array('boolean', 'integer', 'float', 'double', 'string', 'text', 'rich_text', 'date', 'time', 'datetime', 'timestamp', 'email', 'ip', 'url');
            $type  = substr($var, 3);
            if (in_array($type, $types)) {
                return ($this->type == $type);
            } elseif ($type == 'createstamp') {
                return ($this->type == 'datetime' && $this->immutable);
            } elseif ($type == 'id') {
                return ($this->type == 'integer' && $this->auto_increment);
            } elseif ($type == 'numeric') {
                return in_array($this->type, array('integer', 'float', 'double'));
            } elseif ($type == 'enum') {
                return ($this->enum !== NULL);
            } elseif ($type == 'set') {
                return ($this->set !== NULL);
            } elseif ($type == 'image') {
                if ($this->mimes) {
                    foreach ($this->mimes as $mime) {
                        if (substr($mime, 0, 6) != 'image/') {
                            return FALSE;
                        }
                    }
                    return TRUE;
                }
                return FALSE;
            } elseif ($type == 'color') {                
                return ($this->pattern === '/#[A-F0-9]{6}/i');                   
            } else {
                return parent::__get($var);
            }
        }
        return parent::__get($var);
    }

    public function __set($var, $val) {
        if ($var == 'type') {
            $this->type = $val;
            switch ($val) {
                case 'id':
                    $this->auto_increment =
                    $this->required =
                    $this->primary_key =
                    $this->immutable = TRUE;
                    $this->type = 'integer';
                    break;
                case 'audio':
                case 'video':
                case 'image':
                    $this->type = 'string';
                    $this->mimes = array("{$val}/*");
                    break;
                case 'file':
                    $this->type = 'string';
                    $this->mimes = array('text/plain');
                    break;
                case 'color':
                    $this->type = 'string';
                    $this->pattern = '/#[A-F0-9]{6}/i';
                    break;
                case 'createstamp':
                    $this->required =
                    $this->immutable = TRUE;
                    $this->type = 'datetime';
                    break;
                case 'timestamp':
                    $this->required =
                    $this->immutable = TRUE;
                    break;
            }
        } else {
            parent::__set($var, $val);

        }
    }

    public function export() {
        $str = "Table::field('{$this->name}')->type(";
        if ($this->is_id) {
            $str .= "'id')";
        } elseif ($this->is_createstamp) {
            $str .= "'createstamp')";
        } else {
            $str .= "'{$this->type}')";
            if (!$this->is_timestamp) {
                if ($this->is_primary_key) {
                    $str .= "->primary_key()";
                } elseif ($this->is_unique) {
                    $str .= "->unique()";
                } elseif ($this->is_foreign_key) {
                    $str .= "->foreign_key()";
                } elseif ($this->is_key) {
                    $str .= "->key()";
                }
				if ($this->is_required && !$this->is_primary_key && !$this->is_foreign_key) {
                    $str .= "->required()";
                }

                if ($this->default) {
                    $default = is_string($this->default) ? "'{$this->default}'" : $this->default;
                    $str .= "->default($default)";
                }
                if ($this->is_immutable) {
                    $str .= "->immutable()";
                }
                if ($this->encrypt_with) {
                    $str .= "->encrypt_with('{$this->encrypt_with}')";
                }
                if ($this->pattern) {
                    $str .= "->pattern('{$this->pattern}')";
                }
                if ($this->is_title) {
                    $str .= "->title()";
                }
                if ($this->is_hidden) {
                    $str .= "->hidden()";
                }
                if ($this->minlen) {
                    $str .= "->minlen({$this->minlen})";
                }
                if ($this->maxlen) {
                    $str .= "->maxlen({$this->maxlen})";
                }
                if ($this->minvalue) {
                    $str .= "->minvalue({$this->minvalue})";
                }
                if ($this->maxvalue) {
                    $str .= "->maxvalue({$this->maxvalue})";
                }
                if ($this->enum || $this->set || $this->mimes) {
                    $list_type = $this->enum ? 'enum' : ($this->set ? 'set' : 'mimes');
                    $items = array();
                    foreach ($this[$list_type] as $item) {
                        $items[] = "'$item'";
                    }
                    $str .= "->{$list_type}(array(" . implode(', ', $items) . "))";
                }
            }
        }
        return $str;
    }

    public function lookup($mclass, $where=NULL) {
        if ($this->is_foreign_key) {
            $model = $mclass::getAssociateClass(substr($this->name, 0, -3)); //String::substr($this->name, 0, -3)->camelize()->to_s;
            $table = $model::getTable();
            $data  = $model::select(
                array($table->_id_key, $table->_title_key),
                $where,
                array('order_by' => array($table->_title_key => 'asc'))
            );
            $result = array();
            foreach ($data as $item) {
                $result[$item[$table->_id_key]] = $item[$table->_title_key];
            }
            return $result;
        }
        return NULL;
    }

    public function validate($value) {
        $status = TRUE;
        $error  = '';
        if (!empty($value)) {
            if ($this->type == 'email') {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $error = "$value is not a valid e-mail address";
                }
            } elseif ($this->type == 'ip') {
                if (!filter_var($value, FILTER_VALIDATE_IP)) {
                    $error = "$value is not a valid I.P. address";
                }
            } elseif ($this->type == 'url') {
                if (!filter_var($value, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                    $error = "$value is not a valid URL";
                }
            } elseif ($this->pattern && !preg_match($this->pattern, $value)) {
                $error = "$value did not match the required pattern for $this->name";
            } elseif ($this->enum && !$this->validateEnumValue($value)) {
                $error = "$value is not accepted for $this->name. accepted values are: (" . implode(', ', $this->enum) . ")";
            } elseif ($this->set && !$this->validateSetValue($value)) {
                $error = "$value is not accepted for $this->name. accepted values are: (" . implode(', ', $this->set) . ")";
            } elseif (!$this->validateMaxValue($value)) {
                $error = "$value execeeds the maximum value of $this->maxvalue for $this->name";
            } elseif (!$this->validateMinValue($value)) {
                $error = "$value is below the mininum value of $this->minvalue for $this->name";
            } else {
                if (in_array($this->type, array('integer', 'float', 'double'))) {
                    if (!is_numeric($value)) {
                        $error = "$this->name requires a numeric value";
                    }
                }
            }
        } else {
            if ($this->is_required && empty($this->default)) {
                $error  = "$this->name is required";
            }
        }
        $status = empty($error);
        return array($status, $error);
    }

    private function validateEnumValue($value) {
        if ($this->enum) {
            return in_array($value, $this->enum);
        }
        return TRUE;
    }

    private function validateSetValue($value) {
        if ($this->set) {
            return in_array($value, $this->set);
        }
        return TRUE;
    }

    private function validateMaxLen($value) {
        if ($this->maxlen) {
            if (in_array($this->type, array('string', 'text', 'rich_text'))) {
                return (strlen($value) <= $this->maxlen);
            }
        }
        return TRUE;
    }

    private function validateMaxValue($value) {
        if ($this->maxvalue) {
            if (in_array($this->type, array('integer', 'float', 'double', 'date', 'datetime', 'timestamp'))) {
                if (in_array($this->type, array('date', 'datetime', 'timestamp'))) {
                    $val    = strtotime($value);
                    $maxval = strtotime($this->maxvalue);
                } else {
                    $val    = $value;
                    $maxval = $this->maxvalue;
                }
                return ($val <= $maxval);
            }
        }
        return TRUE;
    }

    private function validateMinLen($value) {
        if ($this->minlen) {
            if (in_array($this->type, array('string', 'text', 'rich_text'))) {
                return (strlen($value) >= $this->minlen);
            }
        }
        return TRUE;
    }

    private function validateMinValue($value) {
        if ($this->minvalue) {
            if (in_array($this->type, array('integer', 'float', 'double', 'date', 'datetime', 'timestamp'))) {
                if (in_array($this->type, array('date', 'datetime', 'timestamp'))) {
                    $val    = strtotime($value);
                    $minval = strtotime($this->minvalue);
                } else {
                    $val    = $value;
                    $minval = $this->minvalue;
                }
                return ($val >= $minval);
            }
        }
        return TRUE;
    }

    /*private function getColorPattern() {
        $colors = array(
            'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black',
            'blanchedalmond', 'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse',
            'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan', 'darkblue',
            'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgreen', 'darkkhaki', 'darkmagenta',
            'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon', 'darkseagreen',
            'darkslateblue', 'darkslategray', 'darkturquoise', 'darkviolet', 'deeppink', 'deepskyblue',
            'dimgray', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen', 'fuchsia', 'gainsboro',
            'ghostwhite', 'gold', 'goldenrod', 'gray', 'green', 'greenyellow', 'honeydew', 'hotpink',
            'indianred', 'indigo', 'ivory', 'khaki', 'lavender', 'lavenderblush', 'lawngreen',
            'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan', 'lightgoldenrodyellow',
            'lightgray', 'lightgreen', 'lightpink', 'lightsalmon', 'lightseagreen', 'lightskyblue',
            'lightslategray', 'lightsteelblue', 'lightyellow', 'lime', 'limegreen', 'linen', 'magenta',
            'maroon', 'mediumaquamarine', 'mediumblue', 'mediumorchid', 'mediumpurple', 'mediumseagreen',
            'mediumslateblue', 'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'midnightblue',
            'mintcream', 'mistyrose', 'moccasin', 'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab',
            'orange', 'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise', 'palevioletred',
            'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple', 'red', 'rosybrown',
            'royalblue', 'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell', 'sienna', 'silver',
            'skyblue', 'slateblue', 'slategray', 'snow', 'springgreen', 'steelblue', 'tan', 'teal', 'thistle',
            'tomato', 'turquoise', 'violet', 'wheat', 'white', 'whitesmoke', 'yellow', 'yellowgreen'
        );
        return implode('|', $colors);
    }*/
}
?>