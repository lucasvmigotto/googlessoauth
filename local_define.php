<?php

ini_set('session.cookie_samesite', 'Lax');

// Those settings **must** be present in 
// production level <GLPI_ROOT>/config/local_define.php
# ini_set('session.cookie_secure', 1);
# ini_set('session.cookie_httponly', 1);

// Starting behind a load balancer 
# if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
#     $_SERVER['HTTPS'] = 'on';
#     $_SERVER['SERVER_PORT'] = 443;
# }