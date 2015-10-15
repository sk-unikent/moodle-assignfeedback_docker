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
    const LATEST_VERSION = '0.0.1';
    private $docker;
    private $context;
    private $version;
    private $containerid;
    private $buffer;
    private $outputbuffer;
    private $built = false;

    /**
     * Constructor.
     */
    public function __construct($containerversion = 'latest', $buffer = true) {
        $this->buffer = $buffer;

        $client = new \Docker\Http\DockerClient(array(), 'unix:///var/run/docker.sock');
        $this->docker = new \Docker\Docker($client);

        if ($containerversion == 'latest') {
            $containerversion = self::LATEST_VERSION;
        }

        $this->version = $containerversion;
        $this->containerid = hash('adler32', "moodle-docker-{$this->version}");

        $this->context = new \Docker\Context\ContextBuilder();
        $this->context->from('centos:latest');
        $this->context->run('adduser w3moodle');
        $this->context->run('mkdir /build');
        $this->context->run('chown w3moodle /build');

        switch ($this->version) {
            case '0.0.1':
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

        echo "~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n";
        echo "~~~~~~~ University of kent ~~~~~~~\n";
        echo "~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n";
        echo "\n";

        // Check we have the image.
        $imageman = $this->docker->getImageManager();
        try {
            $image = $imageman->find('centos', 'latest');
        } catch (\Exception $e) {
            echo " -> Pulling base image...\n\n";
            $imageman->pull('centos', 'latest');
        }

        echo " -> Building docker container, version: {$this->version}...\n\n";

        // Run the build.
        $context = $this->context->getContext();
        $this->docker->build($context, $this->containerid, function ($output) {
            if (isset($output['stream'])) {
                echo $output['stream'] . "\n";
            }
        }, true, true, true);

        echo " ->  Build complete!\n\n";

        $this->built = true;
    }

    /**
     * Return the output buffer (only if we are buffering).
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

        // Slashes.
        if (strrpos($relativeto, '/') + 1 !== strlen($relativeto)) {
            $relativeto .= '/';
        }

        $basename = substr($filename, strlen($relativeto));
        $this->context->add('/build/' . $basename, file_get_contents($filename));
    }

    /**
     * Run the container.
     */
    public function run($command) {
        if ($this->buffer) {
            ob_start();
        }

        $this->build_container();

        // Create the runtime environment.
        $manager = $this->docker->getContainerManager();
        $container = new \Docker\Container(array(
            'Image' => $this->containerid,
            'Cmd' => $command
        ));
        $manager->create($container);

        // Attach to the container.
        $response = $manager->attach($container, function ($log, $stdtype) {
            echo "\n{$log}\n";
        });
        $manager->start($container);

        // Buffer this.
        $response->getBody()->getContents();

        // Finish up.
        $manager->stop($container);

        if ($this->buffer) {
            $this->outputbuffer .= ob_get_contents();
            ob_end_clean();
        }

        return $container->getExitCode();
    }
}
