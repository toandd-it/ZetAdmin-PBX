#!/bin/sh

echo "+-----------------------------------------------------------------------+"
echo "|  Uninstall Cloud PBX API vs Asterisk 18 on Centos 7                   |"
echo "|  By ZetAdmin Framework                                                |"
echo "+-----------------------------------------------------------------------+"

while [ "$q_install" != "y" ] && [ "$q_install" != "n" ] ;do
    echo
	echo -n "Press y to start uninstall, n to cancel ? [y/n]: "
	read q_install
done

if [ "$q_install" != "y" ];then 
	echo
	exit
fi

sudo systemctl stop pbxlog.service
sudo rm -rf /etc/fail2ban/jail.d/asterisk.local

sudo systemctl stop lsws.service
sudo rm -rf /usr/local/lsws/
sudo rm -rf /var/www/public_html/
echo -e "\033[32mRemove OpenLiteSpeed successful!\033[m"
sleep 0.5

sudo systemctl mongod lsws.service
sudo yum -y erase $(rpm -qa | grep mongodb-org)
sudo rm -r /var/log/mongodb
sudo rm -r /var/lib/mongo
echo -e "\033[32mRemove MongoDB successful!\033[m"
sleep 0.5

sudo systemctl stop asterisk
sudo rm -rf /etc/asterisk
sudo rm -rf /var/log/asterisk
sudo rm -rf /var/lib/asterisk
sudo rm -rf /var/spool/asterisk
sudo rm -rf /usr/lib/asterisk
echo -e "\033[32mRemove asterisk successful!\033[m"
sleep 0.5
echo -e "\033[32mUninstall successful!\033[m"