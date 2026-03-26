<?php
// Simple web server - run from project folder
$host = '0.0.0.0';
$port = 8000;

echo "Starting server at http://$host:$port\n";
echo "Access from your phone at: http://YOUR_PC_IP:8000\n";

exec("php -S $host:$port");