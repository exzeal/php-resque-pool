<?php

namespace Resque\Pool;

/**
 * CLI runner for php-resque-pool
 *
 * @package   Resque-Pool
 * @auther    Erik Bernhardson <bernhardsonerik@gmail.com>
 * @copyright (c) 2012 Erik Bernhardson
 * @license   http://www.opensource.org/licenses/mit-license.php
 */
class Cli
{
    public function run($opts)
    {
        if ($opts['daemon']) {
            $this->daemonize();
        }
        $this->managePidfile($opts['pidfile']);
        $config = $this->buildConfiguration($opts);
        $this->startPool($config);
    }

    public function daemonize()
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException("Failed pcntl_fork");
        }
        if ($pid) {
            // parent
            echo "Started background process: $pid\n\n";
            exit(0);
        }
    }

    public function managePidfile($pidfile)
    {
        if (!$pidfile)
        {
            return;
        }

        if (file_exists($pidfile))
        {
            if ($this->processStillRunning($pidfile))
            {
                throw new \Exception("Pidfile already exists at '$pidfile' and process is still running.");
            }
            else
            {
                unlink($pidfile);
            }
        }
        elseif (!is_dir($piddir = basename($pidfile)))
        {
            mkdir($piddir, 0777, true);
        }

        file_put_contents($pidfile, getmypid(), LOCK_EX);
        register_shutdown_function(function() use ($pidfile) {
            if (getmypid() === file_get_contents($pidfile)) {
                unlink($pidfile);
            }
        });
    }

    public function processStillRunning($pidfile)
    {
        $oldPid = trim(file_get_contents($pidfile));

        return posix_kill($oldPid, 0);
    }

    public function buildConfiguration(array $options)
    {
        $config = new Configuration;
        if ($options['appname']) {
            $config->appName = $options['appname'];
        }
        if ($options['environment']) {
            $config->environment = $options['environment'];
        }
        if ($options['config']) {
            $config->queueConfigFile = $options['config'];
        }
        if ($options['daemon']) {
            $config->handleWinch = true;
        }
        if ($options['term-graceful-wait']) {
            $config->termBehavior = 'graceful_worker_shutdown_and_wait';
        } elseif ($options['term-graceful']) {
            $config->termBehavior = 'graceful_worker_shutdown';
        }

        return $config;
    }

    public function startPool(Configuration $config)
    {
        $pool = new Pool($config);
        $pool->start();
        $pool->join();
    }
}
