<?php

/**
 * Miscellaneous functions and consts not in a class
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License 3
 * @author Sylae Jiendra Corell <sylae@calref.net>
 */

namespace Ligrev {

  define("L_DEBUG", 0);
  define("L_INFO", 1);
  define("L_CAUT", 2);
  define("L_WARN", 3);
  define("L_AAAA", 4);

  // Default error reporting level
  define("L_REPORT", L_DEBUG);

  // Take over PHP's error handling, since it's a picky whore sometimes.
  function php_error_handler($no, $str, $file, $line) {
    $message = sprintf(_("%s at %s: %s"), $str, $file, $line);
    switch ($no) {
      case E_ERROR:
      case E_RECOVERABLE_ERROR:
        l($message, "PHP", L_AAAA);
        die();
        break;
      case E_WARNING:
      case E_PARSE:
        l($message, "PHP", L_WARN);
        break;
      case E_NOTICE:
        l($message, "PHP", L_CAUT);
        break;
      case E_DEPRECATED:
      case E_STRICT:
        l($message, "PHP", L_DEBUG);
        break;
      default:
        l($message, "PHP", L_INFO);
        break;
    }
    return true;
  }

// Function to log/echo to the console. Includes timestamp and what-not
  function l($text, $prefix = "", $level = L_INFO) {
    // get current log time
    $time = date("H:i:s");
    switch ($level) {
      case L_DEBUG:
        $tag = "[\033[0;36mDBUG\033[0m]";
        break;
      case L_INFO:
        $tag = "[\033[0;37mINFO\033[0m]";
      default:
        break;
      case L_CAUT:
        $tag = "[\033[0;33mCAUT\033[0m]";
        break;
      case L_WARN:
        $tag = "[\033[0;31mWARN\033[0m]";
        break;
      case L_AAAA:
        $tag = "[\033[41mAAAA\033[0m]";
        break;
    }
    $prefix = (strlen($prefix) > 0) ? "[$prefix]" : "";
    if ($level >= L_REPORT) {
      echo "[$time]$tag$prefix " . html_entity_decode($text) . PHP_EOL;
    }
  }

  function rss_init() {
    global $config;
    $rss = $config['rss'];
    $feeds = array();
    foreach ($rss as $feed) {
      $feeds[] = new RSS($feed['url'], $feed['rooms'], $feed['ttl']);
    }
  }

}

namespace Ligrev\Command {

  function l($text, $tag = "", $level = L_INFO) {
    return \Ligrev\l($text, $tag, $level);
  }

}