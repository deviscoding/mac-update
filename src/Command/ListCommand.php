<?php

namespace DevCoding\Mac\Update\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends AbstractUpdateConsole
{
  protected function configure()
  {
    $this->setName('list');
    $this->addOption('json', null, InputOption::VALUE_NONE, 'Output results in JSON');

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $isJson = $this->io()->getOption('json');
    if ($isJson)
    {
      $this->io()->getOutput()->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    }

    $this->io()->msg('Checking For Updates', 50);
    $Updates = $this->getUpdateList();
    $this->io()->successln('[SUCCESS]');
    $this->io()->blankln();

    if ($isJson)
    {
      $this->io()->writeln(json_encode($Updates, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT), null, false, OutputInterface::VERBOSITY_QUIET);
    }
    elseif (empty($Updates))
    {
      $this->io()->msgln('No Updates are Available.');
    }
    else
    {
      $this->doListText($Updates);
    }

    $this->io()->blankln();

    return self::EXIT_ERROR;
  }

  protected function doListText($Updates)
  {
    if (empty($Updates))
    {
      $this->io()->msgln('No Updates are Available.');
    }
    else
    {
      foreach ($Updates as $Update)
      {
        if ($this->io()->getOutput()->isQuiet())
        {
          $this->io()->writeln($Update->getId(), null, false, OutputInterface::VERBOSITY_QUIET);
        }
        else
        {
          $this->io()->msg($Update->getName(), 60);
          $this->io()->write($Update->getSize().'K', null, 15);

          $last = [];
          if ($Update->isRecommended())
          {
            $last[] = '[recommended]';
          }

          if ($Update->isRestart())
          {
            $last[] = '[restart]';
          }

          if ($Update->isShutdown())
          {
            $last[] = '[shutdown]';
          }

          $this->io()->writeln(implode(' ', $last), null);
        }
      }
    }
  }
}
