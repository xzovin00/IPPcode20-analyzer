<?php

/* PHP analyser for IPPcode21
 * Author: Martin Zovinec
 * Login : xzovin00
 */

//---------------// Main body of the script //---------------//
$jumps = 0;
$labels = 0;
$labelList = array();
$DEBUG = FALSE;

// Argument check
foreach ($argv as $args){
	if( $args == "--help"){
		if ($argc == 2){
			help();
			debug_exit(0); #DEBUG_EXIT
		}else{
			echo "Cant combine help with other arguments!\n";
			debug_exit(10); #DEBUG_EXIT	
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
				debug_exit(10); #DEBUG_EXIT
			default:
				break;
		}
	}
}

// Load input file
$file = file_get_contents('php://stdin');

if ($file == FALSE){
    debug_exit(10); #DEBUG_EXIT
}

// Delete all comments
$file = preg_replace( '/\s*#.*/','', $file,-1,$comments);

// Split into lines
$line = explode ("\n", $file);

// Delete empty lines
$line = array_filter($line);
$line = array_values($line);

// Set header to lowercase for case insensitive check
$line[0] = strtolower($line[0]);

// Header check
if(!preg_match('/^\s*\.ippcode21\s*$/',$line[0])){
	debug_exit(21); #DEBUG_EXIT
}else{
	$output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	$output .= "<program language=\"IPPcode21\">\n";
}

// Get rid of 2+ whitespace characters in all lines
foreach ($line as $i){
	trim($line[$i]);
	preg_replace('/\s+/',' ',$line[$i]);
}

for($insCount = 1; $insCount < sizeof($line); $insCount++){

	// Spliting line into words and removing empty strings
	$word = explode(" ", $line[$insCount]);
	$word = array_filter($word);
  	$word = array_values($word);
    
	// initializing/clearing an array
	$correctArgs = array();

	// Switch for instruction adress type checking
	if($DEBUG) 
		echo "Checking instruction $insCount: $word[0]";

	$word[0] = strtoupper($word[0]);
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
			debug_exit(22); #DEBUG_EXIT
	}
	checkInsArgs($word, $correctArgs, $insCount);
	$loc++;
}

//----------//Successful end//----------//

$output=$output."</program>\n";
echo "$output";
statistics($argv,$statfile,$loc,$comments,$labels,$jumps);
debug_exit(0); #DEBUG_EXIT

//---------------// End of main body of the script //---------------//




//---------------// Function declarations //---------------//

// Function for argument checking and printing
function checkInsArgs ( $word, $correctArgs, $insCount ){
	global $DEBUG;
	
	if($DEBUG){
		for($i = 0; $i < sizeof($correctArgs); $i++){
			echo " <$correctArgs[$i]>";
		}
		echo "\n";
	}

	// Argument count check
	if(sizeof($word) != sizeof($correctArgs)+1 && $correctArgs[0] != ""){
		debug_exit(23); #DEBUG_EXIT
	}

	// Argument type checkd
	for($i = 0; $i < sizeof($correctArgs); $i++){
		$bigger_i = $i+1; #TODO name me properly
		switch($correctArgs[$i]){
			case "":
				if($word[$bigger_i]){
					debug_exit(23); #DEBUG_EXIT
				}
				break;
			case "type":
				if($DEBUG) echo "\tis it a type?"; # DEBUG
				if(!isType($word[$bigger_i])){
					debug_exit(23); #DEBUG_EXIT
				} 
				break;

			case "label":
				if($DEBUG) echo "\tis it a label?\n"; # DEBUG
				if(!isLabel($word[$bigger_i]) || !uniqueLabel($word[$bigger_i])) 
					debug_exit(23); #DEBUG_EXIT 		
				break;

			case "var":
				if($DEBUG) echo "\tis it a var?\n"; # DEBUG
				if(!isVar($word[$bigger_i])) 
					debug_exit(23); #DEBUG_EXIT
				break;

      case "symb":
			    // Variable check
			  if($DEBUG) echo "\tis \"$word[$bigger_i]\" a symbol?\n"; # DEBUG
				if($DEBUG) echo "\t\tis it a var?\n"; # DEBUG
				if(isVar($word[$bigger_i])){
					$correctArgs[$i] = "var";
			   // Constant check 
				}else{
					$parts = explode("@", $word[$bigger_i] );
					
					switch($parts[0]){
						case "int":
							if($DEBUG) echo "\t\tis it an int?\n"; # DEBUG
							if($parts[1] != "nil" && !is_int((int)$parts[1]) || $parts[1] == "") 
								debug_exit(23); #DEBUG_EXIT
							break;

            case "string":
                if($DEBUG) echo "\t\tis it a string?\n"; # DEBUG
                if($parts[1] != "nil" && !isString($parts[1]) && $parts[1] != ""){
                    debug_exit(23); #DEBUG_EXIT 
								}
							break;	

						case "nil":
							if($DEBUG) 
								echo "\t\tis it a nill?\n"; # DEBUG
							
							if($parts[1] != "nil") 
								debug_exit(23); #DEBUG_EXIT
							break;
						case "bool":
							if($DEBUG) 
								echo "\t\tis it a bool?\n"; # DEBUG
							
							if($parts[1] != "true" && $parts[1] != "false" && $parts[1] != "nil")
								debug_exit(23); #DEBUG_EXIT
							break;
                        default:
							if($DEBUG) 
								echo "\t\tnot int, not string, not nil, not bool\n"; # DEBUG
							debug_exit(23); #DEBUG_EXIT
					}		

					$correctArgs[$i] = $parts[0];
					$word[$bigger_i] = $parts[1];
				}
				break;
			default:
				echo "Wrong internal argument for an adress\n";
				debug_exit(99); #DEBUG_EXIT
		}
	} // Instruction has correct adresses
	insStart($insCount,$word[0]);

	// Prints all arguments
	if($correctArgs[0] != ""){
		for($adrCount = 1; $adrCount <= sizeof($correctArgs); $adrCount++)
			insArgPrint( $adrCount, $correctArgs[$adrCount-1], $word[$adrCount]);
	}
	insEnd();
}

//-----// RegEx based syntax checking functions //-----//

function isLabel($word){
	return preg_match('/^[A-Za-z_\-\&\%\*\$\!\?][A-Za-z0-9_\-\&\$\%\*\!\?]*$/', $word);
}

function isVar($word){
	return preg_match('/^(GF|LF|TF)@[A-Za-z_\-\$\&\%\*\!\?][A-Za-z0-9_\-\&\@\$\%\*\!\?]*$/', $word );
}

function isString($word){
	return preg_match('/^([^#\\\\ \t]|(\\\\[0-9]{3}))+$/', $word );
}

function isType($word){
	return preg_match('/^(int)|(str)|(nil)|(bool)$/', $word );
}

//-----// Output printing functions //-----//

// Outputs <instruction order="1" opcode="MOVE">
function insStart( $insCnt, $insType ){
	global $output;
	$output=$output."\t<instruction order=\"$insCnt\" opcode=\"$insType\">\n";
}

// Outputs </instruction>
function insEnd(){
	global $output;
	$output=$output."\t</instruction>\n";
}


// Outputs <arg1 type="string">IPP</arg1>
function insArgPrint( $argPos, $type, $value){

	// Overwrites  <, > and & in strings 
	if($type == "string"){
		$replaceMe =  array ( '/&/', '/</', '/>/' );
		$putMe = array ( '&amp;',  '&lt;' , '&gt;' );
		$value = preg_replace($replaceMe,$putMe,$value);
	}

	global $output;

	$output=$output."\t\t<arg$argPos type=\"$type\">$value</arg$argPos>\n";
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

# function for printing statics
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

# debuging exit function
function debug_exit($err_code){
	global $DEBUG;
	if($DEBUG){
		echo "Exit $err_code\n\n";
	}
	exit($err_code);
}


function help(){
	echo "Analyzator IPPcode2021 
Prepinace:
--help			zobrazi napovedu
--statfile=xxx	do souboru xxx pujdou statistiky
--loc			pocet instrukci
--comments		pocet komentaru
--label			pocet navesti
--jumps			pocet instrukci skoku

Ocekavany vstup:
parse.php <input
Kde input je vstupni soubor v IPPcode2021,
ktery bude prelozen do XML.\n";
}
?>