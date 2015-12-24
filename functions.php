<?php

/**
 * Miscellaneous functions and consts not in a class
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License 3
 * @author Sylae Jiendra Corell <sylae@calref.net>
 */

namespace Ligrev {

  define("V_LIGREV", trim(`git rev-parse HEAD`));

  define("L_DEBUG", 0);
  define("L_INFO", 1);
  define("L_CAUT", 2);
  define("L_WARN", 3);
  define("L_AAAA", 4);

  // Default error reporting level
  define("L_REPORT", L_DEBUG);

  // Take over PHP's error handling, since it's a picky whore sometimes.
  function php_error_handler($no, $str, $file, $line) {
    $message = sprintf("%s at %s: %s", $str, $file, $line);
    switch ($no) {
      case E_ERROR:
      case E_RECOVERABLE_ERROR:
      case E_PARSE:
        l($message, "PHP", L_AAAA);
        die(1);
        break;
      case E_WARNING:
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

  function userTime($epoch, $tzo = "+00:00", $locale = null, $html = true) {

    // first, parse our tzo into seconds
    preg_match_all('/([+-]?)(\\d{2}):(\\d{2})/', $tzo, $matches);
    if (array_key_exists(0, $matches[1])) {
      $sign = $matches[1][0];
      $h = $matches[2][0] * 3600;
      $m = $matches[3][0] * 60;
      $offset = ($sign == "-") ? -1 * $h + $m : $h + $m;
      ;
    } else {
      $offset = 0;
    }
    $intl_soon = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::LONG, timezone_name_from_abbr("", $offset, false));
    $intl_past = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::LONG, timezone_name_from_abbr("", $offset, false));
    $date = new \DateTime(date('c', $epoch));
    $date->setTimezone(new \DateTimezone(timezone_name_from_abbr("", $offset, false)));
    $time = ($epoch > time() - (60 * 60 * 24)) ? $intl_soon->format($date) : $intl_past->format($date);
    $xmpptime = date(DATE_ATOM, $epoch);
    if ($html) {
      return "<span data-timestamp=\"$xmpptime\">$time</span>";
    } else {
      return $time;
    }
  }

  function t($string, $lang = null) {
    global $i18n, $config;

    if (is_null($lang)) {
      $lang = $config['lang'];
    }

    $opts = array();
    foreach ($i18n as $ilang => $strings) {
      if (array_key_exists($string, $strings) && strlen($strings[$string]['msgstr'][0]) > 0) {
        $opts[$ilang] = $strings[$string]['msgstr'][0];
      }
    }

    // the "en" lang file won't show up, because it's empty, but that's okay.
    $opts['en'] = $string;

    $best = \Locale::lookup(array_keys($opts), $inlang, true, "en");
    if (count($opts) == 0) {
      return $string;
    }
    return $opts[$best];
  }

}

namespace Ligrev\Command {

  function l($text, $tag = "", $level = L_INFO) {
    return \Ligrev\l($text, $tag, $level);
  }

}
