<?php

namespace CartesianComposition;

use Tonix\PHPUtils\Enum\EnumToKeyValTrait;

/**
 * Enum-like class.
 */
abstract class CartesianCompositionOption {
  use EnumToKeyValTrait;

  /**
   * @var string
   */
  const OPTIONAL = __CLASS__ . '::OPTIONAL';

  /**
   * @var string
   */
  const ADDITIONAL_PARAMS = __CLASS__ . '::ADDITIONAL_PARAMS';
}
