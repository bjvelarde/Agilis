<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

class ModelAssociate extends FixedStruct {

    public function __construct() {
	    parent::__construct('type', 'name', 'model', 'through', 'dependent', 'inversed_by');
	}

    public function __call($method, $args) {
        if ($method != 'type' && $method != 'dependent') {
            if ($args && $args[0] !== NULL) {
                $this[$method] = String::singularize($args[0])->underscore()->to_s;
            }
            return $this;
        } else {
            return parent::__call($method, $args);
        }
    }

    public function cleanUp(Model $model) {
        if ($this->isPolymorphic()) {
            $subj_model = String::underscore(get_class($model))->to_s;
            $criteria = array('ref_id' => $model->id, 'ref_model' => $subj_model);
        } else {
            $subj_class = get_class($model);
            $fkey       = $subj_class::foreignKey();
            $criteria   = array($fkey => $model->id);
        }
        if ($this->through) {
            $xref_class = String::camelize($this->through);
            if ($this->type == 'has_and_belongs_to_many') {
                $xref_table = $xref_class::deleteMany($criteria);
            } else {
                $xrefs = $xref_class::all($criteria);
                if ($xrefs) {
                    foreach ($xrefs as $xref) {
                        if ($this->dependent != 'nullify') {
                            $xref->{$this->dependent}();
                        } else {
                            $xref[$fkey] = NULL;
                            $xref->save();
                        }
                    }
                }
            }
        } elseif ($this->type != 'belongs_to') {
            $model_class = String::camelize($this->model);
            $associates = $model_class::all($criteria);
            if ($associates) {
                foreach ($associates as $associate) {
                    if ($this->dependent != 'nullify') {
                        $associate->{$this->dependent}();
                    } else {
                        $associate[$fkey] = NULL;
                        $associate->save();
                    }
                }
            }
        }
    }
    
    public function export() {
        return "Associate::newInstance()->model('{$this->model}')->type('{$this->type}')" 
                . "->name('{$this->name}')->through('{$this->through}')->dependent('{$this->dependent}')"
                . ($this->inversed_by ? "->inversed_by('{$this->inversed_by}')" : '');        
    }
    
    public static function newInstance() { return new self; }

    public function getAssociateClass() {
        return String::camelize($this->model)->to_s;
    }

    public function getAssociateTable() {
        $class = $this->getAssociateClass();
        return $class::getTable();
    }

    public function getXref($class) {
        $model  = String::classify($this->model)->to_s;
        $table1 = Table::$class();
        //$table2 = Table::$model();
        //if ($table1->_conn != $table2->_conn) {
        //    throw new ModelAssosiateException("$class and $model do not share the same connection.");
        //}
        $class1 = String::pluralize($class)->underscore()->to_s;
        $class2 = String::pluralize($model)->underscore()->to_s;
        if (($result = strcasecmp($class1, $class2)) !== 0) {
            if ($result < 0) {
                $through = $class1 . '_' . $class2;
            } else {
                $through = $class2 . '_' . $class1;
            }
        } else {
            $through = $class1 . '_' . $class2;
        }
        $this->through($through);
        $this->generateXref($table1->_conn, $class, $this->model);
        return $this;
    }

    public function getXrefClass() {
        return $this->through ? String::camelize($this->through)->to_s : '';
    }

    public function getXrefTable() {
        $class = $this->getXrefClass();
        return $class ? $class::getTable() : '';
    }

    public function isPolymorphic() {
        $table = $this->getAssociateTable();
        return $table->_polymorphic;
    }

    private function generateXref($conn, $class1, $class2) {
        $xref_class = String::classify($this->through)->to_s;
        if (!class_exists($xref_class)) {
            $class1 = String::underscore($class1);
            $this->generateXrefMigration($conn, $class1, $class2);
            $this->generateXrefModel($class1, $class2);
        }
    }

    private function generateXrefMigration($conn, $class1, $class2) {        
        $xref    = String::pluralize($this->through);
        $mname   = "create_{$xref}";
        $file    = Migration::generate($mname, 12);
        $class   = String::camelize($mname);
        $content = "<?php\nnamespace Agilis;\n\nclass $class extends Migration {\n\n    public function up() {\n"
                 . "        Table::open('{$xref}', '{$conn}')->fields(\n"
                 . "            Table::field('{$class1}_id')->type('integer')->primary_key(),\n"
                 . "            Table::field('{$class2}_id')->type('integer')->primary_key()\n"
                 . "        )->create();\n"
                 . "    }\n\n    public function down() {\n"
                 . "        Table::open('{$xref}', '{$conn}')->drop();\n"
                 . "    }\n}\n?>";
        file_put_contents($file, $content);
        include_once($file);
        $class = __NAMESPACE__ . "\\" . $class;
        $original_env = Conf::get('CURRENT_ENV');
        foreach (Conf::get('MIGRATION_ENVS') as $env) {
            if ($env !== $original_env) {
                Conf::set('CURRENT_ENV', $env, Conf::VARIABLE);
            }
            $migration = new $class;
            $migration->up();
            if ($env !== $original_env) {
                Conf::set('CURRENT_ENV', $original_env, Conf::VARIABLE);
            }
        }
    }

    private function generateXrefModel($class1, $class2) {
        $modelname = String::singularize($this->through)->to_s;
        $model_uri = APP_ROOT . Model::DIR . $modelname . '.php';
        if (!file_exists($model_uri)) {
            $model_uri = Model::generate($modelname);
        }
        $class = String::classify($modelname);
        $content = "<?php\nuse Agilis\Model;\n\nclass $class extends Model {\n\n    "
                  . "protected static function config() {\n        //associations\n"
                     . "        self::belongs_to_{$class1}();\n        self::belongs_to_{$class2}();\n"
                     . "        //validations\n        //scope\n    }\n}\n?>";
        file_put_contents($model_uri, $content);
    }

}

class ModelAssosiateException extends \Exception {} 
?>