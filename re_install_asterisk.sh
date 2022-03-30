echo " "
echo "+------------------------------------+"
echo "|      Install Asterisk-13           |"
echo "+------------------------------------+"
echo " "

rm -rf /etc/asterisk
rm -rf /run/asterisk
rm -rf /var/{lib,log,spool}/asterisk

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