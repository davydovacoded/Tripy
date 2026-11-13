<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
function photoResponse(array $data,int $status=200):void{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'||!verifyCsrf($_POST['csrf_token']??null))photoResponse(['ok'=>false,'message'=>'Сессия устарела.'],403);
$pdo=db();if(!$pdo)photoResponse(['ok'=>false,'message'=>'Подключение к MySQL не настроено.'],503);
$tripId=filter_var($_POST['tripId']??0,FILTER_VALIDATE_INT);$s=$pdo->prepare('SELECT 1 FROM Trips WHERE trip_id=:id AND user_id=:user');$s->execute(['id'=>$tripId,'user'=>currentUserId()]);if(!$s->fetchColumn())photoResponse(['ok'=>false,'message'=>'Путешествие не найдено.'],404);
if(!isset($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK||$_FILES['file']['size']>10*1024*1024)photoResponse(['ok'=>false,'message'=>'Выберите фотографию до 10 МБ.'],422);
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['file']['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($allowed[$mime]))photoResponse(['ok'=>false,'message'=>'Разрешены JPG, PNG и WebP.'],422);
$dir=dirname(__DIR__).'/uploads/photos';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))photoResponse(['ok'=>false,'message'=>'Не удалось подготовить хранилище.'],500);
$name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];if(!move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$name))photoResponse(['ok'=>false,'message'=>'Не удалось сохранить фотографию.'],500);
$path='uploads/photos/'.$name;$caption=trim((string)($_POST['caption']??''))?:null;$date=!empty($_POST['photoDate'])?(string)$_POST['photoDate']:null;$description=trim((string)($_POST['description']??''))?:null;
if($date!==null){$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date){@unlink($dir.'/'.$name);photoResponse(['ok'=>false,'message'=>'Укажите корректную дату фотографии.'],422);}}
try{$statement=$pdo->prepare('INSERT INTO Photos (trip_id,file_path,caption,photo_date,description) VALUES (:trip,:path,:caption,:date,:description)');$statement->execute(['trip'=>$tripId,'path'=>$path,'caption'=>$caption,'date'=>$date,'description'=>$description]);}catch(Throwable $error){@unlink($dir.'/'.$name);error_log('Tripy photo save failed: '.$error->getMessage());photoResponse(['ok'=>false,'message'=>'Не удалось сохранить фотографию.'],500);}
photoResponse(['ok'=>true,'photo'=>['id'=>(int)$pdo->lastInsertId(),'filePath'=>$path,'caption'=>$caption,'date'=>$date,'description'=>$description]]);
