/* charset */
#define CHARSET "iso-8859-1"

/* global */
#define CALLITFROMBROWSER "NO deber�as ejecutar este programa directamente !"

/* start */
#define STARTGAMENICK1 "Apodo del jugador 1"
#define STARTGAMENICK2 "Apodo del jugador 2"
#define STARTGAMELEAVEBLANK "Deja el segundo jugador en blanco si no a�n no est� establecido."
#define STARTGAMEMAIL1 "Email del jugador 1"
#define STARTGAMEMAIL2 "Email del jugador 2"
#define STARTHASWHITE "Este jugador jugar� con blancas"
#define STARTNEW "Comenzar juego"
#define STARTGAMEID "ID del juego"
#define STARTRESUME "Continuar juego"
#define STARTFINISH "El juego se ha iniciado. �Verifica tu casilla de correo!"
#define STARTWAIT "Ahora debes esperar que juegue tu oponente..."
#define STARTSEARCH "Unirse a uno de los siguientes juegos"
#define STARTNUMBERGAMES "juegos"
#define STARTNUMBEROGAMES "juegos abiertos"
#define STARTANEWGAME "Comenzar nuevo juego"
#define STARTRESUMEAGAME "Continuar juego"
#define STARTSENDMYGAMES "Enviarme mis juegos"
#define STARTMYMAIL "Mi direcci�n de email"
#define STARTSEND "Enviar"
#define STARTSENDNOMAIL "Mostrar mis juegos en lugar de enviarlos"

/* mail */
#define MAILDEAR "Estimado"
#define MAILMOVE "Es tu turno ahora. Tu password es"
#define MAILURLRESUME "Abre el siguiente enlace para continuar el juego"
#define MAILURLVIEW "Abre el siguiente enlace para ver el juego"
#define MAILDONE "Tu movida est� realizada."
#define MAILINVITE "�Has sido invitado a un partido de ajedrez!"
#define MAILID "La ID del juego es"
#define MAILBOARD "El tablero est� ubicado en"
#define MAILWINNER "��� Ganaste el juego !!!"
#define MAILOPCHECK "�Tu oponente est� en jaque!"
#define MAILPASS "�Tu password te ha sido enviado por email!"
#define MAILREMIS "�Se ha ofrecido tablas!"
#define MAILISREMIS "�Tablas!"
#define MAILGIVESUP "�%s se ha rendido!"
#define MAILHIT "Toma"
#define MAILFINISHED "�Este juego est� finalizado!"
#define MAILAGAINST "contra"
#define MAILYOUWHITE "Tienes las piezas blancas (may�sculas)"
#define MAILYOUBLACK "Tienes las piezas negras (min�sculas)"

/* resume */
#define RESTITLE "Partido: %s contra %s"
#define RESTURN "�Es el turno de %s con %s!"
#define RESTURNR "�Es tu turno, %s!"
#define RESLAST "La �ltima movida fue"
#define RESPASS "Ingresa el password enviado"
#define RESMOVES "Movidas hasta ahora"
#define RESCHANGE "�Selecciona una pieza para cambiar por el pe�n movido!"
#define RESMESSAGE "Mensaje"
#define RESREQUESTSENT "�Tu solicitud ha sido enviada!"
#define RESREMREQ "�Se ha ofrecido tablas!"
#define RESMAILOF "Direcci�n de email de %s"
#define RESID "ID del juego"
#define RESSTART "Juego comenzado"
#define RESFIXPASS "Password establecido"
#define RESFIXPASSA "Vuelve a tipear el password establecido"
#define RESPASSWARN "Recordatorio: �ESTA APLICACI�N NO ES SEGURA!<br>Cualquiera puede obtener tu password con facilidad.<br>�No utilices tus passwords comunes!"
#define RESNOMAILTOME "No quiero recibir emails durante el juego."

/* moved */
#define MOVEDFINISH "Tu movida fue enviada. Haz clic aqu� para volver al tablero."
#define MOVECANC "�Tu movida fue cancelada!"

/* sendgames */
#define SENDGAMESPLAY "Estas participando de los siguientes juegos"
#define SENDGAMESBYE "�Que tengas un buen d�a!"
#define SENDGAMESBACK "�Tus juegos han sido enviados a tu direcci�n de email!"
#define SENDGAMENOTFOUND "�No hay juegos asociados a esta direcci�n de email!"
#define SENDBACK "Volver a la entrada"

/* errors */
#define ERRORDEFAULT "Error"
#define ERRORCREATE "Imposible crear archivo"
#define ERROROPEN "Imposible abrir archivo"
#define ERRORPIPE "Imposible abrir tubo"
#define ERRORID "�El juego solicitado no existe!"
#define ERRORMEM "Sin memoria"
#define ERRORMOV "Error al mover pieza"
#define ERRORPASS "�Password incorrecto!"
#define ERRORGAMEDIR "�Imposible abrir directorio de juegos!"
#define ERRORGAMECOR "�Los datos del juego est�n corruptos!"
#define ERRORMAILVALID "�Direcci�n de email inv�lida!"
#define ERRORMISMAIL "�Direcci�n de email ausente!"
#define ERRORMISNICK "�Apodo ausente!"
#define ERRORNICKLONG "�El apodo es demasiado largo!"
#define ERRORMAILLONG "�La direcci�n de email es demasiado larga!"
#define ERRORPASSLONG "�El password es demasiado largo!"
#define ERROROLDBOARD "Tu tablero muestra una jugada antigua... �Debes recargar el tablero!"
#define ERRORILLEGAL "�Movida ilegal!"
#define ERRORNODP "�No se permiten : en el apodo!"
#define ERRORPASSMATCH "�Los passwords no coinciden!"

/* pieces */
#define TLONGR "Torre"
#define TLONGP "Pe�n"
#define TLONGN "Caballo"
#define TLONGK "Rey"
#define TLONGQ "Dama"
#define TLONGB "Alfil"
#define TEMPTY ""

#define TWHITE "blancas"
#define TBLACK "negras"

#define TSHORTR "T"
#define TSHORTP "P"
#define TSHORTN "C"
#define TSHORTK "R"
#define TSHORTQ "D"
#define TSHORTB "A"
#define TSHORTE " "

/* Special Moves */
#define SMCKS "Enroque hacia el lado del rey"
#define SMCQS	"Enroque hacia el lado de la reina"
#define SMEP "Al Paso"
#define MATE "Mate"
#define CHECK "Jaque"

/* Special Actions */
#define SPECIALRESENT "Reenviar password"
#define SPECIALGIVEUP "Rendirse"
#define SPECIALREMIS "Ofrecer tablas"
#define SPECIALSETTINGS "Opciones"
#define SPECIALDO "Hacerlo"
#define SPECIALSETTINGSCHANGED "�Tus opciones han sido guardadas!"
#define SPECIALLOGOUT "Salir"

/* Messages */
#define MSGOK "Ok"
#define MSGYES "Si"
#define MSGNO "No"
#define MSGACCEPT "Aceptar"
#define MSGDIS "Declinar"

/* Admin Mode and Games List */
#define ADMIN_SPECIFY_PASS "�Debes especificar un password!"
#define ADMIN_PASS_OLD "Password antiguo"
#define ADMIN_PASS "Password"
#define ADMIN_PASS_RE "Vuelve a tipear el password"
#define ADMIN_OK "Ok"
#define ADMIN_EOPASS "Imposible acceder al archivo de passwords !"
#define ADMIN_EKEY	"�Clave de encripci�n equivocada, encripci�n desactivada!"
#define ADMIN_PASSDIFFER "�Los passwords difieren!"
#define ADMIN_TABLE_START "Comienzo"
#define ADMIN_TABLE_ACC "�ltimo acceso"
#define ADMIN_TABLE_P1 "Jugador 1"
#define ADMIN_TABLE_P2 "Jugador 2"
#define ADMIN_TABLE_PW "Password"
#define ADMIN_DELETE "Eliminar"
#define ADMIN_END "Volver a la entrada"
#define ADMIN_PWSAVED "�Password almacenado!"
#define ADMIN_WRONGPASS "�Password incorrecto!"
#define ADMIN_NUM " # "
#define ADMIN_AGAINST "Contra"
#define ADMIN_STATUS "Status"
#define ADMIN_STATUS_YOU "Mover"
#define ADMIN_STATUS_NOT_YOU "Esperar"
#define ADMIN_STATUS_FIN "Finalizado"
#define ADMIN_GAMES_OF "Juegos de"

/* Arrows */
#define ARROW_T_L "Izquiera"
#define ARROW_T_R "Derecha"
#define ARROW_T_LL "Comienzo"
#define ARROW_T_RR "Fin"

/* other icons */
#define SWAP_TEXT0 "bn"
#define SWAP_TEXT1 "nb"
#define REDFRAME_TEXT "R"
#define SWAP_TEXT0_LONG "Blancas arriba"
#define SWAP_TEXT1_LONG "Negras arriba"
#define REDFRAME_TEXT_LONG "Mostrar todas la movidas posibles"
