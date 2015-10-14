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

/**
 * This file contains the docker class for the assignfeedback_docker plugin
 *
 * @package   assignfeedback_docker
 * @copyright 2015 Skylar kelty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignfeedback_docker;

/**
 * This class adds and removes annotations from a page of a response.
 *
 * @package   assignfeedback_docker
 * @copyright 2015 Skylar kelty
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class docker {
    private $docker;
    private $context;
    private $version;
    private $containerid;
    private $outputbuffer = '';
    private $built = false;

    /**
     * Constructor.
     */
    public function __construct() {
        $client = new \Docker\Http\DockerClient(array(), 'unix:///var/run/docker.sock');
        $this->docker = new \Docker\Docker($client);

        // TODO - separate into different container versions
        // so we can allow users to say "I want PHP 7.0".
        $this->version = 1;
        $this->containerid = "moodle-docker-{$this->version}";

        $this->context = new \Docker\Context\ContextBuilder();
        $this->context->from('centos:latest');
        $this->context->run('adduser w3moodle');
        $this->context->run('mkdir /build');
        $this->context->run('chown w3moodle /build');

        switch ($this->version) {
            default:
                $this->context->run('yum install -y php');
            break;
        }
    }

    /**
     * Build a container.
     */
    private function build_container() {
        if ($this->built) {
            return;
        }

        $text = '';
        $context = $this->context->getContext();

        $this->docker->build($context, $this->containerid, function ($output) use (&$text) {
            $text .= $output['stream'] . "\n";
        }, true, false, true);

        $this->outputbuffer .= $text;
        $this->built = true;
    }

    /**
     * Return the output buffer.
     */
    public function get_output() {
        return $this->outputbuffer;
    }

    /**
     * Add a file to the container.
     */
    public function add_file($filename, $relativeto) {
        $relativeto = realpath($relativeto);
        $filename = realpath($filename);

        $basename = substr($filename, strlen($relativeto));
        $this->context->add('/build/' . $basename, file_get_contents($filename));
    }

    /**
     * Run the container.
     */
    public function run($command) {
        $this->build_container();

        $type   = 0;
        $output = "";

        // Create the runtime environment.
        $manager = $this->docker->getContainerManager();
        $container = new \Docker\Container(array(
            'Image' => $this->containerid,
            'Cmd' => $command
        ));
        $manager->create($container);

        // Attach to the container.
        $response = $manager->attach($container, function ($log, $stdtype) use (&$type, &$output) {
            $type = $stdtype;
            $output = $log;
        });
        $manager->start($container);

        $this->outputbuffer .= "\n{$output}\n";

        // Buffer this.
        $response->getBody()->getContents();

        // Finish up.
        $manager->stop($container);
        $manager->remove($container);

        return $container->getExitCode();
    }
}
