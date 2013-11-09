<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

Conf::ifNotDefined('CACHE_TTL', 86400);

final class Table extends DynaStruct {

    const CACHE_DIR = 'cache/models/';
    const DUMP_DIR  = 'db/schema/';

    private static $registry = array();
    private static $schema = NULL;

    private $_name;
    private $_db;
    private $_conn;
    private $_model;
    private $_locked;
    private $_polymorphic;

    private $_title_key;
    private $_primary_keys;
    private $_unique_keys; // for combos only
    private $_id_key;
    private $_createstamp_key;
    private $_timestamp_key;

    public function __construct($table, $conn='master') {
        //$this->_db    = DataSourceManager::connect($conn, Conf::get('CURRENT_ENV'));
        $this->_name  = $table;
        $this->_conn  = $conn;
        $this->connect();
        $this->_model = String::classify($this->_name)->to_s;
        $this->_primary_keys =
        $this->_unique_keys  = array(); // for combos only
        $this->_id_key          =
        $this->_createstamp_key =
        $this->_title_key       =
        $this->_timestamp_key   = NULL;
        $this->_locked = FALSE;
        $this->_polymorphic = FALSE;
    }

    public function __clone() { return self::getInstance($this->_model); }

    public function __get($var) {
        if (property_exists($this, $var)) {
            return $this->{$var};
        } elseif ($var == 'data_source_config') {
            return $this->_db->data_source_config;
        }
        return parent::__get($var);
    }

    public function __sleep() {
        return array(
            '_elements',
            '_name',
            '_conn',
            '_model',
            '_locked',
            '_title_key',
            '_primary_keys',
            '_unique_keys',
            '_id_key',
            '_createstamp_key',
            '_timestamp_key',
            '_polymorphic'
        );
    }

    public function __wakeup() {
        $this->connect();
    }

    private function connect() {
        $this->_db = DataSourceManager::connect($this->_conn, Conf::get('CURRENT_ENV'));
    }

    public function __set($var, $val) {
        if (!property_exists($this, $var)) {
            if ($val instanceof TableField) {
                $val->name = $var;
                parent::__set($var, $val);
                //if (($var == 'name' || $var == 'title') && !isset($this->_title_key)) {
                //    $this->_title_key = $var;
                //}
                if ($this->isLocked()) {
                    $this->unlock();
                }
            }
        }
    }

    public static function __callStatic($method, $args) {
        return self::getInstance($method);
    }

    public function __toString() { return $this->_name; }

    public function &add(TableField $field, $position='LAST', $ref='') {
        if (!$this->hasElement($field->name)) {
            $db_adapter = $this->getDbAdapter();
            $db_adapter->addColumn($this, $field, $position, $ref);
            $this[$field->name] = $field;
            $this->unlock();
        }
        return $this;
    }

    public function addUniqueKey($index_name, array $keys=array()) {
        $this->_unique_keys[$index_name] = $keys;
    }

    public function cache() {
        $env = Conf::get('CURRENT_ENV');
        $key = "{$env}-{$this->_model}";
        Cache::set($key, $this, Cache::ANY_STORAGE, CACHE_TTL, APP_ROOT . self::CACHE_DIR);
        self::$registry[$env][$this->_model] = $key;      
    }

    public function create($with_translations=FALSE) {
        $result = FALSE;
        $db_adapter = $this->getDbAdapter();
        if ($db_adapter->drop($this)) {
            $this->cache();
            $this->configure();
            $result = $db_adapter->create(self::getInstance($this->_model)); // get the reloaded instance (after configure) instead of 'this'
            if ($result && $with_translations) {
                $model = $this->_model;
                return $model::createTranslator();
            }
        }
        return $result;
    }

    public function drop($with_translations=FALSE) {
        $result = FALSE;
        $env = Conf::get('CURRENT_ENV');
        $cache = APP_ROOT . self::CACHE_DIR . md5("{$env}-{$this->_model}");
        if (file_exists($cache)) {
            unlink($cache);
            unset(self::$registry[$env][$this->_model]);
        }
        $model = $this->_model;
        $db_adapter = $this->getDbAdapter();
        $result = $db_adapter->drop($this);
        if ($result && $with_translations) {
            return $model::dropTranslator();
        }
        return $result;
    }

    public function dump($model=NULL) {
        $model = ($model instanceof $this->_model) ? $model : $this;
        return $this->getDbAdapter()->dump($model);
    }

    public function export() {
        $fields = $this->getElements();
        $field_exports = array();
        foreach ($fields as $field) {
            $field_exports[] = $field->export();
        }
        $name = $this->_conn == 'master' ? $this->_name : "{$this->_conn}#{$this->_name}";
        return "Table::open('{$name}')->fields(\n        "
               . implode(",\n        ", $field_exports) . "\n    )"
               . ($this->_polymorphic ? '->polymorphic()' : '');
    }

    public function polymorphic() {
        $this->_polymorphic = TRUE;
        $field_keys = array_keys($this->_elements);
        $new_keys = array();
        if (!$this->_id_key && !$this->hasElement('id')) {
            $this['id'] = self::field('id')->type('id');
            $new_keys[] = 'id';
        }
        if (!$this->hasElement('ref_id')) {
            $this['ref_id'] = self::field('ref_id')->type('integer')->key()->hidden();
            $new_keys[] = 'ref_id';
        }
        if (!$this->hasElement('ref_model')) {
            $this['ref_model'] = self::field('ref_model')->type('string')->key()->hidden();
            $new_keys[] = 'ref_model';
        }
        $field_keys = array_merge($new_keys, $field_keys);
        $elements = array();
        foreach ($field_keys as $fk) {
            $elements[$fk] = $this[$fk];
        }
        $this->_elements = $elements;
        $this->initKeys();
        return $this;
    }

    public static function exportRegistry() {
        $content = "<?php\nuse Agilis\Table;\n\nreturn array(\n    ";
        $envs = array();
        $current_env = Conf::get('CURRENT_ENV');
        if (isset(self::$registry[$current_env])) {
            $tables = array();
            ksort(self::$registry[$current_env]);
            foreach (self::$registry[$current_env] as $m => $key) {
                $table = self::getCachedTable($key);
                if (!$table) {
                    throw new TableException('Missing schema cache for key: ' . $key);
                }
                $model = $table->getModel();                
                if (!$table->isLocked()) {
                    $table->unlock();
                    $table->configure(); // modify cache and lock the schema
                    $table = self::getInstance($model); //reload from cache before export
                }
                $tables[] = "'{$model}' => " . $table->export();
            }
            $content .= implode(",\n    ", $tables) . "\n);\n?>";
            file_put_contents(APP_ROOT . self::DUMP_DIR . 'schema-def.php', $content);
        } else {
            echo "Registry is Empty!\n";
        }
    }

    public static function field($name) {
        $field = new TableField('string');
        $field->name = $name;
        return $field;
    }

    public function &fields() {
        $args = func_get_args();
        if (!empty($args)) {
            foreach ($args as $f) {
                if ($f instanceof TableField) {
                    $this[$f->name] = $f;
                }
            }
            $this->initKeys();
        }
        return $this;
    }

    public function resetFieldAttributes() {
        foreach ($this as $field) {
            if ($field->is_id || $field->is_foreign_key || $field->is_createstamp || $field->is_timestamp) {
                continue;
            }
            $field = $field->required(FALSE)
                           ->encrypt_with(NULL)
                           ->title(FALSE)
                           ->hidden(FALSE)
                           ->unique(FALSE);
            $this[$field->name] = $field;
        }
    }

    public function getDbAdapter() {
        $adapter = __NAMESPACE__ . "\\" . str_replace('Conn', 'Adapter', $this->_db->_class);
        if (class_exists($adapter)) {
            return $adapter::getInstance($this->_db->engine);
        }
        throw new TableException("Can't get associated DbAdapter: $adapter");
    }
    /**
     * Retrieves the field names
     *
     * @return array
     */
    public function getFieldNames() {
        return array_keys($this->_elements);
    }

    public function getModel() { return $this->_model; }

    public function isLocked() { return $this->_locked; }

    public function configure() {
        $model = $this->_model;
        $model::configure();
    }

    public function lock() {
        if (!$this->_locked) {
            $this->_locked = TRUE;
            $this->cache();
        }
    }

    public static function open($table) {
        $conn = 'master';
        if (strstr($table, '#')) {
            list($conn, $table) = explode('#', $table);
        }
        $class = String::classify($table)->str;
        if (($t = self::getInstance($class)) instanceof Table) {
            return $t;
        }
        return new self($table, $conn);
    }

    public function &remove($field) {
        $db_adapter = $this->getDbAdapter();
        $db_adapter->removeColumn($this, $field);
        unset($this[$field]);
        $this->unlock();
        return $this;
    }

    public function setTitleKey($field) {
        $this->_title_key = $field;
        $this[$field]->title = TRUE;
		foreach ($this as $f) {
		    if ($f->name != $field) {
			    $this[$f->name]->title = FALSE;
			}
		}
        if (!isset($this['slug'])) {
            $this['slug'] = self::field('slug')->type('string')->key()->hidden();
        }
    }

    public function total($where=array()) {
        $db_adapter = $this->getDbAdapter();
        return $db_adapter->total($this, $where);
    }

    public function unlock() {
        if ($this->_locked) {
            $this->_locked = FALSE;
            $this->resetFieldAttributes();
            $this->cache();
        }
    }

    private static function getCachedTable($key) {
        $cache = Cache::get($key);
        if ($cache) {
            list($env, $model) = explode('-', $key);
            self::$registry[$env][$model] = $key;
            return $cache;
        } else {
            return self::getFromSchema($key);
        }
    }

    private static function getFromSchema($key) {
        $master_schema = self::readMasterSchemaFile();
        if ($master_schema) {
            list($env, $model) = explode('-', $key);
            if (isset($master_schema[$model]))  {
                self::$registry[$env][$model] = $key;
                $table = $master_schema[$model];
                $table->cache();
                //unset($master_schema[$model]); //destroy once pulled
                return $table;
            }
        }
        return NULL;
    }

    public static function preMigration() {
        $master_schema = self::readMasterSchemaFile();
        if ($master_schema) {
            $current_env = Conf::get('CURRENT_ENV');
            foreach ($master_schema as $model => $table) {
                self::$registry[$current_env][$model] = "{$current_env}-{$model}";
                $table->unlock();
            }
        }
    }

    private static function readMasterSchemaFile() {
        if (!self::$schema) {   
            $master_def = APP_ROOT . self::DUMP_DIR . 'schema-def.php';
            if (file_exists($master_def)) {
                self::$schema = include_once($master_def);
            }
        }    
        return self::$schema;
    }

    protected function initKeys() {
        foreach ($this as $field) {
            if ($field->is_auto_increment || $field->is_primary_key) {
                if (!in_array($field->name, $this->_primary_keys)) {
                    $this->_primary_keys[] = $field->name;
                }
                if ($field->is_auto_increment) {
                    $this->_id_key = $field->name;
                }
            } elseif ($field->is_createstamp && !$this->_createstamp_key) {
                $this->_createstamp_key = $field->name;
            } elseif ($field->is_timestamp && !$this->_timestamp_key) {
                $this->_timestamp_key = $field->name;
            } elseif ($field->is_title && !$this->_title_key) {
                $this->_title_key = $field->name;
            }
        }
        if (!$this->_title_key) {
            if ($this->hasElement('title')) {
                $this->_title_key = 'title';
                $this['title']->title = TRUE;
            } elseif ($this->hasElement('name')) {
                $this->_title_key = 'name';
                $this['name']->title = TRUE;
            }
        }
        if ($this->_title_key && !$this->slug) {
            $this['slug'] = self::field('slug')->type('string')->key()->hidden();
        }
        if (!$this->_id_key && count($this->_primary_keys) == 1) {
            $this->_id_key = $this->_primary_keys[0];
        }
        $this->_unique_keys = array();
    }

    public static function getInstance($class) {
        $master_def = APP_ROOT . self::DUMP_DIR . 'schema-def.php';
        $current_env = Conf::get('CURRENT_ENV');
        //$master_schema = NULL;
        if (empty(self::$schema) && file_exists($master_def)) {
            self::$schema = include_once($master_def);
            if (is_array(self::$schema)) {
                foreach (self::$schema as $model => $table) {
                    $table->lock();
                }
                //unset($master_schema); //destroy once loaded
            }
        }
        $key = "{$current_env}-{$class}";
        return self::getCachedTable($key);
    }

}

class TableException extends \Exception {}
?>