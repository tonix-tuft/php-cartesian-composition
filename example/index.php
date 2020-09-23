<?php

use CartesianComposition\CartesianComposition;
use CartesianComposition\CartesianCompositionOption;

require_once __DIR__ . '/../vendor/autoload.php';

function a(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function b(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function c(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function d(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function e(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function f(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function g(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function h(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function i(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function x(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function u(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function v(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

function w(...$args) {
  return __FUNCTION__ . '(' . implode(', ', $args) . ')';
}

class AClass {
  public function __construct(...$args) {
    $this->args = $args;
  }

  public function __toString() {
    return 'new ' . get_class($this) . '(' . implode(', ', $this->args) . ')';
  }
}
class BClass extends AClass {
}
class CClass extends AClass {
}
class DClass extends AClass {
}
class EClass extends AClass {
}
class FClass extends AClass {
}
class GClass extends AClass {
}
class HClass extends AClass {
}
class IClass extends AClass {
}

$composition = new CartesianComposition(
  ['a', 'b', 'c'], // A
  [[CartesianCompositionOption::OPTIONAL], 'd', 'e', 'f', 'g'], // B
  ['h', [[CartesianCompositionOption::OPTIONAL], 'i']] // C
);
$res = $composition->compose(1, 2, 3);

$composition = new CartesianComposition(
  ['a', ['b', 'x', 'u', 'v', 'w'], 'c'], // A
  [
    [
      CartesianCompositionOption::OPTIONAL,
      CartesianCompositionOption::ADDITIONAL_PARAMS => [4, 5],
    ],
    'd',
    [
      [
        CartesianCompositionOption::ADDITIONAL_PARAMS => [6, 7],
      ],
      'e',
    ],
    'f',
    'g',
  ], // B
  ['h', 'i'] // C
);
$res = $composition->compose(1, 2, 3);

$i = 0;
foreach ($res as $result) {
  echo ++$i . ') ' . $result . '<br/>';
}

echo '<br/>' . '<br/>';

$composition = new CartesianComposition(
  [AClass::class, [BClass::class, 'x', 'u', 'v', 'w'], CClass::class], // A
  [
    [
      CartesianCompositionOption::OPTIONAL,
      CartesianCompositionOption::ADDITIONAL_PARAMS => [4, 5],
    ],
    DClass::class,
    [
      [
        CartesianCompositionOption::ADDITIONAL_PARAMS => [6, 7],
      ],
      EClass::class,
    ],
    FClass::class,
    GClass::class,
  ], // B
  [HClass::class, IClass::class] // C
);
$res = $composition->compose(1, 2, 3);

$i = 0;
foreach ($res as $result) {
  echo ++$i . ') ' . $result . '<br/>';
}
