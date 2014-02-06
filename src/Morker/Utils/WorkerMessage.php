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


class WorkerMessage implements \Serializable
{
    const SOFT_KILL = "soft_kill";

    const KILL = "kill";

    const RELOAD_LOG = "reload_log";

    const CLOSE = "close";

    public $action;

    public $data;


    public function serialize()
    {
        return serialize(array(
                $this->action,
                $this->data,
            ));
    }

    public function unserialize($str)
    {
        list(
            $this->action,
            $this->data
            ) = unserialize($str);
    }
}