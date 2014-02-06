<?php
/*
 * This file is part of the Morker package.
 *
 * (c) Aurimas Niekis <aurimas.niekis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Morker\EventDispatcher;

/**
 * Morker General Events
 *
 * @package Morker\EventDispatcher
 */
class Events
{
    const START = 'morker.start';
    const PREPARE = 'morker.prepare';
    const SHUTDOWN = 'morker.shutdown';
    const PRE_FORK = 'morker.pre_fork';
    const POST_FORK = 'morker.post_fork';
    const EVENT_KILL = 'morker.event_kill';
    const MASTER_LOOP = 'morker.master_loop';
    const SOFT_SHUTDOWN = 'morker.soft_shutdown';
    const EVENT_INCREASE = 'morker.event_increase';
    const EVENT_DECREASE = 'morker.event_decrease';
    const TIMEOUT_WORKERS = 'morker.timeout_workers';
    const EVENT_SOFT_KILL = 'morker.event_soft_kill';
    const SPAWNING_WORKERS = 'morker.spawning_workers';
    const EVENT_KILL_WORKERS = 'morker.event_kill_workers';
    const CHECKING_DEAD_WORKERS = 'morker.checking_dead_workers';
    const REGISTER_EVENT_HANDLER = 'morker.register_event_handler';
    const REGISTER_SIGNAL_HANDLER = 'morker.register_signal_handler';
}
 