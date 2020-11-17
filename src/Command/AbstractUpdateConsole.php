<?php


namespace DevCoding\Mac\Update\Command;


use DevCoding\Command\Base\AbstractConsole;
use DevCoding\Mac\Update\Objects\MacUpdate;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;


class AbstractUpdateConsole extends AbstractConsole
{
  const PATTERN_DETAILS = '#^(.*), ([0-9]+)K\s?(.*)$#';

  /** @var string */
  protected $_SoftwareUpdateBinary;
  /** @var array */
  protected $_Updates = [];
  /** @var array */
  protected $_DarwinVersion = [];

  protected function configure()
  {
    $this
        ->addOption('recommended', null, InputOption::VALUE_NONE,'Only include recommended updates')
        ->addOption('restart', null, InputOption::VALUE_NONE, 'Only include restart updates')
        ->addOption('shutdown', null, InputOption::VALUE_NONE, 'Only include shutdown updates')
        ->addOption('no-scan', null, InputOption::VALUE_NONE, 'Do not scan for new updates')
    ;
  }

  protected function doInstall()
  {

  }

  /**
   * @param MacUpdate $MacUpdate
   *
   * @return bool
   */
  protected function isIncluded(MacUpdate $MacUpdate)
  {
    $isRecommended = $this->io()->getOption('recommended') ? true : false;
    $isRestart     = $this->io()->getOption('restart') ? true : false;
    $isShutdown    = $this->io()->getOption('shutdown') ? true : false;

    if ($isRecommended && ($MacUpdate->isRecommended() === false))
    {
      // Only recommended updates requested, this update isn't recommended
      return false;
    }

    if ($MacUpdate->isShutdown() !== $isShutdown)
    {
      // The shutdown property of the update doesn't match the shutdown input option
      return false;
    }

    if ($MacUpdate->isRestart() !== $isRestart)
    {
      // The restart property of the update doesn't match the restart input option
      return false;
    }

    return true;
  }

  /**
   * @return MacUpdate[]|array
   * @throws \Exception
   */
  protected function getValidUpdates()
  {
    $MacUpdates = $this->getUpdateList();
    $output     = [];
    foreach($MacUpdates as $macUpdate)
    {
      if ($this->isIncluded($macUpdate))
      {
        $output[] = $macUpdate;
      }
    }

    return $output;
  }

  /**
   * @return MacUpdate[]
   * @throws \Exception
   */
  protected function getUpdateList()
  {
    if (empty($this->_Updates))
    {
      if ($suBin = $this->getSoftwareUpdate())
      {
        // Create Process
        $noScan = $this->isNoScan() ? "--no-scan" : null;
        $Process = Process::fromShellCommandline(trim(sprintf('%s -l %s', $suBin, $noScan)));
        // Eliminate Timeouts
        $Process->setIdleTimeout(null)->setTimeout(null);
        // Run the Process
        $Process->run();

        // Check for Success
        if (!$Process->isSuccessful()) {
          throw new ProcessFailedException($Process);
        }
        else
        {
          $outLines = $Process->getOutput();
          if (empty($output))
          {
            $outLines = $Process->getErrorOutput();
          }
        }

        $output = explode("\n", $outLines);
        if (!empty($output))
        {
          $count = count($output);
          for($x = 0; $x < $count; $x++)
          {
            unset($Update);
            if(preg_match('#\*\s(.*)$#', trim($output[$x]), $matches))
            {
              if (!empty($matches[1]))
              {
                $xx = $x+1;
                if(!empty($output[$xx]))
                {
                  if ($this->isCatalinaUp())
                  {
                    $Update = $this->fromCatalina($matches[1], $output[$xx]);
                  }
                  else
                  {
                    $Update = $this->fromMojave($matches[1],$output[$xx]);
                  }
                }

                if (isset($Update))
                {
                  $this->_Updates[] = $Update;
                }
              }
            }
          }
        }
      }
    }

    return $this->_Updates;
  }

  protected function fromCatalina($label, $details)
  {
    if (preg_match('#^Label:\s(.*)$#', trim($label), $lParts))
    {
      $Update = new MacUpdate($lParts[1]);

      if (preg_match_all('#([A-Z][a-z]+):\s([^,]+),#', $details, $parts, PREG_SET_ORDER))
      {
        foreach($parts as $part)
        {
          $key = $part[1];
          $val = $part[2];

          if ($key == 'Title')
          {
            $Update->setName($val);
          }
          if ($key == 'Size')
          {
            $Update->setSize($val);
          }
          elseif ($key == 'Recommended' && $val == 'YES')
          {
            $Update->setRecommended(true);
          }
          elseif ($key == 'Action' && $val == 'restart')
          {
            $Update->setRestart(true);
          }
          elseif ($key == 'Action' && $val == 'shut down')
          {
            $Update->setShutdown(true);
          }
        }
      }

      return $Update;
    }

    return null;
  }

  /**
   * @param string $label
   * @param string $details
   *
   * @return MacUpdate
   */
  protected function fromMojave($label, $details)
  {
    $Update = new MacUpdate(trim($label));
    if (preg_match(self::PATTERN_DETAILS, $details, $parts))
    {
      if (!empty($parts[1]))
      {
        $Update->setName(trim($parts[1]));
      }

      if (!empty($parts[2]))
      {
        $Update->setSize($parts[2]);
      }

      if (!empty($parts[3]))
      {
        if (strpos($parts[3], 'recommended') !== false)
        {
          $Update->setRecommended(true);
        }

        if (strpos($parts[3], 'restart') !== false)
        {
          $Update->setRestart(true);
        }

        if (strpos($parts[3], 'halt') !== false || strpos($parts[3], 'shut down') !== false)
        {
          $Update->setShutdown(true);
        }
      }
    }

    return $Update;
  }

  protected function isNoScan()
  {
    if ($this->io()->getInput()->hasOption('no-scan'))
    {
      return $this->io()->getOption('no-scan') ? true : false;
    }

    return false;
  }

  /**
   * @return string
   * @throws \Exception
   */
  protected function getSoftwareUpdate()
  {
    if (empty($this->_SoftwareUpdateBinary))
    {
      $this->_SoftwareUpdateBinary = $this->getBinaryPath('softwareupdate');
    }

    return $this->_SoftwareUpdateBinary;
  }

  // region //////////////////////////////////////////////// System Information Functions

  protected function getConsoleUser()
  {
    return $this->getShellExec("/usr/sbin/scutil <<< \"show State:/Users/ConsoleUser\" | /usr/bin/awk '/Name :/ && ! /loginwindow/ { print $3 }'");
  }

  protected function getConsoleUserId()
  {
    return $this->getUserId($this->getConsoleUser());
  }

  protected function getUserId($user)
  {
    return $this->getShellExec(sprintf("/usr/bin/id -u %s", $user));
  }

  protected function getMacOsVersion()
  {
    if (empty($this->_DarwinVersion))
    {
      if ($v = $this->getShellExec('sw_vers -productVersion'))
      {
        $parts = explode('.', $v);

        $major = !empty($parts[0]) ? $parts[0] : 10;
        $minor = !empty($parts[1]) ? $parts[1] : 1;
        $rev = !empty($parts[2]) ? $parts[2] : 0;

        $this->_DarwinVersion = ['major' => $major, 'minor' => $minor, 'revision' => $rev];
      }
    }

    return $this->_DarwinVersion;
  }

  protected function isBatteryPowered()
  {
    $battery = $this->getShellExec('/usr/bin/pmset -g ps');

    return (strpos($battery, 'Battery Power') !== false);
  }

  protected function isCatalinaUp()
  {
    $version = $this->getMacOsVersion();

    return $version['minor'] >= 15;
  }

  /**
   * @return string|null
   */
  protected function isDisplaySleepPrevented()
  {
    $a = $this->getShellExec("/usr/bin/pmset -g assertions | /usr/bin/awk '/NoDisplaySleepAssertion | PreventUserIdleDisplaySleep/ && match($0,/\(.+\)/) && ! /coreaudiod/ {gsub(/^\ +/,\"\",$0); print};'");

    return !empty($a);
  }

  protected function isEncryptingFileVault()
  {
    $fv = $this->getShellExec('/usr/bin/fdesetup status');

    return (strpos($fv, 'Encryption in progress') !== false);
  }

  protected function isSecurityChip()
  {
    $bridge = $this->getShellExec("/usr/sbin/system_profiler SPiBridgeDataType | /usr/bin/awk -F: '/Model Name/ { gsub(/.*: /,\"\"); print $0}'");

    return !empty($bridge);
  }

  protected function isSecureBoot()
  {
    $P = Process::fromShellCommandline("nvram 94b73556-2197-4702-82a8-3e1337dafbfb:AppleSecureBootPolicy | awk '{ print $2 }'");
    $P->run();

    if ($P->isSuccessful())
    {
      return (strpos($P->getOutput(), "%02") !== false);
    }

    return false;
  }
}