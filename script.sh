#!/bin/bash
#set -xe
if [ -f "/tmp/MDLinkCheck_Report.csv" ]; then
  rm -rf /tmp/MDLinkCheck_Report.csv
  echo "Old /tmp/MDLinkCheck_Report.csv report file deleted!"
fi
#sudo apt-get update -y
#sudo apt-get install php7.4 -y
which php
php -version
#sudo yum install https://rpms.remirepo.net/enterprise/7/remi/x86_64/php74-php-7.4.29-1.el7.remi.x86_64.rpm -y
#rpm -Uvh remi-release*rpm
#yum --enablerepo=remi install php74-php -y

echo "Welcome to MDSymLink check!"
echo "Provide GitHub location for MDSymLink check"
read gitrepo

if [ ! -d "$gitrepo" ]; then
  echo "$gitrepo folder does not exits!"
  exit 1
fi

if [ ! -f "./catalog/official/tasks/mdlink-check/v1/checkmdlinks.php" ]; then
  echo "checkmdlinks.php tool is not found!"
  exit 1
fi

echo "MDSymLink check in progress for $gitrepo"

for filedoc in $(find $gitrepo -type f -print | grep -i ".md$"); do
  sed -i -e '/^#/s/*//g' -e '/^#/s/_//g' -e '/^#/s/\`//g' "$filedoc"
  ./catalog/official/tasks/mdlink-check/v1/checkmdlinks.php --root=. "$filedoc"
done

echo "MDSymLink check completed for $gitrepo repo."

#cp /tmp/MDLinkCheck_Report.csv $gitrepo/report
echo "\n"
echo "*************************MDSymLink repo check report for $gitrepo repo******************************"
echo "\n"

cat /tmp/MDLinkCheck_Report.csv

echo "**************************Thank you for using MDSymLink Check****************************************"

#Echo "Upload MDSymLink check report to artifactory"
#curl -sSf -H "X-JFrog-Art-Api:${CEDP_ARTIFACTORY_KEY}" -X PUT "https://na.artifactory.swg-devops.com/artifactory/cedp-generic-local/docsymlink/DocSymlinkReport-$(date +"%d-%b-%y").csv" -T /tmp/MDLinkCheck_Report.csv || echo "MDLinkCheck_Report.csv file not found"
