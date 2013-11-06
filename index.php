<?php
/**
 * @param PDO $db
 * @param string $url
 * @return string
 */
function pwd_save($db, $url) {
    if(strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0)
        $url = 'http://'.$url;
    $hash = sha1($url);
    $st = $db->prepare('INSERT INTO link (hash, url) VALUES (:hash, :url)');
    $st->bindValue(':hash', $hash, PDO::PARAM_STR);
    $st->bindValue(':url', $url, PDO::PARAM_STR);
    if($st->execute()) {
        $id = $db->lastInsertId();
    } else {
        $e = $st->errorInfo();
        if($e[1] == 1062) {
            $st = $db->query("SELECT id FROM link WHERE hash='$hash'");
            $id = $st->fetchColumn();
        } else {
            return false;
        }
    }
    $key = int2pk($id);
    xcache_set($key, $url, 86400);
    return "http://{$key}.pwd.tw/";
}

/**
 * @param PDO $db
 * @param string $key
 * @return string
 */
function pwd_load($db, $key) {
    if($url = xcache_get($key)) {
        header('X-Cache-Hit: 1');
        return $url;
    } else {
        header('X-Cache-Hit: 0');
    }
    $id = pk2int($key);
    $st = $db->prepare('SELECT * FROM link WHERE id=:id');
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();
    if($r = $st->fetch(PDO::FETCH_ASSOC)) {
        xcache_set($key, $r['url'], 86400);
        return $r['url'];
    } else {
        return false;
    }
}

/**
 * @param $int
 * @return string
 */
function int2pk($int) {
    $map = array(
        '.' => 'adgjmptw', 'a' => '.dgjmptw', 'd' => '.agjmptw', 'g' => '.adjmptw',
        'j' => '.adgmptw', 'm' => '.adgjptw', 'p' => '.adgjmtw', 't' => '.adgjmpw',
        'w' => '.adgjmpt',
    );
    $o = base_convert($int, 10, 8);
    $o = strrev($o);
    $len = strlen($o);
    $char = '.';
    $pk = '';
    for($i = 0; $i < $len; $i++) {
        $char = $map[$char][intval($o[$i])];
        $pk .= $char;
    }
    return $pk;
}

function pk2int($pk) {
    $map = array(
        '.' => 'adgjmptw', 'a' => '.dgjmptw', 'd' => '.agjmptw', 'g' => '.adjmptw',
        'j' => '.adgmptw', 'm' => '.adgjptw', 'p' => '.adgjmtw', 't' => '.adgjmpw',
        'w' => '.adgjmpt',
    );
    $len = strlen($pk);
    $char = '.';
    $o = '';
    for($i = 0; $i < $len; $i++) {
        $o .= strpos($map[$char], $pk[$i]);
        $char = $pk[$i];
    }
    $o = strrev($o);
    $int = base_convert($o, 8, 10);
    return intval($int);
}

$pdo = new PDO(
    'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=pwd;charset=utf8;',
    'pwd',
    'RYMP2CNbY8RAUazQ'
);
$host = $_SERVER['HTTP_HOST'];
if($host == 'pwd.tw') {
    $uri = isset($_SERVER['REQUEST_URI'])?$_SERVER['REQUEST_URI']:'';
    if($uri == '/') {
        require('index.html');
        die();
    }
    $url = substr($uri, 1);
    $short = pwd_save($pdo, $url);
    if($short === false) {
        header('HTTP/1.1 500 Internal Error');
        $e = $pdo->errorInfo();
        die($e[2]);
    }
    echo $short;
} else {
    $p = stripos($host, '.pwd.tw');
    if($p === false) {
        header('HTTP/1.1 404 Not Found');
        die('Not Found');
    }
    $key = substr($host, 0, $p);
    if(!preg_match('#^[.adgjmptw]+$#', $key)) {
        header('HTTP/1.1 400 Bad Request');
        die('Bad Request');
    }
    $target = pwd_load($pdo, $key);
    if($target === false) {
        header('HTTP/1.1 404 Not Found');
        die('Not Found');
    }
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: '.$target);
}



