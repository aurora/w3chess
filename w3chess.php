<?php
/*
 w3Chess
	Mail- and Web-based Chess

 Copyright (C) 1998-2004 Tobias Mueller

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA

 PHP port of w3chess.c (request-based, 1:1).
 Note: header()/mail()/hexDec() are renamed to page_header()/w3mail()/hex2dec()
 because they collide with PHP builtins.
*/

require_once __DIR__.'/config.php';
require_once __DIR__.'/defaults.php';
require_once __DIR__.'/lang.php';

/* the C programm relies on the CGI working directory for its relative */
/* pathes (DATAPATH, HTML_HEADER, ...), so do the same here */
chdir(__DIR__);

/* don't let PHP diagnostics corrupt the html output (like the C original) */
if (PHP_SAPI !== 'cli') ini_set('display_errors', '0');

/* some global variables and fields */
$GPLAYER1 = '';
$GPLAYER2 = '';
$GNICK1 = '';
$GNICK2 = '';
$GBOARD = '';
$GPASS = '';
$GPASS2 = '';
$gmessage = null;
$gmoves = null;
$gnummoves = 0;
$gnomail1 = 0;
$gnomail2 = 0;
$globalpass = 1;

$query = null;


/*====*/

/* MYISUPPER */
function myisupper($c) {
	if (!ctype_upper($c))
		return 0;
	else
		return 1;
}

/* MYISLOWER */
function myislower($c) {
	if (!ctype_lower($c))
		return 0;
	else
		return 1;
}


/* HEX2DEC (C: hexDec) */
function hex2dec($hex) {
	$ret = 0;
	$pos = 0;
	while ($pos < 2) {
		$ret *= 16;
		$c = ($pos < strlen($hex)) ? $hex[$pos] : '';
		switch ($c) {
			case '0':
			break;
			case '1':
				$ret += 1;
			break;
			case '2':
				$ret += 2;
			break;
			case '3':
				$ret += 3;
			break;
			case '4':
				$ret += 4;
			break;
			case '5':
				$ret += 5;
			break;
			case '6':
				$ret += 6;
			break;
			case '7':
				$ret += 7;
			break;
			case '8':
				$ret += 8;
			break;
			case '9':
				$ret += 9;
			break;
			case 'A':
			case 'a':
				$ret += 10;
			break;
			case 'B':
			case 'b':
				$ret += 11;
			break;
			case 'C':
			case 'c':
				$ret += 12;
			break;
			case 'D':
			case 'd':
				$ret += 13;
			break;
			case 'E':
			case 'e':
				$ret += 14;
			break;
			case 'F':
			case 'f':
				$ret += 15;
			break;
		}
		$pos++;
	}
	return $ret;
}

/* QUERYPARAM - extract one parameter from the query-string, like the
   C-Code does it with strstr(query,"NAME=") and cutting at '&' */
function queryParam($name) {
	global $query;
	$pos = strpos($query, $name.'=');
	if ($pos === false)
		return null;
	$pos += strlen($name) + 1;
	$end = strpos($query, '&', $pos);
	if ($end === false)
		return substr($query, $pos);
	return substr($query, $pos, $end - $pos);
}

/* CCHAR - access a byte of a string with C-like bounds (returns '' when
   out of range, where C would read the terminating NUL or garbage) */
function cchar($s, $i) {
	if ($i >= 0 && $i < strlen($s))
		return $s[$i];
	return '';
}

/* CTIME - the string C's ctime() returns */
function ctime($ts) {
	return sprintf("%s %s %2d %02d:%02d:%02d %4d\n",
		date('D', $ts), date('M', $ts), date('j', $ts),
		date('H', $ts), date('i', $ts), date('s', $ts), date('Y', $ts));
}

/* ERRORWIN */
function errorwin($mesg, $pass, $id, $swap) {
	printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">", BOARDGRID, BOARDMARGBG, BOARDMARGFG);
	printf("<b>%s</b><br><br>\n", $mesg);
	if (($id !== null) && ($id !== '')) {
		printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
		printf("<INPUT TYPE=hidden name=ACTION value=RESUME>\n");
		printf("<INPUT TYPE=hidden name=ID value=\"%s\">\n", $id);
		if ($swap != 0)
			printf("<INPUT TYPE=hidden name=SWAP value=\"%d\">\n", $swap);
		if (($pass !== null) && ($pass !== ''))
			printf("<INPUT TYPE=hidden name=PASS value=\"%s\">\n", $pass);
		printf("<INPUT TYPE=image src=\"%s\" ALT=\"%s\" border=\"0\">", BUTTONYES, MSGOK);
		printf("</FORM>\n");
	} else {
		printf("<a href=\"%s\">", $_SERVER['SCRIPT_NAME']);
		printf("<img src=\"%s\" ALT=\"%s\" border=\"0\">", BUTTONYES, MSGOK);
		printf("</a>\n");
	}
	printf("</font></td></tr></table></td></tr></table><br>\n");
}

/* BACK2BOARD */
function back2board($mesg, $PASS, $ID, $swap) {
	printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">", BOARDGRID, BOARDMARGBG, BOARDMARGFG);
	printf("<b>%s</b><br>\n", $mesg);
	if (($ID !== null) && ($ID !== '')) {
		if (($PASS !== null) && ($PASS !== '')) {
			printf("<META http-equiv=\"refresh\" content=\"%d; URL=%s?ACTION=RESUME&ID=%s&PASS=%s&SWAP=%d\">\n", WAITTIME, $_SERVER['SCRIPT_NAME'], $ID, $PASS, $swap);
			printf("<br><a href=\"%s?ACTION=RESUME&ID=%s&PASS=%s&SWAP=%d\"><img src=\"%s\" alt\"%s\" border=\"0\"></a>", $_SERVER['SCRIPT_NAME'], $ID, $PASS, $swap, BUTTONYES, MSGOK);
		} else {
			printf("<META http-equiv=\"refresh\" content=\"%d; URL=%s?ACTION=RESUME&ID=%s&SWAP=%d\">\n", WAITTIME, $_SERVER['SCRIPT_NAME'], $ID, $swap);
			printf("<br><a href=\"%s?ACTION=RESUME&ID=%s&SWAP=%d\"><img src=\"%s\" alt\"%s\" border=\"0\"></a>", $_SERVER['SCRIPT_NAME'], $ID, $swap, BUTTONYES, MSGOK);
		}
	} else {
		printf("<META http-equiv=\"refresh\" content=\"%d; URL=%s\">\n", WAITTIME, $_SERVER['SCRIPT_NAME']);
		printf("<br><a href=\"%s\"><img src=\"%s\" alt\"%s\" border=\"0\"></a>", $_SERVER['SCRIPT_NAME'], BUTTONYES, MSGOK);
	}
	printf("<br>\n");
	printf("</font></td></tr></table></td></tr></table><br>\n");
}

/* CHECKMAIL */
function checkmail($mail) {
	if ($mail === null)	/* no NULL-pointer */
		return -1;
	if ($mail === '')	/* not empty */
		return -2;
	$et = strpos($mail, '@');
	if ($et === false)	/* should include a @ */
		return -3;
	$dot = strrpos($mail, '.');	/* LAST . */
	if ($dot === false)	/* should include a . before toplevel domainname */
		return -4;
	if ($et > $dot)	/* et should be before dot */
		return -5;
	if ($et === 0)	/* no username given */
		return -6;
	if (($dot - $et - 1) < 2)	/* domainname must have at least 2 characters */
		return -7;
	$i1 = strlen(substr($mail, $dot)) - 1;
	if (($i1 != 2) && ($i1 != 3) && ($i1 != 4))	/* topleveldomain must have two, three or four digits */
		return -8;

	return 0;	/* ok, could be valid */

}

/* PIECEPOINTER */
function piecepointer($piece) {
	switch ($piece) {
		case 'r':
			return array(TSHORTR, TLONGR, BROOKE, SBROOKE, SSBROOKE);
		case 'p':
			return array(TSHORTP, TLONGP, BPAWN, SBPAWN, SSBPAWN);
		case 'n':
			return array(TSHORTN, TLONGN, BKNIGHT, SBKNIGHT, SSBKNIGHT);
		case 'k':
			return array(TSHORTK, TLONGK, BKING, SBKING, SSBKING);
		case 'q':
			return array(TSHORTQ, TLONGQ, BQUEEN, SBQUEEN, SSBQUEEN);
		case 'b':
			return array(TSHORTB, TLONGB, BBISHOP, SBBISHOP, SSBBISHOP);
		case 'E':
		case 'e':
			return array(TSHORTE, TEMPTY, EMPTYIMG, '', '');
		case 'R':
			return array(TSHORTR, TLONGR, WROOKE, SWROOKE, SSWROOKE);
		case 'P':
			return array(TSHORTP, TLONGP, WPAWN, SWPAWN, SSWPAWN);
		case 'N':
			return array(TSHORTN, TLONGN, WKNIGHT, SWKNIGHT, SSWKNIGHT);
		case 'K':
			return array(TSHORTK, TLONGK, WKING, SWKING, SSWKING);
		case 'Q':
			return array(TSHORTQ, TLONGQ, WQUEEN, SWQUEEN, SSWQUEEN);
		case 'B':
			return array(TSHORTB, TLONGB, WBISHOP, SWBISHOP, SSWBISHOP);
	}
	return array(null, null, null, null, null);
}


/* QUERY2STRING */
function query2String(&$string) {
	/* The string could only get shorter, so we can need no extra space allocated */
	$i = 0;
	$n = strlen($string);

	while ($i < $n) {
		$c = $string[$i];

		if ($c === '%') {
			$hex = substr($string, $i + 1, 2);
			if ($hex === '')
				break;
			$string = substr_replace($string, chr(hex2dec($hex)), $i, 1 + strlen($hex));
			$n = strlen($string);
			continue;	/* re-examine this position, like the C-Code does */
		}

		if ($c === '+')
			$string[$i] = ' ';

		if ($c === '<')
			$string[$i] = '[';

		if ($c === '>')
			$string[$i] = ']';

		$i++;
	}
}


/* HEADER (renamed, collides with PHP-builtin) */
function page_header() {
	header('Content-type: text/html');

	if (defined('HTML_HEADER') && is_file(HTML_HEADER)) {
		readfile(HTML_HEADER);
	} else {
		printf("<HTML><HEAD>\n");
		printf("<LINK REL=\"icon\" HREF=\"%s/favicon.ico\" TYPE=\"image/ico\">\n", IMGURLPREFIX);
		printf("<TITLE>%s</TITLE>\n", DEFTITLE);
		printf("</HEAD><BODY bgcolor=#ffef89 text=#000000 vlink=#000000 alink=#000000 link=#000000>\n");
		printf("<center>\n");
	}

	printf("<!-- %s -->\n", DEFTITLE);

}

/* FOOTER */
function page_footer() {
	printf("<!-- %s -->\n", DEFTITLE);

	if (defined('HTML_FOOTER') && is_file(HTML_FOOTER)) {
		readfile(HTML_FOOTER);
	} else {
		printf("</center>\n");
		printf("<table border=0 width=100%%><tr><td align=left>\n");
		printf("<a style=\"font-weight:bold;text-decoration:none;\" target=\"_blank\" href=\"%s\" title=\"%s\">&copy;</a>", DEFURL, DEFTITLE);
		printf("</td>\n");
		printf("<td align=right>\n");
		if (defined('ADMIN')) {
			printf("<a style=\"font-weight:bold;text-decoration:none;\" href=\"%s?ACTION=ADMIN\">&pi;</a></td></tr></table>\n", $_SERVER['SCRIPT_NAME']);
		} else {
			printf("<a style=\"font-weight:bold;text-decoration:none;\" target=\"_blank\" href=\"http://german.imdb.com/Title?0113957\">&pi;</a></td></tr></table>\n");
		}
		printf("</BODY></HTML>\n");
	}

}

/* REWRITEDATA */
function rewriteData($ID, $PASS, $MSG, $MAIL, $swap) {
	global $GNICK1, $GNICK2, $GPLAYER1, $GPLAYER2, $GPASS, $GPASS2, $GBOARD,
	       $gmessage, $gmoves, $gnummoves, $gnomail1, $gnomail2, $globalpass;

	/* swap=1: SWAP Player */
	if ($swap != 1)
		$swap = 0;

	/* open datafile */
	$path = DATAPATH.'/'.$ID;
	$datei = @fopen($path, 'w');
	if ($datei === false) {
		errorwin(ERROROPEN, $PASS, $ID, 0);
		return;
	}

	$out = '';
	$i1 = 0;
	while ($i1 < 2) {
		if ($i1 == $swap) {
			/* User 1 has own password */
			if ($globalpass == 0) {
				$out .= $GPASS;
				$out .= ':';
				if ($gnomail1 == 1) {
					$out .= '1';
					$out .= ':';
				}
			}
			$out .= $GNICK1;
			$out .= "\n";
			if ($MAIL === null)
				$out .= $GPLAYER1;
			else
				$out .= $MAIL;
			$out .= "\n";
			/* End of Data for User 1 */
		}

		if ($i1 != $swap) {
			/* User 2 has own password */
			if ($GPASS2 !== '') {
				$out .= $GPASS2;
				$out .= ':';
				if ($gnomail2 == 1) {
					$out .= '1';
					$out .= ':';
				}
			}

			$out .= $GNICK2;
			$out .= "\n";
			$out .= $GPLAYER2;
			$out .= "\n";
			/* End of Data for User 2 */
		}

		$i1++;
	}

	if ($PASS !== null)
		$out .= $PASS;
	else
		$out .= $GPASS;
	if ($gmoves !== null)
		$out .= sprintf("\n%d\n%s\n%s", $gnummoves, $gmoves, $GBOARD);
	else
		$out .= sprintf("\n%d\n\n%s", $gnummoves, $GBOARD);
	if ($MSG !== null) {
		$out .= "\n";
		$out .= $MSG;
	} else {
		if ($gmessage !== null) {
			$out .= "\n";
			$out .= $gmessage;
		}
	}
	fwrite($datei, $out);
	fclose($datei);
	@chmod($path, 0600);
	/* re-initialise the variables */
	readgamedata($ID);
}

function rewriteGame($ID, $PASS, $MSG) {
	rewriteData($ID, $PASS, $MSG, null, 0);
}

function rewriteEmail($ID, $MAIL) {
	rewriteData($ID, null, null, $MAIL, 0);
}

function rewriteGameAndSwap($ID, $PASS, $MSG) {
	rewriteData($ID, $PASS, $MSG, null, 1);
}

/* GENPASS */
function genPass() {
	$PASS = '';
	for ($i1 = 0; $i1 < 10; $i1++) {
		do {
			$c = chr(random_int(30, 121));
		}
		while ((!ctype_alnum($c)) || ($c === ' ') || ($c === '[') || ($c === ']'));
		$PASS .= $c;
	}
	return $PASS;
}

/* MOVESOUT */
function movesout($wohin, $moves, $nmoves, $ID, $swap) {
	global $gnummoves, $GNICK1, $GNICK2;

	/* wohin = true: HTML-output (stdout in C) */
	/* wohin = resource: MAIL */

	if ($nmoves == 0)
		return;

	if ($wohin === true) {
		printf("<br><b><u>%s</u></b><br>\n", RESMOVES);
		printf("<table border=0>");
	} else {
		fprintf($wohin, "%s\n", RESMOVES);
	}

	if (defined('REVERSEMOVELIST')) {
		$step = -1;
		$i1 = $nmoves;
		$z1 = $nmoves * 7 - 7;
	} else {
		$step = 1;
		$i1 = 1;
		$z1 = 0;
	}

	while (($step == -1 && $i1 >= 1) || ($step == 1 && $i1 <= $nmoves)) {
		if ((($gnummoves + $i1) % 2) == 0)		/* gnummoves, cause NICK1 and NICK2 from old file is ment */
			$z3 = $GNICK2;
		else
			$z3 = $GNICK1;


		list($dn1, $z2, $dn2, $sm, $dn4) = piecepointer($moves[$z1]);  /* piece, shortname, longname, image, smallimage, strokenimage */

		if ($moves[$z1] !== $moves[$z1 + 5]) {
			list($sh, $z4, $dn1, $dn2, $dn3) = piecepointer($moves[$z1 + 5]);  /* piece, shortname, longname, image, smallimage, strokenimage */
		} else {
			$z4 = null;
		}

		if ($wohin === true) {
			printf("<tr><td align=center><b>%d</b></td><td align=center><img align=center src=\"%s\" alt=\"%s\" border=0></td><td align=left><a href=\"%s?ACTION=VIEW&NUM=%d&ID=%s&SWAP=%d\" TARGET=ViewWin>%s%s &raquo; %s%s", $i1, $sm, $z2, $_SERVER['SCRIPT_NAME'], $i1 - 1, $ID, $swap, $moves[$z1 + 1], $moves[$z1 + 2], $moves[$z1 + 3], $moves[$z1 + 4]);

			if ($moves[$z1 + 6] !== 'e' || $moves[$z1 + 5] === 'O') {  /* Hit piece the normal way or en passant */
				if ($moves[$z1 + 5] === 'O' && $moves[$z1] === 'P') {
					list($dn1, $lo, $dn2, $dn3, $st) = piecepointer('p');  /* piece, shortname, longname, image, smallimage, strokenimage */
				} else {
					if ($moves[$z1 + 5] === 'O' && $moves[$z1] === 'p') {
						list($dn1, $lo, $dn2, $dn3, $st) = piecepointer('P');  /* piece, shortname, longname, image, smallimage, strokenimage */
					} else {
						list($dn1, $lo, $dn2, $dn3, $st) = piecepointer($moves[$z1 + 6]);  /* piece, shortname, longname, image, smallimage, strokenimage */
					}
				}
				printf("<img align=center src=\"%s\" alt=\"%s\" border=0>\n", $st, $lo);
			}
			list($dn1, $lo, $dn2, $sm, $dn3) = piecepointer($moves[$z1 + 5]);  /* piece, shortname, longname, image, smallimage, strokenimage */
			if ($moves[$z1] !== $moves[$z1 + 5] && $moves[$z1 + 5] !== 'O')
				printf(" &rArr; <img align=center src=\"%s\" alt=\"%s\" border=0>\n", $sm, $z4);

			$m5 = substr($moves, $z1, 5);
			if ($m5 === 'kE8G8')
				printf(" (%s)", SMCKS);
			if ($m5 === 'kE8C8')
				printf(" (%s)", SMCQS);
			if ($m5 === 'KE1G1')
				printf(" (%s)", SMCKS);
			if ($m5 === 'KE1C1')
				printf(" (%s)", SMCQS);

			if ($moves[$z1 + 5] === 'O')
				printf(" (%s)", SMEP);

			printf("</td><td align=right>(%s)</td></tr>\n", $z3);
		} else {
			fprintf($wohin, "%d) %s: %s%s -> %s%s", $i1, $z2, $moves[$z1 + 1], $moves[$z1 + 2], $moves[$z1 + 3], $moves[$z1 + 4]);

			if ($moves[$z1 + 6] !== 'e' || $moves[$z1 + 5] === 'O') {  /* Hit piece the normal way or en passant */
				if ($moves[$z1 + 5] === 'O' && $moves[$z1] === 'P') {
					list($dn1, $lo, $dn2, $dn3, $st) = piecepointer('p');  /* piece, shortname, longname, image, smallimage, strokenimage */
				} else {
					if ($moves[$z1 + 5] === 'O' && $moves[$z1] === 'p') {
						list($dn1, $lo, $dn2, $dn3, $st) = piecepointer('P');  /* piece, shortname, longname, image, smallimage, strokenimage */
					} else {
						list($dn1, $lo, $dn2, $dn3, $st) = piecepointer($moves[$z1 + 6]);  /* piece, shortname, longname, image, smallimage, strokenimage */
					}
				}
				fprintf($wohin, " [%s %s]", MAILHIT, $lo);
			}

			if ($moves[$z1] !== $moves[$z1 + 5] && $moves[$z1 + 5] !== 'O')
				fprintf($wohin, " -> %s", $z4);

			$m5 = substr($moves, $z1, 5);
			if ($m5 === 'kH5H7')
				fprintf($wohin, " (%s)", SMCKS);
			if ($m5 === 'kH5H3')
				fprintf($wohin, " (%s)", SMCQS);
			if ($m5 === 'KA5A7')
				fprintf($wohin, " (%s)", SMCKS);
			if ($m5 === 'KA5A3')
				fprintf($wohin, " (%s)", SMCQS);

			if ($moves[$z1 + 5] === 'O')
				fprintf($wohin, " (%s)", SMEP);

			fprintf($wohin, " (%s)\n", $z3);
		}

		$z1 += 7 * $step;
		$i1 += $step;
	}


	if ($wohin === true) {
		printf("</table>\n");
	}

}

/* ISNUMBER */
function isnumber($string) {
	if ($string === '')
		return 0;
	if (strlen($string) < 8)
		return 0;
	if ($string[0] === 'X')	/* Could be an game with missing partner */
		$string = substr($string, 1);
	for ($i = 0; $i < strlen($string); $i++) {
		if (!ctype_digit($string[$i]))
			return 0;
	}
	return 1;
}

/* DELETEOLDGAMES */
function deleteoldgames($html_out) {
	global $GNICK1, $GNICK2, $gmessage;

	$gamect = 0;
	$opengct = 0;

	$gamedir = @opendir(DATAPATH);
	if ($gamedir === false) {
		errorwin(ERRORGAMEDIR, '', '', 0);
		return;
	}

	while (($direntry = readdir($gamedir)) !== false) {
		if (isnumber($direntry)) {
			$path = DATAPATH.'/'.$direntry;
			$mtime = @filemtime($path);
			if ($mtime !== false) {
				$days = (int)((time() - $mtime) / 86400);
				if (defined('ALLOWREMOVE') && $days >= DELETEAFTER) {
					@unlink($path);
				} else {
					if ($html_out == 1) {
						$gamect++;
						if ($direntry[0] === 'X') {
							if ($opengct == 0) {
								printf("<tr bgcolor=\"%s\"><td align=center>\n", BOARDMARGBG);
								printf("<font size=\"+1\" color=\"%s\"><b>%s</b></font>\n", BOARDMARGFG, STARTSEARCH);
								printf("</td></tr><tr><td align=\"center\">\n");

								printf("<table border=0>\n");
							}
							$opengct++;
							readgamedata($direntry);
							printf("<tr><td align=center><b>%s%s.%s%s.%s%s%s%s</b> &nbsp; </td><td align=center><a href=\"%s?ID=%s&ACTION=JOIN\">", cchar($direntry, 7), cchar($direntry, 8), cchar($direntry, 5), cchar($direntry, 6), cchar($direntry, 1), cchar($direntry, 2), cchar($direntry, 3), cchar($direntry, 4), $_SERVER['SCRIPT_NAME'], $direntry);
							if ($GNICK1 !== '')
								printf("%s</a></td>\n", $GNICK1);
							else
								printf("%s</a></td>\n", $GNICK2);
							if ($gmessage !== null)
								printf("<td align=left><font color=\"%s\"> &nbsp; %s</font></td>", MSGCOLOR, substr($gmessage, 1));
							else
								printf("<td> &nbsp; </td>");
							printf("</tr>\n");
						}
					}
				}
			} else {
				$e = error_get_last();
				printf("%s\n", is_array($e) && isset($e['message']) ? $e['message'] : "stat ".$path." failed");
			}
		}
	}

	if ($html_out == 1) {
		if ($opengct > 0)
			printf("</table></td></tr>\n");

		printf("<tr bgcolor=\"%s\"><td align=center>\n", BOARDMARGBG);
		printf("<font size=\"-1\" color=\"%s\"><b>%u %s, %u %s</b></font>\n", BOARDMARGFG, $gamect, STARTNUMBERGAMES, $opengct, STARTNUMBEROGAMES);
		printf("</td></tr></table>\n");
	}

	closedir($gamedir);
}

/* START */
function start() {
	if (defined('HTML_DEFPAGE') && is_file(HTML_DEFPAGE)) {
		readfile(HTML_DEFPAGE);
		deleteoldgames(0);
	} else {
		printf("<table border=0 ><tr bgcolor=\"%s\"><td align=center>\n", BOARDMARGBG);
		printf("<font size=\"+1\" color=\"%s\"><b>%s</b></font>\n", BOARDMARGFG, STARTANEWGAME);
		printf("</td></tr><tr><td align=\"center\">\n");

		printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
		printf("<INPUT TYPE=hidden name=ACTION value=NEW>\n");
		printf("<TABLE BORDER=0><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=NICK1 SIZE=%d></TD>\n", STARTGAMENICK1, NICKLENGTH);
		printf("</TR><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=MAIL1 SIZE=%d></TD>\n", STARTGAMEMAIL1, MAILLENGTH);
		printf("</TR><TR>\n");
		printf("<TD colspan=2><INPUT TYPE=RADIO name=WHITE value=1 CHECKED>%s</TD>\n", STARTHASWHITE);
		printf("</TR><TR>\n");
		printf("<TD colspan=2>%s</TD>\n", STARTGAMELEAVEBLANK);
		printf("</TR><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=NICK2 SIZE=%d></TD>\n", STARTGAMENICK2, NICKLENGTH);
		printf("</TR><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=MAIL2 SIZE=%d></TD>\n", STARTGAMEMAIL2, MAILLENGTH);
		printf("</TR><TR>\n");
		printf("<TD colspan=2><INPUT TYPE=RADIO name=WHITE value=2>%s</TD>\n", STARTHASWHITE);
		printf("</TR></TABLE><BR>\n");
		printf("%s: <INPUT TYPE=text NAME=MESSAGE size=\"%d\" value=\"\">\n", RESMESSAGE, MESSAGEBOXLEN);
		printf("<BR><BR><INPUT TYPE=submit value=\"%s\"><br>&nbsp;\n", STARTNEW);
		printf("</FORM>\n");

		printf("</td></tr>");
		printf("<tr bgcolor=\"%s\"><td align=center>\n", BOARDMARGBG);
		printf("<font size=\"+1\" color=\"%s\"><b>%s</b></font>\n", BOARDMARGFG, STARTRESUMEAGAME);
		printf("</td></tr><tr><td align=\"center\">\n");

		printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
		printf("<INPUT TYPE=hidden name=ACTION value=RESUME><br>\n");
		printf("%s: <INPUT TYPE=text NAME=ID><br><br>\n", STARTGAMEID);
		printf("<INPUT TYPE=submit value=\"%s\"><br>&nbsp;\n", STARTRESUME);
		printf("</FORM>\n");

		printf("</td></tr>");
		printf("<tr bgcolor=\"%s\"><td align=center>\n", BOARDMARGBG);
		printf("<font size=\"+1\" color=\"%s\"><b>%s</b></font>\n", BOARDMARGFG, STARTSENDMYGAMES);
		printf("</td></tr><tr><td align=\"center\">\n");

		printf("<FORM action=\"%s\" method=get>\n", $_SERVER['SCRIPT_NAME']);
		printf("<TABLE BORDER=0 cellpadding=0 cellspacing=0 margin=0><TR><TD align=right>");
		printf("<INPUT TYPE=hidden name=ACTION value=SENDGAMES><br>\n");
		printf("%s: <INPUT TYPE=text NAME=MAIL1><br>\n", STARTMYMAIL);
		printf("%s <INPUT TYPE=checkbox NAME=ONLYWEB><br><br>\n", STARTSENDNOMAIL);
		printf("</TD></TR></TABLE>");
		printf("<INPUT TYPE=submit value=\"%s\"><br>&nbsp;\n", STARTSEND);
		printf("</FORM>\n");

		printf("</td></tr>");

		deleteoldgames(1);
	}

}

/* MAIL (renamed to w3mail, collides with PHP-builtin) */
function w3mail($id, $an, $from, $annick, $fromnick, $pass, $moves, $board, $message, $nummov, $mate, $against, $swap) {
	global $gmoves, $GBOARD;

	if (($swap != 1) && ($swap != -1)) $swap = 1;

	if (defined('DEBUG')) {
		printf("An: %s<br>From: %s<br>Pass: %s<br>Moves: %s<br>Board: %s<br>Msg: %s<br>\n", $an, $from, $pass, $moves, $board, $message);
	}

	if (defined('NOMAIL')) {
		return;
	}

	$pipe = @popen(SENDMAIL.' '.escapeshellarg($an), 'w');
	if ($pipe === false) {
		errorwin(ERRORPIPE, '', $id, 0);
		return;
	}

	if (defined('CHARSET')) {
		fprintf($pipe, "Mime-Version: 1.0\n");
		fprintf($pipe, "Content-Type: text/plain; charset=%s\n", CHARSET);
		fprintf($pipe, "Content-Transfer-Encoding: 8bit\n");
	}
	fprintf($pipe, "From: %s\n", $from);
	fprintf($pipe, "To: %s\n", $an);
	fprintf($pipe, "Subject: %s ", SUBJECT);
	fprintf($pipe, "%s %s (%s)", MAILAGAINST, $against, $id);
	fprintf($pipe, "\n\n");

	fprintf($pipe, "%s %s\n\n", MAILDEAR, $annick);

	if ($nummov == 0)
		fprintf($pipe, "%s\n", MAILINVITE);

	fprintf($pipe, "%s: %s\n", MAILID, $id);
	fprintf($pipe, "%s: http://%s%s\n\n", MAILBOARD, $_SERVER['SERVER_NAME'], $_SERVER['SCRIPT_NAME']);

	if ($pass !== '') {
		switch ($mate) {
			case 1:
				fprintf($pipe, "%s, %s!\n", MATE, $annick);
			break;
			case 2:
				fprintf($pipe, "%s, %s!\n", CHECK, $annick);
			break;
			default:
			break;
		}
		if ($mate == 3) {	/* Remis */
			fprintf($pipe, "%s\n", MAILREMIS);
		}
		if ($mate != 1) {
			fprintf($pipe, "%s: %s\n\n", MAILMOVE, $pass);
			fprintf($pipe, "%s\n", MAILURLRESUME);
			fprintf($pipe, "http://%s%s?ACTION=RESUME&PASS=%s&ID=%s\n", $_SERVER['SERVER_NAME'], $_SERVER['SCRIPT_NAME'], $pass, $id);
		} else {
			fprintf($pipe, "%s\n", MAILURLVIEW);
			fprintf($pipe, "http://%s%s?ACTION=RESUME&ID=%s\n", $_SERVER['SERVER_NAME'], $_SERVER['SCRIPT_NAME'], $id);
		}
		if ($message !== null && $message !== '') {
			fprintf($pipe, "\n%s:\n%s\n", RESMESSAGE, $message);
		}
	} else {
		if ($nummov > 0)
			fprintf($pipe, "%s\n", MAILDONE);
		switch ($mate) {
			case 1:
				fprintf($pipe, "%s\n", MAILWINNER);
			break;
			case 2:
				fprintf($pipe, "%s\n", MAILOPCHECK);
			break;
			case 3:
				fprintf($pipe, "%s\n", MAILREMIS);
			break;
			case 4:
				fprintf($pipe, "%s\n", MAILISREMIS);
			break;
			case 5:
				fprintf($pipe, MAILGIVESUP, $fromnick);
				fwrite($pipe, "\n");
			break;
			default:
			break;
		}
		fprintf($pipe, "%s\n", MAILURLVIEW);
		fprintf($pipe, "http://%s%s?ACTION=RESUME&ID=%s\n", $_SERVER['SERVER_NAME'], $_SERVER['SCRIPT_NAME'], $id);
	}

	if ($swap == 1)
		fprintf($pipe, "\n   # A | B | C | D | E | F | G | H #\n");
	else
		fprintf($pipe, "\n   # H | G | F | E | D | C | B | A #\n");
	fprintf($pipe, "###+###+###+###+###+###+###+###+###+###\n");

	$bi = 0;
	if ($swap == -1)
		$bi = 63;

	if ($swap == 1) $i1 = 0; else $i1 = 7;
	for (;; $i1 += $swap) {
		if ($swap == 1 && $i1 > 7) break;
		if ($swap != 1 && $i1 < 0) break;
		if ((($i1 > 0) && ($swap == 1)) || (($swap == -1) && ($i1 < 7)))
			fprintf($pipe, "---#---+---+---+---+---+---+---+---#---\n");
		fprintf($pipe, " %d #", 8 - $i1);
		if ($swap == 1) $i2 = 0; else $i2 = 7;
		for (;; $i2 += $swap) {
			if ($swap == 1 && $i2 > 7) break;
			if ($swap != 1 && $i2 < 0) break;
			if ($board[$bi] === 'e')
				fprintf($pipe, "   ");
			else
				fprintf($pipe, " %s ", $board[$bi]);
			$bi += $swap;

			if (($swap == -1 && $i2 > 0) || ($swap == 1 && $i2 < 7))
				fprintf($pipe, "|");
			else
				fprintf($pipe, "#");
		}
		fprintf($pipe, " %d\n", 8 - $i1);
	}
	fprintf($pipe, "###+###+###+###+###+###+###+###+###+###\n");
	if ($swap == 1)
		fprintf($pipe, "   # A | B | C | D | E | F | G | H #\n");
	else
		fprintf($pipe, "   # H | G | F | E | D | C | B | A #\n");

	if ($swap == -1)
		fprintf($pipe, "(%s)\n\n", MAILYOUBLACK);
	else
		fprintf($pipe, "(%s)\n\n", MAILYOUWHITE);


	movesout($pipe, $moves, $nummov, '', 0);

	if ($nummov > 0)
		fprintf($pipe, "\n\n[MOVES:%s]\n[BOARD:%s]\n", $gmoves, $GBOARD);

	pclose($pipe);
}

/* GETPIECEFROMBOARD */
function getPieceFromBoard($x, $y) {
	global $GBOARD;
	if ($y * 8 + $x < 0)
		return ' ';
	if ($y * 8 + $x > 63)
		return ' ';
	if (($y < 0) || ($y > 7) || ($x < 0) || ($x > 7))
		return ' ';
	return $GBOARD[$y * 8 + $x];
}

/* KINGISATTACKED */
function kingIsAttacked($x, $y, $king) {
	$flags = array(0, 0, 0, 0, 0, 0, 0, 0);

	/* Bishops and Queens could attack if they are from other color and if *no* other piece in the way */

	for ($i1 = 1; $i1 <= 7; $i1++) {
		$p = getPieceFromBoard($x + $i1, $y + $i1);		/* topleft to bottomright */
		if ((myisupper($king) == myisupper($p)) && ($p !== 'e')) $flags[0] = 1;		/* is an own piece in the way, don't test the type */
		if (($flags[0] == 0) && ((strtoupper($p) === 'B') || (strtoupper($p) === 'Q'))) return 1;		/* Bishop or Queen attacks */
		if ($p !== 'e') $flags[0] = 1;	/* is *some* piece in the way then never test again, king is save */

		$p = getPieceFromBoard($x - $i1, $y + $i1);		/* topright to bottomleft */
		if ((myisupper($king) == myisupper($p)) && ($p !== 'e')) $flags[1] = 1;
		if (($flags[1] == 0) && ((strtoupper($p) === 'B') || (strtoupper($p) === 'Q'))) return 1;    /* Bishop or Queen attacks */
		if ($p !== 'e') $flags[1] = 1;

		$p = getPieceFromBoard($x + $i1, $y - $i1);		/* bottomleft to topright */
		if ((myisupper($king) == myisupper($p)) && ($p !== 'e')) $flags[2] = 1;
		if (($flags[2] == 0) && ((strtoupper($p) === 'B') || (strtoupper($p) === 'Q'))) return 1;    /* Bishop or Queen attacks */
		if ($p !== 'e') $flags[2] = 1;

		$p = getPieceFromBoard($x - $i1, $y - $i1);		/* bottomright to topleft */
		if ((myisupper($king) == myisupper($p)) && ($p !== 'e')) $flags[3] = 1;
		if (($flags[3] == 0) && ((strtoupper($p) === 'B') || (strtoupper($p) === 'Q'))) return 1;    /* Bishop or Queen attacks */
		if ($p !== 'e') $flags[3] = 1;

		$p = getPieceFromBoard($x, $y + $i1);			/* top to bottom */
		if ((myisupper($king) == myisupper($p)) && ($p !== 'e')) $flags[4] = 1;
		if (($flags[4] == 0) && ((strtoupper($p) === 'R') || (strtoupper($p) === 'Q'))) return 1;    /* Rook or Queen attacks */
		if ($p !== 'e') $flags[4] = 1;

		$p = getPieceFromBoard($x, $y - $i1);			/* bottom to top */
		if ((myisupper($king) == myisupper($p)) && ($p !== 'e')) $flags[5] = 1;
		if (($flags[5] == 0) && ((strtoupper($p) === 'R') || (strtoupper($p) === 'Q'))) return 1;    /* Rook or Queen attacks */
		if ($p !== 'e') $flags[5] = 1;

		$p = getPieceFromBoard($x + $i1, $y);			/* left to right */
		if ((myisupper($king) == myisupper($p)) && ($p !== 'e')) $flags[6] = 1;
		if (($flags[6] == 0) && ((strtoupper($p) === 'R') || (strtoupper($p) === 'Q'))) return 1;    /* Rook or Queen attacks */
		if ($p !== 'e') $flags[6] = 1;

		$p = getPieceFromBoard($x - $i1, $y);			/* right to left */
		if ((myisupper($king) == myisupper($p)) && ($p !== 'e')) $flags[7] = 1;
		if (($flags[7] == 0) && ((strtoupper($p) === 'R') || (strtoupper($p) === 'Q'))) return 1;    /* Rook or Queen attacks */
		if ($p !== 'e') $flags[7] = 1;
	}

	/* The Pawn could attack */
	if ($king === 'K') {		/* The white king */
		$p = getPieceFromBoard($x - 1, $y - 1);
		if ($p === 'p') return 1;
		$p = getPieceFromBoard($x + 1, $y - 1);
		if ($p === 'p') return 1;
	} else {						/* The black king */
		$p = getPieceFromBoard($x - 1, $y + 1);
		if ($p === 'P') return 1;
		$p = getPieceFromBoard($x + 1, $y + 1);
		if ($p === 'P') return 1;
	}

	/* The knight could attack him ! */
	$p = getPieceFromBoard($x - 2, $y - 1);
	if ((myisupper($p) != myisupper($king)) && (strtoupper($p) === 'N')) return 1;
	$p = getPieceFromBoard($x + 2, $y - 1);
	if ((myisupper($p) != myisupper($king)) && (strtoupper($p) === 'N')) return 1;
	$p = getPieceFromBoard($x - 1, $y - 2);
	if ((myisupper($p) != myisupper($king)) && (strtoupper($p) === 'N')) return 1;
	$p = getPieceFromBoard($x + 1, $y - 2);
	if ((myisupper($p) != myisupper($king)) && (strtoupper($p) === 'N')) return 1;
	$p = getPieceFromBoard($x - 2, $y + 1);
	if ((myisupper($p) != myisupper($king)) && (strtoupper($p) === 'N')) return 1;
	$p = getPieceFromBoard($x + 2, $y + 1);
	if ((myisupper($p) != myisupper($king)) && (strtoupper($p) === 'N')) return 1;
	$p = getPieceFromBoard($x - 1, $y + 2);
	if ((myisupper($p) != myisupper($king)) && (strtoupper($p) === 'N')) return 1;
	$p = getPieceFromBoard($x + 1, $y + 2);
	if ((myisupper($p) != myisupper($king)) && (strtoupper($p) === 'N')) return 1;

	/* The other king could attack him ! */
	/* There only be one king, so its enough to check for "one" other king
      on a neighbourfiled */

	if ($king === 'k')
		$c = 'K';
	else
		$c = 'k';

	if (getPieceFromBoard($x + 1, $y) === $c) return 1;
	if (getPieceFromBoard($x + 1, $y + 1) === $c) return 1;
	if (getPieceFromBoard($x + 1, $y - 1) === $c) return 1;
	if (getPieceFromBoard($x, $y + 1) === $c) return 1;
	if (getPieceFromBoard($x, $y - 1) === $c) return 1;
	if (getPieceFromBoard($x - 1, $y) === $c) return 1;
	if (getPieceFromBoard($x - 1, $y + 1) === $c) return 1;
	if (getPieceFromBoard($x - 1, $y - 1) === $c) return 1;

	return 0;

}

/* INTERNCM */
function interncm($fromx, $fromy, $x, $y) {
	if (($fromx > 7) || ($fromx < 0) || ($x > 7) || ($x < 0) || ($fromy > 7) || ($fromy < 0) || ($y > 7) || ($y < 0)) return 0;
	return canmove(ord('A') + $fromx, 8 - $fromy, $x, $y);
}

/* PIECEFREE */
function pieceFree($piece, $x, $y) {
	switch (strtolower($piece)) {
		case 'q':		/* Queen */
			for ($i1 = 1; $i1 <= 7; $i1++) {
				if (interncm($x, $y, $x - $i1, $y) == 1) return 1;		/* left */
				if (interncm($x, $y, $x + $i1, $y) == 1) return 1;		/* right */
				if (interncm($x, $y, $x, $y - $i1) == 1) return 1;		/* top */
				if (interncm($x, $y, $x, $y + $i1) == 1) return 1;		/* bottom */
				if (interncm($x, $y, $x - $i1, $y + $i1) == 1) return 1;		/* bottom-left */
				if (interncm($x, $y, $x + $i1, $y + $i1) == 1) return 1;		/* bottom-right */
				if (interncm($x, $y, $x - $i1, $y - $i1) == 1) return 1;		/* top-left */
				if (interncm($x, $y, $x + $i1, $y - $i1) == 1) return 1;		/* top-right */
			}
		break;
		case 'b':	/* Bishop */
			for ($i1 = 1; $i1 <= 7; $i1++) {
				if (interncm($x, $y, $x - $i1, $y + $i1) == 1) return 1;   /* bottom-left */
				if (interncm($x, $y, $x + $i1, $y + $i1) == 1) return 1;   /* bottom-right */
				if (interncm($x, $y, $x - $i1, $y - $i1) == 1) return 1;   /* top-left */
				if (interncm($x, $y, $x + $i1, $y - $i1) == 1) return 1;   /* top-right */
			}
		break;
		case 'r':	/* Rook */
			for ($i1 = 1; $i1 <= 7; $i1++) {
				if (interncm($x, $y, $x - $i1, $y) == 1) return 1;    /* left */
				if (interncm($x, $y, $x + $i1, $y) == 1) return 1;    /* right */
				if (interncm($x, $y, $x, $y - $i1) == 1) return 1;    /* top */
				if (interncm($x, $y, $x, $y + $i1) == 1) return 1;    /* bottom */
			}
		break;
		case 'n':	/* Knight */
			if (interncm($x, $y, $x - 1, $y - 2) == 1) return 1;
			if (interncm($x, $y, $x + 1, $y - 2) == 1) return 1;
			if (interncm($x, $y, $x - 1, $y + 2) == 1) return 1;
			if (interncm($x, $y, $x + 1, $y + 2) == 1) return 1;
			if (interncm($x, $y, $x + 2, $y - 1) == 1) return 1;
			if (interncm($x, $y, $x - 2, $y - 1) == 1) return 1;
			if (interncm($x, $y, $x - 2, $y + 1) == 1) return 1;
			if (interncm($x, $y, $x + 2, $y + 1) == 1) return 1;
		break;
		case 'p':	/* Pawn */
			if (interncm($x, $y, $x - 1, $y - 1) == 1) return 1;
			if (interncm($x, $y, $x, $y - 1) == 1) return 1;
			if (interncm($x, $y, $x, $y - 2) == 1) return 1;
			if (interncm($x, $y, $x + 1, $y - 1) == 1) return 1;

			if (interncm($x, $y, $x - 1, $y + 1) == 1) return 1;
			if (interncm($x, $y, $x, $y + 1) == 1) return 1;
			if (interncm($x, $y, $x, $y + 2) == 1) return 1;
			if (interncm($x, $y, $x + 1, $y + 1) == 1) return 1;
		break;
		case 'k':
			if (interncm($x, $y, $x - 1, $y) == 1) return 1;
			if (interncm($x, $y, $x - 1, $y - 1) == 1) return 1;
			if (interncm($x, $y, $x - 1, $y + 1) == 1) return 1;
			if (interncm($x, $y, $x, $y - 1) == 1) return 1;
			if (interncm($x, $y, $x, $y + 1) == 1) return 1;
			if (interncm($x, $y, $x + 1, $y) == 1) return 1;
			if (interncm($x, $y, $x + 1, $y - 1) == 1) return 1;
			if (interncm($x, $y, $x + 1, $y + 1) == 1) return 1;
			if (interncm($x, $y, $x - 2, $y) == 1) return 1;
			if (interncm($x, $y, $x + 2, $y) == 1) return 1;
		break;

		default:	/* Should never happen... */
			return 1;
		break;
	}



	return 0;
}

/* WAYFREE */
function wayFree($fromx, $fromy, $tox, $toy, $xsgn, $ysgn) {
	for ($x = $fromx + $xsgn, $y = $fromy + $ysgn; ($x != $tox) || ($y != $toy); $x += $xsgn, $y += $ysgn) {		/* Dangerous ! If the programm faults, this leads into an infinity loop ! */
		if (getPieceFromBoard($x, $y) !== 'e')
			return 0;
	}

	return 1;	/* No piece found in the way.... */

}


/* CHECKMOVE */
function checkMove($fromx, $fromy, $tox, $toy) {
	/* returned directions: have a look at you numpad... */
	/* zero means no direction */

	$xd = $tox - $fromx;
	$yd = $fromy - $toy;

	if ((abs($xd) != abs($yd)) && ($xd * $yd != 0))	/* no direct move */
		return 0;

	if ($xd < 0) {
		if ($yd < 0) {
			return 1 * wayFree($fromx, $fromy, $tox, $toy, -1, 1);
		}
		if ($yd > 0) {
			return 7 * wayFree($fromx, $fromy, $tox, $toy, -1, -1);
		}
		if ($yd == 0) {
			return 4 * wayFree($fromx, $fromy, $tox, $toy, -1, 0);
		}
	}
	if ($xd > 0) {
		if ($yd < 0) {
			return 3 * wayFree($fromx, $fromy, $tox, $toy, 1, 1);
		}
		if ($yd > 0) {
			return 9 * wayFree($fromx, $fromy, $tox, $toy, 1, -1);
		}
		if ($yd == 0) {
			return 6 * wayFree($fromx, $fromy, $tox, $toy, 1, 0);
		}
	}
	if ($xd == 0) {
		if ($yd < 0) {
			return 2 * wayFree($fromx, $fromy, $tox, $toy, 0, 1);
		}
		if ($yd > 0) {
			return 8 * wayFree($fromx, $fromy, $tox, $toy, 0, -1);
		}
		if ($yd == 0) {
			return 0;
		}
	}
	return 0;
}

/* WHEREISMYKING */
function whereIsMyKing($color) {
	global $GBOARD;
	/*color=0: white, color=1: black */
	if ($color == 0)
		$pos = strpos($GBOARD, 'K');
	else
		$pos = strpos($GBOARD, 'k');
	if ($pos === false)
		return -1;		/* The King is dead.... */
	else
		return $pos;
}

/* CANMOVE */
function canmove($fromx, $fromy, $x, $y) {
	global $GBOARD, $gnummoves, $gmoves;

	if (defined('ALLCANMOVE')) {
		return 1;	/* every move is allowed */
	}

	/* pieces out of board ? */
	if (($fromx > ord('H')) || ($x > ord('H')) || ($fromy > 8) || ($y > 8)) return 0;

	/*================================*/
	/*=== Collect some information ===*/
	/*================================*/

	/* First or second step of a move ? */
	if (($fromx != 0) && ($fromy != 0))
		$moveto = 1;
	else
		$moveto = 0;

	/* Correct fromx and fromy, should be from 0 to 7, beginning at top left */
	if ($fromy > 0)
		$fromy = 8 - $fromy;
	if ($fromx > 0)
		$fromx = $fromx - ord('A');

	/* color which has to move next, 0=white, 1=black */
	$aktfarbe = $gnummoves % 2;

	/* which piece is at (x,y) */
	$piece = getPieceFromBoard($x, $y);
	/* which piece to set */
	$fpiece = getPieceFromBoard($fromx, $fromy);

	/*====================*/
	/*=== Global Rules ===*/
	/*====================*/

	/* You should not move empty fields to empty fields! */
	if ($fpiece === 'e' && $piece === 'e') return 0;

	/* You should only move pieces to fields or pieces */
	if ($piece === ' ' || $fpiece === ' ') return 0;

	/* You should only move with one of your pieces */
	if (($moveto == 0) && (($aktfarbe == myisupper($piece)) || ($piece === 'e'))) return 0;

	/* You should only move TO a field where`s none of your pieces */
	if (($moveto == 1) && ($aktfarbe != myisupper($piece) && ($piece !== 'e'))) return 0;

	/* You shouldn't be in chess *after* moving */
	if ($moveto == 1) {
		$TBOARD = $GBOARD;
		$GBOARD[$y * 8 + $x] = $GBOARD[$fromy * 8 + $fromx];
		$GBOARD[$fromy * 8 + $fromx] = 'e';
		$i1 = whereIsMyKing($aktfarbe);
		if ($aktfarbe == 0) {
			if (kingIsAttacked($i1 % 8, intdiv($i1, 8), 'K') == 1) {
				$GBOARD = $TBOARD;
				return 0;
			}
		} else {
			if (kingIsAttacked($i1 % 8, intdiv($i1, 8), 'k') == 1) {
				$GBOARD = $TBOARD;
				return 0;
			}
		}
		$GBOARD = $TBOARD;
	}

	/* The piece has to be "free" when moving it */
	if (($moveto == 0) && (pieceFree($piece, $x, $y) == 0)) return 0;

	/* Check if the move ist possible:
	   - no other piece is in the way
		 - the way is valid
		 - the to-position *is* an enemy or empty, see above */
	if ($moveto == 1) {

		$dx = abs($fromx - $x);
		$dy = abs($fromy - $y);

		/* The Knight could only move in his ugly way, he can jump over other pieces */
		if (strtolower($fpiece) === 'n') {
			if ($dx + $dy != 3) return 0;
			if ($dx * $dy != 2) return 0;
		}

		/* The Black Pawn */
		if ($fpiece === 'p') {
			/* normal move */
			if (($fromx == $x) && ($fromy == ($y - 1)) && (getPieceFromBoard($x, $y) === 'e')) return 1;
			/* first move */
			if (($fromx == $x) && ($fromy == ($y - 2)) && ($fromy == 1) && (getPieceFromBoard($x, $y - 1) === 'e') && (getPieceFromBoard($x, $y) === 'e')) return 1;
			/* hit */
			if (($fromx == $x - 1) && ($fromy == ($y - 1)) && (getPieceFromBoard($x, $y) !== 'e') && (myislower(getPieceFromBoard($x, $y)) != 1)) return 1;
			if (($fromx == $x + 1) && ($fromy == ($y - 1)) && (getPieceFromBoard($x, $y) !== 'e') && (myislower(getPieceFromBoard($x, $y)) != 1)) return 1;
			/* en Passant */
			if (($y == 5) && ($fromy == 4) && ($fromx > 0) && ($x == $fromx - 1)) {	/* left */
				$ENP = sprintf("P%s2%s4Pe", chr(ord('A') + $fromx - 1), chr(ord('A') + $fromx - 1));
				if ($gmoves !== null && substr($gmoves, -7) === $ENP)
					return 1;
			}
			if (($y == 5) && ($fromy == 4) && ($fromx < 7) && ($x == $fromx + 1)) { /* right */
				$ENP = sprintf("P%s2%s4Pe", chr(ord('A') + $fromx + 1), chr(ord('A') + $fromx + 1));
				if ($gmoves !== null && substr($gmoves, -7) === $ENP)
					return 1;
			}

			return 0;
		}

		/* The White Pawn */
		if ($fpiece === 'P') {
			/* normal move */
			if (($fromx == $x) && ($fromy == ($y + 1)) && (getPieceFromBoard($x, $y) === 'e')) return 1;
			/* first move */
			if (($fromx == $x) && ($fromy == ($y + 2)) && ($fromy == 6) && (getPieceFromBoard($x, $y + 1) === 'e') && (getPieceFromBoard($x, $y) === 'e')) return 1;
			/* hit */
			if (($fromx == $x - 1) && ($fromy == ($y + 1)) && (getPieceFromBoard($x, $y) !== 'e') && (myisupper(getPieceFromBoard($x, $y)) != 1)) return 1;
			if (($fromx == $x + 1) && ($fromy == ($y + 1)) && (getPieceFromBoard($x, $y) !== 'e') && (myisupper(getPieceFromBoard($x, $y)) != 1)) return 1;
			/* en Passant */
			if (($y == 2) && ($fromy == 3) && ($fromx > 0) && ($x == $fromx - 1)) { /* left */
				$ENP = sprintf("p%s7%s5pe", chr(ord('A') + $fromx - 1), chr(ord('A') + $fromx - 1));
				if ($gmoves !== null && substr($gmoves, -7) === $ENP)
					return 1;
			}
			if (($y == 2) && ($fromy == 3) && ($fromx < 7) && ($x == $fromx + 1)) { /* right */
				$ENP = sprintf("p%s7%s5pe", chr(ord('A') + $fromx + 1), chr(ord('A') + $fromx + 1));
				if ($gmoves !== null && substr($gmoves, -7) === $ENP)
					return 1;
			}

			return 0;
		}

		/* The King */
		if (strtolower($fpiece) === 'k') {
			/* Dont set the king in check */
			/* Its wrong at this point if(kingIsAttacked(x,y,fpiece)) return(0);*/ 	/* I know, it is checked before..... */
			/* Castle xxx Side */
			if ($fpiece === 'k') {	/*	black king */
				if (($fromx == 4) && ($fromy == 0) && ($x == 6) && ($y == 0)) {	/* Castle King Side */
					if (strpos(($gmoves ?? ''), "kE8") !== false) return 0;	/* The King has been moved */
					if (strpos(($gmoves ?? ''), "rH8") !== false) return 0;  /* The Rook has been moved */
					if (getPieceFromBoard(5, 0) !== 'e') return 0;	/* all fields between king and rook have to be empty */
					if (getPieceFromBoard(6, 0) !== 'e') return 0;
					if (kingIsAttacked(4, 0, $fpiece)) return 0;		/* the king is not in check */
					if (kingIsAttacked(5, 0, $fpiece)) return 0;		/* no crossing field is in check */
					if (kingIsAttacked(6, 0, $fpiece)) return 0;
					return 1;
				}
				if (($fromx == 4) && ($fromy == 0) && ($x == 2) && ($y == 0)) {  /* Castle Queen Side */
					if (strpos(($gmoves ?? ''), "kE8") !== false) return 0;  /* The King has been moved */
					if (strpos(($gmoves ?? ''), "rA8") !== false) return 0;  /* The Rook has been moved */
					if (getPieceFromBoard(1, 0) !== 'e') return 0;  /* all fields between king and rook have to be empty */
					if (getPieceFromBoard(2, 0) !== 'e') return 0;
					if (getPieceFromBoard(3, 0) !== 'e') return 0;
					if (kingIsAttacked(4, 0, $fpiece)) return 0;		/* the king is not in check */
					if (kingIsAttacked(3, 0, $fpiece)) return 0;   /* no crossing field is in check */
					if (kingIsAttacked(2, 0, $fpiece)) return 0;
					return 1;
				}
			} else {	/* white king */
				if (($fromx == 4) && ($fromy == 7) && ($x == 6) && ($y == 7)) {  /* Castle King Side */
					if (strpos(($gmoves ?? ''), "KE1") !== false) return 0;  /* The King has been moved */
					if (strpos(($gmoves ?? ''), "RH1") !== false) return 0;  /* The Rook has been moved */
					if (getPieceFromBoard(5, 7) !== 'e') return 0;  /* all fields between king and rook have to be empty */
					if (getPieceFromBoard(6, 7) !== 'e') return 0;
					if (kingIsAttacked(4, 7, $fpiece)) return 0;   /* the king is not in check */
					if (kingIsAttacked(5, 7, $fpiece)) return 0;   /* no crossing field is in check */
					if (kingIsAttacked(6, 7, $fpiece)) return 0;
					return 1;
				}
				if (($fromx == 4) && ($fromy == 7) && ($x == 2) && ($y == 7)) {  /* Castle Queen Side */
					if (strpos(($gmoves ?? ''), "KE1") !== false) return 0;  /* The King has been moved */
					if (strpos(($gmoves ?? ''), "RA1") !== false) return 0;  /* The Rook has been moved */
					if (getPieceFromBoard(1, 7) !== 'e') return 0;  /* all fields between king and rook have to be empty */
					if (getPieceFromBoard(2, 7) !== 'e') return 0;
					if (getPieceFromBoard(3, 7) !== 'e') return 0;
					if (kingIsAttacked(4, 7, $fpiece)) return 0;   /* the king is not in check */
					if (kingIsAttacked(3, 7, $fpiece)) return 0;   /* no crossing field is in check */
					if (kingIsAttacked(2, 7, $fpiece)) return 0;
					return 1;
				}
			}


			/* The King should only move 1 field */
			if (($dx > 1) || ($dy > 1)) return 0;


			return 1;
		}


		$direction = checkMove($fromx, $fromy, $x, $y);

		/* The Queen can only move diagonal and orthogonal */
		if ((strtolower($fpiece) === 'q') && ($direction == 0))	return 0;

		/* The Rook can only move orthogonal */
		if ((strtolower($fpiece) === 'r') && ((($direction % 2) == 1) || ($direction == 0)))  return 0;

		/* The Bishop can only move diagonal */
		if ((strtolower($fpiece) === 'b') && (($direction % 2) == 0))  return 0;

	}

	/* If there`s no rule.... Ok, maybe you are allowed to do it ! */
	return 1;
}

/* READGAMEDATA */
function readgamedata($game) {
	global $GNICK1, $GNICK2, $GPLAYER1, $GPLAYER2, $GPASS, $GPASS2, $GBOARD,
	       $gmessage, $gmoves, $gnummoves, $gnomail1, $gnomail2, $globalpass;

	$ismatt = 0;

	/* Default: Sent Mail to both players */
	$gnomail1 = 0;
	$gnomail2 = 0;

	/* Default: Players have no own Password */
	$globalpass = 1;
	$GPASS2 = '';
	$gmoves = null;
	$gmessage = null;

	/* open datafile */
	$path = DATAPATH.'/'.$game;
	$lines = @file($path, FILE_IGNORE_NEW_LINES);
	if ($lines === false) {
		errorwin(ERRORID, '', '', 0);
		return -1;
	}

	$GNICK1 = (isset($lines[0]) ? $lines[0] : '');
	$GPLAYER1 = (isset($lines[1]) ? $lines[1] : '');
	$GNICK2 = (isset($lines[2]) ? $lines[2] : '');
	$GPLAYER2 = (isset($lines[3]) ? $lines[3] : '');
	$GPASS = (isset($lines[4]) ? $lines[4] : '');

	if ($GPASS === '[MATE]')
		$ismatt = 1;

	/* Check for Userpassword if game is valid */

	if ((strncmp($GPASS, "[REMIS]", 12) != 0) && (strncmp($GPASS, "[GIVEUP]", 12) != 0) && (strncmp($GPASS, "[MATE]", 12) != 0)) {
		$z1 = strpos($GNICK1, ':');
		if ($z1 !== false) {
			$prefix = substr($GNICK1, 0, $z1);
			if (strlen($prefix) > 0) {
				$globalpass = 0;
				if ($ismatt == 0)
					$GPASS = substr($prefix, 0, 10);
			}
			$after = substr($GNICK1, $z1 + 1);
			$z2 = strpos($after, ':');
			if ($z2 !== false) {
				if ($after[0] === '1') $gnomail1 = 1;
			}
		}

		$z1 = strpos($GNICK2, ':');
		if ($z1 !== false) {
			$prefix = substr($GNICK2, 0, $z1);
			if (strlen($prefix) > 0)
				$GPASS2 = substr($prefix, 0, 10);
			$after = substr($GNICK2, $z1 + 1);
			$z2 = strpos($after, ':');
			if ($z2 !== false) {
				if ($after[0] === '1') $gnomail2 = 1;
			}
		}
	}

	/* Nickname is after the last : */
	$z1 = strrpos($GNICK1, ':');
	if ($z1 !== false) {
		$GNICK1 = substr($GNICK1, $z1 + 1);	/* Ok, z1 is shorter than GNICK */
	}
	$z1 = strrpos($GNICK2, ':');
	if ($z1 !== false) {
		$GNICK2 = substr($GNICK2, $z1 + 1);
	}

	$gnummoves = (int)(isset($lines[5]) ? $lines[5] : 0);
	if ($gnummoves > 0) {
		$gmoves = (isset($lines[6]) ? $lines[6] : '');
	} else {
		$gmoves = null;
	}
	$GBOARD = (isset($lines[7]) ? $lines[7] : '');

	if (strlen($GBOARD) < 64) {
		errorwin(ERRORGAMECOR, '', '', 0);
		return -1;
	}

	if (isset($lines[8]) && $lines[8] !== '') {
		$gmessage = $lines[8];
	} else {
		$gmessage = null;
	}

	return 0;
}

/* RESUMEGAME */
function resumegame($ischange) {
	global $query, $GNICK1, $GNICK2, $GPLAYER1, $GPLAYER2, $GPASS, $GBOARD,
	       $gmessage, $gmoves, $gnummoves, $gnomail1, $gnomail2, $globalpass;

	$empty = '';
	$message = $empty;

	$moveto = 0;
	$fromx = 0;
	$fromy = 0;
	$tox = 0;
	$toy = 0;
	$color = null;
	$piece = null;
	$possiblepieces = 0;
	$mate = 0;
	$reqremis = 0;
	$swap = 0;
	$rightswap = 1;
	$redframes = 0;
	$blacksturn = 0;
	$PASS = '';

	/* GameID */
	$ID = queryParam('ID');
	if ($ID === null) {
		errorwin(ERRORDEFAULT, '', '', 0);
		return;
	}
	if (readgamedata($ID) == -1)
		return;

	/* Swap Colors */
	$v = queryParam('SWAP');
	if ($v !== null) {
		$swap = (int)$v;
	}

	if (defined('REDFRAMES')) {
		$redframes = 1;
	} else {
		/* RedFrames */
		$redframes = 0;
		$v = queryParam('RF');
		if ($v !== null) {
			$redframes = (int)$v;
		}

		if (($redframes != 0) && ($redframes != 1))
			$redframes = 0;
	}

	/* Special-GameOvers ? */
	if (strcmp($GPASS, "[REMIS]") == 0) {
		$mate = 3;
	}
	if (strcmp($GPASS, "[GIVEUP]") == 0) {
		$mate = 4;
	}


	/* Should white be on top ? */
	if (($gnummoves % 2) == 0) {
		$rightswap = 1;
	} else {
		$rightswap = -1;
	}

	if ($mate != 0 && $swap == 0)
		$swap = $rightswap;

	if ($mate == 0) {
		/* Special Messages */
		if ($gmessage !== null) {
			/* Remis-Request ? */
			if (strpos($gmessage, "[REMIS?]@") === 0) {
				$reqremis = 1;
				$rightswap = (-1) * $rightswap;
			}
		}

		if (($swap != 1) && ($swap != -1))
			$swap = $rightswap;

		/* Password */
		$v = queryParam('PASS');
		if ($v !== null) {
			$PASS = substr($v, 0, 10);
		} else {
			$PASS = '';
		}
	}


	/* GNUMMOVES */
	$oldnummoves = 0;
	$v = queryParam('GNUMMOVES');
	if ($v !== null) {
		$oldnummoves = (int)$v;
	}

	if (($oldnummoves > 0) && ($oldnummoves != $gnummoves)) {	/* Theres not the current Board loaded */
		errorwin(ERROROLDBOARD, $PASS, $ID, $swap);
		return;
	}


	/* Message */
	$v = queryParam('MESSAGE');
	if ($v !== null) {
		$message = $v;
	} else {
		$message = $empty;
	}

	if ($mate == 0) {
		/* Request Remis: Yes/No */
		/* Remis was accepted or not */
		$v = queryParam('REQREMIS');
		if ($v !== null) {
			if (!defined('NOPASS')) {
				if (strcmp($PASS, $GPASS) != 0) {
					errorwin(ERRORPASS, '', $ID, $swap);
					return;
				}
			}
			if (strpos($v, "YES") === 0) { /* remis accepted */

				$z1 = substr('@'.$message, 0, strlen($message));	/* C: snprintf(malloc(strlen+1)) truncates the last char (original bug, kept 1:1) */
				rewriteGameAndSwap($ID, "[REMIS]", $z1);
				if ($gnomail2 != 1)
					w3mail($ID, $GPLAYER2, $GPLAYER2, $GNICK2, $GNICK2, '', $gmoves, $GBOARD, '', $gnummoves, 4, $GNICK1, $rightswap);
				if ($gnomail1 != 1)
					w3mail($ID, $GPLAYER1, $GPLAYER2, $GNICK1, $GNICK2, '', $gmoves, $GBOARD, $message, $gnummoves, 4, $GNICK2, (-1) * $rightswap);

			} else { /* remis NOT accepted */
				$PASS = genPass();
				$z1 = substr('@'.$message, 0, strlen($message));	/* C: snprintf(malloc(strlen+1)) truncates the last char (original bug, kept 1:1) */
				rewriteGameAndSwap($ID, $PASS, $z1);
				if ($gnomail2 != 1)
					w3mail($ID, $GPLAYER2, $GPLAYER2, $GNICK2, $GNICK2, '', $gmoves, $GBOARD, '', $gnummoves, 0, $GNICK1, $rightswap);
				if ($gnomail1 != 1) {
					w3mail($ID, $GPLAYER1, $GPLAYER2, $GNICK1, $GNICK2, $GPASS, $gmoves, $GBOARD, $message, $gnummoves, 0, $GNICK2, (-1) * $rightswap);
				}
			}

			back2board(RESREQUESTSENT, '', $ID, $swap);

			return;
		}

		/* Is it the second move ? */
		$v = queryParam('MOVE');
		if ($v !== null) {
			$i = 0;
			$n = strlen($v);
			if ($i < $n && $v[$i] !== '&') {
				$fromx = ord($v[$i]);
				$i++;
			}
			if ($i < $n && $v[$i] !== '&') {
				$fromy = ord($v[$i]) - 48;
				$i++;
				$moveto = 1;
			}
			if ($i < $n && $v[$i] !== '&') {
				$tox = ord($v[$i]);
				$i++;
			}
			if ($i < $n && $v[$i] !== '&') {
				$toy = ord($v[$i]) - 48;
			}


		} else {

			/* Are there Special actions requested ? */
			$v = queryParam('SPECIAL');
			if ($v !== null) {

				if ($v === 'PASS') {
					w3mail($ID, $GPLAYER1, $GPLAYER2, $GNICK1, $GNICK2, $GPASS, $gmoves, $GBOARD, '', $gnummoves, isMate(), $GNICK2, $rightswap);
					back2board(MAILPASS, $PASS, $ID, $swap);
					return;
				}

				/* User has choosen Remis-Switch */
				if ($v === 'REMIS') {
					if (!defined('NOPASS')) {
						if (strcmp($PASS, $GPASS) != 0) {
							errorwin(ERRORPASS, '', $ID, $swap);
							return;
						}
					}
					$PASS = genPass();
					$z1 = '[REMIS?]@'.$message;
					rewriteGameAndSwap($ID, $PASS, $z1);
					/* Players are swapped now, new GPASS is set */
					if ($gnomail2 != 1)
						w3mail($ID, $GPLAYER2, $GPLAYER2, $GNICK2, $GNICK2, '', $gmoves, $GBOARD, '', $gnummoves, 3, $GNICK1, $rightswap);
					if ($gnomail1 != 1)
						w3mail($ID, $GPLAYER1, $GPLAYER2, $GNICK1, $GNICK2, $GPASS, $gmoves, $GBOARD, $message, $gnummoves, 3, $GNICK2, -1 * $rightswap);

					back2board(RESREQUESTSENT, '', $ID, $swap);

					return;
				}

				if ($v === 'GIVEUP') {
					if (strcmp($PASS, $GPASS) != 0) {
						errorwin(ERRORPASS, '', $ID, $swap);
						return;
					}

					$z1 = '[GIVEUP]@'.$message;
					rewriteGame($ID, "[GIVEUP]", $z1);

					if ($gnomail1 != 1)
						w3mail($ID, $GPLAYER1, $GPLAYER1, $GNICK1, $GNICK1, '', $gmoves, $GBOARD, '', $gnummoves, 5, $GNICK2, $rightswap);
					if ($gnomail2 != 1)
						w3mail($ID, $GPLAYER2, $GPLAYER1, $GNICK2, $GNICK1, '', $gmoves, $GBOARD, $message, $gnummoves, 5, $GNICK1, -1 * $rightswap);

					back2board(RESREQUESTSENT, '', $ID, 0);

					return;
				}

				if ($v === 'EMAIL') {
					if (strcmp($PASS, $GPASS) != 0) {
						errorwin(ERRORPASS, '', $ID, $swap);
						return;
					}

					printf("<br><table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=left><font color=\"%s\">", BOARDGRID, BOARDMARGBG, BOARDMARGFG);
					printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
					printf("<INPUT TYPE=hidden name=ACTION value=CHMAIL>\n");
					printf("<INPUT TYPE=hidden name=PASS value=\"%s\">\n", $GPASS);
					printf("<INPUT TYPE=hidden name=ID value=\"%s\">\n", $ID);
					printf("<INPUT TYPE=hidden name=SWAP value=\"%d\">\n", $swap);
					printf("<b>");
					printf(RESMAILOF, $GNICK1);
					printf(":</b> \n");
					printf("<INPUT TYPE=text NAME=MAIL1 VALUE=\"%s\" SIZE=%d><br><br>\n", $GPLAYER1, MAILLENGTH);
					printf("<TABLE BORDER=0 cellpadding=0 cellspacing=0 margin=0>");
					printf("<TR><TD><font color=\"%s\"><b>%s: </b></font> </TD><TD>", BOARDMARGFG, RESFIXPASS);
					printf("<INPUT TYPE=password NAME=FIXPASS VALUE=\"");
					if ($globalpass != 1) printf("%s", $GPASS);
					printf("\" SIZE=10>");
					printf("</TD></TR><br>");
					printf("<TR><TD><font color=\"%s\"><b>%s: </b></font> </TD><TD>", BOARDMARGFG, RESFIXPASSA);
					printf("<INPUT TYPE=password NAME=FIXPASSA VALUE=\"");
					if ($globalpass != 1) printf("%s", $GPASS);
					printf("\" SIZE=10>");
					printf("</TD></TR></TABLE>\n");
					printf("<br><font size=\"-2\">(%s)</font><br>\n", RESPASSWARN);
					printf("<br><INPUT TYPE=checkbox NAME=NOMAILTOME");
					if ($gnomail1 == 1) printf(" CHECKED ");
					printf("> %s<br><br>", RESNOMAILTOME);
					printf("<CENTER><INPUT TYPE=submit VALUE=\"%s\"></CENTER>", MSGOK);
					printf("</font></td></tr></table></td></tr></table><br>\n");


					printf("</FORM>\n");

					return;
				}

				if ($v === 'LOGOUT') {
					back2board(SPECIALLOGOUT, '', '', 0);
					return;
				}
			}
		}
	}

		printf("<FONT size=\"+1\">");

	/* Who must move ? */
	if (($gnummoves % 2) == 0) {
		printf(RESTITLE, $GNICK1, $GNICK2);
		printf("</FONT><br>");
		if (($reqremis == 0) && ($mate == 0))
			printf(RESTURN, $GNICK1, TWHITE);
		$blacksturn = 0;
	} else {
		printf(RESTITLE, $GNICK2, $GNICK1);
		printf("</FONT><br>");
		if (($reqremis == 0) && ($mate == 0))
			printf(RESTURN, $GNICK1, TBLACK);
		$blacksturn = 1;
	}

	if ($reqremis == 1)
		printf(RESTURNR, $GNICK1);

	if ($mate == 0)
		printf("<br>\n");

	/* Pawn-Change */
	if ($ischange == 1) {
		printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\"><b>", BOARDGRID, BOARDMARGBG, BOARDMARGFG);
		printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
		printf("<input type=hidden name=\"ID\" value=\"%s\">\n", $ID);
		printf("<input type=hidden name=\"ACTION\" value=\"MOVED\">\n");
		printf("<input type=hidden name=\"SWAP\" value=\"%d\">\n", $swap);
		printf("<input type=hidden name=\"PASS\" value=\"%s\">\n", $PASS);
		printf("<input type=hidden name=\"MOVE\" value=\"%c%d%c%d\"\n>", $fromx, $fromy, $tox, $toy);
		if ($message !== null)
			printf("<input type=hidden name=\"MESSAGE\" value=\"%s\">\n", $message);
		printf("%s\n", RESCHANGE);

		printf("<table border=0><tr>");
		if ($blacksturn == 0) {
			printf("<td><input type=image name=\"CHANGE=Q\" VALUE=\"Q\" src=\"%s\" alt=\"%s\"></td>\n", WQUEEN, TLONGQ);
			printf("<td><input type=image name=\"CHANGE=R\" VALUE=\"R\" src=\"%s\" alt=\"%s\"></td>\n", WROOKE, TLONGR);
			printf("<td><input type=image name=\"CHANGE=B\" VALUE=\"B\" src=\"%s\" alt=\"%s\"></td>\n", WBISHOP, TLONGB);
			printf("<td><input type=image name=\"CHANGE=N\" VALUE=\"N\" src=\"%s\" alt=\"%s\"></td>\n", WKNIGHT, TLONGN);
		} else {
			printf("<td><input type=image name=\"CHANGE=q\" VALUE=\"q\" src=\"%s\" alt=\"%s\"></td>\n", BQUEEN, TLONGQ);
			printf("<td><input type=image name=\"CHANGE=r\" VALUE=\"r\" src=\"%s\" alt=\"%s\"></td>\n", BROOKE, TLONGR);
			printf("<td><input type=image name=\"CHANGE=b\" VALUE=\"b\" src=\"%s\" alt=\"%s\"></td>\n", BBISHOP, TLONGB);
			printf("<td><input type=image name=\"CHANGE=n\" VALUE=\"n\" src=\"%s\" alt=\"%s\"></td>\n", BKNIGHT, TLONGN);
		}
		printf("</tr></table>\n");

		printf("</FORM>\n");
		printf("</b></font></td></tr></table></td></tr></table>\n");

	} else {

		switch (isMate()) {
			case 1:
				printf("<font color=\"%s\"><b>%s, %s !</b></font><br>", WARNCOLOR, MATE, $GNICK1);
				$mate = 1;
			break;
			case 2:
				printf("<font color=\"%s\"><b>%s, %s !</b></font><br>", WARNCOLOR, CHECK, $GNICK1);
			break;
			case 3:
				printf("<font color=\"%s\"><b>%s</b></font><br>", WARNCOLOR, MAILISREMIS);
			break;
			case 4:
				printf("<font color=\"%s\"><b>", WARNCOLOR);
				printf(MAILGIVESUP, $GNICK1);
				printf("</b></font><br>");
			break;
			default:
			break;
		}


		if ($gmessage !== null) {
			$z1 = strpos($gmessage, '@');
			if ($z1 !== false) {
				if ($z1 + 1 < strlen($gmessage))
					printf("<b>%s: <font color=\"%s\">%s</font></b><br>\n", RESMESSAGE, MSGCOLOR, substr($gmessage, $z1 + 1));
			}
		}
	}

	if ($mate == 0) {
		printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
		printf("<input type=hidden name=\"ID\" value=\"%s\">\n", $ID);
		if ($moveto == 1)
			printf("<input type=hidden name=\"ACTION\" value=\"MOVED\">\n");
		else
			printf("<input type=hidden name=\"ACTION\" value=\"RESUME\">\n");
		printf("<INPUT TYPE=hidden name=GNUMMOVES value=\"%d\">\n", $gnummoves);
		printf("<input type=hidden name=\"SWAP\" value=\"%d\">\n", $swap);
		printf("<input type=hidden name=\"RF\" value=\"%d\">\n", $redframes);

		if (defined('DEBUG')) {
			$PASS = substr($GPASS, 0, 11);
		}

		if ($ischange == 0) {
			if ($PASS !== '')
				printf("%s: <input type=password value=\"%s\" name=\"PASS\">", RESPASS, $PASS);
			else
				printf("%s: <input type=password name=\"PASS\">", RESPASS);

			if (defined('DEBUG')) {
				printf(" [ %s ]", $GPASS);
			} else {
				if (defined('SHOWPASS')) {
					printf(" [ %s ]", $GPASS);
				}
			}
		}
		printf("<BR>\n");
	}

	if ($reqremis == 1) {
		printf("<br><table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">", BOARDGRID, BOARDMARGBG, BOARDMARGFG);
		printf("<b>%s</b></font></td></tr><tr><td nowrap align=center><font color=\"%s\">", RESREMREQ, BOARDMARGFG);
		printf("<input type=image name=\"REQREMIS=YES\" value=\"TUX\" src=\"%s\" alt=\"%s\" border=0 align=middle>%s&nbsp;&nbsp;&nbsp;\n", BUTTONYES, MSGACCEPT, MSGACCEPT);
		printf("<input type=image name=\"REQREMIS=NO\" value=\"TUX\"  src=\"%s\" alt=\"%s\" border=0 align=middle>%s\n", BUTTONNO, MSGDIS, MSGDIS);
		printf("</font></td></tr></table></td></tr></table><br>\n");
	}

	printf("<table border=0>\n");
	printf("<tr><td bgcolor=\"%s\">\n", BOARDGRID);
	printf("<table border=0 cellspacing=2>\n");

	if ($fromx == 0 || $fromy == 0)
		printf("<tr><td bgcolor=\"%s\" align=center valgin=center><a href=\"%s?ACTION=RESUME&PASS=%s&ID=%s&SWAP=%d&RF=%d&MOVE=&MESSAGE=%s\"><img src=\"%s\" alt=\"%s\" border=0 title=\"%s\"></td>", BOARDMARGBG, $_SERVER['SCRIPT_NAME'], $PASS, $ID, $swap, (-1) * $redframes + 1, $message, REDFRAME_ICON, REDFRAME_TEXT, REDFRAME_TEXT_LONG);
	else
		printf("<tr><td bgcolor=\"%s\" align=center valgin=center><a href=\"%s?ACTION=RESUME&PASS=%s&ID=%s&SWAP=%d&RF=%d&MOVE=%c%c&MESSAGE=%s\"><img src=\"%s\" alt=\"%s\" border=0 title=\"%s\"></td>", BOARDMARGBG, $_SERVER['SCRIPT_NAME'], $PASS, $ID, $swap, (-1) * $redframes + 1, $fromx, $fromy + 48, $message, REDFRAME_ICON, REDFRAME_TEXT, REDFRAME_TEXT_LONG);

	if ($swap == 1) $i1 = 0; else $i1 = 7;
	for (;; $i1 += $swap) {
		if ($swap == 1 && $i1 > 7) break;
		if ($swap != 1 && $i1 < 0) break;
		printf("<td align=\"center\" bgcolor=\"%s\"><font color=\"%s\">%s</font></td>", BOARDMARGBG, BOARDMARGFG, chr(ord('A') + $i1));
	}

	if ($fromx == 0 || $fromy == 0)
		printf("<td bgcolor=\"%s\" align=center valign=center><a href=\"%s?PASS=%s&ID=%s&ACTION=RESUME&SWAP=%d&RF=%d&MOVE=&MESSAGE=%s\">", BOARDMARGBG, $_SERVER['SCRIPT_NAME'], $PASS, $ID, $swap * (-1), $redframes, $message);
	else
		printf("<td bgcolor=\"%s\" align=center valign=center><a href=\"%s?PASS=%s&ID=%s&ACTION=RESUME&SWAP=%d&RF=%d&MOVE=%c%c&MESSAGE=%s\">", BOARDMARGBG, $_SERVER['SCRIPT_NAME'], $PASS, $ID, $swap * (-1), $redframes, $fromx, $fromy + 48, $message);

	if ($swap == 1)
		printf("<img src=\"%s\" alt=\"%s\" border=0 title=\"%s\">", SWAP_ICON0, SWAP_TEXT0, SWAP_TEXT0_LONG);
	else
		printf("<img src=\"%s\" alt=\"%s\" border=0 title=\"%s\">", SWAP_ICON1, SWAP_TEXT1, SWAP_TEXT1_LONG);
	printf("</a></td></tr>");

	if ($swap == 1) $i1 = 0; else $i1 = 7;
	for (;; $i1 += $swap) {
		if ($swap == 1 && $i1 > 7) break;
		if ($swap != 1 && $i1 < 0) break;
		printf("<tr height=\"%d\">\n", FIELDSIZE);
		printf("<td align=\"center\" bgcolor=\"%s\">&nbsp;<font color=\"%s\">%d</font>&nbsp;</td>\n", BOARDMARGBG, BOARDMARGFG, 8 - $i1);
		if ($swap == 1) $i2 = 0; else $i2 = 7;
		for (;; $i2 += $swap) {
			if ($swap == 1 && $i2 > 7) break;
			if ($swap != 1 && $i2 < 0) break;
			$i3 = $GBOARD[$i1 * 8 + $i2];
			if (($i1 + $i2) % 2) {
				$color = BOARDBLACK;
			} else {
				$color = BOARDWHITE;
			}

			printf("<td valign=\"center\" align=\"center\" width=\"%d\" height=\"%d\" bgcolor=\"%s\">", FIELDSIZE, FIELDSIZE, $color);

			$flg = 0;
			if ((canmove($fromx, $fromy, $i2, $i1) == 1) && ($ischange == 0) && ($reqremis == 0) && ($mate != 3) && ($mate != 4)) {
				$possiblepieces++;
				$flg = 1;
			}

			list($z2, $piece, $z1, $z3, $z4) = piecepointer($i3);	/* piece, shortname, longname, image, smallimage, strokenimage */

			if (myisupper($i3))
				$shpi = strtoupper($z2);
			else
				$shpi = strtolower($z2);



			if (($z1 === null) || ($piece === null) || ($color === null)) {
				printf("&nbsp;\n");
			} else {
				if (($fromx == (ord('A') + $i2)) && ($fromy == (8 - $i1)))	/* active piece */
					printf("<table border=0 bgcolor=\"%s\" cellspacing=3 cellpadding=0><tr><td bgcolor=\"%s\">\n", SELECTEDCOLOR, $color);
				if (($tox == (ord('A') + $i2)) && ($toy == (8 - $i1)) && ($ischange == 1)) /* active piece */
					printf("<table border=0 bgcolor=\"%s\" cellspacing=3 cellpadding=0><tr><td bgcolor=\"%s\">\n", SELECTEDCOLOR, $color);

				if ($flg == 1) {  /* canmove */
					if ($redframes == 1) {
						printf("<table border=0 bgcolor=\"red\" cellspacing=3 cellpadding=0><tr><td bgcolor=\"%s\">\n", $color);
					}

					if ($moveto == 1)	/* move TO field */
						printf("<input type=image src=\"%s\" alt=\"%s\" name=\"MOVE=%c%d%c%d\" value=\"TUX\" title=\"%s\">", $z1, $shpi, $fromx, $fromy, ord('A') + $i2, 8 - $i1, $piece);
					else						/* move FROM field */
						printf("<input type=image src=\"%s\" alt=\"%s\" name=\"MOVE=%c%d\" value=\"TUX\" title=\"%s\">", $z1, $shpi, ord('A') + $i2, 8 - $i1, $piece);

					if ($redframes == 1) {
						printf("</td></tr></table>\n");
					}

				} else {	/* can NOT move */
					if (($fromx == (ord('A') + $i2)) && ($fromy == (8 - $i1)))
						printf("<input type=image src=\"%s\" alt=\"%s\" name=\"MOVE=CANC\" value=\"TUX\" title=\"%s\">", $z1, $shpi, $piece);
					else
						printf("<img border=0 src=\"%s\" alt=\"%s\" title=\"%s\">", $z1, $shpi, $piece);
				}
			}

			if (($fromx == (ord('A') + $i2)) && ($fromy == (8 - $i1)))  /* active piece */
				printf("</td></tr></table>\n");
			if (($tox == (ord('A') + $i2)) && ($toy == (8 - $i1)) && ($ischange == 1))  /* active piece */
				printf("</td></tr></table>\n");

			printf("</td>\n");
		}
		printf("<td align=\"center\" bgcolor=\"%s\">&nbsp;<font color=\"%s\">%d</font>&nbsp;</td>\n", BOARDMARGBG, BOARDMARGFG, 8 - $i1);
		printf("</tr>\n");

	}
	printf("<tr><td bgcolor=\"%s\">&nbsp;</td>", BOARDMARGBG);
	if ($swap == 1) $i1 = 0; else $i1 = 7;
	for (;; $i1 += $swap) {
		if ($swap == 1 && $i1 > 7) break;
		if ($swap != 1 && $i1 < 0) break;
		printf("<td align=\"center\" bgcolor=\"%s\"><font color=\"%s\">%s</font></td>", BOARDMARGBG, BOARDMARGFG, chr(ord('A') + $i1));
	}
	printf("<td bgcolor=\"%s\">&nbsp;</td></tr>", BOARDMARGBG);
	printf("</table>\n");
	printf("</td>");
	printf("</tr></table>\n");

	if ($mate == 0) {
		if ($reqremis == 0) {
			printf("<br><table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">", BOARDGRID, BOARDMARGBG, BOARDMARGFG);
			printf("<input type=radio name=SPECIAL value=PASS>%s</input>", SPECIALRESENT);
			printf("&nbsp;<input type=radio name=SPECIAL value=REMIS>%s</input>", SPECIALREMIS);
			printf("&nbsp;<input type=radio name=SPECIAL value=GIVEUP>%s</input>", SPECIALGIVEUP);
			printf("&nbsp;<input type=radio name=SPECIAL value=EMAIL>%s</input>", SPECIALSETTINGS);
			printf("&nbsp;<input type=radio name=SPECIAL value=LOGOUT>%s</input>", SPECIALLOGOUT);
			printf("&nbsp;<br><input type=submit value=\"%s\">", SPECIALDO);
			printf("</font></td></tr></table></td></tr></table>\n");
		}
		if ($message !== null)
			printf("<br>%s:<br><input type=text size=\"%d\" name=MESSAGE value=\"%s\">", RESMESSAGE, MESSAGEBOXLEN, $message);
		else
			printf("<br>%s:<br><input type=text size=\"%d\" name=MESSAGE value=\"\">", RESMESSAGE, MESSAGEBOXLEN);

		printf("</FORM>\n");
	}

	if ($ischange == 0) {
		movesout(true, $gmoves, $gnummoves, $ID, $swap);
	}

	printf("<br><font size=-1><b>%s: %s &nbsp;&nbsp;&nbsp; %s: %s%s.%s%s.%s%s%s%s</font>\n", RESID, $ID, RESSTART, $ID[6], $ID[7], $ID[4], $ID[5], $ID[0], $ID[1], $ID[2], $ID[3]);
}

/* NEWGAME */
function newgame() {
	global $gnummoves;

	$ID = '';
	$JID = '';

	if (defined('DEBUG')) {
		printf("%s<br>\n", $GLOBALS['query']);
	}

	$v = queryParam('JOINID');
	if ($v !== null) {
		$JID = $v;
	}

	$v = queryParam('WHITE');
	if ($v === null) {
		errorwin(ERRORDEFAULT, '', '', 0);
		return;
	}
	$white = ord($v[0]) - 48;
	if ($white != 2)
		$white = 1;

	if (defined('DEBUG')) {
		printf("White is: %d<br>\n", $white);
	}

	if ($white == 1) {
		$nextplayer = queryParam('NICK1');
		$otherplayer = queryParam('NICK2');
		$nextmail = queryParam('MAIL1');
		$othermail = queryParam('MAIL2');
		$enemyIs = 'other';
	} else {
		$nextplayer = queryParam('NICK2');
		$otherplayer = queryParam('NICK1');
		$nextmail = queryParam('MAIL2');
		$othermail = queryParam('MAIL1');
		$enemyIs = 'next';
	}

	if (($nextplayer === null) || ($otherplayer === null) || ($nextmail === null) || ($othermail === null)) {
		errorwin(ERRORDEFAULT, '', '', 0);
		return;
	}

	$message = queryParam('MESSAGE');

	if ((strlen($nextplayer) > NICKLENGTH) || (strlen($otherplayer) > NICKLENGTH)) {
		errorwin(ERRORNICKLONG, '', '', 0);
		return;
	}

	if ((strpos($nextplayer, ':') !== false) || (strpos($otherplayer, ':') !== false)) {
		errorwin(ERRORNODP, '', '', 0);
		return;
	}

	if ((strlen($nextmail) > MAILLENGTH) || (strlen($othermail) > MAILLENGTH)) {
		errorwin(ERRORMAILLONG, '', '', 0);
		return;
	}

	$ZP = localtime(time(), true);

	$ID = sprintf('%4d%2d%2d%d', $ZP['tm_year'] + 1900, $ZP['tm_mon'] + 1, $ZP['tm_mday'], getmypid());
	$ID = str_replace(' ', '0', $ID);

	if (defined('DEBUG')) {
		printf("New ID is %s<br>\n", $ID);
	}

	$PATH = DATAPATH.'/';
	$enemy = ($enemyIs === 'other') ? $othermail : $nextmail;
	if ($enemy === '') {
		if ($JID !== '') {
			errorwin(ERRORMISMAIL, '', '', 0);
			return;
		}
		$PATH .= 'X';
	}
	$PATH .= $ID;

	/* Hey, we wanna join the game.... */
	if ($JID !== '') {
		$JPATH = DATAPATH.'/'.$JID;
		if (@rename($JPATH, $PATH) === false) {
			$e = error_get_last();
			errorwin(is_array($e) && isset($e['message']) ? $e['message'] : 'rename failed', '', '', 0);
			return;
		}
	}


	if (defined('DEBUG')) {
		printf("Path=%s<br>\n", $PATH);
		printf("NextNick: %s<br>\n", $nextplayer);
		printf("NextMail: %s<br>\n", $nextmail);
		printf("OtherNick: %s<br>\n", $otherplayer);
		printf("OtherMail: %s<br>\n", $othermail);
	}

	if ($nextmail !== '') {
		if (checkmail($nextmail) < 0) {
			errorwin(ERRORMAILVALID, '', '', 0);
			return;
		}
	}

	if ($othermail !== '') {
		if (checkmail($othermail) < 0) {
			errorwin(ERRORMAILVALID, '', '', 0);
			return;
		}
	}

	if ($enemyIs != 'next' && $nextmail === '') {
		errorwin(ERRORMISMAIL, '', '', 0);
		return;
	}

	if ($enemyIs != 'other' && $othermail === '') {
		errorwin(ERRORMISMAIL, '', '', 0);
		return;
	}


	if (($othermail !== '') && ($otherplayer === '')) {
		errorwin(ERRORMISNICK, '', '', 0);
		return;
	}

	if (($nextmail !== '') && ($nextplayer === '')) {
		errorwin(ERRORMISNICK, '', '', 0);
		return;
	}

	$pass = genPass();

	if (defined('DEBUG')) {
		printf("Pass: %s\n", $pass);
	}

	$datei = @fopen($PATH, 'w');
	if ($datei === false) {
		errorwin(ERRORCREATE, '', '', 0);
		if (defined('DEBUG')) {
			printf("%s: %s<br>\n", ERRORCREATE, $PATH);
		}
		return;
	}

	$board = "rnbqkbnrppppppppeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeePPPPPPPPRNBQKBNR";

	$out = '';
	$out .= $nextplayer; $out .= "\n";
	$out .= $nextmail; $out .= "\n";
	$out .= $otherplayer; $out .= "\n";
	$out .= $othermail; $out .= "\n";
	$out .= $pass; $out .= "\n";
	$out .= "0"; $out .= "\n";
	$out .= ""; $out .= "\n";
	$out .= $board; $out .= "\n";

	if ($message !== null) {
		$out .= '@';
		$out .= $message;
	}

	fwrite($datei, $out);
	fclose($datei);
	@chmod($PATH, 0600);

	if ($enemy !== '') {
		if ($message === null) {
			w3mail($ID, $nextmail, $othermail, $nextplayer, $otherplayer, $pass, '', $board, '', $gnummoves, 0, $otherplayer, 1);
			w3mail($ID, $othermail, $othermail, $otherplayer, $nextplayer, '', '', $board, '', $gnummoves, 0, $nextplayer, -1);
		} else {
			w3mail($ID, $nextmail, $othermail, $nextplayer, $otherplayer, $pass, '', $board, $message, $gnummoves, 0, $otherplayer, 1);
			w3mail($ID, $othermail, $othermail, $otherplayer, $nextplayer, '', '', $board, $message, $gnummoves, 0, $nextplayer, -1);
		}
		back2board(STARTFINISH, '', $ID, 0);
	} else {
		back2board(STARTWAIT, '', '', 0);
	}

}

/* ISMATE */
function isMate() {
	/* ret=0: normal */
	/* ret=1: mate */
	/* ret=2: chess */
	/* ret=3: remis */
	/* ret=4: giveup */
	global $GPASS, $gnummoves;

	$ct = 0;

	if (strcmp($GPASS, "[REMIS]") == 0) {
		return 3;
	}

	if (strcmp($GPASS, "[GIVEUP]") == 0) {
		return 4;
	}

	if (strcmp($GPASS, "[MATE]") == 0) {
		return 1;
	}

	for ($i1 = 0; $i1 < 8; $i1++) {
		for ($i2 = 0; $i2 < 8; $i2++) {
			if (canmove(0, 0, $i1, $i2) == 1) $ct++;
		}
	}

	if ($ct == 0) return 1;  /* No move possible anymore.... */

	if (($gnummoves % 2) == 0) {							/* white's turn */
		$i1 = whereIsMyKing(0);
		$i2 = kingIsAttacked($i1 % 8, intdiv($i1, 8), 'K');
	} else {																/* black's turn */
		$i1 = whereIsMyKing(1);
		$i2 = kingIsAttacked($i1 % 8, intdiv($i1, 8), 'k');
	}

	if ($i2 == 1) return 2;		/* The king is attacked */

	return 0;
}

/* PIECEMOVED */
function piecemoved() {
	global $query, $GNICK1, $GNICK2, $GPLAYER1, $GPLAYER2, $GPASS, $GPASS2, $GBOARD,
	       $gmoves, $gmessage, $gnummoves, $gnomail1, $gnomail2, $globalpass;

	$MOVE = '';
	$changed = 0;
	$mustset = 1;
	$wasenp = 0;
	$movedtopiece = 'e';
	$PASS = '';
	$rightswap = 1;
	$swap = 0;

	if (defined('DEBUG')) {
		printf("Moved: %s<br>\n", $query);
	}

	/* Special moves have to be handled in RESUMEGAME */
	if (strpos($query, "SPECIAL=") !== false) {
		resumegame(0);
		return;
	}

	/* Read old prefs */
	$ID = queryParam('ID');
	if ($ID === null) {
		errorwin(ERRORMOV, '', '', 0);
		return;
	}
	if (readgamedata($ID) == -1)
		return;

	/* Should white be on top ? */
	if (($gnummoves % 2) == 0) {
		$rightswap = 1;
	} else {
		$rightswap = -1;
	}

	/* Swap Colors */
	$v = queryParam('SWAP');
	if ($v !== null) {
		$swap = (int)$v;
	}

	/* Fiddle out the move */
	$v = queryParam('MOVE');
	if ($v === null) {
		errorwin(ERRORMOV, $GPASS, $ID, $swap);
		return;
	}
	$MOVE = substr($v, 0, 4);		/* HERE OCCURED AN ERROR WITH THE MOVE=BLA= ARGUMENTS, so size 4 instead of 5 */

	if (strlen($MOVE) < 4) {
		errorwin(ERRORMOV, $GPASS, $ID, $swap);
		return;
	}

	if (!defined('NOPASS')) {
		/* Test Password */
		$v = queryParam('PASS');
		if ($v === null) {
			errorwin(ERRORPASS, '', $ID, $swap);
			return;
		}

		if (strcmp($GPASS, $v) != 0) {
			if ($MOVE === 'CANC') {	/* Cancelled and no/wrong PASS given */
				back2board(MOVECANC, '', $ID, 0);
				return;
			}
			errorwin(ERRORPASS, '', $ID, $swap);
			return;
		}
	}

	if ($MOVE === 'CANC') {
		back2board(MOVECANC, $GPASS, $ID, 0);
		return;
	}



	if (defined('DEBUG')) {
		printf("Move was: %s<br>\n", $MOVE);
	}

	/* GNUMMOVES */
	$oldnummoves = 0;
	$v = queryParam('GNUMMOVES');
	if ($v !== null) {
		$oldnummoves = (int)$v;
	}

	if (($oldnummoves > 0) && ($oldnummoves != $gnummoves)) { /* Theres not the current Board loaded */
		errorwin(ERROROLDBOARD, $PASS, $ID, $swap);
		return;
	}

	/* Test if someone tried to hack us */
	if (canmove(ord($MOVE[0]), ord($MOVE[1]) - 48, ord($MOVE[2]) - ord('A'), ord('8') - ord($MOVE[3])) != 1) {
		errorwin(ERRORILLEGAL, $PASS, $ID, $swap);
		return;
	}

	/* has the piece being changed ? */
	$v = queryParam('CHANGE');
	if ($v !== null) {
		$changed = $v[0];
		if (defined('DEBUG')) {
			printf("changed=%s\n", $changed);
		}
	}


	/* check for Pawn-Change */
	if (($changed === 0) && (strtoupper($GBOARD[(ord('8') - ord($MOVE[1])) * 8 + ord($MOVE[0]) - ord('A')]) === 'P')) {			/* The Pawn was moved */
		if (($MOVE[3] === '8') || ($MOVE[3] === '1')) {
			resumegame(1);
			return;
		}
	}

	if (defined('DEBUG')) {
		printf("From: %s<br>To: %s<br>\n", $GBOARD[(ord('8') - ord($MOVE[1])) * 8 + ord($MOVE[0]) - ord('A')], $GBOARD[(ord('8') - ord($MOVE[3])) * 8 + ord($MOVE[2]) - ord('A')]);
	}

	$movedtopiece = $GBOARD[(ord('8') - ord($MOVE[3])) * 8 + ord($MOVE[2]) - ord('A')];

	/* check for en passant and delete enemies */
	if ((strtoupper($GBOARD[(ord('8') - ord($MOVE[1])) * 8 + ord($MOVE[0]) - ord('A')]) === 'P') && ($GBOARD[(ord('8') - ord($MOVE[3])) * 8 + ord($MOVE[2]) - ord('A')] === 'e')) {		/* move an pawn to en ampty field */
		if ($MOVE[0] !== $MOVE[2]) {	/* Dont move straight to this empty field */
			if ($GBOARD[(ord('8') - ord($MOVE[1])) * 8 + ord($MOVE[0]) - ord('A')] === 'P') {	/* white pawn */
				$GBOARD[(ord('8') - ord($MOVE[3])) * 8 + ord($MOVE[2]) - ord('A') + 8] = 'e';		/* remove enemy pawn */
				$wasenp = 1;
			} else {	/* black pawn */
				$GBOARD[(ord('8') - ord($MOVE[3])) * 8 + ord($MOVE[2]) - ord('A') - 8] = 'e';    /* remove enemy pawn */
				$wasenp = 1;
			}
		}
	}

	/*---*/
	if ($changed === 0)	/* If there was no piece change, "change" the piece to itself */
		$changed = $GBOARD[(ord('8') - ord($MOVE[1])) * 8 + ord($MOVE[0]) - ord('A')];

	/* open data file */
	$path = DATAPATH.'/'.$ID;
	$datei = @fopen($path, 'w');
	if ($datei === false) {
		errorwin(ERROROPEN, $GPASS, $ID, $swap);
		if (defined('DEBUG')) {
			printf("%s: %s", $ID, ERROROPEN);
		}
		return;
	}

	/* New Moves-String */
	if ($wasenp != 0) {
		$wasenp = $changed;
		$changed = 'O';
	}

	if ($gmoves !== null) {
		$newmoves = sprintf('%s%s%s%s%s', $gmoves, $GBOARD[(ord('8') - ord($MOVE[1])) * 8 + ord($MOVE[0]) - ord('A')], $MOVE, $changed, $movedtopiece);
	} else {
		$newmoves = sprintf('%s%s%s%s', $GBOARD[(ord('8') - ord($MOVE[1])) * 8 + ord($MOVE[0]) - ord('A')], $MOVE, $changed, $movedtopiece);
	}

	if ($wasenp != 0) {
		$changed = $wasenp;
		$wasenp = 1;
	}



	if (strtoupper($GBOARD[(ord('8') - ord($MOVE[1])) * 8 + ord($MOVE[0]) - ord('A')]) === 'K') {
		if ($MOVE === 'E8G8') {	/* Black Rochade King Side */
			$GBOARD[4] = 'e';
			$GBOARD[7] = 'e';
			$GBOARD[6] = 'k';
			$GBOARD[5] = 'r';
			$mustset = 0;
		}
		if ($MOVE === 'E8C8') {  /* Black Rochade Queen Side */
			$GBOARD[4] = 'e';
			$GBOARD[0] = 'e';
			$GBOARD[2] = 'k';
			$GBOARD[3] = 'r';
			$mustset = 0;
		}
		if ($MOVE === 'E1G1') {  /* White Rochade King Side */
			$GBOARD[60] = 'e';
			$GBOARD[63] = 'e';
			$GBOARD[62] = 'K';
			$GBOARD[61] = 'R';
			$mustset = 0;
		}
		if ($MOVE === 'E1C1') {  /* White Rochade Queen Side */
			$GBOARD[60] = 'e';
			$GBOARD[56] = 'e';
			$GBOARD[58] = 'K';
			$GBOARD[59] = 'R';
			$mustset = 0;
		}
	}

	/* set the piece on the board */
	if ($mustset == 1) {	/* Board hasn`t been changed for now */
		$GBOARD[(ord('8') - ord($MOVE[3])) * 8 + ord($MOVE[2]) - ord('A')] = $changed;
		$GBOARD[(ord('8') - ord($MOVE[1])) * 8 + ord($MOVE[0]) - ord('A')] = 'e';
	}

	$PASS = genPass();

	/* search for MESSAGE */
	$v = queryParam('MESSAGE');
	if ($v !== null) {
		$z1 = $v;
	} else {
		$z1 = null;
	}

	/* save new game-data */
	$out = '';

	if ($GPASS2 !== '') {	/* Has the next player his own pass ? */
		$out .= $GPASS2;
		$out .= ':';
		if ($gnomail2 == 1) {
			$out .= '1';
			$out .= ':';
		}
	}

	$out .= $GNICK2;
	$out .= "\n";
	$out .= $GPLAYER2;
	$out .= "\n";

	if ($globalpass == 0) {
		$out .= $GPASS;
		$out .= ':';
		if ($gnomail1 == 1) {
			$out .= '1';
			$out .= ':';
		}
	}

	$out .= $GNICK1;
	$out .= "\n";
	$out .= $GPLAYER1;
	$out .= "\n";
	$out .= $PASS;
	$out .= "\n";
	$out .= ($gnummoves + 1);
	$out .= "\n";
	$out .= $newmoves;
	$out .= "\n";
	$out .= $GBOARD;
	if ($z1 !== null) {
		$out .= "\n";
		$out .= '@';		/* This are real messages, they have to begin with @ */
		$out .= $z1;
		$out .= "\n";
	}
	fwrite($datei, $out);
	fclose($datei);
	@chmod($path, 0600);

	$TNICK = $GNICK1;
	$GNICK1 = $GNICK2;
	$GNICK2 = $TNICK;

	$TPLAYER = $GPLAYER1;
	$GPLAYER1 = $GPLAYER2;
	$GPLAYER2 = $TPLAYER;

	if ($GPASS2 !== '')	/* Has the next player his own pass ? */
		$GPASS = substr($GPASS2, 0, 11);
	else
		$GPASS = substr($PASS, 0, 11);

	$gnummoves++;

	$gmoves = $newmoves;

	$i1 = isMate();

	/* Warning ! Here are GPLAYER1 and GPLAYER2 already changed, cause its nedded for isMate, but gnomail is not changed ! */

	if ($z1 !== null) {
		if ($gnomail2 != 1 || $i1 == 1 || $i1 == 3 || $i1 == 4)
			w3mail($ID, $GPLAYER1, $GPLAYER2, $GNICK1, $GNICK2, $GPASS, $gmoves, $GBOARD, $z1, $gnummoves, $i1, $GNICK2, (-1) * $rightswap);
	} else {
		if ($gnomail2 != 1 || $i1 == 1 || $i1 == 3 || $i1 == 4)
			w3mail($ID, $GPLAYER1, $GPLAYER2, $GNICK1, $GNICK2, $GPASS, $gmoves, $GBOARD, '', $gnummoves, $i1, $GNICK2, (-1) * $rightswap);
	}
	if ($gnomail1 != 1 || $i1 == 1 || $i1 == 3 || $i1 == 4)
		w3mail($ID, $GPLAYER2, $GPLAYER2, $GNICK2, $GNICK2, '', $gmoves, $GBOARD, '', $gnummoves, $i1, $GNICK1, $rightswap);

	if ($i1 == 1) {	/* is Mate */
		rewriteData($ID, "[MATE]", null, null, 0);
	}

	if (!defined('DEBUG')) {
		back2board(MOVEDFINISH, '', $ID, $swap);
	} else {
		printf("<a href=\"%s?ACTION=RESUME&ID=%s\">%s</a>\n", $_SERVER['SCRIPT_NAME'], $ID, MOVEDFINISH);
	}

}

/* CHANGEMAIL and SETTINGS*/
function changemail() {
	global $GNICK1, $GNICK2, $GPLAYER1, $GPLAYER2, $GPASS, $GPASS2, $GBOARD,
	       $gmessage, $gmoves, $gnummoves, $gnomail1, $gnomail2, $globalpass;

	$ID = queryParam('ID');
	if ($ID === null) {
		errorwin(ERRORDEFAULT, '', '', 0);
		return;
	}
	if (readgamedata($ID) == -1)
		return;

	/* Swap Colors */
	$swap = 0;
	$v = queryParam('SWAP');
	if ($v !== null) {
		$swap = (int)$v;
	}

	$v = queryParam('PASS');
	if ($v === null) {
		errorwin(ERRORDEFAULT, '', $ID, $swap);
		return;
	}
	if (strcmp($v, $GPASS) != 0) {
		errorwin(ERRORDEFAULT, '', $ID, $swap);
		return;
	}

	/* New Mail */
	$v = queryParam('MAIL1');
	if ($v === null) {
		errorwin(ERRORDEFAULT, $GPASS, $ID, $swap);
		return;
	}
	if (checkmail($v) == 0) {
		rewriteEmail($ID, $v);
	} else {
		errorwin(ERRORMAILVALID, $GPASS, $ID, $swap);
		return;
	}

	/* New and fixed Password */
	$v = queryParam('FIXPASS');
	if ($v !== null) {
		if (strlen($v) > 10) {
			errorwin(ERRORPASSLONG, $GPASS, $ID, $swap);
			return;
		}

		if (strlen($v) <= 0) {
			$globalpass = 1;
			$gnomail1 = 0;
		} else {
			$v2 = queryParam('FIXPASSA');
			if ($v2 !== null) {
				if (strcmp($v, $v2) != 0) {
					errorwin(ERRORPASSMATCH, $GPASS, $ID, $swap);
					return;
				}
				/* Passwords match, copy the first in GPASS */
				$GPASS = substr($v, 0, 11);
				$globalpass = 0;
			}


			/* No Mail - only if Passwort is set*/
			$gnomail1 = 0;
			$v3 = queryParam('NOMAILTOME');
			if ($v3 !== null) {
				if (strcasecmp($v3, "on") == 0) {
					$gnomail1 = 1;
				}
			}
		}
		/* All Data to disk ! */
		rewriteData($ID, null, null, null, 0);
	}
	back2board(SPECIALSETTINGSCHANGED, $GPASS, $ID, $swap);
}

/* JOINGAME */
function joingame() {
	global $gmessage, $GNICK1, $GNICK2, $GPLAYER1, $GPLAYER2;

	/* GameID */
	$ID = queryParam('ID');
	if ($ID === null) {
		errorwin(ERRORDEFAULT, '', '', 0);
		return;
	}
	if (readgamedata($ID) == -1)
		return;

	printf("<table border=0 width=\"*\"><tr bgcolor=\"%s\"><td align=center>\n", BOARDMARGBG);
	printf("<font size=\"+1\" color=\"%s\"><b>%s</b></font>\n", BOARDMARGFG, STARTANEWGAME);
	printf("</td></tr><tr><td align=center>\n");

	if ($gmessage !== null) {
		$z1 = strpos($gmessage, '@');
		if ($z1 !== false) {
			printf("<b>%s: <font color=\"%s\">%s</font></b><br><br>\n", RESMESSAGE, MSGCOLOR, substr($gmessage, $z1 + 1));
		}
	}

	printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
	printf("<INPUT TYPE=hidden name=ACTION value=NEW>\n");
	printf("<INPUT TYPE=hidden name=JOINID value=%s>\n", $ID);
	printf("<INPUT TYPE=hidden name=WHITE value=1>\n", $ID);
	printf("<TABLE BORDER=0><TR>\n");

	if ($GPLAYER1 === '') {
		printf("<TD>%s: </TD><TD><b>%s</b></TD>\n", STARTGAMENICK2, $GNICK2);
		printf("</TR><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=NICK1 SIZE=%d></TD>\n", STARTGAMENICK1, NICKLENGTH);
		printf("</TR><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=MAIL1 SIZE=%d></TD>\n", STARTGAMEMAIL1, MAILLENGTH);
		printf("</TR><TR>\n");
		printf("<INPUT TYPE=hidden name=NICK2 value=%s>\n", $GNICK2);
		printf("<INPUT TYPE=hidden name=MAIL2 value=%s>\n", $GPLAYER2);
	} else {
		printf("<TD>%s: </TD><TD><b>%s</b></TD>\n", STARTGAMENICK1, $GNICK1);
		printf("</TR><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=NICK2 SIZE=%d></TD>\n", STARTGAMENICK2, NICKLENGTH);
		printf("</TR><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=MAIL2 SIZE=%d></TD>\n", STARTGAMEMAIL2, MAILLENGTH);
		printf("</TR><TR>\n");
		printf("<INPUT TYPE=hidden name=NICK1 value=%s>\n", $GNICK1);
		printf("<INPUT TYPE=hidden name=MAIL1 value=%s>\n", $GPLAYER1);
	}
	printf("</TR></TABLE><BR>\n");
	printf("%s: <INPUT TYPE=text NAME=MESSAGE size=\"%d\" value=\"\">\n", RESMESSAGE, MESSAGEBOXLEN);
	printf("<BR><BR><INPUT TYPE=submit value=\"%s\">\n", STARTNEW);
	printf("</FORM>\n");

	printf("</td></tr></table>\n");

}

/* SENDGAMES */
function sendgames() {
	global $GPASS, $GPLAYER1, $GPLAYER2, $GNICK1, $GNICK2, $gmoves, $gnummoves;

	$pipe = null;
	$onlyweb = 0;

	$v = queryParam('ONLYWEB');
	if ($v !== null) {
		if ($v !== '') {
			if (strcasecmp($v, "on") == 0) $onlyweb = 1;
		}
	}

	$v = queryParam('MAIL1');
	if ($v === null) {
		errorwin(ERRORDEFAULT, '', '', 0);
		return;
	}
	$z1 = $v;
	if ($z1 === '') {
		errorwin(ERRORMISMAIL, '', '', 0);
		return;
	}
	if (checkmail($z1) != 0) {
		errorwin(ERRORMAILVALID, '', '', 0);
		return;
	}

	if ($onlyweb != 1) {
		$smail = SENDMAIL.' '.escapeshellarg($z1);
	} else {
		printf("<br><b>%s:</b> %s<br><br>", ADMIN_GAMES_OF, $z1);
		printf("<table border=0 cellspacing=0 cellpadding=0><tr bgcolor=\"%s\"><td><table cellpadding=3 cellspacing=3>", BOARDGRID);
		printf("<tr bgcolor=\"%s\"><td><font color=\"%s\"><b>%s</b></font></td><td align=\"center\"><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td></tr>", BOARDMARGBG, BOARDMARGFG, ADMIN_TABLE_START, BOARDMARGFG, ADMIN_NUM, BOARDMARGFG, ADMIN_AGAINST, BOARDMARGFG, ADMIN_TABLE_ACC, BOARDMARGFG, ADMIN_STATUS);
	}

	$gamedir = @opendir(DATAPATH);
	if ($gamedir === false) {
		errorwin(ERRORGAMEDIR, '', '', 0);
		return;
	}
	while (($direntry = readdir($gamedir)) !== false) {
		if ($direntry[0] !== 'X') {
			if (isnumber($direntry)) {
				$path = DATAPATH.'/'.$direntry;

				$mtime = @filemtime($path);
				if ($mtime !== false) {

					readgamedata($direntry);

					if (!defined('NOMAIL')) {
						if ($onlyweb != 1) {
							if ($pipe === null) {
								if ((strcasecmp($z1, $GPLAYER1) == 0) || (strcasecmp($z1, $GPLAYER2) == 0)) {
									$pipe = @popen($smail, 'w');
									if ($pipe === false) {
										errorwin(ERRORPIPE, '', '', 0);
										return;
									}
									if (defined('CHARSET')) {
										fprintf($pipe, "Mime-Version: 1.0\n");
										fprintf($pipe, "Content-Type: text/plain; charset=%s\n", CHARSET);
										fprintf($pipe, "Content-Transfer-Encoding: 8bit\n");
									}
									if (defined('NOREPLYADDRESS')) {
										fprintf($pipe, "From: %s\n", NOREPLYADDRESS);
									} else {
										fprintf($pipe, "From: %s\n", $z1);
									}
									fprintf($pipe, "To: %s\n", $z1);
									fprintf($pipe, "Subject: %s\n\n", SUBJECT);
									if (strcasecmp($z1, $GPLAYER1) == 0)
										fprintf($pipe, "%s %s\n", MAILDEAR, $GNICK1);
									else
										fprintf($pipe, "%s %s\n", MAILDEAR, $GNICK2);
									fprintf($pipe, "%s:\n\n", SENDGAMESPLAY);
								}
							}
						}
					}
					$i1 = isMate();
					if (strcasecmp($z1, $GPLAYER1) == 0) {
						if (defined('NOMAIL')) {
							printf("Open Game (ID=%s)  <b>%s against %s</b> password:%s<br>\n", $direntry, $GNICK1, $GNICK2, $GPASS);
						} else {
							if ($onlyweb != 1) {
								fwrite($pipe, "\n");
								fprintf($pipe, RESTITLE, $GNICK1, $GNICK2);
								fprintf($pipe, "\n%s: %s\n", RESID, $direntry);
								fprintf($pipe, "%s: %s\n", ADMIN_TABLE_ACC, ctime($mtime));
								if ($i1 == 0 || $i1 == 2) {	// Check or "normal"
									fprintf($pipe, "%s %s\n\n", MAILMOVE, $GPASS);
								}
							} else {
								printf("<tr bgcolor=\"%s\"><td><font color=\"%s\">", BOARDMARGBG, BOARDMARGFG);
								printf("%s%s.%s%s.%s%s%s%s", cchar($direntry, 6), cchar($direntry, 7), cchar($direntry, 4), cchar($direntry, 5), cchar($direntry, 0), cchar($direntry, 1), cchar($direntry, 2), cchar($direntry, 3));
								printf("</font></td><td align=\"center\"><font color=\"%s\">", BOARDMARGFG);
								printf("%d", $gnummoves);
								printf("</font></td><td><font color=\"%s\">%s</font></td>", BOARDMARGFG, $GNICK2);
								printf("<td><font color=\"%s\">%s</font></td>", BOARDMARGFG, ctime($mtime));
								printf("<td align=center><a href=\"http://%s%s?ID=%s&ACTION=RESUME\"><font color=\"%s\">", $_SERVER['SERVER_NAME'], $_SERVER['SCRIPT_NAME'], $direntry, BOARDMARGFG);
								if ($i1 == 0 || $i1 == 2) {  // Check or "normal"
									printf("%s", ADMIN_STATUS_YOU);
								} else {
									printf("%s", ADMIN_STATUS_FIN);
								}
								printf("</font></a></td></tr>");
							}
						}
					} else {
						if (strcasecmp($z1, $GPLAYER2) == 0) {
							if (defined('NOMAIL')) {
								printf("Open Game (ID=%s)  %s against: %s<br>\n", $direntry, $GNICK2, $GNICK1);
							} else {
								if ($onlyweb != 1) {
									fwrite($pipe, "\n");
									fprintf($pipe, RESTITLE, $GNICK1, $GNICK2);
									fprintf($pipe, "\n%s: %s\n", RESID, $direntry);
									fprintf($pipe, "%s: %s\n", ADMIN_TABLE_ACC, ctime($mtime));
								} else {
									printf("<tr bgcolor=\"%s\"><td><font color=\"%s\">", BOARDMARGBG, BOARDMARGFG);
									printf("%s%s.%s%s.%s%s%s%s", cchar($direntry, 6), cchar($direntry, 7), cchar($direntry, 4), cchar($direntry, 5), cchar($direntry, 0), cchar($direntry, 1), cchar($direntry, 2), cchar($direntry, 3));
									printf("</font></td><td align=\"center\"><font color=\"%s\">", BOARDMARGFG);
									printf("%d", $gnummoves);
									printf("</font></td><td><font color=\"%s\">%s</font></td>", BOARDMARGFG, $GNICK1);
									printf("<td><font color=\"%s\">%s</font></td>", BOARDMARGFG, ctime($mtime));
									printf("<td align=center><a href=\"http://%s%s?ID=%s&ACTION=RESUME\"><font color=\"%s\">", $_SERVER['SERVER_NAME'], $_SERVER['SCRIPT_NAME'], $direntry, BOARDMARGFG);
									if ($i1 == 0 || $i1 == 2) {  // Check or "normal"
										printf("%s", ADMIN_STATUS_NOT_YOU);
									} else {
										printf("%s", ADMIN_STATUS_FIN);
									}
									printf("</font></a></td></tr>");
								}
							}
						} else {
							$i1 = 0;		/* Don't send "Mail finished" */
						}
					}

					if (!defined('NOMAIL')) {
						if ($onlyweb != 1) {
							if ($pipe !== null) {
								if ($i1 == 1 || $i1 == 3 || $i1 == 4) {
									fprintf($pipe, "%s\n", MAILFINISHED);
								}
							}
						}
					}
				}

			}

		}
	}


	if ($onlyweb != 1) {
		if ($pipe !== null) {
			fprintf($pipe, "\n\n%s: http://%s%s\n\n", MAILBOARD, $_SERVER['SERVER_NAME'], $_SERVER['SCRIPT_NAME']);
			fprintf($pipe, "%s\n\n", SENDGAMESBYE);
			pclose($pipe);
			back2board(RESREQUESTSENT, '', '', 0);
		} else {
			errorwin(SENDGAMENOTFOUND, '', '', 0);
		}
	} else {
		printf("</table></td></tr></table>\n");
		printf("<br>[<a href=\"http://%s%s\">%s</a>]", $_SERVER['SERVER_NAME'], $_SERVER['SCRIPT_NAME'], SENDBACK);
	}

	return;
}

/* LISTGAMES */
function listgames($onlylist) {
	global $GNICK1, $GNICK2, $GPLAYER1, $GPLAYER2, $GPASS, $gmoves, $gnummoves;

	/* List games */
	$gamedir = @opendir(DATAPATH);
	if ($gamedir === false) {
		printf("<b><font color=\"%s\">%s</font></b>", WARNCOLOR, ERRORGAMEDIR);
	}
	printf("<table border=0 cellpadding=0 cellspacing=0><tr bgcolor=\"%s\"><td><table cellpadding=3 cellspacing=3 border=0>", BOARDGRID);
	printf("<tr bgcolor=\"%s\"><td><font color=\"%s\"><b>%s</b></font></td><td align=\"center\"><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td>", BOARDMARGBG, BOARDMARGFG, ADMIN_TABLE_START, BOARDMARGFG, ADMIN_NUM, BOARDMARGFG, ADMIN_TABLE_P1, BOARDMARGFG, ADMIN_TABLE_P2, BOARDMARGFG, ADMIN_TABLE_ACC);
	if ($onlylist == 0) {
		printf("<td><font color=\"%s\"><b>%s</b></font></td>", BOARDMARGFG, ADMIN_TABLE_PW);
	}
	if (defined('ALLOWREMOVE')) {
		if ($onlylist == 0) {
			printf("<td>&nbsp;</td>");
		}
	}
	printf("</tr>");
	if ($gamedir !== false) {
		while (($direntry = readdir($gamedir)) !== false) {
			if (isnumber($direntry)) {
				$path = DATAPATH.'/'.$direntry;
				$mtime = @filemtime($path);
				if ($mtime !== false) {
					readgamedata($direntry);
					printf("<tr bgcolor=\"%s\"><td><font color=\"%s\">", BOARDMARGBG, BOARDMARGFG);
					if ($direntry[0] !== 'X')
						printf("<a style=\"color:%s\" target=\"_blank\" href=\"%s?ACTION=RESUME&ID=%s\">", BOARDMARGFG, $_SERVER['SCRIPT_NAME'], $direntry);
					else
						printf("<a style=\"color:%s\" target=\"_blank\" href=\"%s?ACTION=JOIN&ID=%s\">", BOARDMARGFG, $_SERVER['SCRIPT_NAME'], $direntry);

					if ($direntry[0] === 'X')
						printf("%s%s.%s%s.%s%s%s%s", cchar($direntry, 7), cchar($direntry, 8), cchar($direntry, 5), cchar($direntry, 6), cchar($direntry, 1), cchar($direntry, 2), cchar($direntry, 3), cchar($direntry, 4));
					else
						printf("%s%s.%s%s.%s%s%s%s", cchar($direntry, 6), cchar($direntry, 7), cchar($direntry, 4), cchar($direntry, 5), cchar($direntry, 0), cchar($direntry, 1), cchar($direntry, 2), cchar($direntry, 3));
					printf("</a>");
					if ($onlylist == 0) {
						printf("</font></td><td align=\"center\"><font color=\"%s\">%d</font></td><td><font color=\"%s\"><a style=\"color:%s\" href=\"mailto:%s\">%s</a></font></td><td><font color=\"%s\"><a style=\"color:%s\" href=\"mailto:%s\">%s</a></font></td><td><font color=\"%s\">%s</font></td><td><font color=\"%s\">%s</font></td>\n", BOARDMARGFG, $gnummoves, BOARDMARGFG, BOARDMARGFG, $GPLAYER1, $GNICK1, BOARDMARGFG, BOARDMARGFG, $GPLAYER2, $GNICK2, BOARDMARGFG, ctime($mtime), BOARDMARGFG, $GPASS);
					} else {
						printf("</font></td><td align=\"center\"><font color=\"%s\">%d</font></td><td><font color=\"%s\">%s</font></td><td><font color=\"%s\">%s</font></td><td><font color=\"%s\">%s</font></td>\n", BOARDMARGFG, $gnummoves, BOARDMARGFG, $GNICK1, BOARDMARGFG, $GNICK2, BOARDMARGFG, ctime($mtime));
					}

					if (defined('ALLOWREMOVE')) {
						if ($onlylist == 0) {
							printf("<td>");
							printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
							printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
							printf("<INPUT TYPE=hidden name=DELETE value=%s>\n", $direntry);
							printf("<INPUT NAME=\"PASS3\" TYPE=\"password\" size=%d maxlength=%d>\n", ADMPASSLEN, ADMPASSLEN);
							printf("<input type=\"submit\" value=\"%s\">\n", ADMIN_DELETE);
							printf("</FORM>");
							printf("</td>");
						}
					}
					printf("</tr>\n");
				}
			}
		}
	}
	printf("</table></td></tr></table></font>\n");
	printf("<br><b><a href=\"%s\">%s</a></b><br>\n", $_SERVER['SCRIPT_NAME'], ADMIN_END);

}

if (defined('ADMIN')) {

/* SAVEADMINPASS */
function saveAdminPass($pass1) {

	$pass = @fopen(ADMINPASS, 'w');
	if ($pass === false) {
		printf("<br><b><font color=\"%s\">%s</font></b>\n", WARNCOLOR, ADMIN_EOPASS);
		printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
		printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
		printf("<br><input type=\"submit\" value=\"%s\">\n", ADMIN_OK);
		printf("</FORM>\n");
		return 1;
	}

	$out = '';
	if (defined('CRYPT')) {
		if (strlen(CRYPTKEY) == 2) {
			$key = CRYPTKEY;
			$out = crypt($pass1, $key);
		} else {
			if (strlen(CRYPTKEY) == 8) {
				$key = '$1$'.CRYPTKEY.'$';
				$out = crypt($pass1, $key);
			} else {
				printf("<b><font color=\"%s\">%s</font></b><br>\n", WARNCOLOR, ADMIN_EKEY);
				$out = $pass1;
			}
		}
	} else {
		$out = $pass1;
	}
	fwrite($pass, $out);
	fclose($pass);
	@chmod(ADMINPASS, 0600);
	printf("<b><font color=\"%s\">%s</font></b><br>", WARNCOLOR, ADMIN_PWSAVED);

	return 0;
}


/* ADMIN */
function admin() {
	global $query;

	$pass1 = '';
	$pass2 = '';
	$pass3 = '';

	$passed = 0;

	/* Read old prefs */
	$v = queryParam('PASS1');
	if ($v !== null) {
		$pass1 = substr($v, 0, ADMPASSLEN);
	}

	$v = queryParam('PASS2');
	if ($v !== null) {
		$pass2 = substr($v, 0, ADMPASSLEN);
	}

	$v = queryParam('PASS3');
	if ($v !== null) {
		$pass3 = substr($v, 0, ADMPASSLEN);
	}

	$passfile = @file(ADMINPASS, FILE_IGNORE_NEW_LINES);
	if ($passfile === false) {

		if ((strncmp($pass1, $pass2, ADMPASSLEN) == 0) && ($pass1 !== '')) {
			printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">", BOARDGRID, BOARDMARGBG, BOARDMARGFG);
			if (saveAdminPass($pass1) == 1)
				return;
			printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
			printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
			printf("<INPUT TYPE=hidden name=PASS3 value=\"%s\">\n", $pass1);
			printf("<br><input type=\"submit\" value=\"%s\">\n", ADMIN_OK);
			printf("</FORM>\n");
			printf("</font></td></tr></table></td></tr></table>\n");

		} else {
			printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center>", BOARDGRID, BOARDMARGBG);
			if ((strncmp($pass1, $pass2, ADMPASSLEN) != 0) && ($pass1 !== '')) {
				printf("<b><font color=\"%s\">%s</font></b><br>\n", WARNCOLOR, ADMIN_PASSDIFFER);
			}
			printf("<b><font color=\"%s\">%s</font></b><br><br>\n", BOARDMARGFG, ADMIN_SPECIFY_PASS);
			printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
			printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
			printf("<TABLE border=0>");
			printf("<tr><td>&nbsp;<b><font color=\"%s\">%s</font><b></td><td><input name=\"PASS1\" type=\"password\" size=%d maxlength=%d>&nbsp;</td></tr>\n", BOARDMARGFG, ADMIN_PASS, ADMPASSLEN, ADMPASSLEN);
			printf("<tr><td>&nbsp;<b><font color=\"%s\">%s</font><b></td><td><input name=\"PASS2\" type=\"password\" size=%d maxlength=%d>&nbsp;</td></tr>\n", BOARDMARGFG, ADMIN_PASS_RE, ADMPASSLEN, ADMPASSLEN);
			printf("</TABLE>\n");
			printf("<br><input type=\"submit\" value=\"%s\">\n", ADMIN_OK);
			printf("</FORM>");
			printf("</td></tr></table></td></tr></table>\n");
		}
	} else {

		$savedpass = (isset($passfile[0]) ? $passfile[0] : '');

		if ($pass3 === '') {
			printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">", BOARDGRID, BOARDMARGBG, BOARDMARGFG);
			printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
			printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
			printf("&nbsp;<b>%s<b>&nbsp;<input name=\"PASS3\" type=\"password\" size=%d maxlength=%d>&nbsp;<br><br>\n", ADMIN_PASS, ADMPASSLEN, ADMPASSLEN);
			printf("<input type=\"submit\" value=\"%s\"><br>\n", ADMIN_OK);
			printf("</FORM>");
			printf("</font></td></tr></table></td></tr></table>\n");
		}

		// hier muss bei Passwort-Aenderungen zusaetzlich nach dem aktuellen Passwort gefragt und dieses verglichen werden !

			if ($pass3 !== '') {

				if (defined('CRYPT')) {
					if (strlen(CRYPTKEY) == 2) {
						$key = CRYPTKEY;
						if (strcmp(crypt($pass3, $key), $savedpass) == 0)
							$passed = 1;
					} else {
						if (strlen(CRYPTKEY) == 8) {
							$key = '$1$'.CRYPTKEY.'$';
							if (strcmp(crypt($pass3, $key), $savedpass) == 0)
								$passed = 1;
						} else {
							printf("<b><font color=\"%s\">%s</font></b><br>\n", WARNCOLOR, ADMIN_EKEY);
							if (strcmp($savedpass, $pass3) == 0)
								$passed = 1;
						}
					}
				} else {
					if (strcmp($savedpass, $pass3) == 0)
						$passed = 1;
				}

				if ($passed != 1) {
					printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">", BOARDGRID, BOARDMARGBG, BOARDMARGFG);
					printf("<b><font color=\"%s\">%s</font></b><br>\n", WARNCOLOR, ADMIN_WRONGPASS);
					printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
					printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
					printf("<br><input type=\"submit\" value=\"%s\">\n", ADMIN_OK);
					printf("</FORM>\n");
					printf("</font></td></tr></table></td></tr></table>\n");
				}

			}

		if ($passed == 1) {

			/* Password changed ? */
			if ($pass1 !== '') {
				if (strcmp($pass1, $pass2) == 0) {
					if (saveAdminPass($pass1) == 1)
						return;
				} else {
					printf("<b><font color=\"%s\">%s</font></b><br>\n", WARNCOLOR, ADMIN_PASSDIFFER);
				}
			}

			/* Delete requested ? */
			$v = queryParam('DELETE');
			if ($v !== null) {
				if (isnumber($v)) {
					$path = DATAPATH.'/'.$v;
					if (defined('ALLOWREMOVE')) {
						@unlink($path);
					}
				}
			}

			/* Pasword Change Fields */
			printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">", BOARDGRID, BOARDMARGBG, BOARDMARGFG);
			printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
			printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
			printf("<b>%s<b><input name=\"PASS3\" type=\"password\" size=%d maxlength=%d>\n", ADMIN_PASS_OLD, ADMPASSLEN, ADMPASSLEN);
			printf("&nbsp;<b>%s<b><input name=\"PASS1\" type=\"password\" size=%d maxlength=%d>\n", ADMIN_PASS, ADMPASSLEN, ADMPASSLEN);
			printf("&nbsp;<b>%s<b><input name=\"PASS2\" type=\"password\" size=%d maxlength=%d>\n", ADMIN_PASS_RE, ADMPASSLEN, ADMPASSLEN);
			printf("<input type=\"submit\" value=\"%s\">\n", ADMIN_OK);
			printf("</FORM>");
			printf("</font></td></tr></table></td></tr></table><br>\n");

			/* List games */
			listgames(0);
		}
	}

}
}

/* VIEW */
function view() {
	global $GNICK1, $GNICK2, $GPLAYER1, $GPLAYER2, $GPASS, $gmoves, $gnummoves, $query;

	$number = -1;

	/* GameID */
	$ID = queryParam('ID');
	if ($ID === null) {
		errorwin(ERRORDEFAULT, '', '', 0);
		return;
	}
	if (readgamedata($ID) == -1)
		return;

	/* Which Move */
	$v = queryParam('NUM');
	if ($v !== null) {
		$number = (int)$v;
	}

	if ($number < 0) {
		$pos = strpos($query, 'NUM');
		if ($pos !== false) {
			$s = substr($query, $pos + 3);
			$end = strcspn($s, '.&');
			$number = (int)substr($s, 0, $end);
		}
	}

	if ($number < 0)
		$number = 0;

	/* Swap Colors */
	$swap = 1;
	$v = queryParam('SWAP');
	if ($v !== null) {
		$swap = (int)$v;
	}

	if (($swap != 1) && ($swap != -1))
		$swap = 1;

	$moves = ($gmoves !== null) ? $gmoves : '';
	$sl = strlen($moves);
	$z1 = 0;
	$board = "rnbqkbnrppppppppeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeePPPPPPPPRNBQKBNR";
	for ($i1 = 0; $i1 < $number && $i1 < $sl; $i1++) {

		if (cchar($moves, $z1 + 5) !== 'O' && strncmp(substr($moves, $z1 + 1, 4), "E8G8", 4) != 0 && strncmp(substr($moves, $z1 + 1, 4), "E8C8", 4) != 0 && strncmp(substr($moves, $z1 + 1, 4), "E1G1", 4) != 0 && strncmp(substr($moves, $z1 + 1, 4), "E1C1", 4) != 0) {	/* Normal move or Pawn Change */
			$board[(ord('8') - ord(cchar($moves, $z1 + 2))) * 8 + ord(cchar($moves, $z1 + 1)) - ord('A')] = 'e';	/* Old field now empty */
			$board[(ord('8') - ord(cchar($moves, $z1 + 4))) * 8 + ord(cchar($moves, $z1 + 3)) - ord('A')] = cchar($moves, $z1 + 5);
		}
		if (strncmp(substr($moves, $z1 + 1, 4), "E8G8", 4) == 0) {
			$board[4] = 'e';
			$board[7] = 'e';
			$board[6] = 'k';
			$board[5] = 'r';
		}
		if (strncmp(substr($moves, $z1 + 1, 4), "E8C8", 4) == 0) {
			$board[4] = 'e';
			$board[0] = 'e';
			$board[2] = 'k';
			$board[3] = 'r';
		}
		if (strncmp(substr($moves, $z1 + 1, 4), "E1G1", 4) == 0) {
			$board[60] = 'e';
			$board[63] = 'e';
			$board[62] = 'K';
			$board[61] = 'R';
		}
		if (strncmp(substr($moves, $z1 + 1, 4), "E1C1", 4) == 0) {
			$board[60] = 'e';
			$board[56] = 'e';
			$board[58] = 'K';
			$board[59] = 'R';
		}

		if (cchar($moves, $z1 + 5) === 'O') {	/* En Passant */
			$board[(ord('8') - ord(cchar($moves, $z1 + 2))) * 8 + ord(cchar($moves, $z1 + 1)) - ord('A')] = 'e';
			if (cchar($moves, $z1) === 'P')
				$board[(ord('8') - ord(cchar($moves, $z1 + 4))) * 8 + ord(cchar($moves, $z1 + 3)) - ord('A') + 8] = 'e';
			else
				$board[(ord('8') - ord(cchar($moves, $z1 + 4))) * 8 + ord(cchar($moves, $z1 + 3)) - ord('A') - 8] = 'e';
		}

		$z1 += 7;
	}
	$nextmove = $z1;

	/* Now just display board */

	printf("<FONT size=\"+1\">");
	printf(RESTITLE, $GNICK1, $GNICK2);
	printf("</FONT><br>");

	$i1 = ($gnummoves % 2); /* color of NICK1 */
	$i2 = ($number % 2);	/* actual color */

	if ($i2 == 1) {
		if ($i1 == $i2)
			printf(RESTURN, $GNICK1, TBLACK);
		else
			printf(RESTURN, $GNICK2, TBLACK);
	}	else {
		if ($i1 == $i2)
			printf(RESTURN, $GNICK1, TWHITE);
		else
			printf(RESTURN, $GNICK2, TWHITE);
	}

	printf("</FONT><br>");

	printf("<table border=0>\n");
	printf("<tr><td bgcolor=\"%s\">\n", BOARDGRID);
	printf("<table border=0 cellspacing=2>\n");
	printf("<tr><td bgcolor=\"%s\">&nbsp;</td>", BOARDMARGBG);
	if ($swap == 1) $i1 = 0; else $i1 = 7;
	for (;; $i1 += $swap) {
		if ($swap == 1 && $i1 > 7) break;
		if ($swap != 1 && $i1 < 0) break;

		printf("<td align=\"center\" bgcolor=\"%s\"><font color=\"%s\">%s</font></td>", BOARDMARGBG, BOARDMARGFG, chr(ord('A') + $i1));
	}
	printf("<td bgcolor=\"%s\" align=center valign=center><a href=\"%s?ID=%s&ACTION=VIEW&SWAP=%d&NUM=%d\">", BOARDMARGBG, $_SERVER['SCRIPT_NAME'], $ID, $swap * (-1), $number);
	if ($swap == 1)
		printf("<img src=\"%s\" alt=\"%s\" border=0 title=\"%s\">", SWAP_ICON0, SWAP_TEXT0, SWAP_TEXT0_LONG);
	else
		printf("<img src=\"%s\" alt=\"%s\" border=0 title=\"%s\">", SWAP_ICON1, SWAP_TEXT1, SWAP_TEXT1_LONG);
	printf("</a></td></tr>");

	if ($swap == 1) $i1 = 0; else $i1 = 7;
	for (;; $i1 += $swap) {
		if ($swap == 1 && $i1 > 7) break;
		if ($swap != 1 && $i1 < 0) break;
		printf("<tr height=\"%d\">\n", FIELDSIZE);
		printf("<td align=\"center\" bgcolor=\"%s\">&nbsp;<font color=\"%s\">%d</font>&nbsp;</td>\n", BOARDMARGBG, BOARDMARGFG, 8 - $i1);
		if ($swap == 1) $i2 = 0; else $i2 = 7;
		for (;; $i2 += $swap) {
			if ($swap == 1 && $i2 > 7) break;
			if ($swap != 1 && $i2 < 0) break;
			$i3 = $board[$i1 * 8 + $i2];
			if (($i1 + $i2) % 2) {
				$color = BOARDBLACK;
			} else {
				$color = BOARDWHITE;
			}
			printf("<td valign=\"center\" align=\"center\" width=\"%d\" height=\"%d\" bgcolor=\"%s\">", FIELDSIZE, FIELDSIZE, $color);
			list($z2, $piece, $z1, $z3, $z4) = piecepointer($i3);
			if (myisupper($i3))
				$shpi = strtoupper($z2);
			else
				$shpi = strtolower($z2);
			if (($z1 === null) || ($piece === null) || ($color === null)) {
				printf("&nbsp; &nbsp;\n");
			} else {
				if ((cchar($moves, $nextmove + 1) === chr(ord('A') + $i2) && cchar($moves, $nextmove + 2) === chr(ord('8') - $i1)) || (cchar($moves, $nextmove + 3) === chr(ord('A') + $i2) && cchar($moves, $nextmove + 4) === chr(ord('8') - $i1))) {
					printf("<table border=0 bgcolor=\"%s\" cellspacing=3 cellpadding=0><tr><td bgcolor=\"%s\">\n", SELECTEDCOLOR, $color);
					printf("<img border=0 src=\"%s\" alt=\"%s\" title=\"%s\">", $z1, $shpi, $piece);
					printf("</td></tr></table>\n");
				} else {
					printf("<img border=0 src=\"%s\" alt=\"%s\" title=\"%s\">", $z1, $shpi, $piece);
				}
			}
		}
			printf("<td align=\"center\" bgcolor=\"%s\">&nbsp;<font color=\"%s\">%d</font>&nbsp;</td>\n", BOARDMARGBG, BOARDMARGFG, 8 - $i1);

			printf("</tr>\n");
		}
			printf("<tr><td bgcolor=\"%s\">&nbsp;</td>", BOARDMARGBG);
			if ($swap == 1) $i1 = 0; else $i1 = 7;
			for (;; $i1 += $swap) {
				if ($swap == 1 && $i1 > 7) break;
				if ($swap != 1 && $i1 < 0) break;
				printf("<td align=\"center\" bgcolor=\"%s\"><font color=\"%s\">%s</font></td>", BOARDMARGBG, BOARDMARGFG, chr(ord('A') + $i1));
			}
			printf("<td bgcolor=\"%s\">&nbsp;</td></tr>", BOARDMARGBG);
			printf("</table>\n");
			printf("</td>");
			printf("</tr></table>\n");

			printf("<table border=0><tr><td align=\"center\" valign=\"center\">");
			printf("<FORM action=\"%s\" method=post>\n", $_SERVER['SCRIPT_NAME']);
			printf("<INPUT type=hidden name=ACTION value=VIEW>\n");
			printf("<INPUT type=hidden name=ID value=%s>\n", $ID);
			printf("<INPUT type=hidden name=SWAP value=%d\n", $swap);
			if ($number > 0) {
				printf("<input type=image align=center src=\"%s\" alt=\"%s\" border=0 name=NUM%d >\n", ARROW_LL, ARROW_T_LL, 0);
				printf("<input type=image align=center src=\"%s\" alt=\"%s\" border=0 name=NUM%d >\n", ARROW_L, ARROW_T_L, $number - 1);
			}
			printf("&nbsp;<font size=\"+1\">%d</font>&nbsp;\n", $number + 1);
			if ($number < $gnummoves) {
				printf("<input type=image align=center src=\"%s\" alt=\"%s\" border=0 name=NUM%d >\n", ARROW_R, ARROW_T_R, $number + 1);
				printf("<input type=image align=center src=\"%s\" alt=\"%s\" border=0 name=NUM%d >\n", ARROW_RR, ARROW_T_RR, $gnummoves);
			}
			printf("</FORM>");
			printf("</td></tr></table>\n");
}

/* MAIN */
$GPLAYER1 = '';
$GPLAYER2 = '';
$GNICK1 = '';
$GNICK2 = '';
$GBOARD = '';
$GPASS = '';
$gmoves = null;
$gnummoves = 0;

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {	/* Method: Post */
	$query = file_get_contents('php://input');
	if ($query === false || $query === null)
		$query = '';
	if (PHP_SAPI === 'cli' && $query === '')	/* php://input is only filled by the web-SAPI */
		$query = (string)stream_get_contents(STDIN);
} else {	/* Method: Get */
	$query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null;
}

if ($query === null) {
	echo DEFTITLE."\n";
	echo DEFURL."\n";
	echo "\n";
	echo CALLITFROMBROWSER."\n";
	echo "\n";
	exit(-1);
}

query2String($query);

page_header();

if ($query === '') start();
else {
	if (strpos($query, "ACTION=NEW") !== false) {
		newgame();
	} else {
		if (strpos($query, "ACTION=RESUME") !== false) {
			resumegame(0);
		} else {
			if (strpos($query, "ACTION=MOVED") !== false) {
				piecemoved();
			} else {
				if (strpos($query, "ACTION=CHMAIL") !== false) {
					changemail();
				} else {
					if (strpos($query, "ACTION=JOIN") !== false) {
						joingame();
					} else {
						if (strpos($query, "ACTION=SENDGAMES") !== false) {
							sendgames();
						} else {
							if (strpos($query, "ACTION=VIEW") !== false) {
								view();
							} else {
								if (defined('ADMIN')) {
									if (strpos($query, "ACTION=ADMIN") !== false) {
										admin();
									} else {
										if (defined('ENABLELIST')) {
											if (strpos($query, "ACTION=LIST") !== false) {
												listgames(1);
											} else {
												start();
											}
										} else {
											start();
										}
									}
								} else {
									if (defined('ENABLELIST')) {
										if (strpos($query, "ACTION=LIST") !== false) {
											listgames(1);
										} else {
											start();
										}
									} else {
										start();
									}
								}
							}
						}
					}
				}
			}
		}
	}
}

page_footer();
