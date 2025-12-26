<?php
$password = 'Demg.74372h;';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
echo "Hash generado para 'Demg.74372h;':<br>";
echo $hashed_password;
?>