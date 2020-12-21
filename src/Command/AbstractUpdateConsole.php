<?php

namespace DevCoding\Mac\Update\Command;

use DevCoding\Mac\Command\AbstractMacConsole;
use DevCoding\Mac\Update\Drivers\SoftwareUpdateDriver;
use DevCoding\Mac\Update\Objects\MacUpdate;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class AbstractUpdateConsole.
 *
 * @author  Aaron M Jones <aaron@jonesiscoding.com>
 *
 * @package DevCoding\Mac\Update\Command
 */
class AbstractUpdateConsole extends AbstractMacConsole
{
  const PATTERN_DETAILS = '#^(.*), ([0-9]+)K\s?(.*)$#';

  /** @var string The full path to the softwareupdate binary */
  protected $_SoftwareUpdateBinary;
  /** @var MacUpdate[] An array of MacUpdate objects */
  protected $_Updates = [];

  // region //////////////////////////////////////////////// Symfony Command Methods

  protected function configure()
  {
    $this
        ->addOption('recommended', null, InputOption::VALUE_NONE, 'Only include recommended updates')
        ->addOption('restart', null, InputOption::VALUE_NONE, 'Only include restart updates')
        ->addOption('shutdown', null, InputOption::VALUE_NONE, 'Only include shutdown updates')
        ->addOption('no-scan', null, InputOption::VALUE_NONE, 'Do not scan for new updates')
        ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Software Update timeout in seconds.', $this->getDefaultTimeout())
    ;
  }

  protected function isAllowUserOption()
  {
    return false;
  }

  /**
   * Returns the default number of seconds that softwareupdate should be allowed to run before determining that
   * it has stalled.  This can be overridden with the --timeout option.
   *
   * @return int
   */
  protected function getDefaultTimeout()
  {
    return 7200; // 2 Hours
  }

  /**
   * Returns the default number of seconds that softwareupdate should be allowed to run before determining that
   * it has stalled.  Set with the --timeout option, which provides a default based on the getDefaultTimeout method.
   *
   * @throws \Exception if for some reason, the value of the timeout option is falsy
   */
  private function getTimeout()
  {
    if (!$timeout = $this->io()->getOption('timeout'))
    {
      throw new \Exception('A default timeout for softwareupdate was not provided.');
    }

    return (is_numeric($timeout) && $timeout > 0) ? $timeout : null;
  }

  // endregion ///////////////////////////////////////////// End Symfony Command Methods

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
            if ($this->isShutdownUpdate($Update))
            {
              $Update->setShutdown(true);
            }
            else
            {
              $Update->setRestart(true);
            }
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
          if ($this->isShutdownUpdate($Update))
          {
            $Update->setShutdown(true);
          }
          else
          {
            $Update->setRestart(true);
          }
        }
        elseif (false !== strpos($parts[3], 'halt') || false !== strpos($parts[3], 'shut down'))
        {
          $Update->setShutdown(true);
        }
      }
    }

    return $Update;
  }

  /**
   * @param MacUpdate $Update
   *
   * @return bool
   */
  protected function isShutdownUpdate($Update)
  {
    if ($this->getDevice()->isSecurityChip())
    {
      $search = str_replace('-'.(string) $this->getOs()->getVersion(), '', $Update->getId());
      $result = $this->getShellExec(sprintf('cat /var/log/install.log | grep "%s" | tail -1', $search));

      if (preg_match('#^\s*([^|]*)\s*|#', $result, $matches))
      {
        $log   = '/var/log/install.log';
        $index = trim($matches[1]);
        $date  = substr(date('Y-m-d H:i'), -1);
        $cmd   = sprintf('cat %s | grep "%s requires bridgeOS update" | grep "%s"', $log, $index, $date);
        $grep  = $this->getShellExec($cmd);

        if (!empty($grep))
        {
          return true;
        }
      }
    }

    return false;
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
        $timeout = $this->getTimeout();
        $noscan  = $this->isNoScan() ? ['no-scan' => true] : [];
        $flags   = array_merge(['list' => true, 'all' => true], $noscan);

        $Process = SoftwareUpdateDriver::fromFlags($flags);
        // Eliminate Timeouts
        $Process->setIdleTimeout($timeout)->setTimeout($timeout);
        // Run the Process
        $Process->run();

        // Check for Success
        if (!$Process->isSuccessful())
        {
          throw new ProcessFailedException($Process);
        }

        $output = $Process->getOutput(true);
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
   * @param MacUpdate $MacUpdate
   *
   * @return bool
   */
  protected function isIncluded($MacUpdate)
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

  /**
   * @param string $cmd    The software update command to run
   * @param array  $errors Set by reference; any errors generated
   *
   * @return bool TRUE if successful; FALSE if not
   */
  protected function runSoftwareUpdate($cmd, &$errors = [])
  {
    $t = $this->getTimeout();
    $P = SoftwareUpdateDriver::fromShellCommandline($cmd)->setTimeout($t)->setIdleTimeout($t);
    $P->run();

    if (!$P->isSuccessful())
    {
      $errors = $P->getErrorOutput(true);
      if (empty($errors))
      {
        $errors = $P->getOutput(true);
      }

      return false;
    }

    return true;
  }

  protected function getDiskSpaceError($Process)
  {
    $disk = 'Not enough free disk space';
    $out  = $Process->getOutput();
    $err  = $Process->getErrorOutput();
    if (false !== strpos($out, $disk) || false !== strpos($err, $disk))
    {
      $lines = explode("\n", $out."\n".$err);
      foreach ($lines as $line)
      {
        if (false !== strpos($line, $disk))
        {
          return $line;
        }
      }
    }

    return null;
  }

  // endregion ///////////////////////////////////////////// End Software Update Methods

  // region //////////////////////////////////////////////// System Information Functions

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
   * @return string|null
   */
  protected function getFreeDiskSpace()
  {
    return $this->getDevice()->getFreeDiskSpace();
  }

  /**
   * Returns an opinionated determination of whether the CPU load is 'high' based on the current load and the number of
   * CPU cores. Loads greater than the number of CPU cores are considered high.
   *
   * @return bool
   */
  protected function isLoadHigh()
  {
    if (function_exists('sys_getloadavg'))
    {
      $cores = $this->getDevice()->getCpuCores();
      $load  = sys_getloadavg();

      return isset($load[0]) && (float) $load[0] > $cores;
    }

    return false;
  }

  // endregion ///////////////////////////////////////////// End System Information Functions
}
