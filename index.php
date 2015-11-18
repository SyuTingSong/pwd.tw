<?php
$rsa_private_key = <<<'PRIVATEKEY'
-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQCZkpqF6T2OKDxev5doRxHIxhTICLQJyL3uiTVxSjoPgD0AifDx
qgRXgbRpNa2xeNmALzbwddYyvPtciOMQZztT9B/tYRVpQEDEhtYVfyCTAYNWJmfb
xEjEVZFxiUP+NPBO51VjisSXUdMlKKblrJQAeLWOX5/LvvieNUQ1rlGuIwIDAQAB
AoGAaJQ6IBjeLzFdMxR7cap8BOJHApVSrRsDpC3Rs+1dLnMgl35YEum5fTG5fq/s
MV/flXgRjJxiGjkxXylknyX9crdNoUTGSVlUGXFPsMl0JurwSB8s55rcf+OrWMgR
FA+xl9aibCKxJaABlxZEx9YuZ0RmQEAYwpMb/Wxfue+++KECQQDHHbNt16NHv7zg
MsgNWGFzqXvQtFQQHKX0LNXEh6QtQHA8yZI9aO4TbeEiCB36RWasf3s4sU+tMbV3
yk+SZORzAkEAxXIZ/1ASLw7qZliVHIl3X9cGS1MLBgVz18NyqSDZTMrxBsJu3mDo
a9A7xbXbKlLiFJfWqvSYdttSNwZksrlTkQJAC9x5E9IEqAGD/tcHk8PwCjPObGBR
oaQTPrhtA4gQ/6EXDofzbjUR+ZZSEvTo1D/OHfh6HqZxWJ/db4VduBrKgQJAKxsy
Gc99aNC01Ata4pQQf9gOA7vpmDLwi5acHdiSGHXmETe5xMsbcw5PPmbppl/aA+zy
bPhhoPFZDbJTocFcQQJBAMMM2Wa6OhsR6MPAPayCKtPZkicE8fkx4vZDyj2HCnEP
gbgmrb7NRyefsynFiNKO+/r+j3GM9qr5yJWldVRdvAE=
-----END RSA PRIVATE KEY-----
PRIVATEKEY;

/**
 * diegoperini's URL Pattern
 * @see https://mathiasbynens.be/demo/url-regex
 */
$url_pattern = '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?::\d{2,5})?(?:/[^\s]*)?$_iuS';

/**
 * @param PDO $db
 * @param string $url
 * @return array
 */
function pwd_save($db, $url) {
    $hash = sha1($url);
    $st = $db->prepare('INSERT INTO link (hash, url) VALUES (:hash, :url)');
    $st->bindValue(':hash', $hash, PDO::PARAM_STR);
    $st->bindValue(':url', $url, PDO::PARAM_STR);
    if ($st->execute()) {
        $id = $db->lastInsertId();
        $visit = 0;
    } else {
        $e = $st->errorInfo();
        if ($e[1] == 1062) {
            $st = $db->query("SELECT id, visit FROM link WHERE hash='$hash'");
            $r = $st->fetch(PDO::FETCH_ASSOC);
            $id = $r['id'];
            $visit = $r['visit'];
        } else {
            return false;
        }
    }
    $key = int2pk($id);
    return ['url' => "http://{$key}.pwd.tw/", 'visit' => $visit];
}

/**
 * @param PDO $db
 * @param string $key
 * @return array
 */
function pwd_load($db, $key) {
    $id = pk2int($key);
    $st = $db->prepare("UPDATE link SET visit=visit+1 WHERE id=:id");
    $st->bindValue(':id', pk2int($key), PDO::PARAM_INT);
    $st->execute();
    $st = $db->prepare('SELECT * FROM link WHERE id=:id');
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();

    if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        return $r;
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
        '.' => 'adgjmptw',
        'a' => '.dgjmptw',
        'd' => '.agjmptw',
        'g' => '.adjmptw',
        'j' => '.adgmptw',
        'm' => '.adgjptw',
        'p' => '.adgjmtw',
        't' => '.adgjmpw',
        'w' => '.adgjmpt',
    );
    $o = base_convert($int, 10, 8);
    $o = strrev($o);
    $len = strlen($o);
    $char = '.';
    $pk = '';
    for ($i = 0; $i < $len; $i++) {
        $char = $map[$char][intval($o[$i])];
        $pk .= $char;
    }
    return $pk;
}

function pk2int($pk) {
    $map = array(
        '.' => 'adgjmptw',
        'a' => '.dgjmptw',
        'd' => '.agjmptw',
        'g' => '.adjmptw',
        'j' => '.adgmptw',
        'm' => '.adgjptw',
        'p' => '.adgjmtw',
        't' => '.adgjmpw',
        'w' => '.adgjmpt',
    );
    $len = strlen($pk);
    $char = '.';
    $o = '';
    for ($i = 0; $i < $len; $i++) {
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
if (in_array($host, array('pwd.tw', 'c.pwd.tw', 'u.pwd.tw'))) {
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if ($uri == '/') {
        require('index.html');
        die();
    }
    $url = '';
    if ($host == 'u.pwd.tw') {
        if (isset($_GET['u'])) {
            $url = $_GET['u'];
        } elseif (isset($_GET['b'])) {
            $crypto = base64_decode($_GET['b']);
            $key = openssl_pkey_get_private($rsa_private_key);
            $success = openssl_private_decrypt($crypto, $url, $key, OPENSSL_PKCS1_PADDING);
            if (!$success) {
                header('HTTP/1.1 400 Bad Request');
                echo '<h2>Invalid URL Crypto';
                die();
            }
            openssl_free_key($key);
        }
    } else {
        $url = substr($uri, 1);
    }
    if (strpos($url, 'favicon.ico') !== false) {
        header('HTTP/1.1 410 Gone');
        echo '<h2>We don\'t provide shorten for favicon.ico';
        die();
    }
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0)
        $url = 'http://' . $url;
    if (!preg_match($url_pattern, $url)) {
        header('HTTP/1.1 400 Bad Request');
        echo '<h2>Invalid URL';
        die();
    }
    $short = pwd_save($pdo, $url);
    if ($short === false) {
        header('HTTP/1.1 500 Internal Error');
        $e = $pdo->errorInfo();
        die($e[2]);
    }
    if ($host != 'c.pwd.tw') {
        require("short.php");
    } else {
        header("Content-Type: text/plain");
        echo $short['url'];
    }
} else {
    $p = stripos($host, '.pwd.tw');
    if ($p === false) {
        header('HTTP/1.1 404 Not Found');
        die('Not Found');
    }
    $key = substr($host, 0, $p);
    if (!preg_match('#^[.adgjmptw]+$#', $key)) {
        header('HTTP/1.1 400 Bad Request');
        die('Bad Request');
    }
    $target = pwd_load($pdo, $key);
    if ($target === false) {
        header('HTTP/1.1 404 Not Found');
        die('Not Found');
    }

    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $target['url']);
    require("jump.php");
}
