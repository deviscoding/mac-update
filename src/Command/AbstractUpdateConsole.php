<?php

namespace DevCoding\Mac\Update\Command;

use DevCoding\Command\Base\AbstractConsole;
use DevCoding\Mac\Update\Objects\MacUpdate;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

/**
 * Class AbstractUpdateConsole.
 *
 * @author  Aaron M Jones <aaron@jonesiscoding.com>
 *
 * @package DevCoding\Mac\Update\Command
 */
class AbstractUpdateConsole extends AbstractConsole
{
  const PATTERN_DETAILS = '#^(.*), ([0-9]+)K\s?(.*)$#';

  /** @var string The full path to the softwareupdate binary */
  protected $_SoftwareUpdateBinary;
  /** @var MacUpdate[] An array of MacUpdate objects */
  protected $_Updates = [];
  /** @var array An array with major, minor, and revision keys, describing the MacOS version */
  protected $_DarwinVersion = [];
  /** @var int The number of physical CPU cores on this Mac hardware */
  protected $_Cores;

  // region //////////////////////////////////////////////// Symfony Command Methods

  protected function configure()
  {
    $this
        ->addOption('recommended', null, InputOption::VALUE_NONE, 'Only include recommended updates')
        ->addOption('restart', null, InputOption::VALUE_NONE, 'Only include restart updates')
        ->addOption('shutdown', null, InputOption::VALUE_NONE, 'Only include shutdown updates')
        ->addOption('no-scan', null, InputOption::VALUE_NONE, 'Do not scan for new updates')
    ;
  }

  // endregion ///////////////////////////////////////////// End Symfony Copmmand Methods

  // region //////////////////////////////////////////////// Software Update Methods

  /**
   * @param string $label
   * @param string $details
   *
   * @return MacUpdate|null
   */
  protected function fromCatalina($label, $details)
  {
    if (preg_match('#^Label:\s(.*)$#', trim($label), $lParts))
    {
      $Update = new MacUpdate($lParts[1]);

      if (preg_match_all('#([A-Z][a-z]+):\s([^,]+),#', $details, $parts, PREG_SET_ORDER))
      {
        foreach ($parts as $part)
        {
          $key = $part[1];
          $val = $part[2];

          if ('Title' == $key)
          {
            $Update->setName($val);
          }
          if ('Size' == $key)
          {
            $Update->setSize($val);
          }
          elseif ('Recommended' == $key && 'YES' == $val)
          {
            $Update->setRecommended(true);
          }
          elseif ('Action' == $key && 'restart' == $val)
          {
            $Update->setRestart(true);
          }
          elseif ('Action' == $key && 'shut down' == $val)
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
   * @return MacUpdate|null
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
        if (false !== strpos($parts[3], 'recommended'))
        {
          $Update->setRecommended(true);
        }

        if (false !== strpos($parts[3], 'restart'))
        {
          $Update->setRestart(true);
        }

        if (false !== strpos($parts[3], 'halt') || false !== strpos($parts[3], 'shut down'))
        {
          $Update->setShutdown(true);
        }
      }
    }

    return $Update;
  }

  /**
   * @return string The path to the softwareupdate Binary
   *
   * @throws \Exception If the binary if not found
   */
  protected function getSoftwareUpdate()
  {
    if (empty($this->_SoftwareUpdateBinary))
    {
      $this->_SoftwareUpdateBinary = $this->getBinaryPath('softwareupdate');
    }

    return $this->_SoftwareUpdateBinary;
  }

  /**
   * @return MacUpdate[]
   *
   * @throws \Exception
   */
  protected function getUpdateList()
  {
    if (empty($this->_Updates))
    {
      if ($suBin = $this->getSoftwareUpdate())
      {
        // Create Process
        $noScan  = $this->isNoScan() ? '--no-scan' : null;
        $Process = Process::fromShellCommandline(trim(sprintf('%s -l %s', $suBin, $noScan)));
        // Eliminate Timeouts
        $Process->setIdleTimeout(null)->setTimeout(null);
        // Run the Process
        $Process->run();

        // Check for Success
        if (!$Process->isSuccessful())
        {
          throw new ProcessFailedException($Process);
        }
        else
        {
          $outLines = $Process->getOutput();
          if (empty($outLines))
          {
            $outLines = $Process->getErrorOutput();
          }
        }

        $output = explode("\n", $outLines);
        if (!empty($output))
        {
          $count = count($output);
          for ($x = 0; $x < $count; ++$x)
          {
            unset($Update);
            if (preg_match('#\*\s(.*)$#', trim($output[$x]), $matches))
            {
              if (!empty($matches[1]))
              {
                $xx = $x + 1;
                if (!empty($output[$xx]))
                {
                  if ($this->isCatalinaUp())
                  {
                    $Update = $this->fromCatalina($matches[1], $output[$xx]);
                  }
                  else
                  {
                    $Update = $this->fromMojave($matches[1], $output[$xx]);
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

  /**
   * Returns an array of MacUpdate objects that match the flags given to this command instance.
   *
   * @return MacUpdate[]|array
   *
   * @throws \Exception
   */
  protected function getValidUpdates()
  {
    $MacUpdates = $this->getUpdateList();
    $output     = [];
    foreach ($MacUpdates as $macUpdate)
    {
      if ($this->isIncluded($macUpdate))
      {
        $output[] = $macUpdate;
      }
    }

    return $output;
  }

  /**
   * Determines whether the given MacUpdate object should be included in this command instance.
   *
   * @return bool
   */
  protected function isIncluded(MacUpdate $MacUpdate)
  {
    $isRecommended = $this->io()->getOption('recommended') ? true : false;
    $isRestart     = $this->io()->getOption('restart') ? true : false;
    $isShutdown    = $this->io()->getOption('shutdown') ? true : false;

    if ($isRecommended && (false === $MacUpdate->isRecommended()))
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
   * If the 'no-scan' flag was used for this command instance.
   *
   * @return bool
   */
  protected function isNoScan()
  {
    if ($this->io()->getInput()->hasOption('no-scan'))
    {
      return $this->io()->getOption('no-scan') ? true : false;
    }

    return false;
  }

  // endregion ///////////////////////////////////////////// End Software Update Methods

  // region //////////////////////////////////////////////// System Information Functions

  /**
   * Returns the number of physical CPU cores.
   *
   * @return int
   */
  protected function getCpuCores()
  {
    if (empty($this->_Cores))
    {
      $this->_Cores = (int) shell_exec('sysctl -n hw.physicalcpu');
    }

    return $this->_Cores;
  }

  /**
   * Returns the username of the user currently logged into the macOS GUI.
   *
   * @return string|null
   */
  protected function getConsoleUser()
  {
    return $this->getShellExec("/usr/sbin/scutil <<< \"show State:/Users/ConsoleUser\" | /usr/bin/awk '/Name :/ && ! /loginwindow/ { print $3 }'");
  }

  /**
   * Returns the ID of the user currently logged inot the macOS GUI.
   *
   * @return string|null
   */
  protected function getConsoleUserId()
  {
    return $this->getUserId($this->getConsoleUser());
  }

  /**
   * Returns an array with keys for the major, minor, and revision of MacOS currently running.
   *
   * @return array|int[]
   */
  protected function getMacOsVersion()
  {
    if (empty($this->_DarwinVersion))
    {
      if ($v = $this->getShellExec('sw_vers -productVersion'))
      {
        $parts = explode('.', $v);

        $major = !empty($parts[0]) ? $parts[0] : 10;
        $minor = !empty($parts[1]) ? $parts[1] : 1;
        $rev   = !empty($parts[2]) ? $parts[2] : 0;

        $this->_DarwinVersion = ['major' => $major, 'minor' => $minor, 'revision' => $rev];
      }
    }

    return $this->_DarwinVersion;
  }

  /**
   * Returns the user id of the given username.
   *
   * @return string|null
   */
  protected function getUserId(string $user)
  {
    return $this->getShellExec(sprintf('/usr/bin/id -u %s', $user));
  }

  /**
   * Determines if the system is running off of battery power, or AC power.
   *
   * @return bool
   */
  protected function isBatteryPowered()
  {
    $battery = $this->getShellExec('/usr/bin/pmset -g ps');

    return false !== strpos($battery, 'Battery Power');
  }

  /**
   * Determines if the OS version is Catalina or greater.
   *
   * @return bool
   */
  protected function isCatalinaUp()
  {
    $version = $this->getMacOsVersion();

    return $version['minor'] >= 15;
  }

  /**
   * Determines if the display is prevented from sleeping by an assertation, usually an indicator that a presentation
   * or video conference is currently running.
   *
   * @return string|null
   */
  protected function isDisplaySleepPrevented()
  {
    $a = $this->getShellExec("/usr/bin/pmset -g assertions | /usr/bin/awk '/NoDisplaySleepAssertion | PreventUserIdleDisplaySleep/ && match($0,/\(.+\)/) && ! /coreaudiod/ {gsub(/^\ +/,\"\",$0); print};'");

    return !empty($a);
  }

  /**
   * Determines if the OS is currently encrypting a FileVault.
   *
   * @return bool
   */
  protected function isEncryptingFileVault()
  {
    $fv = $this->getShellExec('/usr/bin/fdesetup status');

    return false !== strpos($fv, 'Encryption in progress');
  }

  /**
   * Returns an opinionated determination of whether the CPU load is 'high' based on the current load and the number of
   * CPU cores. Loads greater than half the number of CPU cores are considered high.  This intentinoally conservative.
   *
   * @return bool
   */
  protected function isLoadHigh()
  {
    if (function_exists('sys_getloadavg'))
    {
      $cores = $this->getCpuCores() / 2;
      $load  = sys_getloadavg();

      return isset($load[0]) && (float) $load[0] > $cores;
    }

    return false;
  }

  /**
   * Determines if the Mac has a T2 security chip.
   *
   * @return bool
   */
  protected function isSecurityChip()
  {
    $bridge = $this->getShellExec("/usr/sbin/system_profiler SPiBridgeDataType | /usr/bin/awk -F: '/Model Name/ { gsub(/.*: /,\"\"); print $0}'");

    return !empty($bridge);
  }

  /**
   * Determines if the MacOS Secure Boot feature is set to "full".
   *
   * @return bool
   */
  protected function isSecureBoot()
  {
    $P = Process::fromShellCommandline("nvram 94b73556-2197-4702-82a8-3e1337dafbfb:AppleSecureBootPolicy | awk '{ print $2 }'");
    $P->run();

    if ($P->isSuccessful())
    {
      return false !== strpos($P->getOutput(), '%02');
    }

    return false;
  }
}
