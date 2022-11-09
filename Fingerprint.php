<?php

require('ZKLibrary.php');

class Fingerprint {
	function __construct ()
    {
        $this->ipmesin = getenv('IP');
        $this->portmesin = getenv('PORT');
    }

    function all_attendance ()
    {
        $zk = new ZKLibrary($this->ipmesin, $this->portmesin);
        $zk->connect();
        $zk->disableDevice();
        $data = $zk->getAttendance();
        $zk->enableDevice();
        $zk->disconnect();

		header("Content-Type: application/json");
		return json_encode($data);
    }
}