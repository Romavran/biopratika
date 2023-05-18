<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Заголовок страницы</title>
    <link rel="stylesheet" href="./styles/style.css">
  
    <meta property="og:title" content="Заголовок страницы в OG">
    <meta property="og:description" content="Описание страницы в OG">
    <meta property="og:image" content="https://example.com/image.jpg">
    <meta property="og:url" content="https://example.com/">
	<meta http-equiv="Refresh" content="60" />
	<style type='text/css'>
		table {
		  width: 100%;
		  scale: 100%;
		  margin-top: 20px;
		  margin-left: 0;
		  margin-bottom: 25px;
		  font-size: 18px;
		}
		td {
		  border-bottom: 1px solid #CDC1A7;
		  padding: 5px;
		  font-size: 18px;
		  height: 200px;
		}
		th {
		  padding: 5px;
		  border-bottom-width: 1px;
		  border-bottom-style: solid;
		  border-bottom-color: #CDC1A7;
		}
		
		input[type=submit] {
			background: white; 
			color: black; 
			padding: 0.5rem 1.0rem; 
			margin-bottom: 1rem; 
			border-radius: 12px;
			font-size: 12px;
		}
		
		* {
		  box-sizing: border-box;
		  font-size: 32px;
		}

		.col-3 {
		  height: 100px;
		  width: 33.333%; /* Можно задать любую другую ширину блока */
		  background: #80A6FF;
		  margin: 1em;
		  text-align: center;
		}

		.row {
			display: flex;
			flex-flow: row nowrap;
			align-items: center;
			align-content: center;
			justify-content: space-between;
		}
</style>
  </head>
  <body style="background-color: #3eb489; font-family:Arial, Verdana, sans-serif; margin-left:0;margin-right:0;">
	<div class="row">

<?php
//основной скрипт. 
function logging($name,$ilogold, $ilognew)
{
		global $logMessageA;
		/*записать в реактор logerror. олд/new. генерируем текстовое сообщение в лог. если есть повторяющиеся фрагменты, не пишем их. делаем split по br. удаляем все элементы, которые копируются. если есть старый фрагмент, который не дублируется */
		/*делаем сплит обеих строк по br*/
		$logMessage = '';
		$logold  = explode("<br>", $ilogold);
		$lognew = explode("<br>", $ilognew);
		/*сравниваем куски*/
		$result = array_diff( $lognew,$logold);
        $result2 = array_diff( $logold,$lognew);
		if (count($result)>0) {
			/*генерируем сообщение для лога*/
			$logMessage = implode(". ",$result);
		}
        if (count($result2)>0) {
			/*генерируем сообщение для лога*/
			$logMessage = $logMessage." Исправлено: ". implode(". ",$result2);
		}
		if (strlen($logMessage)>0) {
			$logMessage=$name." ".$logMessage."\n";
			$logMessageA = $logMessageA.' '.$logMessage;
		}

}

date_default_timezone_set('Europe/Moscow');

//читаем конфиг
$config_file = 'config_alarm.xml';
$config = simplexml_load_file($config_file);
$logMessageA = '';

foreach ($config->place as $place) {
	echo '<div class="col-3">';
	echo "<h1>".$place->name."</h1>";
	echo '<table>';
	foreach ($place->bioreactor as $bioreactor) {
		
		echo "<tr>";
		echo "<td>".$bioreactor->observer."</td>";
		if ($bioreactor->inwork == "yes") {
			$link = pg_connect("host=".$bioreactor->ip." port=5432 dbname=".$bioreactor->dbname." user=".$bioreactor->log." password=".$bioreactor->pass)
			or die('Не удалось соединиться: ' . pg_last_error());

			$query1="";
			$problem=array();
			$parameter=array();
			$problem1=array();
			$level=array();
			$wash=array();

			foreach ($bioreactor->fields->field as $field) {
				if ($field['class'] =="datetime") {
					$datetime = $field->inSQL;
				}
				if (($field['class'] =="problem")){
					if (strlen($query1)>0) {
						$query1 = $query1.", ".$field->inSQL;	
					} else {
						$query1 = $field->inSQL;	
					}
					$problem[]=$field;
				}
				if (($field['class'] =="wash")){
					if (strlen($query1)>0) {
						$query1 = $query1.", ".$field->inSQL;	
					} else {
						$query1 = $field->inSQL;	
					}
					$wash[]=$field;
				}
				if ($field['class'] =="Pump") {
					if (strlen($query1)>0) {
						$query1 = $query1.", ".$field->inSQL;	
					} else {
						$query1 = $field->inSQL;	
					}
					$parameter[]=$field;
				}
				if (($field['class'] =="problem1")){
					if (strlen($query1)>0) {
						$query1 = $query1.", ".$field->inSQL;
						$query1 = $query1.", ".$field->inSQLset;					
					} else {
						$query1 = $field->inSQL;
						$query1 = $query1.", ".$field->inSQLset;
					}
					$problem1[]=$field;
				}
				if ($field['class'] =="level") {
					if (strlen($query1)>0) {
						$query1 = $query1.", ".$field->inSQL;	
					} else {
						$query1 = $field->inSQL;	
					}
					$level[]=$field;
				}
			}

			$sql1 = 'SELECT '.$query1.' FROM public.'.$bioreactor->tbname.' ORDER BY '.$datetime.' DESC LIMIT 1';
			$result1 = pg_query($link, $sql1);
			$row = pg_fetch_array($result1);
			//$sql2 = "SELECT ".$query2." FROM public.".$bioreactor->tbname." WHERE ".$datetime.">'".date("Y-m-d H:i:s",strtotime("-1 hours", time()))."' AND ".$datetime."<'".date("Y-m-d H:i:s")."' ORDER by ".$datetime;
			//$result2 = pg_query($link, $sql2);
			$signal = "green";
			$message = "";
			$numgreen = 0;
			$numyellow = 0;
			$numred = 0;
			$numgrey = 0;
			$circpump = 0;
			
			foreach ($bioreactor->fields->field as $field) {
				if ($field['class'] =="Pump") {
					$circpump = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
				}
				if (($field['class'] =="problem1") and ($field->id == "Temperature")) {
					$temp = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQLset)]));
				}
			}

			if ((($circpump > 20) and ($temp<50)) or ($bioreactor->id == 3) or ($bioreactor->id == 80)) {
				foreach ($problem as $key=>$field) {
					$val=  floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
					if (($val<($field->min)) or ($val>($field->max))) {
						//светофор
						if ($field->signal == "yellow") {
							$numyellow = $numyellow + 1;
						}
						if ($field->signal == "red") {
							$numred = $numred + 1;
						}
						//генерируем сообщение
						$message = $message."<br>".(string)$field->message;
					}
				}
				
				foreach ($problem1 as $key=>$field) {
					$val=  floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
					$valset=  floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQLset)]));
					$valalarm=  $field->inSQLalarm;
					if (abs($val - $valset) > ($field->delta)) {
						if ($val > $valalarm) {
							$numred = $numred + 1;
							$message = $message."<br>".(string)$field->message.". АВАРИЙНОЕ ПРЕВЫШЕНИЕ!";
						} else {
							if ($field->signal == "yellow") {
								$numyellow = $numyellow + 1;
							}
							if ($field->signal == "red") {
								$numred = $numred + 1;
							}
							//генерируем сообщение
							$message = $message."<br>".(string)$field->message;
						}
					}
				}
				
				foreach ($level as $key=>$field) {
					$val=  floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
					if ($val<($field->min)) {
						//светофор
						if ($field->signal == "yellow") {
							$numyellow = $numyellow + 1;
						}
						if ($field->signal == "red") {
							$numred = $numred + 1;
						}
						//генерируем сообщение
						$message = $message."<br>".(string)$field->messagemin;
					}
					if ($val>($field->max)) {
						//светофор
						if ($field->signal == "yellow") {
							$numyellow = $numyellow + 1;
						}
						if ($field->signal == "red") {
							$numred = $numred + 1;
						}
						//генерируем сообщение
						$message = $message."<br>".(string)$field->messagemax;
					}
				}
				
				//газы
				//указания на бочки (недостаток - залив, переполнение - отключение)
				
				if ($numred > 0) {
					$signal = "red";
				} else {
					if ($numyellow > 0) {
						$signal = "yellow";
					} else {
						if ($numgrey > 0){
							$signal = "grey";
						} else {
							$signal = "green";
						}
					}
				}
			
			} else if ($circpump<20) {
				//добавить аварийные условия
				$signal = "grey";
				$message = $message."<br>"."Насос не работает. Если по статусу работы реактора он запущен, АВАРИЯ";
			} else {
				//это мойка; добавить условия на мойку (давление должно быть ниже аварийного, уровень в сепараторе должен быть выше уставки)
				foreach ($problem1 as $key=>$field) {
					$val=  floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
					$valset=  floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQLset)]));
					$valalarm=  $field->inSQLalarm;
					if ($val > $valalarm) {
						$numred = $numred + 1;
						$message = $message."<br>".(string)$field->message.". АВАРИЙНОЕ ПРЕВЫШЕНИЕ! (при мойке)";
					}
				}
				foreach ($wash as $key=>$field) {
					$val=  floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
					if (($val<($field->min)) or ($val>($field->max))) {
						//светофор
						if ($field->signal == "yellow") {
							$numyellow = $numyellow + 1;
						}
						if ($field->signal == "red") {
							$numred = $numred + 1;
						}
						//генерируем сообщение
						$message = $message."<br>".(string)$field->message;
					}
				}
				if ($numred > 0) {
					$signal = "red";
				} else {
					if ($numyellow > 0) {
						$signal = "yellow";
					} else {
							$signal = "cyan";
							$message = $message."<br>"."Проводится мойка";
					}
				}
				
			}			
		} else {
			//Отключен
			$signal = "grey";
			$message = $message."<br>"."Реактор отключен (кнопкой)";
		}	
		
//		echo '<td>
//			<svg height="100" width="100">
//			   <circle cx="50" cy="50" r="40" stroke="black" stroke-width="3" fill="'.$signal.'" />
//			</svg>
//		</td>';
		
//		echo '<td>
//		 <form method="post">
//			<input type="submit" name="'.$bioreactor->name.'"
//					value="Ферментер в работе"/>
//					
//		 </form>
//		</td>';
			
		echo '<td>			
			<form method="post"> 
				<input type="submit" name="'.$bioreactor->name.'"
				style="background-color: '.$signal.'"
				value="Ферментер в работе"/>
			</form>
		</td>';
		
		echo '<td>'.$message.'</td>';
		if(isset($_POST[(string)$bioreactor->name])) {
			//записать в конфиг изменение статуса
			//перезаписываем конфиг, в котором уже название колонки сопоставлено со столбцем
			if ($bioreactor->inwork == "yes") {
				$bioreactor->inwork = "no";
			} else {
				$bioreactor->inwork = "yes";
			}
			$xmlPlain = $config->asXML();
			file_put_contents($config_file, $xmlPlain );
			header("Refresh: 0");
		}
		echo "</tr>";
		
		logging($bioreactor->observer,$bioreactor->lognew, $message);
		$bioreactor->logold = $bioreactor->lognew; 
		$bioreactor->lognew = $message;
		
		$signal = "green";
		
		
		$message = "";
		$numgreen = 0;
		$numyellow = 0;
		$numred = 0;
		$numgrey = 0;
		$circpump = 0;
	}

	foreach ($place->dryer as $dryer) {
		
		echo "<tr>";
		echo "<td>".$dryer->observer."</td>";
		if ($dryer->inwork == "yes") {
			$link = pg_connect("host=".$dryer->ip." port=5432 dbname=".$dryer->dbname." user=".$dryer->log." password=".$dryer->pass)
			or die('Не удалось соединиться: ' . pg_last_error());

			$query1="";
			$problem=array();
			$parameter=array();

			foreach ($dryer->fields->field as $field) {
				if ($field['class'] =="datetime") {
					$datetime = $field->inSQL;
				}
				if ($field['class'] =="problem") {
					if (strlen($query1)>0) {
						$query1 = $query1.", ".$field->inSQL;	
					} else {
						$query1 = $field->inSQL;	
					}
					$problem[]=$field;
				}
				if ($field['class'] =="dryer_parameter") {
					if (strlen($query1)>0) {
						$query1 = $query1.", ".$field->inSQL;	
					} else {
						$query1 = $field->inSQL;	
					}
					$parameter[]=$field;
				}
			}

			$sql1 = 'SELECT '.$query1.' FROM public.'.$dryer->tbname.' ORDER BY '.$datetime.' DESC LIMIT 1';
			$result1 = pg_query($link, $sql1);
			$row = pg_fetch_array($result1);
			$signal = "green";
			$message = "";
			$numgreen = 0;
			$numyellow = 0;
			$numred = 0;
			$numgrey = 0;
			foreach ($problem as $key=>$field) {
				$val=  floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
				if (($val<($field->min)) or ($val>($field->max))) {
					//светофор
					if ($field->signal == "yellow") {
						$numyellow = $numyellow + 1;
					}
					if ($field->signal == "red") {
						$numred = $numred + 1;
					}
					//генерируем сообщение
					$message = $message."<br>".(string)$field->message;
				}
			}
			
			foreach ($parameter as $key=>$field) {
				if ($field->id == "PDS2") {
					$pds2 = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
				} else if ($field->id == "TE2") {
					$te2 = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
				} else if ($field->id == "TE3"){
					$te3 = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
				} else if ($field->id == "TE4"){
					$te4 = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
				}
			}

			if ($pds2 > 1000) {
				if ($te3 > 135) {
					//Перегрев
					$numred = $numred + 1;
					$message = $message."<br>"."Перегрев TE3";
				}
				if ((($pds2>2800) and (te2>140)) or ($te4>$te3)) {
					//Засор
					$numred = $numred + 1;
					$message = $message."<br>"."Засор/завал башни";
				}
			} else if ($te3>100) {
				//Отключение электричества - вентилятор не работает (давление маленькое), а температура есть
				$numred = $numred + 1;
				$message = $message."<br>"."Отключение электричества";
			} else {
				$numgrey = $numgrey + 1;
				$message = $message."<br>"."Сушка отключена";
			}
			
			if ($numred > 0) {
				$signal = "red";
			} else {
				if ($numyellow > 0) {
					$signal = "yellow";
				} else {
					if ($numgrey > 0){
						$signal = "grey";
					} else {
						$signal = "green";
					}
				}
			}
		} else {
			$signal = "grey";
			$message = $message."<br>"."Сушка отключена (кнопкой)";
		}
		
//		echo '<td>
//			<svg height="100" width="100">
//			   <circle cx="50" cy="50" r="40" stroke="black" stroke-width="3" fill="'.$signal.'" />
//			</svg>
//		</td>';
		
		echo '<td>			
			<form method="post"> 
				<input type="submit" name="'.$dryer->name.'"
				style="background-color: '.$signal.'"
				value="Сушка в работе"/>
			</form>
		</td>';
		
		echo '<td>'.$message.'</td>';
		if(isset($_POST[(string)$dryer->name])) {
			//записать в конфиг изменение статуса
			//перезаписываем конфиг, в котором уже название колонки сопоставлено со столбцем
			if ($dryer->inwork == "yes") {
				$dryer->inwork = "no";
			} else {
				$dryer->inwork = "yes";
			}
			$xmlPlain = $config->asXML();
			file_put_contents($config_file, $xmlPlain );
			header("Refresh: 0");
		}
		echo "</tr>";
		
		logging($dryer->observer,$dryer->lognew, $message);
		$dryer->logold = $dryer->lognew; 
		$dryer->lognew = $message;
	}

	foreach ($place->irfourier as $irfourier) {
		
		echo "<tr>";
		echo "<td>".$irfourier->observer."</td>";
		$message = "";
		
		foreach ($place->bioreactor as $clara) {
			if ($clara->name == "Clara") {
				if ($clara->inwork == "no") {
					$irfourier->inwork = "no";
				}
			}
		}
		
		if ($irfourier->inwork == "yes") {
			$link = pg_connect("host=".$irfourier->ip." port=5432 dbname=".$irfourier->dbname." user=".$irfourier->log." password=".$irfourier->pass)
			or die('Не удалось соединиться: ' . pg_last_error());

			$query1="";
			$parameter=array();

			foreach ($irfourier->fields->field as $field) {
				if ($field['class'] =="datetime") {
					$datetime = $field->inSQL;
				}
				if ($field['class'] =="parameter") {
					if (strlen($query1)>0) {
						$query1 = $query1.", ".$field->inSQL;	
					} else {
						$query1 = $field->inSQL;	
					}
					$parameter[]=$field;
				}
			}

			$sql1 = 'SELECT '.$query1.' FROM public.'.$irfourier->tbname.' ORDER BY '.$datetime.' DESC LIMIT 2';
			$result1 = pg_query($link, $sql1);
			$row = pg_fetch_array($result1,0);
			$row2 = pg_fetch_array($result1,1);
			$signal = "green";
			$message = "";
			$numgreen = 0;
			$numyellow = 0;
			$numred = 0;
			$numgrey = 0;
			//print_r($row);
			//print_r($row2);
			
			
			foreach ($parameter as $key=>$field) {
				if ($field->id == "PO4") {
					$po4 = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
					$po4old = floatval(str_replace(',','.',$row2[str_replace('"','',(string)$field->inSQL)]));
				} else if ($field->id == "SO4") {
					$so4 = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
					$so4old = floatval(str_replace(',','.',$row2[str_replace('"','',(string)$field->inSQL)]));
				} else if ($field->id == "NH4"){
					$nh4 = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
					$nh4old = floatval(str_replace(',','.',$row2[str_replace('"','',(string)$field->inSQL)]));
				} else if ($field->id == "NO3"){
					$no3 = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
					$no3old = floatval(str_replace(',','.',$row2[str_replace('"','',(string)$field->inSQL)]));
				} else if ($field->id == "CO3"){
					$co3 = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
					$co3old = floatval(str_replace(',','.',$row2[str_replace('"','',(string)$field->inSQL)]));
				} else if ($field->id == "CH3COO"){
					$ch3coo = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
					$ch3cooold = floatval(str_replace(',','.',$row2[str_replace('"','',(string)$field->inSQL)]));
				} else if ($field->id == "datetime"){
					$date = floatval(str_replace(',','.',$row[str_replace('"','',(string)$field->inSQL)]));
				}
			}
			$time = strtotime($date);
			//$message = $message."<br>".$time;
			//$message = $message."<br>".$po4;
			//$message = $message."<br>".$po4old;
			
			
			if (($time - time())/60. < 20) {
				//Прекратил писать
				$numred = $numred + 1;
				$message = $message."<br>"."Прекратил писать. Перезагрузить чёрную программу";
			} else if (abs($po4*$so4*$nh4*$no3*$co3*$ch3coo) <0.01) {
				//Выводятся нули
				$numred = $numred + 1;
				$message = $message."<br>"."Выводятся нули";
			} else if (($po4 == $po4old) or ($so4 == $so4old) or ($nh4 == $nh4old) or ($no3 == $no3old) or ($co3 == $co3old) or ($ch3coo == $ch3cooold)) {
				//Повтор значений с предыдущими
				$numred = $numred + 1;
				$message = $message."<br>"."Выводятся повторяющиеся показания. Перезагрузить серую программу";
			} else if (($po4<-50) or ($so4<-50) or ($nh4<-50) or ($no3<-50) or ($co3<-50) or ($ch3coo<-50)) {
				$numred = $numred + 1;
				$message = $message."<br>"."Неверные отрицательные значения. Написать Я.В.";
			} //В конце else if на уставку
					
			if ($numred > 0) {
				$signal = "red";
			} else {
				if ($numyellow > 0) {
					$signal = "yellow";
				} else {
					if ($numgrey > 0){
						$signal = "grey";
					} else {
						$signal = "green";
					}
				}
			}
		} else {
			$signal = "grey";
			if ($clara->inwork == "no") {
				$message = $message."<br>"."ИК Фурье отключен (не работает Клара)";
			} else {
				$message = $message."<br>"."ИК Фурье отключен (кнопкой)";
			}
		}
		
//		echo '<td>
//			<svg height="100" width="100">
//			   <circle cx="50" cy="50" r="40" stroke="black" stroke-width="3" fill="'.$signal.'" />
//			</svg>
//		</td>';
		
		echo '<td>			
			<form method="post"> 
				<input type="submit" name="'.$irfourier->name.'"
				style="background-color: '.$signal.'"
				value="ИК Фурье в работе"/>
			</form>
		</td>';
		
		echo '<td>'.$message.'</td>';
		if(isset($_POST[(string)$irfourier->name])) {
			//записать в конфиг изменение статуса
			//перезаписываем конфиг, в котором уже название колонки сопоставлено со столбцем
			if ($irfourier->inwork == "yes") {
				$irfourier->inwork = "no";
			} else {
				$irfourier->inwork = "yes";
			}
			$xmlPlain = $config->asXML();
			file_put_contents($config_file, $xmlPlain );
			header("Refresh: 0");
		}
		echo "</tr>";

		logging($irfourier->observer,$irfourier->lognew, $message);
		$irfourier->logold = $irfourier->lognew; 
		$irfourier->lognew = $message;

		
		
	}
	
	echo '</table>';
	echo('</div>');
	
	
	
}
	
$xmlPlain = $config->asXML();
file_put_contents($config_file, $xmlPlain );
	
if (strlen($logMessageA)>0) {
	$logMessageA = date("Y-m-d H:i:s").' '.$logMessageA."\n";
	file_put_contents('logs.txt', $logMessageA, FILE_APPEND | LOCK_EX);
}
	
?>

</div>
 </body>
    </main>
    
  </body>
</html>