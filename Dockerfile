FROM keboola/base-php56

MAINTAINER Vojtech Kurka <vokurka@keboola.com>

ENV APP_VERSION 0.0.3

WORKDIR /home

RUN git clone https://github.com/vokurka/keboola-conductor-ex ./
RUN composer install --no-interaction
ENTRYPOINT php ./src/run.php --data=/data