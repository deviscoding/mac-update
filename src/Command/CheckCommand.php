<?php

namespace DevCoding\Mac\Update\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckCommand extends AbstractUpdateConsole
{
  protected function configure()
  {
    $this->setName('check');

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io()->blankln();

    $this->io()->msg('Checking For Updates', 50);
    $Updates = $this->getValidUpdates();
    $this->io()->successln('[SUCCESS]');

    if (empty($Updates))
    {
      $this->io()->errorln('There are no matching updates to install.');

      return self::EXIT_ERROR;
    }
    else
    {
      $this->io()->successln(sprintf('There are %s matching updates to install.', count($Updates)));

      return self::EXIT_SUCCESS;
    }
  }
}
