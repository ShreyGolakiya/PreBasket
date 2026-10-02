<?php
/*
 * admin/logout.php  -  Ends the staff session only.
 * A customer logged in on the same browser stays logged in.
 */
require_once __DIR__ . '/../includes/admin_auth.php';

logout_admin();
flash('info', 'You have been logged out of the admin panel.');
redirect('admin/login.php');
