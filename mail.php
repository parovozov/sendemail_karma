<?php
/**
 * Created by PhpStorm.
 * User: Дмитрий
 * Date: 15.02.2022
 * Time: 23:11
 */
ini_set('max_execution_time', 60);
function connectDb(){
    $mysqli = new mysqli( "localhost", "root", "", "usersemail", '3306' );
    //$mysqli = new mysqli( "localhost", "p-148_kooz", "ghasJLl985LHH", "p-14825_task;" );
    return $mysqli;
}
function sendEmail(array $senddata){
    $to      = $senddata['to'];
    $subject = $senddata['subj'];
    $message = $senddata['body'];
    $headers = 'From: '.$senddata['from']. "\r\n" .
        'Reply-To: '.$senddata['from']. "\r\n" .
        'X-Mailer: PHP/' . phpversion();

    return mail($to, $subject, $message);
}
$db = connectDb();
if(isset($_POST)){
    /*
     * Устанавливаем первый статус отправки 2
    */
    $sql = "INSERT INTO `status_email_send` (`id`, `status`) VALUES ('{$_POST['id']}', '2')";
    $db->query($sql);
    /*
     * Отправляем почту и устанавливаем статус 3 при успехе
    */
    if(sendEmail($_POST)){
        $sql = "INSERT INTO `status_email_send` (`id`, `status`) VALUES ('{$_POST['id']}', '3')";
        $db->query($sql);
    }
    else{
        $sql = "INSERT INTO `status_email_send` (`id`, `status`) VALUES ('{$_POST['id']}', '3')";
        $db->query($sql);
    }

}
else{
    $sql = "INSERT INTO `status_email_send` (`id`, `status`) VALUES ('{$_POST['id']}', '22')";
    $db->query($sql);
}