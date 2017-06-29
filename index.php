<?php
/**
 * Created by PhpStorm.
 * User: ADMIN
 * Date: 05/04/2017
 * Time: 2:53 CH
 */
include_once './libs/Medoo.php';

include_once './libs/Curl/CaseInsensitiveArray.php';
include_once './libs/Curl/Curl.php';
include_once './libs/Curl/MultiCurl.php';

include_once './libs/DiDom/Document.php';
include_once './libs/DiDom/Element.php';
include_once './libs/DiDom/Query.php';
include_once './libs/DiDom/Errors.php';

use Medoo\Medoo;
use Curl\Curl;

use DiDom\Element;
use DiDom\Document;

define('BASE_URL', 'http://mangaonlinehere.com');


$database = new Medoo([
    'database_type' => 'mysql',
    'database_name' => 'truyentranh',
    'server' => 'localhost',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8'
]);


function insert_store($store){
    $name = $store['store_name'];
    $link = $store['store_link'];
    $desc = $store['store_desc'];
    $release = $store['store_release'];
    $author = $store['store_author'];


    $sql = "INSERT INTO store (store_name, store_link, store_desc, store_release, store_author)".
        " SELECT '$name', '$link', '$desc', '$release', '$author' FROM DUAL".
        " WHERE NOT EXISTS (SELECT * FROM store".
        " WHERE store_link = '$link') LIMIT 1";

    //$sql = "INSERT INTO store (store_name,store_link) VALUES ('$name','$link')";

    //echo $sql;

    global $database;

    $database->query($sql);

    $data = $database->query("SELECT * FROM store WHERE store_link = '$link'")->fetch();

    return $data;
}


function insert_chapter($store_id, $chapter){
    $chapter_name = $chapter['chapter_name'];
    $chapter_date = $chapter['chapter_date'];
    $chapter_link = $chapter['chapter_link'];


    $sql = "INSERT INTO chapter (chapter_name, chapter_date,chapter_link,store_id)".
        " SELECT '$chapter_name', '$chapter_date','$chapter_link',$store_id FROM DUAL".
        " WHERE NOT EXISTS (SELECT * FROM chapter".
        " WHERE chapter_link = '$chapter_link') LIMIT 1";

    global $database;

    $database->query($sql);

    $data = $database->query("SELECT * FROM chapter WHERE chapter_link = '$chapter_link'")->fetch();

    return $data;
}


function insert_image($chapter_id, $image){
    $image_link = $image['image_link'];
    $image_path = $image['image_path'];

    $sql = "INSERT INTO image(image_link, image_path, chapter_id)" .
        " SELECT '$image_link', '$image_path', $chapter_id from DUAL".
        " WHERE NOT EXISTS(SELECT * from image WHERE image_link = '$image_link') LIMIT 1";

    global  $database;

    $database->query($sql);

    $data = $database->query("Select * from image WHERE image_link = '$image_link'")->fetch();

    return $data;
}


function get_data($url, &$content){
    $curl = new Curl();

    $curl->setOpt(CURLOPT_CONNECTTIMEOUT, 120);
    $curl->setTimeout(120);

    echo 'Start crawl: '. $url. PHP_EOL;

    $curl->get($url);

    // không lỗi
    if(!$curl->error){
        $content = $curl->response;
        echo 'Crawl: '. $url. ' sucessfull!'. PHP_EOL;
    }
    else
    {
        echo 'End crawl: '. $url. ' failure!'. PHP_EOL;
    }

    $curl->close();

    return !$curl->error;
}


function download_data($url, $path){
    $curl = new Curl();

    $curl->setOpt(CURLOPT_CONNECTTIMEOUT, 60);
    $curl->setTimeout(60);

    echo 'Start download: '. $url. PHP_EOL;

    $re = $curl->download($url, $path);

    // không lỗi
    if($re){
        echo 'Downloaded: '. $url. ' sucessfull!'. PHP_EOL;
    }
    else
    {
        echo 'End download: '. $url. ' failure!'. PHP_EOL;
    }

    $curl->close();
}
function downloadFile($url, $path){
    $f = fopen($path, 'w');

    $ch = curl_init();
    curl_setopt($ch,  CURLOPT_FILE, $f);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 28800);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 28800);

    curl_exec($ch);

    $e = curl_error($ch);

    curl_close($ch);

    fclose($f);

    return $e;
}


/**
 * Get all infomation from a manga
 */
function get_store($content, &$name, &$desc, &$release, &$author){

    $doc = new Document();

    $doc->load($content);

    $name = $doc->find('div[class=info]')[0]->find('h2')[0]->text();
    $author = $doc->find('div[class=info]')[0]->find('div[class=row-info]')[3]->find('p')[0]->find('span')[1]->text();
    $desc = $doc->find('div[class=info]')[0]->find('div[class=row-info]')[6]->find('p')[1]->text();
    $release =  $doc->find('div[class=info]')[0]->find('div[class=row-info]')[5]->find('p')[0]->find('span')[1]->text();

}


function insert_all_chapter($store_id, $content){
    $dom = new Document();

    $dom->load($content);

    $item_chapters = $dom->find('div[class=list-chapter] ul li');
    if(isset($item_chapters) && count($item_chapters) > 0)
        for($i = 0; $i < count($item_chapters);++$i) {
            $item_chapter = $item_chapters[$i];

            $chapter_info = $item_chapter->find('a')[0]->text();
            preg_match('/([A-Z]).+/', $chapter_info, $chapter_name);

            $date = $item_chapter->find('a')[0]->find('span')[0]->text();
            $url =  BASE_URL. $item_chapter->find('a')[0]->attr('href');

            $chapter = array();
            $chapter['chapter_name'] = $chapter_name[0];
            $chapter['chapter_date'] = $date;
            $chapter['chapter_link'] = $url;

            $res = insert_chapter($store_id, $chapter);

            $chapter_id = $res['chapter_id'];

            insert_all_images($chapter_id, $res['chapter_link']);

        }
}


function insert_all_images($chapter_id, $url){
    $folder_name = bin2hex(openssl_random_pseudo_bytes(16));
    $folder_path = 'data/'.$folder_name;

    echo  'Create folder: '. $folder_name . PHP_EOL;

    mkdir($folder_path, 0777, true);

    if(get_data($url, $content)){
        $dom = new Document();

        $dom->load($content);

        $list_img = $dom->find('div[class=list-img] img');

        if(isset($list_img) && count($list_img) > 0){
            for($i = 0; $i < count($list_img); ++$i){
                $img = $list_img[$i];

                $img_link = $img->attr('src');

                echo 'Image: '.$img_link.PHP_EOL;

                // lấy ra extension của file
                $ext = pathinfo($img_link, PATHINFO_EXTENSION);
                // lấy ra filePath
                $file_path = $folder_path.'/'.$i.'.'.$ext;

                // download image
                $re = downloadFile($img_link, $file_path);

                if(!$re){
                    echo 'Start download: '. $url. ' sucessfull!'. PHP_EOL;
                }
                else
                {
                    echo 'End download: '. $url. ' failure!'. PHP_EOL;
                }

                $image = array();
                $image['image_link'] = $img_link;
                $image['image_path'] = $file_path;

                insert_image($chapter_id, $image);
            }
        }

    }
}



$url  = 'http://mangaonlinehere.com/manga-info/One-Piece';
if(get_data($url, $content)){
    get_store($content, $name, $desc, $release, $author);

    $store = array();
    $store['store_name'] = $name;
    $store['store_link'] = $url;
    $store['store_desc'] = $desc;
    $store['store_release'] = $release;
    $store['store_author'] = $author;

    $res = insert_store($store);

    insert_all_chapter($res[0], $content);
}
else
    echo 'Error!';


