<?php

namespace DevCoding\Mac\Update\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadCommand extends AbstractUpdateConsole
{
  protected function configure()
  {
    $this->setName('download');

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io()->msg('Checking For Updates', 50);
    $Updates = $this->getValidUpdates();
    $this->io()->successln('[SUCCESS]');
    $errors = false;

    foreach ($Updates as $macUpdate)
    {
      $this->io()->info('Downloading '.$macUpdate->getName(), 50);

      $info = [];
      $cmd = sprintf('%s --no-scan --download "%s"', $this->getSoftwareUpdate(), $macUpdate->getId());
      if (!$this->runSoftwareUpdate($cmd, $info))
      {
        $this->io()->error('[ERROR]');

        foreach($info as $line)
          {
            $this->io()->writeln('  '.$line);
          }
        }
      else
      {
        $this->io()->successln('[SUCCESS]');
      }
    }

    return ($errors) ? self::EXIT_ERROR : self::EXIT_SUCCESS;
  }
}
