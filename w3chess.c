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
*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
/* the next line is for glibc2.2 */
#include <sys/time.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>
#include <ctype.h>
#include <dirent.h>
#include <errno.h>

/* Crypto */
#define _XOPEN_SOURCE


#include "config.h"
#include "defaults.h"
#include "lang.h"

/* some global variables and fields */
/* All field +1 to determine parsing errors ! */
char GPLAYER1[MAXSTRING+2];
char GPLAYER2[MAXSTRING+2];
char GNICK1[NICKLENGTH+2+10+3];	/* Nickname, Pass, wantmail, two : */
char GNICK2[NICKLENGTH+2+10+3];
char GBOARD[65];
char GPASS[12];
char GPASS2[12];
char *gmessage;
char *gmoves;
int gnummoves;
int gnomail1;
int gnomail2;
int globalpass;

char *query;


extern int canmove(int fromx, int fromy, int x, int y);
extern int isMate(void);


/*====*/

/* MYISUPPER */
int myisupper(int c) {
	if(isupper(c) == 0)
		return(0);
	else
		return(1);
}

/* MYISLOWER */
int myislower(int c) {
  if(islower(c) == 0)
    return(0);
  else
    return(1);
}


/* HEXDEC */
int hexDec(char *hex) {
  int ret;
  int pos;

  ret=0;
  pos=0;
  while(pos < 2) {
    ret*=16;
    switch(*hex) {
      case '0':
      break;
      case '1':
        ret+=1;
      break;
      case '2':
        ret+=2;
      break;
      case '3':
        ret+=3;
      break;
      case '4':
        ret+=4;
      break;
      case '5':
        ret+=5;
      break;
      case '6':
        ret+=6;
      break;
      case '7':
        ret+=7;
      break;
      case '8':
        ret+=8;
      break;
      case '9':
        ret+=9;
      break;
      case 'A':
      case 'a':
        ret+=10;
      break;
      case 'B':
      case 'b':
        ret+=11;
      break;
      case 'C':
      case 'c':
        ret+=12;
      break;
      case 'D':
      case 'd':
        ret+=13;
      break;
      case 'E':
      case 'e':
        ret+=14;
      break;
      case 'F':
      case 'f':
        ret+=15;
      break;
    }
    hex++;
    pos++;
  }
  return(ret);
}

/* ERRORWIN */
void errorwin(char *mesg, char *pass, char *id, int swap) {
	printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">",BOARDGRID,BOARDMARGBG,BOARDMARGFG);
	printf("<b>%s</b><br><br>\n",mesg);
	if((id != (char *)NULL) && (*id != (char)NULL)) {
		printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
		printf("<INPUT TYPE=hidden name=ACTION value=RESUME>\n");
		printf("<INPUT TYPE=hidden name=ID value=\"%s\">\n",id);
		if(swap != 0) 
			printf("<INPUT TYPE=hidden name=SWAP value=\"%i\">\n",swap);
		if((pass != (char *)NULL) && (*pass != (char)NULL))
			printf("<INPUT TYPE=hidden name=PASS value=\"%s\">\n",pass);	
		printf("<INPUT TYPE=image src=\"%s\" ALT=\"%s\" border=\"0\">",BUTTONYES,MSGOK);
		printf("</FORM>\n");
	} else {
		printf("<a href=\"%s\">",getenv("SCRIPT_NAME"));
		printf("<img src=\"%s\" ALT=\"%s\" border=\"0\">",BUTTONYES,MSGOK);
		printf("</a>\n");
	}
	printf("</font></td></tr></table></td></tr></table><br>\n");
}

/* BACK2BOARD */
void back2board(char *mesg, char *PASS, char *ID, int swap) {
	printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">",BOARDGRID,BOARDMARGBG,BOARDMARGFG);
	printf("<b>%s</b><br>\n",mesg);
	if((ID != (char *)NULL) && (*ID != (char)NULL)) {
		if((PASS != (char *)NULL) && (*PASS != (char)NULL)) {
			printf("<META http-equiv=\"refresh\" content=\"%i; URL=%s?ACTION=RESUME&ID=%s&PASS=%s&SWAP=%i\">\n",WAITTIME,getenv("SCRIPT_NAME"),ID,PASS,swap);
			printf("<br><a href=\"%s?ACTION=RESUME&ID=%s&PASS=%s&SWAP=%i\"><img src=\"%s\" alt\"%s\" border=\"0\"></a>",getenv("SCRIPT_NAME"),ID,PASS,swap,BUTTONYES,MSGOK);
		} else {
			printf("<META http-equiv=\"refresh\" content=\"%i; URL=%s?ACTION=RESUME&ID=%s&SWAP=%i\">\n",WAITTIME,getenv("SCRIPT_NAME"),ID,swap);
			printf("<br><a href=\"%s?ACTION=RESUME&ID=%s&SWAP=%i\"><img src=\"%s\" alt\"%s\" border=\"0\"></a>",getenv("SCRIPT_NAME"),ID,swap,BUTTONYES,MSGOK);
		}
	} else {
		printf("<META http-equiv=\"refresh\" content=\"%i; URL=%s\">\n",WAITTIME,getenv("SCRIPT_NAME"));
		printf("<br><a href=\"%s\"><img src=\"%s\" alt\"%s\" border=\"0\"></a>",getenv("SCRIPT_NAME"),BUTTONYES,MSGOK);
	}
	printf("<br>\n");
	printf("</font></td></tr></table></td></tr></table><br>\n");
}

/* CHECKMAIL */
int checkmail(char *mail) {
	char *dot;
	char *et;
	int i1;

	if(mail==(char *)NULL)	/* no NULL-pointer */
		return(-1);
	if(mail[0] == (char)NULL)	/* not empty */
		return(-2);
	et=strchr(mail,'@');
	if(et == (char *)NULL)	/* should include a @ */
		return(-3);
	dot=strrchr(mail,'.');	/* LAST . */
	if(dot == (char *)NULL)	/* should include a . before toplevel domainname */
		return(-4);
	if(et > dot)	/* et should be before dot */
		return(-5);
	if(et == mail)	/* no username given */
		return(-6);
	if((dot-et-1) < 2)	/* domainname must have at least 2 characters */
		return(-7);
	i1=strlen(dot)-1;
	if((i1 != 2) && (i1 != 3) && (i1 != 4))	/* topleveldomain must have two, three or four digits */
		return(-8);

	return(0);	/* ok, could be valid */

}

/* PIECEPOINTER */
int piecepointer(char piece,char **shortp, char **longp, char **image, char **small, char **stroken) {

	switch(piece) {
		case 'r':
      *shortp=TSHORTR;
      *longp=TLONGR;
      *image=BROOKE;
			*small=SBROOKE;
			*stroken=SSBROOKE;
		break;
		case 'p':
      *shortp=TSHORTP;
      *longp=TLONGP;
      *image=BPAWN;
			*small=SBPAWN;
			*stroken=SSBPAWN;
		break;
		case 'n':
      *shortp=TSHORTN;
      *longp=TLONGN;
      *image=BKNIGHT;
			*small=SBKNIGHT;
			*stroken=SSBKNIGHT;
		break;
		case 'k':
      *shortp=TSHORTK;
      *longp=TLONGK;
      *image=BKING;
			*small=SBKING;
			*stroken=SSBKING;
		break;
		case 'q':
      *shortp=TSHORTQ;
      *longp=TLONGQ;
      *image=BQUEEN;
			*small=SBQUEEN;
			*stroken=SSBQUEEN;
		break;
		case 'b':
      *shortp=TSHORTB;
      *longp=TLONGB;
      *image=BBISHOP;
			*small=SBBISHOP;
			*stroken=SSBBISHOP;
		break;
		case 'E':
		case 'e':
      *shortp=TSHORTE;
      *longp=TEMPTY;
      *image=EMPTY;
			*small="";
			*stroken="";
		break;
		case 'R':
      *shortp=TSHORTR;
      *longp=TLONGR;
      *image=WROOKE;
			*small=SWROOKE;
			*stroken=SSWROOKE;
		break;
		case 'P':
      *shortp=TSHORTP;
      *longp=TLONGP;
      *image=WPAWN;
			*small=SWPAWN;
			*stroken=SSWPAWN;
		break;
		case 'N':
      *shortp=TSHORTN;
      *longp=TLONGN;
      *image=WKNIGHT;
			*small=SWKNIGHT;
			*stroken=SSWKNIGHT;
		break;
		case 'K':
      *shortp=TSHORTK;
      *longp=TLONGK;
      *image=WKING;
			*small=SWKING;
			*stroken=SSWKING;
		break;
		case 'Q':
      *shortp=TSHORTQ;
      *longp=TLONGQ;
      *image=WQUEEN;
			*small=SWQUEEN;
			*stroken=SSWQUEEN;
		break;
		case 'B':
      *shortp=TSHORTB;
      *longp=TLONGB;
      *image=WBISHOP;
			*small=SWBISHOP;
			*stroken=SSWBISHOP;
		break;
		default:
			*shortp=NULL;
			*longp=NULL;
			*image=NULL;
			*small=NULL;
			*stroken=NULL;
		break;
	}

	if(piece == 'e')
		return(-1);
	return(myislower(piece));
}


/* QUERY2STRING */
void query2String(char *string) {
  char *z1,*z2;
	/* The string could only get shorter, so we can need no extra space allocated */
  z1=string;

  while(*z1 != (char)NULL) {
    while((*z1 != '%') && (*z1 != (char)NULL) && (*z1 != '+') && (*z1 != '<') && (*z1 != '>'))
      z1++;

    if(*z1 == '%') {
      *z1=hexDec(z1+1);
      z2=z1+3;
      while(*z2 != (char)NULL) {
        *(z2-2)=*z2;
        z2++;
      }
      *(z2-2)=(char)NULL;
    }

		if(*z1 == '+')
			*z1=' ';

		if(*z1 == '<')
			*z1='[';

		if(*z1 == '>')
			*z1=']';

  }
}


/* HEADER */
void header(void) {
	FILE *head;
	int i1;

	printf("Content-type: text/html\n\n");

#ifdef HTML_HEADER
	head=fopen(HTML_HEADER,"r");
	if(head != (FILE *)NULL) {
		i1=fgetc(head);
		while(i1 != EOF) {
			putchar(i1);
			i1=fgetc(head);
		}
		fclose(head);
	} else {
#endif
	printf("<HTML><HEAD>\n");
	printf("<LINK REL=\"icon\" HREF=\"%s/favicon.ico\" TYPE=\"image/ico\">\n",IMGURLPREFIX);
	printf("<TITLE>%s</TITLE>\n",DEFTITLE);
	printf("</HEAD><BODY bgcolor=#ffef89 text=#000000 vlink=#000000 alink=#000000 link=#000000>\n");
	printf("<center>\n");

#ifdef HTML_HEADER
	}
#endif

	printf("<!-- %s -->\n",DEFTITLE);

}

/* FOOTER */
void footer(void) {
	FILE *foot;
	int i1;

	printf("<!-- %s -->\n",DEFTITLE);

#ifdef HTML_FOOTER
	foot=fopen(HTML_FOOTER,"r");
	if(foot != (FILE *)NULL) {
		i1=fgetc(foot);
		while(i1 != EOF) {
			putchar(i1);
			i1=fgetc(foot);
		}
		fclose(foot);
	} else {
#endif
		printf("</center>\n");
    printf("<table border=0 width=100%c><tr><td align=left>\n",'%');
    printf("<a style=\"font-weight:bold;text-decoration:none;\" target=\"_blank\" href=\"%s\" title=\"%s\">&copy;</a>",DEFURL,DEFTITLE);
    printf("</td>\n");
		printf("<td align=right>\n");
#ifdef ADMIN
		printf("<a style=\"font-weight:bold;text-decoration:none;\" href=\"%s?ACTION=ADMIN\">&pi;</a></td></tr></table>\n",getenv("SCRIPT_NAME"));
#else
		printf("<a style=\"font-weight:bold;text-decoration:none;\" target=\"_blank\" href=\"http://german.imdb.com/Title?0113957\">&pi;</a></td></tr></table>\n");
#endif
		printf("</BODY></HTML>\n");
#ifdef HTML_FOOTER
	}
#endif

}

/* REWRITEDATA */
void rewriteData(char *ID,char *PASS,char *MSG,char *MAIL, int swap) {
	FILE *datei;
	char path[MAXSTRING+1];
	int i1;

	/* swap=1: SWAP Player */
	if(swap != 1)
		swap == 0;

	/* open datafile */
	snprintf(path,(size_t)MAXSTRING,"%s/%s",DATAPATH,ID);
	datei=fopen(path,"w");
	if(datei == (FILE *)NULL) {
		errorwin(ERROROPEN,PASS,ID,0);
		return;
	}
	
	i1=0;
	while(i1 < 2) {
		if(i1 == swap) {
			/* User 1 has own password */
			if(globalpass == 0) {
				fputs(GPASS,datei);
				fputc(':',datei);	
				if(gnomail1 == 1) {
					fputc('1',datei);
					fputc(':',datei);
				}
			}	
			fputs(GNICK1,datei);
			fputc('\n',datei);
			if(MAIL == (char *)NULL)
				fputs(GPLAYER1,datei);
			else
				fputs(MAIL,datei);
			fputc('\n',datei);
			/* End of Data for User 1 */
		}

		if(i1 != swap) {
			/* User 2 has own password */
			if(GPASS2[0] != (char)NULL) {
				fputs(GPASS2,datei);
				fputc(':',datei);
				if(gnomail2 == 1) {
					fputc('1',datei);
					fputc(':',datei);
				}
			} 

			fputs(GNICK2,datei);
			fputc('\n',datei);
			fputs(GPLAYER2,datei);
			fputc('\n',datei);
			/* End of Data for User 2 */
		}

		i1++;
	}

	if(PASS != (char *)NULL)
		fputs(PASS,datei);
	else
		fputs(GPASS,datei);		
	if(gmoves != (char *)NULL)
		fprintf(datei,"\n%i\n%s\n%s",gnummoves,gmoves,GBOARD);
	else
		fprintf(datei,"\n%i\n\n%s",gnummoves,GBOARD);
	if(MSG != (char *)NULL) {
		fputc('\n',datei);
		fputs(MSG,datei);
	} else {
		if(gmessage != (char *)NULL) {
			fputc('\n',datei);
			fputs(gmessage,datei);
		}
	}
	fclose(datei);
	chmod(path,(mode_t)S_IRUSR|S_IWUSR);
	/* re-initialise the variables */
	readgamedata(ID);
}

void rewriteGame(char *ID,char *PASS,char *MSG) {
	rewriteData(ID,PASS,MSG,(char *)NULL,0); 
}

void rewriteEmail(char *ID, char *MAIL) {
	rewriteData(ID,(char *)NULL,(char *)NULL,MAIL,0); 
}

void rewriteGameAndSwap(char *ID,char *PASS,char *MSG) {
	rewriteData(ID,PASS,MSG,(char *)NULL,1);
}

/* GENPASS */
void genPass(char *PASS) {
	/* PASS must be an 11 digit character value */
	time_t Zeit;
	int i1;
	
	Zeit=time((time_t *)NULL);
	srand((unsigned int)Zeit+getpid());
	for(i1=0; i1<10; i1++) {
		do {
			PASS[i1]=30+(int) (92.0*rand()/(RAND_MAX+1.0));
		}
		while((! isalnum(PASS[i1])) || ( PASS[i1]==' ') || ( PASS[i1]=='[') || (PASS[i1] == ']') );
	}
	PASS[10]=(char)NULL;
}

/* MOVESOUT */
void movesout(FILE *wohin, char *moves, int nmoves, char *ID,int swap) {
	/* wohin = sonst: MAIL */
	/* wohin = stdout: HTML */

	char *z1,*z2,*z3,*z4;
	int i1;
	unsigned u1;
	char *sh,*lo,*im,*sm,*st;
	char *dn1,*dn2,*dn3,*dn4;	/* dev/null */

	if(nmoves == 0)
		return;

	if(wohin == stdout) {
		printf("<br><b><u>%s</u></b><br>\n",RESMOVES);
		printf("<table border=0>");
	} else {
		fprintf(wohin,"%s\n",RESMOVES);
	}

	z1=moves;

#ifdef REVERSEMOVELIST
	z1+=nmoves*7-7;
	for(i1=nmoves; i1>=1; i1--) {
#else
	for(i1=1;i1<=nmoves; i1++) {
#endif
		if(((gnummoves + i1) % 2) == 0)		/* gnummoves, cause NICK1 and NICK2 from old file is ment */
			z3=GNICK2;
		else
			z3=GNICK1;


		piecepointer(*z1,&dn1,&z2,&dn2,&sm,&dn4);  /* piece, shortname, longname, image, smallimage, strokenimage */

		if(*z1 != *(z1+5)) {
			piecepointer(*(z1+5),&sh,&z4,&dn1,&dn2,&dn3);  /* piece, shortname, longname, image, smallimage, strokenimage */
	} else {
		z4=(char *)NULL;	
	}

		if(wohin == stdout) {
			printf("<tr><td align=center><b>%i</b></td><td align=center><img align=center src=\"%s\" alt=\"%s\" border=0></td><td align=left><a href=\"%s?ACTION=VIEW&NUM=%i&ID=%s&SWAP=%i\" TARGET=ViewWin>%c%c &raquo; %c%c",i1,sm,z2,getenv("SCRIPT_NAME"),i1-1,ID,swap,*(z1+1),*(z1+2),*(z1+3),*(z1+4));

			if(*(z1+6) != 'e' || *(z1+5) == 'O') {  /* Hit piece the normal way or en passant */
				if(*(z1+5) == 'O' && *z1 == 'P') {
					piecepointer('p',&dn1,&lo,&dn2,&dn3,&st);  /* piece, shortname, longname, image, smallimage, strokenimage */
				} else {
					if(*(z1+5) == 'O' && *z1 == 'p') {
						piecepointer('P',&dn1,&lo,&dn2,&dn3,&st);  /* piece, shortname, longname, image, smallimage, strokenimage */
					} else {
						piecepointer(*(z1+6),&dn1,&lo,&dn2,&dn3,&st);  /* piece, shortname, longname, image, smallimage, strokenimage */
					}
				}
				printf("<img align=center src=\"%s\" alt=\"%s\" border=0>\n",st,lo);
			}
				piecepointer(*(z1+5),&dn1,&lo,&dn2,&sm,&dn3);  /* piece, shortname, longname, image, smallimage, strokenimage */
			if(*z1 != *(z1+5) && *(z1+5) != 'O')
				printf(" &rArr; <img align=center src=\"%s\" alt=\"%s\" border=0>\n",sm,z4);

			u1=*(z1+5);
			*(z1+5)=(char)NULL;
			if(strcmp(z1,"kE8G8") == 0)
				printf(" (%s)", SMCKS);
			if(strcmp(z1,"kE8C8") == 0)
				printf(" (%s)", SMCQS);
			if(strcmp(z1,"KE1G1") == 0)
				printf(" (%s)", SMCKS);
			if(strcmp(z1,"KE1C1") == 0)
				printf(" (%s)", SMCQS);
			*(z1+5)=u1;

			if(*(z1+5)=='O')
				printf(" (%s)", SMEP);

			printf("</td><td align=right>(%s)</td></tr>\n",z3);
		} else {
			fprintf(wohin,"%i) %s: %c%c -> %c%c",i1,z2,*(z1+1),*(z1+2),*(z1+3),*(z1+4));

      if(*(z1+6) != 'e' || *(z1+5) == 'O') {  /* Hit piece the normal way or en passant */
        if(*(z1+5) == 'O' && *z1 == 'P') {
          piecepointer('p',&dn1,&lo,&dn2,&dn3,&st);  /* piece, shortname, longname, image, smallimage, strokenimage */
        } else {
          if(*(z1+5) == 'O' && *z1 == 'p') {
            piecepointer('P',&dn1,&lo,&dn2,&dn3,&st);  /* piece, shortname, longname, image, smallimage, strokenimage */
          } else {
            piecepointer(*(z1+6),&dn1,&lo,&dn2,&dn3,&st);  /* piece, shortname, longname, image, smallimage, strokenimage */
          }
        }
				fprintf(wohin," [%s %s]",MAILHIT,lo);
			}

			if(*z1 != *(z1+5) && *(z1+5) != 'O')
				fprintf(wohin," -> %s",z4);

      u1=*(z1+5);
      *(z1+5)=(char)NULL;
      if(strcmp(z1,"kH5H7") == 0)
        fprintf(wohin," (%s)", SMCKS);
      if(strcmp(z1,"kH5H3") == 0)
        fprintf(wohin," (%s)", SMCQS);
      if(strcmp(z1,"KA5A7") == 0)
        fprintf(wohin," (%s)", SMCKS);
      if(strcmp(z1,"KA5A3") == 0)
        fprintf(wohin," (%s)", SMCQS); 
      *(z1+5)=u1;

			if(*(z1+5)=='O')
				fprintf(wohin," (%s)", SMEP);

			fprintf(wohin," (%s)\n",z3);
		}

#ifdef REVERSEMOVELIST
		z1-=7;
#else
		z1+=7;
#endif
	}


	if(wohin == stdout) {
		printf("</table>\n");
	}

}

/* ISNUMBER */
int isnumber(char *string) {
	if(*string == (char)NULL)
		return(0);
	if(strlen(string) < 8)
		return(0);
	if(*string == 'X')	/* Could be an game with missing partner */
		string++;
	while(*string != (char)NULL) {
		if(! isdigit(*string))
			return(0);
		string++;
	}
	return(1);
}

/* DELETEOLDGAMES */
void deleteoldgames(int html_out) {
	DIR *gamedir;
	struct dirent *direntry;
	struct stat buf;
	char path[MAXSTRING+1];
	struct timeval tv;
	unsigned days;
	unsigned gamect;
	unsigned opengct;

	gamect=0;
	opengct=0;

	path[0]=(char)NULL;

	gamedir=opendir(DATAPATH);
	if(gamedir == (DIR *)NULL) {
		errorwin(ERRORGAMEDIR,"","",0);
		return;
	}

	direntry=readdir(gamedir);
	while(direntry != (struct dirent *)NULL) {
		if(isnumber(direntry->d_name)) {
			strncpy(path,DATAPATH,(size_t)MAXSTRING);
			strncat(path,"/",(size_t)MAXSTRING);
			strncat(path,direntry->d_name,(size_t)MAXSTRING);
			if(stat(path,&buf) == 0) {
				if(gettimeofday(&tv,NULL) == 0) {
					days=(unsigned)(((unsigned long)tv.tv_sec-(unsigned long)buf.st_mtime) / (86400L));
#ifdef ALLOWREMOVE
					if(days >= DELETEAFTER) {
						unlink(path);
					} else {
#endif
						if(html_out == 1) {
							gamect++;
							if(direntry->d_name[0]=='X') {
								if(opengct == 0) {
									printf("<tr bgcolor=\"%s\"><td align=center>\n",BOARDMARGBG);
									printf("<font size=\"+1\" color=\"%s\"><b>%s</b></font>\n",BOARDMARGFG,STARTSEARCH);
									printf("</td></tr><tr><td align=\"center\">\n");

									printf("<table border=0>\n");
								}
								opengct++;
								readgamedata(direntry->d_name);
									printf("<tr><td align=center><b>%c%c.%c%c.%c%c%c%c</b> &nbsp; </td><td align=center><a href=\"%s?ID=%s&ACTION=JOIN\">",direntry->d_name[7],direntry->d_name[8],direntry->d_name[5],direntry->d_name[6],direntry->d_name[1],direntry->d_name[2],direntry->d_name[3],direntry->d_name[4],getenv("SCRIPT_NAME"),direntry->d_name);
								if(GNICK1[0] != (char)NULL)
									printf("%s</a></td>\n",GNICK1);
								else
									printf("%s</a></td>\n",GNICK2);
								if(gmessage != (char *)NULL)
									printf("<td align=left><font color=\"%s\"> &nbsp; %s</font></td>",MSGCOLOR,gmessage+1);
								else
									printf("<td> &nbsp; </td>");
								printf("</tr>\n");
							}
						}
#ifdef ALLOWREMOVE
					}
#endif
				} else {
					puts((char *)strerror(errno));
				}
			} else {
				puts((char *)strerror(errno));
			}
		}
		direntry=readdir(gamedir);
	}

	if(html_out == 1) {
		if(opengct > 0)
			printf("</table></td></tr>\n");

		printf("<tr bgcolor=\"%s\"><td align=center>\n",BOARDMARGBG);
		printf("<font size=\"-1\" color=\"%s\"><b>%u %s, %u %s</b></font>\n",BOARDMARGFG,gamect,STARTNUMBERGAMES,opengct,STARTNUMBEROGAMES);
		printf("</td></tr></table>\n");
	}

	closedir(gamedir);	
}

/* START */
void start(void) {
	FILE *page;
	int i1;
	
#ifdef HTML_DEFPAGE
	page=fopen(HTML_DEFPAGE,"r");
	if(page != (FILE *)NULL) {
		i1=fgetc(page);
		while(i1 != EOF) {
			putchar(i1);
			i1=fgetc(page);
		}
		fclose(page);
		deleteoldgames(0);
		} else {
#endif
  printf("<table border=0 ><tr bgcolor=\"%s\"><td align=center>\n",BOARDMARGBG);
  printf("<font size=\"+1\" color=\"%s\"><b>%s</b></font>\n",BOARDMARGFG,STARTANEWGAME);
  printf("</td></tr><tr><td align=\"center\">\n");

	printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
	printf("<INPUT TYPE=hidden name=ACTION value=NEW>\n");
	printf("<TABLE BORDER=0><TR>\n");
	printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=NICK1 SIZE=%i></TD>\n",STARTGAMENICK1,NICKLENGTH);
	printf("</TR><TR>\n");
	printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=MAIL1 SIZE=%i></TD>\n",STARTGAMEMAIL1,MAILLENGTH);
	printf("</TR><TR>\n");
	printf("<TD colspan=2><INPUT TYPE=RADIO name=WHITE value=1 CHECKED>%s</TD>\n",STARTHASWHITE);
	printf("</TR><TR>\n");
	printf("<TD colspan=2>%s</TD>\n",STARTGAMELEAVEBLANK);
	printf("</TR><TR>\n");
	printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=NICK2 SIZE=%i></TD>\n",STARTGAMENICK2,NICKLENGTH);
	printf("</TR><TR>\n");
	printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=MAIL2 SIZE=%i></TD>\n",STARTGAMEMAIL2,MAILLENGTH);
	printf("</TR><TR>\n");
	printf("<TD colspan=2><INPUT TYPE=RADIO name=WHITE value=2>%s</TD>\n",STARTHASWHITE);
	printf("</TR></TABLE><BR>\n");
	printf("%s: <INPUT TYPE=text NAME=MESSAGE size=\"%i\" value=\"\">\n",RESMESSAGE,MESSAGEBOXLEN);
	printf("<BR><BR><INPUT TYPE=submit value=\"%s\"><br>&nbsp;\n",STARTNEW);
	printf("</FORM>\n");

	printf("</td></tr>");
  printf("<tr bgcolor=\"%s\"><td align=center>\n",BOARDMARGBG);
  printf("<font size=\"+1\" color=\"%s\"><b>%s</b></font>\n",BOARDMARGFG,STARTRESUMEAGAME);
  printf("</td></tr><tr><td align=\"center\">\n");

	printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
	printf("<INPUT TYPE=hidden name=ACTION value=RESUME><br>\n");
	printf("%s: <INPUT TYPE=text NAME=ID><br><br>\n",STARTGAMEID);
	printf("<INPUT TYPE=submit value=\"%s\"><br>&nbsp;\n",STARTRESUME);
	printf("</FORM>\n");	

  printf("</td></tr>");
  printf("<tr bgcolor=\"%s\"><td align=center>\n",BOARDMARGBG);
  printf("<font size=\"+1\" color=\"%s\"><b>%s</b></font>\n",BOARDMARGFG,STARTSENDMYGAMES);
  printf("</td></tr><tr><td align=\"center\">\n");

  printf("<FORM action=\"%s\" method=get>\n",getenv("SCRIPT_NAME"));
	printf("<TABLE BORDER=0 cellpadding=0 cellspacing=0 margin=0><TR><TD align=right>");
  printf("<INPUT TYPE=hidden name=ACTION value=SENDGAMES><br>\n");
  printf("%s: <INPUT TYPE=text NAME=MAIL1><br>\n",STARTMYMAIL);
	printf("%s <INPUT TYPE=checkbox NAME=ONLYWEB><br><br>\n",STARTSENDNOMAIL);
	printf("</TD></TR></TABLE>");
  printf("<INPUT TYPE=submit value=\"%s\"><br>&nbsp;\n",STARTSEND);
  printf("</FORM>\n");

	printf("</td></tr>");
	
	deleteoldgames(1);
#ifdef HTML_DEFPAGE
	}
#endif

}

/* MAIL */
void mail(char *id, char *an, char *from, char *annick, char *fromnick, char *pass, char *moves, char *board, char *message, int nummov, int mate, char *against, int swap) { 
	char smail[MAXSTRING+1];
	FILE *pipe;
	int i1,i2;

	if((swap != 1) && (swap != -1)) swap=1;

#ifdef DEBUG
	printf("An: %s<br>From: %s<br>Pass: %s<br>Moves: %s<br>Board: %s<br>Msg: %s<br>\n",an,from,pass,moves,board,message);
#endif

#ifdef NOMAIL
  return;
#endif

	snprintf(smail,(size_t)MAXSTRING,"%s %s",SENDMAIL,an);
	pipe=popen(smail,"w");
	if(pipe == (FILE *)NULL) {
		errorwin(ERRORPIPE,"",id,0);
		return;
	}

#ifdef CHARSET
	fprintf(pipe,"Mime-Version: 1.0\n");
	fprintf(pipe,"Content-Type: text/plain; charset=%s\n",CHARSET);
	fprintf(pipe,"Content-Transfer-Encoding: 8bit\n");
#endif
	fprintf(pipe,"From: %s\n",from);
	fprintf(pipe,"To: %s\n",an);
	fprintf(pipe,"Subject: %s ",SUBJECT);
	fprintf(pipe,"%s %s (%s)",MAILAGAINST,against,id);
	fprintf(pipe,"\n\n");

	fprintf(pipe,"%s %s\n\n",MAILDEAR,annick);

	if(nummov == 0) 
		fprintf(pipe,"%s\n",MAILINVITE);

	fprintf(pipe,"%s: %s\n",MAILID,id);
	fprintf(pipe,"%s: http://%s%s\n\n",MAILBOARD,getenv("SERVER_NAME"),getenv("SCRIPT_NAME"));

	if(pass[0] != (char)NULL) {
		switch(mate) {
			case 1:
				fprintf(pipe,"%s, %s!\n",MATE,annick);
			break;
			case 2:
				fprintf(pipe,"%s, %s!\n",CHECK,annick);
			break;
			default:
			break;
		}
		if(mate == 3) {	/* Remis */
			fprintf(pipe,"%s\n",MAILREMIS);
		}
		if(mate != 1) {
			fprintf(pipe,"%s: %s\n\n",MAILMOVE,pass);
			fprintf(pipe,"%s\n",MAILURLRESUME);
			fprintf(pipe,"http://%s%s?ACTION=RESUME&PASS=%s&ID=%s\n",getenv("SERVER_NAME"),getenv("SCRIPT_NAME"),pass,id);
		} else {
			fprintf(pipe,"%s\n",MAILURLVIEW);
			fprintf(pipe,"http://%s%s?ACTION=RESUME&ID=%s\n",getenv("SERVER_NAME"),getenv("SCRIPT_NAME"),id);
		}
		if(message[0] != (char)NULL && message != (char *)NULL) {
			fprintf(pipe,"\n%s:\n%s\n",RESMESSAGE,message);
		}
	} else {
		if(nummov > 0)
				fprintf(pipe,"%s\n",MAILDONE);
		switch(mate) {
			case 1:
				fprintf(pipe,"%s\n",MAILWINNER);
			break;
			case 2:
				fprintf(pipe,"%s\n",MAILOPCHECK);
			break;
			case 3:
				fprintf(pipe,"%s\n",MAILREMIS);
			break;
			case 4:
				fprintf(pipe,"%s\n",MAILISREMIS);
			break;
			case 5:
				fprintf(pipe,MAILGIVESUP,fromnick);
				fputc('\n',pipe);
			break;
			default:
			break;
		}
		fprintf(pipe,"%s\n",MAILURLVIEW);
		fprintf(pipe,"http://%s%s?ACTION=RESUME&ID=%s\n",getenv("SERVER_NAME"),getenv("SCRIPT_NAME"),id);
	}

	if(swap == 1)
		fprintf(pipe,"\n   # A | B | C | D | E | F | G | H #\n");
	else
		fprintf(pipe,"\n   # H | G | F | E | D | C | B | A #\n");
	fprintf(pipe,"###+###+###+###+###+###+###+###+###+###\n");

	if(swap == -1)
		board+=63;

	if(swap==1) i1=0; else i1=7;
	for(;;i1+=swap) {
		if(swap==1 && i1>7) break;
		if(swap!=1 && i1<0) break;
		if(((i1 > 0) &&  (swap == 1)) || ((swap == -1) && (i1 < 7)))
			fprintf(pipe,"---#---+---+---+---+---+---+---+---#---\n");
		fprintf(pipe," %i #",8-i1);
		if(swap==1) i2=0; else i2=7;
		for(;;i2+=swap) {
			if(swap==1 && i2>7) break;
			if(swap!=1 && i2<0) break;
			if(*board == 'e') 
				fprintf(pipe,"   ");
			else 
				fprintf(pipe," %c ",*board);
			board+=swap;

			if((swap == -1 && i2 > 0) || (swap == 1 && i2 < 7))
				fprintf(pipe,"|");
			else
				fprintf(pipe,"#");
		}	
		fprintf(pipe," %i\n",8-i1);
	}
	fprintf(pipe,"###+###+###+###+###+###+###+###+###+###\n");
	if(swap == 1)
		fprintf(pipe,"   # A | B | C | D | E | F | G | H #\n");
	else
		fprintf(pipe,"   # H | G | F | E | D | C | B | A #\n");

	if(swap == -1)
		fprintf(pipe,"(%s)\n\n",MAILYOUBLACK);
	else
		fprintf(pipe,"(%s)\n\n",MAILYOUWHITE);


	movesout(pipe, moves, nummov, "", 0);

	if(nummov > 0)
		fprintf(pipe,"\n\n[MOVES:%s]\n[BOARD:%s]\n",gmoves,GBOARD);

	pclose(pipe);
}

/* GETPIECEFROMBOARD */
int getPieceFromBoard(int x, int y) {
	if(y*8+x < 0)
		return(' ');
	if(y*8+x > 63)
		return(' ');
	if((y < 0) || (y > 7) || (x < 0) || (x > 7))
		return(' ');
	return(GBOARD[y*8+x]);
}

/* KINGISATTACKED */
int kingIsAttacked(int x, int y, int king) {
	int i1;
	int p;
	char flags[8];
	char c;

	flags[0]=0;
	flags[1]=0;
	flags[2]=0;
	flags[3]=0;
	flags[4]=0;
	flags[5]=0;
	flags[6]=0;
	flags[7]=0;

	/* Bishops and Queens could attack if they are from other color and if *no* other piece in the way */

	for(i1=1;i1<=7;i1++) {
			p=getPieceFromBoard(x+i1,y+i1);		/* topleft to bottomright */
			if((myisupper(king) == myisupper(p)) && (p != 'e')) flags[0]=1;		/* is an own piece in the way, don't test the type */
			if((flags[0] == 0) && ((toupper(p) == 'B') || (toupper(p) == 'Q'))) return(1);		/* Bishop or Queen attacks */
			if(p != 'e') flags[0]=1;	/* is *some* piece in the way then never test again, king is save */

			p=getPieceFromBoard(x-i1,y+i1);		/* topright to bottomleft */
			if((myisupper(king) == myisupper(p)) && (p != 'e')) flags[1]=1;
			if((flags[1] == 0) && ((toupper(p) == 'B') || (toupper(p) == 'Q'))) return(1);    /* Bishop or Queen attacks */
			if(p != 'e') flags[1]=1;

			p=getPieceFromBoard(x+i1,y-i1);		/* bottomleft to topright */
			if((myisupper(king) == myisupper(p)) && (p != 'e')) flags[2]=1;
			if((flags[2] == 0) && ((toupper(p) == 'B') || (toupper(p) == 'Q'))) return(1);    /* Bishop or Queen attacks */
			if(p != 'e') flags[2]=1;

			p=getPieceFromBoard(x-i1,y-i1);		/* bottomright to topleft */
			if((myisupper(king) == myisupper(p)) && (p != 'e')) flags[3]=1;
			if((flags[3] == 0) && ((toupper(p) == 'B') || (toupper(p) == 'Q'))) return(1);    /* Bishop or Queen attacks */
			if(p != 'e') flags[3]=1;

			p=getPieceFromBoard(x,y+i1);			/* top to bottom */
			if((myisupper(king) == myisupper(p)) && (p != 'e')) flags[4]=1;
			if((flags[4] == 0) && ((toupper(p) == 'R') || (toupper(p) == 'Q'))) return(1);    /* Rook or Queen attacks */
			if(p != 'e') flags[4]=1;

			p=getPieceFromBoard(x,y-i1);			/* bottom to top */
			if((myisupper(king) == myisupper(p)) && (p != 'e')) flags[5]=1;
			if((flags[5] == 0) && ((toupper(p) == 'R') || (toupper(p) == 'Q'))) return(1);    /* Rook or Queen attacks */
			if(p != 'e') flags[5]=1;

			p=getPieceFromBoard(x+i1,y);			/* left to right */
			if((myisupper(king) == myisupper(p)) && (p != 'e')) flags[6]=1;
			if((flags[6] == 0) && ((toupper(p) == 'R') || (toupper(p) == 'Q'))) return(1);    /* Rook or Queen attacks */
			if(p != 'e') flags[6]=1;

			p=getPieceFromBoard(x-i1,y);			/* right to left */
			if((myisupper(king) == myisupper(p)) && (p != 'e')) flags[7]=1;
			if((flags[7] == 0) && ((toupper(p) == 'R') || (toupper(p) == 'Q'))) return(1);    /* Rook or Queen attacks */
			if(p != 'e') flags[7]=1;
	}

	/* The Pawn could attack */
	if(king == 'K') {		/* The white king */
		p=getPieceFromBoard(x-1,y-1);
		if(p == 'p') return(1);
		p=getPieceFromBoard(x+1,y-1);
		if(p == 'p') return(1);
	} else {						/* The black king */
		p=getPieceFromBoard(x-1,y+1);
		if(p == 'P') return(1);
		p=getPieceFromBoard(x+1,y+1);
		if(p == 'P') return(1);
	}

	/* The knight could attack him ! */
	p=getPieceFromBoard(x-2,y-1);
	if((myisupper(p) != myisupper(king)) && (toupper(p) == 'N')) return(1);
	p=getPieceFromBoard(x+2,y-1);
	if((myisupper(p) != myisupper(king)) && (toupper(p) == 'N')) return(1);
	p=getPieceFromBoard(x-1,y-2);
	if((myisupper(p) != myisupper(king)) && (toupper(p) == 'N')) return(1);
	p=getPieceFromBoard(x+1,y-2);
	if((myisupper(p) != myisupper(king)) && (toupper(p) == 'N')) return(1);
	p=getPieceFromBoard(x-2,y+1);
	if((myisupper(p) != myisupper(king)) && (toupper(p) == 'N')) return(1);
	p=getPieceFromBoard(x+2,y+1);
	if((myisupper(p) != myisupper(king)) && (toupper(p) == 'N')) return(1);
	p=getPieceFromBoard(x-1,y+2);
	if((myisupper(p) != myisupper(king)) && (toupper(p) == 'N')) return(1);
	p=getPieceFromBoard(x+1,y+2);
	if((myisupper(p) != myisupper(king)) && (toupper(p) == 'N')) return(1);

	/* The other king could attack him ! */
	/* There only be one king, so its enough to check for "one" other king 
     on a neighbourfiled */

	if(king == 'k')
		c='K';
	else
		c='k';

	if(getPieceFromBoard(x+1,y) == c) return(1);
	if(getPieceFromBoard(x+1,y+1) == c) return(1);
	if(getPieceFromBoard(x+1,y-1) == c) return(1);
	if(getPieceFromBoard(x,y+1) == c) return(1);
	if(getPieceFromBoard(x,y-1) == c) return(1);
	if(getPieceFromBoard(x-1,y) == c) return(1);
	if(getPieceFromBoard(x-1,y+1) == c) return(1);
	if(getPieceFromBoard(x-1,y-1) == c) return(1);

	return(0);

}

/* INTERNCM */
int interncm(int fromx, int fromy, int x, int y) {
	if((fromx > 7) || (fromx < 0) || (x > 7) || (x < 0) || (fromy > 7) || (fromy < 0) || (y > 7) || (y < 0)) return(0);
	return(canmove('A'+fromx,8-fromy,x,y));
}

/* PIECEFREE */
int pieceFree(int piece,int x,int y) {
	int p;
	char *z1;
	int i1;
	
	switch(tolower(piece)) {
		case 'q':		/* Queen */
			for(i1=1; i1<=7; i1++) {
				if(interncm(x,y,x-i1,y) == 1) return(1);		/* left */
				if(interncm(x,y,x+i1,y) == 1) return(1);		/* right */
				if(interncm(x,y,x,y-i1) == 1) return(1);		/* top */
				if(interncm(x,y,x,y+i1) == 1) return(1);		/* bottom */
				if(interncm(x,y,x-i1,y+i1) == 1) return(1);		/* bottom-left */
				if(interncm(x,y,x+i1,y+i1) == 1) return(1);		/* bottom-right */
				if(interncm(x,y,x-i1,y-i1) == 1) return(1);		/* top-left */
				if(interncm(x,y,x+i1,y-i1) == 1) return(1);		/* top-right */
			}
		break;
		case 'b':	/* Bishop */
			for(i1=1; i1<=7; i1++) {
				if(interncm(x,y,x-i1,y+i1) == 1) return(1);   /* bottom-left */
				if(interncm(x,y,x+i1,y+i1) == 1) return(1);   /* bottom-right */
				if(interncm(x,y,x-i1,y-i1) == 1) return(1);   /* top-left */
				if(interncm(x,y,x+i1,y-i1) == 1) return(1);   /* top-right */
			}
		break;
		case 'r':	/* Rook */
			for(i1=1; i1<=7; i1++) {
				if(interncm(x,y,x-i1,y) == 1) return(1);    /* left */
				if(interncm(x,y,x+i1,y) == 1) return(1);    /* right */
				if(interncm(x,y,x,y-i1) == 1) return(1);    /* top */
				if(interncm(x,y,x,y+i1) == 1) return(1);    /* bottom */
			}
		break;
		case 'n':	/* Knight */
			if(interncm(x,y,x-1,y-2) == 1) return(1);
			if(interncm(x,y,x+1,y-2) == 1) return(1);
			if(interncm(x,y,x-1,y+2) == 1) return(1);
			if(interncm(x,y,x+1,y+2) == 1) return(1);
			if(interncm(x,y,x+2,y-1) == 1) return(1);
			if(interncm(x,y,x-2,y-1) == 1) return(1);
			if(interncm(x,y,x-2,y+1) == 1) return(1);
			if(interncm(x,y,x+2,y+1) == 1) return(1);
		break;
		case 'p':	/* Pawn */
			if(interncm(x,y,x-1,y-1) == 1) return(1);
			if(interncm(x,y,x,y-1) == 1) return(1);
			if(interncm(x,y,x,y-2) == 1) return(1);
			if(interncm(x,y,x+1,y-1) == 1) return(1);

			if(interncm(x,y,x-1,y+1) == 1) return(1);
			if(interncm(x,y,x,y+1) == 1) return(1);
			if(interncm(x,y,x,y+2) == 1) return(1);
			if(interncm(x,y,x+1,y+1) == 1) return(1);
		break;
		case 'k':
			if(interncm(x,y,x-1,y) == 1) return(1);
			if(interncm(x,y,x-1,y-1) == 1) return(1);
			if(interncm(x,y,x-1,y+1) == 1) return(1);
			if(interncm(x,y,x,y-1) == 1) return(1);
			if(interncm(x,y,x,y+1) == 1) return(1);
			if(interncm(x,y,x+1,y) == 1) return(1);
			if(interncm(x,y,x+1,y-1) == 1) return(1);
			if(interncm(x,y,x+1,y+1) == 1) return(1);
			if(interncm(x,y,x-2,y) == 1) return(1);
			if(interncm(x,y,x+2,y) == 1) return(1);
		break;

		default:	/* Should never happen... */
			return(1);
		break;
	}



	return(0);
}

/* WAYFREE */
int wayFree(int fromx, int fromy, int tox, int toy,int xsgn, int ysgn) {
	int i1;
	int x,y;

	for(x=fromx+xsgn,y=fromy+ysgn; (x != tox) || (y != toy); x+=xsgn,y+=ysgn) {		/* Dangerous ! If the programm faults, this leads into an infinity loop ! */
		if(getPieceFromBoard(x,y) != 'e')
			return(0);
	}	

	return(1);	/* No piece found in the way.... */

}


/* CHECKMOVE */
int checkMove(int fromx, int fromy, int tox, int toy) {
	int xd,yd;

	/* returned directions: have a look at you numpad... */
	/* zero means no direction */

	xd=tox-fromx;
	yd=fromy-toy;

	if((abs(xd) != abs(yd)) && (xd*yd != 0))	/* no direct move */
		return(0);

	if(xd < 0) {
		if(yd < 0) {
			return(1*wayFree(fromx,fromy,tox,toy,-1,1));
		}
		if(yd > 0) {
			return(7*wayFree(fromx,fromy,tox,toy,-1,-1));
		}
		if(yd == 0) {
			return(4*wayFree(fromx,fromy,tox,toy,-1,0));
		}
	}
  if(xd > 0) {
    if(yd < 0) {
      return(3*wayFree(fromx,fromy,tox,toy,1,1));
    }
    if(yd > 0) {
      return(9*wayFree(fromx,fromy,tox,toy,1,-1));
    }
    if(yd == 0) {
      return(6*wayFree(fromx,fromy,tox,toy,1,0));
    }
  } 
  if(xd == 0) {
    if(yd < 0) {
      return(2*wayFree(fromx,fromy,tox,toy,0,1));
    }
    if(yd > 0) {
      return(8*wayFree(fromx,fromy,tox,toy,0,-1));
    }
    if(yd == 0) {
      return(0);
    } 
  } 
	return(0);
}

/* WHEREISMYKING */
int whereIsMyKing(int color) {
	/*color=0: white, color=1: black */
	char *z1;
	if(color == 0)
		z1=strchr(GBOARD,'K');
	else
		z1=strchr(GBOARD,'k');
	if(z1 == (char *)NULL)
		return(-1);		/* The King is dead.... */
	else
		return(z1-GBOARD);
}

/* CANMOVE */
int canmove(int fromx, int fromy, int x, int y) {

	int moveto;
	int aktfarbe;
	int piece, fpiece;
	int direction;
	int dx;
	int dy;
	char ENP[8];
	char *z1;
	int i1;
	char TBOARD[65];

#ifdef ALLCANMOVE
	return(1);	/* every move is allowed */
#endif	
	
	/* pieces out of board ? */
	if((fromx > 'H') || (x > 'H') || (fromy > 8) || (y > 8)) return(0);

	/*================================*/
	/*=== Collect some information ===*/
	/*================================*/

	/* First or second step of a move ? */
	if((fromx != 0) && (fromy != 0))
		moveto=1;
	else
		moveto=0;

	/* Correct fromx and fromy, should be from 0 to 7, beginning at top left */
	if(fromy > 0)
		fromy=8-fromy;
	if(fromx > 0)
		fromx=fromx-'A';

	/* color which has to move next, 0=white, 1=black */
	aktfarbe=gnummoves % 2;

	/* which piece is at (x,y) */
	piece=getPieceFromBoard(x,y);
	/* which piece to set */
	fpiece=getPieceFromBoard(fromx,fromy);

	/*====================*/
	/*=== Global Rules ===*/
	/*====================*/

	/* You should not move empty fields to empty fields! */
	if(fpiece == 'e' && piece == 'e') return(0);

	/* You should only move pieces to fields or pieces */
	if(piece == ' ' || fpiece == ' ') return(0);

	/* You should only move with one of your pieces */
	if((moveto == 0) && ((aktfarbe == myisupper(piece)) || (piece == 'e'))) return(0);	

	/* You should only move TO a field where`s none of your pieces */
	if((moveto == 1) && (aktfarbe != myisupper(piece) && (piece != 'e'))) return(0);

	/* You shouldn't be in chess *after* moving */
	if(moveto == 1) {
		strncpy(TBOARD,GBOARD,(size_t)65);
		GBOARD[y*8+x]=GBOARD[fromy*8+fromx];
		GBOARD[fromy*8+fromx]='e';
		i1=whereIsMyKing(aktfarbe);
		if(aktfarbe == 0) {
			if(kingIsAttacked(i1%8,i1/8,'K') == 1) {
				strncpy(GBOARD,TBOARD,(size_t)65);
				return(0);
			}
		} else {
			if(kingIsAttacked(i1%8,i1/8,'k') == 1) {
				strncpy(GBOARD,TBOARD,(size_t)65);
				return(0);
			}
		}
		strncpy(GBOARD,TBOARD,(size_t)65);	
	}

	/* The piece has to be "free" when moving it */
	if((moveto == 0) && (pieceFree(piece,x,y) == 0)) return(0);

	/* Check if the move ist possible:
	   - no other piece is in the way
		 - the way is valid
		 - the to-position *is* an enemy or empty, see above */
	if(moveto == 1) {

		dx=abs(fromx-x);
		dy=abs(fromy-y);

		/* The Knight could only move in his ugly way, he can jump over other pieces */
		if(tolower(fpiece) == 'n') {
			if(dx+dy != 3) return(0);
			if(dx*dy != 2) return(0);
		}

		/* The Black Pawn */
		if(fpiece == 'p') {
			/* normal move */
			if((fromx == x) && (fromy == (y-1)) && (getPieceFromBoard(x,y) == 'e')) return(1);	
			/* first move */
			if((fromx == x) && (fromy == (y-2)) && (fromy == 1) && (getPieceFromBoard(x,y-1) == 'e') && (getPieceFromBoard(x,y) == 'e')) return(1);
			/* hit */
			if((fromx == x-1) && (fromy == y-1) && (getPieceFromBoard(x,y) != 'e') && (myislower(getPieceFromBoard(x,y)) != 1)) return(1);
			if((fromx == x+1) && (fromy == y-1) && (getPieceFromBoard(x,y) != 'e') && (myislower(getPieceFromBoard(x,y)) != 1)) return(1);
			/* en Passant */
			if((y == 5) && (fromy == 4) && (fromx > 0) && (x == fromx-1)) {	/* left */
				snprintf(ENP,(size_t)8,"P%c2%c4Pe",fromx-1+'A',fromx-1+'A');
				z1=gmoves;
				z1+=strlen(gmoves)-7;
				if(strcmp(z1,ENP) == 0)
					return(1);
			}
			if((y == 5) && (fromy == 4) && (fromx < 7) && (x == fromx+1)) { /* right */
				snprintf(ENP,(size_t)8,"P%c2%c4Pe",fromx+1+'A',fromx+1+'A');
				z1=gmoves;
				z1+=strlen(gmoves)-7;
				if(strcmp(z1,ENP) == 0)
					return(1);
			}

			return(0);
		}

    /* The White Pawn */
    if(fpiece == 'P') { 
			/* normal move */
			if((fromx == x) && (fromy == (y+1)) && (getPieceFromBoard(x,y) == 'e')) return(1); 
			/* first move */
			if((fromx == x) && (fromy == (y+2)) && (fromy == 6) && (getPieceFromBoard(x,y+1) == 'e') && (getPieceFromBoard(x,y) == 'e')) return(1);
			/* hit */
			if((fromx == x-1) && (fromy == y+1) && (getPieceFromBoard(x,y) != 'e') && (myisupper(getPieceFromBoard(x,y)) != 1)) return(1);
			if((fromx == x+1) && (fromy == y+1) && (getPieceFromBoard(x,y) != 'e') && (myisupper(getPieceFromBoard(x,y)) != 1)) return(1);
      /* en Passant */
      if((y == 2) && (fromy == 3) && (fromx > 0) && (x == fromx-1)) { /* left */
        snprintf(ENP,(size_t)8,"p%c7%c5pe",fromx-1+'A',fromx-1+'A');
        z1=gmoves;
        z1+=strlen(gmoves)-7;
        if(strcmp(z1,ENP) == 0)
          return(1);
      }
      if((y == 2) && (fromy == 3) && (fromx < 7) && (x == fromx+1)) { /* right */
        snprintf(ENP,(size_t)8,"p%c7%c5pe",fromx+1+'A',fromx+1+'A');
        z1=gmoves;
        z1+=strlen(gmoves)-7;
        if(strcmp(z1,ENP) == 0)
          return(1);
      }
	
      return(0);
    }

		/* The King */
		if(tolower(fpiece) == 'k') {
			/* Dont set the king in check */
			/* Its wrong at this point if(kingIsAttacked(x,y,fpiece)) return(0);*/ 	/* I know, it is checked before..... */
			/* Castle xxx Side */
			if(fpiece == 'k') { /*	black king */
				if((fromx == 4) && (fromy == 0) && (x == 6) && (y == 0)) {	/* Castle King Side */
					if(strstr(gmoves,"kE8") != (char *)NULL) return(0);	/* The King has been moved */
					if(strstr(gmoves,"rH8") != (char *)NULL) return(0);  /* The Rook has been moved */
					if(getPieceFromBoard(5,0) != 'e') return(0);	/* all fields between king and rook have to be empty */
					if(getPieceFromBoard(6,0) != 'e') return(0);
					if(kingIsAttacked(4,0,fpiece)) return(0);		/* the king is not in check */
					if(kingIsAttacked(5,0,fpiece)) return(0);		/* no crossing field is in check */
					if(kingIsAttacked(6,0,fpiece)) return(0);
					return(1);
				}
				if((fromx == 4) && (fromy == 0) && (x == 2) && (y == 0)) {  /* Castle Queen Side */
					if(strstr(gmoves,"kE8") != (char *)NULL) return(0);  /* The King has been moved */
					if(strstr(gmoves,"rA8") != (char *)NULL) return(0);  /* The Rook has been moved */
					if(getPieceFromBoard(1,0) != 'e') return(0);  /* all fields between king and rook have to be empty */
					if(getPieceFromBoard(2,0) != 'e') return(0);
					if(getPieceFromBoard(3,0) != 'e') return(0);
					if(kingIsAttacked(4,0,fpiece)) return(0);		/* the king is not in check */
					if(kingIsAttacked(3,0,fpiece)) return(0);   /* no crossing field is in check */
					if(kingIsAttacked(2,0,fpiece)) return(0);
					return(1);
				}
			} else {	/* white king */
				if((fromx == 4) && (fromy == 7) && (x == 6) && (y == 7)) {  /* Castle King Side */
          if(strstr(gmoves,"KE1") != (char *)NULL) return(0);  /* The King has been moved */
          if(strstr(gmoves,"RH1") != (char *)NULL) return(0);  /* The Rook has been moved */
          if(getPieceFromBoard(5,7) != 'e') return(0);  /* all fields between king and rook have to be empty */
          if(getPieceFromBoard(6,7) != 'e') return(0);
          if(kingIsAttacked(4,7,fpiece)) return(0);   /* the king is not in check */
          if(kingIsAttacked(5,7,fpiece)) return(0);   /* no crossing field is in check */
          if(kingIsAttacked(6,7,fpiece)) return(0);
					return(1);
        }
        if((fromx == 4) && (fromy == 7) && (x == 2) && (y == 7)) {  /* Castle Queen Side */
          if(strstr(gmoves,"KE1") != (char *)NULL) return(0);  /* The King has been moved */
          if(strstr(gmoves,"RA1") != (char *)NULL) return(0);  /* The Rook has been moved */
          if(getPieceFromBoard(1,7) != 'e') return(0);  /* all fields between king and rook have to be empty */
          if(getPieceFromBoard(2,7) != 'e') return(0);
          if(getPieceFromBoard(3,7) != 'e') return(0);
          if(kingIsAttacked(4,7,fpiece)) return(0);   /* the king is not in check */
          if(kingIsAttacked(3,7,fpiece)) return(0);   /* no crossing field is in check */
          if(kingIsAttacked(2,7,fpiece)) return(0);
					return(1);
        }
			}


			/* The King should only move 1 field */
			if((dx > 1) || (dy > 1)) return(0);


			return(1);
		}


		direction=checkMove(fromx,fromy,x,y);

		/* The Queen can only move diagonal and orthogonal */
		if((tolower(fpiece) == 'q') && (direction == 0))	return(0);

		/* The Rook can only move orthogonal */
		if((tolower(fpiece) == 'r') && (((direction % 2) == 1) || (direction == 0)))  return(0);

		/* The Bishop can only move diagonal */
		if((tolower(fpiece) == 'b') && ((direction % 2) == 0))  return(0);	

	}

	/* If there`s no rule.... Ok, maybe you are allowed to do it ! */
	return(1);
} 

/* READGAMEDATA */
int readgamedata(char *game) {
	FILE *datei;
	char path[MAXSTRING+1];
	unsigned u1;
	char *z1, *z2;
	long merker;
	int ct,i1;
	int ismatt;

	ismatt=0;

	/* Default: Sent Mail to both players */
	gnomail1=0;
	gnomail2=0;

	/* Default: Players have no own Password */
	globalpass=1;
	GPASS2[0]=(char)NULL;

	snprintf(path,(size_t)MAXSTRING,"%s/%s",DATAPATH,game);
	datei=fopen(path,"r");
	if(datei == (FILE *)NULL) {
		errorwin(ERRORID,"","",0);
		return(-1);	
	}
	fgets(GNICK1,NICKLENGTH+2+10+3,datei);
	if(strlen(GNICK1) == NICKLENGTH+1+10+3) {
		GNICK1[NICKLENGTH+1+10+3]==(char)NULL;
		i1=fgetc(datei);
		while(i1 != 0xA)
			i1=fgetc(datei);
	}
	fgets(GPLAYER1,MAXSTRING,datei);
	fgets(GNICK2,NICKLENGTH+2+10+3,datei);
	if(strlen(GNICK2) == NICKLENGTH+1+10+3) {	
		GNICK2[NICKLENGTH+1+10+3]==(char)NULL;
		i1=fgetc(datei);
		while(i1 != 0xA)
			i1=fgetc(datei);
	}
	fgets(GPLAYER2,MAXSTRING,datei);
	fgets(GPASS,11,datei);

	if(strcmp(GPASS,"[MATE]\n") == 0) {
		ismatt=1;
	}

	z1=strchr(GNICK1,'\n'); if(z1 != (char *)NULL) *z1=(char)NULL;
	z1=strchr(GNICK2,'\n'); if(z1 != (char *)NULL) *z1=(char)NULL;
	z1=strchr(GPLAYER1,'\n'); if(z1 != (char *)NULL) *z1=(char)NULL;
	z1=strchr(GPLAYER2,'\n'); if(z1 != (char *)NULL) *z1=(char)NULL;
	z1=strchr(GPASS,'\n'); if(z1 != (char *)NULL) *z1=(char)NULL;

	/* Check for Userpassword if game is valid */

	if((strncmp(GPASS,"[REMIS]",(size_t)12) != 0) && (strncmp(GPASS,"[GIVEUP]",(size_t)12) != 0) && (strncmp(GPASS,"[MATE]",(size_t)12) != 0)) { 
		z1=strchr(GNICK1,':');
		if(z1 != (char *) NULL) {
			*z1=(char)NULL;
			if(strlen(GNICK1) > 0) {
				globalpass=0;
				if(ismatt == 0) 
					strncpy(GPASS,GNICK1,(size_t)10);
			}
			*z1=':';
			z1++;
			z2=strchr(z1,':');
			if(z2 != (char *) NULL) {
				if(*z1 == '1') gnomail1 = 1;
			}
		}

		z1=strchr(GNICK2,':');
		if(z1 != (char *) NULL) {
			*z1=(char)NULL;
			if(strlen(GNICK2) > 0)
				strncpy(GPASS2,GNICK2,(size_t)10);
			*z1=':';
			z1++;
			z2=strchr(z1,':');
			if(z2 != (char *) NULL) {
				if(*z1 == '1') gnomail2 = 1;
			}
		}
	}

	/* Nickname is after the last : */
	z1=strrchr(GNICK1,':');
	if(z1 != (char *) NULL) {
		strcpy(GNICK1,z1+1);	/* Ok, z1 is shorter than GNICK */
	}
	z1=strrchr(GNICK2,':');
	if(z1 != (char *) NULL) {
		strcpy(GNICK2,z1+1);
	}

	fscanf(datei,"%i\n",&gnummoves);
	if(gnummoves > 0) {
		u1=gnummoves*7+1;
		gmoves=malloc((size_t)u1);
		if(gmoves == NULL) {
			errorwin(ERRORMEM,"","",0);
			return(-1);	
		}
		fgets(gmoves,u1,datei);
		fgetc(datei);	/* eliminate the \n */
	}	
	else {
		gmoves=(char *)NULL;
	}
	fgets(GBOARD,65,datei);

	if(strlen(GBOARD) < 64) {
		errorwin(ERRORGAMECOR,"","",0);
		fclose(datei);
		return(-1);
	}

	ct=0;
	while(fgetc(datei) == '\n');
	merker=ftell(datei)-1;
	i1=fgetc(datei);
	while((i1 != '\n') && (i1 != EOF)) {
		ct++;
		i1=fgetc(datei);			
	}

	if(ct > 0) {
		ct+=2;
		gmessage=malloc((size_t)ct);
		if((fseek(datei,merker,SEEK_SET) == 0) && (gmessage != (char *)NULL)) {
			fgets(gmessage,ct,datei);
		}
	} else {
		gmessage=(char *)NULL;
	}
	fclose(datei);

	return(0);
} 

/* RESUMEGAME */
void resumegame(int ischange) {
	char *z1,*z2,*z3,*z4;
	unsigned char uc1;
	int i1,i2,i3;
	int blacksturn;
	int flg;
	char ID[MAXSTRING+1];
	int fromx,fromy;
	int tox,toy;
	int moveto;
	char PASS[11];
	char *color;
	char *piece;
	int possiblepieces;
	int mate;
	char *message;
	int reqremis;
	int shpi;
	int oldnummoves;
	int swap;
	int redframes;
	int rightswap;
	char empty[1];

	empty[0]=(char)NULL;
	message=empty;

	moveto=0;
	fromx=0;
	fromy=0;
	tox=0;
	toy=0;
	color=(char *)NULL;
	piece=(char *)NULL;
	possiblepieces=0;
	mate=0;
	reqremis=0;
	
	/* GameID */
	z1=strstr(query,"ID=");
	if(z1 == (char *)NULL) {
		errorwin(ERRORDEFAULT,"","",0);
		return;
	}
	z1+=3;
	z2=z1;
	while((*z2 != '&') && (*z2 != (char)NULL))
		z2++;
	uc1=*z2;
	*z2=(char)NULL;
	strncpy(ID,z1,(size_t)MAXSTRING);
	if(readgamedata(ID) == -1)
		return;
	*z2=uc1;

	/* Swap Colors */
	swap=0;
	z1=strstr(query,"SWAP=");
	if(z1 != (char *)NULL) {
		z1+=5;
		z2=z1;
		while((*z2 != '&') && (*z2 != (char)NULL))
			z2++;
		uc1=*z2;
		*z2=(char)NULL;
		swap=atoi(z1);
		*z2=uc1;
	}

#ifdef REDFRAMES
	redframes=1;
#else
	/* RedFrames */
	redframes=0;
	z1=strstr(query,"RF=");
	if(z1 != (char *)NULL) {
		z1+=3;
		z2=z1;
		while((*z2 != '&') && (*z2 != (char)NULL))
			z2++;
		uc1=*z2;
		*z2=(char)NULL;
		redframes=atoi(z1);
		*z2=uc1;
	}

	if((redframes != 0) && (redframes != 1))
		redframes=0;
#endif
	/* Special-GameOvers ? */
	if(strcmp(GPASS,"[REMIS]") == 0) {
		mate=3;
	}
	if(strcmp(GPASS,"[GIVEUP]") == 0) {
		mate=4;
	}


	/* Should white be on top ? */
	if((gnummoves % 2) == 0) {
		rightswap=1;
	} else {
		rightswap=-1;
	}

	if(mate != 0 && swap == 0)
		swap=rightswap;

	if(mate == 0) {
		/* Special Messages */
		if(gmessage != (char *)NULL) {
			/* Remis-Request ? */
			if(strstr(gmessage,"[REMIS?]@") == gmessage) {
				reqremis=1;
				rightswap=(-1)*rightswap;
			}	
		}

  if((swap != 1) && (swap != -1)) 
    swap=rightswap;

		/* Password */
		z1=strstr(query,"PASS=");	
		if(z1 != (char *)NULL) {
			z1+=5;
			z2=z1;
			while((*z2 != '&') && (*z2 != (char)NULL))
				z2++;
			uc1=*z2;
			*z2=(char)NULL;
			strncpy(PASS,z1,(size_t)10);
			PASS[10]=(char)NULL;
			*z2=uc1;	

		} else {
			PASS[0]=(char)NULL;
		}
	}


	/* GNUMMOVES */
	oldnummoves=0;
	z1=strstr(query,"GNUMMOVES=");
	if(z1 != (char *)NULL) {
		z1+=10;
		z2=z1;
		while((*z2 != '&') && (*z2 != (char)NULL))
			z2++;
			uc1=*z2;
			*z2=(char)NULL;
			oldnummoves=atoi(z1);
			*z2=uc1;
		}

		if((oldnummoves > 0) && (oldnummoves != gnummoves)) {	/* Theres not the current Board loaded */
			errorwin(ERROROLDBOARD,PASS,ID,swap);
			return;
		}


	/* Message */
	z1=strstr(query,"MESSAGE=");
	if(z1 != (char *)NULL) {
		z1+=8;
		z2=z1;
		while((*z2 != '&') && (*z2 != (char)NULL))
			z2++;
		uc1=*z2;
		*z2=(char)NULL;
		message=malloc((size_t)strlen(z1)+1);
		if(message != (char *)NULL) {
			strncpy(message,z1,(size_t)strlen(z1)+1);
		}
		*z2=uc1;
	} else {
		message=empty;
	}

	if(mate == 0) {
		/* Request Remis: Yes/No */
		/* Remis was accepted or not */
		z1=strstr(query,"REQREMIS=");
		if(z1 != (char *)NULL) {
#ifndef NOPASS
			if(strcmp(PASS,GPASS) != 0) {
				errorwin(ERRORPASS,"",ID,swap);
				return;
			}
#endif
			z1+=9;
			z2=z1;
			while((*z2 != '&') && (*z2 != (char)NULL))
				z2++;
			uc1=*z2;
			*z2=(char)NULL;
			if(strstr(z1,"YES") == z1) { /* remis accepted */

			i1=strlen(message);
			i1+=1;
			z1=(char *)malloc(i1);
			if(z1 != (char *)NULL) {
				snprintf(z1,(size_t)i1,"@%s",message);
				rewriteGameAndSwap(ID,"[REMIS]",z1);
				free(z1);
			} else {
				rewriteGameAndSwap(ID,"[REMIS]","");
			}
			if(gnomail2 != 1)
				mail(ID,GPLAYER2,GPLAYER2,GNICK2,GNICK2,"",gmoves,GBOARD,"",gnummoves,4,GNICK1,rightswap);
			if(gnomail1 != 1)
				mail(ID,GPLAYER1,GPLAYER2,GNICK1,GNICK2,"",gmoves,GBOARD,message,gnummoves,4,GNICK2,(-1)*rightswap);
	
		} else { /* remis NOT accepted */
				genPass(PASS);
				i1=strlen(message);
				i1+=1;
				z1=(char *)malloc(i1);
				if(z1 != (char *)NULL) {
					snprintf(z1,(size_t)i1,"@%s",message);
					rewriteGameAndSwap(ID,PASS,z1);
					free(z1);
				} else {
					rewriteGameAndSwap(ID,PASS,"");
				}
				if(gnomail2 != 1)
					mail(ID,GPLAYER2,GPLAYER2,GNICK2,GNICK2,"",gmoves,GBOARD,"",gnummoves,0,GNICK1,rightswap);
				if(gnomail1 != 1) {
						mail(ID,GPLAYER1,GPLAYER2,GNICK1,GNICK2,GPASS,gmoves,GBOARD,message,gnummoves,0,GNICK2,(-1)*rightswap);
				}
			}

			back2board(RESREQUESTSENT,"",ID,swap);

			*z2=uc1;
			return;
		}

	  /* Is it the second move ? */
  	z1=strstr(query,"MOVE=");
	  if(z1 != (char *)NULL) {
  	  z1+=5;

    	if((*z1 != (char)NULL) && (*z1 != '&')) {
      	fromx=*z1;
	      z1++;
  	  }
    	if((*z1 != (char)NULL) && (*z1 != '&')) {
      	fromy=*z1-48;
	      z1++;
  	    moveto=1;
    	}
	    if((*z1 != (char)NULL) && (*z1 != '&')) {
  	    tox=*z1;
    	  z1++;
	    }
  	  if((*z1 != (char)NULL) && (*z1 != '&')) {
    	  toy=*z1-48;
	    }


  	} else {
	
			/* Are there Special actions requested ? */
			z1=strstr(query,"SPECIAL=");
			if(z1 != (char *)NULL) {
				z1+=8;
				z2=z1;
				while((*z2 != (char)NULL) && (*z2 != '&'))
					z2++;
				uc1=*z2;
				*z2=(char)NULL;
		
				if(strcmp(z1,"PASS") == 0) {
					mail(ID,GPLAYER1,GPLAYER2,GNICK1,GNICK2,GPASS,gmoves,GBOARD,"",gnummoves,isMate(),GNICK2,rightswap);
					back2board(MAILPASS,PASS,ID,swap);
					return;
				}

				/* User has choosen Remis-Switch */
				if(strcmp(z1,"REMIS") == 0) {
#ifndef NOPASS
					if(strcmp(PASS,GPASS) != 0) {
						errorwin(ERRORPASS,"",ID,swap);
						return;
					}
#endif
					genPass(PASS);
					i1=strlen(message);
					i1+=10;
					z1=(char *)malloc(i1);
					if(z1 != (char *)NULL) {
						snprintf(z1,(size_t)i1,"[REMIS?]@%s",message);
						rewriteGameAndSwap(ID,PASS,z1);
						free(z1);
					} else {
						rewriteGameAndSwap(ID,PASS,"[REMIS?]@");
					}
					/* Players are swapped now, new GPASS is set */
					if(gnomail2 != 1)
						mail(ID,GPLAYER2,GPLAYER2,GNICK2,GNICK2,"",gmoves,GBOARD,"",gnummoves,3,GNICK1,rightswap);
					if(gnomail1 != 1)
						mail(ID,GPLAYER1,GPLAYER2,GNICK1,GNICK2,GPASS,gmoves,GBOARD,message,gnummoves,3,GNICK2,-1*rightswap);

					back2board(RESREQUESTSENT,"",ID,swap);

					return;
				}

				if(strcmp(z1,"GIVEUP") == 0) {
					if(strcmp(PASS,GPASS) != 0) {
						errorwin(ERRORPASS,"",ID,swap);
						return;
					}

          i1=strlen(message);
          i1+=10;
          z1=(char *)malloc(i1);
          if(z1 != (char *)NULL) {
            snprintf(z1,(size_t)i1,"[GIVEUP]@%s",message);
						rewriteGame(ID,"[GIVEUP]",z1);
            free(z1);
          } else {
						rewriteGame(ID,"[GIVEUP]",message);
          }
					
					if(gnomail1 != 1)
						mail(ID,GPLAYER1,GPLAYER1,GNICK1,GNICK1,"",gmoves,GBOARD,"",gnummoves,5,GNICK2,rightswap);
					if(gnomail2 != 1)
						mail(ID,GPLAYER2,GPLAYER1,GNICK2,GNICK1,"",gmoves,GBOARD,message,gnummoves,5,GNICK1,-1*rightswap);

					back2board(RESREQUESTSENT,"",ID,0);

					return;
				}

				if(strcmp(z1,"EMAIL") == 0) {
          if(strcmp(PASS,GPASS) != 0) {
						errorwin(ERRORPASS,"",ID,swap);
            return;
          }
					
					printf("<br><table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=left><font color=\"%s\">",BOARDGRID,BOARDMARGBG,BOARDMARGFG);
					printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
					printf("<INPUT TYPE=hidden name=ACTION value=CHMAIL>\n");
					printf("<INPUT TYPE=hidden name=PASS value=\"%s\">\n",GPASS);
					printf("<INPUT TYPE=hidden name=ID value=\"%s\">\n",ID);
					printf("<INPUT TYPE=hidden name=SWAP value=\"%i\">\n",swap);
					printf("<b>");
					printf(RESMAILOF,GNICK1);
					printf(":</b> \n");
					printf("<INPUT TYPE=text NAME=MAIL1 VALUE=\"%s\" SIZE=%i><br><br>\n",GPLAYER1,MAILLENGTH);
					printf("<TABLE BORDER=0 cellpadding=0 cellspacing=0 margin=0>");
					printf("<TR><TD><font color=\"%s\"><b>%s: </b></font> </TD><TD>",BOARDMARGFG,RESFIXPASS);
					printf("<INPUT TYPE=password NAME=FIXPASS VALUE=\"");
					if(globalpass != 1) printf("%s",GPASS);
					printf("\" SIZE=10>");
					printf("</TD></TR><br>");
					printf("<TR><TD><font color=\"%s\"><b>%s: </b></font> </TD><TD>",BOARDMARGFG,RESFIXPASSA);
					printf("<INPUT TYPE=password NAME=FIXPASSA VALUE=\"");
					if(globalpass != 1) printf("%s",GPASS);
					printf("\" SIZE=10>");
					printf("</TD></TR></TABLE>\n");
					printf("<br><font size=\"-2\">(%s)</font><br>\n",RESPASSWARN);
					printf("<br><INPUT TYPE=checkbox NAME=NOMAILTOME");
					if(gnomail1 == 1) printf(" CHECKED ");
					printf("> %s<br><br>",RESNOMAILTOME);
					printf("<CENTER><INPUT TYPE=submit VALUE=\"%s\"></CENTER>",MSGOK);
					printf("</font></td></tr></table></td></tr></table><br>\n");


					printf("</FORM>\n");

					return;
				}

				if(strcmp(z1,"LOGOUT") == 0) {
					back2board(SPECIALLOGOUT,"","",0);
					return;
				}
			
				*z2=uc1;
			}
		}
	}

		printf("<FONT size=\"+1\">");

  	/* Who must move ? */
	  if((gnummoves % 2) == 0) {
			printf(RESTITLE,GNICK1,GNICK2);
			printf("</FONT><br>");
			if((reqremis == 0) && (mate == 0))
				printf(RESTURN,GNICK1,TWHITE);		
 	  	blacksturn=0;
	  } else {
			printf(RESTITLE,GNICK2,GNICK1);
			printf("</FONT><br>");
			if((reqremis == 0) && (mate == 0))
				printf(RESTURN,GNICK1,TBLACK);
 	  	blacksturn=1;
	 	}

		if(reqremis == 1)
			printf(RESTURNR,GNICK1);

		if(mate == 0)
			printf("<br>\n");

	/* Pawn-Change */
	if(ischange == 1) {
		printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\"><b>",BOARDGRID,BOARDMARGBG,BOARDMARGFG);
		printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
		printf("<input type=hidden name=\"ID\" value=\"%s\">\n",ID);
		printf("<input type=hidden name=\"ACTION\" value=\"MOVED\">\n");
		printf("<input type=hidden name=\"SWAP\" value=\"%i\">\n",swap);
		printf("<input type=hidden name=\"PASS\" value=\"%s\">\n",PASS);
		printf("<input type=hidden name=\"MOVE\" value=\"%c%i%c%i\"\n>",fromx,fromy,tox,toy);
		if(message != (char *)NULL) 
			printf("<input type=hidden name=\"MESSAGE\" value=\"%s\">\n",message);
		printf("%s\n",RESCHANGE);
	
		printf("<table border=0><tr>");
   	if(blacksturn == 0) {
     	printf("<td><input type=image name=\"CHANGE=Q\" VALUE=\"Q\" src=\"%s\" alt=\"%s\"></td>\n",WQUEEN,TLONGQ);
      printf("<td><input type=image name=\"CHANGE=R\" VALUE=\"R\" src=\"%s\" alt=\"%s\"></td>\n",WROOKE,TLONGR);
 	    printf("<td><input type=image name=\"CHANGE=B\" VALUE=\"B\" src=\"%s\" alt=\"%s\"></td>\n",WBISHOP,TLONGB);
   	  printf("<td><input type=image name=\"CHANGE=N\" VALUE=\"N\" src=\"%s\" alt=\"%s\"></td>\n",WKNIGHT,TLONGN);
    } else {
 	    printf("<td><input type=image name=\"CHANGE=q\" VALUE=\"q\" src=\"%s\" alt=\"%s\"></td>\n",BQUEEN,TLONGQ);
   	  printf("<td><input type=image name=\"CHANGE=r\" VALUE=\"r\" src=\"%s\" alt=\"%s\"></td>\n",BROOKE,TLONGR);
     	printf("<td><input type=image name=\"CHANGE=b\" VALUE=\"b\" src=\"%s\" alt=\"%s\"></td>\n",BBISHOP,TLONGB);
      printf("<td><input type=image name=\"CHANGE=n\" VALUE=\"n\" src=\"%s\" alt=\"%s\"></td>\n",BKNIGHT,TLONGN);
 	  }
		printf("</tr></table>\n");	

		printf("</FORM>\n");
		printf("</b></font></td></tr></table></td></tr></table>\n");

	} else {

		switch(isMate()) {
			case 1:
				printf("<font color=\"%s\"><b>%s, %s !</b></font><br>",WARNCOLOR,MATE,GNICK1);
				mate=1;
			break;
			case 2:
				printf("<font color=\"%s\"><b>%s, %s !</b></font><br>",WARNCOLOR,CHECK,GNICK1);
			break;
			case 3:
				printf("<font color=\"%s\"><b>%s</b></font><br>",WARNCOLOR,MAILISREMIS);
			break;
			case 4:
				printf("<font color=\"%s\"><b>",WARNCOLOR);
				printf(MAILGIVESUP,GNICK1);
				printf("</b></font><br>");
			break;
			default:
			break;
		}


		if(gmessage != (char *)NULL) {
			z1=strchr(gmessage,'@');
			if(z1 != (char)NULL) {
				if(*(z1+1) != (char)NULL)
					printf("<b>%s: <font color=\"%s\">%s</font></b><br>\n",RESMESSAGE,MSGCOLOR,z1+1);
			}
		}
	}

	if(mate == 0) {
		printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
		printf("<input type=hidden name=\"ID\" value=\"%s\">\n",ID);
		if(moveto == 1)
			printf("<input type=hidden name=\"ACTION\" value=\"MOVED\">\n");
		else
			printf("<input type=hidden name=\"ACTION\" value=\"RESUME\">\n");
		printf("<INPUT TYPE=hidden name=GNUMMOVES value=\"%i\">\n",gnummoves);
		printf("<input type=hidden name=\"SWAP\" value=\"%i\">\n",swap);
		printf("<input type=hidden name=\"RF\" value=\"%i\">\n",redframes);

#ifdef DEBUG
		strncpy(PASS,GPASS,(size_t)11);
#endif

		if(ischange == 0) {
			if(PASS[0] != (char)NULL)
  			printf("%s: <input type=password value=\"%s\" name=\"PASS\">",RESPASS,PASS);
			else
				printf("%s: <input type=password name=\"PASS\">",RESPASS);

#ifdef DEBUG
		printf(" [ %s ]",GPASS);
#else
#ifdef SHOWPASS
	printf(" [ %s ]",GPASS);
#endif
#endif
		}
		printf("<BR>\n");
	}

	if(reqremis == 1) {
		printf("<br><table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">",BOARDGRID,BOARDMARGBG,BOARDMARGFG);
		printf("<b>%s</b></font></td></tr><tr><td nowrap align=center><font color=\"%s\">",RESREMREQ,BOARDMARGFG);
		printf("<input type=image name=\"REQREMIS=YES\" value=\"TUX\" src=\"%s\" alt=\"%s\" border=0 align=middle>%s&nbsp;&nbsp;&nbsp;\n",BUTTONYES,MSGACCEPT,MSGACCEPT);
		printf("<input type=image name=\"REQREMIS=NO\" value=\"TUX\"  src=\"%s\" alt=\"%s\" border=0 align=middle>%s\n",BUTTONNO,MSGDIS,MSGDIS);
		printf("</font></td></tr></table></td></tr></table><br>\n");
	}

	printf("<table border=0>\n");
	printf("<tr><td bgcolor=\"%s\">\n",BOARDGRID);
	printf("<table border=0 cellspacing=2>\n");

	if(fromx == 0 || fromy == 0)
		printf("<tr><td bgcolor=\"%s\" align=center valgin=center><a href=\"%s?ACTION=RESUME&PASS=%s&ID=%s&SWAP=%i&RF=%i&MOVE=&MESSAGE=%s\"><img src=\"%s\" alt=\"%s\" border=0 title=\"%s\"></td>",BOARDMARGBG,getenv("SCRIPT_NAME"),PASS,ID,swap,(-1)*redframes+1,message,REDFRAME_ICON,REDFRAME_TEXT,REDFRAME_TEXT_LONG);
	else
		printf("<tr><td bgcolor=\"%s\" align=center valgin=center><a href=\"%s?ACTION=RESUME&PASS=%s&ID=%s&SWAP=%i&RF=%i&MOVE=%c%c&MESSAGE=%s\"><img src=\"%s\" alt=\"%s\" border=0 title=\"%s\"></td>",BOARDMARGBG,getenv("SCRIPT_NAME"),PASS,ID,swap,(-1)*redframes+1,fromx,fromy+48,message,REDFRAME_ICON,REDFRAME_TEXT,REDFRAME_TEXT_LONG);

	if(swap==1) i1=0; else i1=7;
	for(;;i1+=swap) {
		if(swap==1 && i1>7) break;
		if(swap!=1 && i1<0) break;
		printf("<td align=\"center\" bgcolor=\"%s\"><font color=\"%s\">%c</font></td>",BOARDMARGBG,BOARDMARGFG,'A'+i1);
	}

	if(fromx == 0 || fromy == 0)
		printf("<td bgcolor=\"%s\" align=center valign=center><a href=\"%s?PASS=%s&ID=%s&ACTION=RESUME&SWAP=%i&RF=%i&MOVE=&MESSAGE=%s\">",BOARDMARGBG,getenv("SCRIPT_NAME"),PASS,ID,swap*(-1),redframes,message);
	else
		printf("<td bgcolor=\"%s\" align=center valign=center><a href=\"%s?PASS=%s&ID=%s&ACTION=RESUME&SWAP=%i&RF=%i&MOVE=%c%c&MESSAGE=%s\">",BOARDMARGBG,getenv("SCRIPT_NAME"),PASS,ID,swap*(-1),redframes,fromx,fromy+48,message);

	if(swap==1)
		printf("<img src=\"%s\" alt=\"%s\" border=0 title=\"%s\">",SWAP_ICON0,SWAP_TEXT0,SWAP_TEXT0_LONG);
	else
		printf("<img src=\"%s\" alt=\"%s\" border=0 title=\"%s\">",SWAP_ICON1,SWAP_TEXT1,SWAP_TEXT1_LONG);
	printf("</a></td></tr>");

	if(swap==1) i1=0; else i1=7;
	for(;;i1+=swap) {
		if(swap==1 && i1>7) break;
		if(swap!=1 && i1<0) break;	
		printf("<tr height=\"%i\">\n",FIELDSIZE);
		printf("<td align=\"center\" bgcolor=\"%s\">&nbsp;<font color=\"%s\">%i</font>&nbsp;</td>\n",BOARDMARGBG,BOARDMARGFG,8-i1);
		if(swap==1) i2=0; else i2=7;
		for(;;i2+=swap) {
			if(swap==1 && i2>7) break;
			if(swap!=1 && i2<0) break;
			i3=GBOARD[i1*8+i2];
			if((i1 + i2) % 2) {
				color=BOARDBLACK;
			} else {
				color=BOARDWHITE;
			}

			printf("<td valign=\"center\" align=\"center\" width=\"%i\" height=\"%i\" bgcolor=\"%s\">",FIELDSIZE,FIELDSIZE,color);

			flg=0;
			if((canmove(fromx,fromy,i2,i1) == 1) && (ischange == 0) && (reqremis == 0) && (mate != 3) && (mate != 4)){
				possiblepieces++;
				flg=1;
			}

			piecepointer(i3,&z2,&piece,&z1,&z3,&z4);	/* piece, shortname, longname, image, smallimage, strokenimage */

			if(myisupper(i3))
				shpi=toupper(*z2);
			else
				shpi=tolower(*z2);
			
				

			if((z1 == NULL) || (piece == NULL) || (color == NULL)) {
				printf("&nbsp;\n");
			} else {
					if((fromx == ('A'+i2)) && (fromy == (8-i1)) )	/* active piece */
						printf("<table border=0 bgcolor=\"%s\" cellspacing=3 cellpadding=0><tr><td bgcolor=\"%s\">\n",SELECTEDCOLOR,color);
					if((tox == ('A'+i2)) && (toy == (8-i1)) && (ischange == 1)) /* active piece */
						printf("<table border=0 bgcolor=\"%s\" cellspacing=3 cellpadding=0><tr><td bgcolor=\"%s\">\n",SELECTEDCOLOR,color);

				if(flg==1) {  /* canmove */	
					if(redframes == 1) {
            printf("<table border=0 bgcolor=\"red\" cellspacing=3 cellpadding=0><tr><td bgcolor=\"%s\">\n",color);
					}

					if(moveto == 1)	/* move TO field */
						printf("<input type=image src=\"%s\" alt=\"%c\" name=\"MOVE=%c%i%c%i\" value=\"TUX\" title=\"%s\">",z1,shpi,fromx,fromy,'A'+i2,8-i1,piece);
					else						/* move FROM field */
						printf("<input type=image src=\"%s\" alt=\"%c\" name=\"MOVE=%c%i\" value=\"TUX\" title=\"%s\">",z1,shpi,'A'+i2,8-i1,piece);

					if(redframes == 1) {
						printf("</td></tr></table>\n");
					}

				} else {	/* can NOT move */
						if((fromx == ('A'+i2)) && (fromy == (8-i1)) )
							printf("<input type=image src=\"%s\" alt=\"%c\" name=\"MOVE=CANC\" value=\"TUX\" title=\"%s\">",z1,shpi,piece);
						else
							printf("<img border=0 src=\"%s\" alt=\"%c\" title=\"%s\">",z1,shpi,piece);
				}
			}

			if((fromx == ('A'+i2)) && (fromy == (8-i1)))  /* active piece */
				printf("</td></tr></table>\n");
			if((tox == ('A'+i2)) && (toy == (8-i1)) && (ischange == 1))  /* active piece */
				printf("</td></tr></table>\n");

			printf("</td>\n");
		}
		printf("<td align=\"center\" bgcolor=\"%s\">&nbsp;<font color=\"%s\">%i</font>&nbsp;</td>\n",BOARDMARGBG,BOARDMARGFG,8-i1);
		printf("</tr>\n");
		
	}
	printf("<tr><td bgcolor=\"%s\">&nbsp;</td>",BOARDMARGBG);
	if(swap==1) i1=0; else i1=7;
	for(;;i1+=swap) {
		if(swap==1 && i1>7) break;
		if(swap!=1 && i1<0) break;
		printf("<td align=\"center\" bgcolor=\"%s\"><font color=\"%s\">%c</font></td>",BOARDMARGBG,BOARDMARGFG,'A'+i1);
	}
	printf("<td bgcolor=\"%s\">&nbsp;</td></tr>",BOARDMARGBG);
	printf("</table>\n");
	printf("</td>");
	printf("</tr></table>\n");

	if(mate == 0) {
		if(reqremis == 0) {
			printf("<br><table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">",BOARDGRID,BOARDMARGBG,BOARDMARGFG);
			printf("<input type=radio name=SPECIAL value=PASS>%s</input>",SPECIALRESENT);
			printf("&nbsp;<input type=radio name=SPECIAL value=REMIS>%s</input>",SPECIALREMIS);
			printf("&nbsp;<input type=radio name=SPECIAL value=GIVEUP>%s</input>",SPECIALGIVEUP);
			printf("&nbsp;<input type=radio name=SPECIAL value=EMAIL>%s</input>",SPECIALSETTINGS);
			printf("&nbsp;<input type=radio name=SPECIAL value=LOGOUT>%s</input>",SPECIALLOGOUT);
			printf("&nbsp;<br><input type=submit value=\"%s\">",SPECIALDO);
			printf("</font></td></tr></table></td></tr></table>\n");
		}
		if(message != (char *)NULL) 
			printf("<br>%s:<br><input type=text size=\"%i\" name=MESSAGE value=\"%s\">",RESMESSAGE,MESSAGEBOXLEN,message);
		else
			printf("<br>%s:<br><input type=text size=\"%i\" name=MESSAGE value=\"\">",RESMESSAGE,MESSAGEBOXLEN);
		
		printf("</FORM>\n");
	}

	if(ischange == 0) {
		movesout(stdout,gmoves,gnummoves,ID,swap);	
	}
	
	if(message != empty)	/* so it has been alocated! */
		free(message);

	printf("<br><font size=-1><b>%s: %s &nbsp;&nbsp;&nbsp; %s: %c%c.%c%c.%c%c%c%c</font>\n",RESID,ID,RESSTART,ID[6],ID[7],ID[4],ID[5],ID[0],ID[1],ID[2],ID[3]);
}

/* NEWGAME */
void newgame(void) {
	time_t Zeit;
	struct tm ZP;
	char ID[MAXSTRING+1];
	char JID[MAXSTRING+1];
	char PATH[MAXSTRING+1];
	char JPATH[MAXSTRING+1];
	char *z1;
	FILE *datei;
	char *nextplayer;
	char *otherplayer;
	char *nextmail;
	char *othermail;
	char *message;
	int white;
	char *enemy;
	char pass[11];
	int i1;
	char board[]="rnbqkbnrppppppppeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeePPPPPPPPRNBQKBNR";
	char *z2;
	unsigned char uc1;

	ID[0]=(char)NULL;
	JID[0]=(char)NULL;

#ifdef DEBUG
	printf("%s<br>\n",query);
#endif

	z1=strstr(query,"JOINID=");
	if(z1 !=(char *)NULL) {
		z1+=7;
		z2=z1;
		while((*z2 != '&') && (*z2 != (char)NULL))
			z2++;
		uc1=*z2;
		*z2=(char)NULL;
		strncpy(JID,z1,(size_t)MAXSTRING);
		*z2=uc1;
	}
	
	z1=strstr(query,"WHITE=");
	if(z1 == (char *)NULL) {
		errorwin(ERRORDEFAULT,"","",0);
		return;
	}
	z1+=6;
	white=*z1-48;
	if(white != 2)
		white=1;
	
#ifdef DEBUG
	printf("White is: %i<br>\n",white);
#endif
	 
	if(white == 1) {
		nextplayer=strstr(query,"NICK1=");
		otherplayer=strstr(query,"NICK2=");
		nextmail=strstr(query,"MAIL1=");
		othermail=strstr(query,"MAIL2=");
		enemy=othermail;
	} else {
		nextplayer=strstr(query,"NICK2=");
		otherplayer=strstr(query,"NICK1=");
		nextmail=strstr(query,"MAIL2=");
		othermail=strstr(query,"MAIL1=");
		enemy=nextmail;
	}

	if((nextplayer == (char *)NULL) || (otherplayer == (char *)NULL) || (nextmail == (char *)NULL) || (othermail == (char *)NULL)) {
		errorwin(ERRORDEFAULT,"","",0);
		return;
	}

	message=strstr(query,"MESSAGE=");

	enemy+=6;
	nextplayer+=6;
	otherplayer+=6;
	nextmail+=6;
	othermail+=6;
	message+=8;

	z1=nextplayer;
	while((*z1 != (char)NULL) && (*z1 != '&'))
		z1++;
	*z1=(char)NULL;

	z1=otherplayer;
	while((*z1 != (char)NULL) && (*z1 != '&'))
		z1++;
	*z1=(char)NULL;

	z1=nextmail;
	while((*z1 != (char)NULL) && (*z1 != '&'))
		z1++;
	*z1=(char)NULL;

	z1=othermail;
	while((*z1 != (char)NULL) && (*z1 != '&'))
		z1++;
	*z1=(char)NULL;	

	if((strlen(nextplayer) > NICKLENGTH) || (strlen(otherplayer) > NICKLENGTH)) {
		errorwin(ERRORNICKLONG,"","",0);
		return;
	}

  if((strchr(nextplayer, ':') != (char *)NULL) || (strchr(otherplayer, ':') != (char *)NULL )) {
    errorwin(ERRORNODP,"","",0);
    return;
  }

  if((strlen(nextmail) > MAILLENGTH) || (strlen(othermail) > MAILLENGTH)) {
    errorwin(ERRORMAILLONG,"","",0);
    return;
  }

	Zeit=time((time_t *)NULL);
	ZP=*localtime(&Zeit);

	snprintf(ID,(size_t)MAXSTRING,"%4i%2i%2i%u",ZP.tm_year+1900,ZP.tm_mon+1,ZP.tm_mday,getpid());
	z1=ID;
	while(*z1 != (char)NULL) {
		if(*z1 == ' ')
			*z1='0';
		z1++;
	}

#ifdef DEBUG
	printf("New ID is %s<br>\n",ID);
#endif

	strncpy(PATH,DATAPATH,(size_t)MAXSTRING);
	strncat(PATH,"/",(size_t)MAXSTRING);
	if(*enemy == (char)NULL) {
		if(JID[0] != (char)NULL) {
			errorwin(ERRORMISMAIL,"","",0);
			return;
		}
		strncat(PATH,"X",(size_t)MAXSTRING);
	}
	strncat(PATH,ID,(size_t)MAXSTRING);

	/* Hey, we wanna join the game.... */
	if(JID[0] != (char)NULL) {
		strncpy(JPATH,DATAPATH,(size_t)MAXSTRING);
		strncat(JPATH,"/",(size_t)MAXSTRING);
		strncat(JPATH,JID,(size_t)MAXSTRING);
		if(rename(JPATH, PATH) < 0) {
			errorwin((char *)strerror(errno),"","",0);
			return;
		}
	}
	

#ifdef DEBUG
	printf("Path=%s<br>\n",PATH);
	printf("NextNick: %s<br>\n",nextplayer);
	printf("NextMail: %s<br>\n",nextmail);
	printf("OtherNick: %s<br>\n",otherplayer);
	printf("OtherMail: %s<br>\n",othermail);
#endif

	if(nextmail[0] != (char)NULL) {
		if(checkmail(nextmail) < 0) {
			errorwin(ERRORMAILVALID,"","",0);
			return;
		}
	}

	if(othermail[0] != (char)NULL) {
		if(checkmail(othermail) < 0) {
			errorwin(ERRORMAILVALID,"","",0);
			return;
		}
	}

	if((nextmail != enemy) && (nextmail[0] == (char)NULL)) {
		errorwin(ERRORMISMAIL,"","",0);
		return;
	}

  if((othermail != enemy) && (othermail[0] == (char)NULL)) {
    errorwin(ERRORMISMAIL,"","",0);
    return;
  }


	if((othermail[0] != (char)NULL) && (otherplayer[0] == (char)NULL)) {
		errorwin(ERRORMISNICK,"","",0);
		return;
	}

	if((nextmail[0] != (char)NULL) && (nextplayer[0] == (char)NULL)) {
		errorwin(ERRORMISNICK,"","",0);
		return;
	}

	genPass(pass);

#ifdef DEBUG
	printf("Pass: %s\n",pass);
#endif

	datei=fopen(PATH,"w");
	if(datei == (FILE *)NULL) {
		errorwin(ERRORCREATE,"","",0);
#ifdef DEBUG
		printf("%s: %s<br>\n",ERRORCREATE,PATH);	
#endif
		return;
	}
	

	fputs(nextplayer,datei);fputc('\n',datei);
	fputs(nextmail,datei);fputc('\n',datei);
	fputs(otherplayer,datei);fputc('\n',datei);
	fputs(othermail,datei);fputc('\n',datei);
	fputs(pass,datei);fputc('\n',datei);
	fputs("0",datei);fputc('\n',datei);
	fputs("",datei);fputc('\n',datei);
	fputs(board,datei);fputc('\n',datei);

	if(message != (char *)NULL) {
		z2=message;
		while((*z2 != (char)NULL) && (*z2 != '&')) {
			z2++;
		}
		*z2=(char)NULL;
		fputc('@',datei);
		fputs(message,datei);
	} 

	fclose(datei);
	chmod(PATH,(mode_t)S_IRUSR|S_IWUSR);

	if(*enemy != (char)NULL) {
		if(message == (char *)NULL) {
			mail(ID,nextmail, othermail, nextplayer, otherplayer, pass, "", board, "", gnummoves,0,otherplayer,1);
			mail(ID,othermail, othermail, otherplayer, nextplayer, "", "", board, "", gnummoves,0,nextplayer,-1);
		} else {
			mail(ID,nextmail, othermail, nextplayer, otherplayer, pass, "", board, message, gnummoves,0,otherplayer,1);
			mail(ID,othermail, othermail, otherplayer, nextplayer, "", "", board, message, gnummoves,0,nextplayer,-1);
		}
		back2board(STARTFINISH,"",ID,0);
	} else {
		back2board(STARTWAIT,"","",0);
	}

}

/* ISMATE */
int isMate(void) {
	/* ret=0: normal */
	/* ret=1: mate */
	/* ret=2: chess */
	/* ret=3: remis */
	/* ret=4: giveup */
	int i1,i2;
	int ct;

	ct=0;

	if(strcmp(GPASS,"[REMIS]") == 0) {
		return(3);
	}

	if(strcmp(GPASS,"[GIVEUP]") == 0) {
		return(4);
	}

	if(strcmp(GPASS,"[MATE]") == 0) {
		return(1);
	}

	for(i1=0; i1<8; i1++) {
		for(i2=0; i2<8; i2++) {
			if(canmove(0,0,i1,i2) == 1) ct++;
		}
	}

	if(ct == 0) return(1);  /* No move possible anymore.... */

	if((gnummoves % 2) == 0) {							/* white's turn */
		i1=whereIsMyKing(0);
		i2=kingIsAttacked(i1%8,i1/8,'K');
	} else {																/* black's turn */
		i1=whereIsMyKing(1);
		i2=kingIsAttacked(i1%8,i1/8,'k');
	}

	if(i2 == 1) return(2);		/* The king is attacked */
	
	return(0);
}

/* PIECEMOVED */
void piecemoved(void) {
	char *z1;
	char *z2;
	int i1;
	char ID[MAXSTRING+1];
	char MOVE[5];
  FILE *datei;
  char path[MAXSTRING+1];
  unsigned u1;
	char PASS[11];
	char *newmoves;
	int changed;
	int mustset;
	int wasenp;
	char TNICK[NICKLENGTH+1+10+3];
	char TPLAYER[MAXSTRING+1];
	int movedtopiece;
	int oldnummoves;
	int swap;
	int rightswap;

	MOVE[0]=(char)NULL;
	MOVE[1]=(char)NULL;
	MOVE[2]=(char)NULL;
	MOVE[3]=(char)NULL;
	MOVE[4]=(char)NULL;
	changed=0;
	mustset=1;
	wasenp=0;
	movedtopiece='e';

#ifdef DEBUG
	printf("Moved: %s<br>\n",query);
#endif

	/* Special moves have to be handled in RESUMEGAME */
	z1=strstr(query,"SPECIAL=");
	if(z1 != (char *)NULL) {
		resumegame(0);
		return;
	}

	/* Read old prefs */
	z1=strstr(query,"ID=");
	if(z1==(char *)NULL) {
		errorwin(ERRORMOV,"","",0);
		return;
	}
	z1+=3;
	z2=z1;
	while((*z2 != (char)NULL) && (*z2 != '&')) {
		z2++;
	}
	i1=*z2;
	*z2=(char)NULL;
	strncpy(ID,z1,(size_t)MAXSTRING);
	if(readgamedata(ID) == -1)
		return;	
	*z2=i1;

	/* Should white be on top ? */
	if((gnummoves % 2) == 0) {
		rightswap=1;
	} else {
		rightswap=-1;
	}

  /* Swap Colors */
  z1=strstr(query,"SWAP=");
  if(z1 != (char *)NULL) {
    z1+=5;
    z2=z1;
    while((*z2 != '&') && (*z2 != (char)NULL))
      z2++;
    i1=*z2;
    *z2=(char)NULL;
    swap=atoi(z1);
    *z2=i1;
  }

	/* Fiddle out the move */
	z1=strstr(query,"MOVE=");
	if(z1==(char *)NULL) {
		errorwin(ERRORMOV,GPASS,ID,swap);
		return;
	}
	z1+=5;
	z2=z1;
	while((*z2 != (char)NULL) && (*z2 != '&')) {
		z2++;
	}
	i1=*z2;
	*z2=(char)NULL;
	strncpy(MOVE,z1,(size_t)4);		/* HERE OCCURED AN ERROR WITH THE MOVE=BLA= ARGUMENTS, so size 4 instead of 5 */
	*z2=i1;

	if(MOVE[3] == (char)NULL) {
		errorwin(ERRORMOV,GPASS,ID,swap);
		return;
	}

#ifndef NOPASS
  /* Test Password */
  z1=strstr(query,"PASS=");
  if(z1==(char *)NULL) {
    errorwin(ERRORPASS,"",ID,swap);
    return;
  }
  z1+=5;
  z2=z1;
  while((*z2 != (char)NULL) && (*z2 != '&')) {
    z2++;
  }
  i1=*z2;
  *z2=(char)NULL;

  if(strcmp(GPASS,z1) != 0) {	
		if(strcmp(MOVE,"CANC") == 0) {	/* Cancelled and no/wrong PASS given */
			back2board(MOVECANC, "", ID,0);
			return;
		}
    errorwin(ERRORPASS,"",ID,swap);
    return;
  }

  *z2=i1;
#endif

  if(strcmp(MOVE,"CANC") == 0) {
    back2board(MOVECANC, GPASS, ID,0);
    return;
  }



#ifdef DEBUG
	printf("Move was: %s<br>\n",MOVE);	
#endif

	/* GNUMMOVES */
	oldnummoves=0;
	z1=strstr(query,"GNUMMOVES=");
	if(z1 != (char *)NULL) {
		z1+=10;
		z2=z1;
		while((*z2 != '&') && (*z2 != (char)NULL))
			z2++;
			i1=*z2;
			*z2=(char)NULL;
			oldnummoves=atoi(z1);
			*z2=i1;
		}

		if((oldnummoves > 0) && (oldnummoves != gnummoves)) { /* Theres not the current Board loaded */
			errorwin(ERROROLDBOARD,PASS,ID,swap);
			return;
		}

		/* Test if someone tried to hack us */
		if(canmove(MOVE[0],MOVE[1]-'0',MOVE[2]-'A','8'-MOVE[3]) != 1) {
			errorwin(ERRORILLEGAL,PASS,ID,swap);
			return;
		}

	/* has the piece being changed ? */
	z1=strstr(query,"CHANGE=");
	if(z1 != (char *)NULL) {
		z1+=7;
		changed=*z1;
#ifdef DEBUG
		printf("changed=%c\n",changed);
#endif
	}


	/* check for Pawn-Change */
	if((changed == 0) && (toupper(GBOARD[('8'-MOVE[1])*8+MOVE[0]-'A']) == 'P')) {			/* The Pawn was moved */
		if((MOVE[3] == '8') || (MOVE[3] == '1')) {
			resumegame(1);
			return;
		}
	}

#ifdef DEBUG
	printf("From: %c<br>To: %c<br>\n",GBOARD[('8'-MOVE[1])*8+MOVE[0]-'A'],GBOARD[('8'-MOVE[3])*8+MOVE[2]-'A']);
#endif

	movedtopiece=GBOARD[('8'-MOVE[3])*8+MOVE[2]-'A'];

	/* check for en passant and delete enemies */
	if((toupper(GBOARD[('8'-MOVE[1])*8+MOVE[0]-'A']) == 'P') && (GBOARD[('8'-MOVE[3])*8+MOVE[2]-'A'] == 'e')) {		/* move an pawn to en ampty field */
		if(MOVE[0] != MOVE[2]) {	/* Dont move straight to this empty field */
			if(GBOARD[('8'-MOVE[1])*8+MOVE[0]-'A'] == 'P') {	/* white pawn */
				GBOARD[('8'-MOVE[3])*8+MOVE[2]-'A'+8] = 'e';		/* remove enemy pawn */	
				wasenp=1;
			} else {	/* black pawn */
				GBOARD[('8'-MOVE[3])*8+MOVE[2]-'A'-8] = 'e';    /* remove enemy pawn */
				wasenp=1;
			}
		}
	}

	/*---*/	
	if(changed == 0)	/* If there was no piece change, "change" the piece to itself */
		changed=GBOARD[('8'-MOVE[1])*8+MOVE[0]-'A'];

	/* open data file */
  snprintf(path,(size_t)MAXSTRING,"%s/%s",DATAPATH,ID);
  datei=fopen(path,"w");
  if(datei == (FILE *)NULL) {
		errorwin(ERROROPEN,GPASS,ID,swap);
#ifdef DEBUG
    printf("%s: %s",ID,ERROROPEN);
#endif
    return;
  }

	/* New Moves-String */
  newmoves=(char *)malloc((size_t)(gnummoves+1)*7+1);
  if(newmoves == (char *)NULL) {
		errorwin(ERRORMEM,GPASS,ID,swap);
		return;
	}

	if(wasenp != 0) {
		wasenp=changed;
		changed='O';
	}

	if(gmoves != (char *)NULL) {
		snprintf(newmoves,(size_t)(gnummoves+1)*7+1,"%s%c%s%c%c",gmoves,GBOARD[('8'-MOVE[1])*8+MOVE[0]-'A'],MOVE,changed,movedtopiece);
	} else {
		snprintf(newmoves,(size_t)(gnummoves+1)*7+1,"%c%s%c%c",GBOARD[('8'-MOVE[1])*8+MOVE[0]-'A'],MOVE,changed,movedtopiece);
	}

	if(wasenp != 0) {
		changed=wasenp;
		wasenp=1;
	}
		


	if(toupper(GBOARD[('8'-MOVE[1])*8+MOVE[0]-'A']) == 'K') {
		if(strcmp(MOVE,"E8G8") == 0) {	/* Black Rochade King Side */
			GBOARD[4]='e';
			GBOARD[7]='e';
			GBOARD[6]='k';
			GBOARD[5]='r';
			mustset=0;	
		}
		if(strcmp(MOVE,"E8C8") == 0) {  /* Black Rochade Queen Side */
			GBOARD[4]='e';
			GBOARD[0]='e';
			GBOARD[2]='k';
			GBOARD[3]='r';
			mustset=0;
		}
		if(strcmp(MOVE,"E1G1") == 0) {  /* White Rochade King Side */
			GBOARD[60]='e';
			GBOARD[63]='e';
			GBOARD[62]='K';
			GBOARD[61]='R';
			mustset=0;
		}
		if(strcmp(MOVE,"E1C1") == 0) {  /* White Rochade Queen Side */
			GBOARD[60]='e';
			GBOARD[56]='e';
			GBOARD[58]='K';
			GBOARD[59]='R';
			mustset=0;
		}
	}

	/* set the piece on the board */
	if(mustset == 1) {	/* Board hasn`t been changed for now */
		GBOARD[('8'-MOVE[3])*8+MOVE[2]-'A']=changed;
		GBOARD[('8'-MOVE[1])*8+MOVE[0]-'A']='e';
	}

	genPass(PASS);

	/* search for MESSAGE */
	z1=strstr(query,"MESSAGE=");
	if(z1 != (char *)NULL) {
		z1+=8;
		z2=z1;
		while((*z2 != (char)NULL) && (*z2 != '&')) {
			z2++;
		}
		i1=*z2;
		*z2=(char)NULL;
	} else {
		z1=(char *)NULL;
	}

	/* save new game-data */

	if(GPASS2[0] != (char)NULL) {
		fputs(GPASS2,datei);
		fputc(':',datei);
		if(gnomail2 == 1) {
			fputc('1',datei);
			fputc(':',datei);
		}
	}

	fputs(GNICK2,datei);
	fputc('\n',datei);
	fputs(GPLAYER2,datei);
	fputc('\n',datei);

  if(globalpass == 0) {
    fputs(GPASS,datei);
    fputc(':',datei);
    if(gnomail1 == 1) {
      fputc('1',datei);
      fputc(':',datei);
    }
  }

	fputs(GNICK1,datei);
	fputc('\n',datei);
	fputs(GPLAYER1,datei);
	fputc('\n',datei);
	fputs(PASS,datei);
	fputc('\n',datei);
	fprintf(datei,"%i\n",gnummoves+1);
	fputs(newmoves,datei);
	fputc('\n',datei);
	fputs(GBOARD,datei);
	if(z1 != (char *)NULL) {
		fputc('\n',datei);
		fputc('@',datei);		/* This are real messages, they have to begin with @ */
		fputs(z1,datei);
		fputc('\n',datei);
	}
	fclose(datei);
	chmod(path,(mode_t)S_IRUSR|S_IWUSR);

	strncpy(TNICK,GNICK1,(size_t)NICKLENGTH+10+3);
	strncpy(GNICK1,GNICK2,(size_t)NICKLENGTH+10+3);
	strncpy(GNICK2,TNICK,(size_t)NICKLENGTH+10+3);

	strncpy(TPLAYER,GPLAYER1,(size_t)MAXSTRING); 
	strncpy(GPLAYER1,GPLAYER2,(size_t)MAXSTRING);
	strncpy(GPLAYER2,TPLAYER,(size_t)MAXSTRING);

	if(GPASS2[0] != (char)NULL)	/* Has the next player his own pass ? */
		strncpy(GPASS,GPASS2,(size_t)11);
	else
		strncpy(GPASS,PASS,(size_t)11);

	gnummoves++;
	
	if(gmoves != (char *)NULL)
		free(gmoves);

	gmoves=newmoves;

	i1=isMate();

	/* Warning ! Here are GPLAYER1 and GPLAYER2 already changed, cause its nedded for isMate, but gnomail is not changed ! */

	if(z1 != (char *)NULL) {
		if(gnomail2 != 1 || i1 == 1 || i1 == 3 || i1 == 4)
			mail(ID,GPLAYER1, GPLAYER2, GNICK1, GNICK2, GPASS, gmoves, GBOARD, z1, gnummoves,i1,GNICK2,(-1)*rightswap);
	} else {
		if(gnomail2 != 1 || i1 == 1 || i1 == 3 || i1 == 4)
			mail(ID,GPLAYER1, GPLAYER2, GNICK1, GNICK2, GPASS, gmoves, GBOARD, "", gnummoves,i1,GNICK2,(-1)*rightswap);
	}
	if(gnomail1 != 1 || i1 == 1 || i1 == 3 || i1 == 4)
		mail(ID,GPLAYER2, GPLAYER2, GNICK2, GNICK2, "", gmoves, GBOARD, "", gnummoves,i1,GNICK1,rightswap);

	if(z1 != (char *)NULL) {
		*z2=i1;
	}

	if(i1 == 1) {	/* is Mate */
		rewriteData(ID,"[MATE]",(char)NULL,(char)NULL,0);
	}

#ifndef DEBUG
	back2board(MOVEDFINISH,"",ID,swap);
#else
	printf("<a href=\"%s?ACTION=RESUME&ID=%s\">%s</a>\n",getenv("SCRIPT_NAME"),ID,MOVEDFINISH);
#endif

}

/* CHANGEMAIL and SETTINGS*/
void changemail(void) {
	char *z1,*z2,*z3,*z4;
	char ID[MAXSTRING+1];
	unsigned char uc1,uc2;
	int swap;

	z1=strstr(query,"ID=");
	if(z1 == (char *)NULL) {
		errorwin(ERRORDEFAULT,"","",0);
		return;
	}
	z1+=3;
	z2=z1;
	while((*z2 != '&') && (*z2 != (char)NULL))
		z2++;
	uc1=*z2;
	*z2=(char)NULL;
	strncpy(ID,z1,(size_t)MAXSTRING);
	if(readgamedata(ID)==-1)
		return;
	*z2=uc1;	

  /* Swap Colors */
  z1=strstr(query,"SWAP=");
  if(z1 != (char *)NULL) {
    z1+=5;
    z2=z1;
    while((*z2 != '&') && (*z2 != (char)NULL))
      z2++;
    uc1=*z2;
    *z2=(char)NULL;
    swap=atoi(z1);
    *z2=uc1;
  }
	
	z1=strstr(query,"PASS=");
	if(z1 == (char *)NULL) {
		errorwin(ERRORDEFAULT,"",ID,swap);
		return;
	}
	z1+=5;
	z2=z1;
	while((*z2 != '&') && (*z2 != (char)NULL))
		z2++;
	uc1=*z2;
	*z2=(char)NULL;
	if(strcmp(z1,GPASS) != 0) {
		errorwin(ERRORDEFAULT,"",ID,swap);
		return;
	}
	*z2=uc1;

	/* New Mail */
	z1=strstr(query,"MAIL1=");
	if(z1 == (char *)NULL) {
		errorwin(ERRORDEFAULT,GPASS,ID,swap);
		return;
	}
	z1+=6;
	z2=z1;
	while((*z2 != '&') && (*z2 != (char)NULL))
		z2++;
	uc1=*z2;
	*z2=(char)NULL;
		if(checkmail(z1) == 0) {
			rewriteEmail(ID,z1);
		} else {
			errorwin(ERRORMAILVALID,GPASS,ID,swap);
			return;
		}
	*z2=uc1;

	/* New and fixed Password */
	z1=strstr(query,"FIXPASS=");
	if(z1 != (char *)NULL) {
		z1+=8;
		z2=z1;
		while((*z2 != '&') && (*z2 != (char)NULL))
			z2++;
		uc1=*z2;
		*z2=(char)NULL;
		if(strlen(z1) > 10) {
			errorwin(ERRORPASSLONG,GPASS,ID,swap);
			return;
		}

		if(strlen(z1) <= 0) {
			globalpass=1;
			gnomail1=0;
			*z2=uc1;
		} else {
			*z2=uc1;
			z3=strstr(query,"FIXPASSA=");
			if(z3 != (char *)NULL) {
				z3+=9;
				z4=z3;
				while((*z4 != '&') && (*z4 != (char)NULL))
					z4++;
				uc2=*z4;
				*z4=(char)NULL;
				*z2=(char)NULL;

				if(strcmp(z1,z3) != 0) {
					errorwin(ERRORPASSMATCH,GPASS,ID,swap);
					return;
				}
				/* Passwords match, copy the first in GPASS */
				strncpy(GPASS,z1,(size_t)11);
				globalpass=0;
				*z2=uc1;
				*z4=uc2;
			}			


			/* No Mail - only if Passwort is set*/
			gnomail1=0;
			z1=strstr(query,"NOMAILTOME=");
			if(z1 != (char *)NULL) {
				z1+=11;
				z2=z1;
				while((*z2 != '&') && (*z2 != (char)NULL))
					z2++;
				uc1=*z2;
				*z2=(char)NULL;
				if(strcasecmp(z1,"on") == 0) {
					gnomail1=1;
				} 
				*z2=uc1;
			}
		}
		/* All Data to disk ! */
		rewriteData(ID,(char *)NULL,(char *)NULL,(char *)NULL,0);
	}
	back2board(SPECIALSETTINGSCHANGED,GPASS,ID,swap);
}

/* JOINGAME */
void joingame(void) {
	char *z1,*z2;
	char ID[MAXSTRING+1];
	unsigned char uc1;

	/* GameID */
	z1=strstr(query,"ID=");
	if(z1 == (char *)NULL) {
		errorwin(ERRORDEFAULT,"","",0);
		return;
	}
	z1+=3;
	z2=z1;
	while((*z2 != '&') && (*z2 != (char)NULL))
		z2++;
	uc1=*z2;
	*z2=(char)NULL;
	strncpy(ID,z1,(size_t)MAXSTRING);
	if(readgamedata(ID) == -1)
		return;
	*z2=uc1;

	printf("<table border=0 width=\"*\"><tr bgcolor=\"%s\"><td align=center>\n",BOARDMARGBG);
	printf("<font size=\"+1\" color=\"%s\"><b>%s</b></font>\n",BOARDMARGFG,STARTANEWGAME);
	printf("</td></tr><tr><td align=center>\n");

	if(gmessage != (char *)NULL) {
		z1=strchr(gmessage,'@');
		if(z1 != (char *)NULL) {
			printf("<b>%s: <font color=\"%s\">%s</font></b><br><br>\n",RESMESSAGE,MSGCOLOR,z1+1);
		}
	}

	printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
	printf("<INPUT TYPE=hidden name=ACTION value=NEW>\n");
	printf("<INPUT TYPE=hidden name=JOINID value=%s>\n",ID);
	printf("<INPUT TYPE=hidden name=WHITE value=1>\n",ID);
	printf("<TABLE BORDER=0><TR>\n");
	
	if(GPLAYER1[0] == (char)NULL) {
		printf("<TD>%s: </TD><TD><b>%s</b></TD>\n",STARTGAMENICK2,GNICK2);
		printf("</TR><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=NICK1 SIZE=%i></TD>\n",STARTGAMENICK1,NICKLENGTH);
		printf("</TR><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=MAIL1 SIZE=%i></TD>\n",STARTGAMEMAIL1,MAILLENGTH);
		printf("</TR><TR>\n");
		printf("<INPUT TYPE=hidden name=NICK2 value=%s>\n",GNICK2);
		printf("<INPUT TYPE=hidden name=MAIL2 value=%s>\n",GPLAYER2);
	} else {
		printf("<TD>%s: </TD><TD><b>%s</b></TD>\n",STARTGAMENICK1,GNICK1);
		printf("</TR><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=NICK2 SIZE=%i></TD>\n",STARTGAMENICK2,NICKLENGTH);
		printf("</TR><TR>\n");
		printf("<TD>%s: </TD><TD><INPUT TYPE=text NAME=MAIL2 SIZE=%i></TD>\n",STARTGAMEMAIL2,MAILLENGTH);
		printf("</TR><TR>\n");
		printf("<INPUT TYPE=hidden name=NICK1 value=%s>\n",GNICK1);
		printf("<INPUT TYPE=hidden name=MAIL1 value=%s>\n",GPLAYER1);
	}
	printf("</TR></TABLE><BR>\n");
	printf("%s: <INPUT TYPE=text NAME=MESSAGE size=\"%i\" value=\"\">\n",RESMESSAGE,MESSAGEBOXLEN);
	printf("<BR><BR><INPUT TYPE=submit value=\"%s\">\n",STARTNEW);
	printf("</FORM>\n");	

	printf("</td></tr></table>\n");
	
}

/* SENDGAMES */
void sendgames(void) {
	char *z1,*z2;
	unsigned char uc1;
	DIR *gamedir;
	struct dirent *direntry;
	FILE *pipe;
	char smail[MAXSTRING+1];
	int i1;
	int onlyweb;
	struct stat buf;
	char path[MAXSTRING+1];

	pipe=(FILE *)NULL;
	onlyweb=0;

	z1=strstr(query,"ONLYWEB=");
	if(z1 != (char *)NULL) {
		z1+=8;
		z2=z1;
		while((*z2 != '&') && (*z2 != (char)NULL))
			z2++;
		uc1=*z2;
		*z2=(char)NULL;
		if(*z1 != (char)NULL) {
			if(strcasecmp(z1,"on") == 0) onlyweb=1;
		}
		*z2=uc1;
	}

  z1=strstr(query,"MAIL1=");
  if(z1 == (char *)NULL) {
    errorwin(ERRORDEFAULT,"","",0);
    return;
  }
  z1+=6;
  z2=z1;
  while((*z2 != '&') && (*z2 != (char)NULL))
    z2++;
  uc1=*z2;
  *z2=(char)NULL;
	if(*z1 == (char)NULL) {
		errorwin(ERRORMISMAIL,"","",0);
		return;
	}
	if(checkmail(z1) != 0) {
		errorwin(ERRORMAILVALID,"","",0);
		return;
	}

	if(onlyweb != 1) {
		snprintf(smail,(size_t)MAXSTRING,"%s %s",SENDMAIL,z1);
	} else {
		printf("<br><b>%s:</b> %s<br><br>",ADMIN_GAMES_OF,z1);
		printf("<table border=0 cellspacing=0 cellpadding=0><tr bgcolor=\"%s\"><td><table cellpadding=3 cellspacing=3>",BOARDGRID);
		printf("<tr bgcolor=\"%s\"><td><font color=\"%s\"><b>%s</b></font></td><td align=\"center\"><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td></tr>",BOARDMARGBG,BOARDMARGFG,ADMIN_TABLE_START,BOARDMARGFG,ADMIN_NUM,BOARDMARGFG,ADMIN_AGAINST,BOARDMARGFG,ADMIN_TABLE_ACC,BOARDMARGFG,ADMIN_STATUS);
	}

	gamedir=opendir(DATAPATH);
	if(gamedir == (DIR *)NULL) {
		errorwin(ERRORGAMEDIR,"","",0);
		return;
	}
	direntry=readdir(gamedir);
	while(direntry != (struct dirent *)NULL) {
		if(*(direntry->d_name) != 'X') {
			if(isnumber(direntry->d_name)) {
				strncpy(path,DATAPATH,(size_t)MAXSTRING);
				strncat(path,"/",(size_t)MAXSTRING);
				strncat(path,direntry->d_name,(size_t)MAXSTRING);

				if(stat(path,&buf) == 0) {
 
				readgamedata(direntry->d_name);

#ifndef NOMAIL
				if(onlyweb != 1) {
					if(pipe == (FILE *)NULL) {
						if((strcasecmp(z1,GPLAYER1) == 0) || (strcasecmp(z1,GPLAYER2) == 0)) {
							pipe=popen(smail,"w");
							if(pipe == (FILE *)NULL) {
								errorwin(ERRORPIPE,"","",0);
								return;
							}
#ifdef CHARSET
  fprintf(pipe,"Mime-Version: 1.0\n");
  fprintf(pipe,"Content-Type: text/plain; charset=%s\n",CHARSET);
  fprintf(pipe,"Content-Transfer-Encoding: 8bit\n");
#endif
#ifdef NOREPLYADDRESS
							fprintf(pipe,"From: %s\n",NOREPLYADDRESS);
#else
							fprintf(pipe,"From: %s\n",z1);
#endif
							fprintf(pipe,"To: %s\n",z1);
							fprintf(pipe,"Subject: %s\n\n",SUBJECT);
							if(strcasecmp(z1,GPLAYER1) == 0)
								fprintf(pipe,"%s %s\n",MAILDEAR,GNICK1);
							else
								fprintf(pipe,"%s %s\n",MAILDEAR,GNICK2);
							fprintf(pipe,"%s:\n\n",SENDGAMESPLAY);
						}
					}
				}
#endif
				i1=isMate();
				if(strcasecmp(z1,GPLAYER1) == 0) {
#ifdef NOMAIL
					printf("Open Game (ID=%s)  <b>%s against %s</b> password:%s<br>\n",direntry->d_name,GNICK1,GNICK2,GPASS);
#else
					if(onlyweb != 1) {
						fputc('\n',pipe);
						fprintf(pipe,RESTITLE,GNICK1,GNICK2);
						fprintf(pipe,"\n%s: %s\n",RESID,direntry->d_name);
						fprintf(pipe,"%s: %s\n",ADMIN_TABLE_ACC,ctime(&buf.st_mtime));
						if(i1 == 0 || i1 == 2) {	// Check or "normal"
							fprintf(pipe,"%s %s\n\n",MAILMOVE,GPASS);
						} 
					} else {
						printf("<tr bgcolor=\"%s\"><td><font color=\"%s\">",BOARDMARGBG,BOARDMARGFG);
						printf("%c%c.%c%c.%c%c%c%c",direntry->d_name[6],direntry->d_name[7],direntry->d_name[4],direntry->d_name[5],direntry->d_name[0],direntry->d_name[1],direntry->d_name[2],direntry->d_name[3]);
						printf("</font></td><td align=\"center\"><font color=\"%s\">",BOARDMARGFG);
						printf("%i",gnummoves);
						printf("</font></td><td><font color=\"%s\">%s</font></td>",BOARDMARGFG,GNICK2);
						printf("<td><font color=\"%s\">%s</font></td>",BOARDMARGFG,ctime(&buf.st_mtime));
						printf("<td align=center><a href=\"http://%s%s?ID=%s&ACTION=RESUME\"><font color=\"%s\">",getenv("SERVER_NAME"),getenv("SCRIPT_NAME"),direntry->d_name,BOARDMARGFG);
						if(i1 == 0 || i1 == 2) {  // Check or "normal"
							printf("%s",ADMIN_STATUS_YOU);
						} else {
							printf("%s",ADMIN_STATUS_FIN);
						}
						printf("</font></a></td></tr>");
					}
#endif
				} else {
					if(strcasecmp(z1,GPLAYER2) == 0) {
#ifdef NOMAIL
						printf("Open Game (ID=%s)  %s against: %s<br>\n",direntry->d_name,GNICK2,GNICK1);
#else
						if(onlyweb != 1) {
							fputc('\n',pipe);
							fprintf(pipe,RESTITLE,GNICK1,GNICK2);
							fprintf(pipe,"\n%s: %s\n",RESID,direntry->d_name);
							fprintf(pipe,"%s: %s\n",ADMIN_TABLE_ACC,ctime(&buf.st_mtime));
						} else {
							printf("<tr bgcolor=\"%s\"><td><font color=\"%s\">",BOARDMARGBG,BOARDMARGFG);
							printf("%c%c.%c%c.%c%c%c%c",direntry->d_name[6],direntry->d_name[7],direntry->d_name[4],direntry->d_name[5],direntry->d_name[0],direntry->d_name[1],direntry->d_name[2],direntry->d_name[3]);
							printf("</font></td><td align=\"center\"><font color=\"%s\">",BOARDMARGFG);
							printf("%i",gnummoves);
							printf("</font></td><td><font color=\"%s\">%s</font></td>",BOARDMARGFG,GNICK1);
							printf("<td><font color=\"%s\">%s</font></td>",BOARDMARGFG,ctime(&buf.st_mtime));
							printf("<td align=center><a href=\"http://%s%s?ID=%s&ACTION=RESUME\"><font color=\"%s\">",getenv("SERVER_NAME"),getenv("SCRIPT_NAME"),direntry->d_name,BOARDMARGFG);
							if(i1 == 0 || i1 == 2) {  // Check or "normal"
								printf("%s",ADMIN_STATUS_NOT_YOU);
							} else {
								printf("%s",ADMIN_STATUS_FIN);
							}
							printf("</font></a></td></tr>");
						}
#endif
					} else {
						i1=0;		/* Don't send "Mail finished" */
					}
				} 
					
#ifndef NOMAIL
				if(onlyweb != 1) {
					if(pipe != (FILE *)NULL) {
						if(i1 == 1 || i1 == 3 || i1 == 4) {
							fprintf(pipe,"%s\n",MAILFINISHED);
						}
					}
				} 
#endif
			}

			}

		} 
		direntry=readdir(gamedir);
	}


  *z2=uc1;

	if(onlyweb != 1) {
		if(pipe != (FILE *)NULL) {
			fprintf(pipe,"\n\n%s: http://%s%s\n\n",MAILBOARD,getenv("SERVER_NAME"),getenv("SCRIPT_NAME"));
			fprintf(pipe,"%s\n\n",SENDGAMESBYE);	
			pclose(pipe);
			back2board(RESREQUESTSENT,"","",0);
		} else {
			errorwin(SENDGAMENOTFOUND,"","",0);
		}
	} else {
		printf("</table></td></tr></table>\n");
		printf("<br>[<a href=\"http://%s%s\">%s</a>]",getenv("SERVER_NAME"),getenv("SCRIPT_NAME"),SENDBACK);
	}

	return;
}

/* LISTGAMES */
void listgames(int onlylist) {
	struct dirent *direntry;
	DIR *gamedir;
	char path[MAXSTRING+1];
	struct stat buf;

	/* List games */
	gamedir=opendir(DATAPATH);
	if(gamedir == (DIR *)NULL) {
		printf("<b><font color=\"%s\">%s</font></b>",WARNCOLOR,ERRORGAMEDIR);
	}
	printf("<table border=0 cellpadding=0 cellspacing=0><tr bgcolor=\"%s\"><td><table cellpadding=3 cellspacing=3 border=0>",BOARDGRID);
	printf("<tr bgcolor=\"%s\"><td><font color=\"%s\"><b>%s</b></font></td><td align=\"center\"><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td><td><font color=\"%s\"><b>%s</b></font></td>",BOARDMARGBG,BOARDMARGFG,ADMIN_TABLE_START,BOARDMARGFG,ADMIN_NUM,BOARDMARGFG,ADMIN_TABLE_P1,BOARDMARGFG,ADMIN_TABLE_P2,BOARDMARGFG,ADMIN_TABLE_ACC);
	if(onlylist == 0) {
		printf("<td><font color=\"%s\"><b>%s</b></font></td>",BOARDMARGFG,ADMIN_TABLE_PW);
	}
#ifdef ALLOWREMOVE    
	if(onlylist == 0) {
		printf("<td>&nbsp;</td>");
	}
#endif
	printf("</tr>");
	direntry=readdir(gamedir);
	while(direntry != (struct dirent *)NULL) {
		if(isnumber(direntry->d_name)) {
			strncpy(path,DATAPATH,(size_t)MAXSTRING);
			strncat(path,"/",(size_t)MAXSTRING);
			strncat(path,direntry->d_name,(size_t)MAXSTRING);
			if(stat(path,&buf) == 0) {
				readgamedata(direntry->d_name);
				printf("<tr bgcolor=\"%s\"><td><font color=\"%s\">",BOARDMARGBG,BOARDMARGFG);
				if(direntry->d_name[0] != 'X')
					printf("<a style=\"color:%s\" target=\"_blank\" href=\"%s?ACTION=RESUME&ID=%s\">",BOARDMARGFG,getenv("SCRIPT_NAME"),direntry->d_name);
				else
					printf("<a style=\"color:%s\" target=\"_blank\" href=\"%s?ACTION=JOIN&ID=%s\">",BOARDMARGFG,getenv("SCRIPT_NAME"),direntry->d_name);

				if(direntry->d_name[0] == 'X')
					printf("%c%c.%c%c.%c%c%c%c",direntry->d_name[7],direntry->d_name[8],direntry->d_name[5],direntry->d_name[6],direntry->d_name[1],direntry->d_name[2],direntry->d_name[3],direntry->d_name[4]);
				else
					printf("%c%c.%c%c.%c%c%c%c",direntry->d_name[6],direntry->d_name[7],direntry->d_name[4],direntry->d_name[5],direntry->d_name[0],direntry->d_name[1],direntry->d_name[2],direntry->d_name[3]);
					printf("</a>");
				if(onlylist == 0) {
					printf("</font></td><td align=\"center\"><font color=\"%s\">%i</font></td><td><font color=\"%s\"><a style=\"color:%s\" href=\"mailto:%s\">%s</a></font></td><td><font color=\"%s\"><a style=\"color:%s\" href=\"mailto:%s\">%s</a></font></td><td><font color=\"%s\">%s</font></td><td><font color=\"%s\">%s</font></td>\n",BOARDMARGFG,gnummoves,BOARDMARGFG,BOARDMARGFG,GPLAYER1,GNICK1,BOARDMARGFG,BOARDMARGFG,GPLAYER2,GNICK2,BOARDMARGFG,ctime(&buf.st_mtime),BOARDMARGFG,GPASS);
				} else {
					printf("</font></td><td align=\"center\"><font color=\"%s\">%i</font></td><td><font color=\"%s\">%s</font></td><td><font color=\"%s\">%s</font></td><td><font color=\"%s\">%s</font></td>\n",BOARDMARGFG,gnummoves,BOARDMARGFG,GNICK1,BOARDMARGFG,GNICK2,BOARDMARGFG,ctime(&buf.st_mtime));
				}

#ifdef ALLOWREMOVE
			if(onlylist == 0) {
				printf("<td>");
				printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
				printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
				printf("<INPUT TYPE=hidden name=DELETE value=%s>\n",direntry->d_name);
				printf("<INPUT NAME=\"PASS3\" TYPE=\"password\" size=%i maxlength=%i>\n",ADMPASSLEN,ADMPASSLEN);
				printf("<input type=\"submit\" value=\"%s\">\n",ADMIN_DELETE);
				printf("</FORM>");
				printf("</td>");
			}
#endif
				printf("</tr>\n");
			}
		}
		direntry=readdir(gamedir);
	}
	printf("</table></td></tr></table></font>\n");
	printf("<br><b><a href=\"%s\">%s</a></b><br>\n",getenv("SCRIPT_NAME"),ADMIN_END);

}

#ifdef ADMIN

/* SAVEADMINPASS */
int saveAdminPass(char *pass1) {
	FILE *pass;
	char key[13];

	pass=fopen(ADMINPASS,"w");
	if(pass == (FILE *)NULL) {
		printf("<br><b><font color=\"%s\">%s</font></b>\n",WARNCOLOR,ADMIN_EOPASS);
		printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
		printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
		printf("<br><input type=\"submit\" value=\"%s\">\n",ADMIN_OK);
		printf("</FORM>\n");
		return(1);
	}

#ifdef CRYPT
	if(strlen(CRYPTKEY) == 2) {
		strcpy(key,CRYPTKEY);
		fputs((char *)crypt(pass1,key),pass);
	} else {
		if(strlen(CRYPTKEY) == 8) {
			strcpy(key,"$1$");
			strcat(key,CRYPTKEY);
			strcat(key,"$");
			fputs((char *)crypt(pass1,key),pass);
		} else {
			printf("<b><font color=\"%s\">%s</font></b><br>\n",WARNCOLOR,ADMIN_EKEY);
			fputs(pass1,pass);
		}
	}
#else
	fputs(pass1,pass);
#endif
	fclose(pass);
	chmod(ADMINPASS,(mode_t)S_IRUSR|S_IWUSR);
	printf("<b><font color=\"%s\">%s</font></b><br>",WARNCOLOR,ADMIN_PWSAVED);

return(0);
}


/* ADMIN */
void admin() {
	FILE *pass;
	unsigned u1;
	char *z1;
	char *z2;
	char pass1[ADMPASSLEN+1];
	char pass2[ADMPASSLEN+1];
	char pass3[ADMPASSLEN+1];	
	char key[13];
	char path[MAXSTRING+1];
	char savedpass[MAXSTRING+1];
	int passed;


	pass1[0]=(char)NULL;
	pass2[0]=(char)NULL;
	pass3[0]=(char)NULL;

	path[0]=(char)NULL;
	savedpass[0]=(char)NULL;

	passed=0;

  /* Read old prefs */
	z1=strstr(query,"PASS1=");
	if(z1!=(char *)NULL) {
		z1+=6;
		z2=z1;
		while((*z2 != (char)NULL) && (*z2 != '&')) {
			z2++;
		}
		u1=*z2;
		*z2=(char)NULL;
			strncpy(pass1,z1,ADMPASSLEN);
		*z2=u1;
	}

	z1=strstr(query,"PASS2=");
	if(z1!=(char *)NULL) {
		z1+=6;
		z2=z1;
		while((*z2 != (char)NULL) && (*z2 != '&')) {
			z2++;
		} 
		u1=*z2;
		*z2=(char)NULL;
		strncpy(pass2,z1,ADMPASSLEN);
		*z2=u1;
	}

	z1=strstr(query,"PASS3=");
	if(z1!=(char *)NULL) {
		z1+=6;
		z2=z1;
		while((*z2 != (char)NULL) && (*z2 != '&')) {
			z2++;
		}
		u1=*z2;
		*z2=(char)NULL;
		strncpy(pass3,z1,ADMPASSLEN);
		*z2=u1;
	}

	pass=fopen(ADMINPASS,"r");
	if(pass == (FILE *)NULL) {

		if((strncmp(pass1,pass2,ADMPASSLEN) == 0) && (pass1[0] != (char)NULL)) {
			printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">",BOARDGRID,BOARDMARGBG,BOARDMARGFG);
			if(saveAdminPass(pass1) == 1)
				return;
			printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
			printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
			printf("<INPUT TYPE=hidden name=PASS3 value=\"%s\">\n",pass1);
			printf("<br><input type=\"submit\" value=\"%s\">\n",ADMIN_OK);
			printf("</FORM>\n");
			printf("</font></td></tr></table></td></tr></table>\n");

		} else { 
			printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center>",BOARDGRID,BOARDMARGBG);
			if((strncmp(pass1,pass2,ADMPASSLEN) != 0) && (pass1[0] != (char)NULL)) {
				printf("<b><font color=\"%s\">%s</font></b><br>\n",WARNCOLOR,ADMIN_PASSDIFFER);
			}
			printf("<b><font color=\"%s\">%s</font></b><br><br>\n",BOARDMARGFG,ADMIN_SPECIFY_PASS);
			printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
			printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
			printf("<TABLE border=0>");
			printf("<tr><td>&nbsp;<b><font color=\"%s\">%s</font><b></td><td><input name=\"PASS1\" type=\"password\" size=%i maxlength=%i>&nbsp;</td></tr>\n",BOARDMARGFG,ADMIN_PASS,ADMPASSLEN,ADMPASSLEN);
			printf("<tr><td>&nbsp;<b><font color=\"%s\">%s</font><b></td><td><input name=\"PASS2\" type=\"password\" size=%i maxlength=%i>&nbsp;</td></tr>\n",BOARDMARGFG,ADMIN_PASS_RE,ADMPASSLEN,ADMPASSLEN);
			printf("</TABLE>\n");
			printf("<br><input type=\"submit\" value=\"%s\">\n",ADMIN_OK);
			printf("</FORM>");
			printf("</td></tr></table></td></tr></table>\n");
		}
	} else {

		if(pass3[0] == (char)NULL) {
			printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">",BOARDGRID,BOARDMARGBG,BOARDMARGFG);
			printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
			printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
			printf("&nbsp;<b>%s<b>&nbsp;<input name=\"PASS3\" type=\"password\" size=%i maxlength=%i>&nbsp;<br><br>\n",ADMIN_PASS,ADMPASSLEN,ADMPASSLEN);	
			printf("<input type=\"submit\" value=\"%s\"><br>\n",ADMIN_OK);
			printf("</FORM>");
			printf("</font></td></tr></table></td></tr></table>\n");
		}

		// hier muss bei Passwort-Aenderungen zusaetzlich nach dem aktuellen Passwort gefragt und dieses verglichen werden !

			if(pass3[0] != (char)NULL) {

				fgets(savedpass,(size_t)MAXSTRING,pass);

#ifdef CRYPT
     	 if(strlen(CRYPTKEY) == 2) {
      	  strcpy(key,CRYPTKEY);
					if(strcmp((char *)crypt(pass3,key),savedpass) == 0)
						passed=1;
	      } else {
  	      if(strlen(CRYPTKEY) == 8) {
    	      strcpy(key,"$1$");
      	    strcat(key,CRYPTKEY);
        	  strcat(key,"$");
						if(strcmp((char *)crypt(pass3,key),savedpass) == 0)
							passed=1;
	        } else {
  	        printf("<b><font color=\"%s\">%s</font></b><br>\n",WARNCOLOR,ADMIN_EKEY);
						if(strcmp(savedpass,pass3) == 0)
							passed=1;
        	}
	      }
#else
				if(strcmp(savedpass,pass3) == 0)
					passed=1;
#endif

				if(passed != 1) {
					printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">",BOARDGRID,BOARDMARGBG,BOARDMARGFG);
					printf("<b><font color=\"%s\">%s</font></b><br>\n",WARNCOLOR,ADMIN_WRONGPASS);
					printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
					printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
					printf("<br><input type=\"submit\" value=\"%s\">\n",ADMIN_OK);
					printf("</FORM>\n");
					printf("</font></td></tr></table></td></tr></table>\n");
				}

			}

		if(passed == 1) {

			/* Password changed ? */
			if(pass1[0] !=(char)NULL) {
				if(strcmp(pass1,pass2) == 0) {
					if(saveAdminPass(pass1) == 1)
						return;
				} else {
					printf("<b><font color=\"%s\">%s</font></b><br>\n",WARNCOLOR,ADMIN_PASSDIFFER);	
				}
			}

			/* Delete requested ? */
			z1=strstr(query,"DELETE=");
			if(z1!=(char *)NULL) {
				z1+=7;
				z2=z1;
				while((*z2 != (char)NULL) && (*z2 != '&')) {
					z2++;
				}
				u1=*z2;
				*z2=(char)NULL;

				if(isnumber(z1)) {
					strncpy(path,DATAPATH,(size_t)MAXSTRING);
					strncat(path,"/",(size_t)MAXSTRING);
					strncat(path,z1,(size_t)MAXSTRING);
#ifdef ALLOWREMOVE
					unlink(path);
#endif 
				}

				*z2=u1;
			}

			/* Pasword Change Fields */
			printf("<table border=0 bgcolor=\"%s\"><tr><td><table border=0 bgcolor=\"%s\"><tr><td align=center><font color=\"%s\">",BOARDGRID,BOARDMARGBG,BOARDMARGFG);
      printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
      printf("<INPUT TYPE=hidden name=ACTION value=ADMIN>\n");
			printf("<b>%s<b><input name=\"PASS3\" type=\"password\" size=%i maxlength=%i>\n",ADMIN_PASS_OLD,ADMPASSLEN,ADMPASSLEN);
      printf("&nbsp;<b>%s<b><input name=\"PASS1\" type=\"password\" size=%i maxlength=%i>\n",ADMIN_PASS,ADMPASSLEN,ADMPASSLEN);
      printf("&nbsp;<b>%s<b><input name=\"PASS2\" type=\"password\" size=%i maxlength=%i>\n",ADMIN_PASS_RE,ADMPASSLEN,ADMPASSLEN);
      printf("<input type=\"submit\" value=\"%s\">\n",ADMIN_OK);
      printf("</FORM>");
			printf("</font></td></tr></table></td></tr></table><br>\n");

			/* List games */
			listgames(0);

			fclose(pass);
			chmod(ADMINPASS,(mode_t)S_IRUSR|S_IWUSR);
		}
	}

}
#endif

/* VIEW */
void view(void) {
	char ID[MAXSTRING+1];
	unsigned char uc1;
	char *z1,*z2,*z3,*z4;
	int number;
	int i1,i2,i3,shpi;
	unsigned sl;
	char board[]="rnbqkbnrppppppppeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeePPPPPPPPRNBQKBNR";
	char *color;
	char *piece;
	char uc2;
	char *nextmove;
	int swap;

	number=-1;

	/* GameID */
	z1=strstr(query,"ID=");
	if(z1 == (char *)NULL) {
		errorwin(ERRORDEFAULT,"","",0);
		return;
	}
	z1+=3;
	z2=z1;
	while((*z2 != '&') && (*z2 != (char)NULL))
		z2++;
	uc1=*z2;
	*z2=(char)NULL;
	strncpy(ID,z1,(size_t)MAXSTRING);
	if(readgamedata(ID) == -1)
		return;
	*z2=uc1;

	/* Which Move */
	z1=strstr(query,"NUM=");
	if(z1 != (char *)NULL) {
		z1+=4;
		z2=z1;
		while((*z2 != '&') && (*z2 != (char)NULL))
			z2++;
		uc1=*z2;
		*z2=(char)NULL;
		number=atoi(z1);
		*z2=uc1;
	}

	if(number < 0) {
		z1=strstr(query,"NUM");
		if(z1 != (char *)NULL) {
			z1+=3;
			while((*z2 != '&') && (*z2 != '.') && (*z2 != (char)NULL))
				z2++;
			uc1=*z2;
			*z2=(char)NULL;
			number=atoi(z1);
			*z2=uc1;
		}
	}

	if(number < 0)
		number=0;

	/* Swap Colors */
	swap=1;
	z1=strstr(query,"SWAP=");
	if(z1 != (char *)NULL) {
		z1+=5;
		z2=z1;
		while((*z2 != '&') && (*z2 != (char)NULL))
			z2++;
		uc1=*z2;
		*z2=(char)NULL;
		swap=atoi(z1);
		*z2=uc1;
	}

	if((swap != 1) && (swap != -1))
		swap=1;

	sl=strlen(gmoves);
	z1=gmoves;
	for(i1=0; i1<number && i1<sl; i1++) {

		if(*(z1+5) != 'O' && strncmp(z1+1,"E8G8",4) != 0 && strncmp(z1+1,"E8C8",4) != 0 && strncmp(z1+1,"E1G1",4) != 0 && strncmp(z1+1,"E1C1",4) != 0) {	/* Normal move or Pawn Change */
			board[('8'-*(z1+2))*8+*(z1+1)-'A']='e';	/* Old field now empty */
			board[('8'-*(z1+4))*8+*(z1+3)-'A']=*(z1+5);
		}
		if(strncmp(z1+1,"E8G8",4) == 0) {
			board[4]='e';
			board[7]='e';
			board[6]='k';
			board[5]='r';
		}
		if(strncmp(z1+1,"E8C8",4) == 0) {
			board[4]='e';
			board[0]='e';
			board[2]='k';
			board[3]='r';
		}
		if(strncmp(z1+1,"E1G1",4) == 0) {
			board[60]='e';
			board[63]='e';
			board[62]='K';
			board[61]='R';
		}
		if(strncmp(z1+1,"E1C1",4) == 0) {
			board[60]='e';
			board[56]='e';
			board[58]='K';
			board[59]='R';
		}

		if(*(z1+5) == 'O') {	/* En Passant */
			board[('8'-*(z1+2))*8+*(z1+1)-'A']='e';
			if(*z1 == 'P')
				board[('8'-*(z1+4))*8+*(z1+3)-'A'+8] = 'e';
			else
				board[('8'-*(z1+4))*8+*(z1+3)-'A'-8] = 'e';
		}	
		
		z1+=7;
	}
	nextmove=z1;

	/* Now just display board */

	printf("<FONT size=\"+1\">");
	printf(RESTITLE,GNICK1,GNICK2);
	printf("</FONT><br>");

	i1=(gnummoves % 2); /* color of NICK1 */
	i2=(number % 2);	/* actual color */

	if(i2 == 1) {
		if(i1 == i2)
			printf(RESTURN,GNICK1,TBLACK);
		else
			printf(RESTURN,GNICK2,TBLACK);
	}	else {
		if(i1 == i2)
			printf(RESTURN,GNICK1,TWHITE);
		else
			printf(RESTURN,GNICK2,TWHITE);
	}

	printf("</FONT><br>");

	printf("<table border=0>\n");
	printf("<tr><td bgcolor=\"%s\">\n",BOARDGRID);
	printf("<table border=0 cellspacing=2>\n");
	printf("<tr><td bgcolor=\"%s\">&nbsp;</td>",BOARDMARGBG);
	if(swap==1) i1=0; else i1=7;
	for(;;i1+=swap) {
		if(swap==1 && i1>7) break;
		if(swap!=1 && i1<0) break;
	
		printf("<td align=\"center\" bgcolor=\"%s\"><font color=\"%s\">%c</font></td>",BOARDMARGBG,BOARDMARGFG,'A'+i1);
	}
	printf("<td bgcolor=\"%s\" align=center valign=center><a href=\"%s?ID=%s&ACTION=VIEW&SWAP=%i&NUM=%i\">",BOARDMARGBG,getenv("SCRIPT_NAME"),ID,swap*(-1),number);
	if(swap==1)
		printf("<img src=\"%s\" alt=\"%s\" border=0 title=\"%s\">",SWAP_ICON0,SWAP_TEXT0,SWAP_TEXT0_LONG);
	else
		printf("<img src=\"%s\" alt=\"%s\" border=0 title=\"%s\">",SWAP_ICON1,SWAP_TEXT1,SWAP_TEXT1_LONG);
	printf("</a></td></tr>");

	if(swap==1) i1=0; else i1=7;
	for(;;i1+=swap) {
		if(swap==1 && i1>7) break;
		if(swap!=1 && i1<0) break;
		printf("<tr height=\"%i\">\n",FIELDSIZE);
		printf("<td align=\"center\" bgcolor=\"%s\">&nbsp;<font color=\"%s\">%i</font>&nbsp;</td>\n",BOARDMARGBG,BOARDMARGFG,8-i1);
		if(swap==1) i2=0; else i2=7;
		for(;;i2+=swap) {
			if(swap==1 && i2>7) break;
			if(swap!=1 && i2<0) break;
			i3=board[i1*8+i2];
			if((i1 + i2) % 2) {
				color=BOARDBLACK;
			} else {
				color=BOARDWHITE;
			}
			printf("<td valign=\"center\" align=\"center\" width=\"%i\" height=\"%i\" bgcolor=\"%s\">",FIELDSIZE,FIELDSIZE,color);
			piecepointer(i3,&z2,&piece,&z1,&z3,&z4);
			if(myisupper(i3))
				shpi=toupper(*z2);
			else
				shpi=tolower(*z2);
			if((z1 == NULL) || (piece == NULL) || (color == NULL)) {
				printf("&nbsp; &nbsp;\n");
			} else {
				if((*(nextmove+1)=='A'+i2 && *(nextmove+2)=='8'-i1) || (*(nextmove+3)=='A'+i2 && *(nextmove+4)=='8'-i1)) {
					printf("<table border=0 bgcolor=\"%s\" cellspacing=3 cellpadding=0><tr><td bgcolor=\"%s\">\n",SELECTEDCOLOR,color);
					printf("<img border=0 src=\"%s\" alt=\"%c\" title=\"%s\">",z1,shpi,piece);
					printf("</td></tr></table>\n");
				} else {
					printf("<img border=0 src=\"%s\" alt=\"%c\" title=\"%s\">",z1,shpi,piece);
				}
			}
		}
			printf("<td align=\"center\" bgcolor=\"%s\">&nbsp;<font color=\"%s\">%i</font>&nbsp;</td>\n",BOARDMARGBG,BOARDMARGFG,8-i1);

			printf("</tr>\n");
		}
			printf("<tr><td bgcolor=\"%s\">&nbsp;</td>",BOARDMARGBG);
			if(swap==1) i1=0; else i1=7;
			for(;;i1+=swap) { 
				if(swap==1 && i1>7) break;
				if(swap!=1 && i1<0) break;
				printf("<td align=\"center\" bgcolor=\"%s\"><font color=\"%s\">%c</font></td>",BOARDMARGBG,BOARDMARGFG,'A'+i1);
			}
			printf("<td bgcolor=\"%s\">&nbsp;</td></tr>",BOARDMARGBG);
			printf("</table>\n");
			printf("</td>");
			printf("</tr></table>\n");

			printf("<table border=0><tr><td align=\"center\" valign=\"center\">");
			printf("<FORM action=\"%s\" method=post>\n",getenv("SCRIPT_NAME"));
			printf("<INPUT type=hidden name=ACTION value=VIEW>\n");
			printf("<INPUT type=hidden name=ID value=%s>\n",ID);
			printf("<INPUT type=hidden name=SWAP value=%i\n",swap);
			if(number > 0) {
				printf("<input type=image align=center src=\"%s\" alt=\"%s\" border=0 name=NUM%i >\n",ARROW_LL,ARROW_T_LL,0);
				printf("<input type=image align=center src=\"%s\" alt=\"%s\" border=0 name=NUM%i >\n",ARROW_L,ARROW_T_L,number-1);
			}
			printf("&nbsp;<font size=\"+1\">%i</font>&nbsp;\n",number+1);
			if(number < gnummoves) {
				printf("<input type=image align=center src=\"%s\" alt=\"%s\" border=0 name=NUM%i >\n",ARROW_R,ARROW_T_R,number+1);
				printf("<input type=image align=center src=\"%s\" alt=\"%s\" border=0 name=NUM%i >\n",ARROW_RR,ARROW_T_RR,gnummoves);
			}
			printf("</FORM>");
			printf("</td></tr></table>\n");
}

/* MAIN */
int main(void) {
	int cl;
	GPLAYER1[0]=(char)NULL;
	GPLAYER2[0]=(char)NULL;
	GNICK1[0]=(char)NULL;
	GNICK2[0]=(char)NULL;
	GBOARD[0]=(char)NULL;
	GPASS[0]=(char)NULL;
	gmoves=(char *)NULL;
	gnummoves=0;

	if(getenv("CONTENT_LENGTH") != (char *)NULL) {	/* Method: Post */
		cl=atoi(getenv("CONTENT_LENGTH"));
		query=(char *)malloc(cl+1);		/* On Error the Query String is empty, CALL IT FROM BROWSER-Error... so what ! */
		if(query != (char *)NULL) {
			fgets(query,cl+1,stdin);
		}
	}	else {	/* Method: Get */
		cl=0;
		query=getenv("QUERY_STRING");
	}

	if(query == (char *)NULL) {
		puts(DEFTITLE);
		puts(DEFURL);
		putchar('\n');
		puts(CALLITFROMBROWSER);
		putchar('\n');
		return(-1);
	}

	query2String(query);

	header();

	if(query[0]==(char)NULL) start();
	else {
		if(strstr(query,"ACTION=NEW") != (char *)NULL) {
			newgame();
		} else {
			if(strstr(query,"ACTION=RESUME") != (char *)NULL) {
				resumegame(0);
			} else {
				if(strstr(query,"ACTION=MOVED") != (char *)NULL) {
					piecemoved();
				} else {
					if(strstr(query,"ACTION=CHMAIL") != (char *)NULL) {
						changemail();
					} else {
						if(strstr(query,"ACTION=JOIN") != (char *)NULL) {
							joingame();
						} else {
							if(strstr(query,"ACTION=SENDGAMES") != (char *)NULL) {
								sendgames();
							} else {
								if(strstr(query,"ACTION=VIEW") != (char *)NULL) {
									view();
								} else {
#ifdef ADMIN
									if(strstr(query,"ACTION=ADMIN") != (char *)NULL) {
										admin();
									} else {
#endif
#ifdef ENABLELIST
										if(strstr(query,"ACTION=LIST") != (char *)NULL) {
											listgames(1);
										} else {
#endif
											start();
#ifdef ENABLELIST
										}
#endif
#ifdef ADMIN
									}
#endif
								}
							}
						}
					}
				}
			}
		}
	}

	if(gmoves != (char *)NULL)
		free(gmoves);
	if(gmessage != (char *)NULL)
		free(gmessage);
	if(cl > 0 && query != (char *)NULL) 
		free(query);
	
	footer();

}
