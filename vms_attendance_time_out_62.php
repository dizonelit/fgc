<?php

/*
- vms_attendance_time_out_62.php
- adapted from vms_time_out_01.php (as of 20190714)
- called by vms_attendance_02.php
- links back to vms_attendance_01.php
- process flow
start
  |
vms_attendance_62.php				
  |
  |time-in?
  |
  |---->yes
  |      |--vms_attendance_time_in_62.php  ---->|       
  |      |                                      |
  |      |--vms_attendance_time_out_62.php ---->|       <<<<
  |                                             |
  |---->no                                      |
  |      |--vms_time_records_62.php             |
  |             |                               |
  |<--------------------------------------------|
  |
 end

0.  Business Rules?
 
1. 	This version contains the new requirements of
 	creating rewards based on the policy effective Aug 1 2019.
	Rewards before Aug 1, 2019 are not automatically created in VMS.
	These will be created manually in VMS from FGC manual records.
 	
1.1	Rewards can be awared and availed only in three instances
 	$25.00 after 25 volunteer hours
 	$50.00 after 50 volunteer hours
 	$75.00 after 75 volunteer hours
 	
1.2 After submitting the hours during time out, 
	the following should be displayed on the last screen:

	- Time Period				Hours	Minutes
		Today					  hh	  mm
		Anniversary Year		  hh	  mm
		Since Joining Date		  hh	  mm	

	- Rewards (must be availed within anniversary year: from mmm-dd-yyyy to mmm-dd-yyy)
	  (If the volunteer did not report for more than 90 days, the rewards will be forfeited
	   and the volunteer must attend re-orientation)
	  		Hours  		Amount		Awarded			Availment		Balance		Expiration
	  	 After 25		$25.00		mmm-dd-yyy		$20.00			$05.00		mmm-dd-yyy
	   	 After 50		$25.00		mmm-dd-yyy		$00.00			$25.00		mmm-dd-yyy
	   	 After 75		$25.00		mmm-dd-yyy		$00.00			$25.00		mmm-dd-yyy
	  		Total 		$75.00						$20.00			$55.00		mmm-dd-yyy
	  		
	- Credits (must be used within 90 days after 'awarded' date)
			Amount		Awarded		Expiration		Particulars
			 $20		mmm-dd-yyy	mmm-dd-yyy		Returns per receipt # 2319
			
	- Discounts (teacher's, etc)
			Percent		Awarded		Expiration		Particulars
			  20%		mmm-dd-yyy	mm-dd-yyy		For teaching Linux, Python, Hardware, etc.
			  
1.3	Availment of rewards
	Availments will be automatically processed during sales processing.
	In the meantime that sales processing is not yet implemented, 
	availments will be manually processed using separate programs.

1.3.1 Rewards can only be availed with the amounts of either $25.00 or $50.00 or $75.00

1.3.2 Any previous availments will be subtracted from current total rewards
		
Example:
		
	First 25 hours:
		On the first 25 hours, $25.00 was awarded
		Out of the awarded $25.00, $20.00 was used to purchase items	
		A balance of $5.00 will be left for future availment
				
	First 50 hours:
		For the additional 25 hours, $25.00 is awarded. 
		This makes the total award to $50.00
		The total entitlement is now $30.00
			First 25 hours award			$25.00
			Less:	Availment				$20.00
			Balance from first 25 hours		$05.00
					
			Award from first 50 hours		$25.00
					
				Total Balance				$30.00
		
	or
					Hours		Reward
		First 25 	  25		$25.00
		Next 25 	  25		$25.00
		Total 		  50 		$50.00
		
		Less: Availment			$20.00			
	
		Balance					$30.00			 	
*/



$trace = FALSE;
$trace = TRUE;

//-get user id
session_start();
$userId = $_SESSION["userid"]; 

//-get name of script/program
$script = basename(__FILE__);

//-open database
include '../lis/db_connection.php';

//-get volunteer name (example of selecting only one record)
$query = "SELECT firstname, lastname, joindate 
            FROM volunteer
           WHERE id = '$userId';";

$result = mysqli_query($db_connection, $query) 
          or die (mysqli_error($db_connection));

$row = mysqli_fetch_array($result); 
if ($row) {
  $fullName = $row['firstname'] . " " . $row['lastname'];
  $joining_date = $start_date = $row['joindate'];       
}

$message = '';

//-get current date and time for use as time out data
$timeOutDate = date('Y-m-d');
$timeOutTime = date('H:i:s');

//-set timeout to 5PM if the current time is after 5PM
if ($timeOutTime > '17:00:00' || $timeOutTime > '16:49:00') {
    $timeOutTime = '17:00:00';
}

if (!$_POST) {	//-check if this is first time the screen is displayed/submit button not yet clicked

    //-get the activities selected during time-in
    $query = "SELECT * FROM actvty_trans
               WHERE id      = '$userId'
                 AND tdate   = '$timeOutDate'
                 AND timein  <> ''
                 AND (timeout IS NULL
                  OR timeout = ''
                  OR timeout = '00:00:00');";

    $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));

    if (mysqli_num_rows($result) < 1) {
        //-no time-in activities were selected, redirect to time-in screen 
        echo "<html><body><form action='vms_attendance_62.php' method='post'>
        <p><h2>NOTE! You cannot 'time out' for today.</h2>
        <p><h3>You have to 'time in' before you 'time out'.</h3>"; 
        $message = 'Please click anywhere on this shaded area to go back to Time In...Thanks!';
        echo "<p><input type='submit' value='$message' style='white-space: normal; color: red; font-weight:bold; font-size: 25px; height:250px; width:600px;'></p>";
        echo "</form></body></html>"; 

    } else { # if (mysqli_num_rows($result) < 1) { 
  
        //-compute total hours and minutes between time-in and time-out   
        while($rows = mysqli_fetch_array($result)){
            $_SESSION["recnum"] = $rows['recnum'];
            $timeIn = $rows['timein'];
        } //-end-while
        
        //-compute number of minutes for time-in
        $timeInParts   = explode(":", $timeIn);
        $totalTimeIn   = $timeInParts[0] * 60 + $timeInParts[1];

        //-compute number of minutes for time-out
        $timeOutParts  = explode(":", $timeOutTime);
        $totalTimeOut  = $timeOutParts[0] * 60 + $timeOutParts[1];

        //-get the number of minutes between time-in and time-out
        $totalMinutes  = $totalTimeOut - $totalTimeIn;

        //-save the actual total number of minutes to check entered time-out
        $_SESSION["totalMinutes"] = $totalMinutes;

        //-convert the difference in minutes into hours and minutes
        $timeHours     = intval($totalMinutes / 60);
        $timeMinutes   = $totalMinutes - ($timeHours * 60); 
        
        //-use standard if/else statement - example
        if ($timeHours > 1) {
            $hourDesc = 'hours';
        } else {
            $hourDesc = 'hour';
        }

        //-use 'ternary operator' for above if-else statements
        $minuteDesc = $timeMinutes > 1 ? 'minutes' : 'minute'; 

        //-process all activities for current date with time-in and without time-out
        $query = "SELECT * FROM actvty_trans
                   WHERE id      = '$userId'
                     AND tdate   = '$timeOutDate'
                     AND timein  <> ''
                     AND (timeout IS NULL
                      OR timeout = ''
                      OR timeout = '00:00:00');";

        $result = mysqli_query($db_connection, $query) 
                  or die (mysqli_error($db_connection));
  
        if (mysqli_num_rows($result) < 1) {

        	//-this is a redundant check, but make sure there is no binary transposition
            echo "<br>No activities for Id = " . $userId . " on " . $timeOutDate . "<br>";
            
        } else {

            //-get all the activities with time-in for the day and save in array
            $actvty_ctr   = 0;
            $activities = $recnum = $timeIns = array();

            while($rows = mysqli_fetch_array($result)){
                $actvty_ctr++;
                $activities[$actvty_ctr] = $rows['activity'];		# activity code
                $recnum[$actvty_ctr] = $rows['recnum'];   			# record number	
                $timeIns[$actvty_ctr] = $rows['timein'];        	# time-in
            } // end-while            
         
            //-get total minutes
            $total_minutes = $timeHours * 60 + $timeMinutes;          
                        
  			//-get number of minutes per activity
            $distributed_minutes = $total_minutes / $actvty_ctr;  
            
            //-translate the distributed minutes to hours and minutes
            $dist_hours = $dist_minutes = 0;

            if ($distributed_minutes >= 60) {						# get hours and minutes

                $dist_hours   = intval($distributed_minutes / 60);
                $dist_minutes = round($distributed_minutes - ($dist_hours * 60));
   
            } else {                                                # minutes only

                //$dist_minutes = $distributed_minutes;
                $dist_minutes =round($distributed_minutes);
            }  
 
        } # if (mysqli_num_rows($result) < 1) {

        //-generate hours for dropdown selection without using 'loop' syntax
        $selectHours = array();
        $selectHours[1] = '0';
        $selectHours[2] = '1';
        $selectHours[3] = '2';
        $selectHours[4] = '3';
        $selectHours[5] = '4';
        $selectHours[6] = '5';
        $selectHours[7] = '6';

        //-generate minutes for dropdown selection using 'loop' syntax
        $selectMinutes = array();
        for ($counter = 0; $counter <= 59; $counter++) { 

            if ($counter < 10) {									# prefix with 0 for sorting, respect two digits f
                $value = '0' . $counter;
            } else {
                $value = $counter;
            }

            $selectMinutes[$counter] = $value;

        } # for ($counter = 0; $counter <= 59; $counter++) { 


        //-display selected time in and get the hours and minutes"
        $title = "FGC-VMS: Volunteer Time - Out";
        echo "
        <!doctype html>
        <html lang = 'en'>
        <head>
        <title>$title</title>
        <script type = 'text/javascript'>
        </script>
        <link rel='stylesheet' type='text/css' href='vms_styles.css'>
        </head>
        <body> 
        <h3>$title</h3>";

        //-display the time-in time and the time-out time together with volunteer id and name
        echo "<form action='$script' method='post'>";  

        echo "Volunteer: " . "<strong>$userId</strong>" . " - ". "<strong>$fullName</strong><br>";

        echo "Your <strong>'time in'</strong> is " . "<strong>$timeIn</strong>" . 
             " and your <strong>'time out'</strong> is " . "<strong>$timeOutTime</strong>" . "<br>";

        if ($timeHours > 0) {

            echo "Your computed/volunteered time is " . "<strong>$timeHours</strong>" . " " . "<strong>$hourDesc</strong>" .
            " and " . "<strong>$timeMinutes</strong>" . " " . "<strong>$minuteDesc</strong>" . 
            " or a total of " . "<strong>$totalMinutes</strong>"  . " " . "<strong>$minuteDesc</strong>" . ".<br>";

        } else {

            echo "Your computed/volunteered time is " . "<strong>$timeMinutes</strong>" . " " . "<strong>$minuteDesc
            </strong>" . "<br>";

        } # if ($timeHours > 0) {

        echo "Please select the activity or activities you have done and assign the hours and minutes. Thank you." . "<br>" . "<br>";

		//-display table for activities with pre-assigned distributed time
        echo "<table border='' cellpadding='4' cellspacing='0'>";
 
        echo "<tr><th>Activity</th><th>Hours</th><th>Minutes</th></tr>";
 
        $query = "SELECT * from actvty_mstr
                  WHERE status = 'active'
                  order by sequence;"; 		

        $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));

        if (mysqli_num_rows($result) > 0) {

            while ($info = mysqli_fetch_array($result)) {

                $activity = stripslashes($info['activity']);
                $hours_value = $minutes_value = 0;

                //-automatically assign the distributed time to all activities
                for ($i = 1; $i <= $actvty_ctr; $i++) {
 
                    if (trim($activity) === trim($activities[$i])) {
                        $hours_value   = $dist_hours;
                        $minutes_value = $dist_minutes;
						//echo "<br># activity = " . $activity . " hours = " . $hours_value . " minutes = " . $minutes_value;
                    } 

                } # for  

                //-display activities and distributed hours and/or minutes
                echo "<tr>";
                echo "<td style='font-size:20px;'>$activity</td>";

                echo "<td><select id='hours' size='1' name='hours[]' style='height:30px;text-align:center;font-size:20px;'   selected='selected'>";
                for ($i = 1; $i <= count($selectHours); $i++) {  
                    if ($selectHours[$i] == $hours_value) {
                        echo "<option style='font-size:25px;width:50px;height:45px;' value=" . $selectHours[$i] . " selected='selected' >" . $selectHours[$i] . "</option>";
                    } else {
                        echo "<option style='font-size:25px;width:50px;height:45px;' value=" . $selectHours[$i] . ">" . $selectHours[$i] . "</option>";
                    }
                }
                echo "</select></td>";

                echo "<td><select id='minutes' size='1' name='minutes[]' style='height:30px;text-align:center;font-size:20px;' selected='selected'>";
                
                for ($i = 0; $i < count($selectMinutes); $i++) {  

                    if ($selectMinutes[$i] == $minutes_value) {
                        echo "<option style='font-size:40px;width:50px;height:45px;' value=" . $selectMinutes[$i] . "  selected='selected' >" . $selectMinutes[$i] . "</option>";
                    } else {
                        echo "<option style='font-size:40px;width:50px;height:45px;' value=" . $selectMinutes[$i] . ">" . $selectMinutes[$i] . "</option>";
                    }

                } # for ($i = 0; $i < count($selectMinutes); $i++) {
  
                echo "</select></td></tr>";

                echo "<input type='hidden' name='activities[]' value='$activity'>"; # save the activities  
   
            } # while ($info = mysqli_fetch_array($result)) {

        } # if (mysqli_num_rows($result) > 0) {

        echo "</table>";

        echo "<div class='formlabel'>&nbsp</div>";

        echo "<div class='formlabel'>Submit</div>
            <div class='formfield'>
            <input type='submit' value='Submit' size='20' style='width:200px; height:40px;background-color:Aqua;'></div>";

        echo "<div class='formlabel'>&nbsp</div>";

        echo "<div class='formlabel'>Time In/Out</div><div class='formfield'><a href='vms_attendance_62.php'>Time In/Out</a></div>";

        echo "</form></body></html>"; 

    } # if (mysqli_num_rows($result) < 1)


} else { # if (!$_POST) {  =================================================   

	##### 1 - get the selected activities and corresponding time from the previous screen
	
    //-get the selected activities and corresponding hours and minutes
    $activities = array();
    $counter = 0;
    foreach($_POST['activities'] as $key => $activity) {
        $counter++;
        $activities[$counter] = $activity;
    } // end-foreach 
  
    //-construct table for hours
    $hours = array();
    $counter = 0;
    foreach($_POST['hours'] as $key => $hour) {  
        $counter++;
        $hours[$counter] = $hour;
    } // end-foreach   

    //-construct table for minutes
    $minutes = array();
    $counter = 0;
    foreach($_POST['minutes'] as $key => $minute) {   
        $counter++;
        $minutes[$counter] = $minute;
    } // end-foreach  
    
    //-get total hours and minutes
    $totalHours = 0; 
    $totalMinutes = 0;
    for ($i = 1; $i <= count($minutes); $i++) {     
        if ($hours[$i] <> '' || $minutes[$i] <> '') {
            $totalHours   =+ $hours[$i];
            $totalMinutes =+ $minutes[$i];
        }
    }    

    ##### 2 - check if total actual time is more than company policy (6 hours or 360 minutes)
    
	  ##### 2.1 - get the total number of hours allowed per company policy
	
    //-get total actual number of minutes assigned to activities
    $actualMinutes = ($totalHours * 60) + $totalMinutes; 
    
    //-get maximum number of hours to charge per day per company policy 
    $query = "SELECT value 
                FROM sys_objects 
               WHERE system = 'sys' 
                 AND type = 'parameters' 
                 AND id = 'maximum_hours';";  
                     
    $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));
    
    if ($result) {
        $data = mysqli_fetch_array($result);
        $maximumMinutes = $data['value'] * 60;						# get equivalent in minutes
    } else {
        $maximumMinutes = 360; 										# 6 hours multiplied by 60 minutes
    }    
    
    ##### 2.2 - check if total actual time is more than company policy (6 hours or 360 minutes)
    
    if ($actualMinutes > $maximumMinutes ) {
       echo "<html><body><form action='$script' method='post' >
        <p><h2>NOTE! The total time assigned is more than FGC policy.</h2>
        <p><h3>FGC policy in minutes = $maximumMinutes, the assigned minutes = $actualMinutes.</h3>"; 
        $message = 'Please click anywhere on this shaded area to go back to Time Out screen...Thanks!';
        echo "<p><input type='submit' value='$message' style='white-space: normal; color: red; font-weight:bold; font-size:  25px; height:250px; width:600px;'></p>";
        echo "</form></body></html>";    
        exit;     
    }

	if (!$trace) {
    //-check if total actual number of assigned minutes is more than the time duration between time-in and time-out
    if ($actualMinutes > $_SESSION["totalMinutes"]) {
        $durationMinutes = $_SESSION["totalMinutes"];
        echo "<html><body><form action='$script' method='post' >
        <p><h2>NOTE! The total time assigned is more than the actual time duration.</h2>
        <p><h3>The actual time duration in minutes = $durationMinutes, the assigned minutes = $actualMinutes.</h3>"; 
        $message = 'Please click anywhere on this shaded area to go back to Time Out screen...Thanks!';
        echo "<p><input type='submit' value='$message' style='white-space: normal; color: red; font-weight:bold; font-size:  25px; height:250px; width:600px;'></p>";
        echo "</form></body></html>";  
        exit;
    } 
	}
     
    //-distribute the total hours and minutes to selected activities during time-in
    //-check total hours and minutes against pre-computed total hours and minutes
    $computedTotalMinutes = $totalHours * 60 + $totalMinutes;
    $computedHours     = intval($computedTotalMinutes / 60);
    $computedMinutes   = $computedTotalMinutes - ($computedHours * 60); 
    if ($computedHours <> $timeHours) {
        $hoursDifference = $computedHours - $timeHours;
    }
    if ($computedMinutes <> $timeMinutes) {
        $minutesDifference = $computedMinutes - $timeMinutes;
    }

	if ($hoursDifference > 0 || $minutesDifference > 0) {
 
        echo "<html><body><form action='vms_attendance_62.php' method='post' >"; 
        
        if ($hoursDifference <> 0) {
            #echo "<br>" . "Difference in Hours = ". $hoursDifference . "<br>";
            #echo "<br>" . "Computed VMS Hours  = ". $timeHours . "<br>";
            #echo "<br>" . "Allocated Hours     = ". $computedHours . "<br>";
        }
        if ($minutesDifference <> 0){
            #echo "<br>" . "Difference in Minutes = ". $minutesDifference . "<br>";
            #echo "<br>" . "Computed VMS Minutes  = ". $timeMinutes . "<br>";
            #echo "<br>" . "Allocated Minutes     = ". $computedMinutes . "<br>";
        }
        
        $message = 'Please click anywhere on this shaded area to go back to Main Processes...Thanks!';
        echo "<p><input type='submit' value='$message' style='white-space: normal; color: green; font-weight:bold; font-size: 25px; height:250px; width:600px;'></p>";
        
        echo "</form></body></html>"; 
        
    } # if ($hoursDifference > 0 || $minutesDifference > 0) {
    

    ##### 3 - create records for each activity 
    
    #echo "<br>get the time-in time from the time-in record(s)";
    $query = "SELECT * FROM actvty_trans
               WHERE id      = '$userId'
                 AND tdate   = '$timeOutDate'
                 AND timein  <> ''
                 AND (timeout IS NULL
                      OR timeout = ''
                      OR timeout = '00:00:00');";

    $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));
  
    if (mysqli_num_rows($result) < 1) {
        #echo "<br>No activities for Id = " . $userId . " on " . $timeOutDate;
    } else {
        while($rows = mysqli_fetch_array($result)){
            $timeIn = $rows['timein'];
        }
    }
 
    #echo "<br>time-in of activity transaction record = " . $timeIn; 

    #echo "<br>create record for each selected activity";

    #echo "<br>create record for each activity with hours and minutes";
	for ($i = 1; $i <= count($activities); $i++) {  
        #echo "<br>" . "activity = " . $activities[$i] . ";   hours = " . $hours[$i] . ";   minutes = " . $minutes[$i];
        if ($hours[$i] <> '0' || $minutes[$i] <> '0') {
            #echo "<br>create record for activities with assigned time";   
            #echo "<br>time out time = ". $timeOutTime;  
            $insert_sql = "INSERT into actvty_trans
                                 (id, activity, hours, minutes,
                                  tdate, timein, timeout)
                           VALUES ('$userId', '$activities[$i]', '$hours[$i]', 
                                   '$minutes[$i]', '$timeOutDate', '$timeIn', '$timeOutTime');";
     
            $result = mysqli_query($db_connection, $insert_sql) 
                      or die (mysqli_error($db_connection)); 

        } # if ($hours[$i] <> '0' || $minutes[$i] <> '0') {
    } # for ($i = 1; $i <= count($activities); $i++) {  

    #echo "<br>delete activities with zero hours and minutes";
    $delete_sql = "DELETE FROM actvty_trans
                    WHERE id = '$userId'
                      AND tdate = '$timeOutDate'
                      AND (timeout IS NULL or timeout = '00:00:00')
                      AND ((hours IS NULL AND minutes IS NULL)
                            OR (hours = '0' AND minutes = '0'));"; 
  
    $result = mysqli_query($db_connection, $delete_sql); 
              //or die (mysqli_error($db_connection)); 
  
    if ($result) {
        #echo "<br>activity transactions records deleted";
    } else {
        #echo "<br>ERROR, activity transactions records not deleted becase <br>" . mysqli_error($db_connection);
    }        
  
    #echo "<br>delete activities with '00:00:00' time-in";
    $delete_sql = "DELETE FROM actvty_trans
                    WHERE id = '$userId'
                      AND tdate = '$timeOutDate'
                      AND timein = '00:00:00';"; 
  
    $result = mysqli_query($db_connection, $delete_sql); 
              //or die (mysqli_error($db_connection)); 
  
    if ($result) {
        #echo "<br>activity transactions records deleted";
    } else {
        #echo "<br>ERROR, activity transactions records not deleted becase <br>" . mysqli_error($db_connection);
    }      

	##### 4 - create summary record for each activity

	##### 4.1 - get the start and end date of current volunteer year	
	$start_date = substr($timeOutDate,0,4) . substr($joining_date,4,6);
    #echo "<br>-start date = " . $start_date;
    //-get end date, add 1 year to start date
    $end_date   = substr($start_date,0,4) + 1  . substr($joining_date,4,6); 
    //-subtract 1 day from computed end date 
    $end_date   = strtotime ( '-1 day', strtotime ($end_date)); 
    //-format the end date to yyyy-mm-dd
    $end_date   = date ( 'Y-m-j', $end_date ); 
    #echo "<br>-end date = " . $end_date;  
    #echo "<br>end date length = " . strlen($end_date);
    //-respect the 'bug' when the day of the date is less than 10 (1-9)
    if (strlen($end_date) == 9) {
    	$end_date = substr($end_date,0,8) . '0' . substr($end_date,8,1);
    	#echo "<br>-end date = " . $end_date;      	
    } 
    //echo "<br>#-end date = " . $end_date;  

    #echo "<br>get total hours for each combination of id + date of time-out + activity";
    for ($i = 1; $i <= count($activities); $i++) {  
        //echo "<br>activity = " . $activities[$i] . ";   hours = " . $hours[$i] . ";   minutes = " . $minutes[$i];
        if ($hours[$i] <> '0' || $minutes[$i] <> '0') {
            $total_sql = "SELECT sum(hours) as thours, sum(minutes) as tminutes 
                            FROM actvty_trans
                           WHERE id = '$userId' 
                             AND tdate = '$timeOutDate'
                             AND activity = '$activities[$i]';";
  
            $result = mysqli_query($db_connection, $total_sql) 
                      or die (mysqli_error($db_connection));

            if ($result) {
                while($rows = mysqli_fetch_array($result)){ 
                    $tHours = $rows['thours'];
                    $tMinutes = $rows['tminutes'];
                }
                #echo "<br>compute total hours and minutes";
                $sumryHours   = $tHours + (int)($tMinutes / 60);
                $sumryMinutes = $tMinutes % 60;
            }

            //-check if there is an existing activity summary record
            $query = "SELECT * 
                        FROM actvty_sumry
                       WHERE id            = '$userId'
                         AND activity      = '$activities[$i]'
                         AND datefrom      = '$start_date'
                         AND dateto        = '$end_date';";
						//echo "<br>##query1 = " . $query;
            $result = mysqli_query($db_connection, $query) 
                      or die (mysqli_error($db_connection));

            //if ($result) {
            if (mysqli_affected_rows($db_connection) > 0) {
                while($rows = mysqli_fetch_array($result)){ 
                    $balanceHours = $rows['balance_hours'] + $sumryHours;
                    $balanceMinutes = $rows['balance_mints'] + $sumryMinutes;
            	}        
            	$query = "UPDATE actvty_sumry
                	         SET balance_date  = '$timeOutDate',
                	             balance_hours = '$balanceHours',
                	             balance_mints = '$balanceMinutes'
                	       WHERE id            = '$userId'
                	         AND activity      = '$activities[$i]'
                	         AND datefrom      = '$start_date'
                	         AND dateto        = '$end_date';";
							//echo "<br>##query2 = " . $query;
            	$result = mysqli_query($db_connection, $query) 
                	      or die (mysqli_error($db_connection));  
     
            	if (mysqli_affected_rows($db_connection) > 0) {
                	//echo "<br>##Activity summary record updated";
            	} else {
                	echo "<br>ERROR: Activity summary record NOT UPDATED!"; 
                	echo "<br>MySQL Error  = " . mysqli_error($db_connection);         
                	echo "<br>Volunteer Id = " . $userId; 
                	echo "<br>Activity     = " . $activities[$i]; 
                	echo "<br>Start Date   = " . $start_date;   
                	echo "<br>End Date     = " . $end_date;             
            	}
        	} else { # if ($hours[$i] <> '0' || $minutes[$i] <> '0') 
            	$query = "INSERT INTO actvty_sumry
            	                      (id, activity, balance_date,
            	                       balance_hours, balance_mints, datefrom, dateto)
            	               VALUES ($userId, '$activities[$i]', '$timeOutDate',
            	                       '$sumryHours', '$sumryMinutes', '$start_date', '$end_date');";  
       				//echo "<br>##query3 = " . $query;
            	$result = mysqli_query($db_connection, $query) 
            	          or die (mysqli_error($db_connection)); 
        
        	    if (mysqli_affected_rows($db_connection) > 0) {
            	    //echo "<br>##Activity summary (actvty_sumry) record created<br>";
            	} else {
            	    //echo "ERROR! - activity summary record not created becase <br>" . mysqli_error($db_connection);
            	    echo "<br>ERROR: Activity summary record NOT CREATED!"; 
            	    echo "<br>MySQL Error  = " . mysqli_error($db_connection);         
            	    echo "<br>Volunteer Id = " . $userId; 
            	    echo "<br>Activity     = " . $activities[$i]; 
            	    echo "<br>Start Date   = " . $start_date;   
            	    echo "<br>End Date     = " . $end_date;             
            	}        
        	} # if (mysqli_affected_rows($db_connection) > 0) {

    	} # if ($hours[$i] <> '0' || $minutes[$i] <> '0') {

    } # for ($i = 1; $i <= count($activities); $i++) {  

    ##### 5 - display TIME data

    echo "<table border='' cellpadding='4' cellspacing='0'>"; 
    echo "<tr><th colspan=3>Summary Data For ID: $userId, Joining Date: $joining_date (BELOW DATA IS UNOFFICIAL, IT IS FOR TESTING AND VERIFICATION)</th></tr>"; 
	echo "</table>";

	echo "<br>";	        
    echo "<table border='' cellpadding='4' cellspacing='0'>";        
    echo "<tr><th colspan=3>TIME</th></tr>";    
    echo "<tr><th>Description</th><th>Hours</th><th>Minutes</th></tr>";

 	##### 5.1 - get total hours and minutes for the current date
	if ($trace) echo "<br>5.1 - get total hours and minutes for the current date";

    $total_sql = "select sum(hours) as thours, sum(minutes) as tminutes 
                    from actvty_trans
                   where id = '$userId' and tdate = '$timeOutDate';";

	echo "<br>total_sql = " . $total_sql;
  
    $result = mysqli_query($db_connection, $total_sql) 
              or die (mysqli_error($db_connection));

    if ($result) {
        while($rows = mysqli_fetch_array($result)){ 
            $tHours   = $rows['thours'];
            $tMinutes = $rows['tminutes'];
        }
        $dayHours   = $tHours + (int)($tMinutes / 60);
        $dayMinutes = $tMinutes % 60;
    }    

	echo "<tr><td>Today</td><td align=center>$dayHours</td><td align=center>$dayMinutes</td></tr>";


    #### 5.2 - get total hours and minutes for the anniversary years

	/*
	1. Joining date before Aug 1, 2019 (id=1000)
		joining date =		2018-02-12
		1st anniversary =	2019-02-12 - 2020-02-11
		2nd anniversary = 	2020-02-12 - 2021-02-11
		1st reward =		2019-08-01 - 2020-02-11
		2nd reward = 		2020-02-12 - 2021-02-11
		3rd reward = 		2021-02-12 - 2022-02-11
	2. Joining date after Augu 1, 2019 (id=1001)
		joining date = 		2018-10-24
		1st anniversary = 	2019-08-01 - 2019-10-23
		2nd anniversary = 	2019-10-24 - 2020-10-23
		3rd anniversary = 	2020-10-24 - 2021-10-23
		1st reward = 		2019-08-01 - 2019-10-23
		2nd reward = 		2019-10-24 - 2020-10-23
		3rd reward = 		2020-10-24 - 2021-10-23
	3. Joining date after Aug 1, 2019 (id=1002)
		joining date = 		2017-12-15
		1st anniversary = 	2018-08-15 - 2019-12-14
		2nd anniversary = 	2019-12-15 - 2020-12-14
		1st reward = 		2019-08-01 - 2019-12-14
		2nd reward = 		2019-12-15 - 2020-12-14
		3rd reward = 		2020-12-15 - 2021-08-14
	4. Joining date after Aug 1, 2019 (id=1003)
		joining date = 		2020-01-18
		1st anniversary = 	2020-01-18 - 2021-01-17
		2nd anniversary = 	2021-01-18 - 2022-01-17
		1st reward = 		2020-01-18 - 2021-01-17
		2nd reward = 		2021-01-18 - 2022-01-17
	*/

	if ($trace) echo "<br>5.2 - get total hours and minutes for the anniversary year";
    if ($trace) {echo "<br><strong>userId = </strong>" . $userId . "<strong> joining date = </strong>" .  $joining_date;}
    if ($trace) {echo "<br>userId = " . $userId . " current year start date = " .  $start_date, " end date = " . $end_date;}

	// get the anniversary years and save in array
	//$anniversaryYears = array( array() );				# two dimensional array
	$startDates = array();
	$endDates = array();

	// start year
	if (substr($joining_date,0,4) <= '2019') {
		$startYear = '2019';
	} else {
		$startYear = substr($start_date,0,4);
	}

	// end of current year = year of timeout date
	$endYear = substr($timeOutDate,0,4);
	if ($trace) {echo "<br>start year = " . $startYear . ", end year =  " . $endYear;}

	// get the start date and end date for each anniversary years
	// with consideration of Aug 1, 2019 new rewards policy effectivity date
	$index = 0;
	for ($year = $startYear; $year <= $endYear; $year++) {

		if ($trace) {echo "<br>year counter = $year";}
/*
		// exclude years with start date after current date
		$startDate = $year . substr($start_date,4,6);

		if ($startDate < $timeOutDate) {

			$index++;

			$startDates[$index] = $startDate;

			$endDates[$index] = $year + 1 . substr($end_date,4,6);

			if ( ($year == '2019') && (substr($joining_date,5,2)) < 8) {	# month of joining date is before August

				$startDates[$index] = '2019-08-01';

			}

		}		
*/
		$index++;

		if ($index == 1) {

			if ($year == '2019') {

				$startDates[$index]	= '2019-08-01';
				if ( (substr($joining_date,5,2)) > 7) {
					$endDates[$index] 	= "2019" . substr($end_date,4,6);
				} else {
					$compDate	= strtotime ( ' - 1 day', strtotime ($start_date));
					$endDates[$index] 	= date ( 'Y-m-j', $compDate ); 
				}

			} else {

				$startDates[$index]	= $year . substr($start_date,4,6);
				//$endDates[$index] 	= $year . substr($end_date,4,6); 
				$endDates[$index]	= $end_date;

			}								

		} else {

			//  $end_date   = strtotime ( '-1 day', strtotime ($end_date)); 
			$tempDate	= strtotime ( ' +1 day', strtotime ($endDates[$index - 1]));
			$startDates[$index] = date ( 'Y-m-j', $tempDate ); 
			$tempDate = $startDates[$index];
			$endDates[$index]	= substr($tempDate,0,4) + 1 . substr($end_date,4,6); 

		}

	} # for ($year = $startYear; $year <= $endYear; $year++)

	if ($trace) {	
		for ($i = 1; $i <= count($endDates); $i++) {
			echo "<br>year = $i, Start Date = $startDates[$i], End Date = $endDates[$i]";	
		}
	}


	// function get hours for each anniversay year 
	function getHours ($userId, $startDate, $endDate) {

		include '../lis/db_connection.php';

    	$total_sql = "SELECT sum(hours) as thours, sum(minutes) as tminutes 
        	            FROM actvty_trans
        	           WHERE id = '$userId' 
        	             AND tdate between '$startDate' and '$endDate';"; 

		echo "<br>total_sql = " . $total_sql;

    	//echo "<br>#query = " . $total_sql;
    	$result = mysqli_query($db_connection, $total_sql) 
        	      or die (mysqli_error($db_connection));

    	if ($result) {
        	while($rows = mysqli_fetch_array($result)){ 
        	    $tHours = $rows['thours'];
        	    $tMinutes = $rows['tminutes'];
        	}
        	$yearHours   = $tHours + (int)($tMinutes / 60);
        	$yearMinutes = $tMinutes % 60;
		}

		$minutes = $tHours * 60 + $tMinutes;

		return $minutes;

	} # function getHours ($userId, $startDate, $endDate) 


	// function to create rewards for 25, 50, and 75 hours
	function createReward ($userId, $startDate, $endDate, $rewardId, $rewardText, $rewardDate) {

		include '../lis/db_connection.php';

		// check if there is existing record for the reward id and start date
 		$query = "SELECT * FROM accnt_sumry
 	              WHERE id = '$userId'
					AND account = '$rewardId'
					AND datefrom = '$startDate';"; 

	    $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));
 
		$row = mysqli_fetch_array($result); 
	
	    if (!$row) {														# check if there is no reward record 

			// declare standard values
			$rewardAmount = '25.00';			# standard reward amount
			$rewardAdd = '+';					# the plus sign indicates addition to total amount of rewards
				
			$insert = "INSERT into accnt_trans
		    	          (id, account, amount,
       						effect, reference, tdate)
               		   VALUES ('$userId', '$rewardId', '$rewardAmount', 
	                	            '+', '$rewardText', '$rewardDate');";

			$result1 = mysqli_query($db_connection, $insert) or die (mysqli_error($db_connection));	

			// get record number for reference to accnt_sumry
			if ($result1) {
				$ref_accnt_trans = mysqli_insert_id($db_connection);
			}

	        // create account summary records for rewards

       		$insert2 = "INSERT into accnt_sumry  
               	          (id, account, balance_date, 
               	           balance_start, balance_new, 
               	           datefrom, dateto, ref_accnt_trans)
                	   VALUES ('$userId', '$rewardId', '$rewardDate',
               	           '$rewardAmount', '$rewardAmount',
               	           '$startDate', '$endDate', '$ref_accnt_trans');";

       		$result2 = mysqli_query($db_connection, $insert2) or die (mysqli_error($db_connection));
			
 		} # if (!$row) {	

	} # function create25HourRewards ($userId, $startDate, $endDate, $totalMinutes)

	
	// get the total hours and minutes for every anniversay year 
	// and create rewards for each anniversay year by iterating
	// on the saved years in the array '$startDates' and '$endDates'
	if ($trace) {echo "<br><br>iterate on saved start and end date, get the total hours and minutes for every anniversay year ";}

	for ($i = 1; $i <= count($endDates); $i++) {

		// get reward start and end dates for use when printing the rewards in item 6.3
		if ($i == 1) {
			$rewardStartDate = $startDates[$i];		
		}
		$rewardEndDate = $endDates[$i];		

		// set up each anniversary year 
		if ($trace) {echo "<br><br>year = $i, Start Date = $startDates[$i], End Date = $endDates[$i]";}

		$startDate = $startDates[$i];
		$endDate = $endDates[$i];
	
		// use function to get hours
		$totalMinutes = getHours ($userId, $startDates[$i], $endDates[$i]);

		if ($trace) {echo "<br>total minutes = $totalMinutes";}	

		// check if rewards should be created (total hours should be more than 24)
		$totalHours = (int)($totalMinutes / 60);
		$rewardHours = (int)($totalMinutes / 60);
 		
		if ($trace) {echo "<br>reward hours = $rewardHours";}	

 		if ($rewardHours > 24 and $rewardHours < 50) {				# check for 25 hours reward

			// create 25 hour reward
			$rewardId = "25_hour_reward";   
			$rewardText = "Reward for 25 hours";
			createReward ($userId, $startDate, $endDate, $rewardId, $rewardText, $timeOutDate);
 			
 		} elseif ($rewardHours > 49 and $rewardHours < 75) {				# check for 50 hours reward

 			// create 25 hour reward 
			$rewardId = "25_hour_reward";   
			$rewardText = "Reward for 25 hours";
			createReward ($userId, $startDate, $endDate, $rewardId, $rewardText, $timeOutDate);
			
			// create 50 hour reward
			$rewardId = "50_hour_reward";   
			$rewardText = "Reward for 50 hours";
			createReward ($userId, $startDate, $endDate, $rewardId, $rewardText, $timeOutDate);
 		
 		} elseif ($rewardHours > 74) {															# check for 75 hours reward

 			//-check if previously awarded 25, 50, and 75_hour_rewards	
 			// create 25 hour reward 
			$rewardId = "25_hour_reward";   
			$rewardText = "Reward for 25 hours";
			createReward ($userId, $startDate, $endDate, $rewardId, $rewardText, $timeOutDate);
			
			// create 50 hour reward
			$rewardId = "50_hour_reward";   
			$rewardText = "Reward for 50 hours";
			createReward ($userId, $startDate, $endDate, $rewardId, $rewardText, $timeOutDate);
			
			// create 75 hour reward
			$rewardId = "75_hour_reward";   
			$rewardText = "Reward for 75 hours";
			createReward ($userId, $startDate, $endDate, $rewardId, $rewardText, $timeOutDate);
	
 		} # if ($rewardHours > 24 and $rewardHours < 50)

				
	} # for ($i = 1; $i <= count($endDates); $i++) 


	// display hours and minutes for current anniversary year
	// the hours and minutes are taken from the last anniversary year 
	// that was processed in the above 'for' loop 
	$yearHours = (int)($totalMinutes / 60); 
	$yearMinutes = $totalMinutes - ($yearHours * 60);
	echo "<tr><td>Anniversay Year</td><td align=center>$yearHours</td><td align=center>$yearMinutes</td></tr>";	


	#####
	// get hours since joining date
    $total_sql = "select sum(hours) as thours, sum(minutes) as tminutes 
                    from actvty_trans where id = $userId;";

	echo "<br>total_sql = " . $total_sql;

    $result = mysqli_query($db_connection, $total_sql) 
              or die (mysqli_error($db_connection));
    if ($result) {
        while($rows = mysqli_fetch_array($result)){ 
            $tHours = $rows['thours'];
            $tMinutes = $rows['tminutes'];
        }
        $totalHours   = $tHours + (int)($tMinutes / 60);
        $totalMinutes = $tMinutes % 60;
    }

	echo "<tr><td>Since Joining Date</td><td align=center>$totalHours</td><td align=center>$totalMinutes</td></tr>";
	echo "</table>";


	##### 6.3 get amounts, availments, and balances
	if ($trace) echo "<br>6.3 get amounts, availments, and balances";
	if ($trace) echo "<br>6.5 display rewards";
/*
	$query = "SELECT *, tdate  
				FROM accnt_sumry
				INNER JOIN accnt_trans
				ON accnt_sumry.ref_accnt_trans = accnt_trans.recnum
               WHERE accnt_sumry.id = '$userId' 
                AND accnt_sumry.account like '%hour%'
                AND (datefrom >= '$rewardStartDate'
				   and dateto <= '$rewardEndDate');";  
*/	
	$query = "SELECT *, tdate  
				FROM accnt_sumry
				INNER JOIN accnt_trans
				ON accnt_sumry.ref_accnt_trans = accnt_trans.recnum
               WHERE accnt_sumry.id = '$userId' 
                AND accnt_sumry.account like '%hour%'
                AND datefrom >= '$rewardStartDate';";  

	if ($trace) echo "<br>query = $query";          
            
    $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));
              
    //if ($result) {
	if (mysqli_num_rows($result) > 0) {

		if ($trace) echo "<br>number of rows = " . mysqli_num_rows($result);  

		echo "<br>
        	<table border='' cellpadding='4' cellspacing='0'> 
        	<tr><th colspan=7>REWARDS</th></tr>    
        	<tr><th>Hours</th><th>Awarded</th><th>Amount</th><th>Availment</th><th>Balance</th><th>From</th><th>To</th></tr>";
    
        // compute for balances
        $balanceStartTotal = $balanceEndTotal = 0;
        
        while($rows = mysqli_fetch_array($result)) { 
        
        	if ($rows['account'] === '25_hour_reward') {

        	    $awarded25 = $rows['tdate'];  
        	    $balanceStart25 = $rows['balance_start'];  
        	    $balanceStartTotal += $balanceStart25;      	
        		$balanceEnd25 = $rows['balance_new']; 
        		$balanceEndTotal += $balanceEnd25;  
        		$availment25 = $balanceStart25 - $balanceEnd25;  
        		$availmentTotal += $availment25; 
        		$availment25 = number_format($availment25, 2, '.', ',');  
				$fromDate25 = $rows['datefrom'];
				$toDate25 = $rows['dateto']; 
				echo "<tr><td>Reward For 25 Hours</td><td>$awarded25</td><td align=center>$balanceStart25</td><td align=center>$availment25</td><td align=center>$balanceEnd25</td><td>$fromDate25</td><td>$toDate25</td></tr>";

        	}
        	
        	if ($rows['account'] === '50_hour_reward') {

        	    $awarded50 = $rows['tdate']; 
        	    $balanceStart50 = $rows['balance_start'];   
        	    $balanceStartTotal += $balanceStart50;          	         	
        		$balanceEnd50 = $rows['balance_new']; 
        		$balanceEndTotal += $balanceEnd50;  
        		$availment50 = $balanceStart50 - $balanceEnd50; 
        		$availmentTotal += $availment50; 
        		$availment50 = number_format($availment50, 2, '.', ',');
				$fromDate50 = $rows['datefrom'];
				$toDate50 = $rows['dateto'];  
				echo "<tr><td>Reward for 50 Hours</td><td>$awarded50</td><td align=center>$balanceStart50</td><td align=center>$availment50</td><td align=center>$balanceEnd50</td><td>$fromDate50</td><td>$toDate50</td></tr>";  

        	}
        	
        	if ($rows['account'] === '75_hour_reward') {

        	    $awarded75 = $rows['tdate']; 
        	    $balanceStart75 = $rows['balance_start']; 
        	    $balanceStartTotal += $balanceStart75;          	           	
        		$balanceEnd75 = $rows['balance_new']; 
        		$balanceEndTotal += $balanceEnd75;
        		$availment75 = $balanceStart75 - $balanceEnd75; 
        		$availment75 = number_format($availment75, 2, '.', ',');                 		
        		$availmentTotal += $availment75;    
				$fromDate75 = $rows['datefrom'];
				$toDate75 = $rows['dateto'];  
				echo "<tr><td>Reward for 75 Hours</td><td>$awarded75</td><td align=center>$balanceStart75</td><td align=center>$availment75</td><td align=center>$balanceEnd75</td><td>$fromDate75</td><td>$toDate75</td></tr>";	 

        	}
        	
        } # while($rows = mysqli_fetch_array($result))

    } 
    
    $balanceStartTotal = number_format($balanceStartTotal, 2, '.', ',');
    $availmentTotal = number_format($availmentTotal, 2, '.', ',');  
    $balanceEndTotal = number_format($balanceEndTotal, 2, '.', ',');   

    echo "<tr><td>Total</td><td align=center>-</td><td align=center>$balanceStartTotal</td><td align=center>$availmentTotal</td><td align=center>$balanceEndTotal</td><td align=center colspan=2>-</td></tr>";	
    echo "</table>";  

	##### 6.5 display rewards  

	# Rewards (must be availed within anniversary year: from mmm-dd-yyyy to mmm-dd-yyy)
	#  (If the volunteer did not report for more than 90 days, the rewards will be forfeited
	#   and the volunteer must attend re-orientation)
	#  		Hours  		Amount		Awarded			Availment		Balance		Expiration
	#  	 After 25		$25.00		mmm-dd-yyy		$20.00			$05.00		mmm-dd-yyy
	#    After 50		$25.00		mmm-dd-yyy		$00.00			$25.00		mmm-dd-yyy
	#    After 75		$25.00		mmm-dd-yyy		$00.00			$25.00		mmm-dd-yyy
	#  		Total 		$75.00						$20.00			$55.00		mmm-dd-yyy
	# 
/*
	if ($trace) echo "<br>6.5 display rewards";
                    
	echo "<br>
        <table border='' cellpadding='4' cellspacing='0'> 
        <tr><th colspan=7>REWARDS</th></tr>    
        <tr><th>Hours</th><th>Awarded</th><th>Amount</th><th>Availment</th><th>Balance</th><th>From</th><th>To</th></tr>";

	echo "<tr><td>Reward For 25 Hours</td><td>$awarded25</td><td align=center>$balanceStart25</td><td align=center>$availment25</td><td align=center>$balanceEnd25</td><td>$fromDate25</td><td>$toDate25</td></tr>";
	
	echo "<tr><td>Reward for 50 Hours</td><td>$awarded50</td><td align=center>$balanceStart50</td><td align=center>$availment50</td><td align=center>$balanceEnd50</td><td>$fromDate50</td><td>$toDate50</td></tr>";
	
	echo "<tr><td>Reward for 75 Hours</td><td>$awarded75</td><td align=center>$balanceStart75</td><td align=center>$availment75</td><td align=center>$balanceEnd75</td><td>$fromDate75</td><td>$toDate75</td></tr>";	
	
	echo "<tr><td>Total</td><td align=center>-</td><td align=center>$balanceStartTotal</td><td align=center>$availmentTotal</td><td align=center>$balanceEndTotal</td><td align=center colspan=2>-</td></tr>";	
	
	echo "</table>";	
*/
	
 	##### 7 - display store credits if there any transactions	

	 	  		
	# Credits (must be used within 90 days after 'awarded' date)
	#	Amount		Awarded		Expiration		Particulars
	#	 $20		mmm-dd-yyy	mmm-dd-yyy		Returns per receipt # 2319
	#	

	$query = "SELECT account, tdate
                FROM accnt_trans
               WHERE id = '$userId' 
                 AND account like '%credit%' 
                 AND (tdate >= '$rewardStartDate'
                       and tdate <= '$rewardEndDate');"; 

	if ($trace) echo "<br>query = $query";        
 
    $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));
              
    //if ($result) {
	//$row = mysqli_fetch_array($result); 

	//if (!$row) {
	if (mysqli_num_rows($result) > 0) {

		echo "<br>
        	<table border='' cellpadding='4' cellspacing='0'> 
        	<tr><th colspan=6>CREDITS</th></tr>    
        	<tr><th>Awarded</th><th>Amount</th><th>Availment</th><th>Balance</th><th>Expiration</th><th>Particulars</th></tr>";	
		echo "</table>";

        while($rows = mysqli_fetch_array($result)){ 


		}

	}
	

 	##### 8 - display discounts if there are any transactions
	# 			
	# Discounts (teacher's, etc)
	#	Percent		Awarded		Expiration		Particulars
	#	  20%		mmm-dd-yyy	mm-dd-yyy		For teaching Linux, Python, Hardware, etc.
	# 	

	$query = "SELECT account, tdate
                FROM accnt_trans
               WHERE id = '$userId' 
                 AND account like '%discount%' 
                 AND (tdate >= '$rewardStartDate'
                       and tdate <= '$rewardEndDate');"; 

	if ($trace) echo "<br>query = $query";        
 
    $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));
              

	if (mysqli_num_rows($result) > 0) {

		echo "<br>
        	<table border='' cellpadding='4' cellspacing='0'> 
        	<tr><th colspan=6>DISCOUNTS</th></tr>    
        	<tr><th>Awarded</th><th>Percent</th><th>Amount</th><th>Availment</th><th>Balance</th><th>Expiration</th><th>Particulars</th></tr>";	
		echo "</table>"; 	

        while($rows = mysqli_fetch_array($result)){ 


		}

	}
	

/*	
	// get first year based on the effectivity date of the new reward policy - Aug 1, 2019
	$newPolicyDate = '2019-08-01';
	if ($joining_date > $newPolicyDate) {
		$rewardStartDate = $joining_date;				# start date of current anniversary date
	} else {
		$rewardStartDate = $newPolicyDate;
	}
	if ($trace) {echo "<br>reward start date = $rewardStartDate";}

	// get start year
	$startYear = substr($rewardStartDate,0,4);
	if ($trace) {echo "<br>reward start year = $startYear";}

	// end year
	$endYear = substr($timeOutDate,0,4) + 1; 
	if ($trace) {echo "<br>reward end year = $endYear";}

	//for ($index = 1; $i <= count($activities); $i++) {
	$index = 0;
	for ($year = $startYear; $year <= $endYear; $year++) {
		if ($trace) {echo "<br>year counter = $year";}

		$index++;	
		if ($trace) {echo "<br>index = $index";}

		if ($index == 1) {	

			// get start and end date of year 1 after Aug 1, 2019
			// check if the start date is Aug 1, 2019
	
			if ($joining_date < $newPolicyDate) {				
				
				// joining date before Aug 1, 2019, start date is 2019-08-01
				$startDates[$index] = $newPolicyDate;			 

				// get the end date after Aug 1, 2019, based on the month of joining date
				if (substr($joining_date,5,2) < 8) {	# month of joining date

					$endDates[$index] = "2020" . substr($end_date,4,6);

				} else {

					$endDates[$index] = "2019" . substr($end_date,4,6);
					$index++;
					$startDates[$index] = "2019" . substr($start_date,4,6);
					$endDates[$index] = "2020" . substr($end_date,4,6);
				}	
				
			} else {
				
				// joining date is after Aug 1, 2019
				$startDates[$index] = $joining_date;
				$endDates[$index] = substr($joining_date,0,4) + 1 . substr($end_date,4,6);	
	
            }

		} else {	

			if ($joining_date < $newPolicyDate ) {

				if (substr($joining_date,5,2) > 7) {	# month of joining date
					$startDates[$index] = "2019" . substr($start_date,4,6);
					$endtDates[$index] = "2020" . substr($end_date,4,6);	
				
				} else {

					$startDates[$index] = "2020" . substr($start_date,4,6);
					$endtDates[$index] = "2021" . substr($end_date,4,6);	
				}	

			} else {	# if ($joining_date > $newPolicyDate 

				// for second and subsequent years
				if ($trace) {echo "<br>index = $index";}	
				$startDates[$index] = $year . substr($start_date,4,6);
				$endDates[$index] = $year + 1 . substr($end_date,4,6);

			}	

		} # if ($index == 1) 

		if ($trace) {echo "<br>year = $year, Start Date = $startDates[$index], End Date = $endDates[$index]";}
		
	} # for 

	for ($i = 1; $i <= count($endDates); $i++) {
		if ($trace) {echo "<br>year = $i, Start Date = $startDates[$i], End Date = $endDates[$i]";}
	
	}
*/

/*
	if ($joining_date > '2019-08-01') { 
    	
		// get total hours for current volunteer year
		if ($trace) {echo "<br>get total hours and minutes for the current volunteer year<br>";}
    	$total_sql = "SELECT sum(hours) as thours, sum(minutes) as tminutes 
        	            FROM actvty_trans
        	           WHERE id = '$userId' 
        	             AND (tdate >= '$start_date'
        	                  and tdate <= '$end_date');"; 

		echo "<br>total_sql = " . $total_sql;

    	//echo "<br>#query = " . $total_sql;
    	$result = mysqli_query($db_connection, $total_sql) 
        	      or die (mysqli_error($db_connection));

    	if ($result) {
        	while($rows = mysqli_fetch_array($result)){ 
        	    $tHours = $rows['thours'];
        	    $tMinutes = $rows['tminutes'];
        	}
        	$yearHours   = $tHours + (int)($tMinutes / 60);
        	$yearMinutes = $tMinutes % 60;
		}

    } else {
		
		// get total hours from Aug 1, 2019 up to end date of current volunteer year
    	if ($trace) {echo "<br>get total hours and minutes for the current volunteer year<br>";}

		// get reward end date
		if (substr($joining_date,5,2) < 8) {
			$reward_end_date = "2020" . substr($end_date,4,6);
		} else {
			$reward_end_date = "2019" . substr($end_date,4,6);
		}		

    	$total_sql = "SELECT sum(hours) as thours, sum(minutes) as tminutes 
        	            FROM actvty_trans
        	           WHERE id = '$userId' 
        	             AND (tdate >= '2019-08-01'
        	                  and tdate <= '$reward_end_date');"; 

		echo "<br>total_sql = " . $total_sql;

    	//echo "<br>#query = " . $total_sql;
    	$result = mysqli_query($db_connection, $total_sql) 
        	      or die (mysqli_error($db_connection));

    	if ($result) {
        	while($rows = mysqli_fetch_array($result)){ 
        	    $tHours = $rows['thours'];
        	    $tMinutes = $rows['tminutes'];
        	}
        	$yearHours   = $tHours + (int)($tMinutes / 60);
        	$yearMinutes = $tMinutes % 60;

		}

	} # if ($joining_date > '2019-08-01') {

    //echo "<br>#yearHours = " . $yearHours . " yearMinutes = " . $yearMinutes;

 	##### 5.3 - get totoal hours and minutes since joining date date
	if ($trace) echo "<br>5.3 - get totoal hours and minutes since joining date date";

    $total_sql = "select sum(hours) as thours, sum(minutes) as tminutes 
                    from actvty_trans where id = $userId;";

	echo "<br>total_sql = " . $total_sql;

    $result = mysqli_query($db_connection, $total_sql) 
              or die (mysqli_error($db_connection));
    if ($result) {
        while($rows = mysqli_fetch_array($result)){ 
            $tHours = $rows['thours'];
            $tMinutes = $rows['tminutes'];
        }
        $totalHours   = $tHours + (int)($tMinutes / 60);
        $totalMinutes = $tMinutes % 60;
    }

	##### 5.4 - display time data
	//- Time					Hours	Minutes
	//	Today					  hh	  mm
	//	Anniversary Year		  hh	  mm
	//	Since Joining Date		  hh	  mm	
	if ($trace) echo "<br>5.4 - display time data";

    echo "<table border='' cellpadding='4' cellspacing='0'>"; 
    echo "<tr><th colspan=3>Summary Data For ID: $userId, Joining Date: $joining_date (BELOW DATA IS UNOFFICIAL, IT IS FOR TESTING AND VERIFICATION)</th></tr>"; 
	echo "</table>";

	echo "<br>";	        
    echo "<table border='' cellpadding='4' cellspacing='0'>";        
    echo "<tr><th colspan=3>TIME</th></tr>";    
    echo "<tr><th>Description</th><th>Hours</th><th>Minutes</th></tr>";
	echo "<tr><td>Today</td><td align=center>$dayHours</td><td align=center>$dayMinutes</td></tr>";
	echo "<tr><td>Anniversay Year</td><td align=center>$yearHours</td><td align=center>$yearMinutes</td></tr>";	
	echo "<tr><td>Since Joining Date</td><td align=center>$totalHours</td><td align=center>$totalMinutes</td></tr>";
	echo "</table>";

	#### 5.5 - temporary get/display REWARDS hours and minutes for new policy 
	if ($trace) echo "<br>5.5 - temporary display REWARDS hours and minutes for new policy";

	if ($trace) {echo "<br>joining date = " . $joining_date;}

	$reward_start_date = '2019-08-01';			# 'new reward policy' effectivity date 

	if ($joining_date >= $reward_start_date) {
		$reward_start_date = $start_date;		# anniversary joining date 
		$reward_end_date = $end_date;
	} else {
		$reward_start_date = '2019-08-01';		# 'new reward policy' effectivity date 
		if (substr($joining_date,5,2) < 8) {	
			$reward_end_date = substr($reward_start_date,0,4) + 1 . substr($end_date,4,6);
		} else {
			$reward_end_date = substr($reward_start_date,0,4) . substr($end_date,4,6);
		}		
	}

	// joining date is on or after Aug 1, 2019, get hours for the anniversary year
	if ($joining_date >= $reward_start_date) { 

		// joining date is on or after Aug 1, 2019
		// get hours for the anniversary year
		if ($trace) {echo "<br>joining date is on or after Aug 1, 2019, get hours for the anniversary year";} 
		$total_sql = "SELECT sum(hours) as thours, sum(minutes) as tminutes 
    		            FROM actvty_trans
    		           WHERE id = '$userId' 
    		             AND (tdate >= '$reward_start_date'
   	 	                  and tdate <= '$reward_end_date');"; 

		echo "<br>total_sql = " . $total_sql;

		$result = mysqli_query($db_connection, $total_sql) or die (mysqli_error($db_connection));

		if ($result) {

			while($rows = mysqli_fetch_array($result)){ 

				$tHours = $rows['thours'];
				$tMinutes = $rows['tminutes'];

			}

			$rewardHours   = $tHours + (int)($tMinutes / 60);
			$rewardMinutes = $tMinutes % 60;
		}

	} else { # if ($joining_date => $reward_start_date)

		// joining date is before Aug 1, 2019
		// check if a reward was previously created starting Aug 1, 2019 up to end date of anniversary year
		if ($trace) {echo "<br>check if a reward was previously created starting Aug 1, 2019 up to end date of anniversary year";} 

		// get reward end date
		if (substr($joining_date,5,2) < 8) {
			$reward_end_date = substr($reward_start_date,0,4) + 1 . substr($end_date,4,6);
		} else {
			$reward_end_date = substr($reward_start_date,0,4) . substr($end_date,4,6);
		}		

		if ($trace) {echo "<br>reward end date = $reward_end_date";} 

		$rewards_sql = "SELECT * 
						FROM accnt_sumry
						WHERE id = '$userId'
						AND datefrom = '$reward_start_date';";

		if ($trace) {echo "<br>rewards sql = " . $rewards_sql;}

		$result1 = mysqli_query($db_connection, $rewards_sql) or die (mysqli_error($db_connection));

		if ($result1) {	

			// there is already reward created starting Aug 1, 2019
			// get hours for the volunteer year 

			$total_sql = "SELECT sum(hours) as thours, sum(minutes) as tminutes 
    			            FROM actvty_trans
    			           WHERE id = '$userId' 
    			             AND (tdate >= '$reward_start_date'
   	 	    		              and tdate <= '$reward_end_date');"; 

			if ($trace) {echo "<br>total sql = " . $total_sql;}

			$result = mysqli_query($db_connection, $total_sql) or die (mysqli_error($db_connection));

			if ($result) {

				while($rows = mysqli_fetch_array($result)){ 

					$tHours = $rows['thours'];
					$tMinutes = $rows['tminutes'];

				}

				$rewardHours   = $tHours + (int)($tMinutes / 60);
				$rewardMinutes = $tMinutes % 60;

			}

		} else {

			// there is no reward created starting Aug 1, 2019
			// get hours staring Aug 1, 2019 up to end date of the anniversary year
			// (start date => Aug 1, 2019 up to end date of anniversary year)
			if ($trace) {echo "<br>there is no reward created starting Aug 1, 2019, get hours staring Aug 1, 2019 up to end date of the anniversary";} 

			$total_sql = "SELECT sum(hours) as thours, sum(minutes) as tminutes 
							FROM actvty_trans
    			           	WHERE id = '$userId' 
    			           	AND (tdate >= '$reward_start_date'
   	 	    			    	and tdate <= '$reward_end_date');"; 

			echo "<br>total sql = " . $total_sql;

			$result = mysqli_query($db_connection, $total_sql) or die (mysqli_error($db_connection));

			if ($result) {

				while($rows = mysqli_fetch_array($result)){ 

					$tHours = $rows['thours'];
					$tMinutes = $rows['tminutes'];

				}

				$rewardHours   = $tHours + (int)($tMinutes / 60);
				$rewardMinutes = $tMinutes % 60;
			}

		} #	if ($result1) 

	} # if ($joining_date => $reward_start_date) 

	
	if ($trace) {echo "<br>#rewardHours = " . $rewardHours . " rewardMinutes = " . $rewardMinutes;} 

	#### 5.5.1 - display hours and minutes for new reward policy

	echo "<br>
	    <table border='' cellpadding='4' cellspacing='0'> 
	    <tr><th>New Rewards Policy</th><th>Hours</th><th>Minutes</th></tr>";  
	echo "<tr><td>Since August 1, 2019</td><td align=center>$rewardHours</td><td align=center>$rewardMinutes</td></tr>";
	echo "</table>";

	#### 6 - get REWARDS data based on reward hours and minutes (rewards hours and minutes in item 5.5 above)
	
 	# Rewards (must be availed within anniversary year: from mmm-dd-yyyy to mmm-dd-yyy)
	#  (If the volunteer did not report for more than 90 days, the rewards will be forfeited
	#   and the volunteer must attend re-orientation)
	#  		Hours  		Amount		Awarded			Availment		Balance		Expiration
	#  	 After 25		$25.00		mmm-dd-yyy		$20.00			$05.00		mmm-dd-yyy
	#    After 50		$25.00		mmm-dd-yyy		$00.00			$25.00		mmm-dd-yyy
	#    After 75		$25.00		mmm-dd-yyy		$00.00			$25.00		mmm-dd-yyy
	#  		Total 		$75.00						$20.00			$55.00		mmm-dd-yyy
	

    ##### 6.1 - create reward entitlement based on total hours
    #			$25.00 for first 25 hours     
 	#			$25.00 for first 50 hours
 	#			$25.00 for first 75 hours
 	#			no reward beyond 75 hours
	
	#echo "<br>year hours = " . $yearHours;

	if ($trace) echo "<br>6.1 create reward entitlement based on total hours";

	$rewardId25Hours = "25_hour_reward";   
	$rewardText25Hours = "Reward for 25 hours";
	if ($trace) echo "<br>25 hours reward = $rewardId25Hours , reward text = $rewardText25Hours";

	$rewardId50Hours = "50_hour_reward";   
	$rewardText50Hours = "Reward for 50 hours";
	if ($trace) echo "<br>50 hours reward = $rewardId50Hours , reward text = $rewardText50Hours";

	$rewardId75Hours = "75_hour_reward";   
	$rewardText75Hours = "Reward for 75 hours";	
	if ($trace) echo "<br>75 hours reward = $rewardId75Hours , reward text = $rewardText75Hours";
	
 	//if ($yearHours < 25 || $yearHours > 85) {
 	if ($rewardHours < 25) {
 		$rewardId = 'no_reward';
 		
 	} elseif ($rewardHours > 24 and $rewardHours < 50) {				# check for 25 hours reward
 		//-check if previously awarded (25_hour_reward)
 		$rewardId = $rewardId25Hours;                 		
 		$rewardText = $rewardText25Hours;  
 			
 	} elseif ($rewardHours > 49 and $rewardHours < 75) {				# check for 50 hours reward
 		//-check if previously awarded (50_hour_reward)
 		$rewardId = $rewardId50Hours; 		
 		$rewardText = $rewardText50Hours;
 		
 	} else {															# check for 75 hours reward
 		//-check if previously awarded (75_hour_reward)	
 		$rewardId = $rewardId75Hours; 
 		$rewardText = $rewardText75Hours;
 		
 	}

	if ($trace) echo "<br>total hours = $rewardHours , reward id = $rewardId , reard text = $rewardText";
	
	
    ##### 6.2 - check for existing reward data, if there is none, create reward record based on total hours
	if ($trace) echo "<br>6.2 check for existing reward data based on rewards Id of total hours";
    
    if ($rewardId <> 'no_reward') {	
     
 		$query = "SELECT * FROM accnt_sumry
 	              WHERE id = '$userId'
	                 AND datefrom = '$reward_start_date'
	                 AND account = '$rewardId';"; 

	 	if ($trace) {echo "<br>query = " . $query;}

	    $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));
 
		$row = mysqli_fetch_array($result); 
	
	    if (!$row) {														# check if there is no reward record 
   
            // create account transaction records for rewards
			if ($trace) {echo "<br>create account transaction records for rewards";}

	 	   	$rewardAmount = "25.00";

			// reward hours between 49 and 75, check if previous 'previous' 25 hour reward will be created
			if ($rewardHours > 49 and $rewardHours < 75) {					# check for 50 hours reward
				if ($trace) {echo "reward hours between 49 and 75, check if previous 'previous' 25 hour reward will be created";}
			
				$rewards_sql = "SELECT * 
								FROM accnt_sumry
								WHERE id = '$userId'
								AND account = '$rewardId25Hours'
								AND datefrom = '$reward_start_date';";

	 			if ($trace) {echo "<br>rewards sql = " . $rewards_sql;}

				$result1 = mysqli_query($db_connection, $rewards_sql) or die (mysqli_error($db_connection));

				//if (!$result1) {
				$row1 = mysqli_fetch_array($result1); 

	    		if (!$row1) {	

					// create 25 hour reward					
	 				$insert = "INSERT into accnt_trans
				    	          (id, account, amount,
	         						effect, reference, tdate)
	                		   VALUES ('$userId', '$rewardId25Hours', '25.00', 
	                	            '+', '$rewardText25Hours', '$timeOutDate');";

	 				if ($trace) {echo "<br>insert sql = " . $insert;}

					$result2 = mysqli_query($db_connection, $insert) or die (mysqli_error($db_connection));	

					// get record number for reference to accnt_sumry
					if ($result2) {
  						$ref_accnt_trans = mysqli_insert_id($db_connection);
					}

	        		// create account summary records for rewards
	 				if ($trace) {echo "<br>create account summary records for rewards";}

            		$insert = "INSERT into accnt_sumry  
	                	          (id, account, balance_date, 
	                	           balance_start, balance_new, 
	                	           datefrom, dateto, ref_accnt_trans)
	                	   VALUES ('$userId', '$rewardId25Hours', '$timeOutDate',
	                	           '$rewardAmount', '$rewardAmount',
	                	           '$reward_start_date', '$reward_end_date', '$ref_accnt_trans');";

	 				if ($trace) {echo "<br>insert sql = " . $insert;}

	        		$result = mysqli_query($db_connection, $insert) or die (mysqli_error($db_connection));
			
				}


			} # if ($rewardHours > 49 and $rewardHours < 75)
 
			
			// total 75 hours or more, check if 'previous' 25 hours reward and 50 hours reward were created
	 		if ($trace) {echo "<br>total 75 hours or more, check if 'previous' 25 hours reward and 50 hours reward were created";}

			if ($rewardHours >= 75) {													# check for 75 hours reward

				// check if previous 'previous' 25 hour reward will be created
	 			if ($trace) {echo "<br>reward hours >= 75, check if previous 'previous' 25 hour reward will be created";}

				$rewards_sql = "SELECT * 
								FROM accnt_sumry
								WHERE id = '$userId'
								AND account = '$rewardId25Hours'
								AND datefrom = '$reward_start_date';";

	 			if ($trace) {echo "<br>rewards = " . $rewards_sql;}

				$result1 = mysqli_query($db_connection, $rewards_sql) or die (mysqli_error($db_connection));

				//if (!$result1) {
				$row1 = mysqli_fetch_array($result1); 

	    		if (!$row1) {

					// create 25 hour reward
	 				if ($trace) {echo "<br>create 25 hour reward";}		
			
	 				$insert = "INSERT into accnt_trans
				    	          (id, account, amount,
	         						effect, reference, tdate)
	                		   VALUES ('$userId', '$rewardId25Hours', '25.00', 
	                	            '+', '$rewardText25Hours', '$timeOutDate');";

	 				if ($trace) {echo "<br>insert sql = " . $insert;}

					$result2 = mysqli_query($db_connection, $insert) or die (mysqli_error($db_connection));	

					// get record number for reference to accnt_sumry
					if ($result2) {
  						$ref_accnt_trans = mysqli_insert_id($db_connection);
					}

	        		// create account summary records for rewards
	 				if ($trace) {echo "<br>create account summary records for rewards";}

            		$insert = "INSERT into accnt_sumry  
	                	          (id, account, balance_date, 
	                	           balance_start, balance_new, 
	                	           datefrom, dateto, ref_accnt_trans)
	                	   VALUES ('$userId', '$rewardId25Hours', '$timeOutDate',
	                	           '$rewardAmount', '$rewardAmount',
	                	           '$reward_start_date', '$reward_end_date', '$ref_accnt_trans');";

	 				if ($trace) {echo "<br>insert sql = " . $insert;}

	        		$result3 = mysqli_query($db_connection, $insert) or die (mysqli_error($db_connection));
			
				}

				// check if previous 'previous' 50 hour reward will be created
	 			if ($trace) {echo "<br>check if previous 'previous' 50 hour reward will be created";}		

				$rewards_sql = "SELECT * 
								FROM accnt_sumry
								WHERE id = '$userId'
								AND account = '$rewardId50Hours'
								AND datefrom = '$reward_start_date';";

	 			if ($trace) {echo "<br>rewards sql = " . $rewards_sql;}

				$result1 = mysqli_query($db_connection, $rewards_sql) or die (mysqli_error($db_connection));

				//if (!$result1) {
				$row1 = mysqli_fetch_array($result1); 

	    		if (!$row1) {

					// create 50 hour reward
	 				if ($trace) {echo "<br>create account transaction record for 50 hour reward";}	
					
	 				$insert = "INSERT into accnt_trans
				    	          (id, account, amount,
	         						effect, reference, tdate)
	                		   VALUES ('$userId', '$rewardId50Hours', '25.00', 
	                	            '+', '$rewardText50Hours', '$timeOutDate');";

	 				if ($trace) {echo "<br>insert sql = " . $insert;}

					$result2 = mysqli_query($db_connection, $insert) or die (mysqli_error($db_connection));	

					// get record number for reference to accnt_sumry
					if ($result2) {
  						$ref_accnt_trans = mysqli_insert_id($db_connection);
					}

	        		// create account summary records for rewards
	 				if ($trace) {echo "<br>create account summary records for 50 hour rewards";}

            		$insert = "INSERT into accnt_sumry  
	               		          (id, account, balance_date, 
	               		           balance_start, balance_new, 
	               		           datefrom, dateto, ref_accnt_trans)
	               		   VALUES ('$userId', '$rewardId50Hours', '$timeOutDate',
	               		           '$rewardAmount', '$rewardAmount',
	               		           '$reward_start_date', '$reward_end_date', '$ref_accnt_trans');";

 					if ($trace) {echo "<br>insert sql = " . $insert;}

        			$result3 = mysqli_query($db_connection, $insert) or die (mysqli_error($db_connection));

				} # if (!$row1) 

			} #	if ($rewardHours >= 75)


			// create the current (not previous) reward for the total number of hours
	 		if ($trace) {echo "<br>create the current reward for the total number of hours";}	

			$insert = "INSERT into accnt_trans
				              (id, account, amount,
	         					effect, reference, tdate)
	                   VALUES ('$userId', '$rewardId', '$rewardAmount', 
	                            '+', '$rewardText', '$timeOutDate');";

	 		if ($trace) {echo "<br>insert sql = " . $insert;}

	        $result = mysqli_query($db_connection, $insert) or die (mysqli_error($db_connection));  

			// get record number for reference to accnt_sumry
			if ($result) {
  				$ref_accnt_trans = mysqli_insert_id($db_connection);
			}       

	        // create account summary records for rewards
	 		if ($trace) {echo "<br>create account summary records for rewards";}

            $insert = "INSERT into accnt_sumry  
	                          (id, account, balance_date, 
	                           balance_start, balance_new, 
	                           datefrom, dateto, ref_accnt_trans)
	                   VALUES ('$userId', '$rewardId', '$timeOutDate',
	                           '$rewardAmount', '$rewardAmount',
	                           '$reward_start_date', '$reward_end_date', '$ref_accnt_trans');";

	 		if ($trace) {echo "<br>insert sql = " . $insert;}

	        $result = mysqli_query($db_connection, $insert) or die (mysqli_error($db_connection));

	        if ($result === TRUE) {
	            #echo "<br>Rewards SUCCESSFULLY created for " . $rewardText;  
	        } else {
	            #echo "<br>ERROR - Rewards NOT created for " . $rewardText;
	        }

	    } else {

    	    #echo "<br>REWARD data EXISTS for " . $rewardText;   
   
    	} # if (!$row) 
    
    } # if ($rewardId <> 'no_reward') {	


	##### 6.3 get amounts, availments, and balances
	if ($trace) echo "<br>6.3 get amounts, availments, and balances";

	$query = "SELECT *, tdate  
				FROM accnt_sumry
				INNER JOIN accnt_trans
				ON accnt_sumry.ref_accnt_trans = accnt_trans.recnum
               WHERE accnt_sumry.id = '$userId' 
                AND accnt_sumry.account like '%hour%'
                AND (datefrom >= '$reward_start_date'
                     and dateto <= '$reward_end_date');";  

	if ($trace) echo "<br>query = $query";          
            
    $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));
              
    //if ($result) {
	if (mysqli_num_rows($result) > 0) {
    
        // compute for balances
        $balanceStartTotal = $balanceEndTotal = 0;
        
        while($rows = mysqli_fetch_array($result)){ 
        
        	if ($rows['account'] === '25_hour_reward') {
        	    $awarded25 = $rows['tdate'];  
        	    $balanceStart25 = $rows['balance_start'];  
        	    $balanceStartTotal += $balanceStart25;      	
        		$balanceEnd25 = $rows['balance_new']; 
        		$balanceEndTotal += $balanceEnd25;  
        		$availment25 = $balanceStart25 - $balanceEnd25;  
        		$availmentTotal += $availment25; 
        		$availment25 = number_format($availment25, 2, '.', ',');  
				$fromDate25 = $rows['datefrom'];
				$toDate25 = $rows['dateto'];       		   		
        	}
        	
        	if ($rows['account'] === '50_hour_reward') {
        	    $awarded50 = $rows['tdate']; 
        	    $balanceStart50 = $rows['balance_start'];   
        	    $balanceStartTotal += $balanceStart50;          	         	
        		$balanceEnd50 = $rows['balance_new']; 
        		$balanceEndTotal += $balanceEnd50;  
        		$availment50 = $balanceStart50 - $balanceEnd50; 
        		$availmentTotal += $availment50; 
        		$availment50 = number_format($availment50, 2, '.', ',');
				$fromDate50 = $rows['datefrom'];
				$toDate50 = $rows['dateto'];      	        		        		
        	}
        	
        	if ($rows['account'] === '75_hour_reward') {
        	    $awarded75 = $rows['tdate']; 
        	    $balanceStart75 = $rows['balance_start']; 
        	    $balanceStartTotal += $balanceStart75;          	           	
        		$balanceEnd75 = $rows['balance_new']; 
        		$balanceEndTotal += $balanceEnd75;
        		$availment75 = $balanceStart75 - $balanceEnd75; 
        		$availment75 = number_format($availment75, 2, '.', ',');                 		
        		$availmentTotal += $availment75;    
				$fromDate75 = $rows['datefrom'];
				$toDate75 = $rows['dateto'];      		          		              		
        	}
        	
        }
    } 
    
    $balanceStartTotal = number_format($balanceStartTotal, 2, '.', ',');
    $availmentTotal = number_format($availmentTotal, 2, '.', ',');  
    $balanceEndTotal = number_format($balanceEndTotal, 2, '.', ',');     

	##### 6.5 display rewards  

	# Rewards (must be availed within anniversary year: from mmm-dd-yyyy to mmm-dd-yyy)
	#  (If the volunteer did not report for more than 90 days, the rewards will be forfeited
	#   and the volunteer must attend re-orientation)
	#  		Hours  		Amount		Awarded			Availment		Balance		Expiration
	#  	 After 25		$25.00		mmm-dd-yyy		$20.00			$05.00		mmm-dd-yyy
	#    After 50		$25.00		mmm-dd-yyy		$00.00			$25.00		mmm-dd-yyy
	#    After 75		$25.00		mmm-dd-yyy		$00.00			$25.00		mmm-dd-yyy
	#  		Total 		$75.00						$20.00			$55.00		mmm-dd-yyy
	# 

	if ($trace) echo "<br>6.5 display rewards";
                    
	echo "<br>
        <table border='' cellpadding='4' cellspacing='0'> 
        <tr><th colspan=7>REWARDS</th></tr>    
        <tr><th>Hours</th><th>Awarded</th><th>Amount</th><th>Availment</th><th>Balance</th><th>From</th><th>To</th></tr>";

	echo "<tr><td>Reward For 25 Hours</td><td>$awarded25</td><td align=center>$balanceStart25</td><td align=center>$availment25</td><td align=center>$balanceEnd25</td><td>$fromDate25</td><td>$toDate25</td></tr>";
	
	echo "<tr><td>Reward for 50 Hours</td><td>$awarded50</td><td align=center>$balanceStart50</td><td align=center>$availment50</td><td align=center>$balanceEnd50</td><td>$fromDate50</td><td>$toDate50</td></tr>";
	
	echo "<tr><td>Reward for 75 Hours</td><td>$awarded75</td><td align=center>$balanceStart75</td><td align=center>$availment75</td><td align=center>$balanceEnd75</td><td>$fromDate75</td><td>$toDate75</td></tr>";	
	
	echo "<tr><td>Total</td><td align=center>-</td><td align=center>$balanceStartTotal</td><td align=center>$availmentTotal</td><td align=center>$balanceEndTotal</td><td align=center colspan=2>-</td></tr>";	
	
	echo "</table>";	

	
 	##### 7 - display store credits if there any transactions	

	 	  		
	# Credits (must be used within 90 days after 'awarded' date)
	#	Amount		Awarded		Expiration		Particulars
	#	 $20		mmm-dd-yyy	mmm-dd-yyy		Returns per receipt # 2319
	#	

	$query = "SELECT account, tdate
                FROM accnt_trans
               WHERE id = '$userId' 
                 AND account like '%credit%' 
                 AND (tdate >= '$reward_start_date'
                       and tdate <= '$reward_end_date');"; 

	if ($trace) echo "<br>query = $query";        
 
    $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));
              
    //if ($result) {
	//$row = mysqli_fetch_array($result); 

	//if (!$row) {
	if (mysqli_num_rows($result) > 0) {

		echo "<br>
        	<table border='' cellpadding='4' cellspacing='0'> 
        	<tr><th colspan=6>CREDITS</th></tr>    
        	<tr><th>Awarded</th><th>Amount</th><th>Availment</th><th>Balance</th><th>Expiration</th><th>Particulars</th></tr>";	
		echo "</table>";

        while($rows = mysqli_fetch_array($result)){ 


		}

	}
	

 	##### 8 - display discounts if there are any transactions
	# 			
	# Discounts (teacher's, etc)
	#	Percent		Awarded		Expiration		Particulars
	#	  20%		mmm-dd-yyy	mm-dd-yyy		For teaching Linux, Python, Hardware, etc.
	# 	

	$query = "SELECT account, tdate
                FROM accnt_trans
               WHERE id = '$userId' 
                 AND account like '%discount%' 
                 AND (tdate >= '$reward_start_date'
                       and tdate <= '$reward_end_date');"; 

	if ($trace) echo "<br>query = $query";        
 
    $result = mysqli_query($db_connection, $query) or die (mysqli_error($db_connection));
              

	if (mysqli_num_rows($result) > 0) {

		echo "<br>
        	<table border='' cellpadding='4' cellspacing='0'> 
        	<tr><th colspan=6>DISCOUNTS</th></tr>    
        	<tr><th>Awarded</th><th>Percent</th><th>Amount</th><th>Availment</th><th>Balance</th><th>Expiration</th><th>Particulars</th></tr>";	
		echo "</table>"; 	

        while($rows = mysqli_fetch_array($result)){ 


		}

	}
	
*/
	##### 9 - display acknowledgment

    //-acknowledge time-out and redirect to time in/out screen
    echo "<html><body><form action='vms_attendance_62.php' method='post' >";
    $message = 'Thank you for your time, please click anywhere on this shaded area to go back to Time In/Out screen.';
    echo "<p><input type='submit' value='$message' style='white-space: normal; color: green; font-weight:bold; font-size: 25px; height:200px; width:600px;'></p>";
    echo "</form></body></html>"; 

    //-reset data previously posted
    unset($_POST);
    session_destroy();

    //-automatically exit after 15 seconds
	if (!$trace) { 
    	header('Refresh:15; url=vms_attendance_62.php');					# comment on testing, uncomment after testing
	}

} # if (!$_POST) {

//-close database
mysqli_free_result($result);
mysqli_close();

?> 


