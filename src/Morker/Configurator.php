<?php
/**
 * Created by PhpStorm.
 * User: gcds
 * Date: 04/02/14
 * Time: 20:01
 */

namespace Morker;

/**
 * Class Configurator
 *
 * @package Morker
 */
class Configurator implements \ArrayAccess
{
    /**
     * @var bool
     */
    public $debug = false;

    /**
     * Prefix Name
     *
     * @var string Name
     */
    public $name = "Morker";
    /**
     * @var string
     */
    public $processNameFormat = "%s-%s-%s";
    /**
     * Worker Processes
     *
     * @var int Processes
     */
    public $workerProcesses = 3;
    /**
     * Default Signals
     *
     * @var int[] Signals
     */
    public $defaultSignals = [
        SIGWINCH,
        SIGQUIT,
        SIGINT,
        SIGTERM,
        SIGUSR1,
        SIGUSR2,
        SIGHUP,
        SIGTTIN,
        SIGTTOU,
        SIGCHLD
    ];
    /**
     * Fifo Signal
     *
     * @var int Signal
     */
    public $fifoSignal = SIGCHLD;
    /**
     * Timeout
     *
     * @var int Timeout
     */
    public $timeout = 1;

    /**
     * @return array
     */
    public function getDefaultSignals()
    {
        return $this->defaultSignals;
    }

    /**
     * @param array $defaultSignals
     */
    public function setDefaultSignals($defaultSignals)
    {
        $this->defaultSignals = $defaultSignals;
    }

    /**
     * @return int
     */
    public function getFifoSignal()
    {
        return $this->fifoSignal;
    }

    /**
     * @param int $fifoSignal
     */
    public function setFifoSignal($fifoSignal)
    {
        $this->fifoSignal = $fifoSignal;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getProcessNameFormat()
    {
        return $this->processNameFormat;
    }

    /**
     * @param string $processNameFormat
     */
    public function setProcessNameFormat($processNameFormat)
    {
        $this->processNameFormat = $processNameFormat;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @return int
     */
    public function getWorkerProcesses()
    {
        return $this->workerProcesses;
    }

    /**
     * @param int $workerProcesses
     */
    public function setWorkerProcesses($workerProcesses)
    {
        $this->workerProcesses = $workerProcesses;
    }

    /**
     * Assigns a value to the specified offset
     *
     * @param string $offset The offset to assign the value to
     * @param mixed  $value  The value to set
     * @access public
     * @abstracting ArrayAccess
     * @return mixed
     */
    public function offsetSet($offset, $value)
    {
        if (!is_null($offset) && property_exists($this, $offset)) {
            $this->$offset = $value;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Whether or not an offset exists
     *
     * @param string $offset An offset to check for
     * @access public
     * @return boolean
     * @abstracting ArrayAccess
     */
    public function offsetExists($offset)
    {
        return property_exists($this, $offset);
    }

    /**
     * Unsets an offset
     *
     * @param string $offset The offset to unset
     * @access public
     * @abstracting ArrayAccess
     */
    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->$offset);
        }
    }

    /**
     * Returns the value at specified offset
     *
     * @param string $offset The offset to retrieve
     * @access public
     * @return mixed
     * @abstracting ArrayAccess
     */
    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->$offset : null;
    }

}
