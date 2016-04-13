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
    const BASE_VERSION = 'base_1';
    const PHP5_VERSION = 'php5_1';
    const PYTHON2_VERSION = 'python2_1';
    const PYTHON3_VERSION = 'python3_1';
    private $docker;
    private $context;
    private $version;
    private $containerid;
    private $buffer;
    private $outputbuffer;
    private $built;

    /**
     * Constructor.
     */
    public function __construct($containerversion = 'base', $buffer = false) {
        $this->buffer = $buffer;
        $this->built = false;

        $client = new \Docker\DockerClient([
            'remote_socket' => 'unix:///var/run/docker.sock',
            'ssl' => false
        ]);
        $this->docker = new \Docker\Docker($client);

        if ($containerversion == 'base') {
            $containerversion = self::BASE_VERSION;
        }

        $this->version = $containerversion;
        $this->containerid = hash('adler32', "moodle-docker-{$this->version}");

        $this->context = new \Docker\Context\ContextBuilder();
        $this->context->from('centos:latest');
        $this->context->run('adduser w3moodle');
        $this->context->run('mkdir /build');
        $this->context->run('chown w3moodle /build');
        $this->context->run('yum install -y scl-utils scl-utils-build redhat-rpm-config xml-common zip');

        switch ($this->version) {
            case self::PHP5_VERSION:
                $this->install_scl('rh-php56');
            break;

            case self::PYTHON2_VERSION:
                $this->install_scl('python27');
            break;

            case self::PYTHON3_VERSION:
                $this->install_scl('rh-python34');
            break;
        }
    }

    /**
     * SCL shorthand.
     */
    private function install_scl($package) {
        $url = "https://www.softwarecollections.org/en/scls/rhscl/{$package}/epel-7-x86_64/download/rhscl-{$package}-epel-7-x86_64.noarch.rpm";
        $this->context->run("rpm -ivh {$url}");
        $this->context->run("yum install -y --skip-broken {$package}");
        $this->context->run("scl enable {$package} bash");
        $this->context->run("echo 'source /opt/rh/{$package}/enable' >> /etc/bashrc");
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
        echo "Container hash: {$this->containerid}\n\n";

        // Check we have the image.
        $imageman = $this->docker->getImageManager();
        try {
            $image = $imageman->find('centos', 'latest');
        } catch (\Exception $e) {
            echo " -> Pulling base image...\n\n";
            $imageman->create('centos:latest');
        }

        echo "-> Building docker container, version: {$this->version}...\n\n";

        // Run the build.
        $context = $this->context->getContext();
        $result = $imageman->build($context->toStream(), [
            't' => $this->containerid
        ], \Docker\Manager\ContainerManager::FETCH_STREAM);
        $result->onFrame(function (\Docker\API\Model\BuildInfo $buildinfo) {
            echo $buildinfo->getStream();
        });
        $result->wait();

        if (!$result) {
            echo "->  Build failed!\n\n";
            return false;
        }

        echo "->  Build complete!\n\n";

        $this->built = true;
        return true;
    }

    /**
     * Return the output buffer (only if we are buffering).
     */
    public function get_output() {
        return $this->outputbuffer;
    }

    /**
     * Flush the output buffer (only if we are buffering).
     */
    public function flush_output() {
        $output = $this->outputbuffer;
        $this->outputbuffer = '';
        return $output;
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
     * Should we start buffering?
     */
    private function start_buffer() {
        if ($this->buffer) {
            ob_start();
        }
    }

    /**
     * Should we end buffering?
     */
    private function end_buffer() {
        if ($this->buffer) {
            $this->outputbuffer .= ob_get_contents();
            ob_end_clean();
        }
    }

    /**
     * Run the container.
     */
    public function run($command) {
        $this->start_buffer();

        if (!$this->built) {
            if (!$this->build_container()) {
                return false;
            }
        }

        // Create the runtime environment.
        $manager = $this->docker->getContainerManager();

        $config = new \Docker\API\Model\ContainerConfig();
        $config->setImage($this->containerid);
        $config->setCmd($command);
        $config->setAttachStdin(true);
        $config->setAttachStdout(true);
        $config->setAttachStderr(true);

        $createresult = $manager->create($config);
        $id = $createresult->getId();
        $container = $manager->find($id);

        // Attach to the container.
        $response = $manager->attach($id, [
            'stream' => true,
            'stdin' => true,
            'stdout' => true,
            'stderr' => true
        ]);

        $manager->start($id);

        $response->onStdout(function ($stdout) {
            echo "\n{$stdout}\n";
        });
        $response->onStderr(function ($stderr) {
            echo "\n{$stderr}\n";
        });

        $response->wait();

        // Finish up.
        $manager->stop($id);

        $this->end_buffer();

        return $container->getState()->getExitCode();
    }
}
