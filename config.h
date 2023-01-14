/*========*/
/* Pathes */
/*========*/

/* URL-Prefix for the images */
#define IMGURLPREFIX "/figures/"
/* local path to game dir. On some systems this this has to be an absolute path. */
/* make sure that the web-server has proper rights to write and read this directory.*/
#define DATAPATH "games"
/* path to the administrators password, same restrictions as for DATAPATH */
#define ADMINPASS "games/adm.pss"

/*=================*/
/* program options */
/*=================*/

/* path to your mail program. It should recognize the mail on stdin and */
/* the emailaddress as first commandline argument. */
#define SENDMAIL "/usr/sbin/sendmail"

/* to avoid identical from- and to- headers when sending a list
   of all games, you can set another from-address here */
#undef NOREPLYADDRESS

/* the subject prefix for all mails */
#define SUBJECT "[W3Chess]"

/* undef next line to get Moves List from 1 to n */
#define REVERSEMOVELIST

/* some security issues: Remove old games or not */
#define ALLOWREMOVE 

/* Delete old games after... days */
#define DELETEAFTER 30

/* Own Header and footer (optional) */
/* if these pathes (sames restrictions as DATAPATH) are set, */
/* the content is used as header/footer for each page resp. */
/* as whole defaultpage (when called with no arguments) */
/* You should put HTML-Code in this files. */
#define HTML_HEADER "header"
#define HTML_FOOTER "footer"
#define HTML_DEFPAGE "defaultpage"

/* include Admin-Mode or not, undef to not include admin-mode */
#define ADMIN 

/* define next line to have the list all games option */
/* to use call script as w3chess.cgi?ACTION=LIST then */
#undef ENABLELIST

/* use libcrypt to crypt the admin pass, you should use it !
   If your systems hast no libcrypt installed, undef this line
   and edit the Makefile ! */ 
#define CRYPT
/* Which crypt-key to use, this could be a 2-character or a 8-character long
   string. The 8-character long strings works only on glib2-based systems ! */
#define CRYPTKEY "tm"

