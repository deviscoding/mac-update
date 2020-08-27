<?php


namespace DevCoding\Mac\Update\Command;


use DevCoding\Command\Base\AbstractConsole;
use DevCoding\Mac\Update\Objects\MacUpdate;
use Symfony\Component\Console\Input\InputOption;



class AbstractUpdateConsole extends AbstractConsole
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
    $this
        ->addOption('recommended', null, InputOption::VALUE_NONE,'Only include recommended updates')
        ->addOption('restart', null, InputOption::VALUE_NONE, 'Only include restart updates')
        ->addOption('size', null, InputOption::VALUE_REQUIRED, 'Only include updates below the given size.')
    ;
  }

  protected function doInstall()
  {

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
          for($x = 0; $x < $count; $x++)
          {
            unset($Update);
            if(preg_match('#\*\s(.*)$#', trim($output[$x]), $matches))
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
        $Update->setName(trim($parts[1]));
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