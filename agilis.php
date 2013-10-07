#!/usr/bin/php
<?php
define('AGILIS_PATH', dirname(__FILE__) . '/');
define('DOCUMENT_ROOT', getcwd());

class Setup {

    private $approot;
    private $appname;

    public function __construct($appname) {
        $this->approot = DOCUMENT_ROOT . "/{$appname}/";
        $this->appname = $appname;
    }

    protected function createDirs() {
        $directories = array(
            'app/assets/admin',
            'app/assets/less',
            'app/assets/less/admin',
            'app/assets/coffee',
            'app/assets/coffee/admin',
            'app/assets/uglify',
            'app/assets/uglify/admin',            
            'app/cells',
            'app/controllers/admin',
            'app/helpers/admin',
            'app/models',
            'app/templates/layouts',
            'cache/config',
            'cache/models',
            'cache/templates',
            'config/admin',
            'config/production',
            'config/staging',
            'config/development',
            'config/locales',
            'config/test',
            'config/plugins',
            'config/payment-gateways',
            'db/migration',
            'db/schema',
            'lib/classes',
            'lib/vendors',
            'public/css',
            'public/images/system',
            'public/images/data',
            'public/js',
            'specs',
            'tools'
        );
        $failed = array();
        foreach ($directories as $dir) {
            $dir = "{$this->approot}{$dir}";
            if (!file_exists($dir) && !@mkdir($dir, 0766, TRUE)) {
                $failed[] = $dir;
            }
        }
        if ($failed) {
            throw new Exception("Failed to create the following: " . implode(', ', $failed));
        }
        return TRUE;
    }

    protected function createConfig() {
        $content = file_get_contents(AGILIS_PATH . 'setup/config.php.txt');
        $content = str_replace('__AGILISPATH__', AGILIS_PATH, $content);
        file_put_contents("{$this->approot}/config/config.php", $content);
        file_put_contents("{$this->approot}/config/config.dev.php", $content);
        file_put_contents("{$this->approot}/config/config.staging.php", $content);
        file_put_contents("{$this->approot}/config/config.prod.php", $content);
        
        $content = file_get_contents(AGILIS_PATH . 'setup/config.yml.txt');        
        $content = str_replace('__APPNAME__', $this->appname, $content);
        file_put_contents("{$this->approot}/config/config.yml", $content);
        file_put_contents("{$this->approot}/config/config.dev.yml", $content);
        file_put_contents("{$this->approot}/config/config.staging.yml", $content);
        file_put_contents("{$this->approot}/config/config.prod.yml", $content);        
    }

    protected function copyDbConfig() {
        $content = file_get_contents(AGILIS_PATH . 'setup/database.yml.txt');
        $content = str_replace('__APPNAME__', $this->appname, $content);
        file_put_contents("{$this->approot}/config/database.yml", $content);
    }

    protected function copyOthers() {
        $files = array(
            AGILIS_PATH . 'setup/gitignore.txt'          => "{$this->approot}/.gitignore",            
            AGILIS_PATH . 'setup/bundle.json.txt'        => "{$this->approot}/config/bundle.json",            
            AGILIS_PATH . 'setup/boot.php.txt'           => "{$this->approot}/config/boot.php",
            AGILIS_PATH . 'setup/routes.php.txt'         => "{$this->approot}/config/routes.php",            
            AGILIS_PATH . 'setup/autoload.php.txt'       => "{$this->approot}/config/autoload.php",
            AGILIS_PATH . 'setup/delegations.yml.txt'    => "{$this->approot}/config/plugins/default.yml",
            AGILIS_PATH . 'setup/assets.yml.txt'         => "{$this->approot}/config/assets.yml",
            AGILIS_PATH . 'setup/mailer.sample.yml.txt'  => "{$this->approot}/config/mailer.sample.yml",
            AGILIS_PATH . 'setup/locales.yml.txt'        => "{$this->approot}/config/locales.yml",
            AGILIS_PATH . 'setup/page-caching.yml.txt'   => "{$this->approot}/config/page-caching.yml",
            AGILIS_PATH . 'setup/config.htaccess.txt'    => "{$this->approot}/config/.htaccess",
            AGILIS_PATH . 'setup/exceptions.php.txt'     => "{$this->approot}/lib/exceptions.php",            
            AGILIS_PATH . 'setup/404.phtml.txt'          => "{$this->approot}/app/templates/404.phtml",
            AGILIS_PATH . 'setup/paginator.phtml.txt'    => "{$this->approot}/app/templates/paginator.phtml",
            AGILIS_PATH . 'setup/default.phtml.txt'      => "{$this->approot}/app/templates/layouts/default.phtml",
			AGILIS_PATH . 'setup/index.php.txt'          => "{$this->approot}/public/index.php",
        );
        $failed = array();
        foreach ($files as $origin => $destiny) {
            if (!copy($origin, $destiny)) {
                $failed[] = $destiny;
            }
        }
        if ($failed) {
            throw new Exception("Failed to copy to the following: " . implode(', ', $failed));
        }
    }

    protected function copyTools() {
        $tools = array('createdb', 'migrate', 'generate', 'reload', 'dumproutes', 'clearcache', 'bundle');
        foreach ($tools as $tool) {
            $content = file_get_contents(AGILIS_PATH . "setup/{$tool}.php.txt");
            $content = str_replace('__APPROOT__', $this->approot, $content);
            file_put_contents("{$this->approot}/tools/{$tool}.php", $content);
            if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' && file_exists(AGILIS_PATH . "setup/{$tool}.bat.txt")) {
                $content = file_get_contents(AGILIS_PATH . "setup/{$tool}.bat.txt");
                $content = str_replace('__APPROOT__', $this->approot, $content);
                file_put_contents("{$this->approot}/tools/{$tool}.bat", $content);  
            } elseif (file_exists(AGILIS_PATH . "setup/{$tool}.sh.txt")) {
                $content = file_get_contents(AGILIS_PATH . "setup/{$tool}.sh.txt");
                $content = str_replace('__APPROOT__', $this->approot, $content);
                file_put_contents("{$this->approot}/tools/{$tool}.sh", $content); 
                @chmod("{$this->approot}/tools/{$tool}.sh", 0755);                
            }             
        }
    }    

    public function run() {
        echo "Creating app framework...";
        $this->createDirs();
        $this->createConfig();
        $this->copyDbConfig();
        $this->copyTools();        
        $this->copyOthers();
        echo "Done!\n\n";
        echo "It is recommended (but not required)\nthat you go to\n{$this->approot}tools and run: bundle install";
    }
}

try {
    if (empty($argv[1])) {
        echo "Usage: php agilis.php <yourapp>";
    } else {
        $setup = new Setup($argv[1]);
        $setup->run();
    }
} catch (Exception $e) {
    echo $e->getMessage() . "\n" . $e->getTraceAsString();
}
?>