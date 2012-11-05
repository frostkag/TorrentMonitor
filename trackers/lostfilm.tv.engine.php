<?php
class lostfilm
{
	protected static $sess_cookie;
	protected static $exucution;
	protected static $warning;
	
	protected static $page;	
	protected static $log_page;
	protected static $xml_page;

	//инициализируем класс
	public static function getInstance()
    {
        if ( ! isset(self::$instance))
        {
            $object = __CLASS__;
            self::$instance = new $object;
        }
        return self::$instance;
    }
    
	//получаем куки для доступа к сайту
	private static function login($login, $password)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.6; ru; rv:1.9.2.4) Gecko/20100611 Firefox/3.6.4");
		curl_setopt($ch, CURLOPT_HEADER, 1); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, "http://lostfilm.tv/useri.php");
		curl_setopt($ch, CURLOPT_POSTFIELDS, "FormLogin={$login}&FormPassword={$password}&module=1&repage=user&act=login");
		$result = curl_exec($ch);
		curl_close($ch);
		
		$result = iconv("windows-1251", "utf-8", $result);
		return $result;
	}
	
	//получаем страницу для парсинга
	private static function getPage($sess_cookie)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "http://lostfilm.tv/my.php");
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		$header[] = "Host: lostfilm.tv\r\n";
		$header[] = "Content-length: ".strlen($sess_cookie)."\r\n\r\n";
		curl_setopt($ch, CURLOPT_COOKIE, $sess_cookie);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		$result = curl_exec($ch);
		curl_close($ch);
			
		$result = iconv("windows-1251", "utf-8", $result);
		return $result;
	}	
	
	//получаем страницу для парсинга
	private static function getContent()
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.6; ru; rv:1.9.2.4) Gecko/20100611 Firefox/3.6.4");
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, "http://lostfilm.tv/rssdd.xml");
		$result = curl_exec($ch);
		curl_close($ch);
		
		return $result;
	}
	
	//получаем содержимое torrent файла
	private static function getTorrent($link, $sess_cookie, $where)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.6; ru; rv:1.9.2.4) Gecko/20100611 Firefox/3.6.4");
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, "{$link}");
		curl_setopt($ch, CURLOPT_COOKIE, $sess_cookie);
		$header[] = "Host: lostfilm.tv\r\n";
		$header[] = "Content-length: ".strlen($sess_cookie)."\r\n\r\n";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		$result = curl_exec($ch);
		curl_close($ch);
		
		file_put_contents($where, $result);
	}
	
	//функция проверки введёного названия
	public static function checkRule($data)
	{
		if (preg_match("/^[\.\+\sa-zA-Z0-9]+$/", $data))
			return TRUE;
		else
			return FALSE;
	}

	//функция преобразования даты из строки
	private static function dateStringToNum($data)
	{
		$data = substr($data, 5);
		$data = substr($data, 0, -6);
		
		$monthes = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
		$month = substr($data, 3, 3);
		$data = preg_replace("/(\d\d)-(\d\d)-(\d\d)/", "$3-$2-$1", str_replace($month, str_pad(array_search($month, $monthes)+1, 2, 0, STR_PAD_LEFT), $data));
		
		$data = preg_split("/\s/", $data);
		$date = $data[2].'-'.$data[1].'-'.$data[0].' '.$data[3];
		return $date;
	}
	
	//функция преобразования даты в строку
	private static function dateNumToString($data)
	{
		$data = substr($data, 0, -3);
		$data = preg_split("/\s/", $data);
		$time = $data[1];
		$data = $data[0];
		$data = preg_split("/\-/", $data);

		$monthes_num = array("/01/", "/02/", "/03/", "/04/", "/05/", "/06/", "/07/", "/08/", "/09/", "/10/", "/11/", "/12/");
		$monthes_ru = array("Янв", "Фев", "Мар", "Апр", "Мая", "Июн", "Июл", "Авг", "Сен", "Окт", "Ноя", "Дек");
		$month = preg_replace($monthes_num, $monthes_ru, $data[1]);
		$date = $data[2].' '.$month.' '.$data[0].' '.$time;
		return $date;
	}
	
	//функция анализа xml ленты
	private static function analysis($name, $hd, $item)
	{
		if (preg_match('/\b'.$name.'\b/i', $item->title))
		{
			if ($hd)
			{
				if (preg_match_all('/720p|HD/', $item->title, $matches))
				{
					preg_match('/\w\d{2}\.?\w\d{2}/', $item->link, $matches);
					$episode = $matches[0];
					$date = lostfilm::dateStringToNum($item->pubDate);
					return array('episode'=>$episode, 'date'=>$date, 'link'=>(string)$item->link);
				}
			}
			else
			{
				if (preg_match_all('/^(?!(.*720p|.*HD))/', $item->link, $matches))
				{
					preg_match('/\w\d{2}\.?\w\d{2}/', $item->link, $matches);
					$episode = $matches[0];
					$date = lostfilm::dateStringToNum($item->pubDate);
					return array('episode'=>$episode, 'date'=>$date, 'link'=>(string)$item->link);
				}
			}
		}
	}
	
	//основная функция
	public static function main($id, $tracker, $name, $hd, $ep, $timestamp)
	{
		//проверяем небыло ли до этого уже ошибок
		if (empty(lostfilm::$exucution) || (lostfilm::$exucution))
		{
			//проверяем получена ли уже кука
			if (empty(lostfilm::$sess_cookie))
			{
				//проверяем заполнены ли учётные данные
				if (Database::checkTrackersCredentialsExist($tracker))
				{
					//получаем учётные данные
					$credentials = Database::getCredentials($tracker);
					$login = $credentials['login'];
					$password = $credentials['password'];
					
					lostfilm::$page = lostfilm::login($login, $password);
					
					if ( ! empty(lostfilm::$page))
					{
						//проверяем подходят ли учётные данные
						if (preg_match_all("/Set-Cookie: (\w*)=(\S*)/", lostfilm::$page, $array))
						{
							lostfilm::$sess_cookie = $array[1][0]."=".$array[2][0]." ".$array[1][1]."=".$array[2][1];
							lostfilm::$page = lostfilm::getPage(lostfilm::$sess_cookie);
							preg_match("/<td align=\"left\">(.*)<br >/", lostfilm::$page, $out);
							lostfilm::$sess_cookie .= " usess=".$out[1];
							//запускам процесс выполнения, т.к. не может работать без кук
							lostfilm::$exucution = TRUE;
						}
						//проверяем нет ли сообщения о неправильном логине
						elseif (preg_match("/\D{24}\s\D{4}\s\D{12}/", lostfilm::$page, $out))
						{
							//устанавливаем варнинг
    						if (lostfilm::$warning == NULL)
                			{
                				lostfilm::$warning = TRUE;
                				Errors::setWarnings($tracker, 'credential_wrong');
                			}
							//останавливаем выполнение цепочки
							lostfilm::$exucution = FALSE;
						} 
						//проверяем нет ли сообщения о неправильном пароле
						elseif (preg_match("/recover\.php/", lostfilm::$page, $out)) 
						{
							//устанавливаем варнинг
							if (lostfilm::$warning == NULL)
                			{
                				lostfilm::$warning = TRUE;
                				Errors::setWarnings($tracker, 'credential_wrong');
                			}							//останавливаем выполнение цепочки
							lostfilm::$exucution = FALSE;
						}
						//если не удалось получить никаких данных со страницы, значит трекер не доступен
						else 
						{
							//устанавливаем варнинг
							if (lostfilm::$warning == NULL)
                			{
                				lostfilm::$warning = TRUE;
                				Errors::setWarnings($tracker, 'not_available');
                			}
							//останавливаем выполнение цепочки
							lostfilm::$exucution = FALSE;
						}
					}
					else
					{
						//устанавливаем варнинг
						if (lostfilm::$warning == NULL)
            			{
            				lostfilm::$warning = TRUE;
            				Errors::setWarnings($tracker, 'not_available');
            			}
						//останавливаем выполнение цепочки
						lostfilm::$exucution = FALSE;	
					}
				}
				else
				{
					//устанавливаем варнинг
    				if (lostfilm::$warning == NULL)
        			{
        				lostfilm::$warning = TRUE;
        				Errors::setWarnings($tracker, 'credential_miss');
        			}
					//останавливаем выполнение цепочки
					lostfilm::$exucution = FALSE;						
				}	
			}
	
			//проверяем получена ли уже RSS лента
			if ( ! lostfilm::$log_page)
			{
				if (lostfilm::$exucution)
				{
					//получаем страницу
					lostfilm::$page = lostfilm::getContent();
					if ( ! empty(lostfilm::$page))
					{
						//читаем xml
						lostfilm::$xml_page = @simplexml_load_string(lostfilm::$page);
						//если XML пришёл с ошибками - останавливаем выполнение, иначе - ставим флажок, что получаем страницу
						if ( ! lostfilm::$xml_page)
						{
							//устанавливаем варнинг
            				if (lostfilm::$warning == NULL)
                			{
                				lostfilm::$warning = TRUE;
                				Errors::setWarnings($tracker, 'rss_parse_false');
                			}
							//останавливаем выполнение цепочки
							lostfilm::$exucution = FALSE;
						}
						else
							lostfilm::$log_page = TRUE;
					}
					else
					{
						//устанавливаем варнинг
						if (lostfilm::$warning == NULL)
            			{
            				lostfilm::$warning = TRUE;
            				Errors::setWarnings($tracker, 'not_available');
            			}
						//останавливаем выполнение цепочки
						lostfilm::$exucution = FALSE;							
					}
				}
			}
			
			//если выполнение цепочки не остановлено
			if (lostfilm::$exucution)
			{
				if ( ! empty(lostfilm::$xml_page))
				{
					//сбрасываем варнинг
					Database::clearWarnings($tracker);
					$nodes = array();
					foreach (lostfilm::$xml_page->channel->item AS $item)
					{
					    array_unshift($nodes, $item);
					}
					
					foreach ($nodes as $item)
					{
						$serial = lostfilm::analysis($name, $hd, $item);
						if ( ! empty($serial))
						{
							$episode = substr($serial['episode'], 4, 2);
							$season = substr($serial['episode'], 1, 2);
							
							if ( ! empty($ep))
							{
								if ($season == substr($ep, 1, 2) && $episode > substr($ep, 4, 2))
									$download = TRUE;
								elseif ($season > substr($ep, 1, 2) && $episode < substr($ep, 4, 2))
									$download = TRUE;
								else
									$download = FALSE;
							}
							else
								$download = TRUE;

							if ($download)
							{
								$amp = ($hd) ? 'HD' : NULL;
								//сохраняем торрент в файл
								$path = Database::getSetting('path');
								$file = $path.'[lostfilm.tv]_'.$name.'_'.$serial['episode'].'_'.$amp.'.torrent';
								lostfilm::getTorrent($serial['link'], lostfilm::$sess_cookie, $file);
								//обновляем время регистрации торрента в базе
								Database::setNewDate($id, $serial['date']);
								//обновляем сведения о последнем эпизоде
								Database::setNewEpisode($id, $serial['episode']);
								$episode = (substr($episode, 0, 1) == 0) ? substr($episode, 1, 1) : $episode;
								$season = (substr($season, 0, 1) == 0) ? substr($season, 1, 1) : $season;
								//отправляем уведомлении о новом торренте
								$message = $name.' '.$amp.' обновлён до '.$episode.' серии, '.$season.' сезона.';
								Notification::sendNotification('notification', lostfilm::dateNumToString($serial['date']), $tracker, $message);
							}
						}
					}
				}
			}
		}
	}
}
?>