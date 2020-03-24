#!/bin/bash
set -ex

add-apt-repository ppa:git-core/ppa
# Must use a newer hg version than default on Xenial and lower
UBUNTU_VERSION=$(lsb_release -r -s)
if [ $(echo $UBUNTU_VERSION | cut -c1-2) -lt "18" ]; then
  add-apt-repository ppa:mercurial-ppa/releases
fi
apt-get update
apt-get install -y \
  git \
  mercurial \
  locales

locale-gen en_US.UTF-8
export LC_ALL=en_US.UTF-8

git --version
hg --version
hhvm --version

curl https://getcomposer.org/installer | php -- /dev/stdin --install-dir=/usr/local/bin --filename=composer

cd /var/source
php /usr/local/bin/composer install

hh_server --check $(pwd)
vendor/bin/hacktest tests/
