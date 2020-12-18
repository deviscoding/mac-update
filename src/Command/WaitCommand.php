<?php

namespace DevCoding\Mac\Update\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WaitCommand extends AbstractUpdateConsole
{
  protected function configure()
  {
    $this->setName('wait');
    $this->addArgument('seconds');
    $this->addOption('json', null, InputOption::VALUE_NONE, 'Output results in JSON');
    $this->addOption('user', null, InputOption::VALUE_NONE, 'Wait for the User to Log Out');
    $this->addOption('cpu', null, InputOption::VALUE_NONE, 'Wait for CPU Load');
    $this->addOption('power', null, InputOption::VALUE_NONE, 'Wait for AC Power');
    $this->addOption('filevault', null, InputOption::VALUE_NONE, 'Wait for FileVault Encryption');
    $this->addOption('screen', null, InputOption::VALUE_NONE, 'Wait for Screen Sleep Enabled');
    $this->addOption('all', null, InputOption::VALUE_NONE, 'Wait for All Items');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $isAll      = $this->io()->getOption('all');
    $isCpu      = $isAll || $this->io()->getOption('cpu');
    $isUser     = $isAll || $this->io()->getOption('user');
    $isPower    = $isAll || $this->io()->getOption('power');
    $isScreen   = $isAll || $this->io()->getOption('screen');
    $isVault    = $isAll || $this->io()->getOption('filevault');
    $seconds    = $this->io()->getArgument('seconds');
    $isJson     = $this->io()->getOption('json');
    $waitUser   = false;
    $waitPower  = false;
    $waitScreen = false;
    $waitVault  = false;
    $waitCpu    = false;

    // Set Verbosity for JSON Output
    if ($isJson)
    {
      $this->io()->getOutput()->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    }

    // Wait for Time
    $this->io()->blankln(1, OutputInterface::VERBOSITY_VERBOSE);
    $this->io()->info('Counting Down... ', 40, OutputInterface::VERBOSITY_VERBOSE);
    while ($seconds > 0)
    {
      $this->io()->write($seconds.'..', null, null, OutputInterface::VERBOSITY_VERBOSE);
      sleep(1);

      $waitCpu    = $isCpu    && $this->isLoadHigh();
      $waitVault  = $isVault  && $this->isEncryptingFileVault();
      $waitPower  = $isPower  && $this->isBatteryPowered();
      $waitScreen = $isScreen && $this->isDisplaySleepPrevented();
      $waitUser   = $isUser   && !empty($this->getConsoleUser());

      if ($waitCpu || $waitVault || $waitPower || $waitScreen || $waitUser)
      {
        --$seconds;
      }
      else
      {
        $seconds = 0;
      }
    }

    if ($this->io()->getOption('json'))
    {
      $summary = ['cpu' => $waitCpu, 'filevault' => $waitVault, 'power' => $waitPower, 'screen' => $waitScreen, 'user' => $waitUser];
      $this->io()->writeln(json_encode($summary, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT), null, false, OutputInterface::VERBOSITY_QUIET);
    }
    else
    {
      $this->io()->blankln();

      $this->io()->info('Waiting on CPU', 40);
      if ($waitCpu)
      {
        $this->io()->errorln('[YES]');
      }
      else
      {
        $this->io()->successln('[NO]');
      }

      $this->io()->info('Waiting on FileVault', 40);
      if ($waitVault)
      {
        $this->io()->errorln('[YES]');
      }
      else
      {
        $this->io()->successln('[NO]');
      }

      $this->io()->info('Waiting on AC Power', 40);
      if ($waitPower)
      {
        $this->io()->errorln('[YES]');
      }
      else
      {
        $this->io()->successln('[NO]');
      }

      $this->io()->info('Waiting on Screen Sleep', 40);
      if ($waitScreen)
      {
        $this->io()->errorln('[YES]');
      }
      else
      {
        $this->io()->successln('[NO]');
      }

      $this->io()->info('Waiting on User Logout', 40);
      if ($waitUser)
      {
        $this->io()->errorln('[YES]');
      }
      else
      {
        $this->io()->successln('[NO]');
      }

      $this->io()->blankln();
    }

    return $waitCpu || $waitVault || $waitPower || $waitScreen || $waitUser ? self::EXIT_ERROR : self::EXIT_SUCCESS;
  }
}
