#!/usr//bin/php
<?php
/**
 * This script will search the specified md files for markdown links
 * and check their validity.
 */

// Helper functions
function printHelp(){
  echo "Checks for broken links in file(s) specified".PHP_EOL;
  echo "Usage: checkmdlinks.php [options] <file> [<file>]..".PHP_EOL;
  echo "Options:".PHP_EOL;
  echo "  -h                  Print this help and exit".PHP_EOL;
  echo "  --root=<directory>  Set project root directory".PHP_EOL;
  echo "                      If not set, will use the current directory as the project root".PHP_EOL;
  echo "Example usage:".PHP_EOL;
  echo "Check all md files in a specific subdirectory for broken links".PHP_EOL;
  echo "  $ checkmdlinks.php --root=/path/to/project-root /path/to/project-root/path/to/docs/*.md".PHP_EOL;
}

// Colour string
function colour($string, $colour){
  switch($colour){
    case 'g':
      // Green for success
      return "\033[32m$string\033[0m";
    case 'y':
      // Yellow for warnings
      return "\033[33m$string\033[0m";
    case 'r':
      // Red for failures
      return "\033[31m$string\033[0m";
    case 'c':
      // Cyan for debugging
      return "\033[36m$string\033[0m";
    default:
      // White
      return "\033[0m$string";
  }
}

//WriteFile
function writefile($string){
  $fp = fopen('/tmp/MDLinkCheck_Report.csv', 'a');
  fwrite($fp, $string);
  fclose($fp);
}


// Parses file looking for markdown links
// Adds all links found to a list
// Passes the list of links to validateLinks() 
function lookForLinks($file){
  $fh = fopen($file, 'r');
  if($fh){
    $links = [];

    // Parse file and collect links
    $linenumber = 0;
    while(($line = fgets($fh)) !== false){
      ++$linenumber;
      preg_match_all('/\[([^]]*)\]\(([^)]*)\)/', $line, $matches);
      for($i=0;$i<count($matches[0]);++$i){
        $links[] = array(
          'link'  => $matches[0][$i],
          'title' => $matches[1][$i],
          'path'  => $matches[2][$i],
          'line'  => $linenumber
        );
      }
    }

    // Validate links
    if(count($links) >= 1){
      $prevdir = getcwd();
      chdir(dirname($file));  // Change working dir to test relative links
      validateLinks($file, $links);
      chdir($prevdir);
    }
    fclose($fh);
  }else{
    fwrite(STDERR, "Error: couldn't open $file".PHP_EOL);
  }
}

// Accepts an array of links to check for validity
// Accepts http/https, file links within the project, and links to doc headings (anchors)
// For each link, print out whether it is valid or not
function validateLinks($file, $links){
  foreach($links as $link){
    //print_r($link);
    $path = $link['path'];
    $text = $link['link'];
    $line = sprintf("%4s", 'L'.$link['line']);

    if(preg_match('/^https?:\/\//', $path)){
      // Path is a url
      $valid = checkURL($path);
    }elseif(preg_match('/^#/', $path)){
      // Path is anchor link to doc heading
      $valid = checkAnchor(basename($file), $path);
    }elseif(preg_match('/^mailto:/', $path)){
      // Path is an email address
      // TODO Maybe validate as valid email format
      continue;
    }else{
      // Path is a file
      $valid = checkFile($path);
    }

    // Print whether link is valid or not
    if($valid === true){
      echo colour("[✓] $line: $text".PHP_EOL, "g");
      writefile("$file;GREEN;$line: $text".PHP_EOL);
    }elseif($valid === false){
      echo colour("[✖] $line: $text".PHP_EOL, "r");
      writefile("$file;RED;$line: $text".PHP_EOL);
    }else{
      echo colour("[?] $line: $text - $valid".PHP_EOL, "y");
      writefile("$file;YELLOW;$line: $text".PHP_EOL);
    }
  }
}

// Check that a file link is valid
// Accepts paths both relative to project root (preceding /)
// as well as relative to the file containing the link
// Returns true if file exists or false if it does not
function checkFile($path){
  // Separate anchor link if it exists
  if(strpos($path, '#')){
    $parts = explode('#', $path, 2);
    $path = $parts[0];
    $anchor = $parts[1];

  }

  $checkpath = trim($path);
  if(substr($path,0,1) == '/'){
    // Path is relative to project root, prepand project path
    global $root;
    $checkpath = $root.$path;
  }

  // Handle links that contain %20 instead of spaces
  $checkpath = str_replace('%20', ' ', $checkpath);

  if(file_exists($checkpath)){
    if(isset($anchor)){
      return checkAnchor($checkpath, $anchor);
    }else{
      return true;
    }
  }else{
    return false;
  }
}

// Checks that a link to a documentation heading is valid
// Works with both manually placed <a id='keywork'></a> tags
// as well as github auto-generated heading anchors
function checkAnchor($file, $link){
  $regex = '/<a (id|name)=["'."']".str_replace('/', '\\/', substr($link,1))."['".'"]\/?>/';

  $fh = fopen($file, "r");
  if($fh){
    while(($line = fgets($fh)) !== false){
      if(preg_match('/^\s*#/', $line)){
        // This line is a heading
        // Mimic githubs auto-generated heading anchors
        $test = str_replace('# ', '#', $line);
        $test = str_replace(' ', '-', $test);
        $test = str_replace(['.', '(', ')'], '', $test);
        $test = preg_replace('/--+/', '-', $test);
        if(strpos(strtolower($test), strtolower($link)) !== false){
          // Matches
          fclose($fh);
          return true;
        }
      }

      // Line is not a heading or just didn't match, might still have anchor
      // Check for <a> anchor id
      if(preg_match($regex, $line)){
        fclose($fh);
        return true;
      }
    }

    fclose($fh);
  }else{
    fwrite(STDERR, "Error: couldn't open $file".PHP_EOL);
  }
  return false;
}

// Check if URL is valid
function checkURL($url){
  // Ignore errors on get_headers() because some IBM websites return
  // malformed responses with spaces
  $headers = @get_headers($url, 1);
  if($headers === false){
    // Malformed response (contains spaces when it shouldn't)
    if(!isset($http_response_header)){
      return "No Response";
    }elseif(strpos($http_response_header[0], '301 MOVED PERMANENTLY') !== false){
      $newurl = trim(str_replace('Location: ', '', $http_response_header[1]));
      $headers = get_headers($newurl, 1);
    }
  }

  $i = 0;
  while(isset($headers[$i])){
    if(strpos($headers[$i], '200') !== false){
      // URL link is valid
      return true;
    }elseif(strpos($headers[$i], '404') !== false){
      // URL link is invalid
      return false;
    }
    // If it's not 200 or 404, it could be 302 redirect
    // in which case there will be another status to check
    // hence incrementing $i in a loop
    $i++;
  }

  // Last response was not 200 or 404
  // Could be 401 unauthorized, 403 forbidden, 500 server error etc
  // Return the response code string for printing a warning
  return $headers[$i-1];
}

// Don't throw error on self-signed certs
stream_context_set_default( [
  'ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false,
  ],
]);

// Parse options
$optind = null;
$options = getopt("h", ["root::"]);

if(isset($options['h'])){
  printHelp();
  exit;
}

// Set project root
global $root;
if(isset($options["root"])){
  $root = $options["root"];
  if(!file_exists($root)){
    fwrite(STDERR, "Error: directory does not exist: $root".PHP_EOL);
    exit(1);
  }
  $files = array_slice($argv, 2);
}else{
  $root = getcwd();
  $files = array_slice($argv, 1);
}

if(count($files) == 0){
  fwrite(STDERR, "Error: you must specify at least one file to check".PHP_EOL);
  exit(1);
}

// Get file to check for broken links in
foreach($files as $file){
  if(!file_exists($file)){
    fwrite(STDERR, "Error: file does not exist: $file".PHP_EOL);
    continue;
  }
  echo "$file".PHP_EOL;

  lookForLinks($file);
}

?>
