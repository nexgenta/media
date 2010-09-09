<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

$buf = file_get_contents('test1.json');
$buf = str_replace("\t", '', str_replace("\n", '', $buf));

//echo $buf . "\n";

$json_errors = array(
    JSON_ERROR_NONE => 'No error has occurred',
    JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
    JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
    JSON_ERROR_SYNTAX => 'Syntax error',
);

$obj = json_decode($buf);
echo 'Last error : ', $json_errors[json_last_error()], PHP_EOL, PHP_EOL;

print_r($obj);
