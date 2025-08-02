<?php echo "Welcome to Nathanael's IT202 site";
// replace {your name} with your name 
require(__DIR__."/../lib/functions.php");
die(header("Location: " . get_url("/project/login.php")));