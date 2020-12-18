<?php

namespace DevCoding\Mac\Update\Drivers;

use DevCoding\Mac\Utility\MacShellTrait;
use Symfony\Component\Process\Process;

class SoftwareUpdateDriver extends Process
{
  use MacShellTrait;

  /**
   * @param $flags
   *
   * @return SoftwareUpdateDriver
   */
  public static function fromFlags($flags)
  {
    $cmd = 'softwareupdate';
    foreach ($flags as $flag => $value)
    {
      if (false !== $value)
      {
        $cmd .= ' --'.$flag;

        if (!empty($value) && true !== $value)
        {
          $cmd .= ' '.$value;
        }
      }
    }

    return SoftwareUpdateDriver::fromShellCommandline($cmd);
  }

  public function isRestart()
  {
    $output = $this->getOutput(true);
    if (!empty($output))
    {
      foreach ($output as $line)
      {
        if (false !== stripos($line, 'restart'))
        {
          return true;
        }
      }
    }

    return false;
  }

  public function isShutdown()
  {
    $output = $this->getOutput(true);
    if (!empty($output))
    {
      foreach ($output as $line)
      {
        if (preg_match('#(shut down|shutdown|halt)#i', $line))
        {
          return true;
        }
      }
    }

    return false;
  }

  // region //////////////////////////////////////////////// Process Methods

  public function isSuccessful()
  {
    if (parent::isSuccessful())
    {
      $diskError = $this->getDiskSpaceError();

      return empty($diskError);
    }

    return false;
  }

  /**
   * @param bool $array
   *
   * @return string|array|null
   */
  public function getOutput($array = false)
  {
    $string = parent::getOutput();
    $output = [];
    if (!empty($string))
    {
      $lines = explode("\n", $string);
      foreach ($lines as $line)
      {
        if ('Software Update Tool' != $line && !empty($line))
        {
          $output[] = $line;
        }
      }

      return ($array) ? $output : implode("\n", $output);
    }

    return null;
  }

  public function getErrorOutput($array = false)
  {
    $string = parent::getErrorOutput();
    $errors = [];
    if (!empty($string))
    {
      // Check both StdOut & StdErr for output
      $lines = explode("\n", $string);
      foreach ($lines as $line)
      {
        if ('Software Update Tool' != $line && !empty($line))
        {
          $errors[] = $line;
        }
      }
    }

    if ($diskSpace = $this->getDiskSpaceError())
    {
      $errors[] = $diskSpace;
      $errors[] = sprintf('Only %sGB Free Space Available.', $this->getFreeDiskSpace());
    }

    return ($array) ? $errors : implode("\n", $errors);
  }

  // endregion ///////////////////////////////////////////// End Process Methods

  // region //////////////////////////////////////////////// Helper Methods

  protected function getDiskSpaceError()
  {
    if ($this->isTerminated())
    {
      $disk = 'Not enough free disk space';
      $out  = parent::getOutput();
      $err  = parent::getErrorOutput();
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
    }

    return null;
  }

  /**
   * Returns free disk space in Gibibyte (1024), as returned by the DF binary.
   *
   * @see    https://en.wikipedia.org/wiki/Gibibyte
   *
   * @return string|null
   */
  public function getFreeDiskSpace()
  {
    return $this->getShellExec("/bin/df -g / | /usr/bin/awk '(NR == 2){print $4}'");
  }

  protected function isImmutable()
  {
    if (!empty($this->_Process))
    {
      if (Process::STATUS_READY !== $this->_Process->getStatus())
      {
        return true;
      }
    }

    return false;
  }

  // endregion ///////////////////////////////////////////// End Helper Methods
}
