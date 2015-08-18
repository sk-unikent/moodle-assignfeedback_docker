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

require_once("../../../../config.php");

require_login();
$PAGE->set_url('/mod/assign/feedback/docker/index.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_context(\context_system::instance());

echo $OUTPUT->header();

$client = new Docker\Http\DockerClient(array(), 'unix:///var/run/docker.sock');
$docker = new Docker\Docker($client);

$contextbuilder = new Docker\Context\ContextBuilder();
$contextbuilder->from('centos:latest');
$contextbuilder->add('/tmp/assignment', file_get_contents(dirname(__FILE__) . '/tests/fixtures/assignment.tar'));

echo "<pre>";
$docker->build($contextbuilder->getContext(), 'thisisauniqueid', function ($output) use (&$content, &$timecalled) {
    if (isset($output['stream'])) {
        echo $output['stream'] . "\n";
    }
}, true, false, true);
echo "</pre>";

$type   = 0;
$output = "";

$manager = $docker->getContainerManager();
$container = new Docker\Container(array(
    'Image' => 'thisisauniqueid',
    'Cmd' => array('/usr/bin/python', '/tmp/assignment/src/grade.py')
));
$manager->create($container);
$response = $manager->attach($container, function ($log, $stdtype) use (&$type, &$output) {
    $type = $stdtype;
    $output = $log;
});
$manager->start($container);

$response->getBody()->getContents();

echo "<pre>";
echo $output;
echo "</pre>";

$manager->stop($container);
$manager->remove($container);

echo $OUTPUT->footer();
