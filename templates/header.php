<head>
  <title>TOURS</title>
  <link rel="stylesheet" type="text/css" href="materialize/css/materialize.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <style type="text/css">
  <style type="text/css">
    
  


  html, body { margin: 0; padding: 0; background-color: gray; }

  /* Form styles */
  .bg-container { padding: 20px; width: 90%; }
  .submitbtn { padding-top: 20px !important; }
  h2, h3 { font-weight: bold; }
  input[type="text"] { text-transform: uppercase; }


  .btn {
    font-weight: bold;
    font-size: 20px;
    color: white;
    background-color: #2196f3;
  }
  .btn:hover {
    background-color: #4993de;
  }

  /* Carousel Section */
  .hero-carousel {
    position: relative;
    background: url('assets/island.jpg') center/cover fixed no-repeat;
    padding: 80px 0;
  }
  .hero-carousel .overlay-bg {
    background: rgba(0, 0, 0, 0.45);
    padding: 40px 0;
  }

  /* Destination Cards */
  .destination-card {
    position: relative;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    cursor: pointer;
    transition: transform 0.3s ease;
    width: 90%;
    max-width: 600px;
    margin: 0 auto;
  }

  .destination-card img {
    width: 100%;
    height: 220px;
    object-fit: cover;
    display: block;
    border-radius: 15px;
  }

  .country-label {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: rgba(0, 0, 0, 0.55);
    color: white;
    padding: 5px 10px;
    border-radius: 10px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 1rem;
  }

  .card-reveal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(20, 20, 20, 0.85);
    color: white;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
    text-align: center;
    transition: opacity 0.4s ease, transform 0.4s ease;
  }

  .card-reveal-overlay.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
  }

  .reveal-content { max-width: 90%; }
  .reveal-content .card-title {
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    justify-content: space-between;
  }

  .close-reveal { cursor: pointer; color: #fff; }

  /* Responsive Adjustments */
  @media (max-width: 768px) {
    .destination-card img { height: 130px; }
    .country-label { font-size: 0.9rem; }
  }

  @media (max-width: 480px) {
    .destination-card img { height: 110px; }
    .country-label { font-size: 0.8rem; bottom: 6px; left: 6px; }
  }
  nav{
    background-image: url(assets/Banner.png);
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center center;
    height: 100px;
  }
  a #tours{
    left: 50px; 
    size: 100px;
    
    }
  .page-footer {
      margin: 0;      
      padding-bottom: 0;
    }

     #urs-logo{

    height: 100px;
    width: 140px;
    

  }
  #sayd{
    left: -25;
  }
  #mat{
    color: purple;
  }
  
















  
  </style>
</head>
<body class="white lighten-4">
<nav>
    <div class="nav-wrapper">

              <a href="#" class="brand-logo center" id="tours"><i class="material-icons hide-on-med-and-down ">flight_takeoff</i>TOURS</a>
          
                <a href="#" class="sidenav-trigger show-on-large" data-target="mobile-menu" id="sayd">       
                      
                <i class="img" id="log">
                  <img  src="assets/Logo.png" id="urs-logo" >
                </i></a>  


              <ul class="hide-on-med-and-down" id="asdasd">
                <li><a class="" style="position: relative; top: 15px; right: -1250px;" id="lolap"><i class="material-icons left" id="mat">airplane_ticket</i>Get your tickets here</a></li>
              </ul>  
              <ul class=" hide-on-med-and-down" id="logen">
                 <li><a class="" href="#" style="position: relative; top: 15px; right: -1400px;" ><i class="material-icons left" id="mat">login</i></a>Log-in</li>
              </ul>  


             
        
    </div>
   

                



    <ul class="sidenav blue-grey lighten-4" id="mobile-menu">
        <li><div class="user-view">
            <img class="circle" src="assets/circle.jpg">
            <span class="blue-text name">Pangalan ni user</span>

          </div></li>
          <li><a href="#">Travels</a></li>
          <li><a href="#" >TITE</a></li>
          <li><a href="#">Ewan</a></li>
  
    </ul>


  </nav>   
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
  $(document).ready(function(){
    $('.sidenav').sidenav();
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
