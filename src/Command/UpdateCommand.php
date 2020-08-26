<?php


namespace DevCoding\Mac\Update\Command;


use DevCoding\Command\Base\AbstractConsole;
use DevCoding\Mac\Update\Objects\MacUpdate;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;



class UpdateCommand extends AbstractConsole
{
  const PATTERN_DETAILS = '#^(.*), ([0-9]+)K\s?(.*)$#';

  /** @var string */
  protected $_SoftwareUpdateBinary;
  /** @var array */
  protected $_Updates = [];
  /** @var array */
  protected $_DarwinVersion = [];

  protected function configure()
  {
    $this->setName('check');
    $this->addArgument('verb', InputArgument::OPTIONAL, 'The action to run', 'list');
    $this->addOption('recommended', null, InputOption::VALUE_NONE,'Only include recommended updates');
    $this->addOption('restart', null, InputOption::VALUE_NONE, 'Only include restart updates');
    $this->addOption('size', null, InputOption::VALUE_REQUIRED, 'Only include updates below the given size.');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io()->blankln();
    switch($this->io()->getArgument('verb'))
    {
      case 'check':
        $Updates = $this->getValidUpdates();
        if (empty($Updates))
        {
          $this->io()->errorln('There are no matching updates to install.');
          return self::EXIT_ERROR;
        }
        else
        {
          $this->io()->successln(sprintf('There are %s matching updates to install.',count($Updates)));

          return self::EXIT_SUCCESS;
        }
      case 'list':
        return $this->doList();
      case 'install':
        return self::EXIT_ERROR;
      case 'download':
        return $this->doDownload();
      default:
        $this->io()->error('Unrecognized Verb.  Exiting.');

        return self::EXIT_ERROR;
    }
  }

  protected function doInstall()
  {
    $isRecommended = $this->io()->getOption('recommended') ? true : false;
    $isRestart     = $this->io()->getOption('restart') ? true : false;
    $belowSize     = $this->io()->getOption('size') ? $this->io()->getOption('size') : PHP_INT_MAX;

    if ($isRestart && $belowSize)
    {
      $this->io()->errorblk("The --restart and --size option cannot be used together with the install verb.");
    }

    if ($isRestart)
    {
      $switches = ['i','a','R'];

      if ($isRecommended)
      {
        $switches[] = 'r';
      }

      $this->io()->msgln('Downloading and installing updates.  The system will restart when appropriate.');
      exec(sprintf('%s -%s', $this->getSoftwareUpdate(), implode(' ',$switches)), $output, $retval);

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
      $Updates = $this->getValidUpdates();
      foreach($Updates as $macUpdate)
      {
        $this->io()->info('Installing ' . $macUpdate->getName(), 60);
        exec(sprintf('%s -i "%s"', $this->getSoftwareUpdate(), $macUpdate->getId()), $output, $retval);

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

  protected function doDownload()
  {
    $Updates = $this->getValidUpdates();
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

  protected function doList()
  {
    $Updates = $this->getValidUpdates();

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

  /**
   * @param MacUpdate $MacUpdate
   *
   * @return bool
   */
  protected function isIncluded(MacUpdate $MacUpdate)
  {
    $isRecommended = $this->io()->getOption('recommended') ? true : false;
    $isRestart     = $this->io()->getOption('restart') ? true : false;
    $belowSize     = $this->io()->getOption('size') ? $this->io()->getOption('size') : PHP_INT_MAX;

    if ($MacUpdate->getSize() > $belowSize)
    {
      return false;
    }

    if($isRecommended)
    {
      if (!$MacUpdate->isRecommended())
      {
        // If we are only showing recommended and this isn't recommended, nope
        return false;
      }
      elseif ($MacUpdate->isRestart() && !$isRestart)
      {
        // If this requires a restart and we aren't doing restarts, nope.
        return false;
      }
    }

    if ($isRestart)
    {
      if (!$MacUpdate->isRestart())
      {
        // If we only are showing restarts and this doesn't restart, nope.
        return false;
      }
    }
    elseif ($MacUpdate->isRestart())
    {
      return false;
    }

    return true;
  }

  /**
   * @return MacUpdate[]|array
   * @throws \Exception
   */
  protected function getValidUpdates()
  {
    $MacUpdates = $this->getUpdateList();
    $output     = [];
    foreach($MacUpdates as $macUpdate)
    {
      if ($this->isIncluded($macUpdate))
      {
        $output[] = $macUpdate;
      }
    }

    return $output;
  }

  /**
   * @return MacUpdate[]
   * @throws \Exception
   */
  protected function getUpdateList()
  {
    if (empty($this->_Updates))
    {
      if ($suBin = $this->getSoftwareUpdate())
      {
        exec(sprintf('%s -l', $suBin), $output, $retval);

        if (!empty($output))
        {
          $count = count($output);
          for($x = 0; $x <= $count; $x++)
          {
            unset($Update);
            if(preg_match('#^\s?\*\s(.*)$#', $output[$x], $matches))
            {
              if (!empty($matches[1]))
              {
                $xx = $x+1;
                if(!empty($output[$xx]))
                {
                  if ($this->isCatalinaUp())
                  {
                    $Update = $this->fromCatalina($matches[1], $output[$xx]);
                  }
                  else
                  {
                    $Update = $this->fromMojave($matches[1],$output[$xx]);
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

  protected function fromCatalina($label, $details)
  {
    if (preg_match('#^Label:\s(.*)$#', trim($label), $lParts))
    {
      $Update = new MacUpdate($lParts[1]);

      if (preg_match_all('#([A-Z][a-z]+):\s([^,]+),#', $details, $parts, PREG_SET_ORDER))
      {
        foreach($parts as $part)
        {
          $key = $part[1];
          $val = $part[2];

          if ($key == 'Title')
          {
            $Update->setName($val);
          }
          if ($key == 'Size')
          {
            $Update->setSize($val);
          }
          elseif ($key == 'Recommended' && $val == 'YES')
          {
            $Update->setRecommended(true);
          }
          elseif ($key == 'Action' && $val == 'restart')
          {
            $Update->setRestart(true);
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
   * @return MacUpdate
   */
  protected function fromMojave($label, $details)
  {
    $Update = new MacUpdate(trim($label));
    if (preg_match(self::PATTERN_DETAILS, $details, $parts))
    {
      if (!empty($parts[1]))
      {
        $Update->setName($parts[1]);
      }

      if (!empty($parts[2]))
      {
        $Update->setSize($parts[2]);
      }

      if (!empty($parts[3]))
      {
        if (strpos($parts[3], 'recommended') !== false)
        {
          $Update->setRecommended(true);
        }

        if (strpos($parts[3], 'restart') !== false)
        {
          $Update->setRestart(true);
        }
      }
    }

    return $Update;
  }

  protected function isCatalinaUp()
  {
    $version = $this->getMacOsVersion();

    return $version['minor'] >= 15;
  }

  protected function getMacOsVersion()
  {
    if (empty($this->_DarwinVersion))
    {
      if ($v = $this->getShellExec('sw_vers -productVersion'))
      {
        $parts = explode('.', $v);

        $major = !empty($parts[0]) ? $parts[0] : 10;
        $minor = !empty($parts[1]) ? $parts[1] : 1;
        $rev = !empty($parts[2]) ? $parts[2] : 0;

        $this->_DarwinVersion = ['major' => $major, 'minor' => $minor, 'revision' => $rev];
      }
    }

    return $this->_DarwinVersion;
  }

  /**
   * @return string
   * @throws \Exception
   */
  protected function getSoftwareUpdate()
  {
    if (empty($this->_SoftwareUpdateBinary))
    {
      $this->_SoftwareUpdateBinary = $this->getBinaryPath('softwareupdate');
    }

    return $this->_SoftwareUpdateBinary;
  }


}