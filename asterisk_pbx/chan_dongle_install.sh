

wget http://mirror.centos.org/centos/7/os/x86_64/Packages/usb_modeswitch-2.5.1-1.el7.x86_64.rpm
yum install -y usb_modeswitch-2.5.1-1.el7.x86_64.rpm

wget https://github.com/oleg-krv/asterisk-chan-dongle/archive/asterisk13.zip
unzip asterisk13.zip
cd asterisk13
aclocal && autoconf && automake -a
./configure --with-astversion=13.13.1
make 
make install
cp chan_dongle.so /usr/lib/asterisk/modules/
cp etc/dongle.conf /etc/asterisk
#