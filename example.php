<?php

require_once "./vendor/autoload.php";

use Morker\Master;
use Morker\Task\WorkerTaskInterface;

class Task implements WorkerTaskInterface {

    public $kill = false;

    public function run(\Morker\Worker $worker)
    {
        while(!$this->kill) {
            $worker->houseKeep();
            echo "Running " . $worker->nr . "\n";
            sleep(3);
            exit(254);
        }
    }

    public function softKill()
    {
        $this->kill = true;
    }

    public function kill()
    {
        exit;
    }

    public function close()
    {
        echo "Master send close command\n";
        sleep(5);
        $this->kill = true;
    }

}

$task = new Task();

$config = new \Morker\Configurator();
$config->workerProcesses = 3;
$config->timeout = 0.1;

$master = new Master($config, null);
$master->workerTask = $task;
$master->start();