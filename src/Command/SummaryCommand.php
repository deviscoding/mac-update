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
    $this->io()->successln('[SUCCESS]');

    $this->io()->msg('Getting Summary', 50);
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
      $this->io()->info('Content Cache', 50)->msgln(!empty($summary['content_cache']) ? implode(',', $summary['content_cache']) : 'None');
      $this->io()->info('SUS Url', 50)->msgln($summary['sus_url'] ? $summary['sus_url'] : 'None');
      $this->io()->info('Free Disk Space', 50)->msgln($summary['disk_space'].'GiB');
      $this->io()->blankln();

      // Battery Minutes
      $this->io()->info('Battery Remaining', 50);

      if ($summary['battery_minutes'] && $summary['battery_minutes'] > 60)
      {
        $this->io()->successln($summary['battery_minutes'].'mins');
      }
      elseif ($summary['battery_minutes'])
      {
        $suffix = ($summary['battery_minutes'] > 1) ? ' mins' : ' min';
        $this->io()->errorln($summary['battery_minutes'].$suffix);
      }
      else
      {
        $this->io()->msgln('N/A');
      }

      // Battery Percentage
      $this->io()->info('Battery Percentage', 50);
      if ($summary['battery_percent'] && $summary['battery_percent'] < 33)
      {
        $this->io()->errorln($summary['battery_percent'].'%');
      }
      elseif ($summary['battery_percent'])
      {
        $this->io()->successln($summary['battery_percent'].'%');
      }
      else
      {
        $this->io()->msgln('N/A');
      }

      // On Battery Power?
      $this->io()->info('On Battery Power?', 50);
      if ($summary['battery'])
      {
        $this->io()->errorln('Yes');
      }
      else
      {
        $this->io()->successln('No');
      }
      $this->io()->blankln();

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

      // SUS Available?
      $this->io()->info('SUS Offline?', 50);
      if ($summary['sus_offline'])
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
    $susUrl = $this->getDevice()->getOs()->getSoftwareUpdateCatalogUrl();
    $onBatt = $this->isBatteryPowered();
    $isBatt = $this->getDevice()->getBattery()->isInstalled();

    $output = [
        'count'           => count($Updates),
        'recommended'     => 0,
        'restart'         => 0,
        'shutdown'        => 0,
        'battery'         => $onBatt,
        'battery_percent' => $isBatt ? $this->getDevice()->getBattery()->getPercentage() : null,
        'battery_minutes' => $onBatt ? $this->getDevice()->getBattery()->getUntilEmpty('%i') : null,
        'console_user'    => $this->getConsoleUser(),
        'console_userid'  => $this->getConsoleUserId(),
        'disk_space'      => $this->getDevice()->getFreeDiskSpace(),
        'encrypting'      => $this->isEncryptingFileVault(),
        'prevent_sleep'   => $this->isDisplaySleepPrevented(),
        'sus_offline'     => !$this->isSusAvailable($susUrl),
        'sus_url'         => $this->getDevice()->getOs()->getSoftwareUpdateCatalogUrl(),
        'content_cache'   => $this->getDevice()->getOs()->getSharedCaches(),
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
