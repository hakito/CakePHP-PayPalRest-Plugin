<?php

/**
 * Test suite bootstrap for EpsBankTransfer.
 *
 * This function is used to find the location of CakePHP whether CakePHP
 * has been installed as a dependency of the plugin, or the plugin is itself
 * installed as a dependency of an application.
 */
$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir(join(DS, [$root, 'vendor', 'cakephp', 'cakephp']))) {
            return $root;
        }
    } while ($root !== $lastRoot);

    throw new Exception("Cannot find the root of the application, unable to run tests");
};
$root = $findRoot(__FILE__);
unset($findRoot);

define('ROOT', $root); unset($root);
define('APP', ROOT . DS);
define('APP_DIR', 'App');
define('TMP', sys_get_temp_dir() . DS);
define('CONFIG', join(DS, [ROOT, 'tests', 'config', '']));
define('CACHE', TMP . 'cache' . DS);

define('CAKE_CORE_INCLUDE_PATH', join(DS, [ROOT, 'vendor', 'cakephp', 'cakephp']));
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
define('CAKE', CORE_PATH . 'src' . DS);
define('LOGS', join(DS, [TMP, 'logs']));

/**
 * Define fallback values for required constants and configuration.
 * To customize constants and configuration remove this require
 * and define the data required by your plugin here.
 */
require_once join(DS, [CORE_PATH, 'config', 'bootstrap.php']);
require CAKE . 'functions.php';

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Security;

Configure::config('default', new PhpConfig());
Configure::load('app', 'default', false);
Configure::load('PayPal', 'default', false);
Cache::setConfig(Configure::consume('Cache'));
ConnectionManager::setConfig('test', Configure::consume('Datasources.test'));
Security::setSalt(Configure::consume('Security.salt'));

use Migrations\TestSuite\Migrator;
$migrator = new Migrator();

// Simple setup for with no plugins
$migrator->run();