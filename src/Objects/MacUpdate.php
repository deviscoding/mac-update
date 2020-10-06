<?php

namespace DevCoding\Mac\Update\Objects;

class MacUpdate
{
  /** @var string */
  protected $name;
  /** @var int */
  protected $size;
  /** @var string */
  protected $id;
  /** @var bool */
  protected $recommended = false;
  /** @var bool */
  protected $restart = false;
  /** @var bool */
  protected $shutdown = false;

  /**
   * MacUpdate constructor.
   *
   * @param string $id
   */
  public function __construct($id) { $this->id = $id; }

  /**
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * @param string $name
   *
   * @return MacUpdate
   */
  public function setName($name)
  {
    $this->name = $name;

    return $this;
  }

  /**
   * @return int
   */
  public function getSize()
  {
    return $this->size;
  }

  /**
   * @param int $size
   *
   * @return MacUpdate
   */
  public function setSize($size)
  {
    $this->size = (int)str_replace('K','', $size);

    return $this;
  }

  /**
   * @return string
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param string $id
   *
   * @return MacUpdate
   */
  public function setId($id)
  {
    $this->id = $id;

    return $this;
  }

  /**
   * @return bool
   */
  public function isRecommended()
  {
    return $this->recommended;
  }

  /**
   * @param bool $recommended
   *
   * @return MacUpdate
   */
  public function setRecommended($recommended)
  {
    $this->recommended = $recommended;

    return $this;
  }

  /**
   * @return bool
   */
  public function isRestart()
  {
    return $this->restart;
  }

  public function isShutdown()
  {
    return $this->shutdown;
  }

  /**
   * @param bool $restart
   *
   * @return MacUpdate
   */
  public function setRestart($restart)
  {
    $this->restart = $restart;

    return $this;
  }

  /**
   * @param bool $shutdown
   *
   * @return MacUpdate
   */
  public function setShutdown($shutdown): MacUpdate
  {
    $this->shutdown = $shutdown;

    return $this;
  }
}