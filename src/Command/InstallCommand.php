<?php


namespace DevCoding\Mac\Update\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends AbstractUpdateConsole
{
  protected function configure()
  {
    $this->setName('install');

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $isRecommended = $this->io()->getOption('recommended') ? true : false;
    $isRestart     = $this->io()->getOption('restart') ? true : false;
    $isShutdown    = $this->io()->getOption('shutdown') ? true : false;

    if ($isRestart || $isShutdown)
    {
      $switches = ['a','R'];

      if ($isRecommended)
      {
        $switches[] = 'r';
      }

      $this->io()->msgln('Downloading and installing updates.  The system will restart when appropriate.');
      exec(sprintf('%s --install -%s', $this->getSoftwareUpdate(), implode(' ',$switches)), $output, $retval);

      if ($retval != 0)
      {
        foreach($output as $line)
        {
          if ($line != 'Software Update Tool' && !empty($line))
          {
            $this->io()->errorln('  ' . $line);
          }
        }

        return self::EXIT_ERROR;
      }

      return self::EXIT_SUCCESS;
    }
    else
    {
      $errors = false;
      $this->io()->msg('Checking For Updates', 50);
      $Updates = $this->getValidUpdates();
      $this->io()->successln('[SUCCESS]');
      foreach($Updates as $macUpdate)
      {
        $this->io()->info('Installing ' . $macUpdate->getName(), 50);
        exec(sprintf('%s --no-scan --install "%s"', $this->getSoftwareUpdate(), $macUpdate->getId()), $output, $retval);

        if ($retval !== 0)
        {
          $errors = true;
          $this->io()->error('[ERROR]');
          foreach($output as $line)
          {
            if ($line != 'Software Update Tool' && !empty($line))
            {
              $this->io()->writeln('  ' . $line);
            }
          }
        }
        else
        {
          $this->io()->successln('[SUCCESS]');
        }
      }
    }

    return ($errors) ? self::EXIT_ERROR : self::EXIT_SUCCESS;
  }
}