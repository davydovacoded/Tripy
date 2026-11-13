<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'||!verifyCsrf($_POST['csrf_token']??null)){http_response_code(403);exit('Запрос отклонён.');}
$pdo=db();$tripId=filter_var($_POST['trip_id']??0,FILTER_VALIDATE_INT);if(!$pdo||!$tripId)redirectWithMessage('../trips.php','Не удалось удалить путешествие.','error');
$paths=[];$s=$pdo->prepare('SELECT cover_path AS path FROM Trips WHERE trip_id=:id AND user_id=:user UNION ALL SELECT tk.file_path FROM Tickets tk JOIN Trips t ON t.trip_id=tk.trip_id WHERE t.trip_id=:id2 AND t.user_id=:user2 UNION ALL SELECT ph.file_path FROM Photos ph JOIN Trips t ON t.trip_id=ph.trip_id WHERE t.trip_id=:id3 AND t.user_id=:user3');$s->execute(['id'=>$tripId,'user'=>currentUserId(),'id2'=>$tripId,'user2'=>currentUserId(),'id3'=>$tripId,'user3'=>currentUserId()]);$paths=array_filter(array_column($s->fetchAll(),'path'));
$delete=$pdo->prepare('DELETE FROM Trips WHERE trip_id=:id AND user_id=:user');$delete->execute(['id'=>$tripId,'user'=>currentUserId()]);if(!$delete->rowCount())redirectWithMessage('../trips.php','Путешествие не найдено.','error');
foreach($paths as $path){$full=dirname(__DIR__).'/'.$path;if(is_file($full))@unlink($full);}redirectWithMessage('../trips.php','Путешествие удалено.');
