<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

use \Spyc;

Conf::ifNotDefined('DEFAULT_LOCALE', 'en');
Conf::ifNotDefined('AUTO_PUBLISH', FALSE);

final class Translator {

    const DIR = 'config/locales/';
	const ENABLED = TRUE;

    private static $registry = array();
    private static $class_registry = array();
    private static $locales = array();

    private $model;
    private $fields;
    private $locked;

    public function __construct($model, $fields=array()) {
        $this->model  = $model;
        $this->fields = $fields;
    }

    public static function __callStatic($method, $args) {
        return self::getTranslator($method);
    }

    public function addField($field) { $this->fields[] = $field; }

    public function getFields() { return $this->fields; }

    public function cache() {
        Cache::set(
            "{$this->model}-translations",
            $this,
            Cache::ANY_STORAGE,
            CACHE_TTL,
            APP_ROOT . Table::CACHE_DIR
        );
    }

    public function getModel() { return $this->model; }

    public function isLocked() { return $this->locked; }

    public function lock() {
        $this->locked = TRUE;
        $this->cache();
    }

    public function unlock() {
        $this->locked = FALSE;
        $this->cache();
    }

    public static function configure() {
        $key      = 'locales';
        $yml_file = APP_ROOT . 'config/locales.yml';
        if (file_exists($yml_file)) {
            $config = \Spyc::YAMLLoad($yml_file);
            if (isset($config['locales']) && is_array($config['locales']) && count($config['locales']) > 0) {
                $keys = array_keys($config['locales']);
                if (isset($config['default'])) {
                    Conf::ifNotDefined('DEFAULT_LOCALE', $config['default']);
                } else {                    
                    Conf::ifNotDefined('DEFAULT_LOCALE', $keys[0]);
                }
                Conf::ifNotDefined('SUPPORTED_LOCALES', $keys);                
            }
            Conf::ifNotDefined('LOCALES', $config['locales']);
        }   
    }     

    public static function createTranslator() {
        $args  = func_get_args();
        $class = array_shift($args);
        $table = Table::$class();
        $idfield = $table[$table->_id_key];
        $fields = array();
        $fields[] = Table::field('id')->type('id');
        $fields[] = Table::field($class::foreignKey())->type($idfield->type)->required();
        $fields[] = Table::field('locale')->type('string')->key()->required();
        $translator = self::getTranslator($class);
        foreach ($translator->getFields() as $field) {
            $fields[] = $table[$field];
        }
        $fields[] = Table::field('publish')->type('boolean')->default(AUTO_PUBLISH);
        $fields[] = Table::field('created_at')->type('createstamp');
        $fields[] = Table::field('updated_at')->type('timestamp');
        $tclass = "{$class}Translation";
        if (!class_exists(__NAMESPACE__ . "\\Model\\{$tclass}")) {
            self::generateModel($class);
        }
        $table = Table::open($table->_conn . '#' . String::underscore($tclass)->pluralize()->to_s);
        $table = call_user_func_array(array($table, 'fields'), $fields);
        $table->create();
    }

    public static function dropTranslator($class) {
        $table  = Table::$class();
        $tclass = "{$class}Translation";
        return Table::open($table->_conn . '#' . String::underscore($tclass)->pluralize()->to_s)->drop();
    }

    public static function getCurrentLocale() {
        $locale_session_key = Conf::get('LOCALE_SESSION_KEY');
        $locale = Session::get($locale_session_key);
        return ($locale) ? $locale : DEFAULT_LOCALE;

    }

    public static function &getTranslator($class) {
        if (!isset(self::$class_registry[$class]) || !self::$class_registry[$class] instanceof Translator) {
            $cache = Cache::get("{$class}-translations");
            if (!($cache instanceof Translator)) {
                $cache = new self($class);
                $cache->cache();
            }
            self::$class_registry[$class] = $cache;
        }
        return self::$class_registry[$class];
    }

    public static function t($alias, $locale='') {
        $locale = (!$locale) ? self::getCurrentLocale() : $locale;
		$key = "locales-{$locale}";
        $cache = Cache::get($key);
        if (!$cache) {
            $yml_file = APP_ROOT . self::DIR . $locale . '.yml';
            if (file_exists($yml_file)) {
                $translator = Spyc::YAMLLoad($yml_file);
                Cache::set($key, $translator);
                return isset($translator[$alias]) ? $translator[$alias] : $alias;
            }
        }
        return isset($cache[$alias]) ? $cache[$alias] : $alias;
    }

    public static function translate(Model $model, $field, $locale='') {
        $locale = (!$locale) ? self::getCurrentLocale() : $locale;
		$translator = self::getTranslation($model, $locale);
        return ($translator instanceof Model && !empty($translator[$field]) && $translator['publish']) ? $translator[$field] : $model[$field];
    }

    public static function getTranslation(Model $model, $locale='') {
        $locale = (!$locale) ? self::getCurrentLocale() : $locale;
		$mclass = get_class($model);
		$class  = $mclass . 'Translation';
		$id = $model->getId();
		$key = $class . '-' . $id;
        if (!isset(self::$registry[$key][$locale])) {		
            $condition = array($mclass::foreignKey() => $id, 'locale' => $locale);
            $translator = $class::first($condition);
            self::$registry[$key][$locale] = ($translator instanceof Model) ? $translator : new $class($condition);            
        }
		return self::$registry[$key][$locale];
    }

    public static function localeStatus(Model $model, $locale) {
        $translator = self::getTranslation($model, $locale);
        if ($translator instanceof Model && $translator->_persisted) {
            if ($translator->publish) {
                return ($translator->getUpdateStamp() >= $model->getUpdateStamp()) ? 'updated' : 'outdated';
            } else {
                return 'unpublished';
            }
        } else {
            return 'none';
        }
    }

    public static function translates() {
        $args = func_get_args();
        $class = array_shift($args);
        $table = Table::$class();
        $translator = self::getTranslator($class);
        if ($translator->isLocked()) {
            $translator->unlock();
        }
        foreach ($args as $field) {
            if ($table->hasElement($field)) {
                $translator->addField($field);
            }
        }
        $translator->lock();
    }

    private static function generateModel($class) {
        $modelname = String::underscore($class)->to_s;
        $model_uri = APP_ROOT . Model::DIR . $modelname . '.php';
        if (!file_exists($model_uri)) {
            $model_uri = Model::generate($modelname);
        }
        $content = "<?php\nuse Agilis\Model;\n\nclass {$class}Translation extends Model {\n\n"
                  . "    protected static function config() {\n"
                  . "        //associations\n"
                  . "        self::belongs_to('{$modelname}');\n"
                  . "        //validations\n"
                  . "        self::validates_presence_of('locale');\n"
                  . "        //scope\n    }\n}\n?>";
        file_put_contents(APP_ROOT . Model::DIR . $modelname . '_translation.php', $content);
    }

}
?>