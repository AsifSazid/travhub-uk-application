<?php

$pdo = new PDO("mysql:host=localhost;dbname=travhub-uk-apply", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
