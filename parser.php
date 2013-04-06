<?php
	
	function curl_request($url, $tosx = false, $error_report = false, $postfields = false){
			
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 99999);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 99999);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		
		
		if($postfields !== false){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
		}
		
		$res = curl_exec($ch);
		
		if($error_report){
			echo "<pre>";
			print_r(curl_getinfo($ch));
			echo "</pre><br>";
			echo "\n\ncURL error number:" .curl_errno($ch)."<br/>";  
			echo "\n\ncURL error:" . curl_error($ch)."<br/>";
		}
		
		curl_close($ch);
		
		if($tosx){
			$sx = simplexml_load_string($res);
			return $sx;
		}
		else{
			return $res;
		}
	}
	
	$res = curl_request("http://yandex.ru");
	var_dump($res); exit;
	
	/* for($i=651; $i<=800; $i++){
	
		//получаем страницу
		$url = "http://www.salon-olga.ru/catalog.phtml?idim=".$i;
		$res = curl_request($url);
		
		if(preg_match("/404 Not Found/", $res)){
			continue;
		}
		
		//находим название модели
		$name_pattern = "/<title>(.+)\s*-\s*.+<\/title>/i";
		$pocket = array();
		preg_match($name_pattern, $res, $pocket);
		
		$model_name = $pocket[1];

		$rpl = array(
			"'" => ""
		);
		$model_name = strtr($model_name, $rpl);
		
		if(preg_match("/\||\//", $model_name) || trim($model_name) == ""){
			$model_name = "idim".$i;
		}
		else{
			$model_name.= "_".$i;
		}
		
		//создаем директорию для модели
		if(!is_dir("models/".$model_name)){
			$r = mkdir("models/".$model_name, 0777);
		}
		

		
		//находим описание
		$description_pattern = "/<br><b>(.+)<\/td><\/tr>/";
		$pocket = array();
		
		if(preg_match($description_pattern, $res, $pocket)){
			
			$data = strip_tags($pocket[1]);
						
			$file = "models/".$model_name."/description.txt";
			file_put_contents($file, $data);	
		};
		

		//находим картинки
		//'/src="media\/image\/catalog\/"/i'
		$img_pattern = '/img src="(\/media\/image\/catalog\/.+?)"/i';
		

		$pocket = array();
		preg_match_all($img_pattern, $res, $pocket, PREG_SET_ORDER);
		

		
		foreach($pocket as $num => $match){
			$data = file_get_contents('http://www.salon-olga.ru/'.$match[1]);
			$file = "models/".$model_name."/".time().'_'.$num.'.jpg';
			file_put_contents($file, $data);
		}
	
	} */
	//print_r($res);