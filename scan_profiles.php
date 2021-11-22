<?php


/* Анализатор профилей v1.0 / 22.11.21

Уберите детей и беременных женщин от монитора. Ниже говнокод на коленке.


Парсит данные в каждом профиле т.е всего 2 файла:
- fingerprint.json
- Cookies 

Зачем?
Чтоб всякое говно не попадало. В моем случае стоит платный fingerprint онли мобилы.
Из-за анализатора я узнал:
- бывают шлются боты аля PetalBot;+https://webmaster.petalsearch.com/site/petalbot
- неопознаный дейвай, ос
- язык профиля вообще не РУ

Описание того что парсит:

	folder - папка профиля
	device - девайс пк/мобилка/планш
	os - операционка
	browser - браузер
	lang - язык
	user_agent - кк
	view_width - экран
	view_height - экран
	cooki_count - количество кук в профиле
	cooki_count_host_key - количество уник хостов в куках
	cooki_unique_count_host - количество уник хостов по второму уровню (лучше ориентироватся на этот параметр)
	cooki_yandex - Есть ли куки яшки
	cooki_yandex_metrika - есть ли куки метрики
	cooki_yandex_ad - куки яд рекламы?
	cooki_google - есть ли куки гугла

На основе этих данных можете мониторить и удалять левые профили.

Для запуска нужен пых 5.6+ (делалось на 7.4).
Указываем диру и запускаем через консоль, фаил с полным отчетом появится в папке со скриптом.


*/


$profile_dir = "C:/WebVisitor/Profile/NEW_0/"; // папка с профилями




$data_profile = parse_profile();
echo profile_stat_report($data_profile); // просто стата
echo csv_export($data_profile); // экспорт данных в csv





function profile_stat_report($data_profile) {

	$i = 0;
	$wrong_locale_folders = [];
	
	foreach ($data_profile as $profile_info) {
		$browser[] = $profile_info['browser'];
		$os[] = $profile_info['os'];
		
		if (strripos($profile_info['lang'], 'ru') === false) {
			$wrong_locale_folders[] = $profile_info['folder'] ." |Wrong locale: " .$profile_info['lang'];
		}
		
		if ($profile_info['browser'] == 'unknown') {
			$wrong_browser_folders[] = $profile_info['folder'] ." | unknown browser! OS: {$profile_info['os']} ";
		}
		
		
		
	}

	
	print_r($wrong_locale_folders);
	print_r($wrong_browser_folders);	
	
	
	$stat_browser = array_count_values ($browser);
	$stat_os = array_count_values ($os);
	$profiles_count['profiles_count'] = count($data_profile);
	
	$stats = array_merge($stat_browser, $stat_os, $profiles_count);
	
	print_r($stats);

}







function csv_export($data_profile) {

	$data = [];
	$date = date("y.m.d_H.i.s");
	
	$fp = fopen("{$date}_export.csv", 'w');
	
	$head = '"folder";"device";"os";"browser";"lang";"user_agent";"view_width";"view_height";"cooki_count";"cooki_count_host_key";"cooki_unique_count_host";"cooki_yandex";"cooki_yandex_metrika";"cooki_yandex_ad";"cooki_google"';
	fwrite($fp, $head);
	
	foreach ($data_profile as $fields) {
			fputcsv($fp, $fields);
	}
	
	fclose($fp);

}

function parse_profile() {
	global $profile_dir;

	$profiles_list = profile_list($profile_dir);
	

	$data_profile = [];
		foreach ($profiles_list as $key => $profile_folder) {
			$date = date("H:i:s");
			$folder_name['folder'] = str_replace($profile_dir, '', $profile_folder);
			echo "{$date} | Scan \"{$folder_name['folder']}\"" .PHP_EOL;
			$finger = parse_fingerprint($profile_folder);
			$cookie = parse_cookie($profile_folder);
			$data_profile[] = array_merge($folder_name, $finger, $cookie);
		}
	

	return $data_profile;
	
}



function profile_list($profile_dir) {
	$list_profile = scandir($profile_dir);

	$profiles_list = [];
		foreach ($list_profile as $key => $profile_folder) {
			if (!($profile_folder == '.')) {
				if (!($profile_folder == '..')) {
				$profiles_list[] = $profile_dir. $profile_folder;
				}
			}			
		}
		
		return $profiles_list;
	}






function parse_fingerprint($file) {
	
	$json = file_get_contents($file .'/fingerprint.json', 'jsonp');
	$json = json_decode($json, true);
	$json = json_decode($json['fingerprint'], true);
	
	$data = [
		'device' => $json['tags'][2],
		'os' => $json['tags'][0],
		'browser' => $json['tags'][1],
		'lang' => $json['lang'],
		'user_agent' => $json['ua'],
		'view_width' => $json['width'],
		'view_height' => $json['height'],
	];
	

	return $data;
	
}

function parse_cookie($file) {
	
	
	$db = new SQLite3($file.'/Cookies');
	
	try {
		$db->enableExceptions(true);
		$cooki_count = $db->query('SELECT count(*) as count FROM cookies limit 1')->fetchArray();
		$cooki_count_host_key = $db->query('SELECT count(distinct(host_key)) as count_host FROM cookies')->fetchArray();
		
		$cooki_host_list_sql = $db->query('SELECT distinct(host_key) as host_list FROM cookies');
		
		while ($row = $cooki_host_list_sql->fetchArray()) {
			$cooki_host_list[] = $row[0];
		}


		$cooki_yandex = $db->query("SELECT host_key FROM cookies where host_key like '.yandex%'")->fetchArray();
		$cooki_yandex_metrika = $db->query("SELECT host_key FROM cookies where host_key like 'mc.yandex%'")->fetchArray();
		$cooki_yandex_ad = $db->query("SELECT host_key FROM cookies where host_key like 'an.yandex%'")->fetchArray();
		$cooki_google = $db->query("SELECT host_key FROM cookies where host_key like '%google%'")->fetchArray();
	} catch (Exception $e) {
		echo 'Err: not cookies' .PHP_EOL;
	}
	
	$cooki_yandex = ($cooki_yandex == true) ? '1' : '0';
	$cooki_yandex_metrika = ($cooki_yandex_metrika == true) ? '1' : '0';
	$cooki_yandex_ad = ($cooki_yandex_ad == true) ? '1' : '0';
	$cooki_google = ($cooki_google == true) ? '1' : '0';

	$domain = [];
	if ($cooki_host_list) {
			foreach ($cooki_host_list as $host) {
				$split = explode(".", $host);
				$split = array_reverse($split);
				$domain[] = $split[1] .'.'. $split[0];
			}
	}

	$unique_domain_count = count(array_unique($domain));

		$data = [
			'cooki_count' => (int)$cooki_count['count'],
			'cooki_count_host_key' => (int)$cooki_count_host_key['count_host'],
			'cooki_unique_count_host' => (int)$unique_domain_count,
			'cooki_yandex' => (int)$cooki_yandex,
			'cooki_yandex_metrika' => (int)$cooki_yandex_metrika,
			'cooki_yandex_ad' => (int)$cooki_yandex_ad,
			'cooki_google' => (int)$cooki_google
	];
	
	return $data;
	
}

?>