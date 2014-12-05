FROM x3tech/nginx-hhvm:latest

WORKDIR /var/www

ENV X3CS_TITLE x3CS
ENV X3CS_THEME default
ENV X3CS_RESET_PASSWORD dev

ADD contrib/nginx.conf /etc/nginx/nginx.conf
ADD contrib/hhvm-run.sh /etc/service/hhvm/run

ADD . /var/www
RUN chown www-data:www-data /var/www/public
