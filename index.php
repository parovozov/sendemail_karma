<?php
/**
 * Created by PhpStorm.
 * User: Дмитрий
 * Date: 15.02.2022
 * Time: 15:10
 */
ini_set('max_execution_time', 0);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

function connectDbPDO(){
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=usersemail;","root","");
        return $pdo;
    }
    catch(PDOException $e) {
        echo $e->getMessage();
    }
}
function connectDb(){
    $mysqli = new mysqli( "localhost", "root", "", "usersemail", '3306' );
    //$mysqli = new mysqli( "localhost", "p-148_kooz", "ghasJLl985LHH", "p-14825_task;" );
    return $mysqli;
}

function createRandWord(int $start=6, int $limit= 30): string{
    $letters = 'ABCDEFGKIJKLMNOPQRSTUVWXYZ_-1234567890';
    $caplen = rand($start, $limit);
    $mailName = "";
    for ($i = 0; $i < $caplen; $i++) {
        $mailName .= $letters[ rand(0, strlen($letters)-1) ];
    }
    return strtolower($mailName);
}

function createRandDate(){
    $randCase = rand(0, 5);
    if($randCase == 5){
        $randDay = rand(1, 3);
    }
    else{
        $randDay = rand(6, 60);
    }
    $randH = rand(1, 23);
    $randM = rand(1, 59);
    $randS = rand(1, 59);
    $randDate = date('Y-m-d H:i:s', strtotime("-{$randDay} day {$randH} hours {$randM} minutes {$randS} seconds"));
    return $randDate;

}

function execSqlPDO(string $sql, array $arr=[]){
    global $db;
    try {
        $fitch = $db->exec($sql);
    }
    catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
    }
}

function execMultiSql(array &$arr=[], int $limit=40, bool $last=false){
    global $db;
    if(!$last){
        if(count($arr) == $limit){
            $sql = implode(";", $arr);
            $db->multi_query($sql);
            while ($db->next_result()) {
                if (!$db->more_results()) break;
            }
            echo $limit."<br>\n";
            $arr=[];
        }
    }
    else {
        $sql = implode(";", $arr);
        $db->multi_query($sql);
        while ($db->next_result()) {
            if (!$db->more_results()) break;
        }
        echo "last <br>\n";
        $arr=[];
    }
}

function createDbItems(){
    for ($i = 0; $i < 1000000; $i++){
        $mailName = createRandWord();
        $mailName .= "@smail.com";
        $userName = createRandWord(6, 10);
        $createRandDate = createRandDate();
        /*$rows = [
            ['username' => $userName, 'email' => $mailName, 'validts' => $createRandDate,  'conﬁrmed' => '0'],
        ];*/
        $sql[] = "INSERT INTO `users` (`username`, `email`, `validts`, `conﬁrmed`) VALUES ('{$userName}', '{$mailName}', '{$createRandDate}', '0')";
        execMultiSql($sql, 100);
    }
    execMultiSql($sql, 100,true);
}




function findAndDellIdFromCollect(array $collectEmail, array $arrId){
    foreach ($collectEmail as $key => $value){
        if(in_array($value['id'], $arrId)){
            unset($collectEmail[$key]);
        }
    }
    return $collectEmail;
}

/*
 * проверка почты на правильное написание
 * */
function check_email(string $email){
    return preg_match("/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,6})+$/", $email);
}
/*
 * send_email
 * Функция собирает данные в пакет, чтобы в дальнейшем отправить на асинхронную обработку,
 * что ускорит процес рассылки почты
 * */
function send_email(int $id, string $username, string $from=FROM, string $to, string $subj=SUBJ, string $body=BODY){
    global $collectEmail;
    $body = sprintf($body, $username);
    $collectEmail[]=['id'=>$id,'username'=>$username, 'from'=>$from, 'to'=>$to, 'subj'=>$subj, 'body'=>$body];
}
/*
 * sendStart
 * Совершает асинхронную отправку собранного пакета данных с помощью CURL. В файле mail.php, который
 * обрабатывает данные установлено время выполнения скрипта не более минуты, поэтому в случает каких-то подвисаний или сбоев
 * работа основного скрипта не будет остановлена.
 * */
function sendStart(){
    global $collectEmail, $db;
    $arrCh=[];
    /*
     * Инициализируем каждую итерацию отправки курл, устанавливаем опции и post данные,
     * дескриптор иницализации записываем в массив, чтобы был доступ и устанавливаем статус в 1
     * показывающий, что этот код отработал
     * */
    foreach ($collectEmail as $key => $value){
        $arrCh[$key] = curl_init();
        curl_setopt($arrCh[$key], CURLOPT_URL, SENDEMAILURL);
        curl_setopt($arrCh[$key], CURLOPT_HEADER, 0);
        curl_setopt($arrCh[$key], CURLOPT_POST, 1);
        curl_setopt($arrCh[$key], CURLOPT_POSTFIELDS, $value);

        /*
         * status_email_send - данная таблица является временным буфером хранения статусов, в конце она очистится. В ней использую
         * insert без update, что ускоряет работу с таблицей
     * */
        $sql = "INSERT INTO `status_email_send` (`id`, `status`) VALUES ('{$value['id']}', '1')";
        $db->query($sql);
    }

    /*
     * Инициализируем мулти отправку
     * */
    $mh = curl_multi_init();

    /*
     * Добавляем в мультиотправку каждую итерацию
     * */
    foreach ($arrCh as $value){
        curl_multi_add_handle($mh, $value);
    }

    /*
     * Отправляем и следим за статусом отправки
     * */
    do {
        $status = curl_multi_exec($mh, $active);
    } while ($active > 0);

    /*
     * Закрываем мултиотправку
     * */
    foreach ($arrCh as $value){
        curl_multi_remove_handle($mh, $value);
    }
    curl_multi_close($mh);

    /*
     * Выбираем id всех итераций, которые не имеют по каким-то причинам все статусы 1,2,3. Эти id заносим
     * в таблицу, чтобы в дальнейшем разобраться в причине
     * */
    $sql = "SELECT id, COUNT(*) as co FROM `status_email_send` WHERE status='1' OR status='2' OR status='3'  GROUP BY id HAVING co<3";
    $result = $db->query($sql);
    $arrId=[];
    while ($row = $result->fetch_object()){
        $arrId[]=(int)$row->id;
        $sql="INSERT INTO `error_send_email` (`id`) VALUES ('{$row->id}')";
        $db->query($sql);
    }

    /*
     * присваиваем статус отправки 1 всем у которых имеются все статусы
     * findAndDellIdFromCollect удаляет из массива элементы с не достающими статусами
     * */
    $newCollectionEmail = findAndDellIdFromCollect($collectEmail, $arrId);
    foreach ($newCollectionEmail as $key => $value){
        $sql="UPDATE users SET send_email_status='1' WHERE `id`={$value['id']}";
        $db->query($sql);
    }

    /*
     * Очищаем таблицу, это таблица служит временным буфером хранения статусов. удобно тк использую insert а не update
     * что ускоряет работу
     * */
    $sql="TRUNCATE status_email_send";
    $db->query($sql);



}

// ------------ main-----------
const LIMITSELECT = 10;
const FROM = "emailcompany@gmail.com";
const SUBJ = "Your subscription is expiring soon.";
const BODY = "%s, your subscription is expiring soon.";
const SENDEMAILURL = "http://curl.loc/hilempopo/sendemail/mail.php";

$db = connectDb();
//createDbItems();
$count=0;
$collectEmail=[];
while(true){
    $count++;
    //AND send_email_status = 0
    $sql = "
SELECT id, username, email, validts FROM users 
WHERE DATEDIFF(CURDATE(), validts) <= 3
AND send_email_status = 0
LIMIT ".LIMITSELECT;

    try {
        if ($result = $db->query($sql)) {
            while ($obj = $result->fetch_object()) {
                $id       = (int) $obj->id;
                $username = (string) $obj->username;
                $to       = (string) $obj->email;
                send_email($id, $username, FROM, $to, SUBJ, BODY );
            }
            sendStart();
        }
        else new Exception("Не удалось выбрать из базы. Проход while {$count}");
    }
    catch (Exception $e) {
        echo "Ошибка бд: " . $e->getMessage();
    }

if($count==100){$db->close();
    break;}

}




/*
 *
 SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `users` (
`id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `validts` datetime NOT NULL,
  `conﬁrmed` tinyint(4) NOT NULL DEFAULT '0',
  `send_email_status` tinyint(4) NOT NULL DEFAULT '0',
  `hash_email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);


ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
*/
//---------------------------------------------------------------------