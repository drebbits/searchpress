#!/usr/bin/env bash
if [ $# -lt 1 ]; then
  echo "usage: $0 <es-version>"
  exit 1
fi

sudo sysctl -w vm.max_map_count=262144
sudo sysctl -w fs.file-max=65536
killall java 2>/dev/null
which java
java -version
echo $JAVA_HOME

ES_VERSION=$1

setup_es() {
  download_url=$1
  mkdir /tmp/elasticsearch
  wget -O - $download_url | tar xz --directory=/tmp/elasticsearch --strip-components=1
}

start_es() {
  /tmp/elasticsearch/bin/elasticsearch $1 > /tmp/elasticsearch.log &
  sleep 10

  curl http://localhost:9200 && echo "ES is up" || (cat /tmp/elasticsearch.log && exit 1)
}

if [[ "$ES_VERSION" == 5.* ]]; then
  setup_es https://artifacts.elastic.co/downloads/elasticsearch/elasticsearch-${ES_VERSION}.tar.gz
elif [[ "$ES_VERSION" == 2.* ]]; then
  setup_es https://download.elastic.co/elasticsearch/release/org/elasticsearch/distribution/tar/elasticsearch/${ES_VERSION}/elasticsearch-${ES_VERSION}.tar.gz
elif [[ "$ES_VERSION" == 1.* ]]; then
  setup_es https://download.elastic.co/elasticsearch/elasticsearch/elasticsearch-${ES_VERSION}.tar.gz
fi

# java_home='/usr/lib/jvm/java-8-oracle'
if [[ "$ES_VERSION" == 5.* ]]; then
  start_es '-Edefault.path.repo=/tmp'
else
  start_es '-Des.path.repo=/tmp'
fi
