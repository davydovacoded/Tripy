<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
function respond(array $payload, int $status = 200): void { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
function ownedTrip(PDO $pdo, int $tripId): bool { $s=$pdo->prepare('SELECT 1 FROM Trips WHERE trip_id=:id AND user_id=:user'); $s->execute(['id'=>$tripId,'user'=>currentUserId()]); return (bool)$s->fetchColumn(); }
function booleanValue(mixed $value): int { return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0; }
function ticketCategory(string $type): string { return in_array($type,['Самолёт','Поезд','Автобус'],true) ? 'Транспорт' : ($type === 'Музей или мероприятие' ? 'Развлечения' : 'Другое'); }
function validOptionalDate(mixed $value): string|false|null { $raw=trim((string)$value);if($raw==='')return null;$date=DateTimeImmutable::createFromFormat('!Y-m-d',$raw);return $date&&$date->format('Y-m-d')===$raw?$raw:false; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['ok'=>false,'message'=>'Метод не поддерживается.'],405);
$pdo=db(); if(!$pdo) respond(['ok'=>false,'message'=>'Подключение к MySQL не настроено.'],503);
$data=$_POST;
if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) $data=json_decode(file_get_contents('php://input'),true) ?: [];
if(!verifyCsrf($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) respond(['ok'=>false,'message'=>'Сессия устарела. Обновите страницу.'],403);
$action=(string)($data['action']??''); $tripId=(int)($data['trip_id']??0);
if(!$tripId || !ownedTrip($pdo,$tripId)) respond(['ok'=>false,'message'=>'Путешествие не найдено.'],404);

try {
 $uploadedFile=null;
 if($action==='place_save'){
  $id=(int)($data['id']??0); $name=trim((string)($data['name']??'')); if($name==='') respond(['ok'=>false,'message'=>'Введите название места.'],422);
  $values=['trip'=>$tripId,'name'=>$name,'category'=>trim((string)($data['category']??''))?:null,'address'=>trim((string)($data['address']??''))?:null,'note'=>trim((string)($data['note']??''))?:null,'status'=>booleanValue($data['visited']??false)?'visited':'planned'];
  if($id){$s=$pdo->prepare('UPDATE Places SET place_name=:name,category=:category,address=:address,notes=:note,status=:status WHERE place_id=:id AND trip_id=:trip');$s->execute($values+['id'=>$id]);}
  else{$s=$pdo->prepare('INSERT INTO Places (trip_id,place_name,category,address,notes,status) VALUES (:trip,:name,:category,:address,:note,:status)');$s->execute($values);$id=(int)$pdo->lastInsertId();}
  respond(['ok'=>true,'id'=>$id]);
 }
 if($action==='place_toggle'){$id=(int)($data['id']??0);$s=$pdo->prepare("UPDATE Places SET status=IF(status='visited','planned','visited') WHERE place_id=:id AND trip_id=:trip");$s->execute(['id'=>$id,'trip'=>$tripId]);respond(['ok'=>true]);}
 if($action==='place_delete'){$s=$pdo->prepare('DELETE FROM Places WHERE place_id=:id AND trip_id=:trip');$s->execute(['id'=>(int)$data['id'],'trip'=>$tripId]);respond(['ok'=>true]);}

 if($action==='expense_save'){
  $id=(int)($data['id']??0);$name=trim((string)($data['name']??''))?:'Расход';$amount=filter_var($data['amount']??null,FILTER_VALIDATE_FLOAT);
  if($amount===false||$amount<=0) respond(['ok'=>false,'message'=>'Укажите положительную сумму.'],422);
  $category=trim((string)($data['category']??''))?:'Другое';$expenseDate=validOptionalDate($data['date']??'');if($expenseDate===false)respond(['ok'=>false,'message'=>'Укажите корректную дату расхода.'],422);
  $values=['trip'=>$tripId,'name'=>$name,'amount'=>$amount,'category'=>$category,'date'=>$expenseDate?:date('Y-m-d'),'note'=>trim((string)($data['note']??''))?:null];
  if($id){$s=$pdo->prepare('UPDATE Expenses SET title=:name,amount=:amount,category=:category,expense_date=:date,notes=:note WHERE expense_id=:id AND trip_id=:trip AND ticket_id IS NULL');$s->execute($values+['id'=>$id]);}
  else{$s=$pdo->prepare('INSERT INTO Expenses (trip_id,title,amount,category,expense_date,notes) VALUES (:trip,:name,:amount,:category,:date,:note)');$s->execute($values);$id=(int)$pdo->lastInsertId();}
  respond(['ok'=>true,'id'=>$id,'date'=>$values['date']]);
 }
 if($action==='expense_delete'){$s=$pdo->prepare('DELETE FROM Expenses WHERE expense_id=:id AND trip_id=:trip AND ticket_id IS NULL');$s->execute(['id'=>(int)$data['id'],'trip'=>$tripId]);respond(['ok'=>true]);}

 if($action==='trip_budget'){
  $raw=trim((string)($data['budget']??''));$budget=$raw===''?null:filter_var($raw,FILTER_VALIDATE_FLOAT);
  if($raw!==''&&($budget===false||$budget<0))respond(['ok'=>false,'message'=>'Бюджет должен быть положительным числом или пустым.'],422);
  $s=$pdo->prepare('UPDATE Trips SET budget_limit=:budget WHERE trip_id=:trip AND user_id=:user');
  $s->execute(['budget'=>$budget,'trip'=>$tripId,'user'=>currentUserId()]);
  respond(['ok'=>true,'budget'=>$budget]);
 }

 if($action==='photo_delete'){
  $id=(int)($data['id']??0);$s=$pdo->prepare('SELECT file_path FROM Photos WHERE photo_id=:id AND trip_id=:trip');$s->execute(['id'=>$id,'trip'=>$tripId]);$path=$s->fetchColumn();
  if(!$path)respond(['ok'=>false,'message'=>'Фотография не найдена.'],404);
  $d=$pdo->prepare('DELETE FROM Photos WHERE photo_id=:id AND trip_id=:trip');$d->execute(['id'=>$id,'trip'=>$tripId]);
  $full=dirname(__DIR__).'/'.$path;if(is_file($full))@unlink($full);
  respond(['ok'=>true]);
 }

 if($action==='ticket_pin'){$s=$pdo->prepare('UPDATE Tickets SET is_pinned=1-is_pinned WHERE ticket_id=:id AND trip_id=:trip');$s->execute(['id'=>(int)$data['id'],'trip'=>$tripId]);respond(['ok'=>true]);}
 if($action==='ticket_delete'){$pdo->beginTransaction();$s=$pdo->prepare('SELECT file_path FROM Tickets WHERE ticket_id=:id AND trip_id=:trip FOR UPDATE');$s->execute(['id'=>(int)$data['id'],'trip'=>$tripId]);$path=$s->fetchColumn();$d=$pdo->prepare('DELETE FROM Tickets WHERE ticket_id=:id AND trip_id=:trip');$d->execute(['id'=>(int)$data['id'],'trip'=>$tripId]);$pdo->commit();if($path){$full=dirname(__DIR__).'/'.$path;if(is_file($full))@unlink($full);}respond(['ok'=>true]);}
 if($action==='ticket_save'){
  $id=(int)($data['id']??0);$name=trim((string)($data['name']??''));$type=trim((string)($data['type']??'Другое'));$costRaw=trim((string)($data['cost']??''));$cost=$costRaw===''?null:filter_var($costRaw,FILTER_VALIDATE_FLOAT);$include=booleanValue($data['includeExpense']??false);$ticketDate=validOptionalDate($data['date']??'');
  if($ticketDate===false)respond(['ok'=>false,'message'=>'Укажите корректную дату билета.'],422);
  if($name===''||($costRaw!==''&&($cost===false||$cost<=0))||($include&&!$cost)) respond(['ok'=>false,'message'=>'Проверьте название и стоимость билета.'],422);
  $old=null;if($id){$s=$pdo->prepare('SELECT * FROM Tickets WHERE ticket_id=:id AND trip_id=:trip');$s->execute(['id'=>$id,'trip'=>$tripId]);$old=$s->fetch();if(!$old)respond(['ok'=>false,'message'=>'Билет не найден.'],404);}
  $previousPath=$old['file_path']??null;$path=$previousPath;$extension=$old['file_extension']??null;$original=$old['original_name']??null;
  if(isset($_FILES['file'])&&$_FILES['file']['error']!==UPLOAD_ERR_NO_FILE){
   if($_FILES['file']['error']!==UPLOAD_ERR_OK||$_FILES['file']['size']>10*1024*1024)respond(['ok'=>false,'message'=>'Файл не загружен или больше 10 МБ.'],422);
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['file']['tmp_name']);$allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];if(!isset($allowed[$mime]))respond(['ok'=>false,'message'=>'Разрешены PDF, JPG и PNG.'],422);
   $dir=dirname(__DIR__).'/uploads/tickets';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))respond(['ok'=>false,'message'=>'Не удалось подготовить хранилище.'],500);
   $filename=bin2hex(random_bytes(16)).'.'.$allowed[$mime];if(!move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$filename))respond(['ok'=>false,'message'=>'Не удалось сохранить файл.'],500);$uploadedFile=$dir.'/'.$filename;
   $path='uploads/tickets/'.$filename;$extension=$allowed[$mime];$original=basename($_FILES['file']['name']);
  }
  if(!$id&&!$path)respond(['ok'=>false,'message'=>'Выберите файл билета.'],422);
  $pdo->beginTransaction();
  $values=['trip'=>$tripId,'name'=>$name,'type'=>$type,'date'=>$ticketDate,'path'=>$path,'ext'=>$extension,'original'=>$original,'note'=>trim((string)($data['note']??''))?:null,'pinned'=>booleanValue($data['pinned']??false),'cost'=>$cost,'include'=>$include];
  if($id){$s=$pdo->prepare('UPDATE Tickets SET title=:name,ticket_type=:type,use_date=:date,file_path=:path,file_extension=:ext,original_name=:original,notes=:note,is_pinned=:pinned,cost=:cost,include_in_expenses=:include WHERE ticket_id=:id AND trip_id=:trip');$s->execute($values+['id'=>$id]);}
  else{$s=$pdo->prepare('INSERT INTO Tickets (trip_id,title,ticket_type,use_date,file_path,file_extension,original_name,notes,is_pinned,cost,include_in_expenses) VALUES (:trip,:name,:type,:date,:path,:ext,:original,:note,:pinned,:cost,:include)');$s->execute($values);$id=(int)$pdo->lastInsertId();}
  if($include){$s=$pdo->prepare('INSERT INTO Expenses (trip_id,ticket_id,title,amount,category,expense_date,notes) VALUES (:trip,:ticket,:name,:amount,:category,CURRENT_DATE,:note) ON DUPLICATE KEY UPDATE title=VALUES(title),amount=VALUES(amount),category=VALUES(category),notes=VALUES(notes)');$s->execute(['trip'=>$tripId,'ticket'=>$id,'name'=>$name,'amount'=>$cost,'category'=>ticketCategory($type),'note'=>'Стоимость связанного билета']);}
  else{$s=$pdo->prepare('DELETE FROM Expenses WHERE trip_id=:trip AND ticket_id=:ticket');$s->execute(['trip'=>$tripId,'ticket'=>$id]);}
  $pdo->commit();if($previousPath&&$previousPath!==$path){$oldFile=dirname(__DIR__).'/'.$previousPath;if(is_file($oldFile))@unlink($oldFile);}respond(['ok'=>true,'id'=>$id,'format'=>strtoupper((string)$extension),'filePath'=>$path]);
 }
 respond(['ok'=>false,'message'=>'Неизвестное действие.'],400);
} catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();if(isset($uploadedFile)&&$uploadedFile&&is_file($uploadedFile))@unlink($uploadedFile);error_log('Tripy action failed: '.$error->getMessage());respond(['ok'=>false,'message'=>'Не удалось сохранить изменения.'],500);}
