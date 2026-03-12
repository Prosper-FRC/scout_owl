
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>the Scout Owl</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- Load jQuery & Chart.js -->
        
  
        <style>
         @font-face {
            font-family: 'Roboto';
            src: url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf'),
            url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            @font-face {
            font-family: 'Griffy';
            src: url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf'),
            url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            @font-face {
            font-family: 'Comfortaa';
            src: url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf'),
            url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            /* Global Styles */
            body, html {
            font-family: 'Comfortaa', sans-serif;
            margin: 0;
            padding: 0;

            display: flex; /* Center the content */
            justify-content: center;
            background-color: #111; /* Dark background */
            color: #fff; /* High contrast text */
            }
            /* Headings */
            h1 {
            text-align: left;
            font-size: .75rem;
            margin-left: 12px;
            margin-top: -24px;
            color:#fff;
            }
            h2, h3, h4 {
            text-align: center;
            margin: 0; /* Remove extra space */
            color: #ccc;
            }
            h2 { font-size: 1.rem; margin-top: -10px; color:#fff;}
            h3 { font-size: 1rem; }
            h4 { font-size: 0.8rem; }
            /* Scrollable Container */
            #block {
                position:absolute;
            background-color:#222;
            top:0;
            margin: auto;
        padding:12px;
          max-width: 800px;    /*Restrict to tablet dimensions */
          max-height: 1280px;

    
            scrollbar-width: thin; /* Firefox scrollbar */
            scrollbar-color: #888 #333; /* Thumb and track colors */
            }
            #block::-webkit-scrollbar { width: 12px; height: 12px; }
            #block::-webkit-scrollbar-track {
            background: #111;
            border-radius: 6px;
            }
            #block::-webkit-scrollbar-thumb {
            background: #333;
            border-radius: 6px;
            border: 3px solid #111;
            }
            #block::-webkit-scrollbar-thumb:hover { background: #555; }
            /* Top Section */
            #top {
            width: 95%;
            margin: auto;
            display: flex;
            flex-direction: column;
            }
            /* Logo and Scoreboard */
            #logoAndScoreboard {
            display: flex;
            width: 100%;
            height: 80%;
            }
            #logoOuter, #scoreboardOuter {
            width: 50%; /* Equal width for logo and scoreboard */

            }
            .logo { width: 100%; }
            /* Scoreboard */
            #scoreboard {
            width: 94%;
            height: 80%;
            margin: 5% auto;
            border: 1px solid #fff;
            background-color: #111;
            display: flex;
            flex-direction: column;
            }
            #timer, #score {
            width: 100%;
            height: 50%; /* Half the height for each */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            cursor: pointer;
            }
            #timer-inner, #score-inner {
            margin: auto; /* Center the content */
            }
            /* Match Information */
            #matchInfoOuter {
            width: 100%;
            }
            .info {
            display: flex;
            justify-content: space-between; /* Space out info blocks */
            }
            .infoBlock {
            flex: 1; /* Equal width */
            padding: 10px;
            margin: 5px;
            }
            /* Mid Section */
            #mid {
            display: flex;
            align-items: stretch;
            height: 100%;
            }
            .threeBox{
            display: flex;
            align-items: stretch;
            }
            #success, #failure {
            flex: 0 0 5%; /* Fixed width */
            text-align: center;
            font-size: 1rem;
            opacity:60%;
            color: #fff;
            }
            #success{background-color: lightgreen;}
            #failure{background-color: indianred;}
            /* Buttons Grid */
            .buttons {
            flex: 1; /* Take remaining space */
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* Two columns */
            gap: 10px;
            width: 80%;
            margin: auto;
            position: relative;
            }
            .button {
          font-size: 1rem;
            text-align: center;
            background-color: #222;
            color: #fff;
            border-radius: 5px;
            border: 1px solid #fff;
     padding:.5rem;
            cursor: pointer;
            transition: background-color 0.2s;
            }
            .button:hover {
            background-color: #fff;
            color: #111;
            }
            .button.selected {
            background-color: #fff;
            color: #111;
            }
            .long-button { grid-column: span 2; } /* Spans both columns */
            .PUC {grid-row: span 2; padding-top:10%} 
            /*
            .PUA { grid-row: span 2; border-radius: 50%; width:30%; margin-left: 28.5%; padding-top:15%; background-color: #8AE8E0;color:#111;border-color: #fff;border-width: 6px} 
            */
            /* Spans two rows */
            .PUA.selected{border-color: #8AE8E0;border-width: 6px} 
            .circle {border-radius: 50%; margin:auto; } 
            /* Button Highlights */
            .coral { border-color: #CF4FB2; }
            .algae { border-color: #8AE8E0; }
            .opponent, .aliance { border-color: palevioletred; }
            /* Validator */
            #validator {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            background-color: rgba(0, 0, 0, 0.7); /* Semi-transparent */
            padding: 40px;
            border-radius: 10px;
            }
            /* Input Fields */
            .input-container {
            display: flex;
            gap: 10px; /* Space between inputs */
            margin-top: 20px;
            }
            .input-box {
            width: 70px;
            height: 70px; /* Square inputs */
           
            text-align: center;
            border: 2px solid #fff;
            background-color: #111;
            color: #fff;
            border-radius: 10px;
            }
            .input-box:focus { border-color: #0f0; }
            /* Bottom Section */
            #bottom {
            width: 95%;
            margin: auto;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding-bottom: 20px;
            }
            #locationDisplay, #statusDisplay {
            text-align: center;
           
            margin-bottom: 10px;
            }
            #coda {
            width: 100%;
            display: flex; /* Horizontal layout */
            }
            #loc {
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            }
            #bottomDisplays, #stat {
            width: 100%;
            text-align: center;
         
            margin-top: 10px;
            }
            /* Animations */
            @keyframes shake {
            0%, 100% { transform: translate(0); }
            15%, 65% { transform: translateY(-3px); }
            25%, 75% { transform: translateX(-3px); }
            35%, 85% { transform: translateY(3px); }
            50% { transform: translateX(3px); }
            }
            .shake {
            animation: shake 0.5s ease-in-out;
            }
            



            @keyframes flashFailure {
           
            0%, 20%, 40%, 60%, 80%, 100% { background-color: #C0392B; }
            10%, 30%, 50%, 70%, 90% { background-color: #000; }
            }
            .flashFailure {
            animation: flashFailure .5s ease-in-out;
            }

            @keyframes flashSuccess {
           
            0%, 20%, 40%, 60%, 80%, 100% { background-color: #006632; }
            10%, 30%, 50%, 70%, 90% { background-color: #000; }
            }
            .flashSuccess {
            animation: flashSuccess .5s ease-in-out;
            }







            /* Center Hexagon */
            .hex{
            background-color: #CF4FB2; ;
            margin:auto;
            clip-path: polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%);
            }
            .fontBig{}
            .griffy{font-family: 'Griffy', sans-serif;}
            .button .icon {
            top: 5px;           /* Adjust top offset as needed */
            right: 5px;         /* Adjust right offset as needed */
            width: 2rem;        /* Set a width for the icon */
            height: auto;       /* Maintain aspect ratio */
            pointer-events: none; /* Ensures clicks go through to the button */
            }

            .button .iconSmall {

            width: 1.5rem;        /* Set a width for the icon */
            height: auto;       /* Maintain aspect ratio */
            pointer-events: none; /* Ensures clicks go through to the button */
            }



            .auton{background-color: #444;
            }
            .auton:hover{background-color:#fff;}
            .auton.selected{background-color:#fff;}


#offlineSubmissions{display:none}


        </style>
        <link rel="stylesheet" href="../css/select.css">
    </head>
    <body>

        <div id="block">
            <div id="top">
                <div id="logoAndScoreboard">
                    <div id="logoOuter">
                       <a href="../scouter.php"> <img src="../images/thescoutowl.png" class="logo" alt="Logo"></a>
                        <h1 class="griffy">Reefscape Edition</h1>
                    </div>
                    <div id="scoreboardOuter">
                        <div id="scoreboard" class="alliance">
                            <div id="timer">
                                <div id="timer-inner">150</div>
                            </div>
                            <div id="score">
                                <div id="score-inner">0</div>
                            </div>
                        </div>
                     <h2 id="eventName" style="margin-top:8px; display:none"></h2>
                    </div>
                </div>
                <div id="matchInfoOuter">
                    <div class="content">
                        <div class="info">
                            <div class="infoBlock">
                                <h3 id="matchNumber"></h3>
                            </div>
                            <div class="infoBlock">
                                <h3 id="robotName"></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Bottom div with locationDisplay and statusDisplay at the bottom -->
            <div id="mid">
                <!--  <div id="success"></div>-->
                <div class="buttons ">
                    <div class="long-button threeBox">
                        <div class="button alliance circle" data-action="starting_position_1">S1</div>
                        <div class="button alliance circle" data-action="starting_position_2">S2</div>
                        <div class="button alliance circle" data-action="starting_position_3">S3</div>
                        <div class="button alliance circle auton" data-action="auton_left">
                            <img class="iconSmall" src="/../stat_goblin/icons/west.svg" alt="Exclamation Icon">
                        </div>
                        <div class="button alliance circle auton" data-action="auton_center">
                            <img class="iconSmall" src="/../stat_goblin/icons/north.svg" alt="Exclamation Icon">      
                        </div>
                        <div class="button alliance circle auton" data-action="auton_right">
                            <img class="iconSmall" src="/../stat_goblin/icons/east.svg" alt="Exclamation Icon">
                        </div>
                    </div>
                    <div class="button long-button alliance fontBig" data-action="crosses_starting_line">Crosses Starting Line
                        <img class="icon" src="/../stat_goblin/icons/commit_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg" alt="Exclamation Icon">
                    </div>


                    <div class="button coral PUC fontBig" data-action="picks_up_coral">Picks Up Coral
                        <img class="icon" src="/../stat_goblin/icons/swipe_up_alt_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg" alt="Exclamation Icon">
                    </div>


                    <div class="threeBox">
                        <div class="button coral hex " data-action="scores_coral_level_1">SCL 1</div>
                        <div class="button coral hex" data-action="scores_coral_level_2">SCL 2</div>
                    </div>
                    <div class="threeBox">
                        <div class="button coral hex" data-action="scores_coral_level_3">SCL 3</div>
                        <div class="button coral hex" data-action="scores_coral_level_4">SCL 4</div>
                    </div>
                    <div class="button algae fontBig" data-action="picks_up_algae">Picks Up Algae
                        <img class="icon" src="/../stat_goblin/icons/swipe_up_alt_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg" alt="Exclamation Icon">
                    </div>
                    <div class="threeBox">
                        <div class="button algae circle" data-action="scores_algae_net">Net
                        </div>
                        <div class="button algae circle"  data-action="scores_algae_processor">Pro
                        </div>
                    </div>
                    <div class="button opponent fontBig" data-action="plays_defense">Crosses Field
                        <img class="icon" src="/../stat_goblin/icons/shield_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg" alt="Exclamation Icon">
                    </div>
                    <div class="button opponent fontBig" data-action="block">Blocks
                        <img class="icon" src="/../stat_goblin/icons/encrypted_minus_circle_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg" alt="Exclamation Icon">
                    </div>
                    <div class="button alliance fontBig" data-action="attempts_shallow_climb">Shallow Climb
                        <img class="icon" src="/../stat_goblin/icons/hiking_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg" alt="Exclamation Icon">
                    </div>
                    <div class="button alliance fontBig" data-action="attempts_deep_climb">Deep Climb
                        <img class="icon" src="/../stat_goblin/icons/hiking_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg" alt="Exclamation Icon">
                    </div>
                    <div class="button long-button alliance fontBig" data-action="attempts_parked">Park
                        <img class="icon" src="/../stat_goblin/icons/bike_dock_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg" alt="Exclamation Icon">
                    </div>
                    <div class="button fontBig" data-action="disabled">Busted
                        <img class="icon" src="/../stat_goblin/icons/sick_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg" alt="Exclamation Icon">
                    </div>
                    <div class="button fontBig griffy" data-action="delete_action">Undo 
                        <img class="icon" src="/../stat_goblin/icons/undo_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg" alt="Exclamation Icon">
                    </div>
                </div>
                <!--  <div id="failure"></div> -->
            </div>
            <!-- Location and Status at the bottom -->
            <div id="coda">
                <div id="bottomDisplays">
                    <div id="loc">
                        <h3 id="locationDisplay">Location: </h3>
                    </div>
                    <div id="stat">
                        <h3 id="statusDisplay">Swipe to Submit</h3>
                    </div>
                </div>
            </div>
            <script src="../js/wing_man.js" type="text/javascript"></script>

<script src="../js/canvas-confetti.js" type="text/javascript"></script>


<table id="offlineSubmissions" border="1">
  <thead>
    <tr>
      <th>Event</th>
      <th>Match</th>
      <th>Time</th>
      <th>Robot</th>
      <th>Alliance</th>
      <th>Action</th>
      <th>Location</th>
      <th>Result</th>
      <th>Points</th>
    </tr>
  </thead>
  <tbody>
    <!-- Offline entries will be added here -->
  </tbody>
</table>


</table>



        </div>
    </body>
</html>