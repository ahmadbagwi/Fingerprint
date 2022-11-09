<?php

use ZKLibrary;

class Fingerprint {
	public function __construct ()
    {
        $this->ipmesin = '10.10.120.80';
        $this->portmesin = '4370';
    }

    public function test_absen()
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