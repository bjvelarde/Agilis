<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

final class ModelRules {

    private static $registry = array();

    private $model;
    private $associates;
    private $scopes;
    private $callbacks;
    private $cb_status;
    private $owners;
    private $locked;

    public function __construct($model, $params=array()) {
        $this->model      = $model;
        $this->associates = isset($params['associates']) ? $params['associates'] : array();
        $this->scopes     = isset($params['scopes']) ? $params['scopes'] : array();
        $this->callbacks  = isset($params['callbacks']) ? $params['callbacks'] : array();
        $this->cb_status  = isset($params['cb_status']) ? $params['cb_status'] : array();
        $this->owners     = isset($params['owners']) ? $params['owners'] : array();
        $this->locked     = isset($params['locked']) ? $params['locked'] : FALSE;
        if (!isset(self::$registry[$model])) {
            self::$registry[$model] = "{$model}-rules";
        }
    }

    public static function __callStatic($method, $args) {
        return self::getRules($method);
    }

    public static function newInstance($model, $params=array()) {
        return new self($model, $params);
    }

    public function getModel() { return $this->model; }

    public function export() {
        if (empty($this->associates)) {
            $assoc_dump = 'array()';
        } else {
            $assoc_dump = array();
            foreach ($this->associates as $as => $assoc) {
                $assoc_dump[] = "'{$as}' => " . $assoc->export();
            }
            $assoc_dump = "array(\n            " . implode(",\n            ", $assoc_dump) . "\n        )";
        }
        $scope_dump = empty($this->scopes)    ? 'array()' : $this->cleanVarExport($this->scopes);
        $cb_dump    = empty($this->callbacks) ? 'array()' : $this->cleanVarExport($this->callbacks);
        $owner_dump = empty($this->owners)    ? 'array()' : $this->cleanVarExport($this->owners);
        return "Rules::newInstance('{$this->model}', array(\n        'associates' => {$assoc_dump},\n"
            . "        'scopes'     => {$scope_dump},\n        'callbacks'  => {$cb_dump},\n"
            . "        'owners'     => {$owner_dump}\n    ))";
    }

    private function cleanVarExport($var) {
        return str_replace(
            '=>', ' => ',
            str_replace(
                ',', ', ',
                str_replace(
                    ',)', ')',
                    preg_replace(
                        '/\d+=>/', '',
                        preg_replace('/\s+/', '', var_export($var, TRUE))
                    )
                )
            )
        );
    }

    public static function exportRegistry() {
        $content = "<?php\nuse Agilis\ModelRules as Rules;\nuse Agilis\ModelAssociate as Associate;\n\nreturn array(\n    ";
        $rules = array();
        ksort(self::$registry);
        foreach (self::$registry as $model => $key) {
            $rule = self::getCachedRules($key);
            if (!$rule) {
                throw new ModelRulesException('Missing rules cache for key: ' . $key);
            }
            $model = $rule->getModel();
            if (!$rule->isLocked()) {
                $rule = self::getRules($model); //reload from cache before export
            }
            $rules[] = "'{$model}' => " . $rule->export();
        }
        $content .= implode(",\n    ", $rules) . "\n);\n?>";
        file_put_contents(APP_ROOT . Table::DUMP_DIR . 'rules-def.php', $content);
    }
    /*-----------------------------------------------------------------------------
     * General rules methods
     */
    public function cache() {
        Cache::set(
            "{$this->model}-rules",
            $this,
            Cache::ANY_STORAGE,
            CACHE_TTL,
            APP_ROOT . Table::CACHE_DIR
        );
    }

    public static function getRules($class) {
        $master_file  = APP_ROOT . Table::DUMP_DIR . 'rules-def.php';
        $master_rules = NULL;
        if (empty(self::$registry) && file_exists($master_file)) {
            include_once($master_file);
            if ($master_rules) {
                foreach ($master_rules as $model => $rules) {
                    $rules->lock();
                }
                //unset($master_rules); //destroy once loaded
            }
        }
        $key = "{$class}-rules";
        return self::getCachedRules($key);
    }

    public static function preMigration() {
        $master_def = self::readMasterRulesFile();
        if ($master_def) {
            foreach ($master_def as $model => $rules) {
                self::$registry[$model] = "{$model}-rules";
                $rules->unlock();
            }
        }
    }

    private static function getCachedRules($key) {
        $cache = Cache::get($key);
        if ($cache) {
            list($model,) = explode('-', $key);
            self::$registry[$model] = $key;
            return $cache;
        } else {
            return self::getFromMasterFile($key);
        }
    }

    private static function getFromMasterFile($key) {
        $master_rules = self::readMasterRulesFile();
        list($model,) = explode('-', $key);
        if ($master_rules && isset($master_rules[$model])) {
            self::$registry[$model] = $key;
            $rules = $master_rules[$model];
            $rules->cache();
            //unset($master_rules[$model]);  //destroy once pulled
            return $rules;
        }
        return new self($model);
    }

    private static function readMasterRulesFile() {
        $master_def = APP_ROOT . Table::DUMP_DIR . 'rules-def.php';
        if (file_exists($master_def)) {
            return include($master_def);
        }
        return NULL;
    }

    public function isLocked() { return $this->locked; }

    public function lock() {
        $this->locked = TRUE;
        $this->cache();
    }

    public function unlock() {
        $this->locked = FALSE;
        $this->cache();
    }
    /*-----------------------------------------------------------------------------
     * Scopes methods
     */
    //public function getInternalScope($var) {
    //    $scope = $this->getScopeRule($var);
    //    $class = $this->model;
    //    return $class::all($scope);
    //}

    public function getScopeRule($scope) {
        return isset($this->scopes[$scope]) ? $this->scopes[$scope] : NULL;
    }

    public function getScopes() { return $this->scopes; }

    public function isScope($scope) {
        return (isset($this->scopes[$scope]));
    }

    public function scope($name, $criteria, $options=array()) {
        $this->scopes[$name] = array($criteria, $options);
    }
    /*-----------------------------------------------------------------------------
     * Callback methods
     */
    public function getCallback($method) {
        return $this->isCallbackDefined($method) ? $this->callbacks[$method] : NULL;
    }

    public function getCallbackStatus($id) {
        return (isset($this->cb_status[$id]) && $this->cb_status[$id] === FALSE) ? FALSE : TRUE;
    }

    public static function isCallback($method) {
        // unsupported yet: 'after_touch','around_save','around_create','around_update','around_destroy','after_commit', 'after_rollback'
        $callbacks = array(
            'after_initialize', 'after_find', 'before_validation', 'after_validation',
            'before_save', 'after_save', 'before_create', 'after_create',
            'before_update', 'after_update', 'before_destroy', 'after_destroy'
        );
        return in_array($method, $callbacks);
    }

    public function isCallbackDefined($method) {
        return (self::isCallback($method) && isset($this->callbacks[$method]));
    }

    public function setCallback($method, $args) {
        if (self::isCallback($method)) {
            $this->callbacks[$method] = $args[0];
            return TRUE;
        }
        return FALSE;
    }

    public function setCallbackStatus($id, $status) {
        $this->cb_status[$id] = $status;
        $this->cache();
    }
    /*-----------------------------------------------------------------------------
     * Association methods
     */
    public function belongs_to($model, array $options=array()) {
        $this->setAssociation('belongs_to', $model, $options);
    }

    public function getAssociateClass($var) {
        $association = $this->getAssociation($var);
        return ($association instanceof ModelAssociate) ? $association->getAssociateClass() : NULL;
    }

    public function has_and_belongs_to_many($model, array $options=array()) {
        $this->setAssociation('has_and_belongs_to_many', $model, $options);
    }

    public function has_many($model, array $options=array()) {
        $this->setAssociation('has_many', $model, $options);
    }

    public function has_one($model, array $options=array()) {
        $this->setAssociation('has_one', $model, $options);
    }

    public function getAssociates() { return $this->associates; }

    public function getAssociation($associate) {
        return isset($this->associates[$associate]) ? $this->associates[$associate] : NULL;
    }

    public function isAssociate($associate) {
        return isset($this->associates[$associate]);
    }

    public function makeAssociation($method, $args) {
        $class  = $this->model;
        $table =& $class::loadTable();
        if ($table instanceof Table) {
            $assoc_obj = new ModelAssociate;
            if (count($args) && is_array($args[0]) && !is_callable($args[0])) {
                $options     = $args[0];
                $dependent   = (isset($options['dependent'])) ? $options['dependent'] : NULL;
            }
            $dependent = isset($dependent) ? $dependent : 'nullify';
            if (preg_match('/^has_(one|many)_((?:(?!through_)|(?!as_)|(?!(of|by)_).)+)(_through_((?:(?!as_).)+))?(_as_([a-z0-9_]+))?(_(of|by)_([a-z0-9_]+))?$/', $method, $matches)) {
                $of = isset($matches[10]) ? $matches[10] : $class;
                $as = isset($matches[7]) ? $matches[7] : $matches[2];
                $through = isset($matches[5]) ? $matches[5] : NULL;
                $this->associates[$as] = $assoc_obj->through($through)
                                                   ->model($matches[2])
                                                   ->type('has_' . $matches[1])
                                                   ->name($as)
                                                   ->inversed_by($of)
                                                   ->dependent($dependent);
                return TRUE;
            } elseif (preg_match('/^belongs_to_((?:(?!as_).)+)(_as_([a-z0-9_]+))?$/', $method, $matches)) {
                $as    = isset($matches[3]) ? $matches[3] : $matches[1];
                $field = "{$as}_id";
                $table[$field] = $table[$field]->foreign_key();
                $this->associates[$as] = $assoc_obj->model($matches[1])
                                                   ->type('belongs_to')
                                                   ->name($as)
                                                   ->dependent($dependent);
                if (!in_array($as, $this->owners)) {
                    $this->owners[] = $as;
                }
                return TRUE;
            } elseif (preg_match('/^has_and_belongs_to_many_((?:(?!as_)|(?!(of|by)_).)+)(_as_([a-z0-9_]+))?(_(of|by)_([a-z0-9_]+))?$/', $method, $matches)) {
                $of = isset($matches[7]) ? $matches[7] : $class;
                $as = isset($matches[4]) ? $matches[4] : $matches[1];
                $this->associates[$as] = $assoc_obj->model($matches[1])
                                                   ->type('has_and_belongs_to_many')
                                                   ->name($as)
                                                   ->inversed_by($of)
                                                   ->getXref($this->model)
                                                   ->dependent($dependent);
                return TRUE;
            }
        }
        return FALSE;
    }

    private function setAssociation($type, $model, array $options=array()) {
        $class     = $this->model;
        $table    =& $class::loadTable();
        $assoc_obj = new ModelAssociate;
        $as        = isset($options['as']) ? $options['as'] : $model;
        $dependent = isset($options['dependent']) ? $options['dependent'] : ($type != 'has_and_belongs_to_many' ? 'nullify' : 'destroy');
        $assoc_obj = $assoc_obj->model($model)->type($type)->name($as)->dependent($dependent);
        if ($type != 'has_and_belongs_to_many') {
            $through = isset($options['through']) ? $options['through'] : NULL;
            $assoc_obj = $assoc_obj->through($through);
            if ($type == 'belongs_to') {
                $field = "{$as}_id";
                if (!$table[$field]) {
                    throw new ModelRulesException("Ooops! undefined field $field @ $class");
                }
                $table[$field] = $table[$field]->foreign_key();
            }
        } else {
            $assoc_obj = $assoc_obj->getXref($this->model);
        }
        if (isset($options['of']) || isset($options['by'])) {
            $inversed_by = isset($options['of']) ? $options['of'] : $options['by'];
            $assoc_obj = $assoc_obj->inversed_by($inversed_by);
        }
        $this->associates[$as] = $assoc_obj;
    }
    /*-----------------------------------------------------------------------------
     * Misc methods
     */
    public function to_string_by($field) {
        $class     = $this->model;
        $table    =& $class::loadTable();
        if ($table->hasElement($field)) {
            $table->setTitleKey($field);
        }
    }

}

class ModelRulesException extends \Exception {}
?>