<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

function dump($arr){
?>
	<pre>
<?
	echo htmlspecialchars(print_r($arr, true));
	
?>
	</pre>
<?
}

function printElapsed($clock){
	$elapsed = (microtime(true)-$clock)/1000;
	echo "time elapsed is ".$elapsed."ms <br />";
}

function parseWTML($filename){
	
	$clock = microtime(true);

	$templateHandle = fopen($filename, 'r');
	$template = fread($templateHandle, filesize($filename));
	
	$template = preg_replace("/\/\/.*?(?=\n)|\/\*([^*]|[\r\n])*\*\//", '', $template); //remove all template comments, and uneccessary code (;)
	
	$tag = '[a-zA-Z0-9]+';
	$class = '((\.-?[_a-zA-Z]+[_a-zA-Z0-9-]*)*';
	$id = '(\#-?[_a-zA-Z]+[_a-zA-Z0-9-]*)*';
	$attribute = '(\[.*?\])*';
	$content = '(\(\".*?\"\))*)*';
	
	$selectorRegex = "$tag$class$id$attribute$content";
	
	$twigBlock = "{%.*?%}";
	$htmlComment = "<!--.*?-->";
	$nestingGrammar = "[{>}]";
	
	$finalRegex = "/($twigBlock)|($htmlComment)|($selectorRegex)|($nestingGrammar)/s";

/* echo "<h1>Comments stripped</h1>"; */
/* echo nl2br($template); */



/*
?>
	<h2>Split</h2>
<?
*/


/* echo "selector regex: ".htmlspecialchars($selectorRegex); */
/* echo "final regex: ".htmlspecialchars($finalRegex); */

preg_match_all($finalRegex, $template, $matches);

/* $matches = preg_split("/($selectorRegex)/s", $template, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY); */

/* printElapsed($clock); */



$regexBlocks = array(
				1=>'twig_block',
				2=>'html_comment',
				3=>'selector',
				9=>'nesting_grammar'
				);
				
/*
$selectorRegexes = array (
	'text'	=>	"\([\"\'].*?[\'\"]\)",
	'attr'	=>	"\[.*?\]",
	'id'	=>	"#.*?(?=[\.#\[$])",
	'class'	=>	"\..*?(?=[\.#\[$])",
	'tag'	=>	"[a-z0-9]+"
);
*/

$selectorRegexes = array (
	'content'	=>	"/\(\".*?\"\)/s",
	'attr'	=>	"/\[.*?\]/",
	'id'	=>	"/#.*?(?![a-zA-Z0-9_-])/",
	'class'	=>	"/\..*?(?![a-zA-Z0-9_-])/",
	'tag'	=>	"/[a-z0-9]+/"
);

foreach($matches[0] as $key => $match){
	foreach($regexBlocks as $i=>$instruction){
		if (strlen($matches[$i][$key])>0){
			if($i==3){ //selector
				$selectorArray = array(); //initialise and unset
				$line = $match;
				
/* 				$selectorArray['_line'] = $line; */
				foreach($selectorRegexes as $name=>$regex){
					$line = preg_replace_callback("$regex", function($match) use($name, &$selectorArray){
						$selectorArray[$name][] = $match[0];
						return '';
					}, $line);
				}
				
/* 				$selectorArray['~end_line'] = $line; */
				
				$instructions[][$instruction] = $selectorArray;
				
			}else{
				$instructions[][$instruction] = $match;
			}
		}
	}
}

/* dump($matches); */
/* dump($instructions); */

$tagBuffer = array();
$htmlArray = array();

$selfClosingElements = array('area','base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr');

function addItem($item, &$offset, &$array){
	array_splice($array, $offset, 0, str_repeat("\t", count($array)-$offset).$item."\n");
	$offset++; //increment to point after item
}

$insertOffset = 0;
$offsetArray = array();

foreach($instructions as $key => $instructionArray){
	
	$offsetArray[] = $insertOffset;
	
	$instruction = key($instructionArray);
/* 	echo "instruction is: ".$instruction; */
/* 	dump($instructionArray); */

	

	switch($instruction){
		case 'selector':
		{
		
			$tagComponents = array();
			$htmlTag = $instructionArray['selector']['tag'][0];
			$tagComponents[] = $htmlTag;
			if (isset($instructionArray['selector']['id']))		$tagComponents[] = 'id="'.str_replace('#', '', $instructionArray['selector']['id'][0]).'"';
			if (isset($instructionArray['selector']['class']))	$tagComponents[] = 'class="'.str_replace('.', '', implode(' ', $instructionArray['selector']['class'])).'"';
			if (isset($instructionArray['selector']['attr']))	$tagComponents[] = str_replace(array('[',']'), '', implode(' ', $instructionArray['selector']['attr']));
			$selfClosing = in_array($htmlTag, $selfClosingElements);
			if ($selfClosing)	$tagComponents[] = "/";
			
			$openingTag = "<".implode(' ', $tagComponents).">";
/* 			$htmlArray[] = $openingTag; */
/* 			addItem($openingTag, $insertOffset, $htmlArray); */
			
/* 			if (isset($instructionArray['selector']['content']))	$htmlArray[] = preg_replace("/^\(\"|\"\)$/", '', $instructionArray['selector']['content'][0]); */
			$tagContent = '';
			if (isset($instructionArray['selector']['content']))	$tagContent = preg_replace("/^\(\"|\"\)$/", '', $instructionArray['selector']['content'][0]);
/* 			$htmlArray[] = $instructionArray['selector']['tag'][0]; */
		
			addItem($openingTag.$tagContent, $insertOffset, $htmlArray);
		
			if (!$selfClosing)	addItem("</".$htmlTag.">", $insertOffset, $htmlArray); //if item needs a closure
			
		}
		break;
		case 'twig_block':
		{
			addItem($instructionArray['twig_block'], $insertOffset, $htmlArray);
/* 			echo "twig block added:"; */
		}
		break;
		case 'html_comment':
		{
			addItem($instructionArray['html_comment'], $insertOffset, $htmlArray);
/* 			echo "html comment added:"; */
		}
		break;
		case 'nesting_grammar':
		{
/* 			$htmlArray[] = $instructionArray['nesting_grammar'][0]; */
			
			switch($instructionArray['nesting_grammar'][0]){
				case '{':
					$insertOffset--; //child entered, inserting before previous closure
				break;
				case '}':
					$insertOffset++; //child exited, jump in front of parent closure		
				break;
				case '>': //havent worked out how to deal with you yet
					$insertOffset--; //jump in a level
					if (!isset($childDepth))	$childDepth = 0;
					$childDepth++;
				break; 
			}
		}
		break;
	}
	
	if (isset($childDepth)){
		if (!(($instruction=='nesting_grammar' && $instructionArray['nesting_grammar']=='>')||($instruction=='selector' && isset($instructions[$key+1]['nesting_grammar']) && $instructions[$key+1]['nesting_grammar'] == '>'))){ //look ahead
			$insertOffset += $childDepth;
			$offsetArray[] = "($childDepth) added to offset";
			
			unset($childDepth);
		}
	}
}

/* printElapsed($clock); */


/* dump($htmlArray); */
/* dump($offsetArray); */


/* echo "<pre>".htmlspecialchars(implode('', $htmlArray))."</pre>"; */

return implode('', $htmlArray);

} //end function






?>