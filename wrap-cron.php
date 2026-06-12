<?php
$cmd = 'php /home/finkask/www/finkashi-analytics/scripts/cron-quotidien.php 2>&1';
$out = shell_exec($cmd);
file_put_contents('/home/finkask/cron.log', $out);