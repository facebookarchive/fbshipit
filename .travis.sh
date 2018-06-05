#!/bin/sh
set -ex

add-apt-repository ppa:git-core/ppa
add-apt-repository ppa:mercurial-ppa/releases
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

curl https://getcomposer.org/installer | hhvm -d hhvm.jit=0 --php -- /dev/stdin --install-dir=/usr/local/bin --filename=composer

cd /var/source
hhvm -d hhvm.jit=0 /usr/local/bin/composer install

hh_server --check $(pwd)
hhvm vendor/bin/phpunit
hhvm vendor/bin/hhast-lint
