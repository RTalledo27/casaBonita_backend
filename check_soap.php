<?php
echo "PHP Version: " . phpversion() . "\n";
echo "Loaded Configuration File: " . php_ini_loaded_file() . "\n";
echo "SOAP Extension Loaded: " . (extension_loaded('soap') ? 'YES' : 'NO') . "\n";
echo "SoapClient Class Exists: " . (class_exists('SoapClient') ? 'YES' : 'NO') . "\n";

$inipath = php_ini_loaded_file();
if ($inipath) {
    echo "\nContenido de extension=soap en $inipath:\n";
    $content = file_get_contents($inipath);
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        if (strpos($line, 'soap') !== false) {
            echo trim($line) . "\n";
        }
    }
} else {
    echo "No loaded php.ini file found.\n";
}
