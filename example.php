<?php
/*


   _____ _                     _____  _    _ _____  
  / ____| |                   |  __ \| |  | |  __ \ 
 | (___ | | ___   _ _ __   ___| |__) | |__| | |__) |
  \___ \| |/ / | | | '_ \ / _ \  ___/|  __  |  ___/ 
  ____) |   <| |_| | |_) |  __/ |    | |  | | |     
 |_____/|_|\_\\__, | .__/ \___|_|    |_|  |_|_|     
               __/ | |                              
              |___/|_|                              


Version: 1.0
GitHub : https://github.com/Kibioctet/SkypePHP

You can use this example with a browser or CLI.

*/
header("Content-Type: text/plain");



require("skype.class.php");

$username = "your username";
$password = "your password";

$skype = new skype($username, $password);

$skype->sendMessage("echo123", "Hello world from PHP!"); // sends a message to echo123

echo "Message list of you and echo123:\n\n";
print_r($skype->getMessagesList("echo123")); // reads the list of messages of you and echo123

$skype->createGroup(Array("echo123")); // creates a group with you and echo123