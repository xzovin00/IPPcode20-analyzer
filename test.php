<?php

/* PHP testing script for IPPcode21
 * Author: Martin Zovinec
 * Login : xzovin00
 */

$HTMLHead= "<!DOCTYPE html>
<html>
  <head>
    <title>Test output</title>
    <style>
      body {
        background-color: #001f3f;
        margin: 0%;
        padding: 0%;
      }

      table {
        background-color: #7FDBFF;
        margin-left: auto;
        margin-right: auto;
        width: 200px;
        padding-right: 100px;
        padding-left: 100px;
        cellpadding: 0%;
        margin-top: 0%;
        margin-bottom: 0%;
      }

      td {
        border: 1px solid black;
        text-align: left;
        margin: 0%;
        padding: 3px;
      }

      th {
        border: 1px solid black;
        margin: 0%;
        padding: 3px;
      }

      .dir {
        background-color: #39CCCC;
      }
      .failed {
        background-color: #FF4136;
      }

      .passed {
        background-color: #01FF70;
      }

      .result {
        background-color: #39CCCC;
      }

   </style>
  </head>
  <body>
    <table>\n;";

$HTMLEnd ="\t</table>\n</body>\n</html>\n";

$testDir = "./";
$parseFile = "parse.php";
$intFile = "interpret.py";
$jexamFile = "/pub/courses/ipp/jexamxml/jexamxml.jar";
$jexamCfg = "/pub/courses/ipp/jexamxml/options";

$DEBUG_INTERNAL = FALSE;
$recursive = FALSE;
$parseOnly = FALSE;
$intOnly = FALSE;
$debug = FALSE;
$progress = FALSE;


$RcFailedCount = 0;
$XMLFailedCount = 0;
$RcpassedCount = 0;
$XMLpassedCount = 0;
$testState = TRUE;
$lastDir = "";

# Parsing arguments
foreach($argv as $args){
    if(preg_match('/\-\-help/',$args)){
        if ($argc == 2){
            help();
            exit(0);
        }else{
            fwrite(STDERR, "Error: Can't combine help with other arguments!\n");
            exit(10);	
        }
    }

    if(preg_match('/\-\-directory\=\w(\w+|\/|\-)*/',$args,$matches)){
        $temp = explode("=", $matches[0]);
        $testDir=$temp[1];

        if(!file_exists($testDir)){
            fwrite(STDERR, "Error: Test directory does not exist.\n");
            exit(41);
        }
    }

    if(preg_match('/\-\-parse\-script\=\w+/',$args,$matches)){
        if($matches[1]){ 
            fwrite(STDERR, "Error: more than 1 parse script path\n");
            exit(10);
        }
        $temp = explode("=", $matches[0]);
        $parseFile=$temp[1];

        if(!file_exists($parseFile)){
            fwrite(STDERR, "Error: parse.php file does not exist.\n");
            exit(41);
        }
    }

    if(preg_match('/\-\-int\-script\=\w+/',$args,$matches)){
        if($matches[1]){ 
            fwrite(STDERR, "Error: more than 1 interpret script path\n");
            exit(10);
        }
        $temp = explode("=", $matches[0]);
        $intFile=$temp[1];

        if(!file_exists($intFile)){
            fwrite(STDERR, "Error: interpret.py file does not exist.\n");
            exit(41);
        }
    }

    if(preg_match('/\-\-jexamxml\=\w+/',$args,$matches)){
        if($matches[1]){ 
            fwrite(STDERR, "Error: more than 1 jexamxml path\n");
            exit(10);
        }
        $temp = explode("=", $matches[0]);
        $jexamFile=$temp[1];

        if(!file_exists($jexamFile)){
            fwrite(STDERR, "Error: jexamxml file does not exist.\n");
            exit(41);
        }
    }

    if(preg_match('/\-\-jexamcfg\=\w+/',$args,$matches)){
        if($matches[1]){ 
            fwrite(STDERR, "Error more than 1 jexamxml path\n");
            exit(10);
        }
        $temp = explode("=", $matches[0]);
        $jexamCfg=$temp[1];

        if(!file_exists($jexamCfg)){
            fwrite(STDERR, "Error: jexamCfg file does not exist.\n");
            exit(41);
        }
    }

    if(preg_match('/\-\-recursive/',$args)){
        $recursive = TRUE;
    }

    if(preg_match('/\-\-parse\-only/',$args)){
        $parseOnly = TRUE;
    }

    if(preg_match('/\-\-int\-only/',$args)){
        $intOnly = TRUE;
    }

    if(preg_match('/\-\-debug/',$args)){
        $debug = TRUE;
    }

    if(preg_match('/\-\-progress/',$args)){
        $progress = TRUE;
    }
}

if($parseOnly == TRUE && ($intOnly == TRUE || $intFile != "interpret.py")){
    fwrite(STDERR, "Can't combine --parse-only with --int- arguments.\n");
    exit(10);
}

if($intOnly == TRUE && ($parseOnly == TRUE || $parseFile != "parse.php")){
    fwrite(STDERR, "Can't combine --int-only with --parse- arguments.\n");
    exit(10);
}

# Find all testfiles in this directory and in all sub directories if recures is active
if (!$recursive) 
    exec("find " . $testDir . " -maxdepth 1 -regex '.*\.src$'", $testArray);
else 
    exec("find " . $testDir . " -regex '.*\.src$'", $testArray);


# Output variable
$outputHTML = $HTMLHead;

# Testing in a cycle each test file
foreach($testArray as $currentFile){
    # Split the path string
    $pathArray = explode('/', $currentFile);
    $srcFile = explode('.', end($pathArray))[0];
    $currentDir = preg_replace( '/\/[^\/]*\.src/','', $currentFile,-1);

    if($currentDir != $lastDir){
        $lastDir = $currentDir;
        $outputHTML.="\t<tr>\n\t\t<th colspan=\"2\" class=\"dir\">$currentDir</th>\n\t<tr>\n";
    }
    
    # if(preg_match('/.*\.src/',$currentFile)){

    $fileName = $srcFile;

    # Get file name
    $fileName = preg_replace( '/\.src/','', $fileName,-1);
    
    # Get the filenames of all files
    $rcFile = preg_replace( '/\.src/','.rc', $currentFile,-1);
    $inFile = preg_replace( '/\.src/','.in', $currentFile,-1);
    $outFile = preg_replace( '/\.src/','.out', $currentFile,-1);
    $tempOutputFile = preg_replace( '/\.src/','.out_temp', $currentFile,-1);

    if($DEBUG_INTERNAL){
        fwrite(STDERR, "TEST \"$fileName\":\n");
        fwrite(STDERR, "\tSource file: \"$srcFile\"\n");
    }

    # Load or create return code file 
    if(file_exists($rcFile)){
        if($DEBUG_INTERNAL) 
            fwrite(STDERR, "\tReturn file OK\n");
        
        $rc = file_get_contents($rcFile);
        
    }else{
        if($DEBUG_INTERNAL) 
            fwrite(STDERR, "\tReturn file NOT FOUND, created rc file with 0\n");
        
        $rcTemp = fopen($rcFile, "w");
        fwrite($rcTemp, "0");
        $rc = file_get_contents($rcFile);
        fclose($rcTemp);
    }

    # Load or create input file 
    if(file_exists($inFile)){
        if($DEBUG_INTERNAL) 
            fwrite(STDERR, "\tInput  file OK\n");

        $in = file_get_contents($inFile);
    }else{
        if($DEBUG_INTERNAL) 
            fwrite(STDERR, "\tInput file NOT FOUND, created an empty input file\n");
        
        $inTemp = fopen($inFile, "w");
        fclose($inTemp);
    }
    
    # Load or create output file 
    if(file_exists($outFile)){
        if($DEBUG_INTERNAL) 
            fwrite(STDERR, "\tOutput file OK\n\n");
            
        $out = file_get_contents($outFile);
    }else{
        if($DEBUG_INTERNAL) 
            fwrite(STDERR, "\tOutput file NOT FOUND, created an empty output file\n\n");
        
        $outTemp = fopen($outFile, "w");
        fclose($outTemp);
    }

    # Create a temporary script output file 
    if(file_exists($tempOutputFile) && !$debug){
        fwrite(STDERR, "\tError $tempOutputFile file was not removed and still exists.\n\n");
        exit(10);
    }
    
    $tempOutput = fopen($tempOutputFile, "w");

    if($debug){
        unlink($tempOutputFile);
        $tempOutput = fopen($tempOutputFile, "w");
    } 

    if($intOnly == FALSE){
        # Create the testing command
        $command = "php7.4 " . $parseFile . " <" . $currentFile;

        if($DEBUG_INTERNAL) 
            fwrite(STDERR, "\tRunning command:\n\t\t $command\n\n");

        # Run the testing command
        exec($command, $tempOutputArray, $resultRc);

        $tempOutString = implode( "\n", $tempOutputArray);
        if($tempOutString) 
            $tempOutString .= "\n";

        fwrite($tempOutput, $tempOutString);
        fclose($tempOutput);

        if($DEBUG_INTERNAL)
            fwrite(STDERR, "\tRC test:\n\t\tShould be: $rc\n\t\tResult is: $resultRc\n\n\n");
            
        # Save result to output HTML
        $outputHTML .= "\t<tr>\n\t\t<td>\t$fileName</td>\n";

        # Return code check
        if($rc != $resultRc ){
            $outputHTML .= "\t\t<th class=\"failed\">FAILED</th>\n";
            $RcFailedCount++;
            $testState = FALSE;

        # XML Check
        }else if ($rc == 0){
            exec("java -jar $jexamFile $tempOutputFile $outFile",$diffResult,$diffRC);

            if($diffRC){
                $outputHTML .= "\t\t<th class=\"failed\">FAILED</th>\n";
                $XMLFailedCount++;
                $testState = FALSE;
            }else{
                $outputHTML .= "\t\t<th class=\"passed\">PASSED</th>\n";
                $XMLPassedCount++;
                $testState = TRUE;
            }

        # Test has passed
        }else{
            $outputHTML .= "\t\t<th class=\"passed\">PASSED</th>\n";
            $RcpassedCount++;
            $testState = TRUE;
        }

        $outputHTML .= "\t</tr>\n";

        if($progress){
            fwrite(STDERR, "$currentFile: ");
            if($testState)
                fwrite(STDERR, "\e[32mPASSED\e[0m\n");
            else
                fwrite(STDERR, "\e[31mFAILED\e[0m\n");
        }

        # Delete temporary files
        if(!$debug)
            unlink($tempOutputFile);

        #clear the output array
        $tempOutputArray = array();

    }else if($parseOnly == FALSE){

    }
}

$passedTotal =  $RcpassedCount + $XMLPassedCount;
$total = $RcFailedCount + $XMLFailedCount +$passedTotal;

$outputHTML .= "\t<tr>\n\t\t<th colspan=\"2\" class=\"result\">Results</th>\n\t</tr>\n";
$outputHTML .= "\t<tr>\n\t\t<th class=\"failed\">FAILED RC</th>\n\t\t<th>$RcFailedCount</th>\n\t</tr>\n";
$outputHTML .= "\t<tr>\n\t\t<th class=\"passed\">PASSED RC</th>\n\t\t<th>$RcpassedCount</th>\n\t</tr>\n";
$outputHTML .= "\t<tr>\n\t\t<th class=\"failed\">FAILED XML</th>\n\t\t<th>$XMLFailedCount</th>\n\t</tr>\n";
$outputHTML .= "\t<tr>\n\t\t<th class=\"passed\">PASSED XML</th>\n\t\t<th>$XMLPassedCount</th>\n\t</tr>\n";
$outputHTML .= "\t<tr>\n\t\t<th class=\"passed\">PASSED TOTAL</th>\n\t\t<th>$passedTotal/$total</th>\n\t</tr>\n";

$outputHTML .= $HTMLEnd;
echo $outputHTML;

function help(){
    echo "Testing script for parse.php and interpret.py

    --help                  shows this message
    --directory=path        selects the test directory
    --parse-only            only parser part will be tested
    --int-Only              only interpret part will be tested
    --parse-script=file     sets path to parse script file 
    --int-script=file       sets path to interpret script file
    --recursive             the testing script will look for tests in all sub directories
    --debug                 turns on debug mode
    --jexamxml=file         sets path to jexamxml
    --jexamcfg=file         sets path to jaxamxml options file

    --progress              shows test progress in stdout\n";
}
?>