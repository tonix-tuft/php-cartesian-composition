<?php

/*
 * Copyright (c) 2020 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

namespace CartesianComposition;

use CartesianComposition\CartesianCompositionOption;
use Fun\Fun;
use Tonix\PHPUtils\ArrayUtils;
use Tonix\PHPUtils\CombinatoricsUtils;

/**
 * Class implementing the cartesian composition algorithm.
 */
class CartesianComposition {
  /**
   * @var array
   */
  protected $args;

  /**
   * @var array
   */
  protected $specificParamsMap;

  /**
   * @var array
   */
  protected static $allowedOptionsMap = null;

  /**
   * Constructor.
   *
   * @param array ...$args Array of arrays, each array containing the functions or classes to instantiate and compose.
   */
  public function __construct(...$args) {
    $this->args = $args;
    $this->specificParamsMap = [];
    if (!static::$allowedOptionsMap) {
      static::$allowedOptionsMap = ArrayUtils::arrayMapPreserveKeys(
        function () {
          return true;
        },
        array_flip(CartesianCompositionOption::toKeyVal())
      );
    }
  }

  /**
   * Generates the cartesian composition.
   *
   * @param mixed[] ...$params Initial parameter to pass to the "leaf" function or constructor
   *                           ("leaf" being the last function or constructor of the composition, i.e. the first one to be called).
   * @return array The array representing the cartesian composition.
   *               An empty array in case this class was instantiated with an empty array.
   */
  public function compose(...$params) {
    $l = count($this->args);
    if (empty($l)) {
      return [];
    }
    $ret = [];
    $stack = new \SplStack();
    $optionsPaddingArrayMap = [];
    $alreadyVisitedOptionsMap = [];
    $alreadyVisitedCompositionOfFunctionsOptionsMap = [];
    $alreadyComposedPathsMap = [];

    $this->addToStack(
      $stack,
      0,
      $optionsPaddingArrayMap,
      $alreadyVisitedOptionsMap,
      $alreadyVisitedCompositionOfFunctionsOptionsMap
    );
    while (!$stack->isEmpty()) {
      $current = $stack->pop();
      $currentPath = $current['path'];
      $currentPathLength = count($currentPath);
      if ($currentPathLength === $l) {
        $optionals = [];
        $additionalParamsMap = [];
        for ($i = 0; $i < $currentPathLength; $i++) {
          $currentPathNode = $currentPath[$i];
          if (
            $this->hasOption(
              CartesianCompositionOption::OPTIONAL,
              $optionsPaddingArrayMap,
              $currentPathNode['argIndex']
            ) ||
            $this->hasOption(
              CartesianCompositionOption::OPTIONAL,
              $optionsPaddingArrayMap,
              $currentPathNode['argIndex'],
              $currentPathNode['index']
            )
          ) {
            $optionals[] = $i;
          }
          if (
            ($specificAdditionalParams = $this->option(
              CartesianCompositionOption::ADDITIONAL_PARAMS,
              $optionsPaddingArrayMap,
              $currentPathNode['argIndex'],
              $currentPathNode['index']
            )) !== false
          ) {
            $additionalParamsMap[$i] = $specificAdditionalParams;
          } elseif (
            ($additionalParams = $this->option(
              CartesianCompositionOption::ADDITIONAL_PARAMS,
              $optionsPaddingArrayMap,
              $currentPathNode['argIndex']
            )) !== false
          ) {
            $additionalParamsMap[$i] = $additionalParams;
          }
        }
        $compositionRes = $this->composeRes(
          $currentPath,
          $params,
          $additionalParamsMap
        );
        $ret[] = $compositionRes;
        $optionalsCombinations = CombinatoricsUtils::uniqueProgressiveIncrementalCombinations(
          $optionals
        );
        foreach ($optionalsCombinations as $optionalsCombination) {
          $optionalsCombinationMap = array_flip($optionalsCombination);
          $path = array_filter($currentPath, function ($node) use (
            &$optionalsCombinationMap
          ) {
            return !isset($optionalsCombinationMap[$node['argIndex']]);
          });
          $keys = array_merge(
            [count($path)],
            Fun::flatMap(function ($node) {
              return [$node['argIndex'], $node['index']];
            }, $path)
          );
          if (!ArrayUtils::arrayKeysExist($alreadyComposedPathsMap, ...$keys)) {
            $compositionRes = $this->composeRes(
              $path,
              $params,
              $additionalParamsMap
            );
            $ret[] = $compositionRes;
            ArrayUtils::setNestedArrayValue(
              $alreadyComposedPathsMap,
              ...array_merge($keys, [true])
            );
          }
        }
      } else {
        $nextIndex = $current['node']['argIndex'] + 1;
        $this->addToStack(
          $stack,
          $nextIndex,
          $optionsPaddingArrayMap,
          $alreadyVisitedOptionsMap,
          $alreadyVisitedCompositionOfFunctionsOptionsMap,
          $currentPath
        );
      }
    }

    return $ret;
  }

  /**
   * Sets a map of additional parameters to pass to each specific function or constructor
   * during the composition.
   *
   * @param array $specificParamsMap An array mapping the functions or classes to instantiate (strings identifying the fully qualified name of the class)
   *                                 to the additional parameters to pass when those functions or constructors will be called during the composition.
   * @return void
   */
  public function setSpecificParamsMap($specificParamsMap) {
    $this->specificParamsMap = $specificParamsMap;
  }

  /**
   * Internal method that adds nodes to the current stack during the Depth-First Search used to generate the cartesian composition.
   *
   * @param \SplStack $stack Stack.
   * @param int $argIndex The current index of the composition.
   * @param array $optionsPaddingArrayMap A map containing the metadata of the options currently set.
   * @param array $alreadyVisitedOptionsMap A map used to determine the options already set.
   * @param array $alreadyVisitedCompositionOfFunctionsOptionsMap A map used to determine the specified options already set.
   * @param array $path An array containing the current path.
   * @return void
   */
  protected function addToStack(
    \SplStack $stack,
    $argIndex,
    &$optionsPaddingArrayMap,
    &$alreadyVisitedOptionsMap,
    &$alreadyVisitedCompositionOfFunctionsOptionsMap,
    $path = []
  ) {
    $arg = $this->args[$argIndex];
    $l = count($arg);
    for ($i = $l - 1; $i >= 0; $i--) {
      $possibleFn = $arg[$i];
      $isOptionsPaddingArray = false;
      $possibleFnIsArray = is_array($possibleFn);
      if ($i === 0) {
        $isSetAlreadyVisited = isset($alreadyVisitedOptionsMap[$argIndex]);
        $isOptionsPaddingArray = $isSetAlreadyVisited;
        if ($possibleFnIsArray && !$isSetAlreadyVisited) {
          $areOptions = $this->isOptionsPaddingArray($possibleFn);
          if ($areOptions) {
            // Options (padding array, always the first and always an array).
            $isOptionsPaddingArray = true;
            foreach ($possibleFn as $optionK => $optionV) {
              list($option, $optionValue) = $this->getOptionAndValue(
                $optionK,
                $optionV
              );
              $optionsPaddingArrayMap[$argIndex] = $optionsPaddingArrayMap[
                $argIndex
              ] ?? [
                'optionsMap' => [],
                'specificComposition' => [],
              ];
              $optionsPaddingArrayMap[$argIndex]['optionsMap'][
                $option
              ] = $optionValue;
            }
            $alreadyVisitedOptionsMap[$argIndex] = true;
          }
        }
      }
      if (!$isOptionsPaddingArray) {
        // Function/class to instantiate or array of functions/classes to instantiate and to compose,
        // possibly prepended with an array of options (padding array).
        if ($possibleFnIsArray) {
          // Array of functions/classes to instantiate.
          $fns = [];
          $c = count($possibleFn);
          for ($j = 0; $j < $c; $j++) {
            $innerCompositionPossibleFn = $possibleFn[$j];
            if ($j === 0 && is_array($innerCompositionPossibleFn)) {
              // Padding array (always the first and always an array).
              if (
                empty(
                  $alreadyVisitedCompositionOfFunctionsOptionsMap[
                    "{$argIndex}.{$i}"
                  ]
                )
              ) {
                foreach ($innerCompositionPossibleFn as $optionK => $optionV) {
                  list($option, $optionValue) = $this->getOptionAndValue(
                    $optionK,
                    $optionV
                  );
                  $optionsPaddingArrayMap[$argIndex] = $optionsPaddingArrayMap[
                    $argIndex
                  ] ?? [
                    'optionsMap' => [],
                    'specificComposition' => [],
                  ];
                  $optionsPaddingArrayMap[$argIndex]['specificComposition'][
                    $i
                  ] = $optionsPaddingArrayMap[$argIndex]['specificComposition'][
                    $i
                  ] ?? [
                    'optionsMap' => [],
                  ];
                  $optionsPaddingArrayMap[$argIndex]['specificComposition'][$i][
                    'optionsMap'
                  ][$option] = $optionValue;
                }
                $alreadyVisitedCompositionOfFunctionsOptionsMap[
                  "{$argIndex}.{$i}"
                ] = true;
              }
            } else {
              // Function.
              $fns[] = $innerCompositionPossibleFn;
            }
          }
          // Composition of functions.
          // Push to the stack.
          $node = [
            'fns' => $fns,
            'argIndex' => $argIndex,
            'index' => $i,
          ];
          $stack->push([
            'node' => $node,
            'path' => array_merge($path, [$node]),
          ]);
        } else {
          // Function.
          // Push to the stack.
          $node = [
            'fns' => [$possibleFn],
            'argIndex' => $argIndex,
            'index' => $i,
          ];
          $stack->push([
            'node' => $node,
            'path' => array_merge($path, [$node]),
          ]);
        }
      }
    }
  }

  /**
   * Returns the option and its value in a tuple given the key and the value of an element in the padding array of options.
   *
   * @param string|int $optionK The key of an element of the padding array.
   * @param mixed $optionV The value of an element of the padding array corresponding the the `$optionK` key.
   * @return array A tuple of two elements containing the option and its value, respectively.
   */
  protected function getOptionAndValue($optionK, $optionV) {
    if (isset(static::$allowedOptionsMap[$optionK])) {
      $option = $optionK;
      $optionValue = $optionV;
    } else {
      $option = $optionV;
      $optionValue = true;
    }
    return [$option, $optionValue];
  }

  /**
   * Tests if the array is a padding array of options ({@link CartesianCompositionOption}).
   *
   * @param array $possibleOptionsPaddingArray Possible padding array of options.
   * @return bool TRUE if the array is effectively a padding array of options.
   */
  protected function isOptionsPaddingArray($possibleOptionsPaddingArray) {
    foreach ($possibleOptionsPaddingArray as $k => $v) {
      if (
        isset(static::$allowedOptionsMap[$k]) ||
        isset(static::$allowedOptionsMap[$v])
      ) {
        return true;
      }
      return false;
    }
    return false;
  }

  /**
   * Returns the value of an option (TRUE if the option is set, but it does not have a value)
   * if the option is set in the map of options metadata.
   *
   * @param string $optionCode The code of the option ({@link CartesianCompositionOption}).
   * @param array $optionsPaddingArrayMap A map containing the metadata of the options currently set.
   * @param int $argIndex The argument index.
   * @param int $index The index of the function/class or functions/classes of a specific composition.
   * @return mixed The value of the option or TRUE if the option is set but does not have a value, FALSE if the option is not set.
   */
  protected function option(
    $optionCode,
    $optionsPaddingArrayMap,
    $argIndex,
    $index = null
  ) {
    if (
      $this->hasOption($optionCode, $optionsPaddingArrayMap, $argIndex, $index)
    ) {
      $keys = $this->optionKeys($optionCode, $argIndex, $index);
      return ArrayUtils::nestedArrayValue($optionsPaddingArrayMap, $keys);
    }
    return false;
  }

  /**
   * Tests if an option is set in the map of options metadata.
   *
   * @param string $optionCode The code of the option ({@link CartesianCompositionOption}).
   * @param array $optionsPaddingArrayMap The map containing the metadata of options set currently.
   * @param int $argIndex The argument index.
   * @param int $index The index of the function/class or functions/classes of a specific composition.
   * @return bool TRUE if the option is set, FALSE otherwise.
   */
  protected function hasOption(
    $optionCode,
    $optionsPaddingArrayMap,
    $argIndex,
    $index = null
  ) {
    $keys = $this->optionKeys($optionCode, $argIndex, $index);
    return ArrayUtils::arrayKeysExist($optionsPaddingArrayMap, ...$keys);
  }

  /**
   * Returns the subkeys for an option so that they can be searched in the map of the set options.
   *
   * @param string $optionCode The code of the option ({@link CartesianCompositionOption}).
   * @param int $argIndex The argument index.
   * @param int $index The index of the function/class or functions/classes of a specific composition.
   * @return array An array of keys.
   */
  protected function optionKeys($optionCode, $argIndex, $index = null) {
    return is_null($index)
      ? [$argIndex, 'optionsMap', $optionCode]
      : [$argIndex, 'specificComposition', $index, 'optionsMap', $optionCode];
  }

  /**
   * Composes the result of a path.
   *
   * @param array $path The path which represents a single composition of the cartesian composition.
   * @param array $params Initial parameters.
   * @param array $additionalParamsMap A map of additional options.
   *                                   The key is the index of the path of the function or class and the value is the array of additional parameters.
   * @return mixed The result of the composition.
   */
  protected function composeRes($path, $params, $additionalParamsMap = []) {
    return Fun::compose(
      ...Fun::flatMap(function ($node) use (&$additionalParamsMap) {
        $nodeFns = $node['fns'];
        $additionalParams = $additionalParamsMap[$node['argIndex']] ?? [];
        $mappedFns = array_map(function (&$fn) use ($additionalParams) {
          return function (...$args) use (&$fn, $additionalParams) {
            return Fun::invoke($fn, array_merge($args, $additionalParams));
          };
        }, $nodeFns);
        return $mappedFns;
      }, $path)
    )(...$params);
  }
}
