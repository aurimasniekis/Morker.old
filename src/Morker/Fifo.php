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

class Fifo
{
    public  $pid;
    public $ppid;
    public $read;
    public $write;
    public $signal;

    /**
     * @var Master
     */
    public $master;

    /**
     * Constructor.
     *
     * @param Master  $master The master object
     * @param integer $pid    The child process id or null if this is the child
     *
     * @throws \Exception
     * @throws \RuntimeException
     */
    public function __construct(Master $master, $pid = null)
    {
        $this->master = $master;

        $directions = array('up', 'down');

        if (null === $pid) {
            // child
            $pid   = posix_getpid();
            $pPid  = posix_getppid();
            $modes = array('write', 'read');
        } else {
            // parent
            $pPid  = null;
            $modes = array('read', 'write');
        }

        $this->pid  = $pid;
        $this->ppid = $pPid;

        foreach (array_combine($directions, $modes) as $direction => $mode) {
            $fifo = $this->getPath($direction);

            if (!file_exists($fifo) && !posix_mkfifo($fifo, 0600) && 17 !== $error = posix_get_last_error()) {
                throw new \Exception(sprintf('Error while creating FIFO: %s (%d)', posix_strerror($error), $error));
            }

            $this->$mode = fopen($fifo, $mode[0]);
            if (false === $this->$mode = fopen($fifo, $mode[0])) {
                throw new \RuntimeException(sprintf('Unable to open %s FIFO.', $mode));
            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Reads one message from the FIFO.
     *
     * @param $success
     *
     * @return bool|mixed The message, or null
     */
    public function receiveOne(& $success)
    {
        $success = true;

        $serialized = '';
        while (false !== $data = fgets($this->read)) {
            $serialized .= $data;

            if ('b:0;' === $serialized) {
                return false;
            }

            if (false !== $message = @unserialize($serialized)) {
                return $message;
            }
        }

        $success = false;
    }

    /**
     * Reads all messages from the FIFO.
     *
     * @return array An array of messages
     */
    public function receiveMany()
    {
        $messages = array();

        do {
            $messages[] = $this->receiveOne($success);
        } while($success);

        array_pop($messages);

        return $messages;
    }

    /**
     * Writes a message to the FIFO.
     *
     * @param mixed   $message The message to send
     * @param integer $signal  The signal to send afterward
     * @param integer $pause   The number of microseconds to pause after signalling
     *
     * @throws \RuntimeException
     */
    public function send($message, $signal = null, $pause = 500)
    {
        if (false === fwrite($this->write, serialize($message)."\n")) {
            throw new \RuntimeException('Unable to write to FIFO');
        }

        if (false === $signal) {
            return;
        }

        $this->signal($signal ?: $this->master->config['fifoSignal']);
        usleep($pause);
    }

    /**
     * Sends a signal to the other process.
     */
    public function signal($signal)
    {
        $pid = null === $this->ppid ? $this->pid : $this->ppid;

        return posix_kill($pid, $signal);
    }

    public function close()
    {
        if (is_resource($this->read)) {
            fclose($this->read);
        }

        if (is_resource($this->write)) {
            fclose($this->write);
        }
    }

    public function cleanup()
    {
        foreach (array('up', 'down') as $direction) {
            if (file_exists($path = $this->getPath($direction))) {
                unlink($path);
            }
        }
    }

    public function getPath($direction)
    {
        return realpath(sys_get_temp_dir()).'/'.$this->master->config['name'].$this->pid.'.'.$direction;
    }
}
