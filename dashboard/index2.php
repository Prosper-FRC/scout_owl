<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
        
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owl Admin</title>
    <style>
/* General Reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Full-width top section */
#startMatch {
    width: 100vw;
    background-color: #333; /* Example background */
    color: white;
    text-align: center;
    padding: 20px;
    font-size: 1.5rem;
    height: auto;
    padding: 20px;
}

/* Grid container for lower sections */
#lowerContainer {
    display: grid;
    gap: 10px;
    padding: 10px;

    /* Grid layout for larger screens */
    grid-template-columns: repeat(auto-fit, minmax(395px, 1fr));
    justify-content: center;
}

/* Individual square items */
#red1, #red2, #red3, #blue1, #blue2, #blue3 {
    width: 395px;
    height: 395px; /* Ensuring square shape */
    background-color: #555; /* Example color */
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

/* Mobile: Convert to collapsible layout */
@media (max-width: 768px) {
    #lowerContainer {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    #red1, #red2, #red3, #blue1, #blue2, #blue3 {
        width: 100%; /* Take full width on smaller screens */
        max-width: 395px;
    }
}


    </style>

</head>
<body>

<div id="startMatch">
    Start Match
</div>

<div id="lowerContainer">
    <div id="red1">Red 1</div>
    <div id="red2">Red 2</div>
    <div id="red3">Red 3</div>
    <div id="blue1">Blue 1</div>
    <div id="blue2">Blue 2</div>
    <div id="blue3">Blue 3</div>
</div>




</div>

</body>


</html>