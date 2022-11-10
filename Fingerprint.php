<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once realpath(__DIR__ . '/vendor/autoload.php');
require('ZKLibrary.php');

class Fingerprint {
	function __construct ()
    {
        $this->dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $this->dotenv->safeLoad();
        $this->ip = $_ENV['IP'];
        $this->port = $_ENV['PORT'];
        $this->base_url = $_ENV['BASE_URL'];
        $this->late = $this->http_request($this->base_url.'/'.'api/fingerprint/terlambat';
    }

    function test () {
        $test = ['Lorem', 'Ipsum'];
        header("Content-Type: application/json");
        return json_encode($test);
    }

    function http_request($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    function all_attendance ()
    {
        $zk = new ZKLibrary($this->ip, $this->port);
        $zk->connect();
        $zk->disableDevice();
        $get_attendance = $zk->getAttendance();
        $zk->enableDevice();
        $zk->disconnect(); 
		return $get_attendance;
    }

    function today_attendance ($date)
    {
        $all_attendance = $this->all_attendance();
        $today_attendance = [];
        $status = '';

        foreach ($all_attendance as $all_data) {
            $time = explode(" ", $all_data[3]);
            $status = $time[1] <= $this->late ? 'tepat' : 'terlambat';
            // get today only
            if ($time[0] == $date) {
                // push all today data to today_attendance
                array_push($today_attendance, [
                    'user_id' => $this->http_request($this->base_url.'/'.'api/fingerprint/profil/id-mesin/'.$all_data[1]),
                    'id_mesin' => $all_data[1],
                    'tanggal' => $time[0],
                    'datang' => $time[1],
                    'pulang' => null,
                    'status' => $status
                ]);
            }
        }

        // get today data from database
        // check if data already in database
        $today_attendance_database = json_encode($this->http_request($this->base_url.'/'.'api/fingerprint/absen/'.$date));
        $today_attendance_compare = [];

        foreach ($today_attendance as $all_today) {
            $exist = false;
            foreach ($today_attendance_database as $all_database) {
                if ($all_today['id_mesin'] == $all_database['id_mesin']
                    && $all_today['tanggal'] == $all_database['tanggal']){
                    $exist = true;
                }
            }
            if ($exist === false) array_push($today_attendance_compare, $all_today);
        }

        // remove duplicate if touch fingerprint more than once, by id_mesin
        // https://stackoverflow.com/questions/307674/how-to-remove-duplicate-values-from-a-multi-dimensional-array-in-php
        $temp = array_unique(array_column($today_attendance_compare, 'id_mesin'));
        $today_attendance_fix = array_intersect_key($today_attendance_compare, $temp);
        return $today_attendance_fix;
    }
}
