<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$pdo=db();$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$pdo||!$id){http_response_code(404);exit('Файл не найден.');}
$s=$pdo->prepare('SELECT tk.file_path,tk.original_name,tk.file_extension FROM Tickets tk JOIN Trips t ON t.trip_id=tk.trip_id WHERE tk.ticket_id=:id AND t.user_id=:user');
$s->execute(['id'=>$id,'user'=>currentUserId()]);$ticket=$s->fetch();
if(!$ticket){http_response_code(404);exit('Файл не найден.');}
$path=__DIR__.'/'.$ticket['file_path'];if(!is_file($path)){http_response_code(404);exit('Файл не найден.');}
$types=['pdf'=>'application/pdf','jpg'=>'image/jpeg','png'=>'image/png'];
$disposition=isset($_GET['download'])?'attachment':'inline';
header('Content-Type: '.($types[$ticket['file_extension']]??'application/octet-stream'));
header('Content-Length: '.filesize($path));
header("Content-Disposition: {$disposition}; filename*=UTF-8''".rawurlencode($ticket['original_name']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
