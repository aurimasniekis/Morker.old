<?php
/*
 * This file is part of the Morker package.
 *
 * (c) Aurimas Niekis <aurimas.niekis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Morker\Task;

use Morker\Worker;

interface WorkerTaskInterface
{

    /**
     * @param Worker $worker
     *
     * @return mixed
     */
    public function run(Worker $worker);

    /**
     * Soft Killing
     *
     * @return mixed
     */
    public function softKill();

    /**
     * @return mixed
     */
    public function close();
}
 