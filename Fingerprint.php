<?php
/*
PHP file for store and update attendance fingerprint machine
Run this file with cron and PHP CLI command
Ex php -r 'require("Fingerprint.php"); $f = new Fingerprint(); $test = $f->today_attendance("2022-11-09"); print_r($test);'
By Ahmad Bagwi Rifai
Email ahmadbagwi.id@gmail.com
*/
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
        $this->ip2 = $_ENV['IP2'];
        $this->port = $_ENV['PORT'];
        $this->base_url = $_ENV['FURL'];
        // $this->late = $this->http_request($this->base_url.'/'.'api/fingerprint/terlambat';
    }

    function test () {
        $test = ['Lorem', 'Ipsum'];
        header("Content-Type: application/json");
        return json_encode($test);
    }

    function http_request($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    function http_post($url, $postdata){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        // curl_setopt($ch, CURLOPT_USERPWD, $username.":".$password);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    function find_user ($users, $id_mesin, $level)
    {
        $user_id = null;
        if ($level === 'pns') {
            foreach (json_decode($users) as $user) {
                if (strpos($user->id_mesin, $id_mesin) !== false) {
                    $user_id = $user->user_id;
                }
            }
        } else {
            foreach (json_decode($users) as $user) {
                if ($user->user_id == $id_mesin) {
                    $user_id = $user->user_id;
                }
            }
        }
        return $user_id;
    }

    function all_attendance ($ip)
    {
        $zk = new ZKLibrary($ip, $this->port);
        $zk->connect();
        $zk->disableDevice();
        $get_attendance = $zk->getAttendance();
        $zk->enableDevice();
        $zk->disconnect();
		return $get_attendance;
    }

    function today_attendance ($date, $ip, $level)
    {
        $all_attendance = $this->all_attendance($ip);
        $today_attendance = [];
        $late = $this->http_request($this->base_url.'/'.'api/fingerprint/terlambat');
        $status = '';
        $today_attendance_database = $this->http_request($this->base_url.'/'.'api/fingerprint/absen/'.$date);
        $users = $this->http_request($this->base_url.'/'.'api/fingerprint/user');

        foreach ($all_attendance as $all_data) {
            $time = explode(" ", $all_data[3]);
            $status = strtotime($time[1]) <= strtotime($late) ? 'tepat' : 'terlambat';
            // get today only
            if ($time[0] == $date) {
                // push all today data to today_attendance
                array_push($today_attendance, [
                    'user_id' => $this->find_user($users, $all_data[1], $level), // http_request($this->base_url.'/'.'api/fingerprint/profil/id-mesin/'.$all_data[1]),
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
        // $today_attendance_database = json_encode($this->http_request($this->base_url.'/'.'api/fingerprint/absen/'.$date));

        $today_attendance_compare = [];

        foreach ($today_attendance as $all_today) {
            $exist = false;
            foreach (json_decode($today_attendance_database) as $all_database) {
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

    function today_update_attendance ($date, $ip, $level)
    {
        $all_attendance = $this->all_attendance($ip);
        $today_update_attendance = [];
        $today_attendance_database = $this->http_request($this->base_url.'/'.'api/fingerprint/absen/'.$date);
        $users = $this->http_request($this->base_url.'/'.'api/fingerprint/user');


        foreach ($all_attendance as $all_data) {
            $time = explode(" ", $all_data[3]);
            // get today only
            if ($time[0] == $date) {
                // push all today data to today_attendance
                array_push($today_update_attendance, [
                    'user_id' =>$this->find_user($users, $all_data[1], $level), // $this->http_request($this->base_url.'/'.'api/fingerprint/profil/id-mesin/'.$all_data[1]),
                    'id_mesin' => $all_data[1],
                    'tanggal' => $time[0],
                    'pulang' => $time[1]
                ]);
            }
        }

        // get today data from database
        // get only data already in database to update 
        // $today_attendance_database = json_encode($this->http_request($this->base_url.'/'.'api/fingerprint/absen/'.$date));
        $today_attendance_compare = [];

        foreach (array_reverse($today_update_attendance) as $all_today) {
            $exist = false;
            foreach (array_reverse(json_decode($today_attendance_database)) as $all_database) {
                if ($all_today['user_id'] == $all_database['user_id']
                    && $all_today['id_mesin'] == $all_database['id_mesin']
                    && $all_today['tanggal'] == $all_database['tanggal']){
                    $exist = true;
                }
            }
            if ($exist === true) array_push($today_attendance_compare, $all_today);
        }

        // remove duplicate if touch fingerprint more than once, by id_mesin
        // https://stackoverflow.com/questions/307674/how-to-remove-duplicate-values-from-a-multi-dimensional-array-in-php
        $temp = array_unique(array_column($today_attendance_compare, 'id_mesin'));
        $today_attendance_update_fix = array_intersect_key($today_attendance_compare, $temp);
        return $today_attendance_update_fix;
    }

    function store_attendance ($date, $ip)
    {
        $url = $this->http_request($this->base_url.'/'.'api/fingerprint/attendance/store');
        $data = $this->today_attendance($date, $ip);
        $username = $_ENV['FUSERNAME'];
        $password = $_ENV['FPASSWORD'];
        $postdata = json_encode(['data' => $data, 'username' => $username, 'password' => $password]);
        $submit = $this->http_post($url, $postdata);
        return $submit;
    }

    function update_attendance ($date, $ip)
    {
        $url = $this->base_url.'/'.'api/fingerprint/attendance/update';
        $data = $this->today_update_attendance($date, $ip);
        $username = $_ENV['FUSERNAME'];
        $password = $_ENV['FPASSWORD'];
        $postdata = json_encode(['data' => $data, 'username' => $username, 'password' => $password]);
        $submit = $this->http_post($url, $postdata);
        return $submit;
    }
}
