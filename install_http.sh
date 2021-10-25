#!/bin/sh
echo ""
echo "+-----------------------------------------------------------------------+"
echo "|  Install OpenLiteSpeed, MongoDB (OPMC7) on Centos 7                   |"
echo "|  Database: MongoDB 4.4                                                |"
echo "|  PHP: 7.3                                                             |"
echo "|  SSL: Let's Encrypt                                                   |"
echo "|  Synthesized by ZetAdmin Framework                                    |"
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

echo -n "Please enter domain name: ";
read pbx_domain

wget -q --tries=10 --timeout=20 --spider http://$pbx_domain
if [[ $? -eq 0 ]]; then
	IPDATA=$(dig +short $pbx_domain)
	if grep -q $IP <<< $IPDATA; then
		echo -e "\033[32mGood!\033[m Ready to install."
		echo ' '
	else
		echo -e "\033[31mError!\033[m Please point your domain name "$pbx_domain" to IP "$IP""
		exit
	fi
else
    echo -e "\033[31mError!\033[m Please point your domain name "$pbx_domain" to IP "$IP""
	exit
fi

sleep 0.5

sudo setenforce 0
sudo sed -i 's/\(^SELINUX=\).*/\SELINUX=permissive/' /etc/selinux/config
sudo yum -y groupinstall "Development Tools"
sudo yum -y install epel-release
sudo yum -y install gcc make gcc-c++ cpp git openssl-devel m4 autoconf automake vim

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
sudo firewall-cmd --zone=public --add-port=7080/tcp --permanent
sudo firewall-cmd --reload

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

sleep 0.5

echo " "
echo "+-------------------------------------------+"
echo "|   Install Let’s Encrypt                   |"
echo "+-------------------------------------------+"
echo " "
yum install -y certbot
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

webroot=/var/www/public_html/$pbx_domain

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

sudo chown -R nobody:nobody $webroot/

echo -e "\033[32mCreate Cloud PBX API successful!\033[m"
echo " "

echo " "
echo "+-------------------------------------------------------------------------------+"
echo " "
echo " Web Root: "$webroot/""
echo " Webserver Admin: https://"$pbx_domain":7080"
echo " "
echo "+-------------------------------------------------------------------------------+"
echo " "