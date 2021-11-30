<?php

/* PHP analyser for IPPcode20
 * Author: Martin Zovinec
 * Login : xzovin00
 */

//---------------// Main body of the script //---------------//
$jumps = 0;
$labels = 0;
$labelList = array();

// Argument check
foreach ($argv as $args){
	if( $args == "--help"){
		if ($argc == 2){
			help();
			exit(0);
		}else{
			echo "Cant combine help with other arguments!\n";
			exit(10);	
		}

	// Statfile search
	}else if(preg_match('/\-\-stats\=.+/',$args)){
		$temp = explode("=", $args);
		$statfile = $temp[1];
		fopen($statfile, 'w');
	}
	
}

// Check for expansion arguments without statfile
if ($statfile == ""){
	foreach($argv as $i){
		switch($i){
			case "--loc":
			case "--jump":
			case "--labels":
			case "--comments":
				exit(10);
			default:
				break;
		}
	}
}

// Loads input file
$file = file_get_contents('php://stdin');
if ($file == FALSE){
	exit(10);
}

// Delete all comentaries
$file = preg_replace( '/\s*#.*/','', $file,-1,$comments);

// Split into lines
$line = explode ("\n", $file);

// Delete empty lines
$line = array_filter($line);
$line = array_values($line);

// Set header to lowercase for case insensitive check
$line[0] = strtolower($line[0]);

// Header check
if(!preg_match('/\.ippcode20.*/',$line[0])){
	exit(21);
}else {
	$output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	$output = $output."<program language=\"IPPcode20\">\n";
}	

// Get rid of 2+ whitespace characters in all lines
foreach ($line as $i){
	trim($line[$i]);
	preg_replace('/\s+/',' ',$line[$i]);
}

for($insCount = 1; $insCount < sizeof($line); $insCount++){

	// Spliting line into words and cleaning it from empty strings
	
	$word = explode(" ", $line[$insCount]);
	$word = array_filter($word);
	$word = array_values($word);
	// initializing/emptying an array
	$correctArgs = array();

	// Switch for instruction adress type checking
	switch($word[0]){

	  // Three adress instructions

		case "ADD":
		case "SUB":
		case "MUL":
		case "IDIV":
		case "LT":
		case "GT":
		case "EQ":
		case "AND":
		case "OR":
		case "STRI2INT":
		case "CONCAT":
		case "GETCHAR":
		case "SETCHAR":
			$correctArgs = array( "var", "symb", "symb" );
			break;

		case "JUMPIFEQ":
		case "JUMPIFNEQ":
			$jumps++;
			$correctArgs = array( "label", "symb", "symb");
			break;

	  // Two adress instructions

		case "MOVE":
		case "INT2CHAR":
		case "STRLEN":
		case "TYPE":
		case "NOT":
			$correctArgs = array( "var", "symb" );
			break; 

		case "READ":
			$correctArgs = array( "var", "type" );
			break;

	  // One adress instructions

		case "WRITE":
		case "DPRINT":
		case "PUSHS":
		case "EXIT":
			$correctArgs = array( "symb" );
			break;

		case "DEFVAR":
		case "POPS":
			$correctArgs = array( "var" );
			break;

		case "JUMP":
			$jumps++;
		case "CALL":
		case "LABEL":
			$correctArgs = array( "label" );
			break;

	  // No adress instructions

		case "CREATEFRAME":
		case "PUSHFRAME":
		case "POPFRAME":
		case "RETURN":
		case "BREAK":
			$correctArgs = array( "" );
			break;

	  // Non-defined instructions
		default:
			exit(22);
	}
	checkInsArgs($word,$correctArgs, $insCount);
	$loc++;

}

//----------//Successful end//----------//

$output=$output."</program>\n";
echo $output;
statistics($argv,$statfile,$loc,$comments,$labels,$jumps);
exit(1);

//---------------// End of Main //---------------//




//---------------// Function declarations //---------------//

// Function for argument checking and printing
function checkInsArgs ( $word, $correctArgs, $insCount ){

	// Argument count check
	
	if(sizeof($word) != sizeof($correctArgs)+1 && $correctArgs[0] != ""){
		exit(23);
	}

	// Argument type check
	for($i = 0; $i < sizeof($correctArgs); $i++){
		switch($correctArgs[$i]){
			case "":
				break;
			case "type":
				if(!isType($word[$i+1])) exit(23);
				break;

			case "label":
				if(!isLabel($word[$i+1])) exit(23); 
				uniqueLabel($word[$i+1]);
				break;

			case "var":
				if(!isVar($word[$i+1])) exit(23);
				break;

			case "symb":
			   // Variable check
				if(isVar($word[$i+1])){
					$correctArgs[$i] = "var";

			   // Constant check 
				}else{
					$parts = explode("@", $word[$i+1] );
					
					switch($parts[0]){
						case "int":
							if($parts[1] != "nil" && !is_int((int)$parts[1])) exit(23);
							break;
						case "string":
							if($parts[1] != "nil" && !is_string($parts[1])) exit(23);
							break;	
						case "nil":
							if($parts[1] != "nil") exit(23);
							break;
						case "bool":
							if($parts[1] != "true" && $parts[1] != "false" && $parts[1] != "nil")
								exit(23);
							break;
						default:
							exit(23);
						
					}		
					$correctArgs[$i] = $parts[0];
					$word[$i+1] = $parts[1];
				}
				break;
			default:
				echo "Wrongly defined internal argument for an adress\n";
				exit(99);
		}
	} // Instruction has correct adresses
	insStart($insCount,$word[0]);

	// Prints all arguments
	if($correctArgs[0] != ""){
		for($adrCount = 1; $adrCount <= sizeof($correctArgs); $adrCount++)
			insVarPrint( $adrCount, $correctArgs[$adrCount-1], $word[$adrCount]);
	}
	insEnd();
	echo $output;
}

//-----// RegEx based syntax checking functions //-----//

function isLabel ( $word ){
	return preg_match('/[A-Za-z0-9_\-\&\%\*\!\?][A-Za-z_\-\&\%\*\!\?]*/', $word);
}

function isVar ( $word ){
	return preg_match('/((GF)|(LF)|(TF))(@[A-Za-z_\-\&\%\*\!\?][A-Za-z0-9_\-\&\%\*\!\?]*)/', $word );
}

function isType ( $word ){
	return preg_match('/(int)|(str)|(nil)|(bool)/', $word );
}



//-----// Output printing functions //-----//

// Outputs <instruction order="1" opcode="MOVE">
function insStart( $insCnt, $insType ){
	global $output;
	$output=$output.`echo "\t<instruction order=\"$insCnt\" opcode=\"$insType\">"`;
}

// Outputs </instruction>
function insEnd(){
	global $output;
	$output=$output.`echo "\t</instruction>"`;
}

// Outputs <arg1 type="string">IPP</arg1>
function insVarPrint( $argPos, $type, $value){

	// Overwrites  <, > and & in strings 
	if($type == "string"){
		$replaceMe =  array ( '/&/', '/</', '/>/' );
		$putMe = array ( '&amp;',  '&lt;' , '&gt;' );
		$value =preg_replace($replaceMe,$putMe,$value);

	}

	global $output;

	$output=$output.`echo "\t\t<arg$argPos type=\"$type\">$value</arg$argPos>"`;
}

function uniqueLabel($label){
global $labelList;
global $labels;

	foreach ($labelList as $i){
		if($i == $label)
			return false;
	}	
	$labels++;
	array_push($labelList,"$label");
	return true;
}

function statistics($argv,$statfile,$loc,$comments,$labels,$jumps){
	foreach ($argv as $i){
		switch($i){
			case "--loc":
				file_put_contents($statfile, $loc."\n", FILE_APPEND);
				break;
			case "--comments":
				file_put_contents($statfile, $comments."\n", FILE_APPEND);
				break;
			case "--label":		
				file_put_contents($statfile, $labels."\n", FILE_APPEND);
				break;
			case "--jumps":
				file_put_contents($statfile, $jumps."\n", FILE_APPEND);
				break;
			default:
				break;
		}
	}
}

function help(){
 
echo "Analyzator IPPcode2020 

Prepinace:
--help		zobrazi napovedu
--statfile=xxx	do souboru xxx pujdou statistiky
--loc		pocet instrukci
--comments	pocet komentaru
--label		pocet navesti
--jumps		pocet instrukci skoku

Ocekavany vstup:
parse.php <input

Kde input je vstupni soubor v IPPcode2020,
ktery bude prelozen do XML.\n";
}
?>
