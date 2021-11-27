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

sudo wget http://mirror.centos.org/centos/7/os/x86_64/Packages/sox-14.4.1-7.el7.x86_64.rpm
sudo yum  -y install sox-14.4.1-7.el7.x86_64.rpm

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

cd /usr/src/
wget https://downloads.asterisk.org/pub/telephony/asterisk/releases/asterisk-13.38.3.tar.gz
tar xvfz asterisk-13.38.3.tar.gz
cd asterisk-13*/
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

sudo firewall-cmd --zone=public --permanent --add-service={sip,sips}
sudo firewall-cmd --zone=public --add-port=8089/tcp --permanent
sudo firewall-cmd --zone=public --permanent --add-port=10000-20000/udp
sudo firewall-cmd --zone=public --permanent --add-service={http,https}
sudo firewall-cmd --reload
echo -e "\033[32mInstall Asterisk successful!\033[m"
echo " "

sleep 0.5

echo " "
echo "+-------------------------------------------+"
echo "|   Install Webserver OpenLiteSpeed         |"
echo "+-------------------------------------------+"
echo " "

rpm -ivh http://rpms.litespeedtech.com/centos/litespeed-repo-1.2-1.el8.noarch.rpm
yum install -y openlitespeed
yum install -y zip unzip kernel-headers.x86_64 bzip2-devel libjpeg-devel libpng-devel freetype-devel openldap-devel postgresql-devel aspell-devel net-snmp-devel libxslt-devel libc-client-devel libicu-devel gmp-devel curl-devel libmcrypt libmcrypt-devel pcre-devel enchant-devel libXpm-devel mysql-devel readline-devel recode-devel libtidy-devel libtool-ltdl-devel
yum install -y lsphp73 lsphp73-bcmath lsphp73-common lsphp73-dba lsphp73-dbg lsphp73-devel lsphp73-enchant lsphp73-gd lsphp73-gmp lsphp73-imap lsphp73-intl lsphp73-json lsphp73-ldap lsphp73-mbstring lsphp73-mysqlnd lsphp73-odbc lsphp73-opcache lsphp73-pdo lsphp73-pear lsphp73-pecl-apcu lsphp73-pecl-apcu-devel lsphp73-pecl-apcu-panel lsphp73-pecl-igbinary lsphp73-pecl-igbinary-devel lsphp73-pecl-mcrypt lsphp73-pecl-memcached lsphp73-pecl-msgpack lsphp73-pecl-msgpack-devel lsphp73-pecl-redis lsphp73-pgsql lsphp73-process lsphp73-pspell lsphp73-recode lsphp73-snmp lsphp73-soap lsphp73-xml lsphp73-xmlrpc lsphp73-zip lsphp73-pear zlib-devel

ln -s /usr/local/lsws/lsphp73/lib64 /usr/local/lsws/lsphp73/lib
#sudo firewall-cmd --zone=public --add-port=7080/tcp --permanent
#sudo firewall-cmd --reload

sudo systemctl start lsws.service
echo -e "\033[32mInstall Webserver OpenLiteSpeed successful!\033[m"
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

/usr/local/lsws/lsphp73/bin/pecl install mongodb
chmod 755 /usr/local/lsws/lsphp73/lib64/php/modules/mongodb.so
echo "extension=mongodb.so" >> /usr/local/lsws/lsphp73/etc/php.ini
sudo systemctl restart lsws.service

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

sleep 0.5

echo " "
echo "+-------------------------------------------+"
echo "|   Install Let’s Encrypt                   |"
echo "+-------------------------------------------+"
echo " "
yum install -y certbot
webroot=/var/www/public_html/$pbx_domain
certbot certonly --webroot -w $webroot/ --agree-tos -m admin@$pbx_domain -d $pbx_domain
crontab_line="* */12 * * * root /usr/bin/certbot renew >/dev/null 2>&1"
(crontab -u $(whoami) -l; echo "$crontab_line" ) | crontab -u $(whoami) -
sudo systemctl restart crond.service
echo -e "\033[32mInstall Let’s Encrypt successful!\033[m"
echo " "

sleep 0.5

echo " "
echo "+-------------------------------------------+"
echo "|   Create OpenLiteSpeed VirtualHost        |"
echo "+-------------------------------------------+"
echo " "

echo "nobody ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
sudo mkdir /usr/local/lsws/conf/vhosts/$pbx_domain/
vh_conf=/usr/local/lsws/conf/vhosts/$pbx_domain/$pbx_domain.conf
touch $vh_conf
echo 'docRoot                   $VH_ROOT/' >> $vh_conf
echo 'vhDomain                  '$pbx_domain'' >> $vh_conf
echo 'enableGzip                1' >> $vh_conf
echo 'enableBr                  1' >> $vh_conf
echo ' ' >> $vh_conf
echo 'rewrite  {' >> $vh_conf
echo '  enable                  1' >> $vh_conf
echo '  autoLoadHtaccess        1' >> $vh_conf
echo '  logLevel                9' >> $vh_conf
echo '}' >> $vh_conf
echo ' ' >> $vh_conf
echo 'vhssl  {' >> $vh_conf
echo '  keyFile                 /etc/letsencrypt/live/'$pbx_domain'/privkey.pem' >> $vh_conf
echo '  certFile                /etc/letsencrypt/live/'$pbx_domain'/fullchain.pem' >> $vh_conf
echo '}' >> $vh_conf
echo ' ' >> $vh_conf
sudo chown -R lsadm:nobody /usr/local/lsws/conf/vhosts/$pbx_domain/
sudo chmod 600 $vh_conf

httpd_conf=/usr/local/lsws/conf/httpd_config.conf
echo 'virtualhost '$pbx_domain' {' >> $httpd_conf
echo '  vhRoot                  '$webroot/'' >> $httpd_conf
echo '  configFile              $SERVER_ROOT/conf/vhosts/$VH_NAME/'$pbx_domain'.conf' >> $httpd_conf
echo '  allowSymbolLink         1' >> $httpd_conf
echo '  enableScript            1' >> $httpd_conf
echo '  restrained              1' >> $httpd_conf
echo '}' >> $httpd_conf
echo ' ' >> $httpd_conf
echo 'listener HTTP {' >> $httpd_conf
echo '  address                 *:80' >> $httpd_conf
echo '  binding                 1' >> $httpd_conf
echo '  reusePort               0' >> $httpd_conf
echo '  secure                  0' >> $httpd_conf
echo '  map                     '$pbx_domain' '$pbx_domain'' >> $httpd_conf
echo '}' >> $httpd_conf
echo ' ' >> $httpd_conf
echo 'listener HTTPS {' >> $httpd_conf
echo '  address                 *:443' >> $httpd_conf
echo '  binding                 1' >> $httpd_conf
echo '  reusePort               1' >> $httpd_conf
echo '  secure                  1' >> $httpd_conf
echo '  keyFile                 /etc/letsencrypt/live/'$pbx_domain'/privkey.pem' >> $httpd_conf
echo '  certFile                /etc/letsencrypt/live/'$pbx_domain'/fullchain.pem' >> $httpd_conf
echo '  map                     '$pbx_domain' '$pbx_domain'' >> $httpd_conf
echo '}' >> $httpd_conf
echo ' ' >> $httpd_conf
sudo chown -R lsadm:nobody $httpd_conf
sudo chmod 750 $httpd_conf

#sudo sh /usr/local/lsws/admin/misc/admpass.sh

sudo mkdir /var/www/
sudo mkdir /var/www/public_html/
sudo mkdir /var/www/public_html/$pbx_domain/
sudo chown -R nobody:nobody /var/www/public_html/

cd $webroot/
touch index.html
echo 'pageok!' >> index.html
cd ~
sudo systemctl restart lsws.service
echo -e "\033[32mCreate VirtualHost successful!\033[m"
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

asterisk_pbx_dir=$(find ~ -type d -name "asterisk_pbx")
sudo cp -R $asterisk_pbx_dir $webroot/api/

echo -e "\033[32mDownload and export API source code successful!\033[m"
echo " "
sleep 0.5

file_name=$(openssl rand -hex 16)
web_api_id=$(openssl rand -hex 16)
web_api_key=$(openssl rand -hex 24)
api_conf=api/asterisk_pbx/config.php
sudo touch $webroot/$api_conf
echo '<?php' >> $webroot/$api_conf
echo '$api_url = "https://'$pbx_domain'/api/asterisk_pbx/postback.php";' >> $webroot/$api_conf
echo '$api_key = "'$web_api_key'";' >> $webroot/$api_conf
echo '$ipsAlow = []; /*exp ["ip 1", "ip 2", "ip n"]*/' >> $webroot/$api_conf
echo ' ' >> $webroot/$api_conf
echo '$db_url = "mongodb://'$mongodb_dbuser':'$mongodb_dbpwd'@127.0.0.1:27017/'$mongodb_dbname'";' >> $webroot/$api_conf
echo '$db_name = "'$mongodb_dbname'";' >> $webroot/$api_conf
echo '?>' >> $webroot/$api_conf

sleep 0.5

asterisk_etc=/etc/asterisk

sudo touch $asterisk_etc/manager_api.conf
echo '' >> $asterisk_etc/manager_api.conf
echo '[auto_call_api]' >> $asterisk_etc/manager_api.conf
echo 'secret = auto_call_api' >> $asterisk_etc/manager_api.conf
echo 'deny=0.0.0.0/0.0.0.0' >> $asterisk_etc/manager_api.conf
echo 'permit=127.0.0.1/255.255.255.0' >> $asterisk_etc/manager_api.conf
echo 'read = system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan' >> $asterisk_etc/manager_api.conf
echo 'write = system,call,agent,user,config,command,reporting,originate,message' >> $asterisk_etc/manager_api.conf
sudo chmod 777 $asterisk_etc/manager_api.conf

sleep 0.5

rm -rf $asterisk_etc/manager.conf
sudo touch $asterisk_etc/manager.conf
echo '[general]' >> $asterisk_etc/manager.conf
echo 'enabled = yes' >> $asterisk_etc/manager.conf
echo 'port = 5038' >> $asterisk_etc/manager.conf
echo 'bindaddr = 127.0.0.1' >> $asterisk_etc/manager.conf
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
echo '#include manager_api.conf' >> $asterisk_etc/manager.conf
sudo chown -R asterisk:asterisk $asterisk_etc/manager.conf

sudo touch $asterisk_etc/extensions_api.conf
echo ';extensions_api.conf' >> $asterisk_etc/extensions_api.conf
echo '[default_za]' >> $asterisk_etc/extensions.conf
echo 'exten => _X.,1,Answer()' >> $asterisk_etc/extensions.conf
echo '	same => n,Dial(SIP/${EXTEN},30)' >> $asterisk_etc/extensions.conf
echo '	same => n,Hangup()' >> $asterisk_etc/extensions.conf
echo '' >> $asterisk_etc/extensions.conf
echo '#include extensions_api.conf' >> $asterisk_etc/extensions.conf
sudo chmod 777 $asterisk_etc/extensions_api.conf


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
echo 'tlscertfile=/etc/asterisk/keys/asterisk.pem' >> $asterisk_etc/http.conf
echo ';tlscertfile=/etc/letsencrypt/live/'$pbx_domain'/fullchain.pem' >> $asterisk_etc/http.conf
echo ';tlsprivatekey=/etc/letsencrypt/live/'$pbx_domain'/privkey.pem' >> $asterisk_etc/http.conf
sudo chown -R asterisk:asterisk $asterisk_etc/http.conf
#sudo systemctl restart asterisk

sudo touch $asterisk_etc/sip_account.conf
echo ';sip_account.conf' >> $asterisk_etc/sip_account.conf
echo '#include pjsip_account.conf' >> $asterisk_etc/sip.conf
sudo chmod 777 $asterisk_etc/sip_account.conf

sudo touch $asterisk_etc/pjsip_trunk.conf
echo ';sip_trunk.conf' >> $asterisk_etc/sip_trunk.conf
echo '#include sip_trunk.conf' >> $asterisk_etc/sip.conf
sudo chmod 777 $asterisk_etc/sip_trunk.conf

agibin_dir=/var/lib/asterisk/agi-bin
sudo mv $webroot/api/asterisk_pbx/phpagi.conf $asterisk_etc/phpagi.conf
sudo mv $webroot/api/asterisk_pbx/pbxlog.php $webroot/api/asterisk_pbx/$file_name.php
sudo mv $webroot/api/asterisk_pbx/callerid.php $agibin_dir/callerid.php
sudo cp -pf $webroot/api/asterisk_pbx/phpagi.php $agibin_dir/phpagi.php
sudo cp -pf $webroot/api/asterisk_pbx/phpagi-asmanager.php $agibin_dir/phpagi-asmanager.php
sudo cp -pf $webroot/api/asterisk_pbx/phpagi-fastagi.php $agibin_dir/phpagi-fastagi.php
sudo cp -pf $webroot/api/asterisk_pbx/lib/class.mongodb.php $agibin_dir/class.mongodb.php
sudo cp -pf $webroot/api/asterisk_pbx/lib/class.action.php $agibin_dir/class.action.php
sudo cp -pf $webroot/api/asterisk_pbx/config.php $agibin_dir/config.php

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
echo 'ExecStart=/usr/local/lsws/lsphp73/bin/php '$webroot'/api/asterisk_pbx/'$file_name'.php' >> $usr_dir/pbxlog.service
echo 'Restart=always' >> $usr_dir/pbxlog.service
echo 'User=nobody' >> $usr_dir/pbxlog.service
echo '' >> $usr_dir/pbxlog.service
echo '[Install]' >> $usr_dir/pbxlog.service
echo 'WantedBy=multi-user.target' >> $usr_dir/pbxlog.service
sudo chmod 644 $usr_dir/pbxlog.service

sudo ln -s $usr_dir/pbxlog.service $system_dir/
sudo ls -l $usr_dir/pbxlog.service

sudo systemd-analyze verify pbxlog.service 
sudo systemctl daemon-reload
sudo systemctl start pbxlog.service

sleep 0.5

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

sudo chown -R nobody:nobody $webroot/

echo -e "\033[32mCreate Cloud PBX API successful!\033[m"
echo " "

sleep 0.5

echo " "
echo "+-------------------------------------------------------------------------------+"
echo " Info - Webserver OpenLiteSpeed AND Cloud PBX api"
echo " "
echo " Webroot   : "$webroot/""
echo " API URL   : https://"$pbx_domain"/api/asterisk_pbx/postback.php"
echo " API Key   : "$web_api_key""
echo " "
echo "+-------------------------------------------------------------------------------+"
echo " "