<?php
/*
livedoor Weather Hacks class
livedoorのお天気WEBサービスのREST APIのXMLを取得して、
JSONを返す。
*/

class weatherHacks{

private $cityID = 0;
private $cachedir = '';
private $cache_lifetime = 3600;
private $callback = 'weatherHacks';

private $url = 'http://weather.livedoor.com/forecast/webservice/rest/v1?city=%d&day=%s';
private $days = array(
    'today',
    'tomorrow',
    'dayaftertomorrow',
);

function __construct($cityID, $day = null)
{
    if (preg_match("/^[0-9]+$/", $cityID)) {
        $this->cityID = $cityID;
    } else {
        throw new Exception('一次細区分に数値を指定してください。', 100);
    }
    if ($day && is_array($day)) {
        $this->days = $day;
    } elseif ($day) {
        $this->days = array($day);
    }
}

public function setCallback()
{
    if (preg_match('/^[a-zA-Z0-9_]+$/', $callback)) {
        $this->callback = $callback;
    } else {
        throw new Exception('コールバック関数には半角英数またはアンダースコアを使用してください。', 101);
    }
}

public function setCache($cachedir, $lifetime = null)
{
    if (is_dir($cachedir) && is_writeable($cachedir)) {
        $this->cachedir = $cachedir;
    } else {
        throw new Exception($cachedir.' が存在しないか書き込み権限がありません。', 200);
    }
    if ($lifetime) {
        $this->cache_lifetime = $lifetime;
    }
}

public function getArray()
{
    $cache = null;
    if ($this->cachedir) {
        $cache = $this->cachedir.'/'.md5($this->cityID);
        if (is_file($cache)) {
            $st = stat($cache);
            if ($st['mtime'] > (time()-$this->cache_lifetime)) {
                return json_decode(file_get_contents($cache));
            }
        }
    }

    $data = array();
    foreach ($this->days as $d) {
        $xml = sprintf($this->url, $this->cityID, $d);
        $dom = new DOMDocument();
        if (@!$dom->load($xml)) {
            throw new Exception($xml.' を読み込めません。', 300);
            continue;
        }

        $image = $dom->getElementsByTagName('image')->item(0);
        $temp = $dom->getElementsByTagName('temperature')->item(0);

        $title = $dom->getElementsByTagName('title')->item(0)->nodeValue;
        $desc = $dom->getElementsByTagName('description')->item(0)->nodeValue;
        $date = $dom->getElementsByTagName('forecastdate')->item(0)->nodeValue;
        $pdate = $dom->getElementsByTagName('publictime')->item(0)->nodeValue;
        $weather = $image->getElementsByTagName('title')->item(0)->nodeValue;
        $link = $image->getElementsByTagName('link')->item(0)->nodeValue;
        $img = $image->getElementsByTagName('url')->item(0)->nodeValue;
        $width = $image->getElementsByTagName('width')->item(0)->nodeValue;
        $height = $image->getElementsByTagName('height')->item(0)->nodeValue;
        $max = $this->getCelsius($temp->getElementsByTagName('max')->item(0));
        $min = $this->getCelsius($temp->getElementsByTagName('min')->item(0));

        $pp = $dom->getElementsByTagName('pinpoint')->item(0);
        $loc = $pp->getElementsByTagName('location');
        $pinpoints = array();
        foreach ($loc as $lo) {
            $ttl = $lo->getElementsByTagName('title')->item(0)->nodeValue;
            $link = $lo->getElementsByTagName('link')->item(0)->nodeValue;
            $pdate = $lo->getElementsByTagName('publictime')->item(0)->nodeValue;
            $pinpoints[] = array(
                'title' => $ttl,
                'link' => $link,
                'pubdate' => $pdate,
            );
        }

        $data[$d] = array(
            'title' => $title,
            'desc' => $desc,
            'date' => $date,
            'pubdate' => $pdate,
            'weather' => $weather,
            'link' => $link,
            'img' => $img,
            'width' => $width,
            'height' => $height,
            'max' => $max,
            'min' => $min,
            'pinpoint' => $pinpoints,
        );
    }

    if ($this->cachedir) {
        $json = json_encode($data);
        file_put_contents($cache, $json);
    }

    return $data;
}

public function getJson()
{
    $data = $this->getArray();
    return $this->callback.'('.json_encode($data).')';
}

private function getCelsius($node){
    return $node->getElementsByTagName('celsius')->item(0)->nodeValue;
}

}

?>
