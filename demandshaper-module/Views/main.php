<?php global $path; $v=2; ?>
<link rel="stylesheet" href="<?php echo $path; ?>Modules/demandshaper/demandshaper.css?v=<?php echo $v; ?>">

<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.time.min.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/date.format.min.js"></script>

<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/js/forecast_builder.js?v=<?php echo $v; ?>"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/js/get_device_state.js?v=<?php echo $v; ?>"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/js/battery.js?v=<?php echo $v; ?>"></script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/js/openevse.js?v=<?php echo $v; ?>"></script>
<div id="scheduler-top"></div>

<div id="scheduler-outer">
  <div class="config-device"><i class="icon-wrench icon-white"></i></div>
  <div class="node-scheduler-title"><span class="title-icon"></span><span class="custom-name"></span><span class="device-name"></span> <span class='device-state-message'></span></div>
  <div class="node-scheduler" node="">
    <div class="scheduler-inner">
      <div class="scheduler-inner2">
        <div class="scheduler-controls" style="text-align:center">
        
          <!---------------------------------------------------------------------------------------------------------------------------->
          <!-- CONTROLS -->
          <!---------------------------------------------------------------------------------------------------------------------------->                
          <div id="mode" class="btn-group">
            <button mode="on">On</button><button mode="off">Off</button><button mode="smart" class="active">Smart</button><button mode="timer">Timer</button>
          </div><br><br>
          
          <div class="openevse hide">
            <p>Charge Current <span id="charge_current">0</span>A<br><span style="font-weight:normal; font-size:12px">Temperature <span id="openevse_temperature">10</span>C</span></p>
            <div id="battery_bound" style="width:100%" class="hide">
                <canvas id="battery"></canvas>
            </div>
          </div>
          <!---------------------------------------------------------------------------------------------------------------------------->
          <div class="smart">
          
            <div class="row" style="max-width:700px; margin: 0 auto;">
              <div class="span4" style="margin-bottom:0px">
                <div id="run_period">
                  <p>Run period:</p>
                  <div id="period" class="btn-group input-time">
                    <button>-</button><input type="time" val="00:00"><button>+</button>
                  </div>
                </div>
              </div>
              <div class="span4" style="margin-bottom:0px">
                <p>Complete by:</p>
                <div id="end" class="btn-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
              <div class="span4" style="margin-bottom:20px">
                <p>Ok to interrupt:</p>
                <div name="interruptible" state=0 class="scheduler-checkbox" style="margin:0 auto"></div>
              </div>
            </div>
            
            <br>
          
          </div>
          <!---------------------------------------------------------------------------------------------------------------------------->
          <div class="timer hide">
            <div class="row" style="max-width:700px; margin: 0 auto;">
              <div class="span2">
                <br><br>
                <p>Timer 1</p>
              </div>
              <div class="span4">
                <p>Start</p>
                <div id="timer_start1" class="btn-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
              <div class="span4">
                <p>Stop</p>
                <div id="timer_stop1" class="btn-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
            </div>
            
            <br>
            
            <div class="row timer hide" style="max-width:700px; margin: 0 auto;">
              <div class="span2">
                <br><br>
                <p>Timer 2</p>
              </div>
              <div class="span4">
                <p>Start</p>
                  <div id="timer_start2" class="btn-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
              <div class="span4">
                <p>Stop</p>
                <div id="timer_stop2" class="btn-group input-time">
                  <button>-</button><input type="time" val="00:00"><button>+</button>
                </div>
              </div>
            </div>
            <br>
          </div>
          
          <div id="schedule-output" style="font-weight:normal; padding-top:20px; padding-bottom:20px"></div>
          <div id="timeleft" style="font-weight:normal; font-size:14px"></div>
          <div id="placeholder_bound" style="width:100%; height:300px">
            <div id="placeholder" style="height:300px"></div>
          </div><br>
          <div id="schedule-co2" style="font-size:14px; color:#888;"></div>
        </div> <!-- schedule-controls -->
      </div> <!-- schedule-inner2 -->
      <div id="ip_address">IP Address: 192.168.1.20</div>
    </div> <!-- scheduler-inner -->
    
    <div class="scheduler-inner hide" style="background-color:#eaeaea; font-weight:normal">
        <div class="scheduler-config" style="text-align:left">
        
          <div style="border: 1px solid #ccc; padding:10px; background-color:#f0f0f0;">
            <p><b>Forecast Settings</b></p>   
            <table class="table" style="margin-bottom:0px">
              <tr><th>Forecast name</th><th>Parameters</th><th>Weight</th><th></th></tr>
              <tbody id="forecasts"></tbody>
            </table>
            <div class="input-prepend input-append"><span class="add-on">Add forecast</span><select id="forecast_list"></select></div>   
          </div>
          
          <div class="openevse hide" style="border: 1px solid #ccc; padding:10px; margin-top:10px; background-color:#f0f0f0">
            <p><b>OpenEVSE Settings</b></p>
            <table class="table">
              <tr><td>Control based on:</td><td><select class="input" name="soc_source"><option value="time">Charge time</option><!--<option value="energy">Charge energy</option><option value="miles">Travel distance</option>--><option value="input">Battery charge level (Input)</option><option value="ovms">Battery charge level (OVMS)</option></select></td></tr>
              <tr><td>Useable Battery Capacity:</td><td><input class="input" name="battery_capacity" type="text" style="width:80px"/> kWh</td></tr>
              <tr><td>AC Charge Rate:</td><td><input class="input" name="charge_rate" type="text" style="width:80px"/> kW</td></tr>
              <tr class="openevse-balancing hide"><td>Balancing Percentage::</td><td><input class="input" name="balpercentage" type="text" style="width:80px"/> %</td></tr>
              <tr class="openevse-balancing hide"><td>Balancing Time:</td><td><input class="input" name="baltime" type="text" style="width:80px"/> Mins</td></tr>
              <tr class="ovms-options hide"><td>OVMS Vehicle ID:</td><td><input class="input" name="ovms_vehicleid" type="text" style="width:80px"/></td></tr>
              <tr class="ovms-options hide"><td>OVMS Car Password:</td><td><input class="input" name="ovms_carpass" type="text" style="width:80px"/></td></tr> 
            </table>      
          </div>          
          
      </div>
    </div> <!-- scheduler-inner -->
  </div> <!-- node-scheduler -->
</div> <!-- scheduler-outer -->
<script>
var forecast_list = <?php echo json_encode($forecast_list); ?>;
var schedule = <?php echo json_encode($schedule); ?>;
</script>
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Modules/demandshaper/js/main.js"></script>