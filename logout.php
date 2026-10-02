<?php
/*
 * logout.php  -  Ends the customer session.
 * The cart stays in the database, so it is still there at the next login.
 */
require_once __DIR__ . '/includes/auth.php';

logout_user();
flash('info', 'You have been logged out. See you soon!');
redirect('index.php');
