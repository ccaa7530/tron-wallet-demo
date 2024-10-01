#!/bin/bash

#######
# 1. 安裝laravel sail php8.3 docker container
# 2. 執行composer install 因為有些套件php版本不相容問題直接先 `--ignore-platform-reqs` 忽略
# 3. 執行sail:install指令安裝sail
#######
docker run --rm \
    --pull=always \
    -v "$(pwd)":/opt \
    -w /opt \
    laravelsail/php83-composer:latest \
    bash -c "composer install --ignore-platform-reqs && php ./artisan sail:install --with=mysql"

# sail build 安裝docker容器並使用--no-cache以免其他問題
./vendor/bin/sail build --no-cache

# 更改專案USER權限
sudo chown -R $USER .


docker run --rm --pull=always -v "$(pwd)":/opt -w /opt laravelsail/php82-composer:latest bash -c "composer install --ignore-platform-reqs"