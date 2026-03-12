<html lang="en">
  <head>
    <link rel="icon" type="image/x-icon" href="/icons/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>the Stat Owl</title>
    <style>
    <style>
      @font-face {
      font-family: 'Roboto';
      src: url('/../Stat_Goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf'),
      url('/../Stat_Goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf');
      font-weight: normal;
      font-style: normal;
      }
      @font-face {
      font-family: 'Griffy';
      src: url('/../Stat_Goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf'),
      url('/../Stat_Goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf');
      font-weight: normal;
      font-style: normal;
      }
      @font-face {
      font-family: 'Comfortaa';
      src: url('/../Stat_Goblin/fonts/Comfortaa/Comfortaa-Regular.ttf') format('ttf'),
      url('/../Stat_Goblin/fonts/Comfortaa/Comfortaa-Regular.ttf') format('ttf');
      font-weight: normal;
      font-style: normal;
      }
      /* Global Styles */
      body, html {
      font-family: 'Comfortaa', sans-serif;
      margin: 0;
      padding: 0;
      background: #222;
      color: #eee;
      line-height: 1.5;
      text-align: center;
      }
      .containerOuter {
      background-color: #333;
      border-bottom: 1px solid #444;
      width: 100%;
      padding: 1rem;
      box-sizing: border-box;
      }
      .container {
      max-width: 800px;
      margin: auto;
      }
      /* Grid layout for links */
      .grid-container {
      display: grid;
      grid-gap: 1rem;
      margin-bottom: 1rem;
      grid-template-columns: repeat(2, 1fr); /* Two columns by default */
      }
      /* For wider screens, switch to 5 columns */
      @media (min-width: 768px) {
      .grid-container {
      grid-template-columns: repeat(5, 2fr);
      }
      }
      .grid-item {
      display: flex;
      flex-direction: column;
      }
      .grid-item label {
      margin-bottom: 0.3rem;
      font-weight: bold;
      font-size: 1rem;
      }
      .logo {
      width: 400px;
      display: block;
      margin: 0 auto 1rem auto;
      }
      .icon {
      width: 80px;
      }
    </style>
    </style>
  </head>
  <body>
    <div class="containerOuter">
      <div class="container">
     
        <a href=".."><img src="../images/owlAnalytics.png" class="logo" alt="Logo"></a>
        <!-- 2x2 Grid for Dropdowns -->
        <div class="grid-container">

             <div class="grid-item">
            <label for="eventDropdown"><strong>Match Tables:</strong></label>
            <a href ="match_tables.php"><img class="icon" src="../icons/lists.png" alt="event Table" ></a>
          </div>





             <div class="grid-item">
            <label for="eventDropdown"><strong>Match Prediction:</strong></label>
            <a href ="match_prediction.php"><img class="icon" src="../icons/online_prediction.png" alt="event Table" ></a>
          </div>

                    <div class="grid-item">
            <label for="eventDropdown"><strong>Owl Tv:</strong></label>
            <a href ="tv.php"><img class="icon" src="../icons/tv.png" alt="Admin Console" ></a>
          </div>

          <div class="grid-item">

            <label for="eventDropdown"><strong>ML Alliance Picker:</strong></label>
           
 <a href="alliance_picker.php" id="openChart">
    <img class="icon" src="../icons/predict_alliance.png" alt="Admin Console">
  </a>

          </div>








             <div class="grid-item">
            <label for="eventDropdown"><strong>Talon Tables:</strong></label>
            <a href ="stat_owl_event.php"><img class="icon" src="../icons/table.png" alt="event Table" ></a>
          </div>
          <div class="grid-item">
              <label for="eventDropdown"><strong>Owl Cards:</strong></label>
              <a href ="owlcards.php"><img class="icon" src="../icons/table.png" alt="owl Cards"></a>
          
      </div>
    </div>
  </body>
  <script>

   

  </script>
</html>