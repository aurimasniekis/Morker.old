<?php
/*
 * This file is part of the Morker package.
 *
 * (c) Aurimas Niekis <aurimas.niekis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Morker\Utils;

class Error implements \Serializable
{
    private $class;
    private $message;
    private $file;
    private $line;
    private $code;

    public static function fromException(\Exception $e)
    {
        /**
         * @var $flat Error
         */
        $flat = new static();
        $flat->setClass(get_class($e));
        $flat->setMessage($e->getMessage());
        $flat->setFile($e->getFile());
        $flat->setLine($e->getLine());
        $flat->setCode($e->getCode());

        return $flat;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setClass($class)
    {
        $this->class = $class;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function setFile($file)
    {
        $this->file = $file;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function setLine($line)
    {
        $this->line = $line;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setCode($code)
    {
        $this->code = $code;
    }

    public function serialize()
    {
        return serialize(array(
                $this->class,
                $this->message,
                $this->file,
                $this->line,
                $this->code,
            ));
    }

    public function unserialize($str)
    {
        list(
            $this->class,
            $this->message,
            $this->file,
            $this->line,
            $this->code
            ) = unserialize($str);
    }
}