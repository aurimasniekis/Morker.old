<?php
/*
 * This file is part of the Morker package.
 *
 * (c) Aurimas Niekis <aurimas.niekis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Morker;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Morker\EventDispatcher\Events;
use Morker\Utils\WorkerMessage;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Master
{
    /**
     * @var \Morker\Configurator
     */
    public $config;

    /**
     * @var EventDispatcher
     */
    public $dispatcher;

    /**
     * @var \Morker\Task\WorkerTaskInterface
     */
    public $workerTask;

    /**
     * @var int
     */
    public $workerProcesses;

    /**
     * @var Worker[]
     */
    public $workers = array();

    /**
     * @var \Monolog\Logger
     */
    public $logger;

    /**
     * @var bool Respawn
     */
    public $respawn = false;

    public $exit;

    public function __construct(Configurator $config = null, EventDispatcher $dispatcherInterface = null, Logger $logger = null)
    {
        $this->config = $config ?: new Configurator();
        $this->dispatcher = $dispatcherInterface ?: new EventDispatcher();
        $this->logger = $logger ?: new Logger($this->config['name']);
        if (!$logger) {
            if ($this->config['debug']) {
                $debug = Logger::DEBUG;
            } else {
                $debug = Logger::INFO;
            }
            $this->logger->pushHandler(new StreamHandler("/var/log/" . $this->config['name'] . ".log", $debug));
        }

        $this->workers = array();

        $this->workerProcesses = $this->config['workerProcesses'];

        $this->exit = false;
    }

    public function __destruct()
    {
        if (count($this->workers) > 0) {
            $this->killWorkers();
        }
    }

    public function spawnMissingWorkers()
    {
        for ($workerNr = 0; $workerNr < $this->workerProcesses; $workerNr++) {
            foreach ($this->workers as $worker) {
                if ($worker->nr == $workerNr) {
                    continue 2;
                }
            }

            $this->dispatcher->dispatch(Events::SPAWNING_WORKERS);

            echo "DEBUG: spawning new worker: " . $workerNr . "\n";

            $this->dispatcher->dispatch(Events::PRE_FORK);

            $worker = new Worker(false, $workerNr, null, $this, null);
            $worker->task = $this->workerTask;

            if (-1 === $pid = pcntl_fork()) {
                throw new \Exception('Unable to fork a new process', 0);
            }

            if (0 === $pid) {
                // setup the fifo (blocks until parent connects)
                $fifo = new Fifo($this, null);
                $worker->fifo = $fifo;
                $worker->pid  = posix_getpid();

                $this->dispatcher->dispatch(Events::POST_FORK);
                $status = 0;
                try {
                    $worker->start();

                } catch (\Exception $e) {
                    $status = 1;
                }

                exit($status);
            }

            $fifo = new Fifo($this, $pid);
            $worker->fifo     = $fifo;
            $worker->pid      = $pid;
            $worker->isMaster = true;

            $this->workers[$pid] = $worker;
        }
    }

    /**
     * Checks for dead workers using using WAITPID(2)
     */
    public function checkForDeadWorkers()
    {
        $this->dispatcher->dispatch(Events::CHECKING_DEAD_WORKERS);

        while (true) {
            $wPid = pcntl_waitpid(-1, $status, WNOHANG | WUNTRACED);

            if ($wPid > 0) {
                $worker = $this->workers[$wPid];
                unset($this->workers[$wPid]);

                $worker->status = $status;

                if ($worker->isSuccessful()) {
//                     $this->logger->info(
//                         "reaped status={status} pid={pid} worker={forkNr}",
//                         array("status" => $status, "pid" => $wPid, "forkNr" => $fork->nr)
//                     );
                    echo "INFO: reaped status=" . $status . " pid=" . $wPid . " worker=" . $worker->nr . "\n";
                } else {
//                    $this->logger->error(
//                        "reaped status={status} pid={pid} worker={forkNr}",
//                        array("status" => $status, "pid" => $wPid, "forkNr" => $fork->nr)
//                    );
                    echo "DEBUG: reaped status=" . $status . " pid=" . $wPid . " worker=" . $worker->nr . "\n";
                }
                $worker->close();
                unset($worker);
            } else {
//                echo "DEBUG: No Child exited\n";
                break;
            }
        }
    }

    public function timeOutWorkers() {
        $this->dispatcher->dispatch(Events::TIMEOUT_WORKERS);

        $nextSleep = $this->config['timeout'] - 1;
        $now = time();

        foreach ($this->workers as $wPid => $worker) {
            $tick = $worker->tick;
            if (0 === $tick) {
                return null;
            }
            $diff = $now - $tick;
            $tmp = $this->config['timeout'] - $diff;
            if ($tmp >= 0) {
                if ($nextSleep > $tmp) {
                    $nextSleep = $tmp;
                }
                continue;
            }

            $nextSleep = 0;
            echo sprintf(
                "ERRRO: worker=%s PID:%s timeout " .
                "(%fs > %fs), killing \n",
                $worker->nr,
                $worker->pid,
                $diff,
                $this->config['timeout']
            );
//            $this->logger->error(
//                "worker={workerName} PID:{pid} timeout ({diff} > {timeout}), killing",
//                array("workerName" => $worker->nr, "pid" => $worker->pid, "diff" => $diff, "timeout" => $this->config['timeout']));
            $worker->softKill();
        }

        return $nextSleep <= 0 ? 1 : $nextSleep;
    }

    public function maintainWorkerCount()
    {
        $off = count($this->workers) - $this->workerProcesses;
        if ($off == 0) {
            return;
        } elseif ($off < 0) {
            $this->spawnMissingWorkers();
        }

        foreach ($this->workers as $worker) {
            if ($worker->nr >= $this->workerProcesses) {
                $worker->softKill();
            }
        }

        $this->respawn = false;
    }

    public function registerEventHandlers()
    {
        $this->dispatcher->dispatch(Events::REGISTER_EVENT_HANDLER);

        $this->addSignalEventListener(SIGQUIT,  array($this, "eventSoftKillShutdown"));
        $this->addSignalEventListener(SIGTERM,  array($this, "eventKillShutdown"));
        $this->addSignalEventListener(SIGINT,   array($this, "eventKillShutdown"));
        $this->addSignalEventListener(SIGWINCH, array($this, "eventKillWorkers"));
        $this->addSignalEventListener(SIGTTIN,  array($this, "eventIncreaseWorkers"));
        $this->addSignalEventListener(SIGTTOU,  array($this, "eventDecreaseWorkers"));
    }

    public function registerSignalsHandlers()
    {
        $this->dispatcher->dispatch(Events::REGISTER_SIGNAL_HANDLER);

        foreach ($this->config['defaultSignals'] as $signal) {
            $this->addSignalListener($signal);
        }
    }

    public function prepare()
    {
        $this->dispatcher->dispatch(Events::PREPARE);

        $this->registerEventHandlers();
        $this->registerSignalsHandlers();
        $this->spawnMissingWorkers();
    }

    public function start()
    {
        $this->dispatcher->dispatch(Events::START);

        $this->prepare();
        $this->mainLoop();
    }

    public function mainLoop()
    {
        echo "INFO: Master starting PID=".posix_getpid()."\n";
        $lastCheck = time();

        while (!$this->exit) {
            pcntl_signal_dispatch();

            $this->checkForDeadWorkers();
            if (($lastCheck + $this->config['timeout']) >= ($lastCheck = time())) {
                $sleepTime = $this->timeOutWorkers();
            } else {
                $sleepTime = $this->config['timeout'] / 2 + 1;
                if ($this->config['debug']) {
                    echo sprintf("waiting %ds after suspend/hibernation", $sleepTime);
                }
            }

            if ($this->respawn) {
                $this->maintainWorkerCount();
            }

            $this->dispatcher->dispatch(Events::MASTER_LOOP);

            sleep($sleepTime);
        }
    }

    public function eventSoftKillShutdown()
    {
        $this->dispatcher->dispatch(Events::EVENT_SOFT_KILL);

        $this->softShutdown();
    }

    public function eventKillShutdown()
    {
        $this->dispatcher->dispatch(Events::EVENT_KILL);

        $this->shutdown();
    }

    public function eventKillWorkers()
    {
        $this->dispatcher->dispatch(Events::EVENT_KILL_WORKERS);

        $this->killWorkers();
    }

    public function eventIncreaseWorkers()
    {
        $this->dispatcher->dispatch(Events::EVENT_INCREASE);

        $this->workerProcesses++;
        $this->respawn = true;
        echo "Increase " . $this->workerProcesses . "\n";
    }

    public function eventDecreaseWorkers()
    {
        $this->dispatcher->dispatch(Events::EVENT_DECREASE);

        if ($this->workerProcesses > 0) {
            $this->workerProcesses--;
            $this->respawn = true;
            echo "Decrease " . $this->workerProcesses . "\n";
        }
    }
    public function eventMessageFromWorker() {
        echo "Master got message from workers\n";
        $messages = array();
        foreach ($this->workers as $worker) {
            $message = $worker->fifo->receiveOne($success);

            if ($success) {
                $messages[$worker->pid] = $message;
            }
        }

        var_dump($messages);

        if (count($messages) > 0) {
            foreach ($messages as $wPid => $message) {
                if ($message instanceof WorkerMessage) {
                    if ($message->action == WorkerMessage::CLOSE) {
                        $this->workers[$wPid]->fifo->send("ok");
                        unset($this->workers[$wPid]);
                    }
                }
            }
        }
    }

    public function dispatchSignal($signal)
    {
        $this->dispatcher->dispatch('morker.signal.'.$signal);
    }

    public function addSignalListener($signal)
    {
        if ($this->dispatcher->hasListeners('morker.signal.'.$signal)) {
            pcntl_signal($signal, array($this, 'dispatchSignal'));
        }
    }

    public function addSignalEventListener($signal, $callable, $priority = 0)
    {
        $this->dispatcher->addListener('morker.signal.'.$signal, $callable, $priority);
    }

    public function removeSignalListener($signal, $callable)
    {
        $this->dispatcher->removeListener('morker.signal.'.$signal, $callable);
        if (!$this->dispatcher->hasListeners('morker.signal.'.$signal)) {
            pcntl_signal($signal, SIG_DFL);
        }
    }

    public function softKillWorkers() {
        echo "Soft Killing all workers\n";
        foreach ($this->workers as $wPid => $worker) {
            $worker->softKill();
            unset($this->workers[$wPid]);
        }
    }

    public function killWorkers() {
        echo "Killing all workers\n";
        foreach ($this->workers as $worker) {
            $worker->softKill();
        }
    }

    public function softShutdown()
    {
        $this->softKillWorkers();
        echo "Shutdowning...\n";
        $this->exit = true;
    }

    public function shutdown() {
        $this->killWorkers();
        echo "Shutdowning...\n";
        $this->exit = true;
    }
}
 