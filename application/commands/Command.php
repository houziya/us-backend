<?php

function endsWith($haystack, $needle)
{
    return ($tmp = strlen($haystack) - strlen($needle)) > 0 && strpos($haystack, $needle, $tmp) !== false;
}

try {
    define("APP_PATH", realpath(dirname(__FILE__) . '/../../'));
    spl_autoload_register(function($class){
        if (endsWith($class, "Command")) {
            $class = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 0, strlen($class) - strlen("Command"))) . '.php';
            include(implode(DIRECTORY_SEPARATOR, [APP_PATH, "application", "commands", $class]));
        }
    });
    $app = new \Yaf\Application(APP_PATH . "/conf/application.ini");
    $app->bootstrap();
    if (count($argv) < 1) {
        echo "Missing command argument";
        return;
    }
    $argv = array_slice($argv, 1);
    foreach($argv as $command) {
        if (!endsWith($command, "Command")) {
            $command .= "Command";
        }
        call_user_func("$command::main");
    }
} catch (Exception $e) {
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    echo $e->getTraceAsString();
}

?>
