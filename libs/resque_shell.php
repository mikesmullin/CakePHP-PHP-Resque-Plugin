<?php

/**
 * Base Job class for all Resque Jobs
 */
class ResqueShell {
  /**
   * Internal array
   *
   * May contains the following keys
   *  stdout:         filehandle  Standard output stream.
   *  stderr:         filehandle  Standard error stream.
   *  modelNames:     array       An array containing the class names of the models this controller uses.
   *  persistModel:   boolean     Used to create cached instances of models a controller uses.
   *  controller:     object      Internal reference to a dummy controller
   *
   * @var string
   */
  var $_internals = array();

  function __construct() {
    $this->startup();
  }

  function startup() {
  }

  function loadModel($modelName) {
    if (App::import('Model', $modelName)) {
      $this->$modelName = new $modelName;
      return true;
    }
    return false;
  }

  /**
   * Loads a Controller and attaches the appropriate models
   *
   * @param string $controllerClass Name of model class to load
   * @param array $modelNames Array of models to load onto controller
   * @return mixed true when single controller found and instance created, error returned if controller not found.
   * @access public
   */
  function loadController($controllerClass = 'Controller', $modelNames = array()) {
    list($plugin, $controllerClass) = pluginSplit($controllerClass, true, null);

    $loaded = false;
    if ($plugin . $controllerClass == 'Controller') {
      if (!empty($this->_internals['controller'])) {
          $loaded = true;
      }
    } else {
      if (!empty($this->{$controllerClass})) {
          $loaded = true;
      }
    }

    if ($loaded) {
      $message = sprintf("%s Controller", $controllerClass);
      if (!empty($plugin)) {
        $message .= sprintf(" in %s Plugin", substr($plugin, 0, -1));
      }
      throw new Exception(sprintf("%s is already loaded", $message));
    }

    if (!class_exists('Controller')) {
      App::import('Core', 'Controller');
    }
    if (!class_exists($controllerClass)) {
      App::import('Controller', $plugin . $controllerClass);
    }

    if ($controllerClass == 'Controller') {
      $controllerClassName = 'Controller';
      $controller =& new $controllerClassName();
      $controller->uses = array();
    } else {
      $controllerClassName = $controllerClass . 'Controller';
      $controller =& new $controllerClassName();
    }

    $controller->constructClasses();
    $controller->startupProcess();

    foreach ($modelNames as $modelName) {
      $controller->loadModel($modelName);
    }

    if ($plugin . $controllerClass == 'Controller') {
      $this->_internals['controller'] = &$controller;
    } else {
      $this->{$controllerClass} = &$controller;
    }
    return true;
  }

  /**
  * Loads a Component
   *
   * @param string $componentClass Name of model class to load
   * @return void
   * @access public
   */
  function loadComponent($componentClass, $settings = array()) {
    if (empty($this->_internals['controller'])) {
      $this->loadController();
    }

    App::import('Component', $componentClass);
    list($plugin, $componentClass) = pluginSplit($componentClass, true, null);
    $componentClassName = $componentClass . 'Component';
    $object =& new $componentClassName(null);

    if (method_exists($object, 'initialize')) {
      $object->initialize($this->_internals['controller'], $settings);
    }

    if (isset($object->components) && is_array($object->components)) {
      $normal = Set::normalize($object->components);
      foreach ((array) $normal as $component => $config) {
        $this->_internals['controller']->Component->_loadComponents($object, $component);
      }

      foreach ((array) $normal as $component => $config) {
        list($plugin, $relatedComponentClass) = pluginSplit($component, true, null);

        if (method_exists($object, 'initialize')) {
          $object->{$relatedComponentClass}->initialize($this->_internals['controller'], $settings);
        }
        if (method_exists($object, 'startup')) {
          $object->{$relatedComponentClass}->startup($this->_internals['controller']);
        }
      }
    }

    if (method_exists($object, 'startup')) {
      $object->startup($this->_internals['controller']);
    }

    $this->{$componentClass} = &$object;
  }

  /**
   * Outputs a single or multiple messages to stdout. If no parameters
   * are passed outputs just a newline.
   *
   * @param mixed $message A string or a an array of strings to output
   * @param integer $newlines Number of newlines to append
   * @return integer Returns the number of bytes returned from writing to stdout.
   */
  function out($message = null, $newlines = 1) {
    if (is_array($message)) {
        $message = implode($this->nl(), $message);
    }
    return $this->stdout($message . $this->nl($newlines), false);
  }

  /**
   * Outputs a single or multiple error messages to stderr. If no parameters
   * are passed outputs just a newline.
   *
   * @param mixed $message A string or a an array of strings to output
   * @param integer $newlines Number of newlines to append
   * @access public
   */
  function err($message = null, $newlines = 1) {
    if (is_array($message)) {
      $message = implode($this->nl(), $message);
    }
    $this->stderr($message . $this->nl($newlines));
  }

  /**
   * Outputs to the stdout filehandle.
   *
   * @param string $string String to output.
   * @param boolean $newline If true, the outputs gets an added newline.
   * @return integer Returns the number of bytes output to stdout.
   */
  function stdout($string, $newline = true) {
    if (empty($this->_internals['stdout'])) {
      $this->_internals['stdout'] = fopen('php://stdout', 'w');
    }

    if ($newline) {
      return fwrite($this->_internals['stdout'], $string . "\n");
    } else {
      return fwrite($this->_internals['stdout'], $string);
    }
  }

  /**
   * Outputs to the stderr filehandle.
   *
   * @param string $string Error text to output.
   * @access public
   */
  function stderr($string) {
    if (empty($this->_internals['stderr'])) {
      $this->_internals['stderr'] = fopen('php://stderr', 'w');
    }

    fwrite($this->_internals['stderr'], $string);
  }

  /**
   * Returns a single or multiple linefeeds sequences.
   *
   * @param integer $multiplier Number of times the linefeed sequence should be repeated
   * @return string
   */
  function nl($multiplier = 1, $print = false) {
    if ($print) return $this->stdout(str_repeat("\n", $multiplier), false);
    return str_repeat("\n", $multiplier);
  }

  /**
   * Outputs a series of minus characters to the standard output, acts as a visual separator.
   *
   * @param integer $newlines Number of newlines to pre- and append
   */
  function hr($newlines = 0) {
    $this->out(null, $newlines);
    $this->out('---------------------------------------------------------------');
    $this->out(null, $newlines);
  }
}
