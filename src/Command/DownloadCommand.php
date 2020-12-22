<?php

namespace DevCoding\Mac\Update\Command;

use DevCoding\Mac\Update\Drivers\SoftwareUpdateDriver;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadCommand extends AbstractUpdateConsole
{
  protected function configure()
  {
    $this->setName('download');

    parent::configure();
  }

  protected function getDefaultTimeout()
  {
    return 14400; // 4 Hours
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io()->blankln()->msg('Checking For Updates', 50);
    $Updates = $this->getValidUpdates();
    $this->io()->successln('[SUCCESS]');
    $errors = false;

    foreach ($Updates as $macUpdate)
    {
      $this->io()->info('Downloading '.$macUpdate->getName(), 50);

      $flags = ['no-scan' => true, 'download' => $macUpdate->getId()];

      $SU = $this->getSoftwareUpdateDriver($flags);
      $SU->run();

      if (!$SU->isSuccessful())
      {
        $this->io()->error('[ERROR]');

        foreach ($SU->getErrorOutput(true) as $line)
        {
          $this->io()->writeln('  '.$line);
        }
      }
      else
      {
        $this->io()->successln('[SUCCESS]');
      }
    }

    $this->io()->blankln();

    return ($errors) ? self::EXIT_ERROR : self::EXIT_SUCCESS;
  }
}
