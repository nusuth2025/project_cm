<?php
declare(strict_types=1);

define('APP_ENV', 'dev'); // 'dev' | 'prod'
define('DEBUG_MODE', APP_ENV === 'dev');
define('BASE_PATH', dirname(__DIR__));

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'contentmonitor');
define('DB_USER', 'contentmonitor');
define('DB_PASS', 'changeme');

function debug(string $label = '', mixed ...$vars): void
{
    if (!DEBUG_MODE) return;
    echo '<pre>[DEBUG] ' . htmlspecialchars($label) . '</pre>';
    foreach ($vars as $var) {
        var_dump($var);
    }
}

// PSR-4-Autoloader: App\Foo\Bar → app/Foo/Bar.php
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
