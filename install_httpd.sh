#!/bin/sh
echo ""
echo "+-----------------------------------------------------------------------+"
echo "|  Install Cloud PBX API vs Asterisk 13 on Centos 7                     |"
echo "|  Database: MongoDB 4.4                                                |"
echo "|  PHP: 7.3                                                             |"
echo "|  By ZetAdmin Framework                                                |"
echo "|  NOTE: Only apply installation at cloud server or cloud VPS services  |"
echo "+-----------------------------------------------------------------------+"

IP=$(hostname -I)

##### Installation #####
while [ "$q_install" != "y" ] && [ "$q_install" != "n" ] ;do
    echo
	echo -n "Press y to start installation, n to cancel ? [y/n]: "
	read q_install
done
if [ "$q_install" != "y" ];then 
	echo
	exit
fi

###################################
#         Configuration           #
###################################

echo -n "Please enter domain name for PBX: ";
read pbx_domain
echo -n "Please enter name for PBX: ";
read pbx_name

sudo yum -y install bind-utils wget zip unzip

IPDATA=$(dig +short $pbx_domain)
if grep -q $IP <<< $IPDATA; then
	echo -e "\033[32mGood!\033[m Ready to install."
	echo ' '
else
	echo -e "\033[31mError!\033[m Please point your domain name "$pbx_domain" to IP "$IP""
	exit
fi

sleep 0.5
sudo setenforce 0
sudo sed -i 's/\(^SELINUX=\).*/\SELINUX=permissive/' /etc/selinux/config
sudo yum -y groupinstall "Development Tools"
sudo yum -y install epel-release
sudo yum -y install gcc make gcc-c++ cpp git openssl-devel m4 autoconf automake vim net-tools sqlite-devel psmisc ncurses-devel libtermcap-devel newt-devel libxml2-devel libtiff-devel gtk2-devel libtool libuuid-devel subversion kernel-devel kernel-devel-$(uname -r) crontabs cronie-anacron libedit libedit-devel libsrtp libsrtp-devel

sleep 0.5

echo " "
echo "+------------------------------------+"
echo "|  Install Tool for Text to speech   |"
echo "+------------------------------------+"
echo " "
sudo wget http://mirror.centos.org/centos/7/os/x86_64/Packages/sox-14.4.1-7.el7.x86_64.rpm
sudo yum -y install sox-14.4.1-7.el7.x86_64.rpm

sudo yum -y install mpg123

sudo yum -y localinstall --nogpgcheck https://download1.rpmfusion.org/free/el/rpmfusion-free-release-7.noarch.rpm
sudo yum -y install ffmpeg ffmpeg-devel

echo -e "\033[32mInstall Tool for Text to speech successful!\033[m"
echo " "
sleep 0.5

echo " "
echo "+------------------------------------+"
echo "|      Install jansson               |"
echo "+------------------------------------+"
echo " "

cd /usr/src/
git clone https://github.com/akheron/jansson.git
cd jansson
autoreconf  -i
./configure --prefix=/usr/
make && make install
cd ~
echo -e "\033[32mInstall jansson successful!\033[m"
echo " "

sleep 0.5

echo " "
echo "+------------------------------------+"
echo "|      Install pjproject             |"
echo "+------------------------------------+"
echo " "

cd /usr/src/
git clone https://github.com/pjsip/pjproject.git
cd pjproject
./configure CFLAGS="-DNDEBUG -DPJ_HAS_IPV6=1" --prefix=/usr --libdir=/usr/lib64 --enable-shared --disable-video --disable-sound --disable-opencore-amr
make dep
make
sudo make install
sudo ldconfig
cd ~
rm -rf /usr/local/pjproject/

echo -e "\033[32mInstall pjproject successful!\033[m"
echo " "

sleep 0.5

echo " "
echo "+------------------------------------+"
echo "|      Install libsrtp               |"
echo "+------------------------------------+"
echo " "

cd /usr/local
git clone https://github.com/cisco/libsrtp.git
cd libsrtp
./configure CFLAGS=-fPIC --libdir=/usr/lib64
make && make install
cd ~
rm -rf /usr/local/libsrtp/

echo -e "\033[32mInstall libsrtp successful!\033[m"
echo " "
sleep 0.5
echo " "
echo "+------------------------------------+"
echo "|      Install mongo-c-driver        |"
echo "+------------------------------------+"
echo " "

cd /usr/src/
wget https://github.com/mongodb/mongo-c-driver/releases/download/1.6.2/mongo-c-driver-1.6.2.tar.gz
tar xzf mongo-c-driver-1.6.2.tar.gz
cd mongo-c-driver-1.6.2
./configure --disable-automatic-init-and-cleanup
make
sudo make install
cd ~
rm -rf /usr/src/mongo-c-driver-1.6.2.tar.gz

echo -e "\033[32mInstall mongo-c-driver successful!\033[m"
echo " "

sleep 0.5

echo " "
echo "+------------------------------------+"
echo "|      Install Asterisk-13           |"
echo "+------------------------------------+"
echo " "
file_name=$(openssl rand -hex 16)
cd /usr/src/
wget https://downloads.asterisk.org/pub/telephony/asterisk/releases/asterisk-13.38.3.tar.gz
tar xvfz asterisk-13.38.3.tar.gz
cd asterisk-13*/
echo '.'$file_name >> .version
./configure --libdir=/usr/lib64
./configure --with-jansson-bundled
make && make install
make samples && make config && ldconfig 

sudo mkdir /etc/asterisk/keys
cd /usr/src/asterisk-13*/
contrib/scripts/ast_tls_cert -C $pbx_domain -O "$pbx_name" -d /etc/asterisk/keys
rm -rf /usr/src/asterisk-13.38.3.tar.gz
cd ~

groupadd asterisk
useradd -r -d /var/lib/asterisk -g asterisk asterisk
usermod -aG audio,dialout asterisk
chown -R asterisk.asterisk /etc/asterisk
chown -R asterisk.asterisk /var/{lib,log,spool}/asterisk
chmod 777 /run/asterisk/
sudo systemctl start asterisk
/sbin/chkconfig asterisk on

sudo firewall-cmd --zone=public --add-service={sip,sips} --permanent
sudo firewall-cmd --zone=public --add-port=8089/tcp --permanent
sudo firewall-cmd --zone=public --add-port=10000-20000/udp --permanent 
sudo firewall-cmd --zone=public --add-service={http,https} --permanent
sudo firewall-cmd --reload
echo -e "\033[32mInstall Asterisk successful!\033[m"
echo " "

sleep 0.5

echo " "
echo "+-------------------------------------------+"
echo "|   Install Webserver Apache                |"
echo "+-------------------------------------------+"
echo " "

sudo yum -y install epel-release yum-utils
sudo yum -y install http://rpms.remirepo.net/enterprise/remi-release-7.rpm
sudo yum-config-manager --enable remi-php73

sudo yum -y install zip unzip kernel-headers.x86_64 bzip2-devel libjpeg-devel libpng-devel freetype-devel openldap-devel postgresql-devel aspell-devel net-snmp-devel libxslt-devel libc-client-devel libicu-devel gmp-devel curl-devel libmcrypt libmcrypt-devel pcre-devel enchant-devel libXpm-devel mysql-devel readline-devel recode-devel libtidy-devel libtool-ltdl-devel
sudo yum -y install php php-common php-opcache php-mcrypt php-cli php-gd php-curl php-mysql php-dba php-dbg php-devel php-enchant php-gd php-gmp php-imap php-intl php-json php-ldap php-mbstring php-mysqlnd php-odbc php-opcache php-pdo php-pear php-pecl-apcu php-pecl-apcu-devel php-pecl-igbinary php-pecl-igbinary-devel php-pecl-mcrypt php-pecl-memcached php-pecl-msgpack php-pecl-msgpack-devel php-pecl-redis php-pgsql php-process php-pspell php-recode php-snmp php-soap php-xml php-xmlrpc php-zip php-pear

sudo systemctl start httpd
echo -e "\033[32mInstall Webserver Apache successful!\033[m"
echo " "

sleep 0.5

echo " "
echo "+-------------------------------------------+"
echo "|   Install MongoDB Server                  |"
echo "+-------------------------------------------+"
echo " "

if [ ! -d "/etc/yum.repos.d/mongodb.repo" ] 
then
    mongodb_repo=/etc/yum.repos.d/mongodb.repo
	#rm -rf $mongodb_repo
	touch $mongodb_repo
	echo '[MongoDB]' >> $mongodb_repo
	echo 'name=MongoDB Repository' >> $mongodb_repo
	echo 'baseurl=https://repo.mongodb.org/yum/redhat/$releasever/mongodb-org/4.4/x86_64/' >> $mongodb_repo
	echo 'gpgcheck=1' >> $mongodb_repo
	echo 'enabled=1' >> $mongodb_repo
	echo 'gpgkey=https://www.mongodb.org/static/pgp/server-4.4.asc' >> $mongodb_repo
fi
sudo yum install -y mongodb-org
sudo firewall-cmd --zone=public --add-port=27017/tcp --permanent
sudo firewall-cmd --reload
sudo systemctl start mongod.service
sudo systemctl enable mongod.service

curl http://dl.fedoraproject.org/pub/epel/7/x86_64/Packages/r/re2c-0.14.3-2.el7.x86_64.rpm --output re2c-0.14.3-2.el7.x86_64.rpm
rpm -Uvh re2c-0.14.3-2.el7.x86_64.rpm

sudo yum -y install mod_ssl openssh
sudo pecl install mongodb
chmod 755 /usr/lib64/php/modules/mongodb.so
echo "extension=mongodb.so" >> /etc/php.ini

mongodb_createdb=/usr/src/createdb.js
mongodb_dbuser=pbx
mongodb_dbname=pbxdb
mongodb_dbpwd=$(openssl rand -hex 12)
echo 'use pbxdb;' >> $mongodb_createdb
echo 'db.createUser({user: "'$mongodb_dbuser'", pwd: "'$mongodb_dbpwd'", roles: [ { role: "readWrite", db: "'$mongodb_dbname'" } ]});' >> $mongodb_createdb
mongo < $mongodb_createdb
rm -rf $mongodb_createdb
echo -e "\033[32mInstall MongoDB Server successful!\033[m"
echo " "

webroot=/var/www/html/$pbx_domain

sleep 0.5

echo " "
echo "+-------------------------------------------+"
echo "|   Create Apache VirtualHost        |"
echo "+-------------------------------------------+"
echo " "

echo "apache ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
sudo mkdir $webroot/
sudo mkdir $webroot/logs/
vh_conf=/etc/httpd/conf.d/$pbx_domain.conf
rm -rf $vh_conf

touch $vh_conf
echo '<VirtualHost *:80>' >> $vh_conf
echo '    ServerName '$pbx_domain'' >> $vh_conf
echo '    DocumentRoot /var/www/html/'$pbx_domain'/' >> $vh_conf
echo '    ErrorLog /var/www/html/'$pbx_domain'/logs/error-80.log' >> $vh_conf
echo '</VirtualHost>' >> $vh_conf
echo ' ' >> $vh_conf

cd $webroot/
touch index.html
echo 'pageok!' >> index.html
cd ~
sudo chown -R apache:apache $webroot/
sudo systemctl restart httpd
echo -e "\033[32mCreate VirtualHost successful!\033[m"
echo " "

sleep 0.5

echo " "
echo "+-------------------------------------------+"
echo "|   Install Let's Encrypt                   |"
echo "+-------------------------------------------+"
echo " "

yum install -y certbot
certbot certonly --webroot -w $webroot/ --agree-tos -m admin@$pbx_domain -d $pbx_domain

echo '<VirtualHost *:443>' >> $vh_conf
echo '    ServerName '$pbx_domain'' >> $vh_conf
echo '    DocumentRoot /var/www/html/'$pbx_domain'/' >> $vh_conf
echo '    ErrorLog /var/www/html/'$pbx_domain'/logs/error-443.log' >> $vh_conf
echo '    SSLEngine on' >> $vh_conf
echo '    SSLCertificateFile /etc/letsencrypt/live/'$pbx_domain'/fullchain.pem' >> $vh_conf
echo '    SSLCertificateKeyFile /etc/letsencrypt/live/'$pbx_domain'/privkey.pem' >> $vh_conf
echo '</VirtualHost>' >> $vh_conf

sudo systemctl restart httpd
echo -e "\033[32mInstall Let's Encrypt successful!\033[m"
echo " "

sleep 0.5

echo " "
echo "+-------------------------------------------+"
echo "|   Create Cloud PBX API                    |"
echo "+-------------------------------------------+"
echo " "

sudo mkdir $webroot/api/
touch $webroot/api/index.html
echo 'pageok!' >> $webroot/api/index.html

zetadmin_pbx_dir=$(find ~ -type d -name "zetadmin_pbx")
sudo cp -R $zetadmin_pbx_dir $webroot/api/

echo -e "\033[32mDownload and export API source code successful!\033[m"
echo " "
sleep 0.5

web_api_key=$(openssl rand -hex 24)
api_conf=api/zetadmin_pbx/config.php
sudo touch $webroot/$api_conf
echo '<?php' >> $webroot/$api_conf
echo '$api_url = "https://'$pbx_domain'/api/zetadmin_pbx/postback.php";' >> $webroot/$api_conf
echo '$api_key = "'$web_api_key'";' >> $webroot/$api_conf
echo '$ipsAlow = []; /*exp ["ip 1", "ip 2", "ip n"]*/' >> $webroot/$api_conf
echo ' ' >> $webroot/$api_conf
echo '$db_url = "mongodb://'$mongodb_dbuser':'$mongodb_dbpwd'@127.0.0.1:27017/'$mongodb_dbname'";' >> $webroot/$api_conf
echo '$db_name = "'$mongodb_dbname'";' >> $webroot/$api_conf
echo '?>' >> $webroot/$api_conf

sleep 0.5

asterisk_etc=/etc/asterisk

sudo touch $asterisk_etc/manager_api.conf
max_api_user=256
for (( i=1; i <= $max_api_user; ++i ))
do
	echo '' >> $asterisk_etc/manager_api.conf
    echo '[auto_call_api_'$i']' >> $asterisk_etc/manager_api.conf
	echo 'secret = auto_call_api' >> $asterisk_etc/manager_api.conf
	echo 'deny=0.0.0.0/0.0.0.0' >> $asterisk_etc/manager_api.conf
	echo 'permit=127.0.0.1/255.255.255.0' >> $asterisk_etc/manager_api.conf
	echo 'read = system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan' >> $asterisk_etc/manager_api.conf
	echo 'write = system,call,agent,user,config,command,reporting,originate,message' >> $asterisk_etc/manager_api.conf
done
sudo chmod 777 $asterisk_etc/manager_api.conf

sleep 0.5

rm -rf $asterisk_etc/manager.conf
sudo touch $asterisk_etc/manager.conf
echo '[general]' >> $asterisk_etc/manager.conf
echo 'enabled = yes' >> $asterisk_etc/manager.conf
echo 'port = 5038' >> $asterisk_etc/manager.conf
echo 'bindaddr = 127.0.0.1' >> $asterisk_etc/manager.conf
echo '' >> $asterisk_etc/manager.conf
echo '[zetadmin_api]' >> $asterisk_etc/manager.conf
echo 'secret = zetadmin_api' >> $asterisk_etc/manager.conf
echo 'deny=0.0.0.0/0.0.0.0' >> $asterisk_etc/manager.conf
echo 'permit=127.0.0.1/255.255.255.0' >> $asterisk_etc/manager.conf
echo 'read = system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan' >> $asterisk_etc/manager.conf
echo 'write = system,call,agent,user,config,command,reporting,originate,message' >> $asterisk_etc/manager.conf
echo '' >> $asterisk_etc/manager.conf
echo '[phpagi]' >> $asterisk_etc/manager.conf
echo 'secret = phpagi' >> $asterisk_etc/manager.conf
echo 'deny=0.0.0.0/0.0.0.0' >> $asterisk_etc/manager.conf
echo 'permit=127.0.0.1/255.255.255.0' >> $asterisk_etc/manager.conf
echo 'read = system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan' >> $asterisk_etc/manager.conf
echo 'write = system,call,agent,user,config,command,reporting,originate,message' >> $asterisk_etc/manager.conf
echo '' >> $asterisk_etc/manager.conf
echo '#include manager_api.conf' >> $asterisk_etc/manager.conf
sudo chown -R asterisk:asterisk $asterisk_etc/manager.conf

rm -rf $asterisk_etc/extensions.conf
sudo touch $asterisk_etc/extensions.conf
echo '[general]' >> $asterisk_etc/extensions.conf
echo 'static=yes' >> $asterisk_etc/extensions.conf
echo 'writeprotect=no' >> $asterisk_etc/extensions.conf
echo 'clearglobalvars=no' >> $asterisk_etc/extensions.conf
echo ' ' >> $asterisk_etc/extensions.conf
echo '[public]' >> $asterisk_etc/extensions.conf
echo ' ' >> $asterisk_etc/extensions.conf
echo '[default]' >> $asterisk_etc/extensions.conf
echo ' ' >> $asterisk_etc/extensions.conf

sudo touch $asterisk_etc/extensions_api.conf
echo ';extensions_api.conf' >> $asterisk_etc/extensions_api.conf
echo '' >> $asterisk_etc/extensions.conf
echo '[default_za]' >> $asterisk_etc/extensions.conf
echo 'exten => _X.,1,NoOp()' >> $asterisk_etc/extensions.conf
echo '  same => n,AGI(callerid.php,${EXTEN})' >> $asterisk_etc/extensions.conf
echo '	same => n,Dial(SIP/${EXTEN},30)' >> $asterisk_etc/extensions.conf
echo '	same => n,Hangup()' >> $asterisk_etc/extensions.conf
echo '' >> $asterisk_etc/extensions.conf
echo '#include extensions_api.conf' >> $asterisk_etc/extensions.conf
sudo chmod 777 $asterisk_etc/extensions_api.conf
sudo touch $asterisk_etc/extensions_radio.conf
echo ';extensions_radio.conf' >> $asterisk_etc/extensions_radio.conf
echo '' >> $asterisk_etc/extensions.conf
echo '#include extensions_radio.conf' >> $asterisk_etc/extensions.conf
sudo chmod 777 $asterisk_etc/extensions_radio.conf
sudo touch $asterisk_etc/extensions_otp.conf
echo ';extensions_otp.conf' >> $asterisk_etc/extensions_otp.conf
echo '' >> $asterisk_etc/extensions.conf
echo '#include extensions_otp.conf' >> $asterisk_etc/extensions.conf
sudo chmod 777 $asterisk_etc/extensions_otp.conf

sudo touch $asterisk_etc/queues_api.conf
echo ';queues_api.conf' >> $asterisk_etc/queues_api.conf
echo '#include queues_api.conf' >> $asterisk_etc/queues.conf
sudo chmod 777 $asterisk_etc/queues_api.conf

rm -rf $asterisk_etc/http.conf
sudo touch $asterisk_etc/http.conf
echo '[general]' >> $asterisk_etc/http.conf
echo 'servername='$pbx_domain'' >> $asterisk_etc/http.conf
echo 'enabled=yes' >> $asterisk_etc/http.conf
echo 'tlsenable=yes' >> $asterisk_etc/http.conf
echo 'tlsbindaddr=0.0.0.0:8089' >> $asterisk_etc/http.conf
echo ';tlscertfile=/etc/asterisk/keys/asterisk.pem' >> $asterisk_etc/http.conf
echo 'tlscertfile=/etc/letsencrypt/live/'$pbx_domain'/fullchain.pem' >> $asterisk_etc/http.conf
echo 'tlsprivatekey=/etc/letsencrypt/live/'$pbx_domain'/privkey.pem' >> $asterisk_etc/http.conf
sudo chown -R asterisk:asterisk $asterisk_etc/http.conf

rm -rf $asterisk_etc/ari.conf
sudo touch $asterisk_etc/ari.conf
echo '[general]' >> $asterisk_etc/ari.conf
echo 'enabled = yes ; When set to no, ARI support is disabled.' >> $asterisk_etc/ari.conf
echo ';pretty = no ; When set to yes, responses from ARI are formatted to be human readable.' >> $asterisk_etc/ari.conf
echo ';allowed_origins = ; http://192.168.1.254,http://192.168.1.254:8080' >> $asterisk_etc/ari.conf
echo ';auth_realm = ' >> $asterisk_etc/ari.conf
echo ';websocket_write_timeout = 100' >> $asterisk_etc/ari.conf

echo '[ari_api]' >> $asterisk_etc/ari.conf
echo 'type = user' >> $asterisk_etc/ari.conf
echo ';allowed_origins = ' >> $asterisk_etc/ari.conf
echo 'read_only = yes' >> $asterisk_etc/ari.conf
echo 'password = ari_api' >> $asterisk_etc/ari.conf
echo 'password_format = plain' >> $asterisk_etc/ari.conf
echo '' >> $asterisk_etc/ari.conf
echo '#include ari_api.conf' >> $asterisk_etc/ari.conf

rm -rf $asterisk_etc/ari_api.conf
sudo touch $asterisk_etc/ari_api.conf
max_ari_user=32
for (( j=1; j <= $max_ari_user; ++j ))
do
	echo '' >> $asterisk_etc/ari_api.conf
    echo '[ari_api_'$j']' >> $asterisk_etc/ari_api.conf
	echo 'type = user' >> $asterisk_etc/ari_api.conf
	echo 'read_only = yes' >> $asterisk_etc/ari_api.conf
	echo 'password = ari_api' >> $asterisk_etc/ari_api.conf
	echo 'password_format = plain' >> $asterisk_etc/ari_api.conf
done
sudo chown -R asterisk:asterisk $asterisk_etc/ari.conf
sudo chmod 777 $asterisk_etc/ari_api.conf
#sudo systemctl restart asterisk

sudo touch $asterisk_etc/sip_account.conf
echo ';sip_account.conf' >> $asterisk_etc/sip_account.conf
echo '#include sip_account.conf' >> $asterisk_etc/sip.conf
sudo chmod 777 $asterisk_etc/sip_account.conf
sudo chmod 777 $asterisk_etc/sip.conf

sudo touch $asterisk_etc/sip_trunk.conf
echo ';sip_trunk.conf' >> $asterisk_etc/sip_trunk.conf
echo '#include sip_trunk.conf' >> $asterisk_etc/sip.conf
sudo chmod 777 $asterisk_etc/sip_trunk.conf

sudo touch $asterisk_etc/musiconhold_api.conf
echo ';musiconhold_api.conf' >> $asterisk_etc/musiconhold_api.conf
echo '#include musiconhold_api.conf' >> $asterisk_etc/musiconhold.conf
sudo chmod 777 $asterisk_etc/musiconhold_api.conf

sudo touch $asterisk_etc/pjsip_account.conf
echo ';pjsip_account.conf' >> $asterisk_etc/pjsip_account.conf
echo '#include pjsip_account.conf' >> $asterisk_etc/pjsip.conf
sudo chmod 777 $asterisk_etc/pjsip_account.conf

sudo touch $asterisk_etc/pjsip_trunk.conf
echo ';pjsip_trunk.conf' >> $asterisk_etc/pjsip_trunk.conf
echo '#include pjsip_trunk.conf' >> $asterisk_etc/pjsip.conf
sudo chmod 777 $asterisk_etc/pjsip_trunk.conf

echo ' ' >> $asterisk_etc/ari.conf
echo '[ariapi]' >> $asterisk_etc/ari.conf
echo 'type = user' >> $asterisk_etc/ari.conf
echo 'read_only = yes' >> $asterisk_etc/ari.conf
echo 'password = ariapi' >> $asterisk_etc/ari.conf
echo 'password_format = plain' >> $asterisk_etc/ari.conf

agibin_dir=/var/lib/asterisk/agi-bin
sudo mv $webroot/api/zetadmin_pbx/phpagi.conf $asterisk_etc/phpagi.conf
sudo mv $webroot/api/zetadmin_pbx/pbxlog.php $webroot/api/zetadmin_pbx/$file_name.php
sudo mv $webroot/api/zetadmin_pbx/callerid.php $agibin_dir/callerid.php
sudo cp -pf $webroot/api/zetadmin_pbx/phpagi.php $agibin_dir/phpagi.php
sudo cp -pf $webroot/api/zetadmin_pbx/phpagi-asmanager.php $agibin_dir/phpagi-asmanager.php
sudo cp -pf $webroot/api/zetadmin_pbx/phpagi-fastagi.php $agibin_dir/phpagi-fastagi.php
sudo cp -pf $webroot/api/zetadmin_pbx/lib/class.mongodb.php $agibin_dir/class.mongodb.php
sudo cp -pf $webroot/api/zetadmin_pbx/lib/class.action.php $agibin_dir/class.action.php
sudo cp -pf $webroot/api/zetadmin_pbx/config.php $agibin_dir/config.php

sudo chmod 777 $agibin_dir/*.php
sudo chown -R asterisk:asterisk $asterisk_etc/phpagi.conf
sudo systemctl restart asterisk

system_dir=/etc/systemd/system
usr_dir=/usr/lib/systemd/system
sudo touch $usr_dir/pbxlog.service
echo '[Unit]' >> $usr_dir/pbxlog.service
echo 'Description=PBX log service' >> $usr_dir/pbxlog.service
echo 'After=network.target' >> $usr_dir/pbxlog.service
echo '' >> $usr_dir/pbxlog.service
echo '[Service]' >> $usr_dir/pbxlog.service
echo 'ExecStart=/usr/bin/php '$webroot'/api/zetadmin_pbx/'$file_name'.php' >> $usr_dir/pbxlog.service
echo 'Restart=always' >> $usr_dir/pbxlog.service
echo 'User=root' >> $usr_dir/pbxlog.service
echo '' >> $usr_dir/pbxlog.service
echo '[Install]' >> $usr_dir/pbxlog.service
echo 'WantedBy=multi-user.target' >> $usr_dir/pbxlog.service
sudo chmod 644 $usr_dir/pbxlog.service

sudo ln -s $usr_dir/pbxlog.service $system_dir/
sudo ls -l $usr_dir/pbxlog.service

sudo systemd-analyze verify pbxlog.service 
sudo systemctl daemon-reload
sudo systemctl enable pbxlog.service
sudo systemctl start pbxlog.service

sleep 1

sudo touch $usr_dir/radiopbx.service
echo '[Unit]' >> $usr_dir/radiopbx.service
echo 'Description=PBX radio service' >> $usr_dir/radiopbx.service
echo 'After=network.target' >> $usr_dir/radiopbx.service
echo '' >> $usr_dir/radiopbx.service
echo '[Service]' >> $usr_dir/radiopbx.service
echo 'ExecStart=/usr/bin/php '$webroot'/api/zetadmin_pbx/radio.php' >> $usr_dir/radiopbx.service
echo 'Restart=always' >> $usr_dir/radiopbx.service
echo 'User=root' >> $usr_dir/radiopbx.service
echo '' >> $usr_dir/radiopbx.service
echo '[Install]' >> $usr_dir/radiopbx.service
echo 'WantedBy=multi-user.target' >> $usr_dir/radiopbx.service
sudo chmod 644 $usr_dir/radiopbx.service

sudo ln -s $usr_dir/radiopbx.service $system_dir/
sudo ls -l $usr_dir/radiopbx.service

sudo systemd-analyze verify radiopbx.service 
sudo systemctl daemon-reload
sudo systemctl enable radiopbx.service
sudo systemctl start radiopbx.service

sleep 1

httpdCmdFile=/var/www/httpdcmd.sh
sudo rm -rf $httpdCmdFile
sudo touch $httpdCmdFile
echo '#!/bin/sh' >> $httpdCmdFile
echo 'while true ; do' >> $httpdCmdFile
echo '	actions="$(cat /var/www/httpdcmd.log)"' >> $httpdCmdFile
echo '	if [ -z "$actions" ] ' >> $httpdCmdFile
echo '	then' >> $httpdCmdFile
echo '		sleep 2' >> $httpdCmdFile
echo '	else' >> $httpdCmdFile
echo '		echo "CMD#$actions" >> /var/www/httpdcmd.out' >> $httpdCmdFile
echo '		res="$($actions)"' >> $httpdCmdFile
echo '		echo "CMD_response#start---------" >> /var/www/httpdcmd.out' >> $httpdCmdFile
echo '		echo "$res" >> /var/www/httpdcmd.out' >> $httpdCmdFile
echo '		echo "CMD_response#end---------" >> /var/www/httpdcmd.out' >> $httpdCmdFile
echo '		echo " " >> /var/www/httpdcmd.out' >> $httpdCmdFile
echo '		echo -n "" > /var/www/httpdcmd.log' >> $httpdCmdFile
echo '	fi' >> $httpdCmdFile
echo 'done' >> $httpdCmdFile
sudo touch /var/www/httpdcmd.log
echo ' ' >> /var/www/httpdcmd.log
sudo touch /var/www/httpdcmd.out
echo ' ' >> /var/www/httpdcmd.out

sleep 1

system_dir=/etc/systemd/system
usr_dir=/usr/lib/systemd/system
sudo rm -rf $usr_dir/httpdcmd.service
sudo touch $usr_dir/httpdcmd.service
echo '[Unit]' >> $usr_dir/httpdcmd.service
echo 'Description=httpd cmd service' >> $usr_dir/httpdcmd.service
echo 'After=network.target' >> $usr_dir/httpdcmd.service
echo '' >> $usr_dir/radiopbx.service
echo '[Service]' >> $usr_dir/radiopbx.service
echo 'ExecStart=nohup /var/www/httpdcmd.sh >> /var/www/httpdcmd.out &' >> $usr_dir/httpdcmd.service
echo 'Restart=always' >> $usr_dir/httpdcmd.service
echo 'User=root' >> $usr_dir/httpdcmd.service
echo '' >> $usr_dir/httpdcmd.service
echo '[Install]' >> $usr_dir/httpdcmd.service
echo 'WantedBy=multi-user.target' >> $usr_dir/httpdcmd.service
sudo chmod 644 $usr_dir/httpdcmd.service

sudo ln -s $usr_dir/httpdcmd.service $system_dir/
sudo ls -l $usr_dir/httpdcmd.service

sudo systemd-analyze verify httpdcmd.service 
sudo systemctl daemon-reload
sudo systemctl enable httpdcmd.service
sudo systemctl start httpdcmd.service

sleep 1

sudo yum -y install fail2ban fail2ban-systemd
sudo cp -pf /etc/fail2ban/jail.conf /etc/fail2ban/jail.local

sudo touch /etc/fail2ban/jail.d/asterisk.local && chmod +x /etc/fail2ban/jail.d/asterisk.local

asterisk_file=/etc/fail2ban/jail.d/asterisk.local
echo '[asterisk]' >> $asterisk_file
echo 'enabled = true' >> $asterisk_file
echo 'filter = asterisk' >> $asterisk_file
echo 'action = iptables-allports[name=ASTERISK, protocol=all]' >> $asterisk_file
echo '#sendmail-whois[name=Asterisk, dest=you@example.com, sender=fail2ban@example.com]' >> $asterisk_file
echo 'logpath = /var/log/asterisk/messages' >> $asterisk_file
echo 'maxretry = 5' >> $asterisk_file
echo 'bantime = 86400' >> $asterisk_file

sudo systemctl enable fail2ban
sudo systemctl start fail2ban

cronPbxLogFile=/var/www/pbxlog.sh
sudo touch $cronPbxLogFile
echo '#!/bin/sh' >> $cronPbxLogFile
echo 'STATUS="$(systemctl is-active pbxlog.service)"' >> $cronPbxLogFile
echo 'if [ "${STATUS}" = "active" ]; then' >> $cronPbxLogFile
echo '    echo "pbxlog: true"' >> $cronPbxLogFile
echo 'else ' >> $cronPbxLogFile
echo '    echo "pbxlog: false"' >> $cronPbxLogFile
echo '    sudo systemctl restart pbxlog.service' >> $cronPbxLogFile
echo '    exit 1' >> $cronPbxLogFile
echo 'fi' >> $cronPbxLogFile
echo ' ' >> $cronPbxLogFile
echo 'STATUS_RADIO="$(systemctl is-active radiopbx.service)"' >> $cronPbxLogFile
echo 'if [ "${STATUS_RADIO}" = "active" ]; then' >> $cronPbxLogFile
echo '    echo "radiopbx: true"' >> $cronPbxLogFile
echo 'else ' >> $cronPbxLogFile
echo '    echo "radiopbx: false"' >> $cronPbxLogFile
echo '    sudo systemctl restart radiopbx.service' >> $cronPbxLogFile
echo '    exit 1' >> $cronPbxLogFile
echo 'fi' >> $cronPbxLogFile

cronCerbotFile=/var/www/certbot.sh
sudo touch $cronCerbotFile
echo '#!/bin/sh' >> $cronCerbotFile
echo 'sudo certbot renew' >> $cronCerbotFile
echo 'sudo systemctl reload httpd' >> $cronCerbotFile

crontab_line_pbxlog="*/1 * * * * sh /var/www/pbxlog.sh >/dev/pbxlog 2>&1"
(crontab -u $(whoami) -l; echo "$crontab_line_pbxlog" ) | crontab -u $(whoami) -

crontab_line_certbot="* */12 * * * sh /var/www/certbot.sh >/dev/certbot 2>&1"
(crontab -u $(whoami) -l; echo "$crontab_line_certbot" ) | crontab -u $(whoami) -
sudo systemctl restart crond.service

#sudo chown -R nobody:nobody $webroot/

sudo chown -R apache:apache $webroot/

echo -e "\033[32mCreate Cloud PBX API successful!\033[m"
echo " "

sleep 0.5

echo " "
echo "+-------------------------------------------------------------------------------+"
echo " Info - Webserver Apache AND Cloud PBX api"
echo " "
echo " Webroot   : "$webroot/""
echo " API URL   : https://"$pbx_domain"/api/zetadmin_pbx/postback.php"
echo " API Key   : "$web_api_key""
echo " "
echo "+-------------------------------------------------------------------------------+"
echo " "