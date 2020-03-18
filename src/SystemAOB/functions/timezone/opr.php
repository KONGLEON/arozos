<?php

// Read timezone DB
	$timezone = [];
	$file = fopen("data/timezone.csv","r");
	while(! feof($file)){
		$tmp = fgetcsv($file);
		//print_r($tmp);
		array_push($timezone,array($tmp[0],$tmp[1],$tmp[2]));
	}
	fclose($file);
// END

//check the opration
if($_GET["opr"] == "query"){
	// if win
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
		$result["f_timezone"] = exec("tzutil /g"); //timezone name in English
		//search timezone name and convert it to Unix timezone format
		foreach ($timezone as $tz) {
			if($tz[2] == $result["f_timezone"]){
				$result["timezone"] = $tz[1];
			}
		}	
		//full time by program
		$result["fulltime"] = exec('getTime.exe');
		//explode the y-m-d h:m:s out
		$result["time"] =  explode(" ",exec('getTime.exe'))[1]." ".explode(" ",exec('getTime.exe'))[2];
		//since windows had some problem on detecting the daylight, disable it
		$result["existdaylight"] = false; //disable detecing the daylight. NOT supported
		//it is windows
		$result["isWindows"] = true;
		
	}else{
		//get the timezone
		$result["timezone"] = exec("cat /etc/timezone");
		//get the ntp server
		$result["ntpserver"] = explode("=",exec("sudo timedatectl show-timesync | grep 'ServerName='"))[1];
		//convert it to readable name , aka Windows TZ format
		foreach ($timezone as $tz) {
			if($tz[1] == $result["timezone"]){
				$result["f_timezone"] = $tz[2];
			}
		}
		//get full time
		$result["fulltime"] = exec('date +"%a %Y-%m-%d %T %Z %z"');
		//get y-m-d h:m:s out
		$result["time"] = exec('date +"%Y-%m-%d %T"');
		
		//check the Daylight saving
		$nextDayLightSavingTZ = new DateTimeZone($result["timezone"]); //use timezone to fetch the data
		//get next transitions
		$nextDayLightSavingTZTransitions = $nextDayLightSavingTZ->getTransitions(time());
		//if >1 then means had daylight saving in future
		if(sizeOf($nextDayLightSavingTZTransitions) != 1){
			//slice and get the first one out
			$nextDayLightSavingTZTime = array_slice($nextDayLightSavingTZTransitions,1,1)[0];
			//create the DateTime var by using $nextDayLightSavingTZTime
			$nextDayLightSavingTZTimeDT = new DateTime($nextDayLightSavingTZTime["time"]);
			//get Current time
			$currentTimeDT = new DateTime($result["fulltime"]);
			//Compare the difference
			$interval = $currentTimeDT->diff($nextDayLightSavingTZTimeDT);
			//format it to Y-m-d h:m:s, since the input is unix timestamp
			$result["nextdaylight"] = $nextDayLightSavingTZTimeDT->format('Y-m-d H:m:s');
			//calc the different
			$result["nextdaylightremains"] = $interval->format('%a');
			//dst means Day Light Saving Time
			$result["nextdst"] = $nextDayLightSavingTZTime["isdst"];
			$result["existdaylight"] = true;
		}else{
			//since <1, then no dst right now
			$result["existdaylight"] = false;
		}
		//non windows, false
		$result["isWindows"] = false;
	}
	echo json_encode($result);
}else if($_GET["opr"] == "alltimezone"){
	echo json_encode($timezone);
}else if($_GET["opr"] == "modify"){
	if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
		//convert it's timezone to Windows compitiable format
		$tz_win = "";
		foreach ($timezone as $tz) {
			if($tz[1] == $_GET["tz"]){
				$tz_win = $tz[2];
			}
		}
		
		//for TZ
		//change timezone
		exec('tzutil /s "'.$tz_win.'"');
		echo "Finish";
	}else{
	    //for timesyncd.conf
		//output it back to system
		exec("sudo chmod -R 0777 /etc/systemd/timesyncd.conf");
		$NTPFile = '#  This file is part of systemd.'.PHP_EOL.'#'.PHP_EOL.'#  systemd is free software; you can redistribute it and/or modify it'.PHP_EOL.'#  under the terms of the GNU Lesser General Public License as published by'.PHP_EOL.'#  the Free Software Foundation; either version 2.1 of the License, or'.PHP_EOL.'#  (at your option) any later version.'.PHP_EOL.'#'.PHP_EOL.'# Entries in this file show the compile time defaults.'.PHP_EOL.'# You can change settings by editing this file.'.PHP_EOL.'# Defaults can be restored by simply deleting this file.'.PHP_EOL.'#'.PHP_EOL.'# See timesyncd.conf(5) for details.'.PHP_EOL.'# Generated by ArOZ Online at '.date("Y-m-d H:i:s O T").PHP_EOL.PHP_EOL.'[Time]'.PHP_EOL;
		$NTPFile = $NTPFile.'NTP='.$_GET["ntpserver"].PHP_EOL.'#FallbackNTP=0.debian.pool.ntp.org 1.debian.pool.ntp.org 2.debian.pool.ntp.org 3.debian.pool.ntp.org'.PHP_EOL.'#RootDistanceMaxSec=5'.PHP_EOL.'#PollIntervalMinSec=32'.PHP_EOL.'#PollIntervalMaxSec=2048';
		file_put_contents("/etc/systemd/timesyncd.conf",$NTPFile);
		
		//restart
		exec("sudo timedatectl set-ntp true");
        //for TZ
		exec("sudo timedatectl set-timezone '".$_GET["tz"]."'");
		
		//for All
		exec("sudo systemctl restart systemd-timesyncd");
		//exec("sudo timedatectl status");
		//exec("sudo timedatectl timesync-status");
		//exec("sudo timedatectl show-timesync");
		
		echo "Finish";
	}
}
?>