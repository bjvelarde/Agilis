<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \Spyc;
use \PHPMailer;

class ActionMailer {

    private $mailer;
    
    public function __construct() {
        $yml_file = APP_ROOT . 'config/mailer.yml';
        if (!file_exists($yml_file)) {
            throw new ActionMailerException('Missing mailer configuration file: ' . $yml_file);
        }
        $config = Spyc::YAMLLoad($yml_file);
        $this->mailer = new PHPMailer(TRUE);
        switch ($config['mailer']) {
            case 'smtp':
                $this->mailer->IsSMTP();
                break;
            case 'sendmail':
                $this->mailer->IsSendmail();
                break;            
        }
        if ($config['mailer'] == 'smtp') {
            if (isset($config['security'])) {
                $this->mailer->SMTPSecure = $config['security'];
            }
            if (isset($config['host'])) {
                $this->mailer->Host = $config['host'];
            }
            if (isset($config['port'])) {
                $this->mailer->Port = $config['port'];
            }            
            if (isset($config['username'])) {
                $this->mailer->Username = $config['username'];
            }
            if (isset($config['password'])) {
                $this->mailer->Password = $config['password'];
            }
            $this->mailer->SMTPAuth = (isset($config['authenticate']) && $config['authenticate'] == 'YES');
        }
        if (isset($config['from']['address'])) {
            $from  = $config['from']['address'];
            $fname = isset($config['from']['name']) ? $config['from']['name'] : '';
            $this->mailer->SetFrom($from, $fname);
        }
    }
    
    public function __get($var) {        
        if (property_exists($this->mailer, $var)) {
            return $this->mailer->{$var};
        } elseif (substr($var, 0, 5) == 'dkim_') {
            $var = 'DKIM_' . substr($var, 5);
            if (property_exists($this->mailer, $var)) {
                return $this->mailer->{$var};
            }
        } else {
            $var = String::camelize($var)->to_s;
            if (property_exists($this->mailer, $var)) {
                return $this->mailer->{$var};
            } elseif (property_exists($this->mailer, 'SMTP' . $var)) {
                return $this->mailer->{'SMTP' . $var};
            }
        }
        return NULL;
    }
    
    public function __set($var, $val) {        
        if (property_exists($this->mailer, $var)) { 
            $this->mailer->{$var} = $val; 
        } elseif (substr($var, 0, 5) == 'dkim_') {
            $var = 'DKIM_' . substr($var, 5);
            if (property_exists($this->mailer, $var)) {
                $this->mailer->{$var} = $val;
            }            
        } else {
            $var = String::camelize($var)->to_s;
            if (property_exists($this->mailer, $var)) {
                $this->mailer->{$var} = $val; 
            } elseif (property_exists($this->mailer, 'SMTP' . $var)) {
                $this->mailer->{'SMTP' . $var} = $val; 
            }
        }
    }
    
    public function __call($method, $args) {        
        if (method_exists($this->mailer, $method)) {
            return call_user_func_array(array($this->mailer, $method), $args);
        } else {
            $method = ucfirst($method);
            if (method_exists($this->mailer, $method)) {
                return call_user_func_array(array($this->mailer, $method), $args);
            }
        }
        return NULL;
    }

}

class ActionMailerException extends \Exception {}
?>
