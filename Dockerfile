FROM x3tech/nginx-hhvm:latest

WORKDIR /var/www

ADD contrib/nginx.conf /etc/nginx/nginx.conf
ADD contrib/hhvm-run.sh /etc/service/hhvm/run

ADD . /var/www
RUN chown www-data:www-data /var/www/public
