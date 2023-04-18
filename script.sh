#!/bin/bash
#set -xe
if [ -f "/tmp/MDLinkCheck_Report.csv" ]; then
  rm -rf /tmp/MDLinkCheck_Report.csv
  echo "Old /tmp/MDLinkCheck_Report.csv report file deleted!"
fi

if [ ! -f "./workspace/checkmdlinks.php" ]; then
  echo "checkmdlinks.php tool is not found!"
  exit 1
fi

echo "MDSymLink check inprogress"

for filedoc in $(find ./workspace/docs -type f -print | grep -i ".md$"); do
  sed -i -e '/^#/s/*//g' -e '/^#/s/_//g' -e '/^#/s/\`//g' "$filedoc"
  ./workspace/checkmdlinks.php --root=. "$filedoc"
done

echo "MDSymLink check completed!"

#Echo "Upload MDSymLink check report to artifactory"
#curl -sSf -H "X-JFrog-Art-Api:${CEDP_ARTIFACTORY_KEY}" -X PUT "https://na.artifactory.swg-devops.com/artifactory/cedp-generic-local/docsymlink/DocSymlinkReport-$(date +"%d-%b-%y").csv" -T /tmp/MDLinkCheck_Report.csv || echo "MDLinkCheck_Report.csv file not found"
