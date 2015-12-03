<?php

/**
 * Description here
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License 3
 * @author Christoph Burschka <christoph@burschka.de>
 * @author Sylae Jiendra Corell <sylae@calref.net>
 */

namespace Ligrev;

class RSS {

  function __construct($url, $rooms, $ttl = 300) {
    global $db;
    $this->url = $url;
    $this->ttl = $ttl;
    $this->rooms = $rooms;
    $sql = $db->executeQuery('SELECT request, latest FROM rss WHERE url=? ORDER BY request DESC LIMIT 1;', array($url));
    $result = $sql->fetchAll();
    $this->last = array_key_exists(0, $result) ? $result[0] : array("request"=>0, "latest"=>0);
    $this->updateLast = $db->prepare('
         INSERT INTO rss (url, request, latest) VALUES(?, ?, ?)
         ON DUPLICATE KEY UPDATE request=VALUES(request), latest=VALUES(latest);', array('string', 'integer', 'integer'));
    // Update once on startup, and then every TTL seconds.
    $this->update();
    \JAXLLoop::$clock->call_fun_periodic($this->ttl * 1000000, function () {
      $this->update();
    });
  }

  function update() {
    global $client;
    $this->last['request'] = time();
    $data = \qp(file_get_contents($this->url));
    $items = $data->find('item');
    $newest = $this->last['latest'];
    $newItems = array();
    foreach ($items as $item) {
      $published = strtotime($item->find('pubDate')->text());
      if ($published <= $this->last['latest'])
        continue;
      $newest = max($newest, $published);
      $newItems[] = (object) [
          'channel' => $item->parent('channel')->find('channel>title')->text(),
          'title' => $item->find('title')->text(),
          'link' => $item->find('link')->text(),
          'date' => $published,
          'category' => $item->find('category')->text(),
          'body' => $item->find('description')->text(),
      ];
    }
    $this->updateLast->bindValue(1, $this->url, "string");
    $this->updateLast->bindValue(2, $this->last['request'], "integer");
    $this->last['latest'] = $newest;
    $this->updateLast->bindValue(3, $this->last['latest'], "integer");
    $this->updateLast->execute();
    foreach ($newItems as $item) {
      /**
       * @todo Send this as HTML.
       */
      $message = sprintf(_("New post in %s / %s: %s: %s"), $item->channel, $item->category, $item->title, $item->link);
      foreach ($this->rooms as $room) {
        $client->xeps['0045']->send_groupchat($room, $message);
      }
    }
  }

}
