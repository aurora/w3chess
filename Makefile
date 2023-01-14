# if you don't want to use libcrypt, uncomment this line
# and undef CRYPT in config.h !
CRYPT=-lcrypt

DATE=`date +"%Y%m%d-%H%M%S"`

all: w3chess.cgi 

w3chess.cgi: w3chess.c config.h lang.h defaults.h
	@echo "==========================================="
	@echo " I hope you have edited config.h before ?! "
	@echo "==========================================="
	gcc -o w3chess.cgi ${CRYPT} w3chess.c
	strip w3chess.cgi
	chmod a+rx w3chess.cgi
	@echo "=========================================="
	@echo " Don't forget to click the Pi-Sign... !!! "
	@echo "=========================================="

clean:
	rm w3chess*.cgi

install:
	@echo see README.install
