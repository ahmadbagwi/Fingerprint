<?php
require_once realpath(__DIR__ . '/vendor/autoload.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require('ZKLibrary.php');

class Fingerprint {
	function __construct ()
    {
        $this->dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $this->dotenv->safeLoad();
        $this->ip = $_ENV['IP'];
        $this->port = $_ENV['PORT'];
    }

    function test () {
        $test = ['Lorem', 'Ipsum'];
        header("Content-Type: application/json");
        return json_encode($test);
    }

    function all_attendance ()
    {
        $zk = new ZKLibrary($this->ip, $this->port);
        $zk->connect();
        $zk->disableDevice();
        $data = $zk->getAttendance();
        $zk->enableDevice();
        $zk->disconnect();
        
		header("Content-Type: application/json");
		return json_encode($data);
    }
}
