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

    foreach($Updates as $macUpdate)
    {
      $this->io()->info('Downloading ' . $macUpdate->getName(), 60);
      exec(sprintf('%s -d "%s"', $this->getSoftwareUpdate(), $macUpdate->getId()), $output, $retval);

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

    return ($errors) ? self::EXIT_ERROR : self::EXIT_SUCCESS;
  }
}