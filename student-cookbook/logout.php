<?php
require_once 'includes/functions.php';
$_SESSION = [];
session_destroy();
session_start();
flash('You have signed out.', 'info');
redirect('index.php');
