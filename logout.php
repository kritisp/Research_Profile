<?php
/**
 * Logout Handler
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();
set_flash('info', 'You have been safely signed out.');
redirect('login.php');
