<?php
/*
 * This file is part of the Morker package.
 *
 * (c) Aurimas Niekis <aurimas.niekis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(ticks=1);
namespace Morker;

use Morker\Utils\WorkerMessage;

class Worker
{
    /**
     * @var bool
     */
    public $isMaster;

    /**
     * @var int PID of Fork
     */
    public $pid;

    /**
     * @var \Morker\Fifo Fifo Object
     */
    public $fifo;

    /**
     * @var \Morker\Master Master Object
     */
    public $master;

    /**
     * @var int Worker Nr
     */
    public $nr;

    /**
     * @var int Tick for lazy forks keeper
     */
    public $tick = 0;

    /**
     * @var \Morker\Task\WorkerTaskInterface
     */
    public $task;

    /**
     * @var bool Debug flag
     */
    public $debug;

    /**
     * @var int Status flag
     */
    public $status;

    /**
     * @var array
     */
    public $definedActions;

    /**
     * Worker Constructor
     *
     * @param bool   $isMaster
     * @param int    $workerNr
     * @param int    $wPid
     * @param Master $master
     * @param Fifo   $fifo
     */
    public function __construct($isMaster = false, $workerNr, $wPid = null, Master $master, Fifo $fifo = null)
    {
        $this->isMaster = $isMaster;
        $this->nr       = $workerNr;
        $this->wPid     = $wPid;
        $this->master   = $master;
        $this->fifo     = $fifo;
        $this->debug    = $this->master->config['default'];
        $this->definedActions = array(
            WorkerMessage::KILL => array($this, "killChild"),
            WorkerMessage::SOFT_KILL => array($this, "ssoftKillChild"),
            WorkerMessage::CLOSE => array($this, "closeChild"),
        );
    }

    public function isSuccessful()
    {
        return 0 === $this->getExitStatus();
    }

    public function isExited()
    {
        return null !== $this->status && pcntl_wifexited($this->status);
    }

    public function isStopped()
    {
        return null !== $this->status && pcntl_wifstopped($this->status);
    }

    public function isSignaled()
    {
        return null !== $this->status && pcntl_wifsignaled($this->status);
    }

    public function getExitStatus()
    {
        if (null !== $this->status) {
            return pcntl_wexitstatus($this->status);
        }

        return null;
    }

    public function getTermSignal()
    {
        if (null !== $this->status) {
            return pcntl_wtermsig($this->status);
        }

        return null;
    }

    public function getStopSignal()
    {
        if (null !== $this->status) {
            return pcntl_wstopsig($this->status);
        }

        return null;
    }

    public function bindWorkerListener()
    {
        if (!$this->isMaster) {
            $signal = $this->master->config['fifoSignal'];
            if ($signal) {
                pcntl_signal($signal, array($this, "signalListener"));
            }
        }
    }

    public function unbindMasterSignals() {
        if (!$this->isMaster) {
            foreach ($this->master->config['defaultSignals'] as $signal) {
                pcntl_signal($signal, SIG_DFL);
            }
        }
    }

    public function signalListener($signal)
    {
        $message = $this->fifo->receiveOne($success);

        if ($success) {
            $action = $this->definedActions[$message->action];
            if (method_exists($action[0], $action[1])) {
                echo "calling " . $action[0] . " " . $action[1] . "\n";
                call_user_func($action, $message->data);
            }
        }
    }

    public function start()
    {
//        $this->registerShutDown();
        $this->unbindMasterSignals();
        $this->bindWorkerListener();
        $this->task->run($this);
        exit;
    }

    public function registerShutDown()
    {
        register_shutdown_function(array($this, "closeChild"));
    }

    public function houseKeep() {
        pcntl_signal_dispatch();
        $this->tick = time();
    }

    public function kill()
    {
        if ($this->isMaster) {
            $this->killMaster();
        } else {
            $this->killChild();
        }
    }

    public function killChild()
    {
        if (!$this->isMaster) {
            exit;
        }
    }

    public function killMaster()
    {
        $message = new WorkerMessage();
        $message->action = WorkerMessage::KILL;
        $this->fifo->send($message);
    }

    public function softKill()
    {
        if ($this->isMaster) {
            $this->softKillMaster();
        } else {
            $this->softKillChild();
        }
    }

    public function softKillChild()
    {
        $this->task->softKill();
    }

    public function softKillMaster()
    {
        echo "Sending kill to child \n";
        $message = new WorkerMessage();
        $message->action = WorkerMessage::SOFT_KILL;
        $this->fifo->send($message);
    }

    public function close(){
        if ($this->isMaster) {
            $this->closeMaster();
        } else {
            $this->closeChild();
        }
    }

    public function closing()
    {
        if (!$this->isMaster){
            $message = new WorkerMessage();
            $message->action = WorkerMessage::CLOSE;
            $message->data = $this->nr;
            $this->fifo->send($message);
        }
    }

    public function closeChild() {
        $this->task->close();
    }

    public function closeMaster()
    {
        $message = new WorkerMessage();
        $message->action = WorkerMessage::CLOSE;
        $this->fifo->send($message);
    }
}
 