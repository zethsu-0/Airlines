<head>
	<title>TOURS</title>
	<link rel="stylesheet" type="text/css" href="materialize/css/materialize.min.css">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<style type="text/css">
	nav .brand-logo {
  	left: 15px; 
  	transform: none; 
		}
	.page-footer {
  		margin: 0;      
  		padding-bottom: 0;
		}
	</style>
</head>
<body class="white lighten-4">
<nav class="blue">
    <div class="nav-wrapper">
      <a href="#" class="brand-logo center"><i class="material-icons hide-on-med-and-down">flight_takeoff</i>TOURS</a>
      <a href="#" class="sidenav-trigger show-on-large" data-target="mobile-menu">                    
                <i class="material-icons">menu</i>
            </a>
      <ul class="right hide-on-med-and-down">
        <li><a class="btn wave-effect wave-light " href="#"><i class="material-icons left">airplane_ticket</i>Get Your Ticket</a></li>
        <li><a class="btn wave-effect wave-light " href="#"><i class="material-icons left">login</i>Login</a></li>
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
