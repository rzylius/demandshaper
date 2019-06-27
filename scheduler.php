<?php

/*

All Emoncms code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

---------------------------------------------------------------------
Emoncms - open source energy visualisation
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org

*/

define("MAX",1);
define("MIN",0);

// -------------------------------------------------------------------------------------------------------
// FETCH AND PRE-PROCESS FORECASTS AS 24H FROM CURRENT TIME
// -------------------------------------------------------------------------------------------------------

function get_forecast($redis,$signal) {

    $resolution = 1800;
    $resolution_h = $resolution/3600;
    $divisions = round(24*3600/$resolution);

    $now = time();
    $timestamp = floor($now/$resolution)*$resolution;
    $start_timestamp = $timestamp;

    // -----------------------------------------------------------------------------   
    $profile = array();
    $available = 1;
    
    // -----------------------------------------------------------------------------
    // Grid carbon intensity
    // ----------------------------------------------------------------------------- 
    if ($signal=="carbonintensity") {
        $optimise = MIN;
        // $start = $date->format('Y-m-d\TH:i\Z');
        // $result = json_decode(file_get_contents("https://api.carbonintensity.org.uk/intensity/$start/fw24h"));
        $result = json_decode($redis->get("demandshaper:carbonintensity"));
        
        if ($result!=null && isset($result->data)) {
        
            $datetimestr = $result->data[0]->from;
            $date = new DateTime($datetimestr);
            $start = $date->getTimestamp();
            
            $datetimestr = $result->data[count($result->data)-1]->from;
            $date = new DateTime($datetimestr);
            $end = $date->getTimestamp();
        
            for ($timestamp=$start; $timestamp<$end; $timestamp+=$resolution) {
            
                $i = floor(($timestamp - $start)/1800);
                if (isset($result->data[$i])) {
                    $co2intensity = $result->data[$i]->intensity->forecast;
                    
                    $date->setTimestamp($timestamp);
                    $h = 1*$date->format('H');
                    $m = 1*$date->format('i')/60;
                    $hour = $h + $m;
                    
                    if ($timestamp>=$start_timestamp) $profile[] = array($timestamp*1000,$co2intensity,$hour);
                }
            }
        }
    }
    
    // -----------------------------------------------------------------------------
    // Octopus
    // ----------------------------------------------------------------------------- 
    if ($signal=="octopus") {
        $optimise = MIN;
        //$result = json_decode(file_get_contents("https://api.octopus.energy/v1/products/AGILE-18-02-21/electricity-tariffs/E-1R-AGILE-18-02-21-D/standard-unit-rates/"));
        // 1. Fetch Octopus forecast
        $result = json_decode($redis->get("demandshaper:octopus"));
        $start = $timestamp; // current time
        $td = 0;
        
        // if forecast is valid
        if ($result!=null && isset($result->results)) {
            /* for each half hour in forecast
            for ($i=count($result->results)-1; $i>0; $i--) {
                $datetimestr = $result->results[$i]->valid_from;
                $price = $result->results[$i]->value_inc_vat;
                $date = new DateTime($datetimestr);
                $timestamp = $date->getTimestamp();
                if ($timestamp>=$start && $td<48) {
                    $h = 1*$date->format('H');
                    $m = 1*$date->format('i')/60;
                    $hour = $h + $m;
                    if ($timestamp>=$end_timestamp) $available = 0;
                    if ($timestamp>=$start_timestamp) $profile[] = array($timestamp*1000,$price,$hour,$available,0);
                    $td++;
                }
            }*/
            
            // sort octopus forecast into time => price associative array
            $octopus = array();
            foreach ($result->results as $row) {
                $date = new DateTime($row->valid_from);
                $octopus[$date->getTimestamp()] = $row->value_inc_vat;
            }
            
            $timestamp = $start_timestamp;
            for ($i=0; $i<$divisions; $i++) {

                $date->setTimestamp($timestamp);
                $h = 1*$date->format('H');
                $m = 1*$date->format('i')/60;
                $hour = $h + $m;
                
                if (isset($octopus[$timestamp])) {
                    $price = $octopus[$timestamp]; 
                } else if (isset($octopus[$timestamp-(24*3600)])) {
                    $price = $octopus[$timestamp-(24*3600)]; 
                } else {
                    $price = 12.0;
                }
                
                $profile[] = array($timestamp*1000,$price,$hour);
                $timestamp += $resolution; 
            }
        }
    }

    // -----------------------------------------------------------------------------
    // EnergyLocal demand shaper
    // -----------------------------------------------------------------------------  
    else if ($signal=="cydynni") {
        $optimise = MAX;
        $result = json_decode($redis->get("demandshaper:bethesda"));
        
        // Validate demand shaper
        if  ($result!=null && isset($result->DATA)) {
       
            $EL_signal = $result->DATA[0];
            array_shift($EL_signal);
            $len = count($EL_signal);

            //------------------------
            // Normalise into 0.0 to 1.0
            $min = 1000; $max = -1000;
            for ($i=0; $i<$len; $i++) {
                $val = (float) $EL_signal[$i];
                if ($val>$max) $max = $val;
                if ($val<$min) $min = $val;
            }
            
            $tmp = array();
            $max = $max += -1*$min;
            for ($i=0; $i<$len; $i++) $tmp[$i*0.5] = 1.0 - (($EL_signal[$i] + -1*$min) / $max);
            $EL_signal = $tmp;
            
            //------------------------
            
            for ($i=0; $i<count($EL_signal); $i++) {

                $date->setTimestamp($timestamp);
                $h = 1*$date->format('H');
                $m = 1*$date->format('i')/60;
                $hour = $h + $m;
                
                $profile[] = array($timestamp*1000,$EL_signal[$hour],$hour);
                $timestamp += 1800; 
            }
        }
    // -----------------------------------------------------------------------------
    // Economy 7 
    // ----------------------------------------------------------------------------- 
    } else if ($signal=="economy7") {
        $optimise = MIN;
        for ($i=0; $i<$divisions; $i++) {

            $date->setTimestamp($timestamp);
            $h = 1*$date->format('H');
            $m = 1*$date->format('i')/60;
            $hour = $h + $m;
            
            if ($hour>=0.0 && $hour<7.0) $economy7 = 0.07; else $economy7 = 0.15;
            
            $profile[] = array($timestamp*1000,$economy7,$hour);
            $timestamp += $resolution; 
        }
    }

    // get max and min values of profile
    $min = 1000000; $max = -1000000;
    for ($i=0; $i<count($profile); $i++) {
        $val = (float) $profile[$i][1];
        if ($val>$max) $max = $val;
        if ($val<$min) $min = $val;
    }
    
    $result = new stdClass();
    $result->profile = $profile;
    $result->optimise = $optimise;
    $result->min = $min;
    $result->max = $max;
    return $result;
}

// -------------------------------------------------------------------------------------------------------
// SCHEDULE
// -------------------------------------------------------------------------------------------------------

function schedule_smart($forecast,$timeleft,$end,$interruptible)
{   
    $debug = 0;
    
    $resolution = 1800;
    $resolution_h = $resolution/3600;
    $divisions = round(24*3600/$resolution);
    
    // period is in hours
    $period = $timeleft / 3600;
    if ($period<0) $period = 0;
    
    // Start time
    $now = time();
    $timestamp = floor($now/$resolution)*$resolution;
    $start_timestamp = $timestamp;
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone("Europe/London"));

    $date->setTimestamp($timestamp);
    $h = 1*$date->format('H');
    $m = 1*$date->format('i')/60;
    $start_hour = $h + $m;
    
    // End time
    $end = floor($end / $resolution_h) * $resolution_h;
    $date->modify("midnight");
    $end_timestamp = $date->getTimestamp() + $end*3600;
    if ($end_timestamp<$now) $end_timestamp+=3600*24;

    $profile = $forecast->profile;
    
    // No half hours allocated yet
    for ($td=0; $td<count($profile); $td++) {
        $profile[$td][3] = 0;
    }

    if (!$interruptible) 
    {

        // We are trying to find the start time that results in the maximum sum of the available power
        // $max is used to find the point in the forecast that results in the maximum sum..
        $threshold = 0;

        // When $max available power is found, $start_time is set to this point
        $pos = 0;

        // ---------------------------------------------------------------------------------
        // Method 1: move fixed period of demand over probability function to find best time
        // ---------------------------------------------------------------------------------
        
        // For each time division in profile
        for ($td=0; $td<count($profile); $td++) {

             // Calculate sum of probability function values for block of demand covering hours in period
             $sum = 0;
             $valid_block = 1;
             for ($i=0; $i<$period*($divisions/24); $i++) {
                 
                 if (isset($profile[$td+$i])) {
                     if ($profile[$td+$i][0]*0.001>=$end_timestamp) $valid_block = 0;
                     $sum += $profile[$td+$i][1];
                 }
             }
             
             if ($td==0) $threshold = $sum;
             
             // Determine the start_time which gives the maximum sum of available power
             if ($valid_block) {
                 if (($forecast->optimise==MIN && $sum<$threshold) || ($forecast->optimise==MAX && $sum>$threshold)) {
                     $threshold = $sum;
                     $pos = $td;
                 }
             }
        }
        
        $start_hour = 0;
        $tstart = 0;
        if (isset($profile[$pos])) {
            $start_hour = $profile[$pos][2];
            $tstart = $profile[$pos][0]*0.001;
        }
        $end_hour = $start_hour;
        $tend = $tstart;
        
        for ($i=0; $i<$period*($divisions/24); $i++) {
            $profile[$pos+$i][3] = 1;
            $end_hour+=$resolution/3600;
            $tend+=$resolution;
            if ($end_hour>=24) $end_hour -= 24;
            // dont allow to run past end time
            if ($end_hour==$end) break;
        }
        
        $periods = array();
        if ($period>0) {
            $periods[] = array("start"=>array($tstart,$start_hour), "end"=>array($tend,$end_hour));
        }
        return $periods;

    } else {
        // ---------------------------------------------------------------------------------
        // Method 2: Fill into times of most available power first
        // ---------------------------------------------------------------------------------

        // For each hour of demand
        for ($p=0; $p<$period*($divisions/24); $p++) {

            if ($forecast->optimise==MIN) $threshold = $forecast->max; else $threshold = $forecast->min;
            $pos = -1;
            // for each hour in probability profile
            for ($td=0; $td<count($profile); $td++) {
                // Find the hour with the maximum amount of available power
                // that has not yet been alloated to this load
                // if available && !allocated && $val>$max
                $val = $profile[$td][1];
                
                if ($profile[$td][0]*0.001<$end_timestamp && !$profile[$td][3]) {
                    if (($forecast->optimise==MIN && $val<=$threshold) || ($forecast->optimise==MAX && $val>=$threshold)) {
                        $threshold = $val;
                        $pos = $td;
                    }
                }
            }
            
            // Allocate hour with maximum amount of available power
            if ($pos!=-1) $profile[$pos][3] = 1;
        }
                
        $periods = array();
        
        $start = null;
        $tstart = null;
        $tend = null;
        
        $i = 0;
        $last = 0;
        for ($td=0; $td<count($profile); $td++) {
            $hour = $profile[$td][2];
            $timestamp = $profile[$td][0]*0.001;
            $val = $profile[$td][3];
        
            if ($i==0) {
                if ($val) {
                    $start = $hour;
                    $tstart = $timestamp;
                }
                $last = $val;
            }
            
            if ($last==0 && $val==1) {
                $start = $hour;
                $tstart = $timestamp;
            }
            
            if ($last==1 && $val==0) {
                $end = $hour*1;
                $tend = $timestamp;
                $periods[] = array("start"=>array($tstart,$start), "end"=>array($tend,$end));
            }
            
            $last = $val;
            $i++;
        }
        
        if ($last==1) {
            $end = $hour+$resolution/3600;
            $tend = $timestamp + $resolution;
            $periods[] = array("start"=>array($tstart,$start), "end"=>array($tend,$end));
        }
        
        return $periods;
    }
}

function schedule_timer($forecast,$start1,$stop1,$start2,$stop2) {

    $tstart1 = 0; $tstop1 = 0;
    $tstart2 = 0; $tstop2 = 0;
    
    for ($td=0; $td<count($forecast->profile); $td++) {
        $forecast->profile[$td][3] = 0;
    }
                  
    // For each time division in profile
    for ($td=0; $td<count($forecast->profile); $td++) {

        if ($start1>$stop1 && ($forecast->profile[$td][2]<$stop1 || $forecast->profile[$td][2]>$start1)) {
            $forecast->profile[$td][3] = 1;
        }
        
        if ($start1>$stop2 && ($forecast->profile[$td][2]<$stop2 || $forecast->profile[$td][2]>$start2)) {
            $forecast->profile[$td][3] = 1;
        }
                 
        if ($start1<$stop1 && $forecast->profile[$td][2]>=$start1 && $forecast->profile[$td][2]<$stop1) {
            $forecast->profile[$td][3] = 1;
        }

        if ($start2<$stop2 && $forecast->profile[$td][2]>=$start2 && $forecast->profile[$td][2]<$stop2) {
            $forecast->profile[$td][3] = 1;
        }         
        
        if ($forecast->profile[$td][2]==$start1) $tstart1 = $forecast->profile[$td][0]*0.001;
        if ($forecast->profile[$td][2]==$stop1) $tstop1 = $forecast->profile[$td][0]*0.001;
        if ($forecast->profile[$td][2]==$start2) $tstart2 = $forecast->profile[$td][0]*0.001;
        if ($forecast->profile[$td][2]==$stop2) $tstop2 = $forecast->profile[$td][0]*0.001;
    }

    if ($tstart1>$tstop1) $tstart1 -= 3600*24;
    if ($tstart2>$tstop2) $tstart2 -= 3600*24;
           
    $periods = array();
    $periods[] = array("start"=>array($tstart1,$start1), "end"=>array($tstop1,$stop1));
    $periods[] = array("start"=>array($tstart2,$start2), "end"=>array($tstop2,$stop2));
    return $periods;
}
