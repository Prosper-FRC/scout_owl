            // uncomment this section for code validation
            //        const activeCodeFromServer = "<?php echo $activeCode; ?>";  // Assuming PHP is used to pass the active code
            //
            //        // Function to validate the entered code
            //        function validateCode() {
            //            const enteredCode = document.getElementById('input1').value +
            //                                 document.getElementById('input2').value +
            //                                 document.getElementById('input3').value +
            //                                 document.getElementById('input4').value;
            //
            //            if (enteredCode === activeCodeFromServer) {
            //                document.getElementById('block').style.display = 'block';
            //                document.getElementById('validator').style.display = 'none';
            //            } else {
            //                alert("Invalid code. Please enter the correct active code.");
            //            }
            //        }
            //
            //        // Add event listener for input fields
            //        document.querySelectorAll('.input-box').forEach((input, index) => {
            //            input.addEventListener('input', function(event) {
            //                // Move focus to the next input when a character is typed
            //                if (event.target.value.length === 1 && index < 3) {
            //                    document.querySelectorAll('.input-box')[index + 1].focus();
            //                }
            //            });
            //
            //            // Add event listener for Enter key press
            //            input.addEventListener('keydown', function(event) {
            //                // When Enter is pressed in the last box (input4), validate the code
            //                if (event.key === 'Enter' && index === 3) {
            //                    validateCode();  // Trigger validation on Enter key press
            //                }
            //            });
            //        });
            //   







 const params = new URLSearchParams(window.location.search);
            const event = params.get('event');
            const match = params.get('match');
            const robot = params.get('robot');
            const alliance = params.get('alliance');
          
            
            // Points setup
            const auto_points = {
               "crosses_starting_line": 3,
               "starting_position_1":0,
               "starting_position_2":0,
               "starting_position_3":0,
               "picks_up_color":0,
               "scores_coral_level_1": 2,
               "scores_coral_level_2": 4,
               "scores_coral_level_3": 6,
               "scores_coral_level_4": 8,
               "picks_up_algae": 0,
               "scores_algae_net": 8,
               "scores_algae_processor": 12,
               "attempts_shallow_climb": 0, // Typically endgame, but zeroed out here
               "attempts_deep_climb": 0, // Typically endgame, but zeroed out here
               "attempts_parked": 0,     // Typically endgame, but zeroed out here
               "plays_defense": 0,
               "attempts_to_steal": 0,
               "disabled": 0,
               "delete_action": 0,
               "auton_left": 0,
               "auton_center": 0,
               "auton_right": 0,
            };
            
            const actions = {
               "crosses_starting_line": { "location": "starting_line", "points": 0 },
               "starting_position_1":{ "location": "starting_pad", "points": 0 },
               "starting_position_2":{ "location": "starting_pad", "points": 0 },
               "starting_position_3":{ "location": "starting_pad", "points": 0 },
               "auton_left":{ "location": "starting_pad", "points": 0 },
               "auton_center":{ "location": "starting_pad", "points": 0 },
               "auton_right":{ "location": "starting_pad", "points": 0 },




               "picks_up_coral": {"location": "station", "points": 0},
               "scores_coral_level_1": { "location": "reef", "points": 1 },
               "scores_coral_level_2": { "location": "reef", "points": 2 },
               "scores_coral_level_3": { "location": "reef", "points": 3 },
               "scores_coral_level_4": { "location": "reef", "points": 4 },
               "picks_up_algae": { "location": "mid_field", "points": 0 },
               "scores_algae_net": { "location": "net", "points": 4 },
               "scores_algae_processor": { "location": "processor", "points": 6 },
               "attempts_shallow_climb": { "location": "barge", "points": 6 },
               "attempts_deep_climb": { "location": "barge", "points": 12 },
               "attempts_parked": { "location": "barge", "points": 2 },
               "plays_defense": { "location": "opponent_side", "points": 0 },
               "attempts_to_steal": { "location": "opponent_reef", "points": 0 },
               "disabled": { "location": "anywhere", "points": 0 },
               "delete_action": { "location": "anywhere", "points": 0 },
               
            };
            
            // Initial score setup
            let score = 0;
            
            // Timer setup
            let timerValue = 150;  // Initial timer value (starts at 150)
            let timerInterval;
            
            // Update the page with selected data
            document.getElementById('eventName').textContent = event
               ? ` ${event.replace(/_/g, ' ').replace(/\b\w/g, function(char) { return char.toUpperCase(); })}`
               : 'Unknown Event';
            document.getElementById('matchNumber').textContent = match
               ? `Match #: ${match}`
               : 'Match #: N/A';
            document.getElementById('robotName').textContent = robot
               ? `Robot #: ${robot}`
               : 'Robot #: N/A';
            
            // Change the background color of #block based on alliance
            const blockElement = document.getElementById('block');
            

            
            
            // Update alliance and opponent button colors
            const allianceElements = document.querySelectorAll('.alliance');
            if (alliance === 'Red') {
               allianceElements.forEach(element => element.style.borderColor = '#C0392B'); // Red
            } else if (alliance === 'Blue') {
               allianceElements.forEach(element => element.style.borderColor = '#2C3E50'); // Blue
            }
            
            const opponentElements = document.querySelectorAll('.opponent');
            if (alliance === 'Red') {
               opponentElements.forEach(element => element.style.borderColor = '#2C3E50'); // Blue
            } else if (alliance === 'Blue') {
               opponentElements.forEach(element => element.style.borderColor = '#C0392B'); // Red
            }
            
            
            // Display the current location (from selected action)
            function updateLocationDisplay() {
               const action = document.querySelector('.button.selected');
               const location = action ? actions[action.getAttribute('data-action')]?.location : 'Unknown';
               document.getElementById('locationDisplay').textContent = `Location: ${location}`;
            }
            
            // Event Listener for Buttons (selecting actions)
            document.querySelectorAll('.button').forEach(button => {
               button.addEventListener('click', () => {
                   // Remove "selected" class from all buttons
                   document.querySelectorAll('.button').forEach(btn => btn.classList.remove('selected'));
            
                   // Add "selected" class to the clicked button
                   button.classList.add('selected');
            
                   // Update location display based on selected action
                   updateLocationDisplay();
               });
            });
            
            function throwCrazyConfetti() {
    let count = 800;
    let defaults = {
        origin: { y: 0.7 }
    };

    function fire(particleRatio, opts) {
        confetti(Object.assign({}, defaults, opts, {
            particleCount: Math.floor(count * particleRatio)
        }));
    }

    fire(0.25, { spread: 26, startVelocity: 55 });
    fire(0.2, { spread: 60 });
    fire(0.35, { spread: 100, decay: 0.91 });
    fire(0.1, { spread: 120, startVelocity: 25, decay: 0.92 });
    fire(0.1, { spread: 140, startVelocity: 45 });
}



            // Function to start the countdown timer
            function startTimer() {
            
               
               
               // Trigger vibration when the timer starts
               if ("vibrate" in navigator) {
                   navigator.vibrate(200); // Vibrate for 200ms when the timer starts
               }
            
               // Shake the screen when timer starts
               document.getElementById('scoreboard').classList.add('shake');
               
               // Update the timer value every second
               timerInterval = setInterval(function () {
                   timerValue -= 1;
                   document.getElementById('timer-inner').textContent = timerValue;
            
            
            
                  // Check if time into match is 14, and trigger the flash effect
                  const timeIntoMatch = 150 - timerValue;
                  if (timeIntoMatch === 14) {
                      blockElement.classList.add('flash'); // Add flash effect to the block container
            
                      // Remove the flash class after 1 second to stop the flashing
                      setTimeout(() => {
                          blockElement.classList.remove('flash');
                      }, 1000);
                  }
            
            
            
                   // Do not show an alert at 0 or 150, but stop the timer when it reaches 0
                   if (timerValue <= 0) {
                       clearInterval(timerInterval); // Stop the countdown when timer reaches 0
                   }
               }, 1000);  // 1 second interval
            }
            
            
            
            
            
            // Display query parameters in the console div
            const consoleDiv = document.getElementById('console');
            if (consoleDiv) {
               consoleDiv.innerHTML = `
                   <strong>Query Parameters Received:</strong><br>
                   Event: ${event}<br>
                   Match: ${match}<br>
                   Robot: ${robot}<br>
                   Alliance: ${alliance}
               `;
            }
            
            // Global variables for match data
            let startTime = null;
            let totalPause = 0;
            let pausedAt = null;
            let isActive = 0;
            let isPaused = 0;
            let year = null;
            let timerTime =150;
            let confettiTime =0;
            
            // Function to fetch match data
            async function fetchMatchData() {
               try {
                   const url = `../php/getMatchTimer.php?event=${encodeURIComponent(event)}&match=${encodeURIComponent(match)}`;
                   const response = await fetch(url);
                   if (!response.ok) throw new Error(`Failed to fetch match data. Status: ${response.status}`);
                   const data = await response.json();
            
                   // Update global variables
                   startTime = data.start_time || null;
                   totalPause = data.total_pause_duration || 0;
                   pausedAt = data.paused_at || null;
                   isActive = data.active || 0;
                   isPaused = data.pause || 0;
                   year = data.year || null;
            
                   // Display fetched data in the console div
                   if (consoleDiv) {
                       consoleDiv.innerHTML = `
                           <strong>Match Data:</strong><br>
                           Start Time: ${startTime}<br>
                           Total Pause Duration: ${totalPause}<br>
                           Paused At: ${pausedAt || 'N/A'}<br>
                           Active: ${isActive}<br>
                           Pause: ${isPaused}<br>
                           Year: ${year}
                       `;
                   }
               } catch (error) {
                   console.error("Error fetching match data:", error);
                   if (consoleDiv) {
                       consoleDiv.innerHTML += `<br><strong>Error:</strong> ${error.message}`;
                   }
               }
            }
            
            // Function to calculate and update the match timer
            function updateMatchTimer() {
               const timerElement = document.getElementById('timer-inner');
               if (!startTime || isActive === 0) {
                   timerElement.textContent = "Inactive";
                   return;
               }
            
               const startTimeMs = new Date(startTime).getTime();
               let elapsedSeconds = (Date.now() - startTimeMs) / 1000 - totalPause;
            
               if (isPaused) {
                   timerElement.textContent = "Paused";
                   return;
               }
           
               if (elapsedSeconds > 15 && elapsedSeconds < 18){
                navigator.vibrate([20, 10, 20, 10, 50])

               }

            const remainingSeconds = Math.max(150 - elapsedSeconds, 0);
            timerTime=remainingSeconds
            
               if (remainingSeconds === 0) {
                   timerElement.textContent = "Finished";
                   confettiTime +=1;
                   if (confettiTime <=6){
                    throwCrazyConfetti();
                   }
                   
                   // Call the function to start the song
//playVibrationSong();

                   return;
               }
            
               const minutes = Math.floor(remainingSeconds / 60);
               const seconds = Math.floor(remainingSeconds % 60);
               timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
            
            // Poll match data and update the timer every 1/4second
            setInterval(async () => {


                fetchMatchData();
               updateMatchTimer();
            }, 250);
            
            
            
            
            //timerValue - remainingSeconds;
            
            
            
            
            
            
            
            
            
            // removed when getting the timer from the database was added
         //   document.getElementById('scoreboard').addEventListener('click', function () {
         //      if (timerValue === 150) {  // Start the timer only if it has not already started
         //          startTimer();
         //      }
         //   });
            
            
            
            // Function to show alerts with relevant details
            function showAlert(action, location, timeIntoMatch, points, result) {
               // Log the details to the console or show as an alert
             //  alert(`Action: ${action}\nLocation: ${location}\nTime into match: ${timeIntoMatch} seconds\nPoints: ${points}\nResult: ${result}`);
             
               let scoreboardPoints = document.getElementById('score-inner').textContent;
               scoreboardPoints = parseInt(scoreboardPoints, 10); // Convert to a number
               scoreboardPoints += points; // Add points numerically
               document.getElementById('score-inner').textContent = scoreboardPoints;
            }
            
            
            // Call this function for both swipe right and swipe left
            function handleSwipe(success) {
               const action = document.querySelector('.button.selected');
               const actionData = action ? actions[action.getAttribute('data-action')] : null;
               const location = actionData ? actionData.location : 'Unknown';
            



               // Calculate time into the match: 150 - (timerValue)
               timerValue = timerTime;
               const timeIntoMatch = 150 - timerValue;
            
               // Determine points based on time into the match
               let points = 0;
               if (timeIntoMatch <= 18) {
                   // Use auto points if time into match is less than 15 seconds
                   points = auto_points[action.getAttribute('data-action')] || 0;
               } else {
                   // Use normal action points if time into match is 15 seconds or more
                   points = actions[action.getAttribute('data-action')]?.points || 0;
               }
            
               // If failure, points should be 0
               if (!success) {
                   points = 0;
               }
            
               const statusDisplay = document.getElementById('statusDisplay');
               const result = success ? 'Success' : 'Failure';
               statusDisplay.textContent = result;

console.log(result);
if(result=='Failure'){
                    document.getElementById('block').classList.add('flashFailure'); // Add flash effect to the block container
                    document.getElementById('scoreboard').classList.add('shake');
                   
                      // Remove the flash class after 1 second to stop the flashing
                      setTimeout(() => {
                          document.getElementById('block').classList.remove('flashFailure');
                          document.getElementById('scoreboard').classList.remove('shake');
                      }, 250);

}else{

document.getElementById('block').classList.add('flashSuccess'); // Add flash effect to the block container
                    document.getElementById('scoreboard').classList.add('shake');
                   
                      // Remove the flash class after 1 second to stop the flashing
                      setTimeout(() => {
                          document.getElementById('block').classList.remove('flashSuccess');
                          document.getElementById('scoreboard').classList.remove('shake');
                      }, 250);





}



               // Only alert if time is between 1 and 149 seconds
               if (timerValue > 0 && timerValue < 150) {
                   // Vibrate for 200ms on both success or failure
                   if ("vibrate" in navigator) {
                       navigator.vibrate(200);
                   }
            
                   // Show the alert
                   showAlert(action.getAttribute('data-action'), location, timeIntoMatch, points, result);
            
                   // Send data to the backend for insertion into the database
                   const data = {
                       ip_address: 'user-ip-address',  // Get user IP dynamically if needed
                       event_name: event,
                       match_no: match,
                       time_sec: timeIntoMatch,
                       robot: robot,
                       alliance: alliance,
                       action: action?.getAttribute('data-action'),
                       location: location,
                       result: result,
                       points: points
                   };
            
                   // Send data via fetch
                  // Send data via fetch
                    fetch('../php/insert_submission.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(data),
                    })
                    .then(response => response.json())
                    .then(responseData => {
                        console.log(responseData); // Handle the response from the server
                        // Optionally, if the response indicates failure, save offline
                        if (responseData.status !== 'success') {
                            saveSubmissionOffline(data);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Save submission offline on error
                        saveSubmissionOffline(data);
                    });





               }
            }

/*
This function creates a new row in the offline submissions table using the submission data:
*/
function saveSubmissionOffline(data) {
    const tableBody = document.querySelector('#offlineSubmissions tbody');
    const row = document.createElement('tr');

    // Create cells for each property
    const fields = ['event_name', 'match_no', 'time_sec', 'robot', 'alliance', 'action', 'location', 'result', 'points'];
    fields.forEach(field => {
        const cell = document.createElement('td');
        cell.textContent = data[field];
        row.appendChild(cell);
    });

    tableBody.appendChild(row);
}


/*
This function loops through the table rows, builds the submission data from each row, and tries to resend it. If successful, the row is removed from the table:
*/

function sendOfflineSubmissions() {
    const tableBody = document.querySelector('#offlineSubmissions tbody');
    const rows = Array.from(tableBody.querySelectorAll('tr'));
    
    rows.forEach((row) => {
        const cells = row.querySelectorAll('td');
        const submissionData = {
            event_name: cells[0].textContent,
            match_no: cells[1].textContent,
            time_sec: cells[2].textContent,
            robot: cells[3].textContent,
            alliance: cells[4].textContent,
            action: cells[5].textContent,
            location: cells[6].textContent,
            result: cells[7].textContent,
            points: cells[8].textContent,
        };

        fetch('../php/insert_submission.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(submissionData),
        })
        .then(response => response.json())
        .then(responseData => {
            if (responseData.status === 'success') {
                // Remove the row if the submission was sent successfully
                row.remove();
            }
        })
        .catch(error => {
            console.error("Failed to send offline submission:", error);
            // The row remains for future resend attempts
        });
    });
}




            
            // Swipe functionality
//            let startX = 0;
//            blockElement.addEventListener('touchstart', (e) => {
//               startX = e.touches[0].clientX; // Get initial touch position
//            });
//            
//            blockElement.addEventListener('touchend', (e) => {
//               const endX = e.changedTouches[0].clientX; // Get final touch position
//               const diff = endX - startX; // Calculate swipe distance
//            
//               if (diff > 200) {
//                   // Swiped right (Success)
//                   handleSwipe(true);  // Pass true for success
//               } else if (diff < -200) {
//                   // Swiped left (Failure)
//                   handleSwipe(false); // Pass false for failure
//               } else {
//                   // Insufficient swipe distance
//                   document.getElementById('statusDisplay').textContent = 'Swipe to Submit';
//               }
//            });


let startX = 0;
let startY = 0;
let preventSwipeNav = false; // Flag to determine when to prevent navigation

blockElement.addEventListener('touchstart', (e) => {
    startX = e.touches[0].clientX; // Get initial touch X position
    startY = e.touches[0].clientY; // Get initial touch Y position
    preventSwipeNav = false; // Reset flag at start
}, { passive: false });

blockElement.addEventListener('touchmove', (e) => {
    const moveX = e.touches[0].clientX - startX;
    const moveY = e.touches[0].clientY - startY;

    // Detect if movement is mostly horizontal
    if (Math.abs(moveX) > Math.abs(moveY)) {
        preventSwipeNav = true; // Horizontal swipe detected
        e.preventDefault(); // Prevent browser navigation
    }
}, { passive: false });

document.addEventListener('touchend', (e) => {
    if (!preventSwipeNav) return; // Only process swipe if horizontal movement was detected

    const endX = e.changedTouches[0].clientX;
    const diff = endX - startX; // Calculate swipe distance

    if (diff > 200) {
        // Swiped right (Success)

        handleSwipe(true);
    } else if (diff < -200) {
        // Swiped left (Failure)
        handleSwipe(false);
    } else {
        // Insufficient swipe distance
        document.getElementById('statusDisplay').textContent = 'Swipe to Submit';
    }
});


function checkServerConnectivity() {
    fetch('../php/server_status.php', { cache: 'no-store' })
        .then(response => {
            if (response.ok) {
                // Server is reacxhable, attempt to send offline submissions
                sendOfflineSubmissions();
            } else {
                console.log('Server status check failed with status:', response.status);
            }
        })
        .catch(error => {
            console.log('Server is not reachable:', error);
        });
}

// Check connectivity every 5 seconds (adjust as needed)
setInterval(checkServerConnectivity, 5000);


function playVibrationSong(){




}