<?php


namespace DevCoding\Mac\Update\Command;


use DevCoding\Mac\Update\Objects\MacUpdate;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class InstallCommand extends AbstractUpdateConsole
{
  protected function configure()
  {
    $this->setName('install');
    $this->addOption('force', null, InputOption::VALUE_NONE, "Force updates to install regardless of status of battery or user." );
    parent::configure();
  }

  protected function isForced()
  {
    return $this->io()->getOption('force') ? true : false;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $errors     = [];
    $cUser      = $this->getConsoleUser();
    $Updates    = $this->getValidUpdates();
    $isRestart  = $this->isRestartRequired($Updates);
    $isShutdown = $this->isShutdownRequired($Updates);
    $this->io()->blankln();

    // Battery Power
    $this->io()->msg('Checking for AC Power', 40);
    if ($this->isBatteryPowered() && !$this->isForced())
    {
      $this->io()->errorln('[ERROR]');
      $errors[] = "The system is running on battery power.";
    }

    // User Login Status if Shutdown or Restart is Required
    if ($isShutdown || $isRestart)
    {
      $this->io()->msg('Checking User Login', 40);
      if (!empty($cUser) && !$this->isForced())
      {
        $this->io()->errorln('[ERROR]');
        $errors[] = "A user is logged in, and a shutdown or restart is required.";
      }
    }

    // File Vault Status
    $this->io()->msg('Checking File Vault', 40);
    if ($this->isEncryptingFileVault())
    {
      $this->io()->errorln('[ERROR]');
      $errors[] = 'Updates cannot be installed while File Vault is encrypting.';
    }

    // EXIT IF ERRORS
    if (!empty($errors))
    {
      foreach($errors as $error)
      {
        $this->io()->errorln($error);
      }

      $this->io()->blankln();

      return self::EXIT_ERROR;
    }

    // Install Updates
    if ($isRestart || $isShutdown)
    {
      // Install all the updates at once
      $noscan   = $this->isNoScan() ? ' --no-scan' : null;
      $switches = $this->isRecommended() ? ['a','r','R'] : ['a','R'];

      $this->io()->msgln('Downloading and installing updates.  The system will restart when appropriate.');
      $cmd = sprintf('%s --install -%s%s', $this->getSoftwareUpdate(), implode(' ', $switches), $noscan);

      if (!$this->runSoftwareUpdate($cmd, $errors))
      {
        $this->io()->blankln();
        foreach($errors as $error)
        {
          $this->io()->errorln($error);
        }
        $this->io()->blankln();

        return self::EXIT_ERROR;
      }
      else
      {
        // If we got here, the system probably didn't shut down or restart as needed
        $tpl = 'The system installed an update that requires a {t}, but did not {t}.  Please {t}';
        $msg = str_replace('{t}', $isShutdown ? "SHUTDOWN" : "RESTART", $tpl);
        $this->io()->errorln($msg);
        $this->io()->blankln();

        return self::EXIT_ERROR;
      }
    }
    else
    {
      // Install updates one at a time
      foreach($Updates as $macUpdate)
      {
        $this->io()->info('Installing ' . $macUpdate->getName(), 50);
        $cmd = sprintf('%s --no-scan --install "%s"', $this->getSoftwareUpdate(), $macUpdate->getId());

        if (!$this->runSoftwareUpdate($cmd, $err))
        {
          $errors[] = array_merge($errors, $err);
          $this->io()->errorln('[ERROR]');
          foreach($err as $e)
          {
            $this->io()->writeln('  ' . $e);
          }
        }
        else
        {
          $this->io()->successln('[SUCCESS]');
        }
      }
    }

    return empty($errors) ? self::EXIT_SUCCESS : self::EXIT_ERROR;
  }

  /**
   * @param string $cmd      The software update command to run
   * @param array  $errors   Set by reference; any errors generated
   *
   * @return bool            TRUE if successful; FALSE if not
   */
  protected function runSoftwareUpdate($cmd, &$errors = [])
  {
    $P = Process::fromShellCommandline($cmd);
    $P->run();

    if (!$P->isSuccessful())
    {
      $output = !empty($P->getErrorOutput()) ? $P->getErrorOutput() : $P->getOutput();
      $oLines = explode("\n", $output);
      foreach($oLines as $line)
      {
        if ($line != 'Software Update Tool' && !empty($line))
        {
          $errors[] = $line;
        }
      }

      return false;
    }

    return true;
  }

  /**
   * @return bool
   */
  protected function isRecommended()
  {
    return $this->io()->getOption('recommended') ? true : false;
  }

  /**
   * @param MacUpdate[] $Updates
   *
   * @return bool
   */
  protected function isRestartRequired($Updates)
  {
    foreach($Updates as $Update)
    {
      if ($Update->isRestart())
      {
        return true;
      }
    }

    return false;
  }

  /**
   * @param MacUpdate[] $Updates
   *
   * @return bool
   */
  protected function isShutdownRequired($Updates)
  {
    foreach($Updates as $Update)
    {
      if ($Update->isShutdown())
      {
        return true;
      }
    }

    return false;
  }
}