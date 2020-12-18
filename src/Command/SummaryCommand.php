<?php

namespace DevCoding\Mac\Update\Command;

use DevCoding\Mac\Update\Objects\MacUpdate;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SummaryCommand extends AbstractUpdateConsole
{
  protected function configure()
  {
    $this->setName('summary');
    $this->addOption('json', null, InputOption::VALUE_NONE, 'Output results in JSON');
    $this->addOption('no-scan', null, InputOption::VALUE_NONE, 'Do not scan for new updates.');
    $this->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Software Update timeout in seconds.', $this->getDefaultTimeout());
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $isJson = $this->io()->getOption('json');
    if ($isJson)
    {
      $this->io()->getOutput()->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    }

    $this->io()->blankln();
    $this->io()->msg('Checking For Updates', 50);
    $Updates = $this->getUpdateList();
    $summary = $this->getSummary($Updates);
    $this->io()->successln('[SUCCESS]');

    if ($isJson)
    {
      $this->io()->writeln(json_encode($summary, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT), null, false, OutputInterface::VERBOSITY_QUIET);
    }
    else
    {
      $this->io()->blankln();
      $this->io()->info('Total Updates', 50)->msgln($summary['count']);
      $this->io()->info('Recommended Updates', 50)->msgln($summary['recommended']);
      $this->io()->info('Updates Requiring Restart', 50)->msgln($summary['restart']);
      $this->io()->info('Updates Requiring Shutdown', 50)->msgln($summary['shutdown']);
      $this->io()->blankln();
      $this->io()->info('Console Username', 50)->msgln($summary['console_user']);
      $this->io()->info('Free Disk Space', 50)->msgln($summary['disk_space'].'GiB');
      $this->io()->info('Has T2 Security Chip', 50)->msgln($summary['security_chip'] ? 'Yes' : 'No');
      $this->io()->blankln();

      // Battery Power
      $this->io()->info('On Battery Power?', 50);
      if ($summary['battery'])
      {
        $this->io()->errorln('Yes');
      }
      else
      {
        $this->io()->successln('No');
      }

      // Encryption in Progress
      $this->io()->info('Encryption in Progress?', 50);
      if ($summary['encrypting'])
      {
        $this->io()->errorln('Yes');
      }
      else
      {
        $this->io()->successln('No');
      }

      // Presentation in Progress
      $this->io()->info('Screen Sleep Prevented?', 50);
      if ($summary['prevent_sleep'])
      {
        $this->io()->errorln('Yes');
      }
      else
      {
        $this->io()->successln('No');
      }

      $this->io()->blankln();
    }

    return self::EXIT_ERROR;
  }

  /**
   * @param MacUpdate[] $Updates
   *
   * @return array
   */
  protected function getSummary($Updates)
  {
    $output = [
        'count'          => count($Updates),
        'recommended'    => 0,
        'restart'        => 0,
        'shutdown'       => 0,
        'battery'        => $this->isBatteryPowered(),
        'console_user'   => $this->getConsoleUser(),
        'console_userid' => $this->getConsoleUserId(),
        'disk_space'     => $this->getDevice()->getFreeDiskSpace(),
        'encrypting'     => $this->isEncryptingFileVault(),
        'prevent_sleep'  => $this->isDisplaySleepPrevented(),
        'security_chip'  => $this->isSecurityChip(),
    ];

    foreach ($Updates as $Update)
    {
      if ($Update->isRecommended() && !$Update->isRestart() && !$Update->isShutdown())
      {
        ++$output['recommended'];
      }

      if ($Update->isRestart())
      {
        ++$output['restart'];
      }

      if ($Update->isShutdown())
      {
        ++$output['shutdown'];
      }
    }

    return $output;
  }
}
