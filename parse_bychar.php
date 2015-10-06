<?php

// DEV NOTE: This file is "the as-is contribution." It is not by any means an example of good programming.
// However, it does something useful, and that makes it intriguing. Please have a go at improving it!
// First order of business is to separate the presentation from the function itself.

// Function: parse_bychar($stream)
// Returns: array of serialized structure
// Uses: Build reports on document structure; create DTDs from instance documents; parse document fragments
// Caveats: non Well-Formed content can send the result nesting into the weeds, as expected.
// But new state rules can be added that can catch some runaway events and add closure.
// This parser was written for adding "language context rules" that supported scoping for GML markup.
// It can be configured to handle many variants of text-based markup that look like XML in principle.
// Contributed by Don R Day, Austin, TX, 5 Oct. 2015


//<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

// profile declarations
$lang = 'html';
$empty_stag = explode(' ', 'area base basefont br hr meta img col frame input isindex link param wbr iframe');
$closedby['dt'] = explode(' ', 'dt dd /dl');


// constant  declarations
$mode = 'CON';
$CR = chr(0x0D);
$LF = chr(0x0A);
$RE = $LF;
$LIT = '"';
$LITA = "'";
$TAB = chr(0x09);
$EOF = chr(0x1A);
$CRLF = '<br/>'; //$CR . $LF;
$BOM = '﻿';

$buf = '';
$tmp = '';

$lcchar = 'abcdefghijklmnopqrstuvwxyz'; 
$ucchar = strtoupper($lcchar); 
$namestarts = $lcchar . $ucchar ;
$letter = $lcchar . $ucchar . '0123456789' . '.-_:';

// variable presets
$fqgi = array();
$root = '';

// start of execution loop
$infile = 'Dictation Task.html';
if (isset($_GET['infile'])) {
	$infile = $_GET['infile'];
}
$content = trim(file_get_contents($infile)); 


if ($content !== false) {
	// Parse the content!
	$in_len = strlen($content);
	for ( $counter = 0; $counter < $in_len; $counter += 1) {
		$in_char = $content[$counter];
		// This function emits as it works; 
		// Ideally output should be buffered as an array that gets returned (JSON as an alternate data structure).
		cproc($in_char);
	}
} else {
	// Oops.
	echo "An error happened.<br/>";
}



// function declarations

// from http://bytes.com/topic/php/answers/519762-ascii-hex
// use as a diagnostic to discern whitespace and BOM,f or example; ordinarily not needed.
function ascii2hex($ascii) {
	$hex = '';
	for ($i = 0; $i < strlen($ascii); $i++) {
		$byte = strtoupper(dechex(ord($ascii{$i})));
		$byte = str_repeat('0', 2 - strlen($byte)).$byte;
		$hex.=$byte." ";
	}
	return $hex;
}

function resolve_ent($in){
switch($in) {
	case 'amp':
		$z = '&';
		break;
	case 'apos':
		$z = "'";
		break;
	case 'quot':
		$z = '"';
		break;
	case 'lt':
		$z = '<';
		break;
	case 'gt':
		$z = '>';
		break;
	default:
		$z = '&'.$in.';';
	}
	return $z;
} 


	
function cproc($x) {
	global $mode, $CR, $LF, $RE, $LIT, $LITA, $TAB, $EOF, $CRLF, $BOM, $buf, $tmp, $lcchar, $ucchar, $namestarts, $letter, $fqgi, $root, $attname, $empty_stag, $prevstag, $closedby;
	//--------------start of state engine
	$modech = $mode . $x;
	/*
	if ($mode == 'CON' & $x=$RE)
	{$modech = 'CON*';}
	*/
	
	// handle case of empty start tag; finish the markup parse, in effect:
	if ($mode == 'CON' && $x == '>')
	{$mode = 'CON'; return;}

	// disambiguate single hyphen in a comment from magic string '--'
	if ($mode == 'POSTCOM' && $x != '-')
	{$modech = 'COM*';}
	
	// disambiguate <tag/> (for which $buf has a value) from </tag> (for which $buf is empty)
	if ($modech == 'TAG/' && $buf == '')
	{$modech = 'TAG*';}
	
	// check if the character is valid for an attname (different mode than for end of tag delimiter "/" )
	if ($mode == 'PREATT') { 
  		if (strpos($namestarts,$x) <> 0)
  		{$modech = 'PREATT*';}
	}
	
	// check for attribute values with no quotes; generate a phantom event.
	// mode is implicitly ATV already, but the end event is any space or > character,
	// so ATV won't break out without another mode to check for ' >' context
	if ($mode == 'ATL' && strpos('\''.'\"'.$namestarts, $x) != 0) {
		$buf = $x;
		$mode = 'SATV'; 
		return;
	}
  
	// diagnostic, by-character status of modes
	//echo "____ '$modech' <br />";
	
	
	switch($modech) {
		case 'CON<':
			// weed out whitespace and BOM noise
			if ($buf <> $LF & $buf <> '' & $buf <> $BOM) { 
				// check for whitespace between elements; what is normal XML treatment for </p>~<p> in well-formed? Must be preserve
				// Because the initial state is "CON" the DOCTYPE comes through here as content. Better way to debug?
				if (substr($buf,0,7) == 'DOCTYPE') {
					echo '<span style="color:red;">|</span>COMMENT:<span style="color:darkgreen;">' . htmlspecialchars($buf) . '</span><br/>';
				} else {
					echo '<span style="color:red;">-</span>'.'<i style="color:blue;">' . htmlspecialchars($buf) . '</i>'. $CRLF;
				}
			}
			$buf = '';
			$mode = 'TAG';
			break;
	
		case 'CON&':
			//echo '-' . $buf . $CRLF;
			$tmp = $buf;
			$buf = '';
			$mode = 'CONENT';
			break;
	
		case 'CONENT;':
			$buf = $tmp . resolve_ent($buf);
			$mode = 'CON';
			break;
	
		case 'TAG>':
			$buf = strtolower($buf);
			// check if any open conditions need closed
			// if this tag is in the array for the $prevstag...
			//if (array_search($buf, $closedby[$prevstag]) != FALSE) {
				// process ETAG event for prevstag
				//$val = array_pop($fqgi); 
				//echo '</ul><span style="color:red;">)</span>' .  '<b>' . $val . ':' . $prevstag .'</b>'  . $CRLF;
			//}
			echo '<span style="color:red;">(</span>' . '<b>' . $buf .'</b>' . $CRLF;
			array_push($fqgi, $buf); 
			$result = count($fqgi);
			print "<ul>"; 
			//print "__1 (pushed fqgi value: ".$result.":".$buf.")<br />"; 
			if ($result == 1) { $root = $buf; }
			// test for empty start-tag declaration
			if (in_array($buf, $empty_stag)) {
				echo '</ul><span style="color:red;">)</span>' .  '<b>' . $buf .'</b>'  . $CRLF;
				$val = array_pop($fqgi); 
			}
			$prevstag = $buf;
			$buf = '';
			$mode = 'CON';
			break;
	
		case 'ETAG>':
			$buf = strtolower($buf);
			echo '</ul><span style="color:red;">)</span>' .  '<b>' . $buf .'</b>'  . $CRLF;
			$val = array_pop($fqgi); 
			//print "__1 (popped fqgi value: ".$val.")<br />"; 
			$buf = '';
			$mode = 'CON';
			break;
	
		case 'PI>':
			echo '<span style="color:red;">?</span>PI:' . $buf . $CRLF;
			$buf = '';
			$mode = 'CON';
			break;
	
		case 'DS>':
			echo '<span style="color:red;">?</span>DS:' . $buf . $CRLF;
			$buf = '';
			$mode = 'CON';
			break;
	
		case 'DS-':
			$mode = 'PRECOM';
			break;
	
		case 'TAG/': // case of short stag with no attributes: <tag/>
			$buf = strtolower($buf);
			echo '<span style="color:red;">(</span>' .  '<b>' . $buf .'</b>'  . $CRLF;
			array_push($fqgi, $buf); 
			print "<ul>";
			//print "__2 (pushed fqgi value: ".$buf.")<br />"; 
			echo '</ul><span style="color:red;">)</span>' .  '<b>' . $buf .'</b>'  . $CRLF;
			$val = array_pop($fqgi); 
			//print "__2 (popped fqgi value: ".$val.")<br />"; 
			$buf = '';
			$mode = 'CON';
			// This mode exits with > still ahead; see test and exit for this condition early in the function.
			break;
	
		case 'TAG*': // look-ahead case of end tag: </tag>
			$buf = '';
			$mode = 'ETAG';
			break;
	
		case 'TAG?': // &  $buf == '')
			$mode = 'PI';
			break;
	
		case 'TAG!': // &  $buf == ''
			$mode = 'MD';
			break;
	
		case 'MD-': // first '-'; is this a comment yet?
			$mode = 'PRECOM';
			break;
	
		case 'MD"': // possibly parsing a name in a doctype; return the string to the buffer for now
			$buf = $buf . $LIT;
			$mode = 'MDN';
			break;
	
		case 'MDN"': // possibly parsing a name in a doctype; return the string to the buffer for now
			$buf = $buf . $LIT;
			$mode = 'MD';
			break;
		
		case 'PRECOM-': // second '-'?; Yes,this is a comment
			//echo '** (buf going into comment):' . $buf . $CRLF;
			$buf = '';
			$mode = 'COM';
			break;
	
		case 'COM*':
			//echo "|COMMENT with hyphen reset:[$buf]$CRLF";
			// put back in the hyphen removed by previous PRECOM- event
			$buf = $buf . '-' . $x;
			$mode = 'COM';
			break;
	
		case 'COM-':
			$mode = 'POSTCOM';
			break;
	
		case 'POSTCOM-':
			echo "<span style='color:red;'>|</span>COMMENT:<span style='color:darkgreen;'>" . htmlspecialchars($buf) . "</span>$CRLF";
			$buf = '';
			$mode = 'MD';
			break;
	
		case 'PRECOM>':
			$mode = 'CON';
			break;
	
		case 'MD>':
			$mode = 'CON';
			break;
	
	
		case 'MD[':
			$mode = 'DS';
			break;
	
		case 'DS]':
			echo "<span style='color:red;'>|</span>MD:[$buf]$CRLF";
			$buf = '';
			$mode = 'MD';
			break;
	
	// currently a single - in a comment throws back into PRECOM mode. 
	// Ideally the next non-hypen should return the state to Comment.
	
	
	// 'DS<'     say 'DS**'buf; buf=''; mode='DSTMP'
	// 'DSTMP>'  say 'DSTMP**'buf; buf=''; mode='DS'
	// 'DSTMP!'  say 'in DS decls'; buf=''
	// switch modes to gather declaration type ("ENTITY"),
	//   then entname and entval; restore to DS 
	
	// 'DS['     mode='MS'; Say '|MS:'buf; buf=''
	// 'MS]'     mode='DS'
	
	// 'CON*'    buf = buf'\n'
	// 'COM*'    buf = buf||'-'||ch; mode='COM'
	
		
		case 'TAG ':
			$tmp = $buf; $buf=''; 
			$mode = 'PREATT';
			break;
	
		case 'PREATT>':
			//echo 'A' . $attname . ' CDATA ' . $buf . $CRLF;
			$buf = $tmp;
			$buf = strtolower($buf);
			echo '<span style="color:red;">(</span>' . '<b>' . $buf .'</b>' . $CRLF;
			array_push($fqgi, $buf); 
			$result = count($fqgi);
			print "<ul>";
			//print "__3a (pushed fqgi value: ".$result.":".$buf.")<br />"; 
			if ($result == 1) { $root = $buf; }
			// test for empty start-tag declaration
			if (in_array($buf, $empty_stag)) {
				echo '</ul><span style="color:red;">)</span>' .  '<b>' . $buf .'</b>'  . $CRLF;
				$val = array_pop($fqgi); 
			}
			$buf = '';
			$mode = 'CON';
			break;
	
		case 'PREATT/':  // case of empty stag with attributes
			//echo 'A' . $attname . ' CDATA ' . $buf . $CRLF;
			$buf = $tmp;
			$buf = strtolower($buf);
			echo '<span style="color:red;">(</span>' .  '<b>' . $buf .'</b>'  . $CRLF;
			array_push($fqgi, $buf); 
			print "<ul>";
			//print "__3b (pushed fqgi value: ".$buf.")<br />"; 
			echo '</ul><span style="color:red;">)</span>' . '<b>' . $buf .'</b>' . $CRLF;
			$val = array_pop($fqgi); 
			//print "__3 (popped fqgi value: ".$val.")</ul>"; 
			$buf = '';
			$mode = 'CON';
			break;
	
		case 'PREATT*':
			$buf = $x; 
			$mode = 'ATN';
			break;
	
		case 'ATN=':
			$attname = $buf;
			$buf = ''; 
			$mode = 'ATL';
			break;
	
		case 'ATL"':
			$attdelim = '"LIT"';
			$attarg = 'ATV"'.$LIT."'";
			$mode = 'ATV'; 
			break;
	
		case "ATL'":
			$attdelim = "'LITA'";
			$attarg = "ATV'".$LITA.'"';
			$mode = 'ATV'; 
			break;
	
		case 'ATV"':
			echo '<span style="color:red;">A</span>' . $attname . ' CDATA ' . $buf . $CRLF;
			$attarg = '';
			$mode = 'PREATT';
			break;
		
		case "ATV'":
			echo '<span style="color:red;">A</span>' . $attname . ' CDATA ' . $buf . $CRLF;
			$mode = 'PREATT';
			$attarg = '';
			break;
	
		case 'SATV ':
			// token attval ended by space character; resume PREATT mode
			echo '<span style="color:red;">A</span>' . $attname . ' CDATA ' . $buf . $CRLF;
			$attarg = '';
			$mode = 'PREATT';
			break;

		case 'SATV>':
			// token attval ended by end of tag delimiter; process PREATT> event!
			echo '<span style="color:red;">A</span>' . $attname . ' CDATA ' . $buf . $CRLF;
			$buf = $tmp;
			$buf = strtolower($buf);
			echo '<span style="color:red;">(</span>' . '<b>' . $buf .'</b>' . $CRLF;
			array_push($fqgi, $buf); 
			$result = count($fqgi);
			print "<ul>";
			//print "__3a (pushed fqgi value: ".$result.":".$buf.")<br />"; 
			if ($result == 1) { $root = $buf; }
			// test for empty start-tag declaration
			if (in_array($buf, $empty_stag)) {
				echo '</ul><span style="color:red;">)</span>' .  '<b>' . $buf .'</b>'  . $CRLF;
				$val = array_pop($fqgi); 
			}
			$buf = '';
			$mode = 'CON';
			break;
	
	// some line end handling
		case  'CON'.$RE:
			$buf =  $buf . $RE;
			break;
	
		case 'CON'.$CR:
			/*nop*/
			break;
	
	// keep concatenating $x onto $buf		
		default:
			//if ($mode <> 'COM') {
				if ($buf == $CR)
					{ echo "buf=CR$CRLF"; }
				$buf = $buf . $x;
			//}
			//else
			//{ /*echo "failed mode: $mode with character $x and buf $buf";*/ }
			//break;
		}
	}
