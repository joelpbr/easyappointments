#!/bin/sh
set -eu
# Firebird 3+ cannot open the ODS 11 files produced by the original clinic program.
mkdir -p /opt/podologo
cd /opt/podologo
curl -fsSL https://github.com/FirebirdSQL/firebird/releases/download/R2_5_9/FirebirdCS-2.5.9.27139-0.amd64.tar.gz -o firebird.tar.gz
echo '59b1f64db56f50c94ee47babd1bf551cacebd0bdf7f668d6250d18a621390b4e  firebird.tar.gz' | sha256sum -c -
tar xzf firebird.tar.gz
tar xzf FirebirdCS-2.5.9.27139-0.amd64/buildroot.tar.gz
mv opt/firebird firebird
# Embedded Classic needs write access to its security database even for read-only data connections.
chown www-data:www-data firebird/security2.fdb
chmod 600 firebird/security2.fdb
mkdir -p locks
chown www-data:www-data locks
chmod 2770 locks
curl -fsSL https://archive.ubuntu.com/ubuntu/pool/universe/n/ncurses/libncurses5_6.3-2ubuntu0.3_amd64.deb -o ncurses.deb
curl -fsSL https://archive.ubuntu.com/ubuntu/pool/universe/n/ncurses/libtinfo5_6.3-2ubuntu0.3_amd64.deb -o tinfo.deb
echo '58da01991712c6856af0658dc5e9cb533c1a6854ab950055ade185e35bc9c276  ncurses.deb' | sha256sum -c -
echo '4df4288404108f1a156d014e8764a064e977e34e6d44931ab60451694c03c90d  tinfo.deb' | sha256sum -c -
dpkg-deb -x ncurses.deb deps
dpkg-deb -x tinfo.deb deps
python3 -m venv venv
venv/bin/pip install --no-cache-dir fdb==2.0.4
