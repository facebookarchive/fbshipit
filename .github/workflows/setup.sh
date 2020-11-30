#!/bin/bash
set -ex

add-apt-repository ppa:git-core/ppa
apt-get update
apt-get install -y \
  git \
  mercurial \
  locales

locale-gen en_US.UTF-8
echo "LC_ALL=en_US.UTF-8" >> $GITHUB_ENV

git --version
hg --version
hhvm --version

curl https://getcomposer.org/installer | php -- /dev/stdin --install-dir=/usr/local/bin --filename=composer

php /usr/local/bin/composer install
