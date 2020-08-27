<?php


namespace DevCoding\Mac\Update\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends AbstractUpdateConsole
{
  protected function configure()
  {
    $this->setName('list');

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io()->msg('Checking For Updates', 50);
    $Updates = $this->getValidUpdates();
    $this->io()->successln('[SUCCESS]');
    $this->io()->blankln();

    if (empty($Updates))
    {
      $this->io()->msgln('No Updates are Available.');
    }
    else
    {
      foreach($Updates as $Update)
      {
        if ($this->io()->getOutput()->isQuiet())
        {
          $this->io()->writeln($Update->getId(), null, false, OutputInterface::VERBOSITY_QUIET);
        }
        else
        {
          $this->io()->msg($Update->getName(), 60);
          $this->io()->write($Update->getSize() . 'K', null, 15);

          $last = [];
          if ($Update->isRecommended())
          {
            $last[] = '[recommended]';
          }

          if ($Update->isRestart())
          {
            $last[] = '[restart]';
          }

          $this->io()->writeln(implode(' ', $last),null);
        }
      }
    }

    $this->io()->blankln();

    return self::EXIT_ERROR;
  }
}