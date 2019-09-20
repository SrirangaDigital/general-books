<?php

	$contents = file_get_contents("mainfile.idx");
	$list = preg_split("/\n/",$contents);
	
	$entries = [];
	
	foreach($list as $idxentry){
		//~ var_dump($idxentry);
		if($idxentry == "") continue;
		
		$word = preg_replace("/\\\\indexentry\{(.*?)\}\{([0-9]+)\}/", "$1", $idxentry);
		//~ var_dump($word);

		$page = preg_replace("/\\\\indexentry\{(.*?)\}\{([0-9]+)\}/", "$2", $idxentry);
		//~ var_dump($word . " --> "  . $page);
		
		if(!isset($entries[$word])){
			$entries[$word] = [];
			array_push($entries[$word],$page);
		}
		else{
			array_push($entries[$word],$page);
		}
	}

	ksort($entries);

echo "\\begin{theindex}\n";

	foreach($entries as $key => $value){
		
		$string = "";
		$string = "\t\\item " . $key . "\\kern2pt ";
		sort($entries[$key]);
		$value = array_unique($value);

		foreach($value as $item){
				
			$string .=	$item . ", ";
		}
		
		$string = preg_replace("/, $/","",$string);
		
		echo $string . "\n";
	}

echo "\\end{theindex}\n";
	
?>
