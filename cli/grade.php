<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . "/../../../../../config.php");

use \assignfeedback_docker\docker;

$docker = new docker(docker::PHP5_VERSION);
$docker->flush_output();
$docker->run(['/opt/rh/rh-php56/root/usr/bin/php', '--version']);
echo $docker->get_output();

$docker = new docker(docker::PYTHON2_VERSION);
$docker->flush_output();
$docker->run(['/opt/rh/python27/root/usr/bin/python', '--version']);
echo $docker->get_output();

$docker = new docker(docker::PYTHON3_VERSION);
$docker->flush_output();
$docker->run(['/opt/rh/rh-python31/root/usr/bin/python', '--version']);
echo $docker->get_output();
