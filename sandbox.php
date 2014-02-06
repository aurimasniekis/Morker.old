<?php
/*
 * This file is part of the Morker package.
 *
 * (c) Aurimas Niekis <aurimas.niekis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$workers = 0;

$task = function() {
    $count = 5;
    while ($count-- > 0) {
        echo "Running " . $count . "\n";
        sleep(1);
    }
    exit;
};

$exit = false;

while(!$exit) {

    if ($workers == 0) {
        $workers++;
        $pid = pcntl_fork();

        if (-1 === $pid) {
            echo "Fork failed\n";
            exit;
        } elseif (0 === $pid) {
            echo "Spawned child\n";
            $task();
        }

    }

    while(true) {
        $pid = pcntl_waitpid(-1, $status, WNOHANG | WUNTRACED);
        if ($pid === 0) {
            echo "No children exited\n";
            break;
        } elseif ($pid === -1) {
            echo "Failed something\n";
            echo "Status '".$status."'\n";
            if (pcntl_wifexited($status)) {
                echo "pcntl_wifexited\n";
            } elseif (pcntl_wifstopped($status)) {
                echo "pcntl_wifstopped\n";
            } elseif (pcntl_wifsignaled($status)) {
                echo "pcntl_wifsignaled\n";
            } elseif ( pcntl_wexitstatus($status)) {
                echo " pcntl_wexitstatus\n";
            } elseif ( pcntl_wtermsig($status)) {
                echo " pcntl_wtermsig\n";
            } elseif ( pcntl_wstopsig($status)) {
                echo " pcntl_wstopsig\n";
            }
            exit;
        } else {
            echo "Child #" . $pid . " exited\n";
            $exit = true;
        }
    }

    sleep(1);

}