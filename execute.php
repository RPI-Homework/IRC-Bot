<?php

error_reporting(E_ALL) ; ini_set('display_errors','1');

$stuff = system("which php",$return);

if ($return ==0) {
  echo "script executed successfully";
  echo "<br>";
echo $stuff;
echo "<br>";
echo $return;
} else {
  echo "execution failed";
echo "<br>";
echo $stuff;
echo "<br>";
echo $return;
}

?>