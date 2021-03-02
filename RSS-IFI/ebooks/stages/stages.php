<?php

class Stages{

	public function __construct() {
		
	}

	public function processFiles($bookID) {


		$allFiles = $this->getAllFiles($bookID);

		foreach($allFiles as $file){
	
			$this->process($bookID,$file);		
		}
	
	}

	public function getAllFiles($bookID) {

		$allFiles = [];
		
		$folderPath = RAW_SRC . $bookID . '/Stage1/';
		
	    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath));

	    foreach($iterator as $file => $object) {
	    	
	    	if(preg_match('/.*\.htm[l]?$/',$file)) array_push($allFiles, $file);
	    }

	    sort($allFiles);

		return $allFiles;
	}


	public function process($bookID,$file) {

		// stage1.html : Input html from adobe acrobat
		$rawHTML = file_get_contents($file);

		// Process html to strip off unwanted tags and elements
		$processedHTML = $this->processRawHTML($rawHTML);

		// stage2.html : Output html for conversion		
		$baseFileName = basename($file);

		if (!file_exists(RAW_SRC . $bookID . '/Stage2/')) {
			mkdir(RAW_SRC . $bookID . '/Stage2/', 0775);
			echo "Stage2 directory created\n";
		}

		$fileName = RAW_SRC . $bookID . '/Stage2/' . $baseFileName;

		// $processedHTML = html_entity_decode($processedHTML, ENT_QUOTES);
		file_put_contents($fileName, $processedHTML);

		// Convert Anu data to Unicode retaining html tags
		$unicodeHTML = $this->parseHTML($processedHTML);

		// stage3.html : Output Unicode html with tags, english retained as it is
		if (!file_exists(RAW_SRC . $bookID . '/Stage3a/')) {
			mkdir(RAW_SRC . $bookID . '/Stage3a/', 0775);
			echo "Stage3a directory created\n";
		}

		$fileName = RAW_SRC . $bookID . '/Stage3a/' . $baseFileName;	

		$unicodeHTML = html_entity_decode($unicodeHTML);
		
		file_put_contents($fileName, $unicodeHTML);

		if(file_exists(RAW_SRC . $bookID . '/Stage3/' . $baseFileName)) {

			$unicodeHTML = preg_replace('/<sup>.*?<\/sup>/i', ' ', $unicodeHTML);
			$strippedHTML = strip_tags($unicodeHTML);
			// new file normalizations
			$strippedHTML = str_replace('.', '. ', $strippedHTML);
			$strippedHTML = preg_replace('/\s+/', ' ', $strippedHTML);
			$strippedHTML = preg_replace('/ /', "\n", $strippedHTML);
			$strippedHTML = str_replace('–', '-', $strippedHTML);

			$fileNameAfter = RAW_SRC . $bookID . '/Stage3a/' . $baseFileName . '.after.txt';	
			file_put_contents($fileNameAfter, $strippedHTML);

			$oldHTML = file_get_contents(RAW_SRC . $bookID . '/Stage3/' . $baseFileName);

			// remove 200c character
			$oldHTML = str_replace('‌', '', $oldHTML);
			file_put_contents(RAW_SRC . $bookID . '/Stage3/' . $baseFileName, $oldHTML);

			$oldHTML = preg_replace('/<sup>.*?<\/sup>/i', ' ', $oldHTML);
			$oldHTML = strip_tags($oldHTML);
			$oldHTML = preg_replace('/\s+/', ' ', $oldHTML);
			$oldHTML = preg_replace('/ /', "\n", $oldHTML);

			$fileNameBefore = RAW_SRC . $bookID . '/Stage3a/' . $baseFileName . '.before.txt';	
			file_put_contents($fileNameBefore, $oldHTML);

			$fileNameDiff = RAW_SRC . $bookID . '/Stage3a/' . $baseFileName . '.diff';	
			exec('diff ' . $fileNameBefore . ' ' . $fileNameAfter . ' > ' . $fileNameDiff);
			exec('rm ' . $fileNameBefore);
			exec('rm ' . $fileNameAfter);
		}
	}

	public function parseHTML($html) {

		$dom = new DOMDocument("1.0");
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;

		$dom->loadXML($html);
		$xpath = new DOMXpath($dom);

		foreach($xpath->query('//text()') as $text_node) {

			if(preg_replace('/\s+/', '', $text_node->nodeValue) === '') continue; 

			if($text_node->parentNode->hasAttribute('class'))
				if($text_node->parentNode->getAttribute('class') == 'en')
					 continue;

			$text_node->nodeValue = $this->praja2Unicode($text_node->nodeValue);
		}

		return $dom->saveXML();
	}

	public function processRawHTML($text) {

		$text = preg_replace('/<!--.*/', "", $text);

		$text = str_replace("\n", "", $text);
		$text = str_replace("\r", "", $text);
		$text = preg_replace('/&([^a-zA-Z])/', "&amp;$1", $text);

		$text = preg_replace('/(<[a-zA-Z])/', "\n$1", $text);
		$text = preg_replace('/>/', ">\n", $text);

		// Special cases that need to be retained
		$text = preg_replace('/<SPAN .*font.*(times|Sylfaen|Tahoma|Arial).*>/i', '<SPAN-class="en">', $text);
		$text = preg_replace('/<SPAN .*font-weight.*bold.*>/i', '<SPAN-class="bold">', $text);
		$text = preg_replace('/<SPAN .*font-style.*italic.*>/i', '<SPAN-class="italic">', $text);
		//~ $text = preg_replace('/<SPAN .*font-weight.*normal.*>/i', '<SPAN-class="normal">', $text);

		// General cases that need to be deleted
		$text = preg_replace('/<([a-zA-Z0-9]+) .*\/>/i', "<$1-/>", $text);
		$text = preg_replace('/<([a-zA-Z0-9]+) .*>/i', "<$1>", $text);

		// Special cases - reverted to original form
		$text = str_replace('<SPAN-', '<SPAN ', $text);
		$text = str_replace('-/>', ' />', $text);

		// Remove unecessary tags
		$text = preg_replace("/\n<IMG.*/i", "", $text);

		
		// Clean up and indent file	

		$text = preg_replace("/\n+/", "\n", $text);
		$text = preg_replace("/[ \t]+/", " ", $text);

		$text = preg_replace("/</", "\n<", $text);
		$text = preg_replace("/>\n/", ">", $text);
		$text = preg_replace("/\n+/", "\n", $text);

		$text = preg_replace("/<Span class=\"bold\">(.*)\n<\/span>/i", "<strong>$1</strong>", $text);
		$text = preg_replace("/<Span class=\"italic\">(.*)\n<\/span>/i", "<em>$1</em>", $text);

		$text = preg_replace("/[\n]*<Span class=\"en\">(.*)\n<\/span>[\n]*/i", "<SPAN class=\"en\">$1</SPAN>", $text);
		//~ $text = preg_replace("/<Span class=\"normal\">(.*)\n<\/span>/i", "$1", $text);
		$text = preg_replace("/[\n]*<Span>(.*)\n<\/span>[\n]*/i", "$1", $text);

		$text = preg_replace("/\n<(Span|Sup|Sub|Strong|em)/i", "<$1", $text);
		// $text = preg_replace("/<(Span|Sup|Sub|Strong|em)>\n/i", "<$1>", $text);
		$text = preg_replace("/\n<\/(Span|Sup|Sub|Strong|em)>/i", "</$1>", $text);
		$text = preg_replace("/<\/(Span|Sup|Sub|Strong|em)>\n/i", "</$1>", $text);

		$text = preg_replace("/<P>\n/i", "<P>", $text);
		$text = preg_replace("/\n<\/P>/i", "</P>", $text);

		$text = preg_replace("/<LI>\n/i", "<LI>", $text);
		$text = preg_replace("/\n<\/LI>/i", "</LI>", $text);

		$text = preg_replace("/(<H\d>)\n/i", "$1", $text);
		$text = preg_replace("/\n(<\/H\d>)/i", "$1", $text);

		$text = str_replace("\n ", " ", $text);

		$text = str_replace("<DIV", "<SECTION", $text);
		$text = str_replace("</DIV>", "</SECTION>", $text);

		// Special case to handle nested en
		$text = preg_replace('/<SPAN class="en">(.*?)<([a-zA-Z]+)>(.*?)<\/\2>(.*?)<\/SPAN>/i', "<SPAN class=\"en\">$1<$2 class=\"en\">$3</$2>$4</SPAN>", $text);

		$text = preg_replace("/<SPAN class=\"en\">(.*)<\/SPAN>\n/", "<strong>$1</strong>", $text);
		$text = str_replace("</strong><strong>", "", $text);

		// Remove head items
		$text = preg_replace("/<(STYLE|META|HEAD).*\n/i", "", $text);
		$text = preg_replace("/<\/(STYLE|META|HEAD).*\n/i", "", $text);
		
		return $text;
	}
	
	public function praja2Unicode ($text) {

		// Initial parse

		$text = str_replace('"j', 'j"', $text);
		$text = str_replace('kn', 'nk', $text);
		$text = str_replace('jnk', 'nkj', $text);
		$text = str_replace('æk', 'æl', $text);
		$text = str_replace('jk', 'kj', $text);
		$text = str_replace('`', 'u|', $text);
		$text = str_replace('AÎ', 'Aj', $text);
		$text = str_replace('vk', 'vl', $text);
		$text = str_replace('Nk', 'æl', $text);
		//~ $text = str_replace('"|', '|"', $text);

		// ya group
		$text = str_replace('O"', 'ಯ', $text);
		$text = str_replace('O{', 'ಯ', $text);
		$text = str_replace('Ò"', 'ಯೆ', $text);
		$text = str_replace('Òr', 'ಯೊ', $text);
		$text = str_replace('h"', 'ಯಿ', $text);

		// ma group
		$text = str_replace('Aj"', 'ಮ', $text);
		$text = str_replace('Aj{', 'ಮ', $text);
		$text = str_replace('Au"', 'ಮೆ', $text);
		$text = str_replace('Aur', 'ಮೊ', $text);
		$text = str_replace('a"', 'ಮಿ', $text);
		
		// jjha group
		$text = str_replace('Újs"', 'ಝ', $text);
		$text = str_replace('Újst', 'ಝಾ', $text);
		$text = str_replace('Úus"', 'ಝೆ', $text);
		$text = str_replace('Úusr', 'ಝೊ', $text);
		$text = str_replace('Ys"', 'ಝಿ', $text);

		// swara

		// Vyanjana
		$text = str_replace('ül', 'ಛ್', $text);
		$text = str_replace('Vl', 'ಛಿ', $text);

		$text = str_replace('Úm', 'ಠ್', $text);
		$text = str_replace('Ym', 'ಠಿ', $text);

		$text = str_replace('Ûk', 'ಢ್', $text);
		$text = str_replace('Zk', 'ಢಿ', $text);

		$text = str_replace(';nk', 'ಥ್', $text);
		//~ $text = str_replace(';nkj', 'ಥ', $text);
		$text = str_replace(']nk', 'ಥಿ', $text);

		$text = str_replace(';k', 'ಧ್', $text);
		$text = str_replace(']k', 'ಧಿ', $text);
		
		$text = str_replace('=k', 'ಫ್', $text);
		$text = str_replace('_k', 'ಫಿ', $text);

		$text = str_replace('æl', 'ಭ್', $text);
		$text = str_replace('vl', 'ಭಿ', $text);
		$text = str_replace('`l', 'ಭಿ', $text);

		$text = str_replace('Š‡', '್ಧ', $text);
		$text = str_replace('†‡', '್ಢ', $text);
		$text = str_replace('’‡', '್ಭ', $text);

		// Lookup ---------------------------------------------
		$text = str_replace('!', 'ಅ', $text);
		$text = str_replace('"', 'ು', $text);
		$text = str_replace('#', 'ಇ', $text);
		$text = str_replace('$', 'ಈ', $text);
		$text = str_replace('%', 'ಉ', $text);
		$text = str_replace('&', 'ಊ', $text);
		$text = str_replace("'", 'ಪ್', $text);
		$text = str_replace('(', 'ಎ', $text);
		$text = str_replace(')', 'ಏ', $text);
		$text = str_replace('*', 'ಐ', $text);
		$text = str_replace('+', 'ಒ', $text);
		// $text = str_replace(',', '್ಛ', $text);
		$text = str_replace('', '್ಛ', $text);
		//~ $text = str_replace('-', '', $text);
		$text = str_replace('', '್ಝ', $text);
		$text = str_replace('/', 'ಃ', $text);
		$text = str_replace('0', 'ಂ', $text);
		$text = str_replace('1', '೧', $text);
		$text = str_replace('2', '೨', $text);
		$text = str_replace('3', '೩', $text);
		$text = str_replace('4', '೪', $text);
		$text = str_replace('5', '೫', $text);
		$text = str_replace('6', '೬', $text);
		$text = str_replace('7', '೭', $text);
		$text = str_replace('8', '೮', $text);
		$text = str_replace('9', '೯', $text);
		$text = str_replace(':', 'ತ್', $text);
		$text = str_replace(';', 'ದ್', $text);
		$text = str_replace('<', 'ನ್', $text);
		$text = str_replace('=', 'ಪ್', $text);
		$text = str_replace('>', 'ì', $text); //?
		$text = str_replace('?', 'ಲ್', $text);
		$text = str_replace('@', 'ಣಿ', $text);
		$text = str_replace('A', 'ವ್', $text);
		$text = str_replace('B', 'ಶ್', $text);
		$text = str_replace('C', 'ಷ್', $text);
		$text = str_replace('D', 'ಸ್', $text);
		$text = str_replace('E', 'ಹ್', $text);
		$text = str_replace('F', 'ಜ್ಞ್', $text);
		$text = str_replace('G', 'ಖ', $text);
		$text = str_replace('H', 'ಙ', $text);
		$text = str_replace('I', 'ಜ', $text);
		$text = str_replace('J', 'ಞ', $text);
		$text = str_replace('K', 'ಟ', $text);
		$text = str_replace('L', 'ಣ', $text);
		$text = str_replace('M', 'ಋ', $text);// ?
		$text = str_replace('N', 'ಬ', $text);
		$text = str_replace('O', 'ಯ್', $text);
		$text = str_replace('P', 'ಲ', $text);
		$text = str_replace('Q', 'ಜ್ಞ', $text);
		$text = str_replace('R', 'ಕಿ', $text);
		$text = str_replace('S', 'ಖಿ', $text);
		$text = str_replace('T', 'ಗಿ', $text);
		$text = str_replace('U', 'ಚಿ', $text);
		// $text = str_replace('V', 'ಛಿ', $text); //?
		$text = str_replace('W', 'ಜಿ', $text);
		$text = str_replace('X', 'ಟಿ', $text);
		$text = str_replace('Y', 'ರಿ', $text);
		$text = str_replace('Z', 'ಡಿ', $text);
		//~ $text = str_replace('[', '', $text);
		$text = str_replace("\\", 'ತಿ', $text);
		$text = str_replace(']', 'ದಿ', $text);
		$text = str_replace('^', 'ನಿ', $text);
		$text = str_replace('_', 'ಪಿ', $text);
		$text = str_replace('`', 'v', $text);
		$text = str_replace('a', 'ವಿ', $text);
		$text = str_replace('b', 'ಲಿ', $text);
		$text = str_replace('c', 'ಷಿ', $text);
		$text = str_replace('d', 'ಸಿ', $text);
		$text = str_replace('e', 'ಹಿ', $text);
		$text = str_replace('f', 'ಳಿ', $text);
		$text = str_replace('g', 'ಜ್ಞಿ', $text);
		$text = str_replace('h', 'ಯಿ', $text); //?
		$text = str_replace('i', 'ಶಿ', $text);
		$text = str_replace('j', 'ಅ', $text);
		$text = str_replace('k', 'k', $text); //?
		//~ $text = str_replace('l', '', $text); //?
		//~ $text = str_replace('m', '', $text); //?
		//~ $text = str_replace('n', '', $text); //?
		$text = str_replace('o', 'ಾ', $text);
		$text = str_replace('p', 'ಿ', $text);
		$text = str_replace('q', 'ಆ', $text);
		$text = str_replace('r', 'ೂ', $text);
		//~ $text = str_replace('s', '', $text); //?
		$text = str_replace('t', 't', $text);
		$text = str_replace('u', 'ೆ', $text);
		$text = str_replace('v', 'ಬಿ', $text);
		$text = str_replace('w', 'ೆ', $text);
		$text = str_replace('x', '್ಕ', $text);
		$text = str_replace('y', 'ೄ', $text);
		$text = str_replace('z', 'ೌ', $text);
		//~ $text = str_replace('{', '', $text); //?
		//~ $text = str_replace('|', '', $text); //?
		$text = str_replace('}', '್​', $text);
		$text = str_replace('~', 'R', $text);
		$text = str_replace('¡', '್ಖ', $text);
		$text = str_replace('¢', '್ಗ', $text);
		$text = str_replace('£', '್ಘ', $text);
		$text = str_replace('¤', '್ಙ', $text);
		$text = str_replace('¥', '್ಚ', $text);
		//~ $text = str_replace('¦', '', $text);
		$text = str_replace('§', '್ಜ', $text);
		$text = str_replace('©', '್ಞ', $text);
		$text = str_replace('ª', '್ಟ', $text);
		$text = str_replace('«', '್ಠ', $text);
		$text = str_replace('®', '್ಣ', $text);
		$text = str_replace('°', '್ಥ', $text);
		$text = str_replace('±', '್ಱ', $text);
		$text = str_replace('²', '್ೞ', $text);
		$text = str_replace('³', 'ೞ', $text);
		$text = str_replace('´', 'ಱ', $text);
		//~ $text = str_replace('µ', '', $text);
		$text = str_replace('¶', '್ಮ', $text);
		//~ $text = str_replace('·', '', $text);
		$text = str_replace('¸', '್ತ್ರ', $text);
		//~ $text = str_replace('¹', '', $text);
		$text = str_replace('º', '್ವ', $text);
		
		$text = str_replace('»', '್ಶ', $text);
		$text = str_replace('¿', '್ಳ', $text);
		$text = str_replace('À', '್ಹ', $text);
		$text = str_replace('Á', 'ॐ', $text);
		$text = str_replace('Â', '್ಕೃ', $text);
		$text = str_replace('Ã', '್ಬೈ', $text);
		$text = str_replace('Ä', '್ಟ್ರ', $text);
		$text = str_replace('Å', '್ತೃ', $text);
		$text = str_replace('Æ', '್ತೈ', $text);
		$text = str_replace('Ç', '್ಯ', $text);
		$text = str_replace('È', '್ರ', $text);
		$text = str_replace('É', '್ಪ್ರ', $text);
		$text = str_replace('Ê', '್ರೈ', $text);
		$text = str_replace('Ë', '್ಸ್ರ', $text);
		$text = str_replace('Ì', '್ಕ್ಷ', $text);
		$text = str_replace('Í', '್ಕ್ರ', $text);
		$text = str_replace('Î', 'ೆ', $text);
		//~ $text = str_replace('Ï', '', $text); //?
		$text = str_replace('Ñ', 'ೂ', $text);
		$text = str_replace('Ò', 'ಯೆ', $text); //?
		$text = str_replace('Ó', 'ಕ್', $text);
		$text = str_replace('Ô', 'ಗ್', $text);
		$text = str_replace('Õ', 'ಘ್', $text);
		$text = str_replace('Ö', 'ಚ್', $text);
		$text = str_replace('Ø', 'ಜ್', $text);
		$text = str_replace('Ù', 'ಟ್', $text);
		$text = str_replace('Ú', 'ರ್', $text);
		$text = str_replace('Û', 'ಡ್', $text);
		$text = str_replace('Ü', 'ಣ್', $text);
		$text = str_replace('ß', '', $text);
		$text = str_replace('à', 'ಂ', $text);
		$text = str_replace('á', 'ಶ್ರೀ', $text);
		$text = str_replace('â', 'ೃ', $text);
		$text = str_replace('ã', 'ೈ', $text);
		$text = str_replace('ä', ',', $text);
		$text = str_replace('å', '.', $text); // handled separately inside span class english
		$text = str_replace('æ', 'ಬ್', $text);
		$text = str_replace('ç', 'ನ್', $text);
		$text = str_replace('è', 'ಳ್', $text);
		$text = str_replace('é', '್ತ್ರ', $text);
		$text = str_replace('ê', '್ತ್ಯ', $text);
		$text = str_replace('ë', '್ಷ', $text);
		//~ $text = str_replace('ì', '', $text); //?
		$text = str_replace('í', 'ಫ್', $text);
		$text = str_replace('î', 'ಖ್', $text);
		$text = str_replace('ò', 'ಔ', $text);
		$text = str_replace('ô', '', $text);
		$text = str_replace('ö', 'ಘಿ', $text);
		$text = str_replace('ø', 'ಓ', $text);
		$text = str_replace('ù', 'ಕ', $text);
		$text = str_replace('ú', 'ಕೆ', $text);
		$text = str_replace('û', 'ು', $text);
		$text = str_replace('ü', 'ಛ್ ', $text);
		$text = str_replace('ÿ', 'ಌ', $text);
		$text = str_replace('Œ', '್ಪ', $text);
		$text = str_replace('œ', '್ಸ', $text);
		$text = str_replace('Š', '್ದ', $text);
		$text = str_replace('š', '್ಲ', $text);
		$text = str_replace('–', 'ೞ', $text);
		$text = str_replace('—', 'ಱ', $text);
		$text = str_replace('‘', '್ಫ', $text);
		$text = str_replace('’', '್ಬ', $text);
		$text = str_replace('“', '್ಱ', $text);
		$text = str_replace('”', '್ೞ', $text);
		$text = str_replace('†', '್ಡ', $text);
		//~ $text = str_replace('‡', '', $text); //?
		$text = str_replace('‰', '್ತ', $text);
		$text = str_replace('‹', '್ನ', $text);
		$text = str_replace('›', '್ಷ', $text);
		$text = str_replace('™', '', $text);
		$text = str_replace('•', '್ತ್ಯ', $text);

		$text = str_replace('­', '್ಕ್ರ', $text); //caution! Character not visible : U+200B

		// Special cases

		// remove ottu spacer
		$text = str_replace('õ', '', $text);
		$text = str_replace('ï', '', $text);
		$text = str_replace('ñ', '', $text);
		$text = str_replace('ð', '', $text);

		// Swara
		$text = preg_replace('/್[ಅ]/u', '', $text);
		$text = preg_replace('/್([ಾಿೀುೂೃೄೆೇೈೊೋೌ್])/u', "$1", $text);
		
		// vyanjana
		$text = str_replace('ವt', 'ಮಾ', $text);
		$text = str_replace('ಯ್t', 'ಯಾ', $text);
		$text = str_replace('ಫì', 'ಘ', $text);
		$text = str_replace('ಫೆì', 'ಘೆ', $text);
		$text = str_replace('ಫಿì', 'ಘಿ', $text);

		$text = str_replace('ೊ', 'ೊ', $text);
		$text = str_replace('ೆೈ', 'ೈ', $text);

		$swara = "ಅ|ಆ|ಇ|ಈ|ಉ|ಊ|ಋ|ಎ|ಏ|ಐ|ಒ|ಓ|ಔ";
		$vyanjana = "ಕ|ಖ|ಗ|ಘ|ಙ|ಚ|ಛ|ಜ|ಝ|ಞ|ಟ|ಠ|ಡ|ಢ|ಣ|ತ|ಥ|ದ|ಧ|ನ|ಪ|ಫ|ಬ|ಭ|ಮ|ಯ|ರ|ಱ|ಲ|ವ|ಶ|ಷ|ಸ|ಹ|ಳ|ೞ";
		$swaraJoin = "ಾ|ಿ|ೀ|ು|ೂ|ೃ|ೄ|ೆ|ೇ|ೈ|ೊ|ೋ|ೌ|ಂ|ಃ|್";

		$syllable = "($vyanjana)($swaraJoin)|($vyanjana)($swaraJoin)|($vyanjana)|($swara)";

		$text = preg_replace("/($swaraJoin)್($vyanjana)/u", "್$2$1", $text);
		$text = preg_replace("/್​್($vyanjana)/u", "್$1್​", $text);
		

		$text = str_replace('||', '|', $text);

		$text = str_replace('ೊ', 'ೊ', $text);
		$text = str_replace('ೆೈ', 'ೈ', $text);

		$text = str_replace('ಿ|', 'ೀ', $text);
		$text = str_replace('ೆ|', 'ೇ', $text);
		$text = str_replace('ೊ|', 'ೋ', $text);
		
		$text = str_replace('​R', 'R​', $text);

		$text = preg_replace("/($swaraJoin)್($vyanjana)/u", "್$2$1", $text);
	
		// First pass of repha inversion
		$text = preg_replace("/($syllable)/u", "$1zzz", $text);
		$text = preg_replace("/್zzz/u", "್", $text);
		$text = preg_replace("/್R/u", "್zzzR", $text);
		$text = preg_replace("/zzz([^z]*?)zzzR/u", "zzzರ್zzz" . "$1", $text);
		$text = str_replace("zzz", "", $text);

		$text = str_replace('ೊ', 'ೊ', $text);
		$text = str_replace('ೆೈ', 'ೈ', $text);

		$text = str_replace('ಿ|', 'ೀ', $text);
		$text = str_replace('ೆ|', 'ೇ', $text);
		$text = str_replace('ೊ|', 'ೋ', $text);

		// Second pass of repha inversion
		$text = preg_replace("/($syllable)/u", "$1zzz", $text);
		$text = preg_replace("/್zzz/u", "್", $text);
		$text = preg_replace("/್R/u", "್zzzR", $text);
		$text = preg_replace("/zzz([^z]*?)zzzR/u", "zzzರ್zzz" . "$1", $text);
		$text = str_replace("zzz", "", $text);

		$text = str_replace('ೊ', 'ೊ', $text);
		$text = str_replace('ೆೈ', 'ೈ', $text);

		$text = str_replace('ಿ|', 'ೀ', $text);
		$text = str_replace('ೆ|', 'ೇ', $text);
		$text = str_replace('ೊ|', 'ೋ', $text);

		$text = preg_replace('/​([\h[:punct:]‘’“”])/u', "$1", $text);

		$text = preg_replace("/([[:punct:]\s0-9])ಂ/u", '${1}' . '0', $text);

		return $text;
	}

	public function normalizePraja ($text) {

		$text = str_replace('á', 'ù', $text);  // 225,E1 --> 249,F9
		$text = str_replace('¢', 'Â', $text);  // 162,A2 --> 194,C2
		$text = str_replace('¤', 'Ä', $text);  // 164,A4 --> 196,C4
		$text = str_replace('×', 'ü', $text);  // 164,A4 --> 196,C4
		
		$text = str_replace('', 'x', $text);
		$text = str_replace('', '¡', $text);
		$text = str_replace('', '¢', $text);
		$text = str_replace('', '£', $text);

		$text = str_replace('', '¥', $text);
		// $text = str_replace('', ',', $text); //handled in converter
		$text = str_replace('', '§', $text);
		// $text = str_replace('', '.', $text); //handled in converter
		$text = str_replace('', '©', $text);
		$text = str_replace('', 'ª', $text);
		$text = str_replace('', '«', $text);
		$text = str_replace('', '†', $text);
		$text = str_replace('', '‡', $text);
		$text = str_replace('', '®', $text);
		$text = str_replace('', '‰', $text); // 143,8F --> 137,89
		$text = str_replace('', '°', $text);
		$text = str_replace('', 'Š', $text);
		$text = str_replace('', '‹', $text);
		$text = str_replace('', 'Œ', $text);
		$text = str_replace('', '‘', $text);
		$text = str_replace('', '’', $text);
		$text = str_replace('', '¶', $text);
		$text = str_replace('', 'Ç', $text);
		$text = str_replace('', 'È', $text);
		$text = str_replace('', 'š', $text);
		$text = str_replace('', 'º', $text);
		$text = str_replace('', '»', $text);
		$text = str_replace('', 'ë', $text);
		$text = str_replace('', 'œ', $text);
		$text = str_replace('', '~', $text);
		$text = str_replace('', '¿', $text);
		
		// 
		
		$text = str_replace('Ð', 'û', $text);  // 164,A4 --> 196,C4
		$text = str_replace('¾', 'À', $text);

		return $text;
	}

	public function anu2Unicode ($text) {

		// Initial parse
		// $text = str_replace('fl', 'ﬂ', $text);
		// $text = str_replace('fi', 'ﬁ', $text);
		$text = str_replace('', '½', $text);
		$text = str_replace('¶', 'è', $text);
		
		// Reversing occurances
		
		$text = preg_replace('/([Ô¿Ãˆ])([+HQ~ãÇÑ])/u', "$2$1", $text);

		// Consolidation of same glyphs at multiple code points
		$text = preg_replace('/[íõ°¨Æ«»Œ◊]+/u', 'í', $text); // అ
		$text = preg_replace('/[åê•ß®ÍÏ]+/u', 'å', $text); // ా
		$text = preg_replace('/[ç≤˜]+/u', 'ç', $text); // ి
		$text = preg_replace('/[ô©‘]+/u', 'ô', $text); // ీ
		$text = preg_replace('/[∞μµΩΩ√]/u', '∞', $text); // ు
		$text = preg_replace('/[ÄØ¥∂Ó]+/u', 'Ä', $text); // ూ
		$text = preg_replace('/[≥Ã‹Ôˇ]+/u', '≥', $text); // ె
		$text = preg_replace('/[¿ÕËıˆ]+/u', '¿', $text); // ే
		$text = preg_replace('/[ÿ·Â]+/u', 'ÿ', $text); // ై
		$text = preg_replace('/[∏⁄Á˘]+/u', '∏', $text); // ొ
		$text = preg_replace('/[À’ŸÈ]+/u', 'À', $text); // ో
		$text = preg_replace('/[∫øœ“Ò]+/u', '∫', $text); // ౌ
		$text = preg_replace('/[òü£±∑π]+/u', 'ò', $text); // ్ // Caution zero width space present

		// ma group
		$text = str_replace('=∞', 'మ', $text);
		$text = str_replace('=Ä', 'మా', $text);
		$text = str_replace('q∞', 'మి', $text);
		$text = str_replace('g∞', 'మీ', $text);
		$text = str_replace('=Ú', 'ము', $text);
		$text = str_replace('=¸', 'మూ', $text);
		$text = str_replace('"≥∞', 'మె', $text);
		$text = str_replace('"¿∞', 'మే', $text);
		$text = str_replace('"≥ÿ∞', 'మై', $text);
		$text = str_replace('"≥Ú', 'మొ', $text);
		$text = str_replace('"≥Ä', 'మో', $text);
		$text = str_replace('"ò∞', 'మ్​', $text); // Caution zero width space present

		// ya group
		$text = str_replace('Üí∞', 'య', $text);
		$text = str_replace('ÜíÄ', 'యా', $text);
		$text = str_replace('~Ú', 'యి', $text);
		$text = str_replace('ÜÄ', 'యీ', $text);
		$text = str_replace('~¸', 'యీ', $text);
		$text = str_replace('ÜíÚ', 'యు', $text);
		$text = str_replace('Üí¸', 'యూ', $text);		
		$text = str_replace('Ü≥∞', 'యె', $text);
		$text = str_replace('Ü¿∞', 'యే', $text);
		$text = str_replace('Ü≥ÿ∞', 'యై', $text);
		$text = str_replace('Ü≥Ú', 'యొ', $text);
		$text = str_replace('Ü≥Ä', 'యో', $text);
		$text = str_replace('Üò∞', 'య్​', $text); // Caution zero width space present

		// jjha group
		// ఝ ఝి ఝీ ఝు ఝూ ఝె ఝే ఝై ఝొ ఝో ఝౌ 
		$text = str_replace('~í≠', 'ఝ', $text);
		
		// ha group
		$text = str_replace('Çíå', 'హ', $text);
		$text = str_replace('Çí½', 'హా', $text);
		$text = str_replace('Ççå', 'హి', $text);
		$text = str_replace('Çôå', 'హీ', $text);
		$text = str_replace('Çíï', 'హు', $text);
		$text = str_replace('Çí˙', 'హూ', $text);		
		$text = str_replace('Ç≥å', 'హె', $text);
		$text = str_replace('Ç¿å', 'హే', $text);
		$text = str_replace('Ç≥ÿå', 'హై', $text);
		$text = str_replace('Çíå∏', 'హొ', $text);
		$text = str_replace('ÇíåÀ', 'హో', $text);
		$text = str_replace('Çòå', 'హ్​', $text); // Caution zero width space present
		
		// gha group
		$text = str_replace('Ñèí∞', 'ఘ', $text);
		$text = str_replace('ÑèíÄ', 'ఘా', $text);
		$text = str_replace('Ñèç∞', 'ఘి', $text);
		$text = str_replace('Ñèô∞', 'ఘీ', $text);
		$text = str_replace('ÑèíÚ', 'ఘు', $text);
		$text = str_replace('Ñèí¸', 'ఘూ', $text);		
		$text = str_replace('Ñè≥∞', 'ఘె', $text);
		$text = str_replace('Ñè¿∞', 'ఘే', $text);
		$text = str_replace('Ñè≥ÿ∞', 'ఘై', $text);
		$text = str_replace('Ñè≥Ú', 'ఘొ', $text);
		$text = str_replace('Ñè≥Ä', 'ఘో', $text);
		$text = str_replace('Ñè≥∞', 'ఘ్​', $text); // Caution zero width space present
		
		$text = str_replace('ùù', 'ù', $text);
		$text = str_replace('Ûù', '్ఛ', $text);
		$text = str_replace('¤ù', '్ఢ', $text);
		$text = str_replace('Êù', '్ఫ', $text);
		$text = str_replace('ƒù', '్భ', $text);
		// Special cases Jha pending
		 
		// swara
		$text = str_replace('|Ú', 'ఋ', $text);
		$text = str_replace('|¸', 'ౠ', $text);
		
		// Lookup ---------------------------------------------
				
		$text = str_replace('ž', 'డ్', $text);

		$text = str_replace('!', '!', $text);
		$text = str_replace('"', 'వ్', $text);
		$text = str_replace('#', 'న', $text);
		$text = str_replace('$', 'ృ', $text);
		// $text = str_replace('%', '%', $text);
		$text = str_replace('&', 'ఞ', $text);
		// $text = str_replace("'", "‘", $text); // handled later
		$text = str_replace('(', '(', $text);
		$text = str_replace(')', ')', $text);
		$text = str_replace('*', 'జ', $text);
		$text = str_replace('+', 'ష్', $text);
		$text = str_replace(',', ',', $text);
		$text = str_replace('-', '-', $text);
		$text = str_replace('.', '.', $text);
		$text = str_replace('/', '/', $text);
		$text = str_replace('0', '0', $text);
		$text = str_replace('1', '1', $text);
		$text = str_replace('2', '2', $text);
		$text = str_replace('3', '3', $text);
		$text = str_replace('4', '4', $text);
		$text = str_replace('5', '5', $text);
		$text = str_replace('6', '6', $text);
		$text = str_replace('7', '7', $text);
		$text = str_replace('8', '8', $text);
		$text = str_replace('9', '9', $text);
		$text = str_replace(':', ':', $text);
		$text = str_replace(';', '్ష్మ', $text);
		$text = str_replace('<', 'న్', $text);
		$text = str_replace('=', 'వ', $text);
		$text = str_replace('>', 'ట', $text);
		$text = str_replace('?', '?', $text);
		$text = str_replace('@', 'ట', $text);
		$text = str_replace('A', 'జు', $text);
		$text = str_replace('B', 'ఔ', $text);
		$text = str_replace('C', '్పు', $text); // verify 
		$text = str_replace('D', 'ఈ', $text);
		$text = str_replace('E', 'జూ', $text);
		$text = str_replace('F', 'ఓ', $text);
		$text = str_replace('G', 'స్త్ర', $text);
		$text = str_replace('H', 'క్', $text);
		$text = str_replace('I', '।', $text);
		$text = str_replace('J', 'అ', $text);
		$text = str_replace('K', 'చ్', $text);
		$text = str_replace('L', 'ఉ', $text);
		$text = str_replace('M', 'ఖ', $text);
		$text = str_replace('N', 'శ్రీ', $text);
		$text = str_replace('O', 'ం', $text);
		$text = str_replace('P', 'ఆ', $text);
		$text = str_replace('Q', 'గ్', $text);
		$text = str_replace('R', 'ష్ట్ర', $text);
		$text = str_replace('S', 'ఐ', $text);
		$text = str_replace('T', 'ఊ', $text);
		$text = str_replace('U', 'ఏ', $text);
		$text = str_replace('V', 'ఙ', $text);
		$text = str_replace('W', 'ఇ', $text);
		$text = str_replace('X', 'ఒ', $text);
		$text = str_replace('Y', 'ఖ', $text);
		$text = str_replace('Z', 'ఎ', $text);
		$text = str_replace('[', 'జ', $text);
		$text = str_replace("\\", 'ట', $text);
		$text = str_replace(']', '్ఱ', $text);
		$text = str_replace('^', 'ద్', $text);
		$text = str_replace('_', 'డ్', $text);
		$text = str_replace('`', 'త్', $text);
		$text = str_replace('a', 'బి', $text);
		$text = str_replace('b', 'లీ', $text);
		$text = str_replace('c', 'బీ', $text);
		$text = str_replace('d', 'ఖీ', $text);
		$text = str_replace('e', 'లి', $text);
		$text = str_replace('f', 'తీ', $text);
		$text = str_replace('g', 'వీ', $text);
		$text = str_replace('h', 'నీ', $text);
		$text = str_replace('i', 'రి', $text);
		$text = str_replace('j', 'శీ', $text);
		$text = str_replace('k', 'ది', $text);
		$text = str_replace('l', 'జి', $text); 
		$text = str_replace('m', 'ళీ', $text);
		$text = str_replace('n', 'దీ', $text); 
		$text = str_replace('o', 'ళి', $text);
		$text = str_replace('p', 'చీ', $text);
		$text = str_replace('q', 'వి', $text);
		$text = str_replace('r', 'జీ', $text);
		$text = str_replace('s', 'రీ', $text); 
		$text = str_replace('t', 'శి', $text);
		$text = str_replace('u', 'తి', $text);
		$text = str_replace('v', 'ఖీ', $text);
		$text = str_replace('w', 'గీ', $text);
		$text = str_replace('x', 'ని', $text);
		$text = str_replace('y', 'గి', $text);
		$text = str_replace('z', 'చి', $text);
		$text = str_replace('{', '+', $text);
		$text = str_replace('|', 'బ', $text);
		$text = str_replace('}', 'ణ', $text);
		$text = str_replace('~', 'ర్', $text); // could be ya
		$text = str_replace('Ä', 'ూ', $text);
		$text = str_replace('Å', 'ల', $text);
		$text = str_replace('Ç', 'ప్', $text);
		$text = str_replace('É', 'బ', $text); 
		$text = str_replace('Ñ', 'ప్', $text);
		$text = str_replace('Ö', 'ల', $text);
		$text = str_replace('Ü', 'Ü', $text); // pre యి
		$text = str_replace('á', 'ప్', $text);
		$text = str_replace('à', 'ళ్', $text);
		$text = str_replace('â', 'శ్', $text);
		$text = str_replace('ä', 'ä', $text); // pre
		$text = str_replace('ã', 'స్', $text);
		$text = str_replace('å', 'ా', $text);
		$text = str_replace('ç', 'ి', $text);
		$text = str_replace('é', 'ఱ', $text);
		$text = str_replace('è', 'è', $text); // pre
		$text = str_replace('ê', 'ా', $text);
		$text = str_replace('ë', 'ష్', $text);
		$text = str_replace('í', 'అ', $text);
		$text = str_replace('ì', '్ట', $text);
		$text = str_replace('î', 'î', $text); // pre da dha
		$text = str_replace('ï', 'ï', $text); // pre hu
		$text = str_replace('ñ', 'ఁ', $text);
		$text = str_replace('ó', 'ః', $text);
		$text = str_replace('ò', '్​', $text); // Caution zero width space present
		$text = str_replace('ô', 'ీ', $text);
		$text = str_replace('ö', '్ఖ', $text);
		$text = str_replace('õ', 'అ', $text);
		$text = str_replace('ú', '్ధ', $text);
		$text = str_replace('ù', 'ù', $text); // pre
		$text = str_replace('û', '్స', $text);
		$text = str_replace('ü', '్', $text);
		$text = str_replace('†', ';', $text);
		$text = str_replace('°', 'అ', $text);
		$text = str_replace('¢', '¢', $text);
		$text = str_replace('£', '్', $text);
		$text = str_replace('§', '్ళ', $text);
		$text = str_replace('•', 'ా', $text);
		$text = str_replace('¶', '¶', $text); // pre
		$text = str_replace('ß', 'ా', $text);
		$text = str_replace('®', 'ా', $text);
		$text = str_replace('©', 'ీ', $text);
		$text = str_replace('™', 'స్', $text);
		$text = str_replace('´', '=', $text);
		$text = str_replace('¨', 'అ', $text);
		$text = str_replace('≠', '≠', $text); // pre
		$text = str_replace('Æ', 'అ', $text);
		$text = str_replace('Ø', 'ూ', $text);
		$text = str_replace('∞', 'ు', $text);
		$text = str_replace('±', '్', $text);
		$text = str_replace('≤', 'ి', $text);
		$text = str_replace('≥', 'ె', $text);
		$text = str_replace('¥', 'ూ', $text);
		$text = str_replace('μ', 'ు', $text);
		$text = str_replace('∂', 'ూ', $text);
		$text = str_replace('∑', '్', $text);
		$text = str_replace('∏', 'ొ', $text);
		$text = str_replace('π', '్', $text);
		$text = str_replace('∫', 'ౌ', $text);
		$text = str_replace('ª', '్ఠ', $text);
		$text = str_replace('º', '్య', $text);
		$text = str_replace('Ω', 'ు', $text);
		$text = str_replace('æ', '్గ', $text);
		$text = str_replace('ø', 'ౌ', $text);
		$text = str_replace('¿', 'ే', $text);
		$text = str_replace('¡', '్ల', $text);
		$text = str_replace('¬', '్ష', $text);
		$text = str_replace('√', 'ు', $text);
		$text = str_replace('ƒ', '్బ', $text);
		$text = str_replace('≈', '్శ', $text);
		$text = str_replace('Δ', '్ష', $text);
		$text = str_replace('∆', '్ష', $text);
		$text = str_replace('«', 'అ', $text);
		$text = str_replace('»', 'అ', $text);
		$text = str_replace('…', '్ఘ', $text);
		// $text = str_replace(' ', '&', $text);
		$text = str_replace('À', 'ో', $text);
		$text = str_replace('Ã', 'ె', $text);
		$text = str_replace('Õ', 'ే', $text);
		$text = str_replace('Œ', 'అ', $text);
		$text = str_replace('œ', 'ౌ', $text);
		$text = str_replace('–', '–', $text);
		// $text = str_replace('—', '’', $text); // handled later
		$text = str_replace('“', 'ౌ', $text);
		$text = str_replace('”', '÷', $text);
		$text = str_replace('‘', 'ీ', $text);
		$text = str_replace('’', 'ో', $text);
		$text = str_replace('÷', '్థ', $text);
		$text = str_replace('◊', 'అ', $text);
		$text = str_replace('ÿ', 'ౖ', $text);
		$text = str_replace('Ÿ', 'ో', $text);
		$text = str_replace('⁄', 'ొ', $text);
		$text = str_replace('¤', '్డ', $text);
		$text = str_replace('‹', 'ె', $text);
		$text = str_replace('›', '్హ', $text);
		$text = str_replace('ﬁ', '్వ', $text);
		$text = str_replace('ﬂ', '్న', $text);
		$text = str_replace('‡', '్మ', $text);
		$text = str_replace('·', 'ౖ', $text);
		$text = str_replace('‚', '్ణ', $text);
		$text = str_replace('„', '¢', $text);
		$text = str_replace('‰', 'క్', $text);
		$text = str_replace('Â', 'ౖ', $text);
		$text = str_replace('Ê', '్ప', $text);
		$text = str_replace('Á', 'ొ', $text);
		$text = str_replace('Ë', 'ే', $text);
		$text = str_replace('È', 'ో', $text);
		$text = str_replace('Í', 'ా', $text);
		$text = str_replace('Î', '్త', $text);
		$text = str_replace('Ï', 'ా', $text);
		$text = str_replace('Ì', '్ద', $text);
		$text = str_replace('Ó', 'ూ', $text);
		$text = str_replace('Ô', 'ె', $text);
		
		$text = str_replace('Ò', 'ౌ', $text);
		$text = str_replace('Ú', 'Ú', $text); // pre
		$text = str_replace('Û', '్చ', $text);
		$text = str_replace('Ù', 'ు', $text); 
		$text = str_replace('ı', 'ే', $text);
		$text = str_replace('ˆ', 'ే', $text);
		$text = str_replace('˜', 'ి', $text);
		$text = str_replace('¯', '్క', $text);
		$text = str_replace('˘', 'ొ', $text);
		$text = str_replace('˙', '˙', $text); // pre ha
		$text = str_replace('˚', '్జ', $text);
		$text = str_replace('¸', '¸', $text); // pre
		$text = str_replace('˝', '్ఞ', $text);
		$text = str_replace('˛', '×', $text);
		$text = str_replace('ˇ', 'ె', $text);

		$swara = "అ|ఆ|ఇ|ఈ|ఉ|ఊ|ఋ|ౠ|ఎ|ఏ|ఐ|ఒ|ఓ|ఔ";
		$vyanjana = "క|ఖ|గ|ఘ|ఙ|చ|ఛ|జ|ఝ|ఞ|ట|ఠ|డ|ఢ|ణ|త|థ|ద|ధ|న|ప|ఫ|బ|భ|మ|య|ర|ల|వ|శ|ష|స|హ|ళ|ఱ";
		$swaraJoin = "ా|ి|ీ|ు|ూ|ృ|ౄ|ె|ే|ై|ొ|ో|ౌ|ం|ః|్";

		// Swara
		$text = preg_replace('/్[అ]/u', '', $text);
		$text = preg_replace('/్([ాిీుూృౄెేైౖొోౌ్])/u', "$1", $text);

		// Special cases gha, Cha, Jha, Dha, tha, dha, pha, bha 
		$text = preg_replace("/($swaraJoin)([èäî])/u", "$2$1", $text);
		$text = str_replace('చè', 'ఛ', $text);
		$text = str_replace('డè', 'ఢ', $text);
		$text = str_replace('దä', 'థ', $text);
		$text = str_replace('దè', 'ధ', $text);
		$text = str_replace('పè', 'ఫ', $text);
		$text = str_replace('బè', 'భ్', $text);
		$text = str_replace('రî', 'ఠ', $text);

		// Spaces before ottu should be removed
		$text = str_replace(' ్', "్", $text);
		$text = str_replace(' ృ', "ృ", $text);

		// Swara
		$text = preg_replace('/్[అ]/u', '', $text);
		$text = preg_replace('/్([ాిీుూృౄెేైౖొోౌ్])/u', "$1", $text);
		
		// vyanjana

		$text = preg_replace("/ె($vyanjana)ౖ/u", "$1" . 'ై', $text);
		$text = str_replace('ై', 'ై', $text);
		
		$syllable = "($vyanjana)($swaraJoin)|($vyanjana)($swaraJoin)|($vyanjana)|($swara)";
		$text = preg_replace("/($swaraJoin)్($vyanjana)/u", "్$2$1", $text);
		$text = preg_replace("/($swaraJoin)్($vyanjana)/u", "్$2$1", $text);
		$text = preg_replace("/($swaraJoin)్($vyanjana)/u", "్$2$1", $text);
		$text = preg_replace("/($swaraJoin)్($vyanjana)/u", "్$2$1", $text);
		$text = preg_replace("/్​్($vyanjana)/u", "్$1్​", $text);
		$text = preg_replace("/్​్($vyanjana)/u", "్$1్​", $text);
		$text = preg_replace("/్​్($vyanjana)/u", "్$1్​", $text);

		// Ra ottu inversion
		$text = preg_replace("/¢($vyanjana)/u", "$1" . "¢", $text);
		$text = preg_replace("/¢్($vyanjana)/u", "్$1" . "¢", $text);
		$text = str_replace("¢", "్ర", $text);
		$text = str_replace("్య్ర", "్ర్య", $text);

		$text = str_replace('ౖ', "<!-- <error>ౖ</error> -->", $text);

		// Final replacements
		$text = str_replace('।।', '॥', $text);
		$text = str_replace("'", '‘', $text);
		$text = str_replace('—', '’', $text);
		$text = str_replace('‘‘', '“', $text);
		$text = str_replace('’’', '”', $text);

		$text = str_replace('బుు', 'ఋ', $text);
		$text = str_replace('బుూ', 'ౠ', $text);

		return $text;
	}
}

?>
