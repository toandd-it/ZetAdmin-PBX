#!/bin/sh
echo ""
echo "+-----------------------------------------------------------------------+"
echo "|  Uninstall Cloud PBX API vs Asterisk 18 on Centos 7                   |"
echo "|  By ZetAdmin Framework                                                |"
echo "+-----------------------------------------------------------------------+"

while [ "$q_uninstall" != "y" ] && [ "$q_uninstall" != "n" ] ;do
    echo
	echo -n "Press y to start uninstall, n to cancel ? [y/n]: "
	read q_uninstall
done
if [ "$q_uninstall" != "y" ];then 
	echo
	exit
fi

sudo systemctl stop pbxlog.service
sudo systemctl disable pbxlog.service
sudo rm -rf /usr/lib/systemd/system/pbxlog.service
sudo rm -rf /etc/fail2ban/jail.d/asterisk.local

sudo systemctl stop lsws.service
sudo systemctl disable lsws.service
sudo systemctl stop lshttpd.service
sudo systemctl disable lshttpd.service

sudo rm -rf /usr/lib/systemd/system/lshttpd.service
sudo rm -rf /usr/local/lsws/
sudo rm -rf /var/www/public_html/
echo -e "\033[32mRemove OpenLiteSpeed successful!\033[m"

sleep 0.5

sudo systemctl stop mongod.service
sudo systemctl disable mongod.service
sudo yum -y erase $(rpm -qa | grep mongodb-org)
sudo rm -r /var/log/mongodb
sudo rm -r /var/lib/mongo
echo -e "\033[32mRemove MongoDB successful!\033[m"
sleep 0.5

sudo systemctl stop asterisk
sudo systemctl disable asterisk
sudo rm -rf /etc/asterisk
sudo rm -rf /var/log/asterisk
sudo rm -rf /var/lib/asterisk
sudo rm -rf /var/spool/asterisk
sudo rm -rf /usr/lib/asterisk
echo -e "\033[32mRemove asterisk successful!\033[m"
sleep 0.5
echo -e "\033[32mUninstall successful!\033[m"